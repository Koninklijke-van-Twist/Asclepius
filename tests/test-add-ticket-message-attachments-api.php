<?php
/**
 * Tests voor bijlagen bij add_ticket_message via api.php:
 * base64-bijlagen, inline-verwijzingen, limieten, finfo-typecontrole,
 * bestandsnaam-sanitizing en ongewijzigd gedrag zonder bijlagen.
 */

echo "=== TEST: API add_ticket_message met bijlagen ===" . PHP_EOL . PHP_EOL;

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

$uploadRoot = sys_get_temp_dir() . '/asclepius_test_uploads_attachments_api_' . getmypid();

function rrmdirTest(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $entry;
        is_dir($path) ? rrmdirTest($path) : @unlink($path);
    }
    @rmdir($dir);
}

register_shutdown_function(static function () use ($uploadRoot): void {
    rrmdirTest($uploadRoot);
});

function makeAttachmentStore(string $uploadRoot): TicketStore
{
    return new TicketStore(
        ':memory:',
        $uploadRoot,
        ['ict@kvt.nl', 'colleague@kvt.nl'],
        TICKET_CATEGORIES
    );
}

function createAttachmentTicket(TicketStore $store): int
{
    $result = $store->createTicket('TEST API-bijlagen', 'Anders', 'user@kvt.nl', 'Beschrijving', [], 0, [], null, 'ict@kvt.nl');

    return (int) $result['ticket_id'];
}

function countTicketMessages(TicketStore $store, int $ticketId): int
{
    $ticket = $store->getTicket($ticketId, true, 'ict@kvt.nl', 'default', true);

    return count($ticket['messages'] ?? []);
}

function countStoredFiles(string $uploadRoot, int $ticketId): int
{
    $dir = $uploadRoot . DIRECTORY_SEPARATOR . $ticketId;
    if (!is_dir($dir)) {
        return 0;
    }

    return count(array_filter(scandir($dir) ?: [], static fn(string $e): bool => $e !== '.' && $e !== '..'));
}

$GLOBALS['ictUsers'] = ['ict@kvt.nl', 'colleague@kvt.nl'];
$serviceClient = null; // service-key: $hasValidServiceApiKey = true

// 1x1 PNG en minimale GIF/PDF.
$pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
$gifBase64 = base64_encode("GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;");
$pdfBase64 = base64_encode("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");

$store = makeAttachmentStore($uploadRoot);
$ticketId = createAttachmentTicket($store);
$baseRequest = [
    'ticket_id' => $ticketId,
    'sender_email' => 'ict-bot@kvt.nl',
    'sender_name' => 'Metis',
    'sender_title' => 'Assistent',
];

echo '--- Bericht zonder bijlagen blijft ongewijzigd ---' . PHP_EOL;
$plain = handleAddTicketMessageApiAction($store, $baseRequest + ['message' => 'Gewoon bericht'], $serviceClient, true);
assertTrue('Bericht zonder bijlagen slaagt', !empty($plain['success']));
assertFalse('Geen attachments-veld in antwoord', array_key_exists('attachments', $plain));
assertFalse('Geen ticket_url toegevoegd', array_key_exists('ticket_url', $plain));
assertSame('Tekst ongewijzigd opgeslagen', 'Gewoon bericht', (string) ($plain['message']['message_text'] ?? ''));
assertSame('Geen bijlagen bij het bericht', [], $plain['message']['attachments'] ?? null);
$empty = handleAddTicketMessageApiAction($store, $baseRequest + ['message' => ''], $serviceClient, true);
assertSame('Leeg bericht zonder bijlagen geeft message_required', 'message_required', $empty['error_code'] ?? null);
$emptyList = handleAddTicketMessageApiAction($store, $baseRequest + ['message' => '', 'attachments' => []], $serviceClient, true);
assertSame('Lege attachments-lijst geeft nog steeds message_required', 'message_required', $emptyList['error_code'] ?? null);

echo PHP_EOL . '--- Geldige bijlage (zelfde opslag als UI) ---' . PHP_EOL;
$valid = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Zie bijlage',
    'attachments' => [
        ['filename' => 'rapport.pdf', 'mime' => 'application/pdf', 'data_base64' => $pdfBase64],
    ],
], $serviceClient, true);
assertTrue('Bericht met pdf slaagt', !empty($valid['success']));
assertSame('Eén bijlage in antwoord', 1, count($valid['attachments'] ?? []));
$att = $valid['attachments'][0] ?? [];
assertTrue('Bijlage heeft id', (int) ($att['id'] ?? 0) > 0);
assertSame('Bijlage-naam', 'rapport.pdf', $att['filename'] ?? null);
assertSame('Mime via finfo', 'application/pdf', $att['mime_type'] ?? null);
assertFalse('Niet inline', !empty($att['inline']));
assertContains('URL wijst naar data/ticket_uploads', '/data/ticket_uploads/' . $ticketId . '/ticket_', (string) ($att['url'] ?? ''));
assertContains('download_url via index.php?download', 'index.php?download=' . (int) ($att['id'] ?? 0), (string) ($att['download_url'] ?? ''));
assertSame('Tekst zonder marker', 'Zie bijlage', (string) ($valid['message']['message_text'] ?? ''));
assertFalse('Geen serverpad in antwoord', str_contains((string) json_encode($valid), $uploadRoot));
$dbAttachment = $store->getAttachment((int) ($att['id'] ?? 0));
assertTrue('Rij in ticket_attachments', is_array($dbAttachment));
assertSame('message_id gekoppeld', (int) $valid['message_id'], (int) ($dbAttachment['message_id'] ?? 0));
assertTrue('Opgeslagen naam ticket_*.pdf (UI-naamgeving)', preg_match('/^ticket_[0-9a-f.]+\.pdf$/', (string) ($dbAttachment['stored_name'] ?? '')) === 1);
assertTrue('Bestand staat in uploadRoot/ticketId', is_file((string) ($dbAttachment['stored_path'] ?? '')) && str_starts_with((string) $dbAttachment['stored_path'], $uploadRoot . DIRECTORY_SEPARATOR . $ticketId . DIRECTORY_SEPARATOR));
assertSame('file_size opgeslagen', strlen(base64_decode($pdfBase64)), (int) ($dbAttachment['file_size'] ?? 0));
assertSame('uploaded_by_email = afzender', 'ict-bot@kvt.nl', (string) ($dbAttachment['uploaded_by_email'] ?? ''));
assertSame('Rechten gelijk aan upload (0666 & ~umask)', 0666 & ~umask(), fileperms((string) $dbAttachment['stored_path']) & 0777);

echo PHP_EOL . '--- Inline-verwijzingen ---' . PHP_EOL;
$inline = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => "Hier de retourlijst: {{attachment:0}} en verder\nTweede: {{attachment:scherm.gif}}",
    'attachments' => [
        ['filename' => 'retourlijst.png', 'mime' => 'image/png', 'data_base64' => 'data:image/png;base64,' . $pngBase64],
        ['filename' => 'scherm.gif', 'mime' => 'image/gif', 'data_base64' => $gifBase64],
        ['filename' => 'retourlijst.png', 'mime' => 'image/png', 'data_base64' => $pngBase64, 'inline' => true],
    ],
], $serviceClient, true);
assertTrue('Inline-bericht slaagt', !empty($inline['success']));
$storedText = (string) ($inline['message']['message_text'] ?? '');
assertSame(
    'Placeholders → UI-markers op eigen regel, inline:true onderaan, dubbele naam uniek',
    "Hier de retourlijst:\n[[attachment:retourlijst.png]]\nen verder\nTweede:\n[[attachment:scherm.gif]]\n[[attachment:retourlijst-2.png]]",
    $storedText
);
$inlineNames = array_map(static fn(array $a): string => (string) $a['filename'], $inline['attachments'] ?? []);
assertSame('Bijlagenamen', ['retourlijst.png', 'scherm.gif', 'retourlijst-2.png'], $inlineNames);
assertSame('Alle drie inline', [true, true, true], array_map(static fn(array $a): bool => !empty($a['inline']), $inline['attachments'] ?? []));
$html = formatTicketMessageText($storedText, (int) $inline['message_id'], $inline['message']['attachments'] ?? []);
assertContains('Weergave toont inline afbeelding', 'attachment-inline-image', $html);
assertContains('Weergave verwijst naar opgeslagen bestand', 'data/ticket_uploads/' . $ticketId . '/ticket_', $html);
assertSame('Drie inline-afbeeldingen gerenderd', 3, substr_count($html, 'class="attachment-inline-image"'));

$badRef = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Kapot {{attachment:5}}',
    'attachments' => [['filename' => 'a.png', 'mime' => 'image/png', 'data_base64' => $pngBase64]],
], $serviceClient, true);
assertSame('Onbekende verwijzing → attachment_reference_invalid', 'attachment_reference_invalid', $badRef['error_code'] ?? null);

$onlyImage = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => '',
    'attachments' => json_encode([['filename' => 'los.png', 'mime' => 'image/png', 'data_base64' => $pngBase64, 'inline' => true]]),
], $serviceClient, true);
assertTrue('Alleen bijlage (JSON-string, zoals form-data) slaagt', !empty($onlyImage['success']));
assertSame('Tekst is alleen de marker', '[[attachment:los.png]]', (string) ($onlyImage['message']['message_text'] ?? ''));

echo PHP_EOL . '--- Afwijzingen: niets opgeslagen ---' . PHP_EOL;
$messagesBefore = countTicketMessages($store, $ticketId);
$filesBefore = countStoredFiles($uploadRoot, $ticketId);

$tooLarge = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Te groot',
    'attachments' => [['filename' => 'groot.png', 'mime' => 'image/png', 'data_base64' => base64_encode(str_repeat('A', apiMessageAttachmentMaxBytes() + 1))]],
], $serviceClient, true);
assertSame('Te groot → attachment_too_large', 'attachment_too_large', $tooLarge['error_code'] ?? null);
assertTrue('Foutantwoord bevat hint met voorbeeld', str_contains((string) ($tooLarge['hints'][0]['explanation'] ?? ''), 'data_base64'));

$tooMany = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Te veel',
    'attachments' => array_fill(0, apiMessageAttachmentMaxCount() + 1, ['filename' => 'x.png', 'mime' => 'image/png', 'data_base64' => $pngBase64]),
], $serviceClient, true);
assertSame('Te veel → too_many_attachments', 'too_many_attachments', $tooMany['error_code'] ?? null);

$wrongType = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Script',
    'attachments' => [['filename' => 'evil.php', 'mime' => 'image/png', 'data_base64' => base64_encode('<?php echo 1;')]],
], $serviceClient, true);
assertSame('.php → attachment_type_not_allowed', 'attachment_type_not_allowed', $wrongType['error_code'] ?? null);

$svg = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'SVG',
    'attachments' => [['filename' => 'x.svg', 'mime' => 'image/svg+xml', 'data_base64' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]],
], $serviceClient, true);
assertSame('.svg → attachment_type_not_allowed', 'attachment_type_not_allowed', $svg['error_code'] ?? null);

$fakePng = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Nep',
    'attachments' => [['filename' => 'nep.png', 'mime' => 'image/png', 'data_base64' => base64_encode('<html><script>alert(1)</script></html>')]],
], $serviceClient, true);
assertSame('Nep-png (html-inhoud) → attachment_content_mismatch', 'attachment_content_mismatch', $fakePng['error_code'] ?? null);

$pdfAsPng = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Nep 2',
    'attachments' => [['filename' => 'echt.pdf', 'mime' => 'image/png', 'data_base64' => $pdfBase64]],
], $serviceClient, true);
assertSame('Opgegeven mime past niet bij inhoud → attachment_mime_mismatch', 'attachment_mime_mismatch', $pdfAsPng['error_code'] ?? null);

$badBase64 = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Kapot',
    'attachments' => [['filename' => 'a.png', 'mime' => 'image/png', 'data_base64' => '!!!geen base64!!!']],
], $serviceClient, true);
assertSame('Ongeldige base64 → invalid_attachment_data', 'invalid_attachment_data', $badBase64['error_code'] ?? null);

$mixed = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Eerste goed, tweede fout',
    'status' => 'in behandeling',
    'attachments' => [
        ['filename' => 'goed.png', 'mime' => 'image/png', 'data_base64' => $pngBase64],
        ['filename' => 'fout.png', 'mime' => 'image/png', 'data_base64' => base64_encode('geen afbeelding')],
    ],
], $serviceClient, true);
assertSame('Gemengd → eerste fout gemeld', 'attachment_content_mismatch', $mixed['error_code'] ?? null);
assertSame('Fout noemt index', 1, $mixed['attachment_index'] ?? null);

assertSame('Geen half bericht na afwijzingen', $messagesBefore, countTicketMessages($store, $ticketId));
assertSame('Geen bestanden achtergebleven na afwijzingen', $filesBefore, countStoredFiles($uploadRoot, $ticketId));
$afterMixed = $store->getTicket($ticketId, true, 'ict@kvt.nl');
assertSame('Status niet gewijzigd bij bijlagefout', 'ingediend', (string) ($afterMixed['status'] ?? ''));
assertSame('Geen tijdelijke API-bestanden achtergebleven', [], glob(sys_get_temp_dir() . '/asc_api_*') ?: []);

echo PHP_EOL . '--- Bestandsnaam / path traversal ---' . PHP_EOL;
assertSame('../ verwijderd', 'passwd.png', sanitizeApiAttachmentFilename('../../etc/passwd.png'));
assertSame('Windows-pad verwijderd', 'boot.png', sanitizeApiAttachmentFilename('..\\..\\Windows\\boot.png'));
assertSame('Marker-tekens verwijderd', 'ab.png', sanitizeApiAttachmentFilename('[a]b.png'));
assertSame('Besturingstekens verwijderd', 'ab.png', sanitizeApiAttachmentFilename("a\x00\nb.png"));
assertSame('Alleen .. → leeg', '', sanitizeApiAttachmentFilename('../..'));
$traversal = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Traversal',
    'attachments' => [['filename' => '../../../web/evil.png', 'mime' => 'image/png', 'data_base64' => $pngBase64, 'inline' => true]],
], $serviceClient, true);
assertTrue('Traversal-naam wordt opgeschoond en opgeslagen', !empty($traversal['success']));
assertSame('Naam zonder pad', 'evil.png', $traversal['attachments'][0]['filename'] ?? null);
$travRow = $store->getAttachment((int) ($traversal['attachments'][0]['id'] ?? 0));
assertTrue('Bestand binnen de ticketmap', str_starts_with((string) ($travRow['stored_path'] ?? ''), $uploadRoot . DIRECTORY_SEPARATOR . $ticketId . DIRECTORY_SEPARATOR));
$noName = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => 'Geen naam',
    'attachments' => [['filename' => '../', 'mime' => 'image/png', 'data_base64' => $pngBase64]],
], $serviceClient, true);
assertSame('Lege naam → invalid_attachment_filename', 'invalid_attachment_filename', $noName['error_code'] ?? null);

echo PHP_EOL . '--- Rollback bij opslagfout ---' . PHP_EOL;
$brokenRoot = sys_get_temp_dir() . '/asclepius_test_broken_root_' . getmypid();
@file_put_contents($brokenRoot, 'geen map');
$brokenStore = makeAttachmentStore($brokenRoot);
$brokenTicket = createAttachmentTicket($brokenStore);
$brokenBefore = countTicketMessages($brokenStore, $brokenTicket);
$previousReporting = error_reporting(E_ALL & ~E_WARNING);
$broken = handleAddTicketMessageApiAction($brokenStore, $baseRequest + [
    'ticket_id' => $brokenTicket,
    'message' => 'Opslag faalt',
    'attachments' => [['filename' => 'a.png', 'mime' => 'image/png', 'data_base64' => $pngBase64]],
], $serviceClient, true);
error_reporting($previousReporting);
@unlink($brokenRoot);
assertSame('Opslagfout → attachment_store_failed', 'attachment_store_failed', $broken['error_code'] ?? null);
assertSame('Bericht teruggedraaid bij opslagfout', $brokenBefore, countTicketMessages($brokenStore, $brokenTicket));

echo PHP_EOL . '--- Multipart attachments[] ---' . PHP_EOL;
$uploadTmp = tempnam(sys_get_temp_dir(), 'phpup_');
file_put_contents($uploadTmp, base64_decode($pngBase64));
$_FILES['attachments'] = [
    'name' => ['upload.png'],
    'type' => ['image/png'],
    'tmp_name' => [$uploadTmp],
    'error' => [UPLOAD_ERR_OK],
    'size' => [filesize($uploadTmp)],
];
$multipart = handleAddTicketMessageApiAction($store, $baseRequest + [
    'message' => "Upload:\n{{attachment:upload.png}}",
], $serviceClient, true);
unset($_FILES['attachments']);
assertTrue('Multipart-upload slaagt', !empty($multipart['success']));
assertSame('Multipart-bijlage inline', "Upload:\n[[attachment:upload.png]]", (string) ($multipart['message']['message_text'] ?? ''));
assertSame('Multipart-bijlage in antwoord', 'upload.png', $multipart['attachments'][0]['filename'] ?? null);
@unlink($uploadTmp);

echo PHP_EOL . '--- Router / documentatie ---' . PHP_EOL;
$apiSource = (string) file_get_contents(__DIR__ . '/../web/api.php');
assertTrue('Router geeft 413 bij te grote bijlage', str_contains($apiSource, "'attachment_too_large', 'attachments_too_large' => 413"));
$docs = (string) file_get_contents(__DIR__ . '/../web/docs/api.md');
assertTrue('docs/api.md beschrijft attachments', str_contains($docs, '{{attachment:0}}') && str_contains($docs, 'data_base64'));

echo PHP_EOL;
echo "Geslaagd: {$passed}" . PHP_EOL;
echo "Gefaald : {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
