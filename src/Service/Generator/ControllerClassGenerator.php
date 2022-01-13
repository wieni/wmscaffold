<?php

namespace Drupal\wmscaffold\Service\Generator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\wmscaffold\Service\Helper\IdentifierNaming;
use Drupal\wmscaffold\Service\Helper\StringCapitalisation;
use PhpParser\Node\Stmt;

class ControllerClassGenerator extends ClassGeneratorBase
{
    /** @var string */
    protected $baseClass;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager,
        FileSystemInterface $fileSystem,
        ConfigFactoryInterface $configFactory
    ) {
        parent::__construct($entityTypeManager, $entityFieldManager, $fileSystem, $configFactory);

        $this->baseClass = $this->config->get('generators.controller.base_class');
    }

    public function generateNew(string $entityType, string $bundle, string $module): Stmt\Namespace_
    {
        $className = $this->buildClassName($entityType, $bundle, $module, true);
        $namespaceName = $this->buildNamespaceName($entityType, $module);
        $modelClass = new \ReflectionClass(
            $this->entityTypeManager->getStorage($entityType)->getEntityClass($bundle)
        );

        $variableName = StringCapitalisation::toCamelCase($modelClass->getShortName());
        $templatePath = sprintf(
            '%s.%s',
            StringCapitalisation::toKebabCase($entityType),
            str_replace('_', '-', $bundle)
        );

        $namespace = $this->builderFactory->namespace($namespaceName);
        $class = $this->builderFactory->class($className);

        $namespace->addStmt($this->builderFactory->use($modelClass->getName()));

        if ($this->baseClass) {
            $baseClass = new \ReflectionClass($this->baseClass);
            $namespace->addStmt($this->builderFactory->use($baseClass->getName()));
            $class->extend($baseClass->getShortName());
        }

        $method = $this->builderFactory->method('show');
        $method->makePublic()
            ->addParam($this->builderFactory->param($variableName)
                ->setType($modelClass->getShortName()));
        $method->addStmt(
            $this->parseExpression(sprintf('return $this->view(\'%s\', [\'%s\' => $%s]);', $templatePath, $variableName, $variableName))
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
        $parts = array_slice(explode('\\', $className), 2);

        return sprintf(
            '%s/src/%s.php',
            $this->fileSystem->realpath(
                drupal_get_path('module', $module)
            ),
            implode('/', $parts)
        );
    }

    public function buildNamespaceName(string $entityType, string $module): string
    {
        $namespacePattern = $this->config->get('generators.controller.namespace_pattern')
            ?? 'Drupal\{module}\Controller\{entityType}';

        $entityType = StringCapitalisation::toPascalCase($entityType);
        $entityType = IdentifierNaming::stripInvalidCharacters($entityType);

        return str_replace(
            ['{module}', '{entityType}'],
            [$module, $entityType],
            $namespacePattern
        );
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
