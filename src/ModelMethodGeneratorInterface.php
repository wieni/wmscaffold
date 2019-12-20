<?php

namespace Drupal\wmscaffold;

use Drupal\Core\Field\FieldDefinitionInterface;
use PhpParser\Builder\Method;

interface ModelMethodGeneratorInterface
{
    public function buildGetter(FieldDefinitionInterface $field, Method $method, array &$uses): void;

    public function buildSetter();
}
