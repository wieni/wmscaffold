<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "email"
 * )
 */
class Email extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
