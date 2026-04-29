<?php
/**
 * Diagnostic script to check $ictUsers flow through the application
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

// Simulate the auth.php setup with associative ictUsers
$ictUsers = [
    "tfalken@kvt.nl" => '#0a6ee0',
    "milanscheenloop@kvt.nl" => '#8a0ae0',
    "opesket@kvt.nl" => '#2ee00a',
    "cvrij@kvt.nl" => '#b45414',
    "mthors@kvt.nl" => '#c71515',
    "wbresser@kvt.nl" => '#d6206c'
];

$ictUserColors = [];

echo "=== INITIAL STATE (from auth.php) ===" . PHP_EOL;
echo "Type: " . gettype($ictUsers) . PHP_EOL;
echo "Is associative: " . (array_keys($ictUsers) !== range(0, count($ictUsers) - 1) ? 'YES' : 'NO') . PHP_EOL;
echo "Keys: " . implode(', ', array_keys($ictUsers)) . PHP_EOL;
echo "Values: " . implode(', ', array_values($ictUsers)) . PHP_EOL;
echo PHP_EOL;

// Simulate bootstrap.php normalization
echo "=== AFTER BOOTSTRAP.PHP NORMALIZATION ===" . PHP_EOL;
if (isset($ictUsers) && is_array($ictUsers)) {
    $isAssociativeIctUsers = array_keys($ictUsers) !== range(0, count($ictUsers) - 1);
    if ($isAssociativeIctUsers) {
        $normalizedIctUsers = [];
        foreach ($ictUsers as $email => $color) {
            $normalizedEmail = strtolower(trim((string) $email));
            if ($normalizedEmail === '') {
                continue;
            }

            $normalizedIctUsers[] = $normalizedEmail;
            $ictUserColors[$normalizedEmail] = trim((string) $color);
        }

        $ictUsers = array_values(array_unique($normalizedIctUsers));
    }
}

echo "Type: " . gettype($ictUsers) . PHP_EOL;
echo "Is associative: " . (array_keys($ictUsers) !== range(0, count($ictUsers) - 1) ? 'YES' : 'NO') . PHP_EOL;
echo "Content: " . implode(', ', $ictUsers) . PHP_EOL;
echo "Colors: " . implode(', ', array_keys($ictUserColors)) . PHP_EOL;
echo PHP_EOL;

// Test loop like in actions.php
echo "=== LOOP TEST (like in actions.php line 357) ===" . PHP_EOL;
echo "When doing: foreach (\$ictUsers as \$ictUser) {" . PHP_EOL;
foreach ($ictUsers as $ictUser) {
    echo "  - " . $ictUser . PHP_EOL;
}
echo PHP_EOL;

// Check what TicketStore constructor would do
echo "=== TICKETSTORE CONSTRUCTOR SIMULATION (line 23) ===" . PHP_EOL;
$processedIctUsers = array_values(array_unique(array_map('strtolower', $ictUsers)));
echo "Result: " . implode(', ', $processedIctUsers) . PHP_EOL;
echo PHP_EOL;
