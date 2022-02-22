<?php

namespace Drupal\wmscaffold\Plugin\EntityBundleClassMethodGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\wmscaffold\EntityBundleClassMethodGeneratorBase;
use PhpParser\Builder\Method;
use PhpParser\Node\NullableType;

/**
 * @EntityBundleClassMethodGenerator(
 *     id = "datetime",
 *     provider = "datetime",
 * )
 */
class DateTime extends EntityBundleClassMethodGeneratorBase
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
        } elseif ($field->isRequired()) {
            $expression = sprintf('return $this->get(\'%s\')->date->getPhpDateTime();', $field->getName());
        } elseif ($this->helper->supportsOptionalChaining()) {
            $expression = sprintf('return $this->get(\'%s\')->date?->getPhpDateTime();', $field->getName());
        } else {
            $expression = sprintf(<<<'EOT'
            if ($date = $this->get('%s')->date) {
                return $date->getPhpDateTime();
            }
            return null;
            EOT, $field->getName());
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

        $method->addStmts($this->helper->parseExpression($expression));
    }
}
