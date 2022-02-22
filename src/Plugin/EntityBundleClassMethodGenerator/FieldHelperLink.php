<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\EntityBundleClassMethodGeneratorBase;
use PhpParser\Builder\Method;

class FieldHelperLink extends EntityBundleClassMethodGeneratorBase
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void
    {
        $methodName = $this->helper->isFieldMultiple($field) ? 'formatLinks' : 'formatLink';
        $expression = sprintf('return $this->%s(\'%s\');', $methodName, $field->getName());

        $method->setReturnType('array');
        $method->addStmts($this->helper->parseExpression($expression));
    }
}
