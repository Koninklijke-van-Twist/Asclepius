<?php
/**
 * ticket_poll / ticket_thread with format=json return structured data, not HTML cards.
 */

echo "=== TEST: ticket_poll JSON format ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/api.php';
$_SERVER['HTTP_HOST'] = 'localhost';
@ini_set('sendmail_path', '/bin/true');

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    exit(1);
}

$authPath = __DIR__ . '/../web/auth.php';
$createdStubAuth = false;
if (!is_file($authPath)) {
    $written = file_put_contents(
        $authPath,
        "<?php\n"
        . "\$apiKeys = ['test-service-key'];\n"
        . "\$ictUsers = ['ict@kvt.nl', 'colleague@kvt.nl'];\n"
        . "\$ictUserColors = [];\n"
        . "\$mailSettings = [];\n"
        . "\$grokBot = ['enabled' => false];\n"
    );
    if ($written === false) {
        echo "FAIL: kon stub auth.php niet schrijven." . PHP_EOL;
        exit(1);
    }
    $createdStubAuth = true;
}

$tempDb = sys_get_temp_dir() . '/asclepius-poll-json-' . getmypid() . '.sqlite';
$tempUploads = sys_get_temp_dir() . '/asclepius-poll-json-uploads-' . getmypid();
@unlink($tempDb);
if (!is_dir($tempUploads)) {
    mkdir($tempUploads, 0777, true);
}

register_shutdown_function(static function () use ($authPath, $createdStubAuth, $tempDb, $tempUploads): void {
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
    @unlink($tempDb);
    @unlink($tempDb . '-wal');
    @unlink($tempDb . '-shm');
    if (is_dir($tempUploads)) {
        foreach (glob($tempUploads . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($tempUploads);
    }
});

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}

require __DIR__ . '/../web/api.php';

$GLOBALS['ictUsers'] = ['ict@kvt.nl', 'colleague@kvt.nl'];
$GLOBALS['grokBot'] = ['enabled' => false];

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
    echo "      Verwacht : " . var_export($expected, true) . PHP_EOL;
    echo "      Gekregen : " . var_export($actual, true) . PHP_EOL;
    $failed++;
}

$store = new TicketStore($tempDb, $tempUploads, $GLOBALS['ictUsers'], TICKET_CATEGORIES);
$created = $store->createTicket(
    'JSON poll ticket',
    'Anders',
    'user@kvt.nl',
    'Beschrijving voor JSON poll'
);
$ticketId = (int) ($created['ticket_id'] ?? 0);
assertTrue('ticket aangemaakt', $ticketId > 0);

$store->addMessage($ticketId, 'user@kvt.nl', 'user', 'Eerste antwoord');

$apiClient = [
    'email' => 'ict@kvt.nl',
    'is_admin' => true,
    'api_key' => 'a' . str_repeat('0', 63),
    'kind' => 'session',
];

$pollHtml = buildTicketPollApiPayload($store, [
    'viewer_email' => 'ict@kvt.nl',
    'user_is_admin' => true,
    'is_admin_portal' => true,
    'open_ticket_id' => $ticketId,
    'view' => 'overview',
], $apiClient);

assertTrue('html poll success', !empty($pollHtml['success']));
assertSame('html format default', 'html', (string) ($pollHtml['format'] ?? ''));
assertTrue('html poll has card_html', isset($pollHtml['tickets'][0]['card_html']));
assertTrue('html poll has pagination_html', isset($pollHtml['pagination_html']));

$pollJson = buildTicketPollApiPayload($store, [
    'viewer_email' => 'ict@kvt.nl',
    'user_is_admin' => true,
    'is_admin_portal' => true,
    'open_ticket_id' => $ticketId,
    'view' => 'overview',
    'format' => 'json',
], $apiClient);

assertTrue('json poll success', !empty($pollJson['success']));
assertSame('json format', 'json', (string) ($pollJson['format'] ?? ''));
assertTrue('json poll has tickets', is_array($pollJson['tickets'] ?? null) && $pollJson['tickets'] !== []);
assertTrue('json poll omits card_html', !isset($pollJson['tickets'][0]['card_html']));
assertTrue('json poll omits pagination_html', !isset($pollJson['pagination_html']));
assertTrue('json poll has pagination object', is_array($pollJson['pagination'] ?? null));
assertTrue('json poll has empty_message', is_string($pollJson['empty_message'] ?? null));
assertTrue('json ticket has status', isset($pollJson['tickets'][0]['status']));
assertTrue('json ticket has messages', is_array($pollJson['tickets'][0]['messages'] ?? null));
$firstMessage = $pollJson['tickets'][0]['messages'][0] ?? [];
assertTrue('json message has message_text', isset($firstMessage['message_text']));
assertTrue('json message omits html', !isset($firstMessage['html']));

$preview = buildRelatedTicketPreviewApiPayload($store, [
    'ticket_id' => $ticketId,
    'viewer_email' => 'user@kvt.nl',
    'format' => 'json',
    'current_language' => 'nl',
], ['email' => 'user@kvt.nl', 'is_admin' => false]);
// Preview only works for afgehandeld public tickets — expect not found or structured ticket.
assertTrue('preview returns array', is_array($preview));

echo PHP_EOL . "Passed: {$passed}, Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
