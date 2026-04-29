<?php
echo "=== Testing array_map behavior ===" . PHP_EOL . PHP_EOL;

$assoc = ['email1@kvt.nl' => '#color1', 'email2@kvt.nl' => '#color2'];
$flat = ['email1@kvt.nl', 'email2@kvt.nl'];

echo "Associative array:" . PHP_EOL;
echo "Input: ";
var_dump($assoc);
$result = array_map('strtolower', $assoc);
echo "array_map('strtolower', \$assoc):" . PHP_EOL;
var_dump($result);
echo "Keys preserved? " . (array_keys($result) === array_keys($assoc) ? 'YES' : 'NO') . PHP_EOL;
echo PHP_EOL;

echo "Flat array:" . PHP_EOL;
echo "Input: ";
var_dump($flat);
$result = array_map('strtolower', $flat);
echo "array_map('strtolower', \$flat):" . PHP_EOL;
var_dump($result);
echo PHP_EOL;

echo "=== Testing array_values(array_unique(array_map)) ===" . PHP_EOL;
echo "On associative array:" . PHP_EOL;
$assoc = ['email1@kvt.nl' => '#color1', 'email2@kvt.nl' => '#color2'];
$result = array_values(array_unique(array_map('strtolower', $assoc)));
echo "Result: ";
var_dump($result);
echo "  This is WRONG! We got the COLORS, not the EMAILS!" . PHP_EOL;
echo PHP_EOL;

echo "On flat array:" . PHP_EOL;
$flat = ['email1@kvt.nl', 'email2@kvt.nl'];
$result = array_values(array_unique(array_map('strtolower', $flat)));
echo "Result: ";
var_dump($result);
echo "  This is CORRECT!" . PHP_EOL;
