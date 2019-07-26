<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
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
        FileSystemInterface $fileSystem,
        ConfigFactoryInterface $configFactory,
        ModelClassGenerator $modelClassGenerator
    ) {
        parent::__construct($entityTypeManager, $entityFieldManager, $fileSystem, $configFactory);
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

        $variableName = $this->toCamelCase($modelClass->getShortName());
        $templateName = str_replace('_', '-', $bundle);

        if ($isSingle) {
            $templatePath = "single.{$templateName}";
        } else {
            $templatePath = "{$this->toKebabCase($entityType)}.{$templateName}.detail";
        }

        $baseClass = new \ReflectionClass($this->baseClass);
        $namespace = $this->builderFactory->namespace($namespaceName);
        $class = $this->builderFactory->class($className);

        $namespace->addStmt($this->builderFactory->use($baseClass->getName()));
        $namespace->addStmt($this->builderFactory->use($modelClass->getName()));
        $class->extend($baseClass->getShortName());

        $method = $this->builderFactory->method('show');
        $method->addParam($this->builderFactory->param($variableName)
            ->setTypeHint($modelClass->getShortName()));
        $method->addStmt(
            $this->parseExpression("return \$this->view('{$templatePath}', compact('{$variableName}'));")
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
        $label = $this->toPascalCase($entityType);
        $label = $this->stripInvalidCharacters($label);
        return implode('\\', ['Drupal', $module, 'Controller', $label]);
    }

    public function buildClassName(string $entityType, string $bundle, string $module, bool $shortName = false)
    {
        $label = $this->toPascalCase($bundle);
        $label = $this->stripInvalidCharacters($label);
        $label .= 'Controller';

        if ($shortName) {
            return $label;
        }

        return sprintf('%s\%s', $this->buildNamespaceName($entityType, $module), $label);
    }
}
