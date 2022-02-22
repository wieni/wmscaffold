<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\link\LinkItemInterface;

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
        return LinkItemInterface::class;
    }
}
