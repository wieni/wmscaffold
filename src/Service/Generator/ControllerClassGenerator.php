<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\wmmodel\Factory\ModelFactory;
use Drupal\wmscaffold\Service\Helper\IdentifierNaming;
use Drupal\wmscaffold\Service\Helper\StringCapitalisation;
use PhpParser\Node\Stmt;

class ControllerClassGenerator extends ClassGeneratorBase
{
    /** @var ModelFactory */
    protected $modelFactory;

    /** @var string */
    protected $baseClass;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager,
        FileSystemInterface $fileSystem,
        ConfigFactoryInterface $configFactory,
        ModelFactory $modelFactory
    ) {
        parent::__construct($entityTypeManager, $entityFieldManager, $fileSystem, $configFactory);
        $this->modelFactory = $modelFactory;

        $this->baseClass = $this->config->get('generators.controller.baseClass');
    }

    public function generateNew(string $entityType, string $bundle, string $module): Stmt\Namespace_
    {
        $className = $this->buildClassName($entityType, $bundle, $module, true);
        $namespaceName = $this->buildNamespaceName($entityType, $module);
        $definition = $this->entityTypeManager->getDefinition($entityType);
        $modelClass = new \ReflectionClass(
            $this->modelFactory->getClassName($definition, $bundle)
        );

        // Determine entity type folder name
        $bundleEntityType = $this->entityTypeManager
            ->getStorage($entityType)
            ->getEntityType()
            ->getBundleEntityType();
        $bundleType = $this->entityTypeManager
            ->getStorage($bundleEntityType)->load($bundle);
        $isSingle = $bundleType && $bundleType->getThirdPartySetting('wmsingles', 'isSingle');

        $variableName = StringCapitalisation::toCamelCase($modelClass->getShortName());
        $templateName = str_replace('_', '-', $bundle);

        if ($isSingle) {
            $templatePath = "single.{$templateName}";
        } else {
            $templatePath = sprintf(
                '%s.%s.detail',
                StringCapitalisation::toKebabCase($entityType),
                $templateName
            );
        }

        $namespace = $this->builderFactory->namespace($namespaceName);
        $class = $this->builderFactory->class($className);

        $namespace->addStmt($this->builderFactory->use($modelClass->getName()));

        if ($this->baseClass) {
            $baseClass = new \ReflectionClass($this->baseClass);
            $namespace->addStmt($this->builderFactory->use($baseClass->getName()));
            $class->extend($baseClass->getShortName());
        }

        $method = $this->builderFactory->method('show');
        $method->addParam($this->builderFactory->param($variableName)
            ->setType($modelClass->getShortName()));
        $method->addStmt(
            $this->parseExpression("return \$this->view('{$templatePath}', compact('{$variableName}'));")
        );

        $class->addStmt($method);
        $namespace->addStmt($class);

        $node = $namespace->getNode();
        $this->cleanUseStatements($node);

        return $node;
    }

    public function buildControllerPath(string $entityType, string $bundle, string $module): string
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

    public function buildNamespaceName(string $entityType, string $module): string
    {
        $label = StringCapitalisation::toPascalCase($entityType);
        $label = IdentifierNaming::stripInvalidCharacters($label);

        return implode('\\', ['Drupal', $module, 'Controller', $label]);
    }

    public function buildClassName(string $entityType, string $bundle, string $module, bool $shortName = false): string
    {
        $label = StringCapitalisation::toPascalCase($bundle);
        $label = IdentifierNaming::stripInvalidCharacters($label);
        $label .= 'Controller';

        if ($shortName) {
            return $label;
        }

        return sprintf('%s\%s', $this->buildNamespaceName($entityType, $module), $label);
    }
}
