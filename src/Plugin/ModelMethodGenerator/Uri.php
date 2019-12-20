<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
 *     id = "uri"
 * )
 */
class Uri extends BaseScalarType
{
    public static function getType(): string
    {
        return 'string';
    }
}
