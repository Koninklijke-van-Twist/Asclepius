<?php

/**
 * Sessie-keepalive: een open pagina ververst de PHP-sessie, zodat die
 * langer leeft dan session.gc_maxlifetime. De test gebruikt korte
 * lifetime-waarden en de echte keepalive-functies plus het endpoint.
 */

ob_start();
register_shutdown_function(static function (): void {
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
});

echo "=== TEST: sessie-keepalive ===" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function assertTrue(string $label, bool $actual): void
{
    global $passed, $failed;
    if ($actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "  ✗ {$label}" . PHP_EOL;
    $failed++;
}

function assertSame(string $label, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "  ✗ {$label}" . PHP_EOL;
    echo '      Verwacht : ' . var_export($expected, true) . PHP_EOL;
    echo '      Gekregen : ' . var_export($actual, true) . PHP_EOL;
    $failed++;
}

/**
 * @return list<string> basenames van verwijderde sessiebestanden
 */
function expireOldSessionFiles(string $savePath, int $maxLifetime): array
{
    $removed = [];
    $now = time();
    foreach (glob($savePath . '/sess_*') ?: [] as $file) {
        if (!is_string($file) || !is_file($file)) {
            continue;
        }

        clearstatcache(true, $file);
        $age = $now - (int) filemtime($file);
        if ($age > $maxLifetime) {
            unlink($file);
            $removed[] = basename($file);
        }
    }

    return $removed;
}

function configureKeepaliveSession(string $savePath, int $maxLifetime): void
{
    ini_set('session.save_path', $savePath);
    ini_set('session.gc_maxlifetime', (string) $maxLifetime);
    ini_set('session.cookie_lifetime', (string) $maxLifetime);
    ini_set('session.gc_probability', '0');
    ini_set('session.use_strict_mode', '0');
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name('ASCLEPIUSSESS');
}

/**
 * @param array<string, string> $server
 * @return array{code: int, stdout: string, stderr: string}
 */
function runPhp(string $script, array $ini, array $server = []): array
{
    unset($server);
    $command = escapeshellarg(PHP_BINARY);
    foreach ($ini as $key => $value) {
        $command .= ' -d ' . escapeshellarg($key . '=' . $value);
    }
    $command .= ' ' . escapeshellarg($script);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['code' => 1, 'stdout' => '', 'stderr' => 'proc_open mislukt'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);

    return [
        'code' => $code,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
}

require_once __DIR__ . '/../web/content/session_keepalive.php';
require_once __DIR__ . '/../web/content/constants.php';

assertSame('zichtbaar interval is 4 minuten', 240, SESSION_KEEPALIVE_INTERVAL_SECONDS);
assertTrue(
    'verborgen interval is trager dan het zichtbare',
    SESSION_KEEPALIVE_HIDDEN_INTERVAL_SECONDS > SESSION_KEEPALIVE_INTERVAL_SECONDS
);

$savePath = sys_get_temp_dir() . '/asclepius-keepalive-' . bin2hex(random_bytes(4));
if (!mkdir($savePath, 0700, true) && !is_dir($savePath)) {
    echo "FAIL: kon sessiemap niet maken." . PHP_EOL;
    exit(1);
}

$maxLifetime = 6;
configureKeepaliveSession($savePath, $maxLifetime);

echo "Cookie-levensduur" . PHP_EOL;

$sessionId = bin2hex(random_bytes(16));
session_id($sessionId);
session_start();
$_SESSION['user'] = ['email' => 'ict@kvt.nl'];
$fixedNow = 1_700_000_000;
$cookie = asclepiusTouchSessionKeepalive($fixedNow);
assertSame('timestamp in de sessie', $fixedNow, (int) ($_SESSION['last_keepalive_at'] ?? 0));
assertTrue('cookie wordt verlengd als lifetime > 0', is_array($cookie));
assertSame('nieuwe cookie-eindtijd', $fixedNow + $maxLifetime, (int) ($cookie['expires'] ?? 0));
assertSame('cookie houdt het sessie-id', $sessionId, (string) ($cookie['value'] ?? ''));
assertTrue('cookie blijft httponly', !empty($cookie['httponly']));
session_write_close();

ini_set('session.cookie_lifetime', '0');
$browserSessionId = bin2hex(random_bytes(16));
session_id($browserSessionId);
session_start();
$_SESSION['user'] = ['email' => 'ict@kvt.nl'];
$browserCookie = asclepiusSessionCookieRefresh(time());
assertTrue('browser-sessie (lifetime 0) krijgt geen nieuwe eindtijd', $browserCookie === null);
session_write_close();

echo PHP_EOL . "Sessie blijft leven voorbij gc_maxlifetime" . PHP_EOL;

configureKeepaliveSession($savePath, $maxLifetime);

$controlId = bin2hex(random_bytes(16));
session_id($controlId);
session_start();
$_SESSION['user'] = ['email' => 'ict@kvt.nl'];
$_SESSION['last_keepalive_at'] = 1;
session_write_close();

$keptId = bin2hex(random_bytes(16));
session_id($keptId);
session_start();
$_SESSION['user'] = ['email' => 'ict@kvt.nl'];
$_SESSION['last_keepalive_at'] = 1;
session_write_close();

$controlFile = $savePath . '/sess_' . $controlId;
$keptFile = $savePath . '/sess_' . $keptId;
assertTrue('controlesessie bestaat', is_file($controlFile));
assertTrue('keepalive-sessie bestaat', is_file($keptFile));

$createdAt = time();
sleep(4);

$authPath = __DIR__ . '/../web/auth.php';
$createdStubAuth = false;
if (!is_file($authPath)) {
    $written = file_put_contents(
        $authPath,
        "<?php\n\$ictUsers = ['ict@kvt.nl'];\n\$ictUserColors = [];\n\$apiKeys = [];\n\$grokBot = ['enabled' => false];\n"
    );
    if ($written === false) {
        echo "FAIL: kon stub auth.php niet schrijven." . PHP_EOL;
        exit(1);
    }
    $createdStubAuth = true;
}

register_shutdown_function(static function () use ($authPath, $createdStubAuth, $savePath): void {
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
    foreach (glob($savePath . '/sess_*') ?: [] as $file) {
        if (is_string($file)) {
            @unlink($file);
        }
    }
    @rmdir($savePath);
});

$runnerDir = $savePath . '/runners';
mkdir($runnerDir, 0700, true);
$endpoint = realpath(__DIR__ . '/../web/session_keepalive.php');
if ($endpoint === false) {
    echo "FAIL: session_keepalive.php niet gevonden." . PHP_EOL;
    exit(1);
}

$keepaliveRunner = $runnerDir . '/keepalive.php';
file_put_contents(
    $keepaliveRunner,
    "<?php\n"
    . "\$_SERVER['REMOTE_ADDR'] = '203.0.113.8';\n"
    . "\$_SERVER['SERVER_ADDR'] = '203.0.113.1';\n"
    . "\$_SERVER['HTTP_HOST'] = 'localhost';\n"
    . "\$_SERVER['HTTPS'] = '';\n"
    . "\$_SERVER['SCRIPT_NAME'] = '/session_keepalive.php';\n"
    . "\$_SERVER['PHP_SELF'] = '/session_keepalive.php';\n"
    . "\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
    . "\$_COOKIE['ASCLEPIUSSESS'] = '" . $keptId . "';\n"
    . 'require ' . var_export($endpoint, true) . ";\n"
);

$ini = [
    'session.save_path' => $savePath,
    'session.gc_maxlifetime' => (string) $maxLifetime,
    'session.cookie_lifetime' => (string) $maxLifetime,
    'session.gc_probability' => '0',
    'session.use_strict_mode' => '0',
    'session.name' => 'ASCLEPIUSSESS',
    'session.cookie_httponly' => '1',
    'session.cookie_samesite' => 'Lax',
    'display_errors' => '1',
];

$alive = runPhp($keepaliveRunner, $ini);
assertSame('keepalive-endpoint eindigt netjes', 0, $alive['code']);
assertSame('204 heeft een lege body', '', $alive['stdout']);
assertTrue(
    'geen fatale fout in keepalive',
    !str_contains($alive['stderr'], 'Fatal') && !str_contains($alive['stdout'], 'Fatal')
);

$keptRaw = is_file($keptFile) ? (string) file_get_contents($keptFile) : '';
assertTrue('keepalive schrijft last_keepalive_at', str_contains($keptRaw, 'last_keepalive_at'));
assertTrue('gebruiker blijft in de sessie', str_contains($keptRaw, 'ict@kvt.nl'));
assertTrue(
    'timestamp is ververst',
    preg_match('/last_keepalive_at\|i:(\d+);/', $keptRaw, $stampMatch) === 1
    && (int) $stampMatch[1] >= $createdAt
);
assertTrue('antwoord bevat geen api-sleutel', !str_contains($alive['stdout'], 'api_key'));

$expiredRunner = $runnerDir . '/expired.php';
file_put_contents(
    $expiredRunner,
    "<?php\n"
    . "\$_SERVER['REMOTE_ADDR'] = '203.0.113.8';\n"
    . "\$_SERVER['SERVER_ADDR'] = '203.0.113.1';\n"
    . "\$_SERVER['HTTP_HOST'] = 'localhost';\n"
    . "\$_SERVER['HTTPS'] = '';\n"
    . "\$_SERVER['SCRIPT_NAME'] = '/session_keepalive.php';\n"
    . "\$_SERVER['PHP_SELF'] = '/session_keepalive.php';\n"
    . "\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
    . 'require ' . var_export($endpoint, true) . ";\n"
);
$expired = runPhp($expiredRunner, $ini);
assertSame('verlopen sessie eindigt netjes', 0, $expired['code']);
assertTrue('verlopen sessie noemt session_expired', str_contains($expired['stdout'], 'session_expired'));
assertTrue('verlopen sessie stuurt geen api-sleutel', !str_contains($expired['stdout'], 'api_key'));
assertTrue(
    'geen fatale fout bij verlopen sessie',
    !str_contains($expired['stderr'], 'Fatal') && !str_contains($expired['stdout'], 'Fatal')
);

sleep(4);

clearstatcache();
$removed = expireOldSessionFiles($savePath, $maxLifetime);
clearstatcache();
$elapsed = time() - $createdAt;
assertTrue(
    'er is meer tijd verstreken dan gc_maxlifetime (' . $elapsed . 's > ' . $maxLifetime . 's)',
    $elapsed > $maxLifetime
);
assertTrue('zonder keepalive ruimt gc de sessie op', in_array('sess_' . $controlId, $removed, true) || !is_file($controlFile));
assertTrue('mét keepalive overleeft de sessie gc', is_file($keptFile));

$keptRawAfterGc = is_file($keptFile) ? (string) file_get_contents($keptFile) : '';
assertTrue('overlevende sessie hoort nog bij de gebruiker', str_contains($keptRawAfterGc, 'ict@kvt.nl'));

echo PHP_EOL . "Cookie wordt opnieuw gezet" . PHP_EOL;

$httpId = bin2hex(random_bytes(16));
$seedRunner = $runnerDir . '/seed.php';
file_put_contents(
    $seedRunner,
    "<?php\n"
    . "session_id('" . $httpId . "');\n"
    . "session_start();\n"
    . "\$_SESSION['user'] = ['email' => 'ict@kvt.nl'];\n"
    . "\$_SESSION['last_keepalive_at'] = 1;\n"
    . "session_write_close();\n"
    . "echo 'seeded';\n"
);
$seeded = runPhp($seedRunner, $ini);
assertSame('testsessie gezaaid', 0, $seeded['code']);
assertTrue('testsessie-bestand bestaat', is_file($savePath . '/sess_' . $httpId));

$port = 0;
$serverSocket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
if (is_resource($serverSocket)) {
    $serverName = stream_socket_get_name($serverSocket, false);
    if (is_string($serverName) && preg_match('/:(\d+)$/', $serverName, $portMatch) === 1) {
        $port = (int) $portMatch[1];
    }
    fclose($serverSocket);
}
assertTrue('lokale poort gereserveerd', $port > 0);

$webRoot = realpath(__DIR__ . '/../web');
$serverCommand = escapeshellarg(PHP_BINARY);
foreach ($ini as $key => $value) {
    $serverCommand .= ' -d ' . escapeshellarg($key . '=' . $value);
}
$serverCommand .= ' -S ' . escapeshellarg('127.0.0.1:' . $port);
$serverCommand .= ' -t ' . escapeshellarg((string) $webRoot);

$serverDescriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['file', '/dev/null', 'w'],
    2 => ['file', '/dev/null', 'w'],
];
$serverPipes = [];
$serverProcess = proc_open($serverCommand, $serverDescriptors, $serverPipes);
assertTrue('keepalive-server start', is_resource($serverProcess));

register_shutdown_function(static function () use ($serverProcess, &$serverPipes): void {
    if (!is_resource($serverProcess)) {
        return;
    }
    foreach ($serverPipes as $pipe) {
        if (is_resource($pipe)) {
            fclose($pipe);
        }
    }
    proc_terminate($serverProcess);
    proc_close($serverProcess);
});

$serverReady = false;
if (is_resource($serverProcess)) {
    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline) {
        $connection = @fsockopen('127.0.0.1', $port, $connectErrno, $connectError, 0.2);
        if (is_resource($connection)) {
            fclose($connection);
            $serverReady = true;
            break;
        }
        usleep(100000);
    }
}
assertTrue('keepalive-server neemt verbindingen aan', $serverReady);

$curl = 'curl -sS -D - -o ' . escapeshellarg($runnerDir . '/body.txt')
    . ' --cookie ' . escapeshellarg('ASCLEPIUSSESS=' . $httpId)
    . ' ' . escapeshellarg('http://127.0.0.1:' . $port . '/session_keepalive.php');
$headers = shell_exec($curl);
$headers = is_string($headers) ? $headers : '';
$body = is_file($runnerDir . '/body.txt') ? (string) file_get_contents($runnerDir . '/body.txt') : '';
assertTrue('HTTP-keepalive antwoordt 204', str_contains($headers, '204'));
assertSame('HTTP-keepalive heeft een lege body', '', $body);
assertTrue('HTTP-keepalive zet de sessiecookie opnieuw', str_contains($headers, 'Set-Cookie:'));
assertTrue(
    'cookie noemt het sessie-id',
    str_contains($headers, 'ASCLEPIUSSESS=' . $httpId)
);
assertTrue(
    'cookie krijgt een verse levensduur',
    str_contains($headers, 'Max-Age=' . $maxLifetime) || preg_match('/expires=/i', $headers) === 1
);
$httpRaw = is_file($savePath . '/sess_' . $httpId) ? (string) file_get_contents($savePath . '/sess_' . $httpId) : '';
assertTrue('HTTP-keepalive ververst de timestamp', str_contains($httpRaw, 'last_keepalive_at') && !str_contains($httpRaw, 'last_keepalive_at|i:1;'));

echo PHP_EOL;
if ($failed === 0) {
    echo "Alle keepalive-tests geslaagd ({$passed})." . PHP_EOL;
    exit(0);
}

echo "{$failed} keepalive-test(s) gefaald, {$passed} geslaagd." . PHP_EOL;
if (($alive['stdout'] ?? '') !== '' || ($alive['stderr'] ?? '') !== '') {
    echo "--- keepalive stdout ---" . PHP_EOL . ($alive['stdout'] ?? '') . PHP_EOL;
    echo "--- keepalive stderr ---" . PHP_EOL . ($alive['stderr'] ?? '') . PHP_EOL;
}
if (($expired['stderr'] ?? '') !== '') {
    echo "--- expired stderr ---" . PHP_EOL . $expired['stderr'] . PHP_EOL;
}
if (($headers ?? '') !== '') {
    echo "--- http headers ---" . PHP_EOL . $headers . PHP_EOL;
}
exit(1);
