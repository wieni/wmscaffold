<?php

namespace Drupal\wmscaffold\Commands;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * @property LanguageManagerInterface $languageManager
 * @method choice(string $question, array $choices, bool $multiSelect = false, $default = null)
 */
trait AskLanguageDefaultTrait
{
    protected function askLanguageDefault(): string
    {
        $options = [
            LanguageInterface::LANGCODE_SITE_DEFAULT => t("Site's default language (@language)", ['@language' => \Drupal::languageManager()->getDefaultLanguage()->getName()]),
            'current_interface' => t('Interface text language selected for page'),
            'authors_default' => t("Author's preferred language"),
        ];

        $languages = $this->languageManager->getLanguages(LanguageInterface::STATE_ALL);

        foreach ($languages as $langcode => $language) {
            $options[$langcode] = $language->isLocked()
                ? t('- @name -', ['@name' => $language->getName()])
                : $language->getName();
        }

        return $this->choice('Default language', $options, false, 0);
    }
}
