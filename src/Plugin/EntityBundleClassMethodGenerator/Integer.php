<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
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
