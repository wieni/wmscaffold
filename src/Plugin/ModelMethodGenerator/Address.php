<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "address"
 * )
 */
class Address extends BaseFieldItem
{
    public static function getType(): ?string
    {
        return 'Drupal\address\AddressInterface';
    }
}
