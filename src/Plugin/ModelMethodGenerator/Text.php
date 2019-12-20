<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;

/**
 * @ModelMethodGenerator(
 *     id = "text"
 * )
 */
class Text extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $expression = $this->helper->isFieldMultiple($field)
            ? sprintf('return array_map(function ($item) {
                return (string) $item->processed;
            }, iterator_to_array($this->get(\'%s\')));', $field->getName())
            : sprintf('return (string) $this->get(\'%s\')->processed;', $field->getName());

        if ($this->helper->isFieldMultiple($field)) {
            $method->setReturnType('array');
            $method->setDocComment('/** @return string[] */');
        } else {
            $method->setReturnType('string');
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
