<?php
/**
 * Afbeeldingsbijlagen krijgen een zichtbare preview.
 * Een miniatuur die niet bestaat valt terug op het originele bestand.
 * Andere bestandstypen houden hun bestaande voorbeeldknop.
 */

echo "=== TEST: previews van afbeeldingsbijlagen ===" . PHP_EOL . PHP_EOL;

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

function assertPreview(string $label, bool $actual): void
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

$png = [
    'id' => 164397,
    'ticket_id' => 164397,
    'original_name' => '164397_projectkaart.png',
    'stored_name' => 'ticket_projectkaart.png',
    'stored_path' => '/tmp/ticket_uploads/164397/ticket_projectkaart.png',
    'mime_type' => 'application/octet-stream',
    'file_size' => 2400,
];
$gif = [
    'id' => 88,
    'ticket_id' => 164397,
    'original_name' => 'scherm.gif',
    'stored_name' => 'ticket_scherm.gif',
    'mime_type' => 'image/gif',
    'file_size' => 400,
];
$pdf = [
    'id' => 89,
    'ticket_id' => 164397,
    'original_name' => 'rapport.pdf',
    'stored_name' => 'ticket_rapport.pdf',
    'mime_type' => 'application/pdf',
    'file_size' => 1200,
];

assertPreview('octet-stream + .png telt als afbeelding', isImageAttachment($png));
assertPreview(
    'Content-Type van een png zonder image-mime is image/png',
    imageMimeForAttachment($png) === 'image/png'
);
assertPreview(
    'Opgeslagen image/jpeg blijft image/jpeg',
    imageMimeForAttachment(['original_name' => 'a.jpg', 'mime_type' => 'image/jpeg']) === 'image/jpeg'
);
assertPreview(
    'image/jpg wordt image/jpeg',
    imageMimeForAttachment(['original_name' => 'a.jpg', 'mime_type' => 'image/jpg']) === 'image/jpeg'
);

$thumbUrl = buildAttachmentThumbnailUrl($png);
$fullUrl = buildAttachmentContentUrl($png);
assertPreview('Miniatuur-URL wijst naar dezelfde bijlage', str_contains($thumbUrl, 'download=164397'));
assertPreview('Miniatuur-URL vraagt een thumb', str_contains($thumbUrl, 'thumb=1') && str_contains($thumbUrl, 'preview=1'));
assertPreview('Volledige URL is het origineel zonder thumb', $fullUrl === '?download=164397&preview=1');
assertPreview('SVG-preview is het origineel zelf', buildAttachmentThumbnailUrl([
    'id' => 3,
    'original_name' => 'logo.svg',
    'mime_type' => 'image/svg+xml',
]) === '?download=3&preview=1');
assertPreview('Pdf heeft geen miniatuur-URL', buildAttachmentThumbnailUrl($pdf) === '');

$inlineHtml = renderTicketMessageHtml([
    'id' => 1,
    'sender_email' => 'metis@kvt.nl',
    'sender_role' => 'admin',
    'created_at' => '2026-10-21T08:00:00+00:00',
    'message_text' => "Kaart:\n[[attachment:164397_projectkaart.png]]",
    'message_text_raw' => "Kaart:\n[[attachment:164397_projectkaart.png]]",
    'attachments' => [$png],
], 'index.php');

assertPreview('Inline-kaart toont een img', str_contains($inlineHtml, 'class="attachment-inline-image"'));
assertPreview('Inline-src is niet leeg', !str_contains($inlineHtml, 'src=""'));
assertPreview('Inline-src is de miniatuur', str_contains($inlineHtml, 'src="?download=164397&amp;preview=1&amp;thumb=1"'));
assertPreview('Inline-fallback is het origineel', str_contains($inlineHtml, 'data-full-src="?download=164397&amp;preview=1"'));
assertPreview('Klik opent nog steeds het origineel', str_contains($inlineHtml, 'data-preview-src="?download=164397&amp;preview=1"'));
assertPreview('Fallback-script wijst naar data-full-src', str_contains($inlineHtml, 'this.dataset.fullSrc'));
assertPreview('Geen ruw uploadpad in de inline-preview', !str_contains($inlineHtml, 'data/ticket_uploads/'));

$listHtml = renderTicketMessageHtml([
    'id' => 2,
    'sender_email' => 'metis@kvt.nl',
    'sender_role' => 'admin',
    'created_at' => '2026-10-21T08:00:00+00:00',
    'message_text' => 'Zie bijlagen',
    'message_text_raw' => 'Zie bijlagen',
    'attachments' => [$png, $gif, $pdf],
], 'index.php');

assertPreview('Lijst toont twee afbeeldingspreviews', substr_count($listHtml, 'class="attachment-thumb"') === 2);
assertPreview('Png in de lijst heeft een miniatuur-src', str_contains($listHtml, 'src="?download=164397&amp;preview=1&amp;thumb=1"'));
assertPreview('Gif in de lijst heeft een miniatuur-src', str_contains($listHtml, 'src="?download=88&amp;preview=1&amp;thumb=1"'));
assertPreview('Gif valt terug op het origineel', str_contains($listHtml, 'data-full-src="?download=88&amp;preview=1"'));
assertPreview('Pdf krijgt geen img-preview', substr_count($listHtml, '<img') === 2);
assertPreview('Pdf houdt de bestaande voorbeeldknop', str_contains($listHtml, 'data-file-thumb-open') && str_contains($listHtml, 'preview.php?id=89&amp;thumbnail=1'));
assertPreview('Pdf-naam blijft een downloadlink', str_contains($listHtml, 'rapport.pdf'));

$tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
$tmpDir = sys_get_temp_dir() . '/asclepius-thumb-' . getmypid();
mkdir($tmpDir);
$pngPath = $tmpDir . '/kaart.png';
$missingPath = $tmpDir . '/weg.png';
file_put_contents($pngPath, $tinyPng);

$jpeg = renderAttachmentThumbnailJpeg($pngPath);
if (function_exists('imagecreatefromstring')) {
    assertPreview('Miniatuur van een png is een jpeg', is_string($jpeg) && str_starts_with((string) $jpeg, "\xFF\xD8"));
    assertPreview('Miniatuur wordt bewaard naast het origineel', is_file($pngPath . '.thumb.jpg'));
    $again = renderAttachmentThumbnailJpeg($pngPath);
    assertPreview('Tweede keer leest de bewaarde miniatuur', $again === $jpeg);
} else {
    assertPreview('Zonder GD is er geen miniatuur (aanroeper dient het origineel)', $jpeg === null);
}
assertPreview('Ontbrekend bestand geeft geen miniatuur', renderAttachmentThumbnailJpeg($missingPath) === null);
assertPreview('Leeg pad geeft geen miniatuur', renderAttachmentThumbnailJpeg('') === null);

$js = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
assertPreview(
    'Preview-loader maakt een mislukte preview niet meer leeg',
    !str_contains($js, "imageThumb.removeAttribute('src')")
);
assertPreview('Preview-loader valt terug op het origineel', str_contains($js, 'data-full-src'));

@unlink($pngPath . '.thumb.jpg');
@unlink($pngPath);
@rmdir($tmpDir);

echo PHP_EOL;
echo "Geslaagd: {$passed}, gefaald: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
