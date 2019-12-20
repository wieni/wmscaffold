<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "integer"
 * )
 */
class Integer extends BaseScalarType
{
    public static function getType(): string
    {
        return 'int';
    }
}
