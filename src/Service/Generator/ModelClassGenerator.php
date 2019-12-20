<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\wmmodel\Factory\ModelFactory;
use Drupal\wmscaffold\ModelMethodGeneratorInterface;
use Drupal\wmscaffold\ModelMethodGeneratorManager;
use PhpParser\Builder;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class ModelClassGenerator extends ClassGeneratorBase
{
    /** @var ModelFactory */
    protected $modelFactory;
    /** @var ModelMethodGeneratorManager */
    protected $modelMethodGeneratorManager;

    /** @var array */
    protected $baseClasses;
    /** @var array */
    protected $fieldsToIgnore;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager,
        FileSystemInterface $fileSystem,
        ConfigFactoryInterface $configFactory,
        ModelFactory $modelFactory,
        ModelMethodGeneratorManager $modelMethodGeneratorManager
    ) {
        parent::__construct($entityTypeManager, $entityFieldManager, $fileSystem, $configFactory);
        $this->modelFactory = $modelFactory;
        $this->modelMethodGeneratorManager = $modelMethodGeneratorManager;

        $this->baseClasses = $this->config->get('generators.model.baseClasses') ?? [];
        $this->fieldsToIgnore = $this->config->get('generators.model.fieldsToIgnore') ?? [];
    }

    public function generateNew(string $entityType, string $bundle, string $module): Namespace_
    {
        // Make sure the wmmodel class mapping is up to date
        $this->modelFactory->rebuildMapping();

        $className = $this->buildClassName($entityType, $bundle, $module, true);
        $namespaceName = $this->buildNamespaceName($entityType, $module);

        $baseClass = new \ReflectionClass($this->baseClasses[$entityType]);
        $namespace = $this->builderFactory->namespace($namespaceName);
        $class = $this->builderFactory->class($className);

        $namespace->addStmt($this->builderFactory->use($baseClass->getName()));
        $class->extend($baseClass->getShortName());

        foreach ($this->getCustomFields($entityType, $bundle) as $field) {
            if (!$result = $this->buildFieldGetter($field)) {
                continue;
            }

            [$method, $uses] = $result;

            $class->addStmt($method);
            $namespace->addStmts($uses);
        }

        $namespace->addStmt($class);

        $node = $namespace->getNode();
        $this->cleanUseStatements($node);

        return $node;
    }

    public function generateExisting(string $entityType, string $bundle): ?Namespace_
    {
        return $this->appendFieldGettersToExistingModel($entityType, $bundle, $this->getCustomFields($entityType, $bundle));
    }

    public function appendFieldGettersToExistingModel(string $entityType, string $bundle, array $fields): ?Namespace_
    {
        // Make sure the wmmodel class mapping is up to date
        $this->modelFactory->rebuildMapping();

        $definition = $this->entityTypeManager->getDefinition($entityType);
        $className = $this->modelFactory->getClassName($definition, $bundle);

        // Only edit bundle models
        if ($className === $definition->getClass()) {
            return null;
        }

        // Must have an existing class
        try {
            $class = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            return null;
        }

        // Parse the existing file & extract the namespace node
        $parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7);
        $input = $parser->parse(file_get_contents($class->getFileName()));

        if (empty($input)) {
            return null;
        }

        /** @var Namespace_ $namespace */
        $namespace = $input[0];

        // Add the new method to the class
        foreach ($namespace->stmts as $i => $statement) {
            if (!$statement instanceof Class_) {
                continue;
            }

            foreach ($fields as $field) {
                if (!$result = $this->buildFieldGetter($field)) {
                    continue;
                }

                [$method, $uses] = $result;

                // Check for existing methods with the same body
                // and don't create a new method if any are found
                $existingMethods = array_filter(
                    $statement->stmts,
                    function (Node\Stmt $existingStmt) use ($method) {
                        if (!$existingStmt instanceof Node\Stmt\ClassMethod) {
                            return false;
                        }
                        $existingStmt = clone $existingStmt;
                        $existingStmt->name = $method->getNode()->name;
                        return $this->compareNodes($existingStmt, $method->getNode());
                    }
                );

                if (!empty($existingMethods)) {
                    continue;
                }

                // Check for existing methods with the same name
                // and rename the new method if any are found
                $getterMethodName = $this->buildFieldGetterName($field);
                $index = 1;

                while ($class->hasMethod($getterMethodName)) {
                    $getterMethodName = $this->buildFieldGetterName($field) . $index;
                    $index++;
                }

                $methodNode = $method->getNode();
                $methodNode->name = $getterMethodName;

                // Add statements
                foreach ($uses as $use) {
                    if ($use instanceof Builder\Use_) {
                        $namespace->stmts[] = $use->getNode();
                    } elseif ($use instanceof Use_) {
                        $namespace->stmts[] = $use;
                    }
                }

                $namespace->stmts[$i]->stmts[] = $methodNode;
            }
        }

        $this->cleanUseStatements($namespace);

        return $namespace;
    }

    public function buildModelPath(string $entityType, string $bundle, string $module)
    {
        $className = $this->buildClassName($entityType, $bundle, $module);

        return sprintf(
            '%s/src/%s.php',
            $this->fileSystem->realpath(
                drupal_get_path('module', $module)
            ),
            implode('/', array_slice(explode('\\', $className), 2))
        );
    }

    public function buildNamespaceName(string $entityType, string $module)
    {
        $label = $this->toPascalCase($entityType);
        $label = $this->stripInvalidCharacters($label);

        return implode('\\', ['Drupal', $module, 'Entity', $label]);
    }

    public function buildClassName(string $entityType, string $bundle, string $module, bool $shortName = false)
    {
        $label = $this->toPascalCase($bundle);
        $label = $this->stripInvalidCharacters($label);

        if ($this->isReservedKeyword($label)) {
            $label .= 'Model';
        }

        if ($shortName) {
            return $this->toPascalCase($label);
        }

        return sprintf('%s\%s', $this->buildNamespaceName($entityType, $module), $label);
    }

    public function buildFieldGetterName(FieldDefinitionInterface $field)
    {
        $label = $field->getLabel();
        $label = $this->toPascalCase($label);
        $label = $this->stripInvalidCharacters($label);

        return 'get' . $label;
    }

    protected function buildFieldGetter($field)
    {
        if (!$field instanceof FieldDefinitionInterface) {
            return false;
        }

        if (in_array($field->getName(), $this->fieldsToIgnore)) {
            return false;
        }

        $getterMethodName = $this->buildFieldGetterName($field);
        $id = $field->getType();

        $method = $this->builderFactory->method($getterMethodName)->makePublic();
        $uses = [];

        if ($this->modelMethodGeneratorManager->hasDefinition($id)) {
            /** @var ModelMethodGeneratorInterface $generator */
            $generator = $this->modelMethodGeneratorManager->createInstance($id);
            $generator->buildGetter($field, $method, $uses);
        } else {
            throw new \Exception("No ModelMethodGenerator implementation for field type $id");
        }

        return [$method, $uses];
    }

    /** @return FieldDefinitionInterface[] */
    protected function getCustomFields(string $entityType, string $bundle)
    {
        return array_filter(
            $this->entityFieldManager->getFieldDefinitions($entityType, $bundle),
            function (FieldDefinitionInterface $field) {
                return $field->getFieldStorageDefinition()->getProvider() === 'field';
            }
        );
    }

    protected function compareNodes()
    {
        if (func_num_args() < 2) {
            return true;
        }

        $doCompare = function (Node $node) {
            // Remove attributes
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new class() extends NodeVisitorAbstract {
                public function leaveNode(Node $node)
                {
                    $node->setAttributes([]);

                    if ($node instanceof Node\Stmt\ClassMethod) {
                        $node->flags = [];
                    }
                }
            });

            $nodes = $traverser->traverse([$node]);
            $node = $nodes[0];

            // Convert to array
            $node = ['nodeType' => $node->getType()] + get_object_vars($node);

            // Sort keys
            wmscaffold_ksort_recursive($node);

            // Convert to string
            $node = json_encode($node);

            return $node;
        };

        // Prevent side effects
        $args = array_map(
            function ($arg) {
                return clone $arg;
            },
            func_get_args()
        );

        $first = array_shift($args);

        foreach ($args as $arg) {
            $results = array_map($doCompare, [$first, $arg]);

            if ($results[0] === $results[1]) {
                return true;
            }
        }

        return false;
    }
}
