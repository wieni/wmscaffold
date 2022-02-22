<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

use Drupal\link\LinkItemInterface;

/**
 * @EntityBundleClassMethodGenerator(
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
