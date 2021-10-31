<?php

namespace Drupal\wmscaffold\Question;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Question\ChoiceQuestion as ChoiceQuestionBase;

class ChoiceQuestion extends ChoiceQuestionBase
{
    /** @var string */
    private $errorMessage = 'Value "%s" is invalid';

    public function __construct(string $question, array $choices, $default = null)
    {
        parent::__construct($question, $choices, $default);
        $this->setValidator($this->getBetterValidator());
    }

    public function setMultiselect($multiselect): void
    {
        parent::setMultiselect($multiselect);
        $this->setValidator($this->getBetterValidator());
    }

    /**
     * Returns the answer validator. this is the same validator as the default one,
     * except spaces in choices are no longer being collapsed.
     */
    protected function getBetterValidator(): callable
    {
        $choices = $this->getChoices();
        $errorMessage = $this->errorMessage;
        $multiselect = $this->isMultiselect();
        $isAssoc = $this->isAssoc($choices);

        return function ($selected) use ($choices, $errorMessage, $multiselect, $isAssoc) {
            $selectedChoices = $selected;

            if ($multiselect) {
                // Check for a separated comma values
                if (!preg_match('/^[^,]+(?:,[^,]+)*$/', $selectedChoices, $matches)) {
                    throw new InvalidArgumentException(sprintf($errorMessage, $selected));
                }

                $selectedChoices = explode(',', $selectedChoices);
                $selectedChoices = array_map('trim', $selectedChoices);
            } else {
                $selectedChoices = [$selected];
            }

            $multiselectChoices = [];
            foreach ($selectedChoices as $value) {
                $results = [];
                foreach ($choices as $key => $choice) {
                    if ($choice === $value) {
                        $results[] = $key;
                    }
                }

                if (\count($results) > 1) {
                    throw new InvalidArgumentException(sprintf('The provided answer is ambiguous. Value should be one of %s.', implode(' or ', $results)));
                }

                $result = array_search($value, $choices, true);

                if (!$isAssoc) {
                    if (false !== $result) {
                        $result = $choices[$result];
                    } elseif (isset($choices[$value])) {
                        $result = $choices[$value];
                    }
                } elseif (false === $result && isset($choices[$value])) {
                    $result = $value;
                }

                if (false === $result) {
                    throw new InvalidArgumentException(sprintf($errorMessage, $value));
                }

                $multiselectChoices[] = (string) $result;
            }

            if ($multiselect) {
                return $multiselectChoices;
            }

            return current($multiselectChoices);
        };
    }
}
