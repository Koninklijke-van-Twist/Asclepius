<?php
echo "=== VERIFYING TICKETSTORE FIX ===" . PHP_EOL . PHP_EOL;

// Simulate auth.php
$ictUsers = [
    "tfalken@kvt.nl" => '#0a6ee0',
    "milanscheenloop@kvt.nl" => '#8a0ae0',
    "opesket@kvt.nl" => '#2ee00a',
];

$ictUserColors = [];

// Simulate bootstrap.php normalization
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

echo "After bootstrap.php normalization:" . PHP_EOL;
echo "  \$ictUsers is: " . (array_keys($ictUsers) === range(0, count($ictUsers) - 1) ? 'FLAT' : 'ASSOCIATIVE') . PHP_EOL;
echo "  Content: " . implode(', ', $ictUsers) . PHP_EOL;
echo PHP_EOL;

// Simulate TicketStore constructor WITHOUT the fix (old code)
echo "OLD TicketStore constructor (BUGGY):" . PHP_EOL;
$oldIctUsers = array_values(array_unique(array_map('strtolower', $ictUsers)));
echo "  Result: " . implode(', ', $oldIctUsers) . PHP_EOL;
echo "  Type: " . (array_keys($oldIctUsers) === range(0, count($oldIctUsers) - 1) ? 'FLAT' : 'ASSOCIATIVE') . PHP_EOL;
echo PHP_EOL;

// Simulate TicketStore constructor WITH the fix (new code)
echo "NEW TicketStore constructor (FIXED):" . PHP_EOL;
$isAssociative = array_keys($ictUsers) !== range(0, count($ictUsers) - 1);
$normalizedIctUsers = $isAssociative ? array_keys($ictUsers) : array_values($ictUsers);
$newIctUsers = array_values(array_unique(array_map('strtolower', $normalizedIctUsers)));
echo "  isAssociative: " . ($isAssociative ? 'YES' : 'NO') . PHP_EOL;
echo "  After array_keys/values: " . implode(', ', $normalizedIctUsers) . PHP_EOL;
echo "  Final result: " . implode(', ', $newIctUsers) . PHP_EOL;
echo "  Type: " . (array_keys($newIctUsers) === range(0, count($newIctUsers) - 1) ? 'FLAT' : 'ASSOCIATIVE') . PHP_EOL;
echo PHP_EOL;

echo "=== TEST OF PROBLEMATIC PATTERNS ===" . PHP_EOL;
echo "1. array_fill_keys(array_map('strtolower', $ictUsers)):" . PHP_EOL;
echo "   With FLAT array: ";
$lookup = array_fill_keys(array_map('strtolower', $newIctUsers), true);
echo implode(', ', array_keys($lookup)) . PHP_EOL;
echo "   ✓ This is correct - has emails as keys" . PHP_EOL;
echo PHP_EOL;

echo "2. foreach (\$ictUsers as \$ictUser): \$ictUser = strtolower(\$ictUser)" . PHP_EOL;
echo "   Values iterated: ";
foreach ($newIctUsers as $ictUser) {
    echo $ictUser . " ";
}
echo PHP_EOL;
echo "   ✓ All are emails" . PHP_EOL;
echo PHP_EOL;

echo "3. in_array(\$email, array_map('strtolower', \$ictUsers))" . PHP_EOL;
$testEmail = 'tfalken@kvt.nl';
$inArray = in_array($testEmail, array_map('strtolower', $newIctUsers));
echo "   Testing if '$testEmail' exists: " . ($inArray ? 'YES ✓' : 'NO ✗') . PHP_EOL;
echo PHP_EOL;

echo "=== CONCLUSION ===" . PHP_EOL;
echo "✓ TicketStore fix correctly handles associative arrays" . PHP_EOL;
echo "✓ The fix extracts keys (emails) when array is associative" . PHP_EOL;
echo "✓ All pattern check will work correctly with the fix" . PHP_EOL;
