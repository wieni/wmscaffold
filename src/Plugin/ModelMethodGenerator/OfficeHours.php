<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "office_hours"
 * )
 */
class OfficeHours extends BaseFieldItemList
{
    public static function getType(): ?string
    {
        return 'Drupal\office_hours\Plugin\Field\FieldType\OfficeHoursItemListInterface';
    }
}
