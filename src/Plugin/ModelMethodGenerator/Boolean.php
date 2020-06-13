<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "boolean"
 * )
 */
class Boolean extends BaseScalarType
{
    public static function getType(): string
    {
        return 'bool';
    }

    protected function shouldCastToType(): bool
    {
        return true;
    }
}
