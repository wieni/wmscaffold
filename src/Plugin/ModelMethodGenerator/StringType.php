<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "string",
 *     provider = "core",
 * )
 */
class StringType extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
