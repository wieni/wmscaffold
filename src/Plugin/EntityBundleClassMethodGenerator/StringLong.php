<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "string_long",
 *     provider = "core",
 * )
 */
class StringLong extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
