<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "email",
 *     provider = "core",
 * )
 */
class Email extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
