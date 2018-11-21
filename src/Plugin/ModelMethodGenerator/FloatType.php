<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "float"
 * )
 */
class FloatType extends BaseScalarType
{
    public static function getType()
    {
        return 'float';
    }
}
