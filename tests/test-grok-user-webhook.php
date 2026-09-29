<?php
/**
 * Persoonlijke Grok-webhook naast de centrale $grokBot-config.
 */

$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
$_SERVER['SERVER_ADDR'] = '10.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/api.php';
$_SERVER['HTTP_HOST'] = 'localhost';
@ini_set('sendmail_path', '/bin/true');

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    exit(1);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo "=== TEST: persoonlijke Grok-webhook ===" . PHP_EOL . PHP_EOL;

$authPath = __DIR__ . '/../web/auth.php';
$createdStubAuth = false;
if (!is_file($authPath)) {
    $written = file_put_contents(
        $authPath,
        "<?php\n"
        . "\$apiKeys = ['test-service-key'];\n"
        . "\$ictUsers = ['ict@kvt.nl'];\n"
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

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}

require __DIR__ . '/../web/api.php';
require_once __DIR__ . '/../web/content/GrokBot.php';

$passed = 0;
$failed = 0;
$captured = [];
$prefEmails = [
    'assignee-webhook@example.com',
    'actor-webhook@example.com',
    'requester-webhook@example.com',
    'session-webhook@example.com',
];

register_shutdown_function(static function () use ($authPath, $createdStubAuth, &$captured, $prefEmails): void {
    GrokBot::setDeliveryOverride(null);
    $directory = GrokBot::apiClientsDirectory();
    foreach ($captured as $event) {
        $apiKey = (string) ($event['payload']['api_key'] ?? '');
        if ($apiKey === '') {
            continue;
        }
        @unlink($directory . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json');
    }
    foreach ($prefEmails as $email) {
        $path = getUserPrefsPath($email);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
});

function assertSame(string $label, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    $exp = var_export($expected, true);
    $act = var_export($actual, true);
    echo "  ✗ {$label}" . PHP_EOL;
    echo "      Verwacht : {$exp}" . PHP_EOL;
    echo "      Gekregen : {$act}" . PHP_EOL;
    $failed++;
}

function assertTrue(string $label, bool $actual): void
{
    assertSame($label, true, $actual);
}

function assertFalse(string $label, bool $actual): void
{
    assertSame($label, false, $actual);
}

function resetCaptured(): void
{
    global $captured;
    $directory = GrokBot::apiClientsDirectory();
    foreach ($captured as $event) {
        $apiKey = (string) ($event['payload']['api_key'] ?? '');
        if ($apiKey === '') {
            continue;
        }
        @unlink($directory . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json');
    }
    $captured = [];
}

function capturedUrls(): array
{
    global $captured;

    return array_map(
        static fn(array $event): string => (string) ($event['url'] ?? ''),
        $captured
    );
}

$GLOBALS['grokBot'] = [
    'enabled' => true,
    'webhook_url' => 'https://example.com/global-webhook',
    'send_key' => 'global-send-key',
    'sender_email' => 'grok-bot@kvt.nl',
    'default_name' => 'Grok',
    'default_title' => 'Bot',
];
GrokBot::setDeliveryOverride(static function (string $url, array $payload, string $sendKey) use (&$captured): void {
    $captured[] = [
        'url' => $url,
        'payload' => $payload,
        'send_key' => $sendKey,
    ];
});

$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_uploads_user_webhook',
    ['ict@kvt.nl', 'assignee-webhook@example.com', 'actor-webhook@example.com'],
    TICKET_CATEGORIES
);

$saved = GrokBot::saveUserWebhook(
    'assignee-webhook@example.com',
    'https://example.com/personal-webhook',
    'personal-send-key',
    'Tim Falken'
);
assertTrue('Opslaan persoonlijke webhook', !empty($saved['ok']));
assertSame('Publieke view heeft geen sleutel', false, array_key_exists('send_key', $saved['webhook']));
assertTrue('Publieke view meldt sleutel', !empty($saved['webhook']['has_send_key']));
assertSame('Publieke URL', 'https://example.com/personal-webhook', (string) ($saved['webhook']['webhook_url'] ?? ''));
$prefPath = getUserPrefsPath('assignee-webhook@example.com');
$prefRaw = is_string($prefPath) ? (string) file_get_contents($prefPath) : '';
assertTrue('Sleutel staat in user prefs', strpos($prefRaw, 'personal-send-key') !== false);

$kept = GrokBot::saveUserWebhook(
    'assignee-webhook@example.com',
    'https://example.com/personal-webhook',
    '',
    'Tim Falken'
);
assertTrue('Lege sleutel houdt de bestaande', !empty($kept['ok']));
$prefRaw = (string) file_get_contents((string) $prefPath);
assertTrue('Oude sleutel blijft staan', strpos($prefRaw, 'personal-send-key') !== false);

$invalid = GrokBot::saveUserWebhook('assignee-webhook@example.com', 'ftp://example.com/hook', 'nieuw', 'Tim Falken');
assertFalse('ftp-URL wordt geweigerd', !empty($invalid['ok']));
assertSame('Foutcode ongeldige URL', 'invalid_webhook_url', (string) ($invalid['error_code'] ?? ''));

$missingKey = GrokBot::saveUserWebhook('actor-webhook@example.com', 'https://example.com/actor-webhook', '');
assertSame('Eerste opslaan zonder sleutel', 'send_key_required', (string) ($missingKey['error_code'] ?? ''));

GrokBot::saveUserWebhook(
    'actor-webhook@example.com',
    'https://example.com/actor-webhook',
    'actor-send-key',
    'Actor Naam'
);
GrokBot::saveUserWebhook(
    'requester-webhook@example.com',
    'https://example.com/requester-webhook',
    'requester-send-key',
    'Aanvrager'
);

$created = $store->createTicket(
    'Persoonlijke webhook',
    'Anders',
    'requester-webhook@example.com',
    'Beschrijving',
    [],
    0,
    [],
    null,
    'assignee-webhook@example.com'
);
$ticketId = (int) $created['ticket_id'];
assertSame('Nieuw ticket: centraal én persoonlijk', [
    'https://example.com/global-webhook',
    'https://example.com/personal-webhook',
], capturedUrls());
assertSame('Centrale sleutel', 'global-send-key', (string) ($captured[0]['send_key'] ?? ''));
assertSame('Persoonlijke sleutel', 'personal-send-key', (string) ($captured[1]['send_key'] ?? ''));
assertFalse('Aanvrager-webhook vuurt niet bij nieuw ticket', in_array('https://example.com/requester-webhook', capturedUrls(), true));

$globalClient = loadApiClientByToken((string) ($captured[0]['payload']['api_key'] ?? ''));
$personalClient = loadApiClientByToken((string) ($captured[1]['payload']['api_key'] ?? ''));
assertSame('Centrale afzender', 'grok-bot@kvt.nl', (string) ($globalClient['email'] ?? ''));
assertSame('Centrale standaardnaam', 'Grok', (string) ($globalClient['default_name'] ?? ''));
assertSame('Centrale standaardtitel', 'Bot', (string) ($globalClient['default_title'] ?? ''));
assertSame('Persoonlijke afzender', 'assignee-webhook@example.com', (string) ($personalClient['email'] ?? ''));
assertSame('Persoonlijke standaardnaam', 'Tim Falken', (string) ($personalClient['default_name'] ?? ''));
assertSame('Persoonlijke standaardtitel', 'Assistent', (string) ($personalClient['default_title'] ?? ''));

$globalIdentity = GrokBot::resolveDefaultIdentity('grok-bot@kvt.nl');
assertSame('Globale identiteit naam', 'Grok', (string) ($globalIdentity['name'] ?? ''));
assertSame('Globale identiteit titel', 'Bot', (string) ($globalIdentity['title'] ?? ''));
$plainUserIdentity = GrokBot::resolveDefaultIdentity('assignee-webhook@example.com');
assertSame('Gewoon bericht krijgt geen botnaam', '', (string) ($plainUserIdentity['name'] ?? ''));
assertSame('Gewoon bericht krijgt geen bottitel', '', (string) ($plainUserIdentity['title'] ?? ''));

$posted = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Advies van de persoonlijke bot.',
    'ghost' => true,
], $personalClient, false);
assertTrue('Persoonlijk ghost-bericht slaagt', !empty($posted['success']));
assertSame('Bericht-afzender', 'assignee-webhook@example.com', (string) ($posted['sender_email'] ?? ''));
assertSame('Bericht-naam', 'Tim Falken', (string) ($posted['sender_name'] ?? ''));
assertSame('Bericht-titel', 'Assistent', (string) ($posted['sender_title'] ?? ''));
assertTrue('Bericht telt als AI', GrokBot::isAiAssistantMessage(is_array($posted['message'] ?? null) ? $posted['message'] : []));
$afterBot = $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', true);
assertSame('Laatste bericht is AI', 1, (int) ($afterBot['last_message_is_ai'] ?? 0));
assertFalse(
    'AI-advies blijft dicht na botbericht',
    isTicketAiAdviceAvailable($afterBot, is_array($afterBot['messages'] ?? null) ? $afterBot['messages'] : null)
);

$overridden = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Naam uit de botpayload.',
    'ghost' => true,
    'sender_name' => 'ICT-Bot',
    'sender_title' => 'Hulp',
], $personalClient, false);
assertSame('Bot overschrijft naam', 'ICT-Bot', (string) ($overridden['sender_name'] ?? ''));
assertSame('Bot overschrijft titel', 'Hulp', (string) ($overridden['sender_title'] ?? ''));

$store->addMessage($ticketId, 'assignee-webhook@example.com', 'admin', 'Menselijk antwoord');
$afterHuman = $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', true);
assertSame('Menselijk bericht is geen AI', 0, (int) ($afterHuman['last_message_is_ai'] ?? 1));

resetCaptured();
GrokBot::notifyUserRepliedWhileWaiting(
    $store,
    $ticketId,
    'user',
    false,
    'afwachtende op gebruiker',
    'Ik heb het geprobeerd'
);
assertSame('User-reply gaat naar centraal en behandelaar', [
    'https://example.com/global-webhook',
    'https://example.com/personal-webhook',
], capturedUrls());

resetCaptured();
$adviceSent = GrokBot::notifyReEvaluateAndAdvise($store, $ticketId, 'Kijk nog eens', null, [
    'actor_email' => 'actor-webhook@example.com',
    'assigned_email' => 'assignee-webhook@example.com',
]);
assertTrue('AI-advies met persoonlijke webhooks slaagt', $adviceSent);
assertSame('AI-advies: centraal, behandelaar en aanvrager van advies', [
    'https://example.com/global-webhook',
    'https://example.com/personal-webhook',
    'https://example.com/actor-webhook',
], capturedUrls());

resetCaptured();
GrokBot::saveUserWebhook(
    'assignee-webhook@example.com',
    'https://example.com/global-webhook',
    'global-send-key',
    'Tim Falken'
);
$store->createTicket(
    'Zelfde bestemming',
    'Anders',
    'requester-webhook@example.com',
    'Nog een',
    [],
    0,
    [],
    null,
    'assignee-webhook@example.com'
);
assertSame('Zelfde URL en sleutel vuurt één keer', ['https://example.com/global-webhook'], capturedUrls());

resetCaptured();
$GLOBALS['grokBot']['enabled'] = false;
GrokBot::saveUserWebhook(
    'assignee-webhook@example.com',
    'https://example.com/personal-webhook',
    'personal-send-key',
    'Tim Falken'
);
$onlyPersonal = GrokBot::notifyTicketSolved($store, $ticketId, null, [
    'assigned_email' => 'assignee-webhook@example.com',
]);
assertSame('Alleen persoonlijk als centraal uit staat', ['https://example.com/personal-webhook'], capturedUrls());

resetCaptured();
GrokBot::clearUserWebhook('assignee-webhook@example.com');
assertFalse('Wissen haalt de webhook weg', GrokBot::publicUserWebhook('assignee-webhook@example.com')['configured']);
$GLOBALS['grokBot']['enabled'] = true;
$store->updateTicket($ticketId, 'afgehandeld', 'assignee-webhook@example.com', 0, null);
assertSame('Na wissen alleen de centrale webhook', ['https://example.com/global-webhook'], capturedUrls());

$_SESSION['csrf_token'] = 'csrf-test-token';
$_SESSION['user'] = [
    'email' => 'session-webhook@example.com',
    'admin' => true,
];
$apiSaved = handleSaveGrokWebhookApiAction([
    'csrf_token' => 'csrf-test-token',
    'webhook_url' => 'https://example.com/session-webhook',
    'send_key' => 'session-secret',
    'viewer_email' => 'someone-else@example.com',
], null);
assertTrue('API slaat webhook op voor de sessie', !empty($apiSaved['success']));
assertSame('API-URL', 'https://example.com/session-webhook', (string) ($apiSaved['grok_webhook']['webhook_url'] ?? ''));
assertFalse('API-antwoord bevat de sleutel niet', strpos(json_encode($apiSaved), 'session-secret') !== false);
$sessionPref = (string) file_get_contents((string) getUserPrefsPath('session-webhook@example.com'));
assertTrue('Sleutel hoort bij de sessiegebruiker', strpos($sessionPref, 'session-secret') !== false);
assertFalse('viewer_email maakt geen tweede prefs-bestand', is_file((string) getUserPrefsPath('someone-else@example.com')));

$apiKept = handleSaveGrokWebhookApiAction([
    'csrf_token' => 'csrf-test-token',
    'webhook_url' => 'https://example.com/session-webhook-2',
    'send_key' => '',
], null);
assertTrue('API houdt sleutel bij lege invoer', !empty($apiKept['success']));
$sessionPref = (string) file_get_contents((string) getUserPrefsPath('session-webhook@example.com'));
assertTrue('API-sleutel ongewijzigd', strpos($sessionPref, 'session-secret') !== false);
assertTrue('API-URL bijgewerkt', strpos($sessionPref, 'session-webhook-2') !== false);

$_SESSION['user']['admin'] = false;
$denied = handleSaveGrokWebhookApiAction([
    'csrf_token' => 'csrf-test-token',
    'webhook_url' => 'https://example.com/nope',
    'send_key' => 'nope',
], null);
assertFalse('Niet-beheerder mag niet opslaan', !empty($denied['success']));

$_SESSION['user']['admin'] = true;
$badCsrf = handleSaveGrokWebhookApiAction([
    'csrf_token' => 'fout',
    'webhook_url' => 'https://example.com/nope',
    'send_key' => 'nope',
], null);
assertSame('CSRF blokkeert opslaan', 'csrf', (string) ($badCsrf['error'] ?? ''));

$cleared = handleSaveGrokWebhookApiAction([
    'csrf_token' => 'csrf-test-token',
    'clear' => true,
], null);
assertTrue('API wissen slaagt', !empty($cleared['success']));
assertFalse('API wissen leegt de webhook', !empty($cleared['grok_webhook']['configured']));

echo PHP_EOL;
if ($failed === 0) {
    echo "Alle {$passed} checks geslaagd." . PHP_EOL;
    exit(0);
}

echo "{$failed} check(s) gefaald, {$passed} geslaagd." . PHP_EOL;
exit(1);
