<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "link"
 * )
 */
class Link extends BaseFieldItem
{
    public static function getType(): ?string
    {
        return 'Drupal\link\LinkItemInterface';
    }
}
