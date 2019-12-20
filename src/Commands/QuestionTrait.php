<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\wmscaffold\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;

trait QuestionTrait
{
    /**
     * @param array $choices
     *   If an associative array is passed, the chosen *key* is returned.
     * @return mixed
     */
    protected function choice(string $question, array $choices, bool $multiSelect = false, $default = null)
    {
        $choicesValues = array_values($choices);
        $question = new ChoiceQuestion($question, $choicesValues, $default);
        $question->setMultiselect($multiSelect);

        $return = $this->io()->askQuestion($question);

        if ($multiSelect) {
            return array_map(
                function ($value) use ($choices) {
                    return array_search($value, $choices);
                },
                $return
            );
        }

        return array_search($return, $choices);
    }

    protected function confirm($question, $default = false)
    {
        return $this->io()->askQuestion(
            new ConfirmationQuestion($question, $default)
        );
    }

    protected function askOptional(string $question)
    {
        return $this->io()->ask($question, null, function ($value) { return $value; });
    }
}
