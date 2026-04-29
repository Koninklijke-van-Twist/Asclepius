<?php
/**
 * Audit script to find all potential issues with $ictUsers usage
 */

echo "=== AUDIT: \$ictUsers USAGE ANALYSIS ===" . PHP_EOL . PHP_EOL;

$issues = [];

// Check 1: logincheck.php
echo "1. logincheck.php - Line 26:" . PHP_EOL;
echo "   array_any(\$ictUsers, function (\$email) {" . PHP_EOL;
echo "       return \$email == \$_SESSION['user']['email'];" . PHP_EOL;
echo "   })" . PHP_EOL;
echo "   EXPECTED: \$ictUsers must be a flat array with emails as VALUES" . PHP_EOL;
echo "   STATUS: Should be OK if bootstrap.php runs before this" . PHP_EOL;
echo "   NOTE: bootstrap.php line 65 does require logincheck.php AFTER normalizing" . PHP_EOL;
echo PHP_EOL;

// Check 2: actions.php line 357
echo "2. actions.php - Line 357 (in settings POST handler):" . PHP_EOL;
echo "   foreach (\$ictUsers as \$ictUser) {" . PHP_EOL;
echo "       \$ictUser = strtolower(\$ictUser);" . PHP_EOL;
echo "       ..." . PHP_EOL;
echo "   }" . PHP_EOL;
echo "   EXPECTED: \$ictUsers must be a flat array with emails as VALUES" . PHP_EOL;
echo "   PROBLEM: If \$ictUsers is associative, this loops over KEYS (emails), not VALUES (colors)" . PHP_EOL;
echo "   STATUS: Should be OK after normalization in variables.php" . PHP_EOL;
echo PHP_EOL;

// Check 3: view_settings.php line 28
echo "3. view_settings.php - Line 28:" . PHP_EOL;
echo "   foreach (\$ictUsers as \$ictUser):" . PHP_EOL;
echo "       \$ictUser = strtolower(\$ictUser);" . PHP_EOL;
echo "   EXPECTED: \$ictUsers must be a flat array with emails as VALUES" . PHP_EOL;
echo "   STATUS: Should be OK after normalization in variables.php" . PHP_EOL;
echo PHP_EOL;

// Check 4: view_tickets.php line 62  
echo "4. view_tickets.php - Line 62:" . PHP_EOL;
echo "   foreach (\$ictUsers as \$ictUser):" . PHP_EOL;
echo "       \$ictUser = strtolower(\$ictUser);" . PHP_EOL;
echo "   EXPECTED: \$ictUsers must be a flat array with emails as VALUES" . PHP_EOL;
echo "   STATUS: Should be OK after normalization in variables.php" . PHP_EOL;
echo PHP_EOL;

// Check 5: TicketStore constructor
echo "5. TicketStore::__construct() - Line 23:" . PHP_EOL;
echo "   \$this->ictUsers = array_values(array_unique(array_map('strtolower', \$ictUsers)));" . PHP_EOL;
echo "   EXPECTED: Can accept both flat and associative arrays" . PHP_EOL;
echo "   ANALYSIS: array_map works on VALUES for flat array, on VALUES for associative too" . PHP_EOL;
echo "   STATUS: ISSUE FOUND - Associative arrays passed here will only get COLOR VALUES!" . PHP_EOL;
echo "   RESULT: If \$ictUsers = ['email@kvt.nl' => '#color'], array_map will process '#color'" . PHP_EOL;
$issues[] = "TicketStore constructor receives associative array when it shouldn't";
echo PHP_EOL;

// Check 6: normalizeIctUsersConfig
echo "6. helpers.php - normalizeIctUsersConfig() normalization:" . PHP_EOL;
echo "   Line 46: \$ictUsers = array_keys(\$ictUserColors);" . PHP_EOL;
echo "   EXPECTED: Gets email keys from the color map" . PHP_EOL;
echo "   STATUS: Correct logic - extracts emails (keys) from associative array" . PHP_EOL;
echo "   But should result in flat array like: ['email1', 'email2', ...]" . PHP_EOL;
echo PHP_EOL;

// Check 7: bootstrap.php normalization vs helpers.php
echo "7. CRITICAL: Two different normalizations exist!" . PHP_EOL;
echo "   bootstrap.php (lines 18-38):" . PHP_EOL;
echo "       - Duplicates some normalization logic" . PHP_EOL;
echo "       - Runs BEFORE helpers.php is loaded" . PHP_EOL;
echo "   helpers.php normalizeIctUsersConfig() (lines 21-52):" . PHP_EOL;
echo "       - Called from variables.php line 22" . PHP_EOL;
echo "       - Called from api.php line 14" . PHP_EOL;
echo "   PROBLEM: If bootstrap normalizes correctly, why call again?" . PHP_EOL;
echo "   ANSWER: Because api.php doesn't use bootstrap.php!" . PHP_EOL;
echo PHP_EOL;

echo "=== SUMMARY OF ISSUES ===" . PHP_EOL;
echo "Total issues found: " . count($issues) . PHP_EOL;
foreach ($issues as $i => $issue) {
    echo ($i + 1) . ". " . $issue . PHP_EOL;
}
echo PHP_EOL;

echo "=== ROOT CAUSE HYPOTHESIS ===" . PHP_EOL;
echo "The problem likely occurs when:" . PHP_EOL;
echo "1. A code path receives \$ictUsers as associative array" . PHP_EOL;
echo "2. Passes it to a function expecting flat array WITHOUT normalization" . PHP_EOL;
echo "3. OR TicketStore constructor receives associative array by mistake" . PHP_EOL;
echo PHP_EOL;

echo "Checking data flow to TicketStore:" . PHP_EOL;
echo "- Web flow (index.php):" . PHP_EOL;
echo "  1. bootstrap.php normalizes \$ictUsers to flat" . PHP_EOL;
echo "  2. variables.php calls normalizeIctUsersConfig (redundant, \$ictUsers already flat)" . PHP_EOL;
echo "  3. variables.php line 110: new TicketStore(..., \$ictUsers, ...)" . PHP_EOL;
echo "  4. TicketStore gets flat array ✓" . PHP_EOL;
echo PHP_EOL;
echo "- API flow (api.php):" . PHP_EOL;
echo "  1. api.php requires auth.php (\$ictUsers = associative!)" . PHP_EOL;
echo "  2. api.php line 14: normalizeIctUsersConfig(\$ictUsers, ...)" . PHP_EOL;
echo "  3. api.php line 391: new TicketStore(..., \$ictUsers, ...)" . PHP_EOL;
echo "  4. TicketStore gets flat array ✓" . PHP_EOL;
echo PHP_EOL;

echo "Both flows should work... unless normalizeIctUsersConfig has a bug?" . PHP_EOL;
