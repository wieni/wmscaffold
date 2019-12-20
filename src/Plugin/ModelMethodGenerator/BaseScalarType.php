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

        $expression = $this->helper->isFieldMultiple($field)
            ? sprintf(
                'array_column(
                    $this->get(\'%s\')->getValue(),
                    \'value\'
                )',
                $field->getName()
            )
            : sprintf('return $this->get(\'%s\')->value;', $field->getName());

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

    abstract public static function getType(): string;
}
