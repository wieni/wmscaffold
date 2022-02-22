<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "float",
 *     provider = "core",
 * )
 */
class FloatType extends BaseScalarType
{
    public static function getType(): string
    {
        return 'float';
    }
}
