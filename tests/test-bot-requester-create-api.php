<?php
/**
 * Service-create: eigen aanvragersnaam, mailonderdrukking, en een beschrijving
 * die heel blijft tot een harde bytegrens.
 */

echo "=== TEST: botaanvrager, mailonderdrukking, volledige beschrijving ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['SERVER_ADDR'] = '203.0.113.1';
$_SERVER['PHP_SELF'] = '/asclepius/api.php';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SESSION = [];
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
        . "\$ictUsers = ['ict@kvt.nl'];\n"
        . "\$ictUserColors = [];\n"
        . "\$mailSettings = ['subject_prefix' => '', 'from_email' => 'kvtbot@kvt.nl', 'from_name' => 'KVT Bot'];\n"
        . "\$grokBot = ['enabled' => false];\n"
    );
    if ($written === false) {
        echo "FAIL: kon stub auth.php niet schrijven." . PHP_EOL;
        exit(1);
    }
    $createdStubAuth = true;
}

$directoryFile = __DIR__ . '/../web/data/user_directory_emails.json';
$directoryExisted = is_file($directoryFile);
$apiClientsDir = __DIR__ . '/../web/data/api_clients';
$apiClientFilesBefore = glob($apiClientsDir . '/*.json') ?: [];

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}

require __DIR__ . '/../web/api.php';

$passed = 0;
$failed = 0;

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

function assertTrue(string $label, bool $actual): void
{
    assertSame($label, true, $actual);
}

function assertFalse(string $label, bool $actual): void
{
    assertSame($label, false, $actual);
}

register_shutdown_function(static function () use ($authPath, $createdStubAuth, $directoryFile, $directoryExisted, $apiClientFilesBefore): void {
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
    if (!$directoryExisted && is_file($directoryFile)) {
        @unlink($directoryFile);
    }
    $current = glob(dirname($apiClientFilesBefore[0] ?? '') ?: '') ?: [];
    unset($current);
    $now = glob(__DIR__ . '/../web/data/api_clients/*.json') ?: [];
    foreach ($now as $file) {
        if (!in_array($file, $apiClientFilesBefore, true)) {
            @unlink($file);
        }
    }
    GrokBot::setDeliveryOverride(null);
    discardQueuedTicketTranslations();
});

function makeBotStore(): TicketStore
{
    $store = new TicketStore(
        ':memory:',
        sys_get_temp_dir() . '/asclepius_test_uploads_bot_requester',
        ['ict@kvt.nl'],
        TICKET_CATEGORIES
    );
    $store->saveCategoryMatrix([
        'ict@kvt.nl' => [
            'Business Central' => true,
            'Anders' => true,
        ],
    ], [
        'ict@kvt.nl' => true,
    ]);

    return $store;
}

function createViaApi(TicketStore $store, array $payload, ?array $apiClient, bool $serviceKey): array
{
    $GLOBALS['asclepiusLastRawRequestBytes'] = null;

    return handleCreateTicketApiAction($store, $payload, $apiClient, $serviceKey);
}

function enrichedTicket(array $response): array
{
    $body = enrichApiResponseWithUserNames($response['body'] ?? []);
    $ticket = $body['ticket'] ?? null;

    return is_array($ticket) ? $ticket : [];
}

function nineCellDataRows(string $text): int
{
    $count = 0;
    foreach (preg_split("/\r\n|\n|\r/", $text) ?: [] as $line) {
        if (!str_starts_with($line, '|')) {
            continue;
        }
        $cells = array_map('trim', explode('|', trim($line, "| \t")));
        if ($cells === [] || in_array('Projectnummer', $cells, true) || in_array('Bedrijf', $cells, true)) {
            continue;
        }
        $separator = true;
        foreach ($cells as $cell) {
            if ($cell === '' || preg_match('/^:?-{3,}:?$/', $cell) !== 1) {
                $separator = false;
                break;
            }
        }
        if ($separator) {
            continue;
        }
        if (count($cells) === 9) {
            $count++;
        }
    }

    return $count;
}

$GLOBALS['ictUsers'] = ['ict@kvt.nl'];
$GLOBALS['mailSettings'] = ['subject_prefix' => '', 'from_email' => 'kvtbot@kvt.nl', 'from_name' => 'KVT Bot'];
$GLOBALS['grokBot'] = ['enabled' => false];
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => null];
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$GLOBALS['asclepiusLastRawRequestBytes'] = null;
$_SESSION['user'] = ['name' => 'Sessiepersoon', 'display_name' => 'Sessiepersoon', 'email' => 'sessie@example.com'];

$store = makeBotStore();
$botEmail = 'ariadne@bots.invalid';
$base = [
    'title' => 'Projecten zonder projectmanager',
    'category' => 'Business Central',
    'description' => "Korte controle.\n",
    'requester_email' => $botEmail,
    'requester_name' => 'Ariadne',
];

echo "Identiteit" . PHP_EOL;
$beforeRoles = $store->listIctRoleMemberEmails();
$beforeClients = glob($apiClientsDir . '/*.json') ?: [];
$created = createViaApi($store, $base, null, true);
assertSame('Service-key maakt het ticket', 201, (int) ($created['status'] ?? 0));
$ticket = enrichedTicket($created);
assertSame('user_email is het botadres', $botEmail, (string) ($ticket['user_email'] ?? ''));
assertSame('requester_display_name', 'Ariadne', (string) ($ticket['requester_display_name'] ?? ''));
assertSame('requester_name', 'Ariadne', (string) ($ticket['requester_name'] ?? ''));
assertSame('user_name valt niet terug op de sessie', 'Ariadne', (string) ($ticket['user_name'] ?? ''));
assertFalse('user_name is niet het e-mailadres', (string) ($ticket['user_name'] ?? '') === $botEmail);
$messages = is_array($ticket['messages'] ?? null) ? $ticket['messages'] : [];
$first = is_array($messages[0] ?? null) ? $messages[0] : [];
assertSame('bericht-afzender', $botEmail, (string) ($first['sender_email'] ?? ''));
assertSame('sender_display_name', 'Ariadne', (string) ($first['sender_display_name'] ?? ''));
assertSame('sender_name', 'Ariadne', (string) ($first['sender_name'] ?? ''));
assertSame('deelnemers blijven alleen de aanvrager', [$botEmail], $ticket['participant_emails'] ?? null);
$participants = is_array($ticket['participants'] ?? null) ? $ticket['participants'] : [];
assertSame('deelnemersnaam', 'Ariadne', (string) ($participants[0]['name'] ?? ''));
assertSame('geen rolleden erbij', $beforeRoles, $store->listIctRoleMemberEmails());
assertSame('geen api-client erbij', $beforeClients, glob($apiClientsDir . '/*.json') ?: []);
assertFalse('geen directorybestand voor de bot', is_file($directoryFile) && str_contains((string) file_get_contents($directoryFile), $botEmail));

$trimmed = createViaApi($store, array_merge($base, [
    'requester_email' => 'trim.bot@example.com',
    'requester_name' => '  Ariadne  ',
    'description' => 'trim',
]), null, true);
assertSame('naam wordt getrimd en komt zo terug', 'Ariadne', (string) (enrichedTicket($trimmed)['user_name'] ?? ''));

$longName = str_repeat('ë', 80);
$longOk = createViaApi($store, array_merge($base, [
    'requester_email' => 'langenaam@example.com',
    'requester_name' => $longName,
    'description' => 'naamlengte',
]), null, true);
assertSame('80 tekens is geldig', 201, (int) ($longOk['status'] ?? 0));
assertSame('80 tekens komen terug', $longName, (string) (enrichedTicket($longOk)['requester_display_name'] ?? ''));
$tooLongName = createViaApi($store, array_merge($base, [
    'requester_name' => str_repeat('ë', 81),
    'description' => 'te lange naam',
]), null, true);
assertSame('81 tekens wordt geweigerd', 422, (int) ($tooLongName['status'] ?? 0));
assertSame('ongeldige naam-code', 'invalid_requester_name', (string) ($tooLongName['body']['error'] ?? ''));

$control = createViaApi($store, array_merge($base, ['requester_name' => "Ariadne\nBot"]), null, true);
assertSame('stuurteken in de naam wordt geweigerd', 'invalid_requester_name', (string) ($control['body']['error'] ?? ''));

echo PHP_EOL . "Bestaand persoon en rechten" . PHP_EOL;
rememberUserDirectoryName('existing.human@example.com', 'Bestaande Naam');
$beforeHuman = formatUserDisplayName('existing.human@example.com');
$conflict = createViaApi($store, array_merge($base, [
    'requester_email' => 'existing.human@example.com',
    'requester_name' => 'Ariadne',
    'description' => 'mag niet',
]), null, true);
assertSame('bekende directorynaam wordt geweigerd', 422, (int) ($conflict['status'] ?? 0));
assertSame('conflictcode', 'requester_identity_conflict', (string) ($conflict['body']['error'] ?? ''));
assertSame('directorynaam ongewijzigd', 'Bestaande Naam', formatUserDisplayName('existing.human@example.com'));
assertSame('directorynaam was al gezet', $beforeHuman, formatUserDisplayName('existing.human@example.com'));
$ictConflict = createViaApi($store, array_merge($base, [
    'requester_email' => 'ict@kvt.nl',
    'requester_name' => 'Ariadne',
]), null, true);
assertSame('ICT-adres krijgt geen botnaam', 'requester_identity_conflict', (string) ($ictConflict['body']['error'] ?? ''));

$remote = '198.51.100.8';
$previousRemote = $_SERVER['REMOTE_ADDR'];
$previousServer = $_SERVER['SERVER_ADDR'];
$_SERVER['REMOTE_ADDR'] = $remote;
$_SERVER['SERVER_ADDR'] = '198.51.100.1';
$sessionClient = ['email' => 'sessie@example.com', 'is_admin' => false, 'kind' => 'session', 'oid' => 'sessie'];
$forbidden = createViaApi($store, $base, $sessionClient, false);
assertSame('sessie-key mag geen botnaam', 403, (int) ($forbidden['status'] ?? 0));
assertSame('forbidden-code', 'requester_name_forbidden', (string) ($forbidden['body']['error'] ?? ''));
$loginClient = ['email' => 'sessie@example.com', 'is_admin' => false, 'kind' => 'login_rotating', 'oid' => 'sessie'];
$loginForbidden = createViaApi($store, $base, $loginClient, true);
assertSame('login-key mag geen botnaam, ook niet met service-vlag', 403, (int) ($loginForbidden['status'] ?? 0));
$webhookClient = ['email' => 'grok-bot@kvt.nl', 'is_admin' => true, 'kind' => 'grok_bot_ephemeral', 'oid' => 'grok'];
$webhookForbidden = createViaApi($store, [
    'title' => 'Projecten zonder projectmanager',
    'category' => 'Business Central',
    'description' => "Korte controle.\n",
    'requester_email' => $botEmail,
    'suppress_create_mail' => true,
], $webhookClient, false);
assertSame('webhook-key mag de mailvlag niet', 403, (int) ($webhookForbidden['status'] ?? 0));
assertSame('mailvlag-forbidden', 'suppress_create_mail_forbidden', (string) ($webhookForbidden['body']['error'] ?? ''));
$serviceRemote = createViaApi($store, array_merge($base, [
    'requester_email' => 'remote.bot@example.com',
    'description' => 'vanaf buiten',
    'suppress_create_mail' => true,
]), null, true);
assertSame('service-key van buitenaf mag wel', 201, (int) ($serviceRemote['status'] ?? 0));
assertSame('readback vanaf buitenaf', 'Ariadne', (string) (enrichedTicket($serviceRemote)['user_name'] ?? ''));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$trusted = createViaApi($store, array_merge($base, [
    'requester_email' => 'localhost.bot@example.com',
    'description' => 'localhost',
    'suppress_create_mail' => true,
]), null, false);
assertSame('trusted localhost zonder client mag de botnaam', 201, (int) ($trusted['status'] ?? 0));
$_SERVER['REMOTE_ADDR'] = $previousRemote;
$_SERVER['SERVER_ADDR'] = $previousServer;

echo PHP_EOL . "Mail" . PHP_EOL;
$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$plain = createViaApi($store, [
    'title' => 'Gewone create',
    'category' => 'Anders',
    'description' => 'mail-token-default',
    'user_email' => 'persoon@example.com',
], null, true);
assertSame('zonder vlag blijft create slagen', 201, (int) ($plain['status'] ?? 0));
assertFalse('vlag staat uit', (bool) ($plain['body']['suppress_create_mail'] ?? true));
$defaultMails = array_values(array_filter(
    $GLOBALS['asclepiusCapturedTicketEmails'],
    static fn(array $mail): bool => in_array('persoon@example.com', $mail['recipients'] ?? [], true)
));
assertSame('standaard gaat de bevestiging weg', 1, count($defaultMails));
assertTrue('bevestiging bevat de beschrijving', str_contains((string) ($defaultMails[0]['plain'] ?? ''), 'mail-token-default'));

$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$invalidDomain = createViaApi($store, [
    'title' => 'Geen automatische mailstop',
    'category' => 'Anders',
    'description' => 'mail-token-invalid-domain',
    'requester_email' => 'nomail.not@bots.invalid',
], null, true);
$invalidMails = array_values(array_filter(
    $GLOBALS['asclepiusCapturedTicketEmails'],
    static fn(array $mail): bool => in_array('nomail.not@bots.invalid', $mail['recipients'] ?? [], true)
));
assertSame('.invalid krijgt toch de bevestiging', 1, count($invalidMails));
assertSame('create van .invalid slaagt', 201, (int) ($invalidDomain['status'] ?? 0));

$GLOBALS['asclepiusCapturedTicketEmails'] = [];
$suppressed = createViaApi($store, array_merge($base, [
    'requester_email' => 'stille.bot@example.com',
    'description' => 'mail-token-suppress',
    'suppress_create_mail' => true,
]), null, true);
assertSame('onderdrukking slaagt', 201, (int) ($suppressed['status'] ?? 0));
assertTrue('antwoord meldt onderdrukking', (bool) ($suppressed['body']['suppress_create_mail'] ?? false));
assertSame('geen mail verzonden', [], $GLOBALS['asclepiusCapturedTicketEmails']);
$suppressedId = (int) ($suppressed['body']['ticket_id'] ?? 0);
$suppressedTicket = $store->getTicket($suppressedId, true, '');
$suppressedMessageId = firstTicketMessageId(is_array($suppressedTicket) ? $suppressedTicket : []);
assertSame('geen wachtrijrij voor de bevestiging', [], $store->listUserUpdateMailQueue($suppressedMessageId));

$falseString = createViaApi($store, array_merge($base, [
    'requester_email' => 'false.bot@example.com',
    'description' => 'mail-token-false',
    'suppress_create_mail' => 'false',
]), null, true);
assertSame('string false is geldig en uit', false, (bool) ($falseString['body']['suppress_create_mail'] ?? true));
assertTrue(
    'string false stuurt de mail',
    count(array_filter(
        $GLOBALS['asclepiusCapturedTicketEmails'],
        static fn(array $mail): bool => in_array('false.bot@example.com', $mail['recipients'] ?? [], true)
    )) === 1
);

$badFlag = createViaApi($store, array_merge($base, [
    'description' => 'mail-token-maybe',
    'suppress_create_mail' => 'maybe',
]), null, true);
assertSame('ongeldige vlag', 422, (int) ($badFlag['status'] ?? 0));
assertSame('ongeldige vlag-code', 'invalid_suppress_create_mail', (string) ($badFlag['body']['error'] ?? ''));
assertFalse(
    'ongeldige vlag maakt geen mail',
    str_contains(json_encode($GLOBALS['asclepiusCapturedTicketEmails']) ?: '', 'mail-token-maybe')
);
$badNumber = createViaApi($store, array_merge($base, ['suppress_create_mail' => 2]), null, true);
assertSame('getal 2 is geen bool', 'invalid_suppress_create_mail', (string) ($badNumber['body']['error'] ?? ''));

$capturedWebhooks = [];
$GLOBALS['grokBot'] = [
    'enabled' => true,
    'webhook_url' => 'https://example.com/asclepius-webhook',
    'send_key' => 'test-send-key',
    'sender_email' => 'grok-bot@kvt.nl',
];
GrokBot::setDeliveryOverride(static function (string $url, array $payload, string $sendKey) use (&$capturedWebhooks): void {
    $capturedWebhooks[] = $payload;
});
$withHook = createViaApi($store, array_merge($base, [
    'requester_email' => 'hook.bot@example.com',
    'description' => 'webhook-blijft',
    'suppress_create_mail' => true,
]), null, true);
assertSame('webhook blijft new-ticket', ['new-ticket'], array_map(
    static fn(array $payload): string => (string) ($payload['type'] ?? ''),
    $capturedWebhooks
));
assertFalse('webhook bevat de beschrijving niet', array_key_exists('description', $capturedWebhooks[0] ?? []));
assertSame('mail blijft uit terwijl de webhook gaat', true, (bool) ($withHook['body']['suppress_create_mail'] ?? false));
GrokBot::setDeliveryOverride(null);
$GLOBALS['grokBot'] = ['enabled' => false];
foreach ($capturedWebhooks as $payload) {
    $apiKey = (string) ($payload['api_key'] ?? '');
    if ($apiKey !== '') {
        @unlink(GrokBot::apiClientsDirectory() . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json');
    }
}

echo PHP_EOL . "Beschrijving" . PHP_EOL;
$syntheticPath = __DIR__ . '/fixtures/synthetic-ticket-description.md';
$synthetic = (string) file_get_contents($syntheticPath);
assertTrue('synthetische fixture eindigt op een newline', str_ends_with($synthetic, "\n"));
$syntheticCreated = createViaApi($store, array_merge($base, [
    'requester_email' => 'tabel.bot@example.com',
    'description' => $synthetic,
    'suppress_create_mail' => true,
]), null, true);
assertSame('synthetische body slaagt', 201, (int) ($syntheticCreated['status'] ?? 0));
$syntheticTicket = enrichedTicket($syntheticCreated);
$syntheticRead = $store->getTicket((int) ($syntheticCreated['body']['ticket_id'] ?? 0), true, '');
$syntheticMessage = '';
foreach (is_array($syntheticRead['messages'] ?? null) ? $syntheticRead['messages'] : [] as $message) {
    if (is_array($message)) {
        $syntheticMessage = (string) ($message['message_text'] ?? '');
        break;
    }
}
assertSame('description-hash', hash('sha256', $synthetic), hash('sha256', (string) ($syntheticTicket['description'] ?? '')));
assertSame('bericht-hash', hash('sha256', $synthetic), hash('sha256', $syntheticMessage));
assertSame('description gelijk aan fixture', $synthetic, (string) ($syntheticRead['description'] ?? ''));
$html = formatTicketMessageText($syntheticMessage);
assertTrue('tabel wordt een tabel', str_contains($html, 'class="message-md-table"'));
assertTrue('cel met ë blijft', str_contains($html, 'ë'));
assertFalse('script wordt niet raw HTML', str_contains($html, '<script>'));
assertTrue('script is geëscaped', str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'));
assertFalse('img-tag wordt geen element', str_contains($html, '<img'));
assertTrue('onerror blijft tekst in de geëscapete cel', str_contains($html, '&lt;img src=x onerror=alert(1)&gt;'));

$atLimit = str_repeat('a', TICKET_DESCRIPTION_MAX_BYTES);
$limitOk = createViaApi($store, array_merge($base, [
    'requester_email' => 'limiet.bot@example.com',
    'description' => $atLimit,
    'suppress_create_mail' => true,
]), null, true);
assertSame('exact de grens slaagt', 201, (int) ($limitOk['status'] ?? 0));
assertSame('grens-bytes', TICKET_DESCRIPTION_MAX_BYTES, (int) ($limitOk['body']['description_bytes'] ?? 0));
$limitRead = $store->getTicket((int) ($limitOk['body']['ticket_id'] ?? 0), true, '');
assertSame('grens komt heel terug', TICKET_DESCRIPTION_MAX_BYTES, strlen((string) ($limitRead['description'] ?? '')));
$beforeOver = count($store->getTickets(true, ''));
$over = createViaApi($store, array_merge($base, [
    'description' => str_repeat('b', TICKET_DESCRIPTION_MAX_BYTES + 1),
    'suppress_create_mail' => true,
]), null, true);
assertSame('boven de grens faalt', 422, (int) ($over['status'] ?? 0));
assertSame('te-groot-code', 'description_too_large', (string) ($over['body']['error'] ?? ''));
assertSame('max_bytes', TICKET_DESCRIPTION_MAX_BYTES, (int) ($over['body']['max_bytes'] ?? 0));
assertSame('actual_bytes', TICKET_DESCRIPTION_MAX_BYTES + 1, (int) ($over['body']['actual_bytes'] ?? 0));
assertSame('te groot slaat niets op', $beforeOver, count($store->getTickets(true, '')));

$GLOBALS['asclepiusLastRawRequestBytes'] = TICKET_CREATE_JSON_MAX_BYTES + 1;
$tooBigRequest = handleCreateTicketApiAction($store, array_merge($base, [
    'description' => 'klein',
    'suppress_create_mail' => true,
]), null, true);
$GLOBALS['asclepiusLastRawRequestBytes'] = null;
assertSame('JSON-body boven de grens', 413, (int) ($tooBigRequest['status'] ?? 0));
assertSame('request_too_large', 'request_too_large', (string) ($tooBigRequest['body']['error'] ?? ''));
assertSame('JSON-max', TICKET_CREATE_JSON_MAX_BYTES, (int) ($tooBigRequest['body']['max_bytes'] ?? 0));

$transportCalls = 0;
$transport = static function () use (&$transportCalls): array {
    $transportCalls++;

    return ['status' => 500, 'body' => '', 'error' => 'geen externe vertaling in deze test'];
};
$GLOBALS['__translationProviderCache'] = [
    'initialized' => true,
    'provider' => new CopilotTranslationProvider('https://example.invalid/flow', 5.0, $transport),
];
discardQueuedTicketTranslations();
$queuedSmall = createViaApi($store, array_merge($base, [
    'requester_email' => 'vertaling.bot@example.com',
    'description' => 'korte tekst voor de wachtrij',
    'suppress_create_mail' => true,
]), null, true);
assertTrue('korte beschrijving gaat de vertaalwachtrij in', (bool) ($queuedSmall['body']['description_translation_queued'] ?? false));
assertTrue(
    'korte tekst heeft geen skip',
    array_key_exists('translation_skip_reason', $queuedSmall['body'])
    && $queuedSmall['body']['translation_skip_reason'] === null
);
$overTranslation = str_repeat('c', TICKET_TRANSLATION_MAX_BYTES + 1);
$queuedLarge = createViaApi($store, array_merge($base, [
    'requester_email' => 'groot.vertaling@example.com',
    'description' => $overTranslation,
    'suppress_create_mail' => true,
]), null, true);
assertSame('tekst boven de vertaalgrens blijft opgeslagen', 201, (int) ($queuedLarge['status'] ?? 0));
assertFalse('die tekst gaat de wachtrij niet in', (bool) ($queuedLarge['body']['description_translation_queued'] ?? true));
assertSame('skip-reden', 'description_exceeds_translation_limit', (string) ($queuedLarge['body']['translation_skip_reason'] ?? ''));
$largeRead = $store->getTicket((int) ($queuedLarge['body']['ticket_id'] ?? 0), true, '');
assertSame('oorspronkelijke tekst heel', strlen($overTranslation), strlen((string) ($largeRead['description'] ?? '')));
assertSame('vertaler is niet aangeroepen', 0, $transportCalls);
discardQueuedTicketTranslations();
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => null];

$fixture = getenv('ASCLEPIUS_LARGE_BODY_FIXTURE');
$fixturePath = is_string($fixture) && $fixture !== '' ? $fixture : '';
if ($fixturePath === '' || !is_file($fixturePath)) {
    $fallback = '/home/ubuntu/.cursor/projects/agent-repos-Asclepius/uploads/ticketbody_f84b.md';
    $fixturePath = is_file($fallback) ? $fallback : '';
}
if ($fixturePath === '') {
    echo "  (geen lokale volledige body; rondreis van het echte bestand overgeslagen)" . PHP_EOL;
} else {
    $real = (string) file_get_contents($fixturePath);
    $realHash = hash('sha256', $real);
    assertSame('lokale body-bytes', 1432648, strlen($real));
    assertSame('lokale body-hash', '49134d7f5de93964ade5dc0c16ee5535bb8bdb72a7d538be4181344a73714712', $realHash);
    assertSame('9063 projectrijen in de bron', 9063, nineCellDataRows($real));
    $payload = [
        'title' => 'Projecten zonder projectmanager',
        'category' => 'Business Central',
        'description' => $real,
        'requester_email' => 'ariadne@bots.invalid',
        'requester_name' => 'Ariadne',
        'suppress_create_mail' => true,
        'priority' => 0,
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    assertTrue('JSON van de echte body past onder de requestgrens', is_string($json) && strlen($json) <= TICKET_CREATE_JSON_MAX_BYTES);
    assertTrue('echte body past onder de beschrijvingsgrens', strlen($real) <= TICKET_DESCRIPTION_MAX_BYTES);
    $GLOBALS['__translationProviderCache'] = [
        'initialized' => true,
        'provider' => new CopilotTranslationProvider('https://example.invalid/flow', 5.0, $transport),
    ];
    discardQueuedTicketTranslations();
    $realCreated = createViaApi($store, $payload, null, true);
    assertSame('echte body slaagt', 201, (int) ($realCreated['status'] ?? 0));
    assertSame('echte body-bytes in het antwoord', strlen($real), (int) ($realCreated['body']['description_bytes'] ?? 0));
    assertSame('echte body niet naar de vertaler', 'description_exceeds_translation_limit', (string) ($realCreated['body']['translation_skip_reason'] ?? ''));
    assertFalse('echte body niet in de wachtrij', (bool) ($realCreated['body']['description_translation_queued'] ?? true));
    assertSame('vertaler blijft onaangeroepen', 0, $transportCalls);
    $realId = (int) ($realCreated['body']['ticket_id'] ?? 0);
    $realRead = $store->getTicket($realId, true, '');
    $stored = (string) ($realRead['description'] ?? '');
    $storedMessage = '';
    foreach (is_array($realRead['messages'] ?? null) ? $realRead['messages'] : [] as $message) {
        if (is_array($message)) {
            $storedMessage = (string) ($message['message_text'] ?? '');
            break;
        }
    }
    assertSame('opgeslagen hash', $realHash, hash('sha256', $stored));
    assertSame('bericht-hash van de echte body', $realHash, hash('sha256', $storedMessage));
    assertSame('9063 rijen na readback', 9063, nineCellDataRows($stored));
    assertSame('readback gelijk aan de bron', $real, $stored);
    $rendered = formatTicketMessageText($stored);
    assertTrue('echte body rendert een tabel', str_contains($rendered, 'class="message-md-table"'));
    assertFalse('echte body rendert geen script', str_contains($rendered, '<script'));
    assertSame('gerenderde body is niet ingekort tot leeg', true, strlen($rendered) > strlen($stored));
    discardQueuedTicketTranslations();
    $GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => null];
}

echo PHP_EOL . "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
