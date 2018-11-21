<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "uri"
 * )
 */
class Uri extends BaseScalarType
{
    public static function getType()
    {
        return 'string';
    }
}
