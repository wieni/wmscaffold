<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use PhpParser\Builder\Method;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "boolean",
 *     provider = "core",
 * )
 */
class Boolean extends BaseScalarType
{
    public static function getType(): string
    {
        return 'bool';
    }

    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        parent::buildGetter($field, $method, $uses);
        $method->setReturnType(self::getType());
    }

    protected function shouldCastToType(): bool
    {
        return true;
    }
}
