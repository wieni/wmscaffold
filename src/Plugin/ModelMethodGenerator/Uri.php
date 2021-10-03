<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

/**
 * @ModelMethodGenerator(
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
