<?php
/**
 * Download filename, participant access for non-admins, and message reactions.
 */

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/api.php';
$_SERVER['HTTP_HOST'] = 'localhost';
@ini_set('sendmail_path', '/bin/true');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION['csrf_token'] = 'test-csrf';
$_SESSION['user'] = ['email' => 'user@kvt.nl'];

echo "=== TEST: download name, participants, reactions ===" . PHP_EOL . PHP_EOL;

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

assertSame(
    'Downloadnaam is ticket en aanvrager',
    '42_jan.pdf',
    buildAttachmentDownloadFilename([
        'ticket_id' => 42,
        'original_name' => 'offerte.PDF',
        'stored_name' => 'ticket_abc123.pdf',
    ], 'jan@kvt.nl')
);
assertSame(
    'Spaties en quotes verdwijnen uit de naam',
    '7_jan_dijk.txt',
    buildAttachmentDownloadFilename([
        'ticket_id' => 7,
        'original_name' => 'a"b/../c.txt',
    ], 'jan dijk@kvt.nl')
);
assertSame(
    'Extensie valt terug op stored_name',
    '3_piet.png',
    buildAttachmentDownloadFilename([
        'ticket_id' => 3,
        'original_name' => 'foto',
        'stored_name' => 'ticket_hash.PNG',
    ], 'piet@kvt.nl')
);
assertSame(
    'Lege aanvrager wordt aanvrager',
    '9_aanvrager',
    buildAttachmentDownloadFilename([
        'ticket_id' => 9,
        'original_name' => 'zonder-extensie',
    ], '@@@')
);
assertFalse(
    'Geen pad of quote in de downloadnaam',
    str_contains(buildAttachmentDownloadFilename([
        'ticket_id' => 1,
        'original_name' => 'x.pdf',
    ], "../\"jan@kvt.nl"), '"')
    || str_contains(buildAttachmentDownloadFilename([
        'ticket_id' => 1,
        'original_name' => 'x.pdf',
    ], "../\"jan@kvt.nl"), '/')
    || str_contains(buildAttachmentDownloadFilename([
        'ticket_id' => 1,
        'original_name' => 'x.pdf',
    ], "../\"jan@kvt.nl"), '\\')
);

$inlineHtml = renderMessageInlineAttachmentHtml([
    'id' => 15,
    'ticket_id' => 42,
    'original_name' => 'offerte.pdf',
    'stored_name' => 'ticket_hash.pdf',
    'requester_email' => 'jan@kvt.nl',
    'mime_type' => 'application/pdf',
]);
assertContains('Downloadlink gebruikt de download-route', '?download=15', $inlineHtml);
assertContains('Downloadlink noemt de leesbare bestandsnaam', '42_jan.pdf', $inlineHtml);
assertFalse('Downloadlink wijst niet naar de hash-bestandsnaam', str_contains($inlineHtml, 'ticket_hash.pdf'));

$GLOBALS['ictUsers'] = ['ict@kvt.nl'];
$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_uploads_omer',
    ['ict@kvt.nl'],
    TICKET_CATEGORIES
);
$created = $store->createTicket(
    'Deelnemers',
    'Anders',
    'user@kvt.nl',
    'Beschrijving',
    [],
    0,
    [],
    null,
    'ict@kvt.nl'
);
$ticketId = (int) $created['ticket_id'];

$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['SERVER_ADDR'] = '10.0.0.1';
$participantClient = ['email' => 'user@kvt.nl', 'is_admin' => false];
$added = handleManageTicketParticipantsApiAction($store, [
    'operation' => 'add',
    'ticket_id' => $ticketId,
    'participant_emails' => 'collega@kvt.nl',
    'viewer_email' => 'user@kvt.nl',
    'user_is_admin' => false,
], $participantClient);
assertTrue('Deelnemer mag een gebruiker toevoegen', !empty($added['success']));
assertContains('Nieuwe gebruiker staat op het ticket', 'collega@kvt.nl', implode(',', $added['participant_emails'] ?? []));

$stranger = handleManageTicketParticipantsApiAction($store, [
    'operation' => 'add',
    'ticket_id' => $ticketId,
    'participant_emails' => 'extra@kvt.nl',
    'viewer_email' => 'stranger@kvt.nl',
    'user_is_admin' => true,
], ['email' => 'stranger@kvt.nl', 'is_admin' => false]);
assertFalse('Buitenstaander met user_is_admin mag niet beheren', !empty($stranger['success']));

$removeColleague = handleManageTicketParticipantsApiAction($store, [
    'operation' => 'remove',
    'ticket_id' => $ticketId,
    'participant_email' => 'collega@kvt.nl',
    'viewer_email' => 'user@kvt.nl',
], $participantClient);
assertTrue('Weghalen mag zolang er iemand blijft', !empty($removeColleague['success']));

$removeLast = handleManageTicketParticipantsApiAction($store, [
    'operation' => 'remove',
    'ticket_id' => $ticketId,
    'participant_email' => 'user@kvt.nl',
    'viewer_email' => 'user@kvt.nl',
], $participantClient);
assertFalse('Laatste gebruiker blijft', !empty($removeLast['success']));
assertContains(
    'Melding dat er minimaal één gebruiker blijft',
    'minimaal 1',
    (string) ($removeLast['error'] ?? '')
);
$stillThere = $store->getTicketParticipants($ticketId);
assertSame('Er blijft één deelnemer', 1, count($stillThere));

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';

$messageId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Reactie hierop', [], false);
$first = $store->setMessageReaction($ticketId, $messageId, 'user@kvt.nl', 1);
assertSame('Eerste +1 telt als plus', 1, (int) ($first['plus'] ?? 0));
assertSame('Eigen keuze is +1', 1, (int) ($first['mine'] ?? 0));
$store->setMessageReaction($ticketId, $messageId, 'collega@kvt.nl', 1);
$store->setMessageReaction($ticketId, $messageId, 'user@kvt.nl', 1);
$afterRepeat = $store->getMessageReactionSummary($messageId, 'user@kvt.nl');
assertSame('Opnieuw +1 blijft één rij per gebruiker', 2, (int) ($afterRepeat['plus'] ?? 0));
$switched = $store->setMessageReaction($ticketId, $messageId, 'user@kvt.nl', -1);
assertSame('Wisselen haalt de +1 weg bij deze gebruiker', 1, (int) ($switched['plus'] ?? 0));
assertSame('Wisselen zet −1', 1, (int) ($switched['minus'] ?? 0));
assertSame('Eigen keuze is −1', -1, (int) ($switched['mine'] ?? 0));
$cleared = $store->setMessageReaction($ticketId, $messageId, 'user@kvt.nl', 0);
assertSame('Wissen haalt de eigen reactie weg', 0, (int) ($cleared['mine'] ?? 0));
assertSame('Andermans +1 blijft', 1, (int) ($cleared['plus'] ?? 0));

$detail = $store->getTicket($ticketId, true, 'user@kvt.nl', 'default', true);
$loaded = null;
foreach (($detail['messages'] ?? []) as $message) {
    if ((int) ($message['id'] ?? 0) === $messageId) {
        $loaded = $message;
        break;
    }
}
assertTrue('Berichtpayload bevat reacties', is_array($loaded['reactions'] ?? null));
assertSame('Geladen plus-count', 1, (int) ($loaded['reactions']['plus'] ?? 0));

$_SESSION['user']['email'] = 'user@kvt.nl';
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$_SERVER['SERVER_ADDR'] = '10.0.0.1';
$apiPlus = handleSetMessageReactionApiAction($store, [
    'ticket_id' => $ticketId,
    'message_id' => $messageId,
    'value' => 1,
    'csrf_token' => 'test-csrf',
    'viewer_email' => 'stranger@kvt.nl',
], ['email' => 'stranger@kvt.nl', 'is_admin' => false]);
assertTrue('API zet +1 voor de sessiegebruiker', !empty($apiPlus['success']));
assertSame('API negeert een ander viewer_email', 1, (int) ($apiPlus['value'] ?? 0));
$apiClear = handleSetMessageReactionApiAction($store, [
    'ticket_id' => $ticketId,
    'message_id' => $messageId,
    'value' => 0,
    'csrf_token' => 'test-csrf',
], null);
assertSame('API wissen zet value op 0', 0, (int) ($apiClear['value'] ?? -1));
$badCsrf = handleSetMessageReactionApiAction($store, [
    'ticket_id' => $ticketId,
    'message_id' => $messageId,
    'value' => 1,
    'csrf_token' => 'nee',
], null);
assertSame('Zonder CSRF geen reactie', 'csrf', (string) ($badCsrf['error_code'] ?? ''));

echo PHP_EOL;
echo "Geslaagd: {$passed}, gefaald: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
