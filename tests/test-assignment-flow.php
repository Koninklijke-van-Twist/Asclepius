<?php
/**
 * Comprehensive flow test for automatic ticket assignment
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

// Load the actual application
require __DIR__ . '/web/content/bootstrap.php';
require __DIR__ . '/web/content/constants.php';
require __DIR__ . '/web/content/localization.php';
require __DIR__ . '/web/content/helpers.php';

echo "=== TESTING AUTOMATIC TICKET ASSIGNMENT FLOW ===" . PHP_EOL;
echo PHP_EOL;

echo "1. Initial \$ictUsers after bootstrap:" . PHP_EOL;
echo "   Type: " . gettype($ictUsers) . PHP_EOL;
echo "   Count: " . count($ictUsers) . PHP_EOL;
echo "   Values: " . implode(', ', array_slice($ictUsers, 0, 2)) . (count($ictUsers) > 2 ? ', ...' : '') . PHP_EOL;
echo "   Is flat array: " . (array_keys($ictUsers) === range(0, count($ictUsers) - 1) ? 'YES' : 'NO') . PHP_EOL;
echo PHP_EOL;

// Create a test database
$testDbPath = sys_get_temp_dir() . '/test_tickets_' . uniqid() . '.db';
$uploadDir = sys_get_temp_dir() . '/test_uploads_' . uniqid();
@mkdir($uploadDir, 0755, true);

echo "2. Creating test TicketStore..." . PHP_EOL;
try {
    $store = new TicketStore($testDbPath, $uploadDir, $ictUsers, TICKET_CATEGORIES);
    echo "   ✓ TicketStore created successfully" . PHP_EOL;
} catch (Exception $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
echo PHP_EOL;

echo "3. Getting availability for ICT users:" . PHP_EOL;
$availability = $store->getIctUserAvailability();
if (empty($availability)) {
    echo "   WARNING: No availability data found. This is expected for a new database." . PHP_EOL;
    echo "   The database needs to be populated via the admin settings page first." . PHP_EOL;
} else {
    foreach (array_slice($availability, 0, 3) as $email => $isAvail) {
        echo "   - $email: " . ($isAvail ? 'Available' : 'Away') . PHP_EOL;
    }
    if (count($availability) > 3) {
        echo "   ... and " . (count($availability) - 3) . " more" . PHP_EOL;
    }
}
echo PHP_EOL;

echo "4. Getting category settings matrix:" . PHP_EOL;
$matrix = $store->getCategorySettings();
if (empty($matrix)) {
    echo "   WARNING: Matrix is empty. This is expected for a new database." . PHP_EOL;
} else {
    $matrixSize = count($matrix);
    echo "   Users in matrix: $matrixSize" . PHP_EOL;
    foreach (array_slice($matrix, 0, 1) as $email => $categories) {
        echo "   Example: $email has " . count($categories) . " categories" . PHP_EOL;
        break;
    }
}
echo PHP_EOL;

echo "5. Testing pickAssignee (would need populated settings to work):" . PHP_EOL;
// Note: pickAssignee is private, so we can't call it directly
// But we can check if the database structure is correct
$pdo = (function() {
    $reflection = new ReflectionClass('TicketStore');
    $property = $reflection->getProperty('pdo');
    $property->setAccessible(true);
    return $property->getValue($store);
})();

if ($pdo) {
    echo "   PDO connection: OK" . PHP_EOL;
    try {
        $result = $pdo->query('SELECT COUNT(*) as cnt FROM ict_user_availability')->fetch(PDO::FETCH_ASSOC);
        echo "   ICT user availability records: " . $result['cnt'] . PHP_EOL;
        
        $result = $pdo->query('SELECT COUNT(*) as cnt FROM ict_user_category_settings')->fetch(PDO::FETCH_ASSOC);
        echo "   Category settings records: " . $result['cnt'] . PHP_EOL;
    } catch (Exception $e) {
        echo "   Query error: " . $e->getMessage() . PHP_EOL;
    }
}
echo PHP_EOL;

echo "6. DIAGNOSIS:" . PHP_EOL;
if (count($availability) === 0 && count($matrix) === 0) {
    echo "   ⚠ Database has no ICT user settings." . PHP_EOL;
    echo "   ✓ This is NORMAL for first run after changing auth.php structure." . PHP_EOL;
    echo "   ACTION: Admin must visit Settings page and save availability/category preferences." . PHP_EOL;
} else {
    echo "   ✓ Database appears to have user settings." . PHP_EOL;
    echo "   The issue might be elsewhere." . PHP_EOL;
}
echo PHP_EOL;

// Cleanup
@unlink($testDbPath);
@rmdir($uploadDir);

echo "=== TEST COMPLETE ===" . PHP_EOL;
