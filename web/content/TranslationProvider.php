<?php

interface TranslationProvider
{
    /**
     * Translate plain text to the target app language.
     *
     * @param  string $rawText           The text to translate.
     * @param  string $targetAppLanguage App language code: 'nl', 'en', 'de', 'fr'.
     * @return array{translated_text: string, source_language: string}
     *         source_language: detected app language code ('nl', 'en', …), or '' if unknown.
     * @throws \Throwable on provider or network failure.
     */
    public function translateText(string $rawText, string $targetAppLanguage): array;
}
