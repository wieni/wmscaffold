<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
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
