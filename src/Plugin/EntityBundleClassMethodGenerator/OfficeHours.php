<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "office_hours",
 *     provider = "office_hours",
 * )
 */
class OfficeHours extends BaseFieldItemList
{
    public static function getType(): ?string
    {
        return 'Drupal\office_hours\Plugin\Field\FieldType\OfficeHoursItemListInterface';
    }
}
