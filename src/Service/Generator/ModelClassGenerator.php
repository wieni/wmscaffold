<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmmedia\Plugin\Field\FieldType\MediaImageExtras;
use Drupal\wmmodel\Entity\EntityTypeBundleInfo;
use Drupal\wmmodel\Factory\ModelFactory;
use PhpParser\Builder;
use PhpParser\Builder\Method;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

class ModelClassGenerator
{
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var ModelFactory */
    protected $modelFactory;

    /** @var ImmutableConfig */
    protected $config;
    /** @var BuilderFactory */
    protected $builderFactory;
    /** @var ParserFactory */
    protected $parserFactory;

    /** @var array */
    protected $baseClasses;
    /** @var array */
    protected $fieldsToIgnore;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ModelFactory $modelFactory,
        ConfigFactoryInterface $configFactory
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->modelFactory = $modelFactory;

        $this->config = $configFactory->get('wmscaffold.settings');
        $this->builderFactory = new BuilderFactory();
        $this->parserFactory = new ParserFactory();

        $this->baseClasses = $this->config->get('generators.model.baseClasses');
        $this->fieldsToIgnore = $this->config->get('generators.model.fieldsToIgnore');
    }

    public function generateNew(string $entityType, string $bundle, string $module): \PhpParser\Node\Stmt\Namespace_
    {
        $className = $this->buildClassName($entityType, $bundle, $module, true);
        $namespaceName = $this->buildNamespaceName($entityType, $module);

        $baseClass = new \ReflectionClass($this->baseClasses[$entityType]);
        $namespace = $this->builderFactory->namespace($namespaceName);
        $class = $this->builderFactory->class($className);

        $namespace->addStmt($this->builderFactory->use($baseClass->getName()));
        $class->extend($baseClass->getShortName());

        foreach ($this->getCustomFields($entityType, $bundle) as $field) {
            if (!$result = $this->buildFieldGetter($entityType, $bundle, $field, $module)) {
                continue;
            }

            list($method, $uses) = $result;

            $class->addStmt($method);
            $namespace->addStmts($uses);
        }

        $namespace->addStmt($class);

        $node = $namespace->getNode();
        $this->cleanUseStatements($node);

        return $node;
    }

    public function generateExisting(string $entityType, string $bundle, string $module)
    {
        return $this->appendFieldGettersToExistingModel($entityType, $bundle, $module, $this->getCustomFields($entityType, $bundle));
    }

    /**
     * @return bool|\PhpParser\Node\Stmt\Namespace_
     */
    public function appendFieldGettersToExistingModel(string $entityType, string $bundle, string $module, array $fields)
    {
        $className = $this->buildClassName($entityType, $bundle, $module);

        if (!isset($this->baseClasses[$entityType])) {
            throw new \Exception(
                sprintf('Unknown base class for class %s', $className)
            );
        }

        // Must have an existing class
        try {
            $baseClass = new \ReflectionClass($this->baseClasses[$entityType]);
            $class = new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            return false;
        }

        // Parse the existing file & extract the namespace node
        $parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7);
        $input = $parser->parse(file_get_contents($class->getFileName()));

        if (empty($input)) {
            return false;
        }

        /** @var \PhpParser\Node\Stmt\Namespace_ $namespace */
        $namespace = $input[0];

        // Add the new method to the class
        foreach ($namespace->stmts as $i => $statement) {
            if (!$statement instanceof Class_) {
                continue;
            }

            foreach ($fields as $field) {
                if (!$result = $this->buildFieldGetter($entityType, $bundle, $field, $module)) {
                    continue;
                }

                list($method, $uses) = $result;

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

                while ($class->hasMethod($getterMethodName) || $baseClass->hasMethod($getterMethodName)) {
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
            \Drupal::service('file_system')->realpath(
                drupal_get_path('module', $module)
            ),
            implode('/', array_slice(explode('\\', $className), 2))
        );
    }

    public function buildNamespaceName(string $entityType, string $module)
    {
        return implode('\\', ['Drupal', $module, 'Entity', $this->toPascalCase($entityType)]);
    }

    public function buildClassName(string $entityType, string $bundle, string $module, bool $shortName = false)
    {
        $label = $this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundle]['label'] ?? $bundle;

        if ($shortName) {
            return $this->toPascalCase($label);
        }

        return sprintf('%s\%s', $this->buildNamespaceName($entityType, $module), $this->toPascalCase($label));
    }

    /** @param FieldDefinitionInterface|string $field */
    public function buildFieldGetterName($field)
    {
        return 'get' . $this->toPascalCase($field->getLabel());
    }

    /** @param FieldDefinitionInterface|string $field */
    protected function buildFieldGetter(string $entityType, string $bundle, $field, $module = null)
    {
        if (!$field instanceof FieldDefinitionInterface) {
            return false;
        }

        if (in_array($field->getName(), $this->fieldsToIgnore)) {
            return false;
        }

        $getterMethodName = $this->buildFieldGetterName($field);
        $generatorMethodName = sprintf('build%sMethod', $this->toPascalCase($field->getType()));

        $method = $this->builderFactory->method($getterMethodName)->makePublic();
        $uses = [];

        if (method_exists($this, $generatorMethodName)) {
            $this->{$generatorMethodName}($field, $method, $uses);
        } else {
            echo "No field getter builder method '$generatorMethodName'" . PHP_EOL;
        }

        return [$method, $uses];
    }

    protected function buildEntityReferenceMethod(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        $expression = $this->isFieldMultiple($field)
            ? sprintf('return $this->get(\'%s\')->referencedEntities();', $field->getName())
            : sprintf('return $this->get(\'%s\')->entity;', $field->getName());

        $method->addStmt($this->parseExpression($expression));

        if (!$fieldModelClass = $this->getFieldModelClass($field)) {
            return;
        }

        $fieldModelClass = new \ReflectionClass($fieldModelClass);
        $uses[] = $this->builderFactory->use($fieldModelClass->getName());

        if ($this->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$fieldModelClass->getShortName()}[] */");
        } else if ($field->isRequired()) {
            $method->setReturnType($fieldModelClass->getShortName());
        } else {
            $method->setDocComment("/** @return {$fieldModelClass->getShortName()}|null */");
        }
    }

    protected function buildBooleanMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('bool', $field, $method);
    }

    protected function buildIntegerMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('int', $field, $method);
    }

    protected function buildListIntegerMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('int', $field, $method);
    }

    protected function buildFloatMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('float', $field, $method);
    }

    protected function buildListFloatMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('float', $field, $method);
    }

    protected function buildStringMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('string', $field, $method);
    }

    protected function buildStringLongMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('string', $field, $method);
    }

    protected function buildEmailMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('string', $field, $method);
    }

    protected function buildTelephoneMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('string', $field, $method);
    }

    protected function buildTextMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('string', $field, $method);
    }

    protected function buildTextLongMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('string', $field, $method);
    }

    protected function buildListStringMethod(FieldDefinitionInterface $field, Method $method)
    {
        $this->buildScalarMethod('string', $field, $method);
    }

    protected function buildWmmediaMediaImageExtrasMethod(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        $uses[] = $this->builderFactory->use(MediaImageExtras::class);
        $this->buildFieldItemListMethod(MediaImageExtras::class, $field, $method);
    }

    protected function buildLinkMethod(FieldDefinitionInterface $field, Method $method)
    {
        $method->setReturnType('array');
        $method->addStmt(
            $this->parseExpression(
                sprintf('return $this->formatLink(\'%s\');', $field->getName())
            )
        );
    }

    protected function buildDatetimeMethod(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        $className = \DateTime::class;
        $shortName = (new \ReflectionClass($className))->getShortName();
        $uses[] = $this->builderFactory->use($className);

        $method->addStmt(
            $this->parseExpression(
                sprintf('return $this->toDateTime(\'%s\');', $field->getName())
            )
        );

        if ($field->isRequired()) {
            $method->setReturnType($className);
        } else {
            $method->setDocComment("/** @return {$shortName}|null */");
        }
    }

    protected function buildScalarMethod(string $scalarType, FieldDefinitionInterface $field, Method $method)
    {
        if ($this->isFieldMultiple($field)) {
            $expression = sprintf('return (array) $this->get(\'%s\')->value;', $field->getName());
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$scalarType}[] */");
        } else {
            $expression = sprintf('return (%s) $this->get(\'%s\')->value;', $scalarType, $field->getName());
            $method->setReturnType($scalarType);
        }

        $method->addStmt($this->parseExpression($expression));
    }

    protected function buildFieldItemListMethod(string $className, FieldDefinitionInterface $field, Method $method)
    {
        if ($this->isFieldMultiple($field)) {
            $expression = sprintf('return $this->get(\'%s\');', $field->getName());
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$className}[] */");
        } else {
            $expression = sprintf('return $this->get(\'%s\')->first();', $field->getName());
            $shortName = (new \ReflectionClass($className))->getShortName();

            if ($field->isRequired()) {
                $method->setReturnType($className);
            } else {
                $method->setDocComment("/** @return {$shortName}|null */");
            }
        }

        $method->addStmt($this->parseExpression($expression));
    }

    protected function toPascalCase(string $input)
    {
        $input = preg_replace('/\-|\s/', '_', $input);
        return str_replace('_', '', ucwords($input, '_'));
    }

    protected function getFieldModelClass(FieldDefinitionInterface $field)
    {
        $targetType = $field->getFieldStorageDefinition()->getSetting('target_type');
        $targetBundles = $field->getSetting('handler_settings')['target_bundles'];
        $targetBundle = reset($targetBundles);

        return $this->modelFactory->getClassName(
            $this->entityTypeManager->getDefinition($targetType),
            $targetBundle
        );
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

    protected function isFieldMultiple(FieldDefinitionInterface $field)
    {
        return $field->getFieldStorageDefinition()->getCardinality() !== 1;
    }

    protected function parseExpression(string $expression)
    {
        $parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7);
        $statements = $parser->parse('<?php ' . $expression . ';');
        return $statements[0];
    }

    protected function cleanUseStatements(\PhpParser\Node\Stmt\Namespace_ $namespace)
    {
        $uses = [];

        // Deduplicate
        foreach ($namespace->stmts as $i => $statement) {
            if (!$statement instanceof Use_) {
                continue;
            }

            foreach ($statement->uses as $j => $use) {
                $name = (string) $use->name;
                if (in_array($name, $uses)) {
                    unset($statement->uses[$j]);
                    continue;
                }
                $uses[] = $name;
            }

            if (empty($statement->uses)) {
                unset($namespace->stmts[$i]);
            }
        }

        // Sort
        usort(
            $namespace->stmts,
            function (Node\Stmt $a, Node\Stmt $b) {
                if ($a instanceof Class_ && $b instanceof Use_) {
                    return 1;
                }

                return -1;
            }
        );

        return $namespace;
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
