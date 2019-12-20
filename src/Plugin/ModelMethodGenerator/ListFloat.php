<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "list_float"
 * )
 */
class ListFloat extends BaseScalarType
{
    public static function getType(): string
    {
        return 'float';
    }
}
