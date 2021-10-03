<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

/**
 * @ModelMethodGenerator(
 *     id = "text",
 *     provider = "core",
 * )
 */
class Text extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        if ($this->helper->isFieldMultiple($field)) {
            if ($this->helper->supportsArrowFunctions()) {
                $expression = sprintf('return array_map(
                    fn ($item): string => $item->processed,
                    iterator_to_array($this->get(\'%s\'))
                );', $field->getName());
            } else {
                $expression = sprintf('return array_map(
                    function ($item): string { return $item->processed; },
                    iterator_to_array($this->get(\'%s\'))
                );', $field->getName());
            }
        } else {
            $expression = sprintf('return $this->get(\'%s\')->processed;', $field->getName());
        }

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment('/** @return string[] */');
        } elseif ($field->isRequired()) {
            $method->setReturnType('string');
        } elseif ($this->helper->supportsNullableTypes()) {
            $method->setReturnType(new NullableType('string'));
        } else {
            $method->setDocComment('/** @return string|null */');
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
