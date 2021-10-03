<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "list_string",
 *     provider = "options",
 * )
 */
class ListString extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
