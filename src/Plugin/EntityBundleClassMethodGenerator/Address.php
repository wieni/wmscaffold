<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

use Drupal\address\AddressInterface;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "address",
 *     provider = "address",
 * )
 */
class Address extends BaseFieldItem
{
    public static function getType(): ?string
    {
        return AddressInterface::class;
    }
}
