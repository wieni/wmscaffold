<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\EntityBundleClassMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "entity_reference",
 *     provider = "core",
 * )
 */
class EntityReference extends EntityBundleClassMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $fieldEntityClass = $this->helper->getFieldEntityClass($field);
        $fieldEntityClass = new \ReflectionClass($fieldEntityClass);

        $uses[] = $this->builderFactory->use($fieldEntityClass->getName());

        $expression = $this->helper->isFieldMultiple($field)
            ? sprintf('return $this->get(\'%s\')->referencedEntities();', $field->getName())
            : sprintf('return $this->get(\'%s\')->entity;', $field->getName());

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment(sprintf('/** @return %s[] */', $fieldEntityClass->getShortName()));
        } elseif ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($fieldEntityClass->getShortName()));
        } else {
            $method->setDocComment(sprintf('/** @return %s|null */', $fieldEntityClass->getShortName()));
        }

        $method->addStmts($this->helper->parseExpression($expression));
    }
}
