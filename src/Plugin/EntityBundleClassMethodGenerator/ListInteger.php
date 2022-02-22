<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "list_integer",
 *     provider = "options",
 * )
 */
class ListInteger extends BaseScalarType
{
    public static function getType(): string
    {
        return 'int';
    }
}
