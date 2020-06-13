<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

abstract class BaseScalarType extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $scalarType = static::getType();

        if ($this->helper->isFieldMultiple($field)) {
            $expression = sprintf(
                'return array_column(
                    $this->get(\'%s\')->getValue(),
                    \'value\'
                )',
                $field->getName()
            );
        } elseif ($this->shouldCastToType()) {
            $expression = sprintf('return (%s) $this->get(\'%s\')->value;', static::getType(), $field->getName());
        } else {
            $expression = sprintf('return $this->get(\'%s\')->value;', $field->getName());
        }

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$scalarType}[] */");
        } elseif ($field->isRequired()) {
            $method->setReturnType($scalarType);
        } elseif ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($scalarType));
        } else {
            $method->setDocComment("/** @return {$scalarType}|null */");
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }

    protected function shouldCastToType(): bool
    {
        return false;
    }

    abstract public static function getType(): string;
}
