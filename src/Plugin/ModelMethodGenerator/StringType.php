<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "string"
 * )
 */
class StringType extends BaseScalarType
{
    public static function getType()
    {
        return 'string';
    }
}
