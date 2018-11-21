<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "telephone"
 * )
 */
class Telephone extends BaseScalarType
{
    public static function getType()
    {
        return 'string';
    }
}
