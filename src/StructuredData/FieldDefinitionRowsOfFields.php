<?php

namespace Drupal\wmscaffold\StructuredData;

use Consolidation\OutputFormatters\Options\FormatterOptions;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;

class FieldDefinitionRowsOfFields extends RowsOfFields
{
    public function __construct($data)
    {
        parent::__construct($data);

        $this->addRendererFunction([$this, 'renderArray']);
        $this->addRendererFunction([$this, 'renderBoolean']);
    }

    public static function fromFieldDefinitions(array $fieldDefinitions): self
    {
        $rows = [];

        foreach ($fieldDefinitions as $field) {
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

        return new self($rows);
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
}
