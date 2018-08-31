<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\wmmodel\Entity\EntityTypeBundleInfo;
use PhpParser\Node\Stmt\Namespace_;

class ControllerClassGenerator extends ClassGeneratorBase
{
    /** @var ModelClassGenerator */
    protected $modelClassGenerator;

    /** @var string */
    protected $baseClass;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager,
        EntityTypeBundleInfo $entityTypeBundleInfo,
        FileSystemInterface $fileSystem,
        ConfigFactoryInterface $configFactory,
        ModelClassGenerator $modelClassGenerator
    ) {
        parent::__construct($entityTypeManager, $entityFieldManager, $entityTypeBundleInfo, $fileSystem, $configFactory);
        $this->modelClassGenerator = $modelClassGenerator;

        $this->baseClass = $this->config->get('generators.controller.baseClass');
    }

    public function generateNew(string $entityType, string $bundle, string $module): Namespace_
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
            $this->fileSystem->realpath(
                drupal_get_path('module', $module)
            ),
            implode('/', array_slice(explode('\\', $className), 2))
        );
    }

    public function buildNamespaceName(string $entityType, string $module)
    {
        $label = $this->stripInvalidCharacters($entityType);
        return implode('\\', ['Drupal', $module, 'Controller', $this->toPascalCase($label)]);
    }

    public function buildClassName(string $entityType, string $bundle, string $module, bool $shortName = false)
    {
        $label = $this->entityTypeBundleInfo->getBundleInfo($entityType)[$bundle]['label'] ?? $bundle;
        $label = $this->stripInvalidCharacters($label);
        $label .= 'Controller';

        if ($shortName) {
            return $this->toPascalCase($label);
        }

        return sprintf('%s\%s', $this->buildNamespaceName($entityType, $module), $this->toPascalCase($label));
    }
}
