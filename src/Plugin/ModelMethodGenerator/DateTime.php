<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

/**
 * @ModelMethodGenerator(
 *     id = "datetime"
 * )
 */
class DateTime extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        $className = \DateTime::class;
        $shortName = (new \ReflectionClass($className))->getShortName();
        $uses[] = $this->builderFactory->use($className);

        // TODO: Fix multiple
        $expression = $this->helper->isFieldMultiple($field)
            ? sprintf('return $this->toDateTime(\'%s\');', $field->getName())
            : sprintf('return $this->toDateTime(\'%s\');', $field->getName());

        if ($this->helper->isFieldMultiple($field)) {
            $expression = sprintf('return $this->toDateTime(\'%s\');', $field->getName());
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$shortName}[] */");
        } elseif ($field->isRequired()) {
            $method->setReturnType($shortName);
        } elseif ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($shortName));
        } else {
            $method->setDocComment("/** @return {$shortName}|null */");
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
