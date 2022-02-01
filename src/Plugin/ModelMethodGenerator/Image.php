<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\image\Plugin\Field\FieldType\ImageItem;

/**
 * @ModelMethodGenerator(
 *     id = "image",
 *     provider = "image",
 * )
 */
class Image extends BaseFieldItem
{
    public static function getType(): ?string
    {
        return ImageItem::class;
    }
}
