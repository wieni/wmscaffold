<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

class FieldHelperDateTime extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $className = \DateTime::class;
        $shortName = (new \ReflectionClass($className))->getShortName();
        $uses[] = $this->builderFactory->use($className);

        $expression = sprintf('return $this->toDateTime(\'%s\');', $field->getName());

        if ($this->helper->isFieldMultiple($field)) {
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
