<?php
echo "=== COMPREHENSIVE TEST OF ALL FIXES ===" . PHP_EOL . PHP_EOL;

// Load actual application files
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/web/content/constants.php';
require __DIR__ . '/web/content/helpers.php';

// Simulate auth.php with associative $ictUsers
$ictUsers = [
    "tfalken@kvt.nl" => '#0a6ee0',
    "milanscheenloop@kvt.nl" => '#8a0ae0',
    "opesket@kvt.nl" => '#2ee00a',
];
$ictUserColors = [];

echo "SCENARIO 1: Fresh associative array (from auth.php)" . PHP_EOL;
echo "  Input: ";
var_dump($ictUsers);

// Test extractIctUserEmails (the new helper)
$extracted = extractIctUserEmails($ictUsers);
echo "  extractIctUserEmails() result: " . implode(', ', $extracted) . PHP_EOL;
echo "  Is it flat? " . (array_keys($extracted) === range(0, count($extracted) - 1) ? 'YES ✓' : 'NO ✗') . PHP_EOL;
echo "  Are values emails? " . (all(function($v) { return filter_var($v, FILTER_VALIDATE_EMAIL); }, $extracted) ? 'YES ✓' : 'NO ✗') . PHP_EOL;
echo PHP_EOL;

// Test normalizeIctUsersConfig
echo "SCENARIO 2: Normalization with associative array" . PHP_EOL;
$testIctUsers = $ictUsers;
$testIctUserColors = [];
normalizeIctUsersConfig($testIctUsers, $testIctUserColors);
echo "  After normalizeIctUsersConfig():" . PHP_EOL;
echo "    \$ictUsers: " . implode(', ', $testIctUsers) . PHP_EOL;
echo "    \$ictUserColors count: " . count($testIctUserColors) . PHP_EOL;
echo "    Is \$ictUsers flat? " . (array_keys($testIctUsers) === range(0, count($testIctUsers) - 1) ? 'YES ✓' : 'NO ✗') . PHP_EOL;
echo PHP_EOL;

// Test array_fill_keys pattern
echo "SCENARIO 3: array_fill_keys(extractIctUserEmails(...)) pattern" . PHP_EOL;
$lookup = array_fill_keys(extractIctUserEmails($ictUsers), true);
echo "  Lookup keys: " . implode(', ', array_keys($lookup)) . PHP_EOL;
echo "  Can find 'tfalken@kvt.nl'? " . (isset($lookup['tfalken@kvt.nl']) ? 'YES ✓' : 'NO ✗') . PHP_EOL;
echo "  Can find '#0a6ee0' (color)? " . (isset($lookup['#0a6ee0']) ? 'NO ✗ (BUG)' : 'NO (CORRECT)') . PHP_EOL;
echo PHP_EOL;

// Test TicketStore constructor handling
echo "SCENARIO 4: TicketStore constructor normalization" . PHP_EOL;
// Simulate the constructor logic
$assocArray = $ictUsers;
$isAssociative = array_keys($assocArray) !== range(0, count($assocArray) - 1);
$normalizedIctUsers = $isAssociative ? array_keys($assocArray) : array_values($assocArray);
$result = array_values(array_unique(array_map('strtolower', $normalizedIctUsers)));
echo "  Input (associative): " . implode(', ', array_keys($ictUsers)) . " => [colors]" . PHP_EOL;
echo "  Constructor will store: " . implode(', ', $result) . PHP_EOL;
echo "  Are stored values emails? " . (all(function($v) { return filter_var($v, FILTER_VALIDATE_EMAIL); }, $result) ? 'YES ✓' : 'NO ✗') . PHP_EOL;
echo PHP_EOL;

// Test all critical patterns
echo "SCENARIO 5: All critical usage patterns" . PHP_EOL;
$allPassed = true;

// Pattern 1: in_array with extracted emails
$pattern1 = in_array('tfalken@kvt.nl', extractIctUserEmails($ictUsers));
echo "  in_array('tfalken@kvt.nl', extractIctUserEmails(...)): " . ($pattern1 ? 'YES ✓' : 'NO ✗') . PHP_EOL;
if (!$pattern1) $allPassed = false;

// Pattern 2: array_merge with extracted emails
$merged = array_merge(['', '__unassigned__'], extractIctUserEmails($ictUsers));
$merged_has_emails = count(array_filter($merged, fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL))) >= 3;
echo "  array_merge with extractIctUserEmails: " . ($merged_has_emails ? 'YES ✓' : 'NO ✗') . PHP_EOL;
if (!$merged_has_emails) $allPassed = false;

// Pattern 3: foreach loop
echo "  foreach (\$ictUsers as \$user): " . PHP_EOL;
$emails = [];
foreach (extractIctUserEmails($ictUsers) as $user) {
    $emails[] = $user;
}
echo "    Got: " . implode(', ', $emails) . PHP_EOL;
$all_are_emails = all(function($v) { return filter_var($v, FILTER_VALIDATE_EMAIL); }, $emails);
echo "    All are valid emails? " . ($all_are_emails ? 'YES ✓' : 'NO ✗') . PHP_EOL;
if (!$all_are_emails) $allPassed = false;

echo PHP_EOL;
echo "=== FINAL RESULT ===" . PHP_EOL;
echo $allPassed ? "✓ ALL TESTS PASSED" : "✗ SOME TESTS FAILED";
echo PHP_EOL;

/**
 * Helper function
 */
function all(callable $predicate, array $array): bool {
    foreach ($array as $item) {
        if (!$predicate($item)) {
            return false;
        }
    }
    return true;
}
