<?php

namespace Drupal\wmscaffold\Plugin\ModelMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\ModelMethodGeneratorBase;
use PhpParser\Builder\Method;

/**
 * @ModelMethodGenerator(
 *     id = "link"
 * )
 */
class Link extends ModelMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses)
    {
        $methodName = $this->helper->isFieldMultiple($field) ? 'formatLinks' : 'formatLink';
        $expression = sprintf('return $this->%s(\'%s\');', $methodName, $field->getName());

        $method->setReturnType('array');
        $method->addStmt($this->helper->parseExpression($expression));
    }
}
