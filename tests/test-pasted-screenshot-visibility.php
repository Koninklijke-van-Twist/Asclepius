<?php
/**
 * Geplakte schermafdrukken blijven een normale bijlage en blijven zichtbaar
 * als een vertaling de [[attachment:…]]-markering heeft laten vallen.
 */

echo "=== TEST: geplakte schermafdruk blijft zichtbaar ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/helpers.php';
require __DIR__ . '/../web/content/TranslationProvider.php';
require __DIR__ . '/../web/content/LaraTranslationProvider.php';
require __DIR__ . '/../web/content/CoPilotTranslate.php';
require __DIR__ . '/../web/content/CopilotTranslationProvider.php';
require __DIR__ . '/../web/content/translation.php';

$passed = 0;
$failed = 0;

function assertShot(string $label, bool $actual): void
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

$raw = "[[attachment:schermafdruk.png]]\n\nVerzenden lukt niet";
$stripped = 'Verzenden lukt niet';

$restored = restoreMissingAttachmentMarkers($stripped, $raw);
assertShot('Markering komt terug voor de tekst', $restored === $raw);

$already = restoreMissingAttachmentMarkers($raw, $raw);
assertShot('Aanwezige markering wordt niet verdubbeld', $already === $raw);

$middleRaw = "Eerst de tekst\n[[attachment:midden.png]]\nEn daarna nog tekst";
$middleDisplay = "First the text\nAnd then more text";
$middleRestored = restoreMissingAttachmentMarkers($middleDisplay, $middleRaw);
assertShot(
    'Markering na tekst komt achteraan',
    str_ends_with($middleRestored, "[[attachment:midden.png]]")
        && str_starts_with($middleRestored, 'First the text')
);

$stored = ticketTranslationStoredResult($raw, 'nl', $stripped, '');
$shown = ticketTranslationApplyStatusNotes($stored, $raw, 'nl');
assertShot('Weergavetekst is het origineel met markering', ($shown['text'] ?? '') === $raw);
assertShot('Alleen een weggevallen markering telt niet als vertaling', empty($shown['is_translated']));

$englishStored = ticketTranslationStoredResult($raw, 'en', 'Sending failed', 'nl');
$englishShown = ticketTranslationApplyStatusNotes($englishStored, $raw, 'en');
assertShot(
    'Engelse vertaling houdt de schermafdruk',
    ($englishShown['text'] ?? '') === "[[attachment:schermafdruk.png]]\n\nSending failed"
);
assertShot('Engelse tekst blijft een vertaling', !empty($englishShown['is_translated']));

$png = [
    'id' => 1346,
    'ticket_id' => 1244,
    'original_name' => 'schermafdruk.png',
    'stored_name' => 'ticket_abc.png',
    'stored_path' => '/tmp/ticket_uploads/1244/ticket_abc.png',
    'mime_type' => 'image/png',
    'file_size' => 128,
];
$html = renderTicketMessageHtml([
    'id' => 5634,
    'sender_email' => 'user@kvt.nl',
    'sender_role' => 'user',
    'created_at' => '2026-10-15T11:00:00+00:00',
    'message_text' => $stripped,
    'message_text_raw' => $raw,
    'attachments' => [$png],
], 'index.php');
assertShot('Bericht toont de schermafdruk inline', str_contains($html, 'class="attachment-inline-image"'));
assertShot('Inline-afbeelding wijst naar de opgeslagen bijlage', str_contains($html, 'data/ticket_uploads/1244/ticket_abc.png'));
assertShot('Berichttekst blijft staan', str_contains($html, 'Verzenden lukt niet'));
assertShot('Schermafdruk staat niet nog eens in de bijlagelijst', !str_contains($html, 'class="attachment-list"'));
assertShot('Precies één inline-afbeelding', substr_count($html, 'class="attachment-inline-image"') === 1);

$dataUrl = formatTicketMessageText('[plaatje](data:image/png;base64,aaaa)');
assertShot('data:-URL wordt geen link', !str_contains($dataUrl, 'href="data:'));
assertShot('data:-URL wordt geen img', !str_contains($dataUrl, '<img'));

$masked = maskMessageAttachmentMarkers($raw);
assertShot('Masker haalt de markering uit de brontekst', !str_contains($masked['text'], '[[attachment:'));
assertShot('Masker zet een placeholder', str_contains($masked['text'], '⟦att0⟧'));
assertShot(
    'Unmask zet de markering terug',
    unmaskMessageAttachmentMarkers($masked['text'], $masked['tokens']) === $raw
);

echo PHP_EOL;
echo "Geslaagd: {$passed}, gefaald: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
