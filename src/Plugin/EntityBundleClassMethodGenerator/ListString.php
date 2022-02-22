<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
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
