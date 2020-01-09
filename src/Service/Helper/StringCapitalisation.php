<?php

namespace Drupal\wmscaffold\Service\Helper;

class StringCapitalisation
{
    public static function toCamelCase(string $input): string
    {
        return lcfirst(self::toPascalCase($input));
    }

    public static function toPascalCase(string $input): string
    {
        $input = preg_replace('/\-|\s/', '_', $input);

        return str_replace('_', '', ucwords($input, '_'));
    }

    public static function toKebabCase(string $input): string
    {
        $input = preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '-$0', $input);

        return strtolower(ltrim($input, '-'));
    }
}
