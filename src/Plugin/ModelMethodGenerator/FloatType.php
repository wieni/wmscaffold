<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "float",
 *     provider = "core",
 * )
 */
class FloatType extends BaseScalarType
{
    public static function getType(): string
    {
        return 'float';
    }
}
