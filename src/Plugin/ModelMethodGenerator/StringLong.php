<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "string_long",
 *     provider = "core",
 * )
 */
class StringLong extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
