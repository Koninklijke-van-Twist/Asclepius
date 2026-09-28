<?php
/**
 * Simuleert een onbereikbare Mímir en controleert de directe BC-fallback.
 * Run: php tests/mimir_fallback_test.php
 */

$logFile = sys_get_temp_dir() . '/asclepius-mimir-fallback-test.log';
@unlink($logFile);
ini_set('error_log', $logFile);
ini_set('log_errors', '1');

$mimirApi = 'mimir_test_key_should_not_leak';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];

$calls = [];
$GLOBALS['ASCLEPIUS_ODATA_BC_FETCH'] = static function (string $url, array $auth, int $ttl) use (&$calls): array {
    $calls[] = [
        'url' => $url,
        'user' => (string) ($auth['user'] ?? ''),
        'ttl' => $ttl,
    ];
    if (preg_match('#/ODataV4/Company(?:\\?|$)#', $url) === 1) {
        return [
            ['Name' => 'KVT Gas'],
            ['Name' => 'Hunter van Twist'],
            ['name' => 'Koninklijke van Twist'],
        ];
    }
    return [['No' => 'WO-1']];
};

require dirname(__DIR__) . '/web/odata.php';

function fail(string $message): void
{
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function fallback_log(): string
{
    global $logFile;
    $raw = @file_get_contents($logFile);
    return is_string($raw) ? $raw : '';
}

function fallback_count(): int
{
    return substr_count(fallback_log(), '[Asclepius] Mímir failed, falling back to direct OData:');
}

if (odata_mimir_connect_timeout_seconds() !== 10) {
    fail('connect-timeout moet 10s zijn');
}
if (odata_mimir_timeout_seconds_for_sapi('cli') !== 600) {
    fail('CLI-timeout moet 600s blijven');
}
if (odata_mimir_timeout_seconds_for_sapi('fpm-fcgi') !== 90 || odata_mimir_timeout_seconds_for_sapi('apache2handler') !== 90) {
    fail('web-timeout moet ongeveer 90s zijn');
}
if (PHP_SAPI === 'cli' && odata_mimir_timeout_seconds() !== 600) {
    fail('huidige CLI-sapi moet de lange timeout gebruiken');
}

$root = dirname(__DIR__) . '/web';
foreach (['nightly.php', 'hourly.php', 'api.php', 'content/bootstrap.php'] as $entry) {
    $source = file_get_contents($root . '/' . $entry);
    if (!is_string($source) || strpos($source, 'auth.php') === false) {
        fail($entry . ' moet auth.php laden zodat BC-credentials voor de fallback beschikbaar zijn');
    }
    if (preg_match('/if\s*\([^)]*mimirApi[^)]*\)\s*\{[^}]*auth\.php/s', $source) === 1) {
        fail($entry . ' mag auth.php niet overslaan wanneer $mimirApi gezet is');
    }
}

$names = odata_mimir_list_companies(null);
$expectedNames = ['Hunter van Twist', 'Koninklijke van Twist', 'KVT Gas'];
if ($names !== $expectedNames) {
    fail('company-fallback gaf ' . json_encode($names) . ' i.p.v. de gesorteerde BC-namen');
}
if (!odata_mimir_circuit_open()) {
    fail('circuit moet open na de eerste Mímir-fout');
}
if (count($calls) !== 1 || strpos($calls[0]['url'], 'https://bc.example:7148/Production/ODataV4/Company') !== 0) {
    fail('company-fallback riep de directe BC-fetch niet aan: ' . json_encode($calls));
}
if ($calls[0]['user'] !== 'bcuser') {
    fail('company-fallback gebruikte niet de BC-credentials');
}

$mimirBase = 'http://192.0.2.1:9';
$started = microtime(true);
$rows = odata_get_all(
    "https://mimir.invalid/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    120
);
$elapsed = microtime(true) - $started;
if ($elapsed >= 2.0) {
    fail('circuit breaker sloeg Mímir niet over (' . round($elapsed, 3) . 's)');
}
if (($rows[0]['No'] ?? '') !== 'WO-1') {
    fail('entity-fallback gaf niet de gestubde BC-rijen terug');
}
$entityCall = $calls[1] ?? null;
$expectedEntityUrl = "https://bc.example:7148/Production/ODataV4/Company('Koninklijke%20van%20Twist')/AppWerkorders?\$select=No";
if (!is_array($entityCall) || $entityCall['url'] !== $expectedEntityUrl || $entityCall['user'] !== 'bcuser' || $entityCall['ttl'] !== 120) {
    fail('entity-fallback URL/auth/ttl klopt niet: ' . json_encode($entityCall));
}
if (fallback_count() !== 1) {
    fail('alleen de eerste Mímir-fout wordt gelogd, log=' . fallback_log());
}
$log = fallback_log();
if (strpos($log, 'mimir_test_key_should_not_leak') !== false || strpos($log, 'bc-secret') !== false) {
    fail('log bevat een geheim');
}
if (strpos($log, '[Asclepius] Mímir failed, falling back to direct OData:') === false) {
    fail('logregel mist het verwachte prefix');
}

odata_mimir_circuit_reset();
$loggedBeforeCaller = fallback_count();
$callsBeforeCaller = count($calls);
try {
    odata_mimir_or_direct(
        static function (): array {
            throw new Exception('caller boom');
        },
        static function (): array {
            return [['No' => 'should-not-run']];
        }
    );
    fail('een exception van de caller moet doorgaan');
} catch (Throwable $callerException) {
    if ($callerException->getMessage() !== 'caller boom') {
        fail('caller-exception veranderde: ' . $callerException->getMessage());
    }
}
if (odata_mimir_circuit_open()) {
    fail('een exception van de caller mag het circuit niet openen');
}
if (fallback_count() !== $loggedBeforeCaller || count($calls) !== $callsBeforeCaller) {
    fail('een exception van de caller mag geen fallback starten');
}

odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$beforeQuery = count($calls);
$queryRows = odata_mimir_query('KVT Gas', 'AppResource', ['$select' => 'No,Name'], 60);
if (($queryRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_query viel niet terug op de stub');
}
$queryCall = $calls[$beforeQuery] ?? null;
if (!is_array($queryCall) || strpos($queryCall['url'], "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppResource?") !== 0) {
    fail('query-fallback bouwde niet de pre-Mímir BC-URL: ' . json_encode($queryCall));
}

odata_mimir_circuit_reset();
$beforeFetch = count($calls);
$fetchRows = odata_mimir_fetch_all(
    "https://mimir.invalid/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No",
    15
);
if (($fetchRows[0]['No'] ?? '') !== 'WO-1') {
    fail('odata_mimir_fetch_all viel niet terug');
}
$fetchCall = $calls[$beforeFetch] ?? null;
if (!is_array($fetchCall) || $fetchCall['url'] !== "https://bc.example:7148/Production/ODataV4/Company('KVT%20Gas')/AppWerkorders?\$select=No") {
    fail('fetch_all-fallback herschreef de URL niet: ' . json_encode($fetchCall));
}

odata_mimir_circuit_reset();
$map = odata_mimir_company_environment_map(null);
if (($map['Hunter van Twist'] ?? '') !== 'Production' || ($map['KVT Gas'] ?? '') !== 'Production') {
    fail('environment-map viel niet terug op BC: ' . json_encode($map));
}

$spacedUrl = odata_bc_url_from_odata_url("https://mimir.invalid/My%20Env/ODataV4/Company('X')/Projecten?\$select=No");
if ($spacedUrl !== "https://bc.example:7148/My%20Env/ODataV4/Company('X')/Projecten?\$select=No") {
    fail('environment in de URL moet behouden en één keer gecodeerd zijn: ' . $spacedUrl);
}

odata_mimir_circuit_reset();
$auth_list = [
    'Production' => $auth,
    'Sandbox' => ['mode' => 'basic', 'user' => 'sandbox-user', 'pass' => 'sandbox-secret'],
];
$GLOBALS['asclepius_company_environment_map']['Hunter van Twist'] = 'Sandbox';
$beforeSandbox = count($calls);
$sandboxRows = odata_get_all(
    "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
    $auth,
    30
);
$sandboxCall = $calls[$beforeSandbox] ?? null;
if (($sandboxRows[0]['No'] ?? '') !== 'WO-1' || !is_array($sandboxCall) || $sandboxCall['user'] !== 'sandbox-user') {
    fail('fallback gebruikte niet de credentials van het gevraagde environment: ' . json_encode($sandboxCall));
}
if (!is_array($sandboxCall) || strpos($sandboxCall['url'], 'https://bc.example:7148/Sandbox/ODataV4/Company(') !== 0) {
    fail('fallback herschreef niet naar het environment uit de URL: ' . json_encode($sandboxCall));
}
$sandboxCacheKey = build_cache_key($sandboxCall['url'], $sandboxCall['user'] === 'sandbox-user'
    ? ['mode' => 'basic', 'user' => 'sandbox-user', 'pass' => 'sandbox-secret']
    : $auth);
if (substr($sandboxCacheKey, -strlen('|sandbox-user|Sandbox')) !== '|sandbox-user|Sandbox') {
    fail('cache-key moet het echte BC-environment gebruiken: ' . $sandboxCacheKey);
}
$placeholderKey = build_cache_key(
    "https://mimir.invalid/mimir/ODataV4/Company('Onbekend%20BV')/AppResource",
    ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret']
);
if (substr($placeholderKey, -strlen('|bcuser|Production')) !== '|bcuser|Production' || substr($placeholderKey, -6) === '|mimir') {
    fail('cache-key mag geen mimir-placeholder gebruiken: ' . $placeholderKey);
}

odata_mimir_circuit_reset();
$beforeCompanyQuery = count($calls);
$companyQueryRows = odata_mimir_query('Hunter van Twist', 'AppResource', ['$select' => 'No'], 10);
$companyQueryCall = null;
for ($i = $beforeCompanyQuery; $i < count($calls); $i++) {
    if (strpos($calls[$i]['url'], 'AppResource') !== false) {
        $companyQueryCall = $calls[$i];
    }
}
if (($companyQueryRows[0]['No'] ?? '') !== 'WO-1' || !is_array($companyQueryCall) || $companyQueryCall['user'] !== 'sandbox-user') {
    fail('query-fallback gebruikte niet het environment van het bedrijf: ' . json_encode($companyQueryCall));
}
if (strpos($companyQueryCall['url'], "https://bc.example:7148/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppResource?") !== 0) {
    fail('query-fallback bouwde niet de Sandbox-URL: ' . json_encode($companyQueryCall));
}

odata_mimir_circuit_reset();
$auth_list = [
    'Production' => ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'],
];
$callsBeforeMismatch = count($calls);
$mismatch = null;
try {
    odata_get_all(
        "https://mimir.invalid/Sandbox/ODataV4/Company('Hunter%20van%20Twist')/AppWerkorders?\$select=No",
        ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'],
        30
    );
    fail('Sandbox zonder auth_list-entry mag Production-credentials niet gebruiken');
} catch (Throwable $exception) {
    $mismatch = $exception;
}
if (!$mismatch instanceof Throwable || strpos($mismatch->getMessage(), 'Mímir') === false) {
    fail('Sandbox zonder credentials moet de Mímir-fout teruggeven: ' . ($mismatch instanceof Throwable ? $mismatch->getMessage() : 'geen'));
}
if (count($calls) !== $callsBeforeMismatch) {
    fail('Sandbox zonder auth_list-entry mag de BC-stub niet aanroepen');
}
if (!odata_mimir_circuit_open()) {
    fail('een Mímir-fout moet het circuit openen, ook als het gevraagde environment geen credentials heeft');
}

$previousFetch = $GLOBALS['ASCLEPIUS_ODATA_BC_FETCH'];
$GLOBALS['ASCLEPIUS_ODATA_BC_FETCH'] = static function (string $url, array $auth, int $ttl) use (&$calls): array {
    $calls[] = [
        'url' => $url,
        'user' => (string) ($auth['user'] ?? ''),
        'ttl' => $ttl,
    ];
    if (strpos($url, '/Broken/') !== false) {
        throw new Exception('BC environment down');
    }
    if (preg_match('#/ODataV4/Company(?:\\?|$)#', $url) === 1) {
        return [
            ['Name' => 'KVT Gas'],
            ['Name' => 'Hunter van Twist'],
            ['name' => 'Koninklijke van Twist'],
        ];
    }
    return [['No' => 'WO-1']];
};
$auth_list = [
    'Broken' => ['mode' => 'basic', 'user' => 'broken-user', 'pass' => 'broken-secret'],
    'Production' => ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'],
];
odata_mimir_circuit_reset();
$callsBeforeDiscovery = count($calls);
$partialNames = odata_mimir_list_companies(null);
if ($partialNames !== ['Hunter van Twist', 'Koninklijke van Twist', 'KVT Gas']) {
    fail('een stuk environment mag de bedrijfslijst niet leegmaken: ' . json_encode($partialNames));
}
$sawBroken = false;
$sawProductionCompany = false;
for ($i = $callsBeforeDiscovery; $i < count($calls); $i++) {
    if (strpos($calls[$i]['url'], '/Broken/') !== false) {
        $sawBroken = true;
        if ($calls[$i]['user'] !== 'broken-user') {
            fail('Broken-environment gebruikte niet de eigen credentials: ' . json_encode($calls[$i]));
        }
    }
    if (strpos($calls[$i]['url'], '/Production/ODataV4/Company') !== false) {
        $sawProductionCompany = true;
    }
}
if (!$sawBroken || !$sawProductionCompany) {
    fail('company-discovery moet elk environment proberen: ' . json_encode(array_slice($calls, $callsBeforeDiscovery)));
}
$GLOBALS['ASCLEPIUS_ODATA_BC_FETCH'] = $previousFetch;

odata_mimir_circuit_reset();
$loggedBeforeBadUrl = fallback_count();
$callsBeforeBadUrl = count($calls);
try {
    odata_mimir_fetch_all('https://mimir.invalid/not-an-odata-url', 10);
    fail('een onvertaalbare URL moet een fout geven');
} catch (Throwable $badUrl) {
    if (strpos($badUrl->getMessage(), 'kon niet worden vertaald') === false) {
        fail('onvertaalbare URL gaf een andere fout: ' . $badUrl->getMessage());
    }
}
if (odata_mimir_circuit_open() || fallback_count() !== $loggedBeforeBadUrl || count($calls) !== $callsBeforeBadUrl) {
    fail('een onvertaalbare URL mag het circuit niet openen en geen fallback starten');
}

$loggedBeforeRethrow = fallback_count();
$callsBeforeRethrow = count($calls);
odata_mimir_circuit_reset();
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://mimir.invalid/';
$environment = 'mimir';
$auth = [];
$auth_list = [];
$rethrown = null;
try {
    odata_get_all('https://mimir.invalid/mimir/ODataV4/Company(\'X\')/AppWerkorders', ['mode' => 'basic', 'user' => '', 'pass' => ''], 30);
    fail('zonder BC-credentials moet de oorspronkelijke Mímir-fout terugkomen');
} catch (Throwable $exception) {
    $rethrown = $exception;
}
if (!$rethrown instanceof Throwable) {
    fail('zonder BC-credentials moet een exception terugkomen');
}
if (strpos($rethrown->getMessage(), 'Mímir') === false) {
    fail('hergooide fout is niet de Mímir-fout: ' . $rethrown->getMessage());
}
if (stripos($rethrown->getMessage(), 'credential') !== false) {
    fail('hergooide fout maskeert Mímir met een credentials-melding: ' . $rethrown->getMessage());
}
if (count($calls) !== $callsBeforeRethrow) {
    fail('zonder BC-credentials mag de directe fetch niet starten');
}
if (fallback_count() !== $loggedBeforeRethrow) {
    fail('zonder BC-credentials mag er geen fallback gelogd worden');
}

odata_mimir_circuit_reset();
$mimirApi = '';
$mimirBase = 'http://127.0.0.1:9';
$baseUrl = 'https://bc.example:7148/';
$environment = 'Production';
$auth = ['mode' => 'basic', 'user' => 'bcuser', 'pass' => 'bc-secret'];
$auth_list = ['Production' => $auth];
$loggedBeforeDirect = fallback_count();
$directOnlyUrl = 'https://mimir.invalid/Production/ODataV4/Company(\'KVT%20Gas\')/AppWerkorders?$select=No';
$directRows = odata_get_all($directOnlyUrl, $auth, 45);
if (odata_mimir_circuit_open()) {
    fail('lege $mimirApi mag Mímir niet proberen');
}
if (fallback_count() !== $loggedBeforeDirect) {
    fail('lege $mimirApi mag geen Mímir-fallback loggen');
}
$directCall = $calls[count($calls) - 1] ?? null;
if (($directRows[0]['No'] ?? '') !== 'WO-1' || !is_array($directCall) || $directCall['url'] !== $directOnlyUrl) {
    fail('lege $mimirApi moet de oude directe route ongewijzigd gebruiken: ' . json_encode($directCall));
}
if (strpos(fallback_log(), 'sandbox-secret') !== false || strpos(fallback_log(), 'file-secret') !== false) {
    fail('log bevat een geheim');
}

$authFile = sys_get_temp_dir() . '/asclepius-auth-globals.php';
file_put_contents(
    $authFile,
    "<?php\n\$baseUrl = 'https://bc-from-file.example:7148/';\n\$base = 'https://base-from-file.example:7148/';\n\$environment = 'Sandbox';\n\$auth = ['mode' => 'basic', 'user' => 'file-user', 'pass' => 'file-secret'];\n\$auth_list = ['Sandbox' => \$auth];\n"
);
unset($GLOBALS['baseUrl'], $GLOBALS['environment'], $GLOBALS['auth'], $GLOBALS['auth_list'], $GLOBALS['base']);
unset($baseUrl, $environment, $auth, $auth_list, $base);
$GLOBALS['baseUrl'] = 'https://preset.example/';
$GLOBALS['ASCLEPIUS_AUTH_PHP_PATH'] = $authFile;
$loadInsideFunction = static function (): void {
    odata_import_bc_credentials();
};
$loadInsideFunction();
if (($GLOBALS['baseUrl'] ?? '') !== 'https://preset.example/') {
    fail('lazy auth.php overschreef een gezette baseUrl: ' . (string) ($GLOBALS['baseUrl'] ?? ''));
}
if (($GLOBALS['environment'] ?? '') !== 'Sandbox') {
    fail('lazy auth.php kopieerde environment niet naar $GLOBALS');
}
if (($GLOBALS['auth']['user'] ?? '') !== 'file-user') {
    fail('lazy auth.php kopieerde auth niet naar $GLOBALS');
}
if (($GLOBALS['auth_list']['Sandbox']['user'] ?? '') !== 'file-user') {
    fail('lazy auth.php kopieerde auth_list niet naar $GLOBALS');
}
if (($GLOBALS['base'] ?? '') !== 'https://base-from-file.example:7148/') {
    fail('lazy auth.php kopieerde base niet naar $GLOBALS');
}
$loadInsideFunction();
if (($GLOBALS['auth_list']['Sandbox']['user'] ?? '') !== 'file-user' || ($GLOBALS['baseUrl'] ?? '') !== 'https://preset.example/') {
    fail('een tweede require mocht de globals niet wissen');
}
@unlink($authFile);
unset($GLOBALS['ASCLEPIUS_AUTH_PHP_PATH']);

echo "OK\n";
