<?php

namespace Drupal\wmscaffold\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BaseFieldInfoCommands extends DrushCommands
{
    /** @var EntityTypeManagerInterface */
    protected $entityTypeManager;
    /** @var EntityFieldManagerInterface */
    protected $entityFieldManager;

    public function __construct(
        EntityTypeManagerInterface $entityTypeManager,
        EntityFieldManagerInterface $entityFieldManager
    ) {
        $this->entityTypeManager = $entityTypeManager;
        $this->entityFieldManager = $entityFieldManager;
    }

    /**
     * List all base fields of an entity type
     *
     * @command base-field:info
     * @aliases base-field-info,bfi
     *
     * @param string $entityType
     *      The machine name of the entity type
     * @param array $options
     *
     * @option show-machine-names
     *      Show machine names instead of labels in option lists.
     *
     * @default-fields field_name,required,field_type,cardinality
     * @field-labels
     *      label: Label
     *      description: Description
     *      field_name: Field name
     *      field_type: Field type
     *      required: Required
     *      translatable: Translatable
     *      cardinality: Cardinality
     *      default_value: Default value
     *      default_value_callback: Default value callback
     *      allowed_values: Allowed values
     *      allowed_values_function: Allowed values function
     *      handler: Selection handler
     *      target_bundles: Target bundles
     * @filter-default-field field_name
     * @table-style default
     *
     * @usage drush base-field-info taxonomy_term
     *      List all base fields.
     * @usage drush base-field:info
     *      List all base fields and fill in the remaining information through prompts.
     *
     * @return RowsOfFields
     */
    public function info($entityType, $options = ['format' => 'table']) {
        $rows = [];

        $fields = $this->entityFieldManager->getBaseFieldDefinitions($entityType);

        foreach ($fields as $field) {
            $storage = $field->getFieldStorageDefinition();
            $handlerSettings = $field->getSetting('handler_settings');

            $rows[$field->getName()] = [
                'label' => $field->getLabel(),
                'description' => $field->getDescription(),
                'field_name' => $field->getName(),
                'field_type' => $field->getType(),
                'required' => $field->isRequired(),
                'translatable' => $field->isTranslatable(),
                'cardinality' => $storage->getCardinality(),
                'default_value' => empty($field->getDefaultValueLiteral()) ? null : $field->getDefaultValueLiteral(),
                'default_value_callback' => $field->getDefaultValueCallback(),
                'allowed_values' => $storage->getSetting('allowed_values'),
                'allowed_values_function' => $storage->getSetting('allowed_values_function'),
                'handler' => $field->getSetting('handler'),
                'target_bundles' => $handlerSettings['target_bundles'] ?? null,
            ];
        }

        $result = new RowsOfFields($rows);
        $result->addRendererFunction([$this, 'renderArray']);
        $result->addRendererFunction([$this, 'renderBoolean']);

        return $result;
    }

    public function renderArray($key, $value, FormatterOptions $options)
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return $value;
    }

    public function renderBoolean($key, $value, FormatterOptions $options)
    {
        if (is_bool($value)) {
            return $value ? '✔' : '';
        }

        return $value;
    }

    /**
     * @hook interact field:info
     */
    public function interact(InputInterface $input, OutputInterface $output, AnnotationData $annotationData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$entityType) {
            return;
        }
    }

    /**
     * @hook validate field:info
     */
    public function validateEntityType(CommandData $commandData)
    {
        $entityType = $this->input->getArgument('entityType');

        if (!$this->entityTypeManager->hasDefinition($entityType)) {
            throw new \InvalidArgumentException(
                t('Entity type with id \':entityType\' does not exist.', [':entityType' => $entityType])
            );
        }
    }
}
