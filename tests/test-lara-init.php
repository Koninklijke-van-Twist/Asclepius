<?php
require __DIR__ . '/../web/vendor/autoload.php';
require __DIR__ . '/../web/auth.php';

$creds = new \Lara\LaraCredentials($laraTranslate['ID'], $laraTranslate['Secret']);
$lara = new \Lara\Translator($creds);
echo 'Lara client OK' . PHP_EOL;

// Probeer een korte vertaling (string)
$result = $lara->translate('Hello world', null, 'nl-NL');
echo 'Vertaling: ' . $result->getTranslation() . PHP_EOL;
echo 'Brontaal:  ' . $result->getSourceLanguage() . PHP_EOL;

// Probeer met TextBlock array (voor keyboard tokens)
$blocks = [
    new \Lara\TextBlock('Press ', true),
    new \Lara\TextBlock('[ctrl]', false),
    new \Lara\TextBlock(' to continue', true),
];
$result2 = $lara->translate($blocks, null, 'nl-NL');
$parts = $result2->getTranslation();
$out = is_array($parts) ? implode('', array_map(fn($p) => is_string($p) ? $p : $p->getText(), $parts)) : $parts;
echo 'Blocks vertaling: ' . $out . PHP_EOL;
echo 'Brontaal blocks:  ' . $result2->getSourceLanguage() . PHP_EOL;
