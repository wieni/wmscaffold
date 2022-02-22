<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "string",
 *     provider = "core",
 * )
 */
class StringType extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
