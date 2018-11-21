<?php

namespace Drupal\wmscaffold\Service\Helper;

use Composer\Semver\Comparator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManager;
use Drupal\wmmodel\Factory\ModelFactory;
use PhpParser\BuilderFactory;
use PhpParser\ParserFactory;

class ModelMethodGeneratorHelper
{
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var FieldTypePluginManager */
    protected $fieldTypePluginManager;
    /** @var BuilderFactory */
    protected $builderFactory;
    /** @var ParserFactory */
    protected $parserFactory;
    /** @var ModelFactory */
    protected $modelFactory;
    /** @var ImmutableConfig */
    protected $config;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        FieldTypePluginManager $fieldTypePluginManager,
        ModelFactory $modelFactory,
        ConfigFactoryInterface $configFactory
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->fieldTypePluginManager = $fieldTypePluginManager;
        $this->modelFactory = $modelFactory;
        $this->builderFactory = new BuilderFactory();
        $this->parserFactory = new ParserFactory();
        $this->config = $configFactory->get('wmscaffold.settings');
    }

    /**
     * Determine whether a field's cardinality is multiple
     *
     * @param FieldDefinitionInterface $field
     * @return bool
     */
    public function isFieldMultiple(FieldDefinitionInterface $field)
    {
        return $field->getFieldStorageDefinition()->getCardinality() !== 1;
    }

    /**
     * Returns the full classname of the model of a field
     *
     * @param FieldDefinitionInterface $field
     * @return mixed
     */
    public function getFieldModelClass(FieldDefinitionInterface $field)
    {
        $targetType = $field->getFieldStorageDefinition()->getSetting('target_type');
        $targetBundles = $field->getSetting('handler_settings')['target_bundles'];
        $targetBundle = reset($targetBundles);

        return $this->modelFactory->getClassName(
            $this->entityTypeManager->getDefinition($targetType),
            $targetBundle
        );
    }

    /**
     * Returns the full classname of a field type
     *
     * @param FieldDefinitionInterface $field
     * @return string|null
     */
    public function getFieldTypeClass(FieldDefinitionInterface $field)
    {
        $definition = $this->fieldTypePluginManager->getDefinition($field->getType());

        return $definition['class'] ?? null;
    }

    /**
     * Convert a string representation of a PHP statement into a PhpParser node
     *
     * @param string $expression
     * @return \PhpParser\Node\Stmt|null
     */
    public function parseExpression(string $expression)
    {
        $parser = $this->parserFactory->create(ParserFactory::PREFER_PHP7);
        $statements = $parser->parse('<?php ' . $expression . ';');

        return $statements
            ? $statements[0]
            : null;
    }

    public function supportsReturnTypes()
    {
        return Comparator::greaterThan($this->config->get('phpVersion'), 7);
    }

    public function supportsNullableTypes()
    {
        return Comparator::greaterThan($this->config->get('phpVersion'), 7.1);
    }
}
