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

        if ($this->helper->isFieldMultiple($field)) {
            // TODO: Fix this
            $expression = sprintf('return $this->toDateTime(\'%s\');', $field->getName());
        } else {
            $expression = sprintf('return $this->toDateTime(\'%s\');', $field->getName());
        }

        $method->addStmt(
            $this->helper->parseExpression($expression)
        );

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment("/** @return {$shortName}[] */");

        } else {
            if ($field->isRequired()) {
                $method->setReturnType($shortName);

            } else {
                if ($this->helper->supportsNullableTypes()) {
                    $method->setReturnType(new NullableType($shortName));

                } else {
                    $method->setDocComment("/** @return {$shortName}|null */");
                }
            }
        }
    }
}
