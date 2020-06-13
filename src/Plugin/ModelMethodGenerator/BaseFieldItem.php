<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

abstract class BaseFieldItem extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $fieldTypeClass = $this->helper->getFieldTypeClass($field) ?? self::getType();
        $fieldTypeClass = new \ReflectionClass($fieldTypeClass);
        $uses[] = $this->builderFactory->use($fieldTypeClass->getName());

        $expression = $this->helper->isFieldMultiple($field)
            ? sprintf('return iterator_to_array($this->get(\'%s\'));', $field->getName())
            : sprintf('return $this->get(\'%s\')->first();', $field->getName());

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$fieldTypeClass->getShortName()}[] */");
        } elseif ($field->isRequired()) {
            $method->setReturnType($fieldTypeClass->getShortName());
        } elseif ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($fieldTypeClass->getShortName()));
        } else {
            $method->setDocComment("/** @return {$fieldTypeClass->getShortName()}|null */");
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }

    public static function getType(): ?string
    {
        return null;
    }
}
