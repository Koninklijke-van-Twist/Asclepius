<?php
/**
 * Tests for the technical-resolution note shown when ICT marks a ticket Afgehandeld.
 */

echo "=== TEST: resolution note modal ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';
$_SESSION = ['lang' => 'nl'];

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/helpers.php';
require __DIR__ . '/../web/TicketStore.php';

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    exit(1);
}

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
    if (!str_contains($haystack, $needle)) {
        echo "      Gezocht : {$needle}" . PHP_EOL;
    }
}

function assertNotContains(string $label, string $needle, string $haystack): void
{
    assertTrue($label, !str_contains($haystack, $needle));
}

function makeResolutionStore(): TicketStore
{
    $store = new TicketStore(
        ':memory:',
        sys_get_temp_dir() . '/asclepius_test_uploads_resolution',
        ['ict@kvt.nl'],
        TICKET_CATEGORIES
    );
    $store->saveCategoryMatrix([
        'ict@kvt.nl' => [
            'Anders' => true,
        ],
    ], [
        'ict@kvt.nl' => true,
    ]);

    return $store;
}

$expectedPlaceholder = 'Noteer hier op een technisch niveau hoe het probleem van de aanvrager opgelost is. Dit kan dan door je collega\'s ter referentie ingezien worden als ze een vergelijkbare ticket aan moeten pakken.';
assertSame(
    'NL-placeholder is exact de gevraagde tekst',
    $expectedPlaceholder,
    __('ticket.resolution_modal_placeholder')
);
assertSame(
    'NL-titel van de modal',
    'Technische oplossing documenteren',
    __('ticket.resolution_modal_heading')
);
assertSame(
    'Standaard vraagt ICT om een technische notitie',
    true,
    userAsksResolutionNoteOnResolve([])
);
assertFalse(
    'Voorkeur kan de vraag uitzetten',
    userAsksResolutionNoteOnResolve(['ask_resolution_note' => false])
);

$store = makeResolutionStore();
$created = $store->createTicket(
    'VPN werkt niet',
    'Anders',
    'user@kvt.nl',
    'Geen verbinding vanaf thuis',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$ticketId = (int) $created['ticket_id'];

$skippedEmpty = addResolutionGhostNoteIfNeeded($store, $ticketId, 'ict@kvt.nl', "  \n", true);
assertSame('Lege notitie wordt overgeslagen', null, $skippedEmpty);

$skippedOtherStatus = addResolutionGhostNoteIfNeeded(
    $store,
    $ticketId,
    'ict@kvt.nl',
    'Dit mag niet worden opgeslagen',
    false
);
assertSame('Geen ghost zonder Afgehandeld-overgang', null, $skippedOtherStatus);

$ghostId = addResolutionGhostNoteIfNeeded(
    $store,
    $ticketId,
    'ict@kvt.nl',
    "VPN-profiel opnieuw geïnstalleerd.\nDaarna [Ctrl] + [Alt] + [Del] en opnieuw ingelogd.",
    true
);
assertTrue('Ghost-bericht krijgt een id', is_int($ghostId) && $ghostId > 0);

$withoutGhosts = $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', false);
$ghostVisibleToUser = false;
foreach (($withoutGhosts['messages'] ?? []) as $message) {
    if ((int) ($message['id'] ?? 0) === $ghostId) {
        $ghostVisibleToUser = true;
        break;
    }
}
assertFalse('Ghost is verborgen zonder include_ghosts', $ghostVisibleToUser);

$withGhosts = $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', true);
$ghostMessage = null;
foreach (($withGhosts['messages'] ?? []) as $message) {
    if ((int) ($message['id'] ?? 0) === $ghostId) {
        $ghostMessage = $message;
        break;
    }
}
assertTrue('Ghost is zichtbaar voor ICT met include_ghosts', is_array($ghostMessage));
assertSame('Ghost staat op naam van de huidige ICT-gebruiker', 'ict@kvt.nl', (string) ($ghostMessage['sender_email'] ?? ''));
assertSame('Ghost heeft admin-rol', 'admin', (string) ($ghostMessage['sender_role'] ?? ''));
assertTrue('Bericht is een ghost', !empty($ghostMessage['is_ghost']));
assertContains(
    'Ghost-tekst is de notitie van de medewerker',
    'VPN-profiel opnieuw geïnstalleerd.',
    (string) ($ghostMessage['message_text'] ?? '')
);

rememberUserDirectoryName('ict@kvt.nl', 'ICT Test');
rememberUserDirectoryName('user@kvt.nl', 'User Test');

$ticket = $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', true);
$ictHtml = renderTicketCardHtml($ticket, $ticket, [
    'currentPage' => 'index.php',
    'canManageTickets' => true,
    'userIsAdmin' => true,
    'isAdminPortal' => true,
    'ictUsers' => ['ict@kvt.nl'],
    'csrfToken' => 'test-csrf',
    'view' => 'overview',
    'includeMessages' => true,
    'viewerEmail' => 'ict@kvt.nl',
    'includeGhostMessages' => true,
    'showGhostToggle' => true,
    'store' => $store,
]);
assertContains('ICT-kaart heeft de resolutie-modal', 'data-role="ticket-resolution-note-modal"', $ictHtml);
assertContains('Modal gebruikt de gevraagde titel', h(__('ticket.resolution_modal_heading')), $ictHtml);
assertContains('Modal heeft de gevraagde placeholder', h(__('ticket.resolution_modal_placeholder')), $ictHtml);
assertContains('Modal heeft dezelfde toetsenbord-picker', 'class="key-picker-toggle"', $ictHtml);
assertContains('Modal kan geannuleerd worden', 'data-role="resolution-note-cancel"', $ictHtml);
assertContains('Opslaan in de modal', 'data-role="resolution-note-save"', $ictHtml);
assertContains('Hidden field voor de ghost-notitie', 'data-role="resolution-note-input"', $ictHtml);

$userHtml = renderTicketCardHtml($ticket, $ticket, [
    'currentPage' => 'index.php',
    'canManageTickets' => false,
    'userIsAdmin' => false,
    'isAdminPortal' => false,
    'ictUsers' => ['ict@kvt.nl'],
    'csrfToken' => 'test-csrf',
    'view' => 'overview',
    'includeMessages' => true,
    'viewerEmail' => 'user@kvt.nl',
]);
assertNotContains('Aanvrager ziet de resolutie-modal niet', 'data-role="ticket-resolution-note-modal"', $userHtml);

$jsSource = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
assertContains('JS onderschept Opslaan bij Afgehandeld', 'shouldPromptResolutionNote', $jsSource);
assertContains(
    'Modal alleen bij overgang naar Afgehandeld',
    "requestedStatus === 'afgehandeld' && currentStatus !== 'afgehandeld'",
    $jsSource
);
assertContains('Annuleren sluit zonder opslaan', 'resolution-note-cancel', $jsSource);
assertContains('Klik op overlay sluit zonder opslaan', "[data-role=\"ticket-resolution-note-modal\"]", $jsSource);

$actionsSource = (string) file_get_contents(__DIR__ . '/../web/content/actions.php');
assertContains(
    'Ghost-notitie wordt geplaatst via de helper',
    'addResolutionGhostNoteIfNeeded',
    $actionsSource
);
$ghostCallPos = strpos($actionsSource, 'addResolutionGhostNoteIfNeeded');
$updatePos = strpos($actionsSource, '$store->updateTicket($ticketId');
assertTrue(
    'Ghost-call staat ná updateTicket zodat een mislukte statuswijziging geen ghost achterlaat',
    $ghostCallPos !== false && $updatePos !== false && $updatePos < $ghostCallPos
);
$nextMessagePos = strpos($actionsSource, '$store->addMessage($ticketId', $ghostCallPos);
assertTrue(
    'Ghost blijft vóór de statusnotitie in het thread',
    $nextMessagePos !== false && $ghostCallPos < $nextMessagePos
);

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
