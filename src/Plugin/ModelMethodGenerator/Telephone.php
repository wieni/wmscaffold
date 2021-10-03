<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "telephone",
 *     provider = "telephone",
 * )
 */
class Telephone extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
