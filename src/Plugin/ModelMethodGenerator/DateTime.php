<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

/**
 * @ModelMethodGenerator(
 *     id = "datetime",
 *     provider = "datetime",
 * )
 */
class DateTime extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $className = \DateTimeInterface::class;
        $shortName = (new \ReflectionClass($className))->getShortName();
        $uses[] = $this->builderFactory->use($className);

        if ($this->helper->isFieldMultiple($field)) {
            if ($this->helper->supportsArrowFunctions()) {
                $expression = sprintf('return array_map(
                    fn ($item): %s => $item->date->getPhpDatetime(),
                    iterator_to_array($this->get(\'%s\'))
                );', $shortName, $field->getName());
            } else {
                $expression = sprintf('return array_map(
                    function ($item): %s { return $item->date->getPhpDatetime(); },
                    iterator_to_array($this->get(\'%s\'))
                );', $shortName, $field->getName());
            }
        } else {
            $expression = sprintf('return $this->get(\'%s\')->date->getPhpDateTime();', $field->getName());
        }

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment(sprintf('/** @return %s[] */', $shortName));
        } elseif ($field->isRequired()) {
            $method->setReturnType($shortName);
        } elseif ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType($shortName));
        } else {
            $method->setDocComment(sprintf('/** @return %s|null */', $shortName));
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
