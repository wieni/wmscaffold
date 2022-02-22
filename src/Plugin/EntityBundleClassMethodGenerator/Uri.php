<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "uri",
 *     provider = "core",
 * )
 */
class Uri extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
