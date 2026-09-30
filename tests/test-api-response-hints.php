<?php
/**
 * Advisory hints on add_ticket_message and change_ticket_status.
 * Defaults still apply; hints are additive.
 */

echo "=== TEST: API response hints ===" . PHP_EOL . PHP_EOL;

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

register_shutdown_function(static function () use ($authPath, $createdStubAuth): void {
    if ($createdStubAuth && is_file($authPath)) {
        @unlink($authPath);
    }
});

if (!defined('ASCLEPIUS_API_SKIP_ROUTER')) {
    define('ASCLEPIUS_API_SKIP_ROUTER', true);
}

require __DIR__ . '/../web/api.php';

$GLOBALS['grokBot'] = [
    'enabled' => true,
    'sender_email' => 'grok-bot@kvt.nl',
    'default_name' => 'Grok',
    'default_title' => 'Bot',
];
$GLOBALS['ictUsers'] = ['ict@kvt.nl', 'colleague@kvt.nl'];

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

function makeStore(): TicketStore
{
    return new TicketStore(
        ':memory:',
        sys_get_temp_dir() . '/asclepius_test_uploads_api_hints',
        ['ict@kvt.nl', 'colleague@kvt.nl'],
        TICKET_CATEGORIES
    );
}

function createOpenTicket(TicketStore $store, string $title = 'Hint ticket'): int
{
    $result = $store->createTicket(
        $title,
        'Anders',
        'user@kvt.nl',
        'Beschrijving',
        [],
        0,
        [],
        null,
        'ict@kvt.nl'
    );

    return (int) $result['ticket_id'];
}

function hintTexts(array $response): array
{
    $hints = $response['hints'] ?? null;
    if (!is_array($hints)) {
        return [];
    }

    $texts = [];
    foreach ($hints as $hint) {
        $texts[] = (string) ($hint['hint'] ?? '');
    }

    return $texts;
}

$identityHint = 'Geef username, title en email expliciet mee.';
$statusHint = 'Met voorkeur je statuswijziging in dezelfde POST als je bericht plaatsen';

$adminClient = [
    'email' => 'ict@kvt.nl',
    'is_admin' => true,
    'oid' => 'ict',
    'api_key' => 'abc',
];
$grokClient = [
    'email' => 'grok-bot@kvt.nl',
    'is_admin' => true,
    'oid' => 'grok-bot',
    'kind' => 'grok_bot_ephemeral',
    'default_name' => 'Grok',
    'default_title' => 'Bot',
    'api_key' => 'ephemeral',
];

$store = makeStore();
$ticketId = createOpenTicket($store);

$explicit = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Alles ingevuld',
    'sender_email' => 'grok-bot@kvt.nl',
    'sender_name' => 'ICT-Bot',
    'sender_title' => 'Assistent',
], $grokClient, false);
assertTrue('Expliciete identiteit slaagt', !empty($explicit['success']));
assertSame('Expliciete naam blijft', 'ICT-Bot', (string) ($explicit['sender_name'] ?? ''));
assertSame('Expliciete titel blijft', 'Assistent', (string) ($explicit['sender_title'] ?? ''));
assertFalse('Geen hints als alles is meegegeven', array_key_exists('hints', $explicit));

$aliases = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Aliassen',
    'user_email' => 'grok-bot@kvt.nl',
    'display_name' => 'Alias Naam',
    'function_title' => 'Alias Titel',
], $grokClient, false);
assertTrue('Aliassen slagen', !empty($aliases['success']));
assertSame('Alias-naam', 'Alias Naam', (string) ($aliases['sender_name'] ?? ''));
assertFalse('Aliassen tellen als ingevuld', array_key_exists('hints', $aliases));

$defaulted = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Alleen de tekst',
    'ghost' => true,
], $grokClient, false);
assertTrue('Standaardidentiteit blijft geaccepteerd', !empty($defaulted['success']));
assertSame('Standaard-email', 'grok-bot@kvt.nl', (string) ($defaulted['sender_email'] ?? ''));
assertSame('Standaard-naam', 'Grok', (string) ($defaulted['sender_name'] ?? ''));
assertSame('Standaard-titel', 'Bot', (string) ($defaulted['sender_title'] ?? ''));
assertSame('Alleen identiteitshint', [$identityHint], hintTexts($defaulted));
$identityExplanation = (string) ($defaulted['hints'][0]['explanation'] ?? '');
assertTrue('Uitleg noemt sender_email', str_contains($identityExplanation, 'sender_email'));
assertTrue('Uitleg noemt sender_name', str_contains($identityExplanation, 'sender_name'));
assertTrue('Uitleg noemt sender_title', str_contains($identityExplanation, 'sender_title'));

$emailOnlyOmitted = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Naam en titel wel, email niet',
    'sender_name' => 'ICT-Bot',
    'sender_title' => 'Assistent',
], $grokClient, false);
assertTrue('Bericht zonder email slaagt', !empty($emailOnlyOmitted['success']));
assertSame('Email komt uit de client', 'grok-bot@kvt.nl', (string) ($emailOnlyOmitted['sender_email'] ?? ''));
assertSame('Hint ook als alleen email ontbreekt', [$identityHint], hintTexts($emailOnlyOmitted));

$whitespace = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Lege velden',
    'sender_email' => '   ',
    'sender_name' => '',
    'sender_title' => '  ',
], $grokClient, false);
assertSame('Witruimte telt als leeg', [$identityHint], hintTexts($whitespace));

$otherTicket = createOpenTicket($store, 'Ander ticket');
$statusFirst = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $otherTicket,
    'status' => 'in behandeling',
], $adminClient, false);
assertTrue('Status zonder bericht slaagt', !empty($statusFirst['success']));
assertFalse('Geen hint zonder recent bericht', array_key_exists('hints', $statusFirst));

$messageAfterStatus = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $otherTicket,
    'message' => 'Bericht na status',
    'sender_email' => 'ict@kvt.nl',
    'sender_name' => 'ICT',
    'sender_title' => 'Beheer',
], $adminClient, false);
assertTrue('Bericht na status slaagt', !empty($messageAfterStatus['success']));
assertSame('Statushint na losse status', [$statusHint], hintTexts($messageAfterStatus));
$statusExplanation = (string) ($messageAfterStatus['hints'][0]['explanation'] ?? '');
assertTrue('Uitleg noemt add_ticket_message', str_contains($statusExplanation, 'add_ticket_message'));
assertTrue('Uitleg noemt status', str_contains($statusExplanation, 'status'));

$thirdTicket = createOpenTicket($store, 'Bericht dan status');
handleAddTicketMessageApiAction($store, [
    'ticket_id' => $thirdTicket,
    'message' => 'Eerst het bericht',
    'sender_email' => 'ict@kvt.nl',
    'sender_name' => 'ICT',
    'sender_title' => 'Beheer',
], $adminClient, false);
$statusAfterMessage = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $thirdTicket,
    'ticket_status' => 'afwachtende op gebruiker',
], $adminClient, false);
assertTrue('Status na bericht slaagt', !empty($statusAfterMessage['success']));
assertSame('Status na bericht', 'afwachtende op gebruiker', (string) ($statusAfterMessage['status'] ?? ''));
assertSame('Statushint na los bericht', [$statusHint], hintTexts($statusAfterMessage));

$bothTicket = createOpenTicket($store, 'Beide hints');
handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $bothTicket,
    'status' => 'in behandeling',
], $adminClient, false);
$both = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $bothTicket,
    'message' => 'Zonder identiteit, na status',
], $grokClient, false);
assertTrue('Gecombineerde situatie slaagt', !empty($both['success']));
assertSame('Standaardnaam blijft Grok', 'Grok', (string) ($both['sender_name'] ?? ''));
assertSame('Beide hints, identiteit eerst', [$identityHint, $statusHint], hintTexts($both));

$combinedTicket = createOpenTicket($store, 'Zelfde POST');
$combined = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Status in hetzelfde verzoek',
    'status' => 'in behandeling',
    'sender_email' => 'ict@kvt.nl',
    'sender_name' => 'ICT',
    'sender_title' => 'Beheer',
], $adminClient, false);
assertTrue('Gecombineerde POST slaagt', !empty($combined['success']));
assertTrue('Status is mee veranderd', !empty($combined['status_changed']));
assertFalse('Geen hint als status bij het bericht zit', array_key_exists('hints', $combined));

$followUp = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Later los bericht',
    'sender_email' => 'ict@kvt.nl',
    'sender_name' => 'ICT',
    'sender_title' => 'Beheer',
], $adminClient, false);
assertSame('Later los bericht ziet de status', [$statusHint], hintTexts($followUp));

$splitTicket = createOpenTicket($store, 'Ander ticket geen paar');
handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ticketId,
    'message' => 'Bericht op het eerste ticket',
    'sender_email' => 'ict@kvt.nl',
    'sender_name' => 'ICT',
    'sender_title' => 'Beheer',
], $adminClient, false);
$foreignStatus = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $splitTicket,
    'status' => 'in behandeling',
], $adminClient, false);
assertFalse('Ander ticket koppelt niet', array_key_exists('hints', $foreignStatus));

$staleTicket = createOpenTicket($store, 'Te oud');
$store->recordApiTicketEvent($staleTicket, 'message', time() - 120);
$staleStatus = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $staleTicket,
    'status' => 'in behandeling',
], $adminClient, false);
assertFalse('Ouder dan een minuut geen hint', array_key_exists('hints', $staleStatus));

$freshTicket = createOpenTicket($store, 'Net binnen het venster');
$store->recordApiTicketEvent($freshTicket, 'status', time() - 30);
$freshMessage = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $freshTicket,
    'message' => 'Binnen een minuut',
    'sender_email' => 'ict@kvt.nl',
    'sender_name' => 'ICT',
    'sender_title' => 'Beheer',
], $adminClient, false);
assertSame('Dertig seconden telt nog', [$statusHint], hintTexts($freshMessage));

$edgeInside = createOpenTicket($store, 'Rand binnen');
$store->recordApiTicketEvent(9001, 'message', time() - 59);
assertTrue('59 seconden valt binnen het venster', $store->hasRecentApiTicketEvent(9001, 'message', 60));
$store->recordApiTicketEvent(9002, 'message', time() - 61);
assertFalse('61 seconden valt buiten het venster', $store->hasRecentApiTicketEvent(9002, 'message', 60));
assertSame('Randticket bestaat', $edgeInside > 0, true);

$sameStatusTicket = createOpenTicket($store, 'Ongewijzigde status');
handleAddTicketMessageApiAction($store, [
    'ticket_id' => $sameStatusTicket,
    'message' => 'Bericht',
    'sender_email' => 'ict@kvt.nl',
    'sender_name' => 'ICT',
    'sender_title' => 'Beheer',
], $adminClient, false);
$unchanged = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $sameStatusTicket,
    'status' => 'ingediend',
], $adminClient, false);
assertTrue('Ongewijzigde status slaagt', !empty($unchanged['unchanged']));
assertFalse('Ongewijzigde status heeft geen hint', array_key_exists('hints', $unchanged));

$rejected = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $sameStatusTicket,
    'message' => '',
], $adminClient, false);
assertFalse('Leeg bericht wordt geweigerd', !empty($rejected['success']));
assertFalse('Geweigerd verzoek heeft geen hints', array_key_exists('hints', $rejected));

echo PHP_EOL;
if ($failed === 0) {
    echo "Alle tests geslaagd ({$passed})." . PHP_EOL;
    exit(0);
}

echo "Mislukt: {$failed}, geslaagd: {$passed}." . PHP_EOL;
exit(1);
