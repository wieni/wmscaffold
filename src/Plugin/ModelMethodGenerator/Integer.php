<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "integer",
 *     provider = "core",
 * )
 */
class Integer extends BaseScalarType
{
    public static function getType(): string
    {
        return 'int';
    }
}
