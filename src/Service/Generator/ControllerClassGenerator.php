<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\wmmodel\Entity\EntityTypeBundleInfo;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;

class ControllerClassGenerator
{
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityFieldManager */
    protected $entityFieldManager;
    /** @var EntityTypeBundleInfo */
    protected $entityTypeBundleInfo;
    /** @var ModelClassGenerator */
    protected $modelClassGenerator;

    /** @var ImmutableConfig */
    protected $config;
    /** @var BuilderFactory */
    protected $builderFactory;
    /** @var ParserFactory */
    protected $parserFactory;

    /** @var string */
    protected $baseClass;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        ModelClassGenerator $modelClassGenerator,
        ConfigFactoryInterface $configFactory
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
        $this->entityTypeBundleInfo = $entityTypeBundleInfo;
        $this->modelClassGenerator = $modelClassGenerator;

        $this->config = $configFactory->get('wmscaffold.settings');
        $this->builderFactory = new BuilderFactory();
        $this->parserFactory = new ParserFactory();

        $this->baseClass = $this->config->get('generators.controller.baseClass');
    }

    public function generateNew(string $entityType, string $bundle, string $module): \PhpParser\Node\Stmt\Namespace_
    {
        $className = $this->buildClassName($entityType, $bundle, $module, true);
        $namespaceName = $this->buildNamespaceName($entityType, $module);
        $modelClass = new \ReflectionClass(
            $this->modelClassGenerator->buildClassName($entityType, $bundle, $module)
        );

        // Determine entity type folder name
        $bundleEntityType = $this->entityTypeManager
            ->getStorage($entityType)
            ->getEntityType()
            ->getBundleEntityType();
        $bundleType = $this->entityTypeManager
            ->getStorage($bundleEntityType)->load($bundle);
        $isSingle = $bundleType && $bundleType->getThirdPartySetting('wmsingles', 'isSingle');
        $entityTypeFolder = $this->toKebabCase($isSingle ? 'single' : $entityType);

        $baseClass = new \ReflectionClass($this->baseClass);
        $namespace = $this->builderFactory->namespace($namespaceName);
        $class = $this->builderFactory->class($className);

        $namespace->addStmt($this->builderFactory->use($baseClass->getName()));
        $namespace->addStmt($this->builderFactory->use($modelClass->getName()));
        $class->extend($baseClass->getShortName());

        $method = $this->builderFactory->method('show');
        $variableName = $this->toCamelCase($modelClass->getShortName());
        $method->addParam($this->builderFactory->param($variableName)
            ->setTypeHint($modelClass->getShortName()));
        $method->addStmt(
            $this->parseExpression(
                "return \$this->view('{$entityTypeFolder}.{$bundle}.detail', compact('{$variableName}'));"
            )
        );

        $class->addStmt($method);
        $namespace->addStmt($class);

        $node = $namespace->getNode();
        $this->cleanUseStatements($node);

        return $node;
    }

    public function buildControllerPath(string $entityType, string $bundle, string $module)
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
        return implode('\\', ['Drupal', $module, 'Controller', $this->toPascalCase($entityType)]);
    }

    public function buildClassName(string $entityType, string $bundle, string $module, bool $shortName = false)
    {
        $label = $this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundle]['label'] ?? $bundle;
        $label .= 'Controller';

        if ($shortName) {
            return $this->toPascalCase($label);
        }

        return sprintf('%s\%s', $this->buildNamespaceName($entityType, $module), $this->toPascalCase($label));
    }

    protected function toCamelCase(string $input)
    {
        return lcfirst($this->toPascalCase($input));
    }

    protected function toPascalCase(string $input)
    {
        $input = preg_replace('/\-|\s/', '_', $input);
        return str_replace('_', '', ucwords($input, '_'));
    }

    protected function toKebabCase(string $input)
    {
        $input = preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0', $input);
        return ltrim(strtolower($input), '-');
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
}
