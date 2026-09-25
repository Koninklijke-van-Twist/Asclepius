<?php
/**
 * Uitgaande Grok-webhooks: user-reply na "afwachtende op gebruiker"
 * en ticket-reopened bij vertrek uit "afgehandeld".
 */

echo "=== TEST: Grok webhook user-reply en ticket-reopened ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '203.0.113.50';
$_SERVER['SERVER_ADDR'] = '10.0.0.1';
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

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}

require __DIR__ . '/../web/api.php';
require_once __DIR__ . '/../web/content/GrokBot.php';

$passed = 0;
$failed = 0;
$captured = [];

register_shutdown_function(static function () use ($authPath, $createdStubAuth, &$captured): void {
    GrokBot::setDeliveryOverride(null);
    $directory = GrokBot::apiClientsDirectory();
    foreach ($captured as $event) {
        $apiKey = (string) ($event['payload']['api_key'] ?? '');
        if ($apiKey === '') {
            continue;
        }
        @unlink($directory . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json');
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

function capturedTypes(): array
{
    global $captured;

    return array_map(
        static fn(array $event): string => (string) ($event['payload']['type'] ?? ''),
        $captured
    );
}

function assertWebhookShape(string $label, string $expectedType, int $ticketId): void
{
    global $captured;
    assertSame($label . ' aantal', 1, count($captured));
    if (count($captured) !== 1) {
        return;
    }

    $event = $captured[0];
    $payload = $event['payload'];
    assertSame($label . ' type', $expectedType, (string) ($payload['type'] ?? ''));
    assertSame($label . ' ticket_id', $ticketId, (int) ($payload['ticket_id'] ?? 0));
    assertSame($label . ' velden', ['type', 'ticket_id', 'api_key'], array_keys($payload));
    $apiKey = (string) ($payload['api_key'] ?? '');
    assertTrue($label . ' api_key is 64 hex', (bool) preg_match('/^[a-f0-9]{64}$/', $apiKey));
    assertSame($label . ' url', 'https://example.com/asclepius-webhook', (string) ($event['url'] ?? ''));
    assertSame($label . ' send_key', 'test-send-key', (string) ($event['send_key'] ?? ''));
    assertTrue(
        $label . ' ephemeral key bestand',
        is_file(GrokBot::apiClientsDirectory() . DIRECTORY_SEPARATOR . sha1($apiKey) . '.json')
    );
}

$GLOBALS['grokBot'] = [
    'enabled' => true,
    'webhook_url' => 'https://example.com/asclepius-webhook',
    'send_key' => 'test-send-key',
    'sender_email' => 'grok-bot@kvt.nl',
];
GrokBot::setDeliveryOverride(static function (string $url, array $payload, string $sendKey) use (&$captured): void {
    $captured[] = [
        'url' => $url,
        'payload' => $payload,
        'send_key' => $sendKey,
    ];
});

$GLOBALS['ictUsers'] = ['ict@kvt.nl', 'colleague@kvt.nl'];
$adminClient = [
    'email' => 'ict@kvt.nl',
    'is_admin' => true,
    'oid' => 'ict',
    'api_key' => 'abc',
];
$webhookClient = [
    'email' => 'grok-bot@kvt.nl',
    'is_admin' => true,
    'oid' => 'grok-bot',
    'api_key' => 'def',
];
$userClient = [
    'email' => 'user@kvt.nl',
    'is_admin' => false,
    'oid' => 'user',
    'api_key' => 'user-key',
];

$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_uploads_webhooks',
    ['ict@kvt.nl', 'colleague@kvt.nl'],
    TICKET_CATEGORIES
);
$store->saveCategoryMatrix([
    'ict@kvt.nl' => [
        'Anders' => true,
    ],
    'colleague@kvt.nl' => [
        'Anders' => true,
    ],
], [
    'ict@kvt.nl' => true,
    'colleague@kvt.nl' => true,
]);

$created = $store->createTicket(
    'Webhook ticket',
    'Anders',
    'user@kvt.nl',
    'Eerste beschrijving',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$ticketId = (int) $created['ticket_id'];
assertWebhookShape('Nieuw ticket', GrokBot::EVENT_NEW_TICKET, $ticketId);
resetCaptured();

$toProgress = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'in behandeling',
], $webhookClient, false);
assertTrue('Status naar in behandeling slaagt', !empty($toProgress['success']));
assertSame('Geen webhook bij gewone statuswijziging', [], capturedTypes());

$toWaiting = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'afwachtende op gebruiker',
], $webhookClient, false);
assertTrue('API zet afwachtende op gebruiker', !empty($toWaiting['success']));
assertSame('Wachten op gebruiker geeft geen webhook', [], capturedTypes());
assertSame(
    'Status staat op afwachtende op gebruiker',
    TICKET_STATUS_WAITING_ON_USER,
    (string) ($store->getTicket($ticketId, true, 'ict@kvt.nl')['status'] ?? '')
);

$userReply = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Ik heb de printer herstart.',
], $userClient, false);
assertTrue('Gebruikersantwoord via API slaagt', !empty($userReply['success']));
assertSame('Afzenderrol blijft user', 'user', (string) ($userReply['sender_role'] ?? ''));
assertWebhookShape('Gebruikersantwoord', GrokBot::EVENT_USER_REPLY, $ticketId);
assertSame(
    'API laat de status op afwachtende op gebruiker',
    TICKET_STATUS_WAITING_ON_USER,
    (string) ($store->getTicket($ticketId, true, 'ict@kvt.nl')['status'] ?? '')
);
resetCaptured();

$secondReply = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Tweede toelichting van de gebruiker.',
], $userClient, false);
assertTrue('Tweede gebruikersantwoord slaagt', !empty($secondReply['success']));
assertSame('Tweede antwoord vuurt opnieuw user-reply', [GrokBot::EVENT_USER_REPLY], capturedTypes());
resetCaptured();

$adminReply = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'ICT kijkt nog even mee.',
    'sender_email' => 'ict@kvt.nl',
], $adminClient, false);
assertTrue('ICT-bericht slaagt', !empty($adminReply['success']));
assertSame('ICT-bericht vuurt geen user-reply', [], capturedTypes());

$ghostReply = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Interne notitie terwijl we wachten.',
    'ghost' => true,
    'sender_email' => 'grok-bot@kvt.nl',
], $webhookClient, false);
assertTrue('Ghost-bericht slaagt', !empty($ghostReply['success']));
assertSame('Ghost-bericht vuurt geen user-reply', [], capturedTypes());

$otherStatusTicket = $store->createTicket(
    'Ander wachtstatus-ticket',
    'Anders',
    'user@kvt.nl',
    'Beschrijving',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$otherId = (int) $otherStatusTicket['ticket_id'];
resetCaptured();
foreach (['afwachtende op bestelling', 'afwachtende op derde partij', 'in behandeling'] as $status) {
    $changed = handleChangeTicketStatusApiAction($store, [
        'ticket_id' => $otherId,
        'status' => $status,
    ], $adminClient, false);
    assertTrue("Status {$status} slaagt", !empty($changed['success']));
    $reply = handleAddTicketMessageApiAction($store, [
        'ticket_id' => $otherId,
        'message' => 'Bericht bij status ' . $status,
    ], $userClient, false);
    assertTrue("Gebruikersbericht bij {$status} slaagt", !empty($reply['success']));
}
assertSame('Geen user-reply buiten afwachtende op gebruiker', [], capturedTypes());

$uiTicket = $store->createTicket(
    'UI-antwoord ticket',
    'Anders',
    'user@kvt.nl',
    'Beschrijving',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$uiId = (int) $uiTicket['ticket_id'];
resetCaptured();
$uiWaiting = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $uiId,
    'status' => TICKET_STATUS_WAITING_ON_USER,
], $webhookClient, false);
assertTrue('UI-ticket staat op wachten', !empty($uiWaiting['success']));
resetCaptured();

$uiTicketRow = $store->getTicket($uiId, true, 'user@kvt.nl');
assertSame(
    'Status vóór UI-antwoord',
    TICKET_STATUS_WAITING_ON_USER,
    (string) ($uiTicketRow['status'] ?? '')
);
$store->updateTicket($uiId, 'in behandeling', 'ict@kvt.nl', (int) ($uiTicketRow['priority'] ?? 0), null);
assertSame('Automatische stap naar in behandeling vuurt geen webhook', [], capturedTypes());
$uiPersisted = persistTicketReplyMessages(
    $store,
    $uiId,
    'user@kvt.nl',
    'user',
    'Dit is mijn antwoord vanuit het overzicht.',
    [],
    false,
    'Dit is mijn antwoord vanuit het overzicht.',
    null,
    null,
    (string) ($uiTicketRow['status'] ?? '')
);
assertTrue('UI-antwoord opgeslagen', (int) ($uiPersisted['message_id'] ?? 0) > 0);
assertWebhookShape('UI-antwoord na wachten op gebruiker', GrokBot::EVENT_USER_REPLY, $uiId);
assertSame(
    'UI zet de status daarna op in behandeling',
    'in behandeling',
    (string) ($store->getTicket($uiId, true, 'ict@kvt.nl')['status'] ?? '')
);
resetCaptured();

$caseReply = persistTicketReplyMessages(
    $store,
    $uiId,
    'user@kvt.nl',
    'user',
    'Hoofdletters in de oude status',
    [],
    false,
    'Hoofdletters in de oude status',
    null,
    null,
    'Afwachtende op gebruiker'
);
assertTrue('Case-insensitive wachtstatus slaat bericht op', (int) ($caseReply['message_id'] ?? 0) > 0);
assertSame('Hoofdlettervariant vuurt user-reply', [GrokBot::EVENT_USER_REPLY], capturedTypes());
resetCaptured();

GrokBot::notifyUserRepliedWhileWaiting(
    $store,
    $uiId,
    'admin',
    false,
    TICKET_STATUS_WAITING_ON_USER,
    'admintekst'
);
assertSame('Admin-rol vuurt niet', [], capturedTypes());
GrokBot::notifyUserRepliedWhileWaiting(
    $store,
    $uiId,
    'user',
    true,
    TICKET_STATUS_WAITING_ON_USER,
    'ghosttekst'
);
assertSame('Ghost vuurt niet', [], capturedTypes());
GrokBot::notifyUserRepliedWhileWaiting(
    $store,
    $uiId,
    'user',
    false,
    TICKET_STATUS_WAITING_ON_USER,
    '   '
);
assertSame('Leeg bericht vuurt niet', [], capturedTypes());

$solved = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'afgehandeld',
], $adminClient, false);
assertTrue('Afhandelen slaagt', !empty($solved['success']));
assertWebhookShape('Afhandelen', GrokBot::EVENT_TICKET_SOLVED, $ticketId);
resetCaptured();

$stillSolved = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'afgehandeld',
], $adminClient, false);
assertTrue('Idempotent afgehandeld slaagt', !empty($stillSolved['unchanged']));
assertSame('Idempotent afgehandeld vuurt niet', [], capturedTypes());

$store->updateTicket($ticketId, 'afgehandeld', 'colleague@kvt.nl', 0, null);
assertSame('Alleen assignee op afgehandeld vuurt niet', [], capturedTypes());

$reopened = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'in behandeling',
], $adminClient, false);
assertTrue('Heropenen naar in behandeling slaagt', !empty($reopened['success']));
assertWebhookShape('Heropenen', GrokBot::EVENT_TICKET_REOPENED, $ticketId);
resetCaptured();

$closedAgain = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'afgehandeld',
], $webhookClient, false);
assertTrue('Opnieuw afhandelen slaagt', !empty($closedAgain['success']));
resetCaptured();
$reopenedWaiting = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => TICKET_STATUS_WAITING_ON_USER,
], $webhookClient, false);
assertTrue('Heropenen naar wachten op gebruiker slaagt', !empty($reopenedWaiting['success']));
assertSame('Heropenen is ticket-reopened en geen user-reply', [GrokBot::EVENT_TICKET_REOPENED], capturedTypes());
resetCaptured();

$closedCustom = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'afgehandeld',
], $adminClient, false);
assertTrue('Afhandelen voor custom status slaagt', !empty($closedCustom['success']));
resetCaptured();
$customReopen = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'Wacht op leverancier',
], $adminClient, false);
assertTrue('Heropenen naar custom status slaagt', !empty($customReopen['success']));
assertSame('Custom status vanaf afgehandeld is ticket-reopened', [GrokBot::EVENT_TICKET_REOPENED], capturedTypes());
resetCaptured();

$reopenWithMessage = $store->createTicket(
    'Heropen met bericht',
    'Anders',
    'user@kvt.nl',
    'Beschrijving',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$reopenId = (int) $reopenWithMessage['ticket_id'];
resetCaptured();
$store->updateTicket($reopenId, 'afgehandeld', 'ict@kvt.nl', 0, null);
assertSame('Sluiten voor heropen-bericht', [GrokBot::EVENT_TICKET_SOLVED], capturedTypes());
resetCaptured();
$beforeReopen = $store->getTicket($reopenId, true, 'user@kvt.nl');
$store->updateTicket($reopenId, 'ingediend', 'ict@kvt.nl', 0, null);
$reopenMessage = persistTicketReplyMessages(
    $store,
    $reopenId,
    'user@kvt.nl',
    'user',
    'Mag dit ticket weer open?',
    [],
    false,
    'Mag dit ticket weer open?',
    null,
    null,
    (string) ($beforeReopen['status'] ?? '')
);
assertTrue('Bericht bij heropenen opgeslagen', (int) ($reopenMessage['message_id'] ?? 0) > 0);
assertSame(
    'Heropenen met gebruikersbericht is alleen ticket-reopened',
    [GrokBot::EVENT_TICKET_REOPENED],
    capturedTypes()
);
resetCaptured();

echo PHP_EOL . '--- AI Advies / re-evaluate-ticket-and-advise ---' . PHP_EOL;
$GLOBALS['grokBot']['enabled'] = true;
resetCaptured();

$adviceTicket = $store->createTicket(
    'AI advies ticket',
    'Anders',
    'user@kvt.nl',
    'Beschrijving voor advies',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$adviceId = (int) $adviceTicket['ticket_id'];
resetCaptured();

assertTrue('AI advies beschikbaar na aanmaken', $store->isAiAdviceAvailable($adviceId));
$adviceResult = handleRequestAiAdviceApiAction($store, [
    'ticket_id' => $adviceId,
    'advice_prompt' => 'Kijk vooral naar de printerdriver.',
    'viewer_email' => 'ict@kvt.nl',
    'user_is_admin' => true,
    'is_admin_portal' => true,
], $adminClient);
assertTrue('AI advies request slaagt', !empty($adviceResult['success']));
assertSame('AI advies pending na request', TicketStore::AI_ADVICE_AWAITING_AI, (int) ($adviceResult['ai_advice_pending'] ?? -1));
assertFalse('AI advies niet beschikbaar tijdens pending', $store->isAiAdviceAvailable($adviceId));
assertSame('AI advies webhook type', [GrokBot::EVENT_RE_EVALUATE_AND_ADVISE], capturedTypes());
assertSame(
    'AI advies payload velden',
    ['type', 'ticket_id', 'api_key', 'advice_prompt'],
    array_keys($captured[0]['payload'] ?? [])
);
assertSame(
    'AI advies advice_prompt',
    'Kijk vooral naar de printerdriver.',
    (string) ($captured[0]['payload']['advice_prompt'] ?? '')
);
assertSame(
    'AI advies type veld',
    GrokBot::EVENT_RE_EVALUATE_AND_ADVISE,
    (string) ($captured[0]['payload']['type'] ?? '')
);
resetCaptured();

$duplicateAdvice = handleRequestAiAdviceApiAction($store, [
    'ticket_id' => $adviceId,
    'advice_prompt' => 'Nog een keer',
    'viewer_email' => 'ict@kvt.nl',
    'user_is_admin' => true,
    'is_admin_portal' => true,
], $adminClient);
assertFalse('Tweede AI advies geweigerd tijdens pending', !empty($duplicateAdvice['success']));
assertSame('Geen tweede webhook tijdens pending', [], capturedTypes());

$earlyHuman = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $adviceId,
    'message' => 'ICT typt iets voordat de bot antwoordt.',
    'sender_email' => 'ict@kvt.nl',
], $adminClient, false);
assertTrue('Vroege ICT-bericht slaagt', !empty($earlyHuman['success']));
$afterEarly = $store->getTicket($adviceId, true, 'ict@kvt.nl');
assertSame(
    'Pending blijft awaiting-AI zonder bot-ghost',
    TicketStore::AI_ADVICE_AWAITING_AI,
    (int) ($afterEarly['ai_advice_pending'] ?? -1)
);
assertFalse('Nog niet beschikbaar na vroege human', $store->isAiAdviceAvailable($adviceId));

$botGhost = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $adviceId,
    'message' => 'AI ghost advies antwoord.',
    'ghost' => true,
    'sender_email' => 'grok-bot@kvt.nl',
], $webhookClient, false);
assertTrue('Bot ghost slaagt', !empty($botGhost['success']));
$afterGhost = $store->getTicket($adviceId, true, 'ict@kvt.nl');
assertSame(
    'Pending naar awaiting-human na bot ghost',
    TicketStore::AI_ADVICE_AWAITING_HUMAN,
    (int) ($afterGhost['ai_advice_pending'] ?? -1)
);
assertTrue('last_message_is_ai na bot', !empty($afterGhost['last_message_is_ai']));
assertFalse('Nog niet beschikbaar na alleen bot ghost', $store->isAiAdviceAvailable($adviceId));

$unlockHuman = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $adviceId,
    'message' => 'ICT reageert na AI-advies.',
    'sender_email' => 'ict@kvt.nl',
], $adminClient, false);
assertTrue('Unlock ICT-bericht slaagt', !empty($unlockHuman['success']));
$afterUnlock = $store->getTicket($adviceId, true, 'ict@kvt.nl');
assertSame(
    'Pending idle na human na ghost',
    TicketStore::AI_ADVICE_IDLE,
    (int) ($afterUnlock['ai_advice_pending'] ?? -1)
);
assertFalse('last_message_is_ai false na human', !empty($afterUnlock['last_message_is_ai']));
assertTrue('AI advies weer beschikbaar na cycle', $store->isAiAdviceAvailable($adviceId));

$emptyPrompt = handleRequestAiAdviceApiAction($store, [
    'ticket_id' => $adviceId,
    'advice_prompt' => '',
    'viewer_email' => 'ict@kvt.nl',
    'user_is_admin' => true,
    'is_admin_portal' => true,
], $adminClient);
assertTrue('AI advies met lege prompt slaagt', !empty($emptyPrompt['success']));
assertSame(
    'Lege advice_prompt blijft string',
    '',
    (string) ($captured[0]['payload']['advice_prompt'] ?? 'MISSING')
);
assertTrue(
    'isAiAssistantSender herkent grok-bot',
    GrokBot::isAiAssistantSender('grok-bot@kvt.nl')
);
assertFalse(
    'isAiAssistantSender weigert ICT',
    GrokBot::isAiAssistantSender('ict@kvt.nl')
);
resetCaptured();

$GLOBALS['grokBot']['enabled'] = false;
$store->updateTicket($reopenId, 'afgehandeld', 'ict@kvt.nl', 0, null);
$store->updateTicket($reopenId, 'in behandeling', 'ict@kvt.nl', 0, null);
GrokBot::notifyUserRepliedWhileWaiting(
    $store,
    $reopenId,
    'user',
    false,
    TICKET_STATUS_WAITING_ON_USER,
    'Dit mag niet verstuurd worden.'
);
assertSame('Uitgeschakelde bot stuurt niets', [], capturedTypes());

$actionsSource = (string) file_get_contents(__DIR__ . '/../web/content/actions.php');
$apiSource = (string) file_get_contents(__DIR__ . '/../web/api.php');
assertTrue(
    'UI-antwoord geeft de status van vóór de reply door',
    str_contains($actionsSource, "(string) (\$ticket['status'] ?? '')")
);
assertTrue(
    'API-antwoord geeft de status van vóór de reply door',
    str_contains($apiSource, "(string) (\$ticket['status'] ?? '')")
);

echo PHP_EOL;
if ($failed === 0) {
    echo "Alle {$passed} checks geslaagd." . PHP_EOL;
    exit(0);
}

echo "{$failed} check(s) gefaald, {$passed} geslaagd." . PHP_EOL;
exit(1);
