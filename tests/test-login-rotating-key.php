<?php
/**
 * Persoonlijke key van de gedeelde login (sha256(oid|d-m-Y UTC)) voor ticket aanmaken.
 */

echo "=== TEST: persoonlijke login-key ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/api.php';
$_SERVER['HTTP_HOST'] = 'localhost';

$authPath = __DIR__ . '/../web/auth.php';
$createdStubAuth = false;
if (!is_file($authPath)) {
    file_put_contents($authPath, "<?php\n\$apiKeys = ['test-service-key'];\n\$ictUsers = ['ict@kvt.nl'];\n\$ictUserColors = [];\n\$mailSettings = [];\n\$grokBot = ['enabled' => false];\n");
    $createdStubAuth = true;
}
register_shutdown_function(static function () use ($authPath, $createdStubAuth): void {
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
});

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}
require __DIR__ . '/../web/api.php';

$passed = 0;
$failed = 0;
function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  ✗ {$label}" . PHP_EOL;
        $failed++;
    }
}

$oid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
$now = gmmktime(10, 0, 0, 10, 8, 2026);
$todayKey = hash('sha256', $oid . '|08-10-2026');
$yesterdayKey = hash('sha256', $oid . '|07-10-2026');
$oldKey = hash('sha256', $oid . '|06-10-2026');
$payload = ['oid' => $oid, 'user_email' => 'Tim@kvt.nl', 'title' => 'x'];

// Geen bekend oid in api_clients voor dit fictieve oid.
$client = resolveLoginRotatingApiClient($todayKey, $payload, [], $now);
check('key van vandaag geaccepteerd', is_array($client) && $client['email'] === 'tim@kvt.nl' && $client['kind'] === 'login_rotating' && $client['is_admin'] === false);
check('key van gisteren geaccepteerd', resolveLoginRotatingApiClient($yesterdayKey, $payload, [], $now) !== null);
check('key van eergisteren geweigerd', resolveLoginRotatingApiClient($oldKey, $payload, [], $now) === null);
check('key van ander oid geweigerd', resolveLoginRotatingApiClient($todayKey, ['oid' => 'ffffffff-bbbb-cccc-dddd-eeeeeeeeeeee', 'user_email' => 'tim@kvt.nl'], [], $now) === null);
check('zonder e-mail geweigerd', resolveLoginRotatingApiClient($todayKey, ['oid' => $oid], [], $now) === null);
check('zonder oid geweigerd', resolveLoginRotatingApiClient($todayKey, ['user_email' => 'tim@kvt.nl'], [], $now) === null);
check('headers werken ook', resolveLoginRotatingApiClient($todayKey, [], ['HTTP_X_USER_OID' => $oid, 'HTTP_X_USER_EMAIL' => 'tim@kvt.nl'], $now) !== null);
check('ongeldig formaat geweigerd', resolveLoginRotatingApiClient('test-service-key', $payload, [], $now) === null);

// Bekend oid met ander e-mailadres → geweigerd.
$dir = sys_get_temp_dir() . '/asclepius_api_clients_test_' . getmypid();
@mkdir($dir, 0700, true);
file_put_contents($dir . '/x.json', json_encode(['oid' => $oid, 'email' => 'tim@kvt.nl', 'api_key' => $todayKey]));
check('bekend oid → e-mail gevonden', findKnownApiClientEmailForOid($oid, $dir) === 'tim@kvt.nl');
check('onbekend oid → leeg', findKnownApiClientEmailForOid('ffffffff-0000-0000-0000-000000000000', $dir) === '');
@unlink($dir . '/x.json');
@rmdir($dir);

check('alleen POST zonder action is ticket aanmaken', isCreateTicketApiRequest([], ['REQUEST_METHOD' => 'POST']));
check('POST met action is geen ticket aanmaken', !isCreateTicketApiRequest(['action' => 'add_ticket_message'], ['REQUEST_METHOD' => 'POST']));
check('GET is geen ticket aanmaken', !isCreateTicketApiRequest([], ['REQUEST_METHOD' => 'GET']));

echo PHP_EOL . "Resultaat: {$passed} geslaagd, {$failed} gefaald" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
