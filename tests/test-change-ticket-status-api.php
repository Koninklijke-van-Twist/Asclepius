<?php
/**
 * Tests for ICT ticket field mutations via api.php:
 * change_ticket_status, change_ticket_assignee, change_ticket_priority, change_ticket_due_date,
 * and add_ticket_message with optional status/assignee.
 */

echo "=== TEST: API ticket field mutations ===" . PHP_EOL . PHP_EOL;

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

function assertContains(string $label, string $needle, string $haystack): void
{
    assertTrue($label, str_contains($haystack, $needle));
}

function makeMutationStore(): TicketStore
{
    $store = new TicketStore(
        ':memory:',
        sys_get_temp_dir() . '/asclepius_test_uploads_status_api',
        ['ict@kvt.nl', 'colleague@kvt.nl'],
        TICKET_CATEGORIES
    );
    $store->saveCategoryMatrix([
        'ict@kvt.nl' => [
            'Anders' => true,
            'hardware bestellen' => true,
        ],
        'colleague@kvt.nl' => [
            'Anders' => true,
            'hardware bestellen' => true,
        ],
    ], [
        'ict@kvt.nl' => true,
        'colleague@kvt.nl' => true,
    ]);

    return $store;
}

function createOpenTicket(TicketStore $store, string $title = 'API status test'): int
{
    $result = $store->createTicket(
        $title,
        'Anders',
        'user@kvt.nl',
        'Beschrijving voor API-test',
        [],
        0,
        [],
        null,
        'ict@kvt.nl'
    );

    return (int) $result['ticket_id'];
}

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

$apiSource = (string) file_get_contents(__DIR__ . '/../web/api.php');
assertTrue(
    'Router kent change_ticket_status',
    str_contains($apiSource, "action === 'change_ticket_status'")
);
assertTrue(
    'Onbekende action blijft unknown_action',
    str_contains($apiSource, "'error' => 'unknown_action'")
);

assertSame(
    'in behandeling is een vaste status',
    'in behandeling',
    matchBuiltInTicketStatus('In Behandeling')
);
assertSame(
    'resolveTicketStatusValue canonicaliseert in behandeling',
    'in behandeling',
    resolveTicketStatusValue('in behandeling', null, 'ict@kvt.nl')
);

$store = makeMutationStore();
$ticketId = createOpenTicket($store, 'Ticket 776 stand-in');
$created = $store->getTicket($ticketId, true, 'ict@kvt.nl');
assertSame('Nieuw ticket start als ingediend', 'ingediend', (string) ($created['status'] ?? ''));

$statusResponse = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'in behandeling',
], $adminClient, false);

assertTrue('Statuswijziging slaagt', !empty($statusResponse['success']));
assertSame('Status is in behandeling', 'in behandeling', (string) ($statusResponse['status'] ?? ''));
assertFalse('Status was gewijzigd', !empty($statusResponse['unchanged']));
assertTrue('Systeemnotitie-id aanwezig', (int) ($statusResponse['message_id'] ?? 0) > 0);
assertTrue('Statusresponse bevat message', trim((string) ($statusResponse['message'] ?? '')) !== '');
assertTrue('Statusresponse bevat status_color', trim((string) ($statusResponse['status_color'] ?? '')) !== '');
assertTrue('Statusresponse bevat message_html', trim((string) ($statusResponse['message_html'] ?? '')) !== '');

$updated = $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', true);
assertSame('Opgeslagen status is in behandeling', 'in behandeling', (string) ($updated['status'] ?? ''));
assertSame('resolved_at blijft leeg tot afgehandeld', null, $updated['resolved_at'] ?? null);

$noteFound = false;
foreach (($updated['messages'] ?? []) as $message) {
    if (str_contains((string) ($message['message_text'] ?? ''), 'in behandeling')) {
        $noteFound = true;
        break;
    }
}
assertTrue('Systeemnotitie noemt de nieuwe status', $noteFound);

$idempotent = handleChangeTicketStatusApiAction($store, [
    'id' => $ticketId,
    'status' => 'in behandeling',
], $webhookClient, false);
assertTrue('Idempotente statuswijziging slaagt', !empty($idempotent['success']));
assertTrue('Idempotente statuswijziging is unchanged', !empty($idempotent['unchanged']));

$invalid = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => '',
], $adminClient, false);
assertFalse('Lege status wordt geweigerd', !empty($invalid['success']));
assertSame('Lege status error_code', 'invalid_status', (string) ($invalid['error_code'] ?? ''));

$missingId = handleChangeTicketStatusApiAction($store, [
    'status' => 'in behandeling',
], $adminClient, false);
assertSame('ticket_id verplicht', 'ticket_id_required', (string) ($missingId['error_code'] ?? ''));

$missingTicket = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => 999999,
    'status' => 'in behandeling',
], $adminClient, false);
assertSame('Onbekend ticket', 'ticket_not_found', (string) ($missingTicket['error_code'] ?? ''));

$custom = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'Wacht op leverancier',
], $adminClient, false);
assertTrue('Custom status slaagt', !empty($custom['success']));
assertSame('Custom status opgeslagen', 'Wacht op leverancier', (string) ($custom['status'] ?? ''));

$resolved = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'afgehandeld',
], $adminClient, false);
assertTrue('Afgehandeld slaagt', !empty($resolved['success']));
$closed = $store->getTicket($ticketId, true, 'ict@kvt.nl');
assertSame('Status afgehandeld', 'afgehandeld', (string) ($closed['status'] ?? ''));
assertTrue('resolved_at is gezet', trim((string) ($closed['resolved_at'] ?? '')) !== '');

$reopened = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'in behandeling',
], $adminClient, false);
assertTrue('Heropenen via status slaagt', !empty($reopened['success']));
$openAgain = $store->getTicket($ticketId, true, 'ict@kvt.nl');
assertSame('Na heropenen weer in behandeling', 'in behandeling', (string) ($openAgain['status'] ?? ''));
assertTrue('resolved_at gewist na heropenen', ($openAgain['resolved_at'] ?? null) === null || $openAgain['resolved_at'] === '');

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['SERVER_ADDR'] = '10.0.0.1';
$forbidden = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'ingediend',
], ['email' => 'user@kvt.nl', 'is_admin' => false], false);
assertSame('Zonder ICT-rechten forbidden', 'forbidden', (string) ($forbidden['error_code'] ?? ''));

$spoofAdmin = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'ingediend',
    'user_is_admin' => true,
], ['email' => 'user@kvt.nl', 'is_admin' => false], false);
assertSame('Payload user_is_admin wordt genegeerd', 'forbidden', (string) ($spoofAdmin['error_code'] ?? ''));

$serviceKeyOk = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'ingediend',
    'viewer_email' => 'ict@kvt.nl',
], null, true);
assertTrue('Service-key mag status wijzigen', !empty($serviceKeyOk['success']));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';

$assignee = handleChangeTicketAssigneeApiAction($store, [
    'ticket_id' => $ticketId,
    'assigned_email' => 'colleague@kvt.nl',
], $adminClient, false);
assertTrue('Toewijzing slaagt', !empty($assignee['success']));
assertSame('Nieuwe assignee', 'colleague@kvt.nl', (string) ($assignee['assigned_email'] ?? ''));
$afterAssign = $store->getTicket($ticketId, true, 'ict@kvt.nl');
assertSame('Assignee opgeslagen', 'colleague@kvt.nl', strtolower((string) ($afterAssign['assigned_email'] ?? '')));

$selfTicket = $store->createTicket(
    'Zelftoewijzing',
    'Anders',
    'colleague@kvt.nl',
    'Aanvrager is ICT',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$selfTicketId = (int) $selfTicket['ticket_id'];
$selfRequester = handleChangeTicketAssigneeApiAction($store, [
    'ticket_id' => $selfTicketId,
    'assigned_email' => 'colleague@kvt.nl',
], $adminClient, false);
assertSame('Aanvrager mag niet worden toegewezen', 'self_assignment_not_allowed', (string) ($selfRequester['error_code'] ?? ''));

$unknownAssignee = handleChangeTicketAssigneeApiAction($store, [
    'ticket_id' => $ticketId,
    'assigned_email' => 'user@kvt.nl',
], $adminClient, false);
assertSame('Niet-ICT assignee ongeldig', 'invalid_employee', (string) ($unknownAssignee['error_code'] ?? ''));

$closedForUnassign = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $ticketId,
    'status' => 'afgehandeld',
], $adminClient, false);
assertTrue('Afsluiten voor unassign slaagt', !empty($closedForUnassign['success']));
$unassign = handleChangeTicketAssigneeApiAction($store, [
    'ticket_id' => $ticketId,
    'assigned_email' => '',
], $adminClient, false);
assertTrue('Unassign op afgehandeld ticket slaagt', !empty($unassign['success']));
assertSame('Unassign leegt assigned_email', '', (string) ($unassign['assigned_email'] ?? ''));

$priorityTicket = createOpenTicket($store, 'Priority ticket');

$priority = handleChangeTicketPriorityApiAction($store, [
    'ticket_id' => $priorityTicket,
    'priority' => 2,
], $adminClient, false);
assertTrue('Prioriteit slaagt', !empty($priority['success']));
assertSame('Prioriteit 2 opgeslagen', 2, (int) ($priority['priority'] ?? -1));

$badPriority = handleChangeTicketPriorityApiAction($store, [
    'ticket_id' => $priorityTicket,
    'priority' => 9,
], $adminClient, false);
assertSame('Ongeldige prioriteit', 'invalid_priority', (string) ($badPriority['error_code'] ?? ''));

$invalidPriorities = [
    ['invalid', 'Prioriteit-tekst wordt geweigerd'],
    ['1x', 'Prioriteit met suffix wordt geweigerd'],
    [1.9, 'Prioriteit-float wordt geweigerd'],
    [true, 'Prioriteit-boolean wordt geweigerd'],
    ['01', 'Prioriteit met leading zero wordt geweigerd'],
];
foreach ($invalidPriorities as [$invalidPriority, $priorityLabel]) {
    $rejectedPriority = handleChangeTicketPriorityApiAction($store, [
        'ticket_id' => $priorityTicket,
        'priority' => $invalidPriority,
    ], $adminClient, false);
    assertSame($priorityLabel, 'invalid_priority', (string) ($rejectedPriority['error_code'] ?? ''));
}

$stringPriority = handleChangeTicketPriorityApiAction($store, [
    'ticket_id' => $priorityTicket,
    'priority' => '1',
], $adminClient, false);
assertTrue('Prioriteit als cijferstring slaagt', !empty($stringPriority['success']));
assertSame('Prioriteit 1 via string opgeslagen', 1, (int) ($stringPriority['priority'] ?? -1));

$due = handleChangeTicketDueDateApiAction($store, [
    'ticket_id' => $priorityTicket,
    'due_date' => date('Y-m-d', strtotime('+1 day')),
], $adminClient, false);
assertTrue('Due-date slaagt', !empty($due['success']));
assertSame('Due-date opgeslagen', date('Y-m-d', strtotime('+1 day')), (string) ($due['due_date'] ?? ''));

$priorityBlocked = handleChangeTicketPriorityApiAction($store, [
    'ticket_id' => $priorityTicket,
    'priority' => 0,
], $adminClient, false);
assertSame('Prioriteit volgt due-date', 'priority_follows_due_date', (string) ($priorityBlocked['error_code'] ?? ''));

$badDue = handleChangeTicketDueDateApiAction($store, [
    'ticket_id' => $priorityTicket,
    'due_date' => 'niet-een-datum',
], $adminClient, false);
assertSame('Ongeldige due-date', 'invalid_due_date', (string) ($badDue['error_code'] ?? ''));

$impossibleDue = handleChangeTicketDueDateApiAction($store, [
    'ticket_id' => $priorityTicket,
    'due_date' => '2026-02-31',
], $adminClient, false);
assertSame('Onmogelijke due-date', 'invalid_due_date', (string) ($impossibleDue['error_code'] ?? ''));

$suffixDue = handleChangeTicketDueDateApiAction($store, [
    'ticket_id' => $priorityTicket,
    'due_date' => '2026-09-15-invalid',
], $adminClient, false);
assertSame('Due-date met suffix', 'invalid_due_date', (string) ($suffixDue['error_code'] ?? ''));

echo PHP_EOL . '--- add_ticket_message + optionele status/assignee ---' . PHP_EOL;

$combinedTicket = createOpenTicket($store, 'Combined reply ticket');
$beforeCombined = $store->getTicket($combinedTicket, true, 'ict@kvt.nl', 'default', true);
$beforeCount = count($beforeCombined['messages'] ?? []);

$messageOnly = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Alleen een bericht',
    'sender_email' => 'ict@kvt.nl',
], $adminClient, false);
assertTrue('Bericht zonder velden slaagt', !empty($messageOnly['success']));
assertTrue('Bericht-id aanwezig', (int) ($messageOnly['message_id'] ?? 0) > 0);
assertFalse('Geen status_changed zonder statusveld', array_key_exists('status_changed', $messageOnly));
assertFalse('Geen assignee_changed zonder assigneeveld', array_key_exists('assignee_changed', $messageOnly));
$afterMessageOnly = $store->getTicket($combinedTicket, true, 'ict@kvt.nl', 'default', true);
assertSame('Status ongewijzigd bij alleen bericht', 'ingediend', (string) ($afterMessageOnly['status'] ?? ''));
assertSame('Een bericht toegevoegd', $beforeCount + 1, count($afterMessageOnly['messages'] ?? []));

$combined = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Printer opnieuw ingesteld; testafdruk is gelukt.',
    'status' => 'in behandeling',
    'assigned_email' => 'colleague@kvt.nl',
    'sender_email' => 'grok-bot@kvt.nl',
    'sender_name' => 'ICT-Bot',
    'sender_title' => 'Assistent',
], $adminClient, false);
assertTrue('Gecombineerde call slaagt', !empty($combined['success']));
assertSame('Gecombineerde status', 'in behandeling', (string) ($combined['status'] ?? ''));
assertTrue('Status is gewijzigd', !empty($combined['status_changed']));
assertSame('Gecombineerde assignee', 'colleague@kvt.nl', (string) ($combined['assigned_email'] ?? ''));
assertTrue('Assignee is gewijzigd', !empty($combined['assignee_changed']));
assertFalse('Gecombineerde call is niet unchanged', !empty($combined['unchanged']));
assertSame('Bot-naam behouden', 'ICT-Bot', (string) ($combined['sender_name'] ?? ''));

$afterCombined = $store->getTicket($combinedTicket, true, 'ict@kvt.nl', 'default', true);
assertSame('Opgeslagen status na combined', 'in behandeling', (string) ($afterCombined['status'] ?? ''));
assertSame('Opgeslagen assignee na combined', 'colleague@kvt.nl', strtolower((string) ($afterCombined['assigned_email'] ?? '')));
$combinedText = (string) (($combined['message']['message_text'] ?? ''));
assertTrue('Bericht bevat de bottekst', str_contains($combinedText, 'Printer opnieuw ingesteld'));
assertTrue('Bericht bevat statusnotitie', str_contains($combinedText, 'in behandeling'));

$ghostTicket = createOpenTicket($store, 'Ghost combined ticket');
$ghostBefore = $store->getTicket($ghostTicket, true, 'ict@kvt.nl', 'default', true);
$ghostBeforeCount = count($ghostBefore['messages'] ?? []);
$ghostCombined = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $ghostTicket,
    'message' => 'Interne botnotitie',
    'ghost' => true,
    'ticket_status' => 'afwachtende op gebruiker',
    'assignee' => 'colleague@kvt.nl',
    'sender_email' => 'grok-bot@kvt.nl',
    'sender_name' => 'ICT-Bot',
    'sender_title' => 'Assistent',
], null, true);
assertTrue('Ghost combined slaagt (service-key)', !empty($ghostCombined['success']));
assertTrue('Ghost blijft ghost', !empty($ghostCombined['is_ghost']));
assertTrue('Ghost+status heeft status_message_id', (int) ($ghostCombined['status_message_id'] ?? 0) > 0);
assertTrue(
    'Ghost-bericht en statusnotitie zijn verschillend',
    (int) ($ghostCombined['message_id'] ?? 0) !== (int) ($ghostCombined['status_message_id'] ?? 0)
);
$ghostAfter = $store->getTicket($ghostTicket, true, 'ict@kvt.nl', 'default', true);
assertSame('Ghost combined status opgeslagen', 'afwachtende op gebruiker', (string) ($ghostAfter['status'] ?? ''));
assertSame('Ghost combined twee berichten extra', $ghostBeforeCount + 2, count($ghostAfter['messages'] ?? []));
$ghostUserMessage = null;
$ghostStatusNote = null;
foreach (($ghostAfter['messages'] ?? []) as $row) {
    if ((int) ($row['id'] ?? 0) === (int) ($ghostCombined['message_id'] ?? 0)) {
        $ghostUserMessage = $row;
    }
    if ((int) ($row['id'] ?? 0) === (int) ($ghostCombined['status_message_id'] ?? 0)) {
        $ghostStatusNote = $row;
    }
}
assertTrue('Ghost-bericht gevonden', is_array($ghostUserMessage));
assertTrue('Statusnotitie gevonden', is_array($ghostStatusNote));
assertTrue('Bottekst is ghost', !empty($ghostUserMessage['is_ghost']));
assertFalse('Statusnotitie is niet ghost', !empty($ghostStatusNote['is_ghost']));
assertSame('Ghost-bericht is de bottekst', 'Interne botnotitie', trim((string) ($ghostUserMessage['message_text'] ?? '')));

$invalidDoesNotPost = createOpenTicket($store, 'Invalid status should not post');
$invalidBefore = $store->getTicket($invalidDoesNotPost, true, 'ict@kvt.nl', 'default', true);
$invalidCount = count($invalidBefore['messages'] ?? []);
$invalidCombined = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $invalidDoesNotPost,
    'message' => 'Dit mag niet worden opgeslagen',
    'status' => '',
    'sender_email' => 'ict@kvt.nl',
], $adminClient, false);
assertFalse('Ongeldige status weigert combined call', !empty($invalidCombined['success']));
assertSame('Ongeldige status error_code', 'invalid_status', (string) ($invalidCombined['error_code'] ?? ''));
$invalidAfter = $store->getTicket($invalidDoesNotPost, true, 'ict@kvt.nl', 'default', true);
assertSame('Geen bericht bij ongeldige status', $invalidCount, count($invalidAfter['messages'] ?? []));
assertSame('Status ongewijzigd bij ongeldige status', 'ingediend', (string) ($invalidAfter['status'] ?? ''));

$badAssigneeCombined = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Mag niet',
    'assigned_email' => 'user@kvt.nl',
    'sender_email' => 'ict@kvt.nl',
], $adminClient, false);
assertSame('Ongeldige assignee in combined', 'invalid_employee', (string) ($badAssigneeCombined['error_code'] ?? ''));

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['SERVER_ADDR'] = '10.0.0.1';
$forbiddenCombined = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Mag niet',
    'status' => 'in behandeling',
    'sender_email' => 'user@kvt.nl',
], ['email' => 'user@kvt.nl', 'is_admin' => false], false);
assertSame('Combined zonder ICT-rechten forbidden', 'forbidden', (string) ($forbiddenCombined['error_code'] ?? ''));

$spoofCombined = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Mag niet',
    'status' => 'afgehandeld',
    'user_is_admin' => true,
    'sender_email' => 'user@kvt.nl',
], ['email' => 'user@kvt.nl', 'is_admin' => false], false);
assertSame('Combined user_is_admin genegeerd', 'forbidden', (string) ($spoofCombined['error_code'] ?? ''));
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';

$unchangedFields = handleAddTicketMessageApiAction($store, [
    'ticket_id' => $combinedTicket,
    'message' => 'Status blijft hetzelfde',
    'status' => 'in behandeling',
    'assigned_email' => 'colleague@kvt.nl',
    'sender_email' => 'ict@kvt.nl',
], $adminClient, false);
assertTrue('Bericht met dezelfde velden slaagt', !empty($unchangedFields['success']));
assertTrue('Velden unchanged als ze gelijk blijven', !empty($unchangedFields['unchanged']));
assertFalse('status_changed false bij dezelfde status', !empty($unchangedFields['status_changed']));
assertTrue('Bericht-id bij unchanged velden', (int) ($unchangedFields['message_id'] ?? 0) > 0);

$dedicatedStatusStillWorks = handleChangeTicketStatusApiAction($store, [
    'ticket_id' => $combinedTicket,
    'ticket_status' => 'afgehandeld',
], $adminClient, false);
assertTrue('Losse change_ticket_status blijft werken', !empty($dedicatedStatusStillWorks['success']));
assertSame('Losse status afgehandeld', 'afgehandeld', (string) ($dedicatedStatusStillWorks['status'] ?? ''));

echo PHP_EOL;
if ($failed === 0) {
    echo "Alle tests geslaagd ({$passed})." . PHP_EOL;
    exit(0);
}

echo "{$failed} test(s) gefaald, {$passed} geslaagd." . PHP_EOL;
exit(1);
