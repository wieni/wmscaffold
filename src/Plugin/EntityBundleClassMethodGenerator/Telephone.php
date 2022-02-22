<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "telephone",
 *     provider = "telephone",
 * )
 */
class Telephone extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
