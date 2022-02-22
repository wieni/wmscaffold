<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

abstract class BaseFieldItemList extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $fieldTypeClass = $this->helper->getFieldTypeClass($field) ?? self::getType();
        $fieldTypeClass = new \ReflectionClass($fieldTypeClass);
        $uses[] = $this->builderFactory->use($fieldTypeClass->getName());

        $expression = sprintf('return $this->get(\'%s\');', $field->getName());

        if ($field->isRequired()) {
            $method->setReturnType($fieldTypeClass->getShortName());
        } elseif ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($fieldTypeClass->getShortName()));
        } else {
            $method->setDocComment(sprintf('/** @return %s|null */', $fieldTypeClass->getShortName()));
        }

        $method->addStmts($this->helper->parseExpression($expression));
    }

    public static function getType(): ?string
    {
        return null;
    }
}
