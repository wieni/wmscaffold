<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "list_float",
 *     provider = "options",
 * )
 */
class ListFloat extends BaseScalarType
{
    public static function getType(): string
    {
        return 'float';
    }
}
