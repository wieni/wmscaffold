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
use Drupal\wmscaffold\PhpParser\NodeVisitor\ClassMethodNormalizer;
use Drupal\wmscaffold\Service\Helper\IdentifierNaming;
use Drupal\wmscaffold\Service\Helper\StringCapitalisation;
use PhpParser\Builder;
use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
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

        $this->baseClasses = $this->config->get('generators.model.base_classes') ?? [];
        $this->fieldsToIgnore = $this->config->get('generators.model.fields_to_ignore') ?? [];
    }

    public function generateNew(string $entityType, string $bundle, string $module): Stmt\Namespace_
    {
        // Make sure the wmmodel class mapping is up to date
        $this->modelFactory->rebuildMapping();

        $className = $this->buildClassName($entityType, $bundle, $module, true);
        $namespaceName = $this->buildNamespaceName($entityType, $module);

        $baseClass = new \ReflectionClass($this->getBaseClass($entityType));
        $namespace = $this->builderFactory->namespace($namespaceName);
        $class = $this->builderFactory->class($className);

        $use = $this->builderFactory->use($baseClass->getName());
        if ($className === $baseClass->getShortName()) {
            $alias = $className . 'Base';
            $namespace->addStmt($use->as($alias));
            $class->extend($alias);
        } else {
            $namespace->addStmt($use);
            $class->extend($baseClass->getShortName());
        }

        foreach ($this->getCustomFields($entityType, $bundle) as $field) {
            if (!$result = $this->buildFieldGetter($field)) {
                continue;
            }

            [$method, $uses] = $result;

            $class->addStmt($method);
            $namespace->addStmts($uses);
        }

        $classNode = $class->getNode();
        $docComment = new Comment\Doc(sprintf(<<<EOT
/**
 * @Model(
 *     entity_type = "%s",
 *     bundle = "%s",
 * )
 */
EOT
        , $entityType, $bundle));
        $classNode->setDocComment($docComment);

        $namespace->addStmt($classNode);

        $namespaceNode = $namespace->getNode();
        $this->cleanUseStatements($namespaceNode);

        return $namespaceNode;
    }

    public function generateExisting(string $entityType, string $bundle): ?Stmt\Namespace_
    {
        return $this->appendFieldGettersToExistingModel($entityType, $bundle, $this->getCustomFields($entityType, $bundle));
    }

    public function appendFieldGettersToExistingModel(string $entityType, string $bundle, array $fields): ?Stmt\Namespace_
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

        /** @var Stmt\Namespace_ $namespace */
        $namespace = $input[0];

        // Add the new method to the class
        foreach ($namespace->stmts as $i => $statement) {
            if (!$statement instanceof Stmt\Class_) {
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
                    $statement->getMethods(),
                    function (Stmt\ClassMethod $existingStmt) use ($method): bool {
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
                    } elseif ($use instanceof Stmt\Use_) {
                        $namespace->stmts[] = $use;
                    }
                }

                $namespace->stmts[$i]->stmts[] = $methodNode;
            }
        }

        $this->cleanUseStatements($namespace);

        return $namespace;
    }

    public function buildModelPath(string $entityType, string $bundle, string $module): string
    {
        $className = $this->buildClassName($entityType, $bundle, $module);
        $parts = array_slice(explode('\\', $className), 2);

        return sprintf(
            '%s/src/%s.php',
            $this->fileSystem->realpath(
                drupal_get_path('module', $module)
            ),
            implode('/', $parts)
        );
    }

    protected function buildNamespaceName(string $entityType, string $module): string
    {
        $namespacePattern = $this->config->get('generators.model.namespace_pattern')
            ?? 'Drupal\{module}\Entity\{entityType}';

        $entityType = StringCapitalisation::toPascalCase($entityType);
        $entityType = IdentifierNaming::stripInvalidCharacters($entityType);

        return str_replace(
            ['{module}', '{entityType}'],
            [$module, $entityType],
            $namespacePattern
        );
    }

    protected function buildClassName(string $entityType, string $bundle, string $module, bool $shortName = false): string
    {
        $label = StringCapitalisation::toPascalCase($bundle);
        $label = IdentifierNaming::stripInvalidCharacters($label);

        if (IdentifierNaming::isReservedKeyword($label)) {
            $label .= 'Model';
        }

        if ($shortName) {
            return StringCapitalisation::toPascalCase($label);
        }

        return sprintf('%s\%s', $this->buildNamespaceName($entityType, $module), $label);
    }

    protected function buildFieldGetterName(FieldDefinitionInterface $field): string
    {
        $source = $this->config->get('generators.model.field_getter_name_source') ?? 'label';

        if ($source === 'name') {
            $label = str_replace(
                [sprintf('field_%s', $field->getTargetBundle()), 'field_'],
                '',
                $field->getName()
            );
        } else {
            $label = $field->getLabel();
        }

        $label = StringCapitalisation::toPascalCase($label);
        $label = IdentifierNaming::stripInvalidCharacters($label);

        return 'get' . $label;
    }

    protected function buildFieldGetter($field): ?array
    {
        if (!$field instanceof FieldDefinitionInterface) {
            return null;
        }

        if (in_array($field->getName(), $this->fieldsToIgnore, true)) {
            return null;
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
            throw new \Exception(sprintf('No ModelMethodGenerator implementation for field type %s', $id));
        }

        return [$method, $uses];
    }

    protected function getBaseClass(string $entityTypeId): ?string
    {
        if (isset($this->baseClasses[$entityTypeId])) {
            return $this->baseClasses[$entityTypeId];
        }

        $definition = $this->entityTypeManager->getDefinition($entityTypeId, false);

        if ($definition) {
            return $definition->getOriginalClass();
        }

        return null;
    }

    /** @return FieldDefinitionInterface[] */
    protected function getCustomFields(string $entityType, string $bundle): array
    {
        return array_filter(
            $this->entityFieldManager->getFieldDefinitions($entityType, $bundle),
            static function (FieldDefinitionInterface $field): bool {
                return $field->getFieldStorageDefinition()->getProvider() === 'field';
            }
        );
    }

    protected function compareNodes(): bool
    {
        if (func_num_args() < 2) {
            return true;
        }

        $toString = function (Node $node) {
            // Remove attributes
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new ClassMethodNormalizer());

            $nodes = $traverser->traverse([$node]);
            $node = $nodes[0];

            // Convert to array
            $node = $node->jsonSerialize();

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
            $results = array_map($toString, [$first, $arg]);

            if ($results[0] === $results[1]) {
                return true;
            }
        }

        return false;
    }
}
