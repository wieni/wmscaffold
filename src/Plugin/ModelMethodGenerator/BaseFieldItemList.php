<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

class BaseFieldItemList extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        if (!$fieldTypeClass = $this->helper->getFieldTypeClass($field)) {
            return;
        }

        $fieldTypeClass = new \ReflectionClass($fieldTypeClass);
        $uses[] = $this->builderFactory->use($fieldTypeClass->getName());

        $expression = $this->helper->isFieldMultiple($field)
            ? sprintf('return iterator_to_array($this->get(\'%s\'));', $field->getName())
            : sprintf('return $this->get(\'%s\')->first();', $field->getName());

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$fieldTypeClass->getShortName()}[] */");
        } else if ($field->isRequired()) {
            $method->setReturnType($fieldTypeClass->getShortName());
        } else if ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($fieldTypeClass->getShortName()));
        } else {
            $method->setDocComment("/** @return {$fieldTypeClass->getShortName()}|null */");
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
