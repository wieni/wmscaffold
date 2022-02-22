<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\address\AddressInterface;

/**
 * @ModelMethodGenerator(
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
