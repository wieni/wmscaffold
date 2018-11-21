<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;

abstract class BaseScalarType extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        $scalarType = static::getType();

        if ($this->helper->isFieldMultiple($field)) {
            $expression = sprintf('return array_map(function ($item) {
                return (%s) $item->value;
            }, iterator_to_array($this->get(\'%s\')));', $scalarType, $field->getName());
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$scalarType}[] */");

        } else {
            $expression = sprintf('return (%s) $this->get(\'%s\')->value;', $scalarType, $field->getName());
            $method->setReturnType($scalarType);
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }

    public abstract static function getType();
}
