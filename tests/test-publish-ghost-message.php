<?php
/**
 * publish_ghost_message bewaart een meegestuurde tekst en laat de oude draft staan
 * als message/message_text ontbreekt.
 */

echo "=== TEST: publish ghost message text ===" . PHP_EOL . PHP_EOL;

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

function makeGhostStore(): TicketStore
{
    $store = new TicketStore(
        ':memory:',
        sys_get_temp_dir() . '/asclepius_test_uploads_publish_ghost',
        ['ict@kvt.nl', 'colleague@kvt.nl'],
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

$GLOBALS['ictUsers'] = ['ict@kvt.nl'];
$adminClient = [
    'email' => 'ict@kvt.nl',
    'is_admin' => true,
    'oid' => 'ict',
    'api_key' => 'abc',
];

$store = makeGhostStore();
$created = $store->createTicket(
    'Ghost publiceren',
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

$unchangedId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Oude concepttekst', [], true);
$kept = handlePublishGhostMessageApiAction($store, [
    'message_id' => $unchangedId,
], $adminClient, false);
assertTrue('Publiceren zonder tekst slaagt', !empty($kept['success']));
assertSame('Bestaande draft blijft staan', 'Oude concepttekst', (string) ($kept['message_text'] ?? ''));
assertFalse('Bericht is niet meer ghost', !empty($kept['is_ghost']));
assertTrue('Niet-lege tekst wordt gemeld', !empty($kept['notified']));
$storedKept = $store->getTicketMessage($unchangedId);
assertSame('Opgeslagen tekst ongewijzigd', 'Oude concepttekst', (string) ($storedKept['message_text'] ?? ''));
assertFalse('Opgeslagen vlag is geen ghost', !empty($storedKept['is_ghost']));

$editedId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Concept dat niet live mag', [], true);
$store->upsertTextTranslation('ticket_message', $editedId, $ticketId, 'en', 'nl', 'hash-oud', 'Old translation');
$edited = handlePublishGhostMessageApiAction($store, [
    'message_id' => $editedId,
    'message_text' => "Dit is de gepubliceerde tekst.\r\nTweede regel",
], $adminClient, false);
assertTrue('Publiceren met tekst slaagt', !empty($edited['success']));
assertSame(
    'Aangepaste tekst komt terug',
    "Dit is de gepubliceerde tekst.\nTweede regel",
    (string) ($edited['message_text'] ?? '')
);
assertContains('HTML bevat de nieuwe tekst', 'Dit is de gepubliceerde tekst.', (string) ($edited['message_html'] ?? ''));
assertFalse('HTML bevat de oude draft niet', str_contains((string) ($edited['message_html'] ?? ''), 'niet live mag'));
$storedEdited = $store->getTicketMessage($editedId);
assertSame(
    'Aangepaste tekst is opgeslagen',
    "Dit is de gepubliceerde tekst.\nTweede regel",
    (string) ($storedEdited['message_text'] ?? '')
);
assertTrue(
    'Oude vertaling is gewist',
    $store->getTextTranslation('ticket_message', $editedId, 'en', 'hash-oud') === null
);

$sameId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Zelfde tekst', [], true);
$store->upsertTextTranslation('ticket_message', $sameId, $ticketId, 'en', 'nl', 'hash-zelfde', 'Same translation');
$same = handlePublishGhostMessageApiAction($store, [
    'message_id' => $sameId,
    'message' => 'Zelfde tekst',
], $adminClient, false);
assertSame('Alias message publiceert dezelfde tekst', 'Zelfde tekst', (string) ($same['message_text'] ?? ''));
assertTrue(
    'Ongewijzigde tekst houdt de vertaling',
    is_array($store->getTextTranslation('ticket_message', $sameId, 'en', 'hash-zelfde'))
);

$priorityId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Draft', [], true);
$priority = handlePublishGhostMessageApiAction($store, [
    'message_id' => $priorityId,
    'message' => 'van message',
    'message_text' => 'van message_text',
], $adminClient, false);
assertSame('message_text wint van message', 'van message_text', (string) ($priority['message_text'] ?? ''));

$emptyId = $store->addMessage($ticketId, 'ict@kvt.nl', 'admin', 'Wordt leeg', [], true);
$empty = handlePublishGhostMessageApiAction($store, [
    'message_id' => $emptyId,
    'message_text' => '',
], $adminClient, false);
assertTrue('Lege tekst mag gepubliceerd worden', !empty($empty['success']));
assertSame('Lege tekst is opgeslagen', '', (string) ($empty['message_text'] ?? 'missing'));
assertFalse('Lege tekst zonder bijlage mailt niet', !empty($empty['notified']));
assertSame('Lege HTML', '', (string) ($empty['message_html'] ?? 'missing'));

$again = handlePublishGhostMessageApiAction($store, [
    'message_id' => $emptyId,
    'message_text' => 'Mag de tekst niet overschrijven',
], $adminClient, false);
assertSame('Al gepubliceerd blijft not_ghost', 'not_ghost', (string) ($again['error_code'] ?? ''));
$storedEmpty = $store->getTicketMessage($emptyId);
assertSame('Mislukte herpublicatie wijzigt de tekst niet', '', (string) ($storedEmpty['message_text'] ?? 'missing'));

$modal = (string) file_get_contents(__DIR__ . '/../web/content/views/view_publish_ghost_modal.php');
$textareaPos = strpos($modal, 'data-role="publish-ghost-text"');
$confirmPos = strpos($modal, 'role-confirm-copy');
assertTrue(
    'Textarea staat boven de bevestigingstekst',
    $textareaPos !== false && $confirmPos !== false && $textareaPos < $confirmPos
);

$script = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
assertTrue(
    'Bevestigen stuurt de tekst uit de textarea',
    str_contains($script, 'message_text: messageText')
    && str_contains($script, 'publishGhostTextarea.value')
);

echo PHP_EOL;
echo "Geslaagd: {$passed}, gefaald: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
