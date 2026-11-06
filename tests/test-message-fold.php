<?php
/**
 * Inklappen van lange ticketberichten is alleen weergave.
 * Een bronregel is een regel na newline-normalisatie en trim, geen visuele terugloop.
 */

echo "=== TEST: lang bericht invouwen ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';
$_SESSION['lang'] = 'nl';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/helpers.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }
    echo "  ✗ {$label}" . PHP_EOL;
    if ($detail !== '') {
        echo "      {$detail}" . PHP_EOL;
    }
    $failed++;
}

function numberedLines(int $count): string
{
    $lines = [];
    for ($i = 1; $i <= $count; $i++) {
        $lines[] = 'regel ' . $i;
    }

    return implode("\n", $lines);
}

function foldPart(string $html, string $role): string
{
    $start = strpos($html, 'data-role="' . $role . '"');
    if ($start === false) {
        return '';
    }
    $start = strpos($html, '>', $start);
    if ($start === false) {
        return '';
    }
    $start++;
    $endMarker = $role === 'message-fold-preview'
        ? '<div class="message-fold-full"'
        : '<button type="button" class="message-fold-toggle"';
    $end = strpos($html, $endMarker, $start);
    if ($end === false) {
        return '';
    }

    return substr($html, $start, $end - $start);
}

check('grens is 35 bronregels', TICKET_MESSAGE_FOLD_LINE_LIMIT === 35);
check('Nederlandse knoptekst', __('ticket.show_full_message') === 'hele bericht tonen');

$short = renderFoldableMessageBody('Korte tekst.', '<p>Korte tekst.</p>', 1);
check('kort bericht blijft zonder knop', !str_contains($short, 'message-fold') && str_contains($short, 'Korte tekst.'));

$exact = renderFoldableMessageBody(numberedLines(35), '<p>' . numberedLines(35) . '</p>', 2);
check('35 regels blijven volledig zichtbaar', !str_contains($exact, 'message-fold') && str_contains($exact, 'regel 35'));

$oneLine = str_repeat('woord ', 80);
$wrapped = renderFoldableMessageBody($oneLine, '<p>' . $oneLine . '</p>', 3);
check('terugloop telt niet als extra regel', !str_contains($wrapped, 'message-fold'));

$longText = numberedLines(36);
$longFull = '<p>' . $longText . '</p>';
$long = renderFoldableMessageBody($longText, $longFull, 4);
$preview = foldPart($long, 'message-fold-preview');
$full = foldPart($long, 'message-fold-full');
check('36 regels krijgen een vouw', str_contains($long, 'data-role="message-fold"'));
check('knoptekst is exact', str_contains($long, '>hele bericht tonen</button>'));
check('preview houdt regel 35', str_contains($preview, 'regel 35'));
check('preview stopt voor regel 36', !str_contains($preview, 'regel 36'));
check('volledige html houdt regel 36', str_contains($full, 'regel 36') && str_contains($full, $longFull));
check('geen tweede knop', substr_count($long, 'hele bericht tonen') === 1);

$crlf = renderFoldableMessageBody(implode("\r\n", array_map(static fn(int $i): string => 'cr ' . $i, range(1, 36))), '<p>cr-full</p>', 5);
check('CRLF telt als één regeleinde', str_contains(foldPart($crlf, 'message-fold-preview'), 'cr 35') && !str_contains(foldPart($crlf, 'message-fold-preview'), 'cr 36'));

$withBlank = "kop\n\n" . numberedLines(33);
check('35 regels met een lege regel blijven dicht', messageFoldPreviewSource($withBlank) === null);
check('een lege regel telt mee boven de grens', messageFoldPreviewSource("kop\n\n" . numberedLines(34)) !== null);

$tableRows = ["| Bedrijf | Notitie |", "| --- | --- |", "| Voorbeeld | &lt;blijft tekst&gt; |"];
for ($row = 1; $row <= 40; $row++) {
    $cell = $row === 1 ? '<script>alert(1)</script>' : 'rij ' . $row;
    $tableRows[] = '| Voorbeeld BV | ' . $cell . ' |';
}
$tableRows[] = '| Voorbeeld BV | LAATSTE-TABELRIJ |';
$tableText = implode("\n", $tableRows);
$tableHtml = renderFoldableMessageBody($tableText, formatTicketMessageText($tableText, 8), 8);
$tablePreview = foldPart($tableHtml, 'message-fold-preview');
$tableFull = foldPart($tableHtml, 'message-fold-full');
check('tabelpreview is een tabel', str_contains($tablePreview, 'class="message-md-table"'));
check('tabelpreview escapt script', str_contains($tablePreview, '&lt;script&gt;') && !str_contains($tablePreview, '<script>'));
check('volledige tabel houdt de laatste rij', str_contains($tableFull, 'LAATSTE-TABELRIJ'));
check('preview snijdt de laatste tabelrij weg', !str_contains($tablePreview, 'LAATSTE-TABELRIJ'));

$fenceLines = ['inleiding'];
$fenceLines[] = '```php';
for ($i = 3; $i <= 40; $i++) {
    $fenceLines[] = 'code-' . $i;
}
$fenceLines[] = '```';
$fenceText = implode("\n", $fenceLines);
$fenceHtml = renderFoldableMessageBody($fenceText, formatTicketMessageText($fenceText, 9), 9);
$fencePreview = foldPart($fenceHtml, 'message-fold-preview');
check('open codeblok in de preview blijft code', str_contains($fencePreview, 'message-md-pre') && str_contains($fencePreview, 'code-35'));
check('code na de grens zit alleen in het geheel', !str_contains($fencePreview, 'code-36') && str_contains(foldPart($fenceHtml, 'message-fold-full'), 'code-36'));

$big = ["| Bedrijf | Nummer |", "| --- | ---: |"];
for ($i = 1; $i <= 9063; $i++) {
    $big[] = '| Voorbeeld BV | P-' . $i . ' |';
}
$bigText = implode("\n", $big);
$bigHtml = renderFoldableMessageBody($bigText, formatTicketMessageText($bigText, 10), 10);
$bigPreview = foldPart($bigHtml, 'message-fold-preview');
$bigFull = foldPart($bigHtml, 'message-fold-full');
check('9063 rijen vouwen na 35 bronregels', str_contains($bigPreview, 'P-33') && !str_contains($bigPreview, 'P-34'));
check('9063 rijen blijven in de volledige html', str_contains($bigFull, 'P-9063') && str_contains($bigFull, 'P-1'));
check('grote tabel escapt geen inhoud weg', substr_count($bigHtml, 'hele bericht tonen') === 1);

$other = renderFoldableMessageBody(numberedLines(36), '<p>ANDER-BERICHT regel 36</p>', 11);
check('tweede bericht heeft een eigen knop', str_contains($other, 'ANDER-BERICHT regel 36') && str_contains($other, '>hele bericht tonen</button>'));

$article = renderTicketMessageHtml([
    'id' => 12,
    'sender_email' => 'bot@example.com',
    'sender_role' => 'user',
    'message_text' => numberedLines(36),
    'created_at' => '2026-11-06T12:00:00+01:00',
    'attachments' => [],
], 'index.php', false);
check('gewone berichtweergave vouwt', str_contains($article, 'data-role="message-fold-toggle"') && str_contains($article, 'regel 36'));

$plainArticle = renderTicketMessageHtml([
    'id' => 13,
    'sender_email' => 'bot@example.com',
    'sender_role' => 'user',
    'message_text' => numberedLines(35),
    'created_at' => '2026-11-06T12:00:00+01:00',
    'attachments' => [],
], 'index.php', false);
check('bericht van 35 regels heeft geen knop', !str_contains($plainArticle, 'message-fold-toggle') && str_contains($plainArticle, 'regel 35'));

$mail = formatTicketMessageTextForEmail(numberedLines(36));
$apiHtml = formatTicketMessageText(numberedLines(36), 14);
check('mail blijft de volledige tekst', !str_contains($mail, 'message-fold') && str_contains($mail, 'regel 36'));
check('losse opmaak blijft zonder vouw', !str_contains($apiHtml, 'message-fold') && str_contains($apiHtml, 'regel 36'));

$pageJs = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
check('nieuw ingeladen berichten krijgen dezelfde grens', str_contains($pageJs, 'installMessageFold(') && str_contains($pageJs, 'TICKET_MESSAGE_FOLD_LINE_LIMIT'));
check('klik vouwt alleen het aangeklikte bericht', str_contains($pageJs, "closest('[data-role=\"message-fold\"]')"));
check('grafiekfunctie blijft staan', str_contains($pageJs, 'trendSmoothPath'));

echo PHP_EOL . "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
