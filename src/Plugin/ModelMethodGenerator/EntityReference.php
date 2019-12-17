<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

/**
 * @ModelMethodGenerator(
 *     id = "entity_reference"
 * )
 */
class EntityReference extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        $fieldModelClass = $this->helper->getFieldModelClass($field);
        $fieldModelClass = new \ReflectionClass($fieldModelClass);

        $uses[] = $this->builderFactory->use($fieldModelClass->getName());

        $expression = $this->helper->isFieldMultiple($field)
            ? sprintf('return $this->get(\'%s\')->referencedEntities();', $field->getName())
            : sprintf('return $this->get(\'%s\')->entity;', $field->getName());

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$fieldModelClass->getShortName()}[] */");
        } else if ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($fieldModelClass->getShortName()));
        } else {
            $method->setDocComment("/** @return {$fieldModelClass->getShortName()}|null */");
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
