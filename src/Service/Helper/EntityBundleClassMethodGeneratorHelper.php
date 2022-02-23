<?php

namespace Drupal\wmscaffold\Service\Helper;

use Composer\Semver\Comparator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use PhpParser\BuilderFactory;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

class EntityBundleClassMethodGeneratorHelper
{
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var FieldTypePluginManager */
    protected $fieldTypePluginManager;
    /** @var BuilderFactory */
    protected $builderFactory;
    /** @var ParserFactory */
    protected $parserFactory;
    /** @var ImmutableConfig */
    protected $config;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        FieldTypePluginManager $fieldTypePluginManager,
        ConfigFactoryInterface $configFactory
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldTypePluginManager = $fieldTypePluginManager;
        $this->builderFactory = new BuilderFactory();
        $this->parserFactory = new ParserFactory();
        $this->config = $configFactory->get('wmscaffold.settings');
    }

    /** Determine whether a field's cardinality is multiple */
    public function isFieldMultiple(FieldDefinitionInterface $field): bool
    {
        return $field->getFieldStorageDefinition()->getCardinality() !== 1;
    }

    /** Returns the full classname of the entity of a field */
    public function getFieldEntityClass(FieldDefinitionInterface $field): string
    {
        $targetType = $field->getFieldStorageDefinition()->getSetting('target_type');
        $definition = $this->entityTypeManager->getDefinition($targetType);
        $handlerSettings = $field->getSetting('handler_settings');

        if (empty($handlerSettings['target_bundles'])) {
            return $definition->getClass();
        }

        return $this->entityTypeManager
            ->getStorage($definition->id())
            ->getEntityClass(reset($handlerSettings['target_bundles']));
    }

    /** Returns the full classname of a field type */
    public function getFieldTypeClass(FieldDefinitionInterface $field): ?string
    {
        $definition = $this->fieldTypePluginManager->getDefinition($field->getType());

        return $definition['class'] ?? null;
    }

    /** Convert a string representation of a PHP statement into a PhpParser node */
    public function parseExpression(string $expression): array
    {
        $parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7);
        $statements = $parser->parse('<?php ' . $expression . ';');

        return $statements;
    }

    public function supportsNullableTypes(): bool
    {
        return Comparator::greaterThanOrEqualTo($this->config->get('php_version'), 7.1);
    }

    public function supportsArrowFunctions(): bool
    {
        return Comparator::greaterThanOrEqualTo($this->config->get('php_version'), 7.4);
    }

    public function supportsOptionalChaining(): bool
    {
        return Comparator::greaterThanOrEqualTo($this->config->get('php_version'), 8.0);
    }
}
