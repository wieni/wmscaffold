<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "boolean"
 * )
 */
class Boolean extends BaseScalarType
{
    public static function getType()
    {
        return 'bool';
    }
}
