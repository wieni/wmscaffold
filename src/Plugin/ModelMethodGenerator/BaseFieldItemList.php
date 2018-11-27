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

        if ($this->helper->isFieldMultiple($field)) {
            $expression = sprintf('return iterator_to_array($this->get(\'%s\'));', $field->getName());
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$fieldTypeClass->getShortName()}[] */");

        } else {
            $expression = sprintf('return $this->get(\'%s\')->first();', $field->getName());

            if ($field->isRequired()) {
                $method->setReturnType($fieldTypeClass->getShortName());

            } else {
                if ($this->helper->supportsNullableTypes()) {
                    $method->setReturnType(new NullableType($fieldTypeClass->getShortName()));

                } else {
                    $method->setDocComment("/** @return {$fieldTypeClass->getShortName()}|null */");
                }
            }
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
