<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "string_long"
 * )
 */
class StringLong extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
