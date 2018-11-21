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
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        if ($this->helper->isFieldMultiple($field)) {
            $expression = sprintf('return array_map(function ($item) {
                return (string) $item->processed;
            }, iterator_to_array($this->get(\'%s\')));', $field->getName());
            $method->setReturnType('array');
            $method->setDocComment("/** @return string[] */");
        } else {
            $expression = sprintf('return (string) $this->get(\'%s\')->processed;', $field->getName());
            $method->setReturnType('string');
        }

        $method->addStmt($this->helper->parseExpression($expression));
    }
}
