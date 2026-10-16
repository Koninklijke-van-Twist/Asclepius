<?php
/**
 * odata.php-acties zijn niet anoniem, cache_status lekt geen voorbeeldrijen,
 * en data/cache staan dicht zonder de ticketdatabase te deployen.
 * Run: php tests/test-odata-action-access.php
 */

require dirname(__DIR__) . '/web/odata.php';
require dirname(__DIR__) . '/web/ticket_upload.php';
require dirname(__DIR__) . '/web/content/helpers.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function assert_same($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fail($label . ' verwacht ' . var_export($expected, true) . ' kreeg ' . var_export($actual, true));
    }
}

function assert_contains(string $haystack, string $needle, string $label): void
{
    if (strpos($haystack, $needle) === false) {
        fail($label . ' mist "' . $needle . '"');
    }
}

function assert_not_contains(string $haystack, string $needle, string $label): void
{
    if (strpos($haystack, $needle) !== false) {
        fail($label . ' bevat "' . $needle . '"');
    }
}

$root = dirname(__DIR__);
$odataSource = (string) file_get_contents($root . '/web/odata.php');
$dispatch = substr($odataSource, (int) strrpos($odataSource, '$odataAction = '));
$requireAt = strpos($dispatch, 'odata_require_direct_action_access()');
$statusAt = strpos($dispatch, 'odata_send_cache_status_json()');
$deleteAt = strpos($dispatch, 'odata_send_cache_delete_json()');
$clearAt = strpos($dispatch, 'odata_send_cache_clear_json()');
if ($requireAt === false || $statusAt === false || $deleteAt === false || $clearAt === false) {
    fail('actie-dispatch mist login of een cache-actie');
}
if ($requireAt > $statusAt || $requireAt > $deleteAt || $requireAt > $clearAt) {
    fail('logincheck moet vóór cache_status, cache_delete en cache_clear');
}
$gateStart = strpos($odataSource, 'function odata_require_direct_action_access');
$gateEnd = strpos($odataSource, 'function odata_release_session_lock');
if ($gateStart === false || $gateEnd === false || $gateEnd < $gateStart) {
    fail('login-gate of sessie-vrijgave ontbreekt');
}
$gate = substr($odataSource, $gateStart, $gateEnd - $gateStart);
$emailAt = strpos($gate, 'odata_email_is_allowed');
$releaseAt = strpos($gate, 'odata_release_session_lock()');
if ($emailAt === false || $releaseAt === false || $releaseAt < $emailAt) {
    fail('sessielock moet vrij na de logincheck, vóór het lezen van de cache');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['asclepius_access_probe'] = 1;
odata_release_session_lock();
if (session_status() === PHP_SESSION_ACTIVE) {
    fail('actieve sessie blijft gelockt');
}

assert_contains($odataSource, 'php_sapi_name()', 'cli-check');
assert_contains($odataSource, "=== 'cli'", 'cli-vergelijking');
assert_contains($odataSource, 'login/lib.php', 'login-lib');
assert_contains($odataSource, 'HTTP_X_ASCLEPIUS_SECRET', 'eigen runner-header');
assert_contains($odataSource, 'ASCLEPIUS_RUNNER_SECRET', 'eigen runner-env');
assert_contains($odataSource, 'asclepiusRunnerSecret', 'eigen runner-variabele');
assert_not_contains($odataSource, 'is_trusted_requester', 'geen localhost-bypass');
assert_not_contains($odataSource, 'X-Hermes-Secret', 'geen Hermes-header');
assert_not_contains($odataSource, 'HTTP_X_HERMES_SECRET', 'geen Hermes-servervar');
assert_not_contains($odataSource, 'odata_cache_extract_attributes_from_payload', 'geen voorbeeldrij-extractor');
assert_not_contains($odataSource, 'entry.attributes', 'widget toont geen voorbeeldrij');

$allowed = [
    'analyst@kvt.nl',
    'planner@kvt.nl' => [15, 40],
];
assert_same(true, odata_email_is_allowed('Analyst@kvt.nl', $allowed), 'lijst-gebruiker');
assert_same(true, odata_email_is_allowed('planner@kvt.nl', $allowed), 'map-gebruiker');
assert_same(false, odata_email_is_allowed('andere@kvt.nl', $allowed), 'niet op de lijst');
assert_same(false, odata_email_is_allowed('', $allowed), 'lege sessie');
assert_same(false, odata_email_is_allowed('analyst@kvt.nl', null), 'geen whitelist');

assert_same(true, odata_direct_action_skips_login('cli', '', ''), 'cli zonder geheim');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', '', ''), 'web anoniem');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', '', 'server-secret'), 'web zonder header');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', 'server-secret', ''), 'leeg servergeheim');
assert_same(false, odata_direct_action_skips_login('fpm-fcgi', 'nope', 'server-secret'), 'fout geheim');
assert_same(true, odata_direct_action_skips_login('cgi-fcgi', 'server-secret', 'server-secret'), 'bestaand servergeheim');
assert_same(false, odata_direct_action_skips_login('apache2handler', 'server-secret', 'ander-geheim'), 'geheim komt niet overeen');

$savedHeader = $_SERVER['HTTP_X_ASCLEPIUS_SECRET'] ?? null;
$savedHermesHeader = $_SERVER['HTTP_X_HERMES_SECRET'] ?? null;
$_SERVER['HTTP_X_ASCLEPIUS_SECRET'] = '  uit-de-header  ';
$_SERVER['HTTP_X_HERMES_SECRET'] = 'hermes-mag-niet';
assert_same('uit-de-header', odata_presented_runner_secret(), 'header, geen query');
if ($savedHeader === null) {
    unset($_SERVER['HTTP_X_ASCLEPIUS_SECRET']);
} else {
    $_SERVER['HTTP_X_ASCLEPIUS_SECRET'] = $savedHeader;
}
if ($savedHermesHeader === null) {
    unset($_SERVER['HTTP_X_HERMES_SECRET']);
} else {
    $_SERVER['HTTP_X_HERMES_SECRET'] = $savedHermesHeader;
}

$authPath = sys_get_temp_dir() . '/asclepius-auth-runner-secret.php';
file_put_contents($authPath, "<?php\n\$asclepiusRunnerSecret = \"auth-php-geheim\";\n\$allowedUsers = ['nacht@kvt.nl'];\n");
$previousAuthPath = $GLOBALS['ASCLEPIUS_AUTH_PHP_PATH'] ?? null;
$previousRunner = $GLOBALS['asclepiusRunnerSecret'] ?? null;
unset($GLOBALS['asclepiusRunnerSecret']);
$previousEnv = getenv('ASCLEPIUS_RUNNER_SECRET');
putenv('ASCLEPIUS_RUNNER_SECRET');
$GLOBALS['ASCLEPIUS_AUTH_PHP_PATH'] = $authPath;
$fromFile = odata_read_auth_file_vars();
assert_same('auth-php-geheim', odata_configured_runner_secret($fromFile), 'geheim uit auth.php');
assert_same(true, odata_email_is_allowed('nacht@kvt.nl', $fromFile['allowedUsers'] ?? null), 'whitelist uit auth.php');
putenv('ASCLEPIUS_RUNNER_SECRET=env-geheim');
assert_same('auth-php-geheim', odata_configured_runner_secret($fromFile), 'auth.php wint van een lege global');
$GLOBALS['asclepiusRunnerSecret'] = 'global-geheim';
assert_same('global-geheim', odata_configured_runner_secret($fromFile), 'global wint');
unset($GLOBALS['asclepiusRunnerSecret']);
putenv('ASCLEPIUS_RUNNER_SECRET');
if ($previousAuthPath === null) {
    unset($GLOBALS['ASCLEPIUS_AUTH_PHP_PATH']);
} else {
    $GLOBALS['ASCLEPIUS_AUTH_PHP_PATH'] = $previousAuthPath;
}
if ($previousRunner !== null) {
    $GLOBALS['asclepiusRunnerSecret'] = $previousRunner;
}
if ($previousEnv !== false && $previousEnv !== '') {
    putenv('ASCLEPIUS_RUNNER_SECRET=' . $previousEnv);
}
@unlink($authPath);

$cacheDir = cache_base_dir();
$fixtureName = 'asclepius-access-test-' . bin2hex(random_bytes(8)) . '.json';
$fixturePath = $cacheDir . '/' . $fixtureName;
$customerNo = 'C-SECRET-44021';
$customerName = 'Voorbeeld Klant Jansen';
$documentNo = 'VF-998877';
$sourceUrl = "https://bc.example:7148/Production/ODataV4/Company('KVT')/Customer_Ledger_Entries";
$fixture = [
    '_meta' => [
        'cached_at' => time() - 60,
        'expires_at' => time() + 86400,
        'source_url' => $sourceUrl,
        'fetched' => true,
    ],
    'data' => [[
        'Customer_No' => $customerNo,
        'Customer_Name' => $customerName,
        'Document_No' => $documentNo,
        'Amount' => 12345.67,
        '@odata.etag' => 'W/"example"',
    ]],
];
file_put_contents($fixturePath, json_encode($fixture, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

try {
    $payload = odata_cache_status_payload();
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        fail('cache_status payload is geen JSON');
    }
    assert_not_contains($encoded, $customerNo, 'cache_status');
    assert_not_contains($encoded, $customerName, 'cache_status');
    assert_not_contains($encoded, $documentNo, 'cache_status');
    assert_not_contains($encoded, '12345.67', 'cache_status');
    assert_not_contains($encoded, 'attributes', 'cache_status');
    assert_not_contains($encoded, 'Customer_Name', 'cache_status');

    $match = null;
    foreach ($payload['entries'] as $entry) {
        if (($entry['id'] ?? '') === $fixtureName) {
            $match = $entry;
            break;
        }
    }
    if (!is_array($match)) {
        fail('fixture ontbreekt in cache_status');
    }
    if (array_key_exists('attributes', $match) || array_key_exists('data', $match)) {
        fail('cache_status-entry bevat rijdata');
    }
    assert_same($sourceUrl, $match['url'] ?? '', 'bron-url voor ingelogde widget');
    if ((int) ($match['size_bytes'] ?? 0) <= 0) {
        fail('cache_status mist bestandsgrootte');
    }
} finally {
    @unlink($fixturePath);
}

$widget = injectTimerHtml([
    'statusUrl' => 'odata.php?action=cache_status',
]);
assert_not_contains($widget, 'attributes', 'widget-html');
assert_contains($widget, 'odata.php?action=cache_status', 'widget houdt status-url');

$protectedDirs = [
    $root . '/web/cache',
    $root . '/web/cache/odata',
    $root . '/web/data',
    $root . '/web/data/ticket_uploads',
    $root . '/web/data/api_clients',
    $root . '/web/data/theevraagje',
    $root . '/web/data/user_prefs',
];
foreach ($protectedDirs as $dir) {
    $htaccess = $dir . '/.htaccess';
    $rules = (string) file_get_contents($htaccess);
    assert_contains($rules, 'Options -Indexes', $htaccess);
    assert_contains($rules, 'Require all denied', $htaccess);
    assert_contains($rules, 'Deny from all', $htaccess);
    assert_contains($rules, '<IfModule mod_authz_core.c>', $htaccess);
    assert_contains($rules, '<IfModule !mod_authz_core.c>', $htaccess);
    foreach (['index.html', 'index.php'] as $indexName) {
        $indexFile = $dir . '/' . $indexName;
        if (!is_file($indexFile)) {
            fail('ontbrekend indexbestand ' . $indexFile);
        }
        $indexBody = (string) file_get_contents($indexFile);
        if (strlen($indexBody) > 16) {
            fail('indexbestand is niet leeg: ' . $indexFile);
        }
    }
}

$workflow = (string) file_get_contents($root . '/.github/workflows/deploy-ftp.yml');
$includeAt = strpos($workflow, '--include-glob cache/.htaccess');
$dataIncludeAt = strpos($workflow, '--include-glob data/.htaccess');
$excludeCacheAt = strpos($workflow, '--exclude-glob cache/**');
$excludeDataAt = strpos($workflow, '--exclude-glob data/**');
if ($includeAt === false || $dataIncludeAt === false || $excludeCacheAt === false || $excludeDataAt === false) {
    fail('ftp-deploy mist data- of cache-patronen');
}
if ($includeAt < $excludeCacheAt || $dataIncludeAt < $excludeDataAt) {
    fail('.htaccess moet ná de map-excludes staan, anders uploadt lftp hem niet');
}
if (strpos($workflow, '--delete-excluded') !== false) {
    fail('ftp-deploy mag uitgesloten data niet verwijderen');
}
foreach ([
    '--include-glob cache/index.html',
    '--include-glob cache/index.php',
    '--include-glob cache/odata/.htaccess',
    '--include-glob data/ticket_uploads/.htaccess',
    '--include-glob data/api_clients/.htaccess',
    '--include-glob data/theevraagje/.htaccess',
    '--include-glob data/user_prefs/.htaccess',
    '--exclude-glob data/**',
    '--exclude-glob auth.php',
    '--exclude-glob .htaccess',
] as $needle) {
    assert_contains($workflow, $needle, 'ftp-deploy');
}
assert_not_contains($workflow, 'asclepius.sqlite', 'ftp-deploy uploadt de database niet');

$uploadSource = (string) file_get_contents($root . '/web/ticket_upload.php');
$handler = substr($uploadSource, (int) strrpos($uploadSource, 'if (!ticket_upload_is_direct_request())'));
$apiKeyAt = strpos($handler, 'getApiKeyFromRequest()');
$loginAt = strpos($handler, 'login/lib.php');
$sessionAt = strpos($handler, 'ticket_upload_release_session_lock()');
if ($apiKeyAt === false || $loginAt === false || $sessionAt === false || $apiKeyAt > $loginAt || $sessionAt < $loginAt) {
    fail('bijlage-endpoint moet de API-sleutel vóór de login controleren en daarna de sessie sluiten');
}
assert_contains($uploadSource, 'ASCLEPIUS_API_SKIP_ROUTER', 'api.php-router blijft uit');
assert_contains($uploadSource, 'isValidApiKey', 'bestaande API-sleutelcontrole');
assert_not_contains($uploadSource, 'logincheck.php', 'bijlagen hangen niet aan logincheck.php');

$directWithId = buildAttachmentDirectUrl([
    'id' => 4711,
    'ticket_id' => 123,
    'stored_name' => 'ticket_abc.png',
    'stored_path' => '/var/data/ticket_uploads/123/ticket_abc.png',
]);
assert_same('ticket_upload.php?id=4711', $directWithId, 'bijlage-url via endpoint');
assert_not_contains($directWithId, 'data/ticket_uploads', 'bijlage-url');
$directWithoutId = buildAttachmentDirectUrl([
    'ticket_id' => 123,
    'stored_name' => 'ticket_abc.png',
]);
assert_same('ticket_upload.php?ticket=123&file=ticket_abc.png', $directWithoutId, 'bijlage-url zonder id');
assert_same('', buildAttachmentDirectUrl(['stored_name' => '../asclepius.sqlite']), 'geen pad buiten de upload');

$uploadRoot = sys_get_temp_dir() . '/asclepius-upload-guard-' . bin2hex(random_bytes(4));
$ticketDir = $uploadRoot . '/9';
mkdir($ticketDir, 0777, true);
$safeName = 'ticket_abc123.png';
file_put_contents($ticketDir . '/' . $safeName, 'png');
$outside = $uploadRoot . '/asclepius.sqlite';
file_put_contents($outside, 'sqlite');
$resolved = ticket_upload_resolve_file([
    'ticket_id' => 9,
    'stored_name' => $safeName,
    'stored_path' => $ticketDir . '/' . $safeName,
], $uploadRoot);
if ($resolved === null || !str_ends_with($resolved, $safeName)) {
    fail('veilige bijlage wordt niet gevonden');
}
assert_same(null, ticket_upload_resolve_file([
    'ticket_id' => 9,
    'stored_name' => '../asclepius.sqlite',
    'stored_path' => $outside,
], $uploadRoot), 'path traversal');
assert_same(false, ticket_upload_stored_name_is_safe('../asclepius.sqlite'), 'bestandsnaam met traversal');
assert_same(true, ticket_upload_api_may_read(true, null, true), 'service-key mag ghost');
assert_same(false, ticket_upload_api_may_read(false, ['is_admin' => false], true), 'gewone key geen ghost');
assert_same(true, ticket_upload_api_may_read(false, ['is_admin' => false], false), 'gewone key mag gewone bijlage');
@unlink($ticketDir . '/' . $safeName);
@unlink($outside);
@rmdir($ticketDir);
@rmdir($uploadRoot);

$phpCgi = trim((string) shell_exec('command -v php-cgi 2>/dev/null'));
if ($phpCgi !== '') {
    $script = sys_get_temp_dir() . '/asclepius-odata-cgi-' . bin2hex(random_bytes(4)) . '.php';
    $odataFile = $root . '/web/odata.php';
    file_put_contents($script, "<?php\n"
        . '$_SERVER["SCRIPT_FILENAME"] = ' . var_export($odataFile, true) . ";\n"
        . '$_SERVER["SCRIPT_NAME"] = "/asclepius/odata.php";' . "\n"
        . '$_SERVER["PHP_SELF"] = "/asclepius/odata.php";' . "\n"
        . '$_SERVER["REMOTE_ADDR"] = "203.0.113.50";' . "\n"
        . '$_SERVER["SERVER_ADDR"] = "198.51.100.10";' . "\n"
        . '$_GET["action"] = "cache_status";' . "\n"
        . 'require ' . var_export($odataFile, true) . ";\n");
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open([$phpCgi, '-q', '-d', 'display_errors=0', $script], $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        @unlink($script);
        fail('php-cgi startte niet');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);
    @unlink($script);
    $stdout = is_string($stdout) ? $stdout : '';
    $stderr = is_string($stderr) ? $stderr : '';
    if ($exitCode !== 0 && strpos($stdout, 'Geen toegang') === false) {
        fail('anonieme cgi-actie exit ' . $exitCode . ' ' . $stderr . ' ' . $stdout);
    }
    assert_contains($stdout, 'Geen toegang', 'anonieme cgi-actie');
    assert_not_contains($stdout, $customerNo, 'anonieme cgi-actie');
    assert_not_contains($stdout, $customerName, 'anonieme cgi-actie');
    assert_not_contains($stdout, 'Customer_Ledger_Entries', 'anonieme cgi-actie');
}

echo "OK odata action access\n";
