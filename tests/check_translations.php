<?php

/**
 * Controleer of alle vertalingen compleet zijn.
 *
 * Gebruik: php tests/check_translations.php
 * Exitcode 0 = alles in orde, exitcode 1 = ontbrekende of lege vertalingen aangetroffen.
 *
 * Dit script is bedoeld voor de GitHub CI-pipeline.
 */

require_once __DIR__ . '/../web/content/localization.php';

$errors = [];
$languages = array_keys(SUPPORTED_LANGUAGES);
$reference = $languages[0]; // 'nl' is de leidende taal

$referenceKeys = array_keys(TRANSLATIONS[$reference]);

foreach ($languages as $lang) {
    foreach ($referenceKeys as $key) {
        if (!array_key_exists($key, TRANSLATIONS[$lang])) {
            $errors[] = sprintf('[%s] Sleutel ontbreekt: "%s"', $lang, $key);
            continue;
        }

        if (trim((string) TRANSLATIONS[$lang][$key]) === '') {
            $errors[] = sprintf('[%s] Lege vertaling voor sleutel: "%s"', $lang, $key);
        }
    }

    // Controleer ook op sleutels die wél in $lang zitten maar NIET in de referentie (overbodige sleutels)
    foreach (array_keys(TRANSLATIONS[$lang]) as $key) {
        if (!in_array($key, $referenceKeys, true)) {
            $errors[] = sprintf('[%s] Overbodige sleutel die niet in "%s" staat: "%s"', $lang, $reference, $key);
        }
    }
}

if ($errors === []) {
    echo 'Alle vertalingen zijn compleet voor talen: ' . implode(', ', $languages) . PHP_EOL;
    exit(0);
}

foreach ($errors as $error) {
    echo $error . PHP_EOL;
}

echo PHP_EOL . count($errors) . ' fout(en) gevonden.' . PHP_EOL;
exit(1);
