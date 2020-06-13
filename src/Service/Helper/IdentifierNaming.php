<?php

namespace Drupal\wmscaffold\Service\Helper;

class IdentifierNaming
{
    public static function isReservedKeyword(string $input): bool
    {
        return in_array(strtolower($input), [
            '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
            'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
            'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval', 'exit', 'extends',
            'final', 'for', 'foreach', 'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
            'instanceof', 'insteadof', 'interface', 'isset', 'list', 'namespace', 'new', 'or', 'print', 'private',
            'protected', 'public', 'require', 'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try',
            'unset', 'use', 'var', 'while', 'xor',
        ]);
    }

    public static function stripInvalidCharacters(string $string): string
    {
        // A valid function name starts with a letter or underscore.
        while (!preg_match('/^[a-zA-Z_]/', $string)) {
            $string = substr($string, 1);
        }

        // Strip invalid characters
        // @see https://www.php.net/manual/en/functions.user-defined.php
        $string = preg_replace('/[^a-zA-Z0-9_\x7f-\xff]*/i', '', $string);

        return $string;
    }
}
