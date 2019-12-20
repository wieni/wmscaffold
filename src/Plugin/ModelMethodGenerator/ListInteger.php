<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "list_integer"
 * )
 */
class ListInteger extends BaseScalarType
{
    public static function getType(): string
    {
        return 'int';
    }
}
