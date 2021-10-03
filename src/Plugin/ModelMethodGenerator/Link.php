<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "link",
 *     provider = "link",
 * )
 */
class Link extends BaseFieldItem
{
    public static function getType(): ?string
    {
        return 'Drupal\link\LinkItemInterface';
    }
}
