<?php
/**
 * Tests for safe Markdown rendering in ticket messages.
 */

echo "=== TEST: ticket message Markdown ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/helpers.php';

$passed = 0;
$failed = 0;

function assertTrue(string $label, bool $actual): void
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

function assertContains(string $label, string $needle, string $haystack): void
{
    assertTrue($label, str_contains($haystack, $needle));
    if (!str_contains($haystack, $needle)) {
        echo "      Gezocht : {$needle}" . PHP_EOL;
        echo "      HTML    : {$haystack}" . PHP_EOL;
    }
}

function assertNotContains(string $label, string $needle, string $haystack): void
{
    assertTrue($label, !str_contains($haystack, $needle));
    if (str_contains($haystack, $needle)) {
        echo "      Ongewenst: {$needle}" . PHP_EOL;
        echo "      HTML     : {$haystack}" . PHP_EOL;
    }
}

$plain = formatTicketMessageText("Hallo,\n\nDe printer is kapot.");
assertContains('Platte tekst behoudt regeleinden', 'Hallo,<br><br>De printer is kapot.', $plain);
assertNotContains('Platte tekst krijgt geen kop-tag', '<h3', $plain);

$inline = formatTicketMessageText('Dit is **vet** en *cursief* plus `code`.');
assertContains('Vet wordt strong', '<strong>vet</strong>', $inline);
assertContains('Cursief wordt em', '<em>cursief</em>', $inline);
assertContains('Inline code wordt code', '<code class="message-md-code">code</code>', $inline);

$mathLike = formatTicketMessageText('Bereken 3 * 4 * 5 en a ** b ** c.');
assertNotContains('Losse sterretjes blijven geen cursief', '<em>', $mathLike);
assertNotContains('Losse sterretjes blijven geen vet', '<strong>', $mathLike);

$heading = formatTicketMessageText("# Titel\n## Subtitel\nTekst");
assertContains('H1-markdown wordt h3 in het bericht', '<h3 class="message-md-heading message-md-heading-1">', $heading);
assertContains('H2-markdown wordt h4 in het bericht', '<h4 class="message-md-heading message-md-heading-2">', $heading);
assertContains('Koptekst is geëscaped/interactive', 'Titel', $heading);

$lists = formatTicketMessageText("- een\n- twee\n\n1. eerste\n2. tweede");
assertContains('Ongeordende lijst', '<ul class="message-md-list">', $lists);
assertContains('Lijstitem', '<li>', $lists);
assertContains('Geordende lijst', '<ol class="message-md-list message-md-list-ordered">', $lists);

$quote = formatTicketMessageText("> let op\n> tweede regel");
assertContains('Blockquote', '<blockquote class="message-md-quote">', $quote);
assertContains('Blockquote-inhoud', 'let op', $quote);

$table = formatTicketMessageText(
    "| Artikel | Aantal | Gereserveerd |\n"
    . "| --- | ---: | :---: |\n"
    . "| **ABC-1** | 2 | 1 |\n"
    . "| DEF | 3 | 0 |"
);
assertContains('GFM-tabel krijgt table-class', 'class="message-md-table"', $table);
assertContains('GFM-tabel heeft thead', '<thead>', $table);
assertContains('GFM-tabel heeft th', '<th class="message-md-cell">Artikel</th>', $table);
assertContains('GFM-tabel heeft body-rij', '<td class="message-md-cell">DEF</td>', $table);
assertContains('Tabelcel krijgt inline vet', '<strong>ABC-1</strong>', $table);
assertContains('Rechterkolom krijgt alignment-class', 'message-md-cell-right', $table);
assertContains('Gecentreerde kolom krijgt alignment-class', 'message-md-cell-center', $table);

$tableInline = formatTicketMessageText(
    "| Toets | Link |\n"
    . "| --- | --- |\n"
    . "| [Ctrl] | https://sleutels.kvt.nl/asclepius/index.php?open=943 |"
);
assertContains('Toets-icoon in tabelcel', 'class="shortcut-key"', $tableInline);
assertContains('Ticket-URL in tabelcel wordt Ticket #', 'Ticket #943', $tableInline);

$tableXss = formatTicketMessageText(
    "| Col |\n"
    . "| --- |\n"
    . "| <script>alert(1)</script> |"
);
assertNotContains('Tabelcel voert geen script uit', '<script>alert(1)</script>', $tableXss);
assertContains('Tabelcel-escapes HTML', '&lt;script&gt;alert(1)&lt;/script&gt;', $tableXss);

$notATable = formatTicketMessageText('Prijs | voorraad zonder scheidingsrij');
assertNotContains('Pijp zonder scheidingsrij blijft geen tabel', 'message-md-table', $notATable);
assertContains('Pijp zonder scheidingsrij blijft tekst', 'Prijs | voorraad zonder scheidingsrij', $notATable);

$link = formatTicketMessageText('Zie [de docs](https://example.com/docs) voor meer.');
assertContains('Markdown-link krijgt href', 'href="https://example.com/docs"', $link);
assertContains('Markdown-link opent extern veilig', 'rel="noopener noreferrer"', $link);
assertContains('Markdown-linklabel', 'de docs', $link);

$fence = formatTicketMessageText("Voorbeeld:\n```php\necho '<script>';\n```");
assertContains('Codehek wordt pre/code', '<pre class="message-md-pre"><code class="language-php">', $fence);
assertContains('Codehek-escapes HTML', 'echo &#039;&lt;script&gt;&#039;;', $fence);
assertNotContains('Codehek voert geen HTML uit', '<script>', $fence);

$xss = formatTicketMessageText('<script>alert(1)</script> en [klik](javascript:alert(1))');
assertNotContains('Script-tag wordt niet letterlijk weergegeven', '<script>alert(1)</script>', $xss);
assertContains('Script-tag is geëscaped', '&lt;script&gt;alert(1)&lt;/script&gt;', $xss);
assertNotContains('javascript:-href wordt geweigerd', 'href="javascript:', $xss);
assertContains('Onveilige markdown-link blijft platte tekst', '[klik](javascript:alert(1))', $xss);

$letterLink = formatTicketMessageText('[x](https://example.com/x)');
assertContains('Enkel-letter-label blijft markdown-link', 'href="https://example.com/x"', $letterLink);
assertNotContains('Enkel-letter-label is geen checkbox', 'message-checkbox', $letterLink);

$dataUrl = formatTicketMessageText('[docs](data:text/html,alert(1))');
assertNotContains('data:-href wordt geweigerd', 'href="data:', $dataUrl);
assertContains('Onveilige data-link blijft platte tekst', '[docs](data:text/html,alert(1))', $dataUrl);

$underscoreName = formatTicketMessageText('Bestand: rapport_2026_final.pdf van first_last@kvt.nl');
assertNotContains('Underscores in bestandsnamen worden geen cursief', '<em>', $underscoreName);
assertContains('E-mail blijft link', 'mailto:first_last@kvt.nl', $underscoreName);

$shortcut = formatTicketMessageText('Kopieer met [Ctrl] + [C] en daarna **plakken**.');
assertContains('Toets-icoon Ctrl blijft shortcut-key', 'class="shortcut-key"', $shortcut);
assertContains('Toets-label Ctrl', 'Ctrl', $shortcut);
assertContains('Markdown naast toetsen', '<strong>plakken</strong>', $shortcut);

$checkbox = formatTicketMessageText("[ ] Eerste taak\n[x] Tweede taak", 42);
assertContains('Open checkbox behoudt line-index 0', 'data-line-index="0"', $checkbox);
assertContains('Aangevinkte checkbox behoudt line-index 1', 'data-line-index="1"', $checkbox);
assertContains('Checkbox heeft message-id', 'data-message-id="42"', $checkbox);
assertContains('Checkbox-rol blijft aanwezig', 'data-role="message-checkbox"', $checkbox);

$taskList = formatTicketMessageText("- [ ] open\n- [x] klaar", 7);
assertContains('Taaklijst zit in ul', '<ul class="message-md-list">', $taskList);
assertContains('Taaklijst-checkbox line-index 0', 'data-line-index="0"', $taskList);
assertContains('Taaklijst-checkbox line-index 1', 'data-line-index="1"', $taskList);

$attachmentHtml = formatTicketMessageText(
    "Zie bijlage:\n[[attachment:rapport.pdf]]\nKlaar.",
    9,
    [[
        'id' => 15,
        'original_name' => 'rapport.pdf',
        'stored_name' => 'rapport.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 128,
    ]]
);
assertContains('Inline bijlage-wrapper blijft bestaan', 'class="message-inline-attachment"', $attachmentHtml);
assertContains('Bijlagenaam blijft zichtbaar', 'rapport.pdf', $attachmentHtml);
assertContains('Omringende tekst blijft staan', 'Zie bijlage:', $attachmentHtml);

$missingAttachment = formatTicketMessageText("[[attachment:ontbreekt.png]]");
assertContains('Ontbrekende bijlage wordt em', '<em>ontbreekt.png</em>', $missingAttachment);

$ghostHtml = renderTicketMessageHtml([
    'id' => 88,
    'sender_email' => 'ict-bot@kvt.nl',
    'sender_display_name' => 'ICT-Bot',
    'sender_role' => 'admin',
    'sender_role_title' => 'Bot',
    'created_at' => '2026-09-11T12:00:00+00:00',
    'is_ghost' => true,
    'message_text' => "## Ghost-advies\nGebruik **VPN** en [Ctrl] + [L].\n\n| Artikel | Aantal |\n| --- | --- |\n| BC-10 | 4 |",
    'message_text_raw' => "## Ghost-advies\nGebruik **VPN** en [Ctrl] + [L].\n\n| Artikel | Aantal |\n| --- | --- |\n| BC-10 | 4 |",
    'attachments' => [],
], 'index.php');
assertContains('Ghost-bericht krijgt is-ghost', 'class="message admin is-ghost"', $ghostHtml);
assertContains('Ghost-markdown rendert kop', 'Ghost-advies', $ghostHtml);
assertContains('Ghost-markdown rendert vet', '<strong>VPN</strong>', $ghostHtml);
assertContains('Ghost-toets blijft shortcut-key', 'class="shortcut-key"', $ghostHtml);
assertContains('Ghost-tabel wordt HTML-tabel', 'class="message-md-table"', $ghostHtml);
assertContains('Ghost-tabelcel blijft zichtbaar', 'BC-10', $ghostHtml);
assertContains('Vooraf gerenderde HTML staat in data-attribuut', 'data-translated-html', $ghostHtml);

$normalHtml = renderTicketMessageHtml([
    'id' => 89,
    'sender_email' => 'user@kvt.nl',
    'sender_display_name' => 'User',
    'sender_role' => 'user',
    'created_at' => '2026-09-11T12:00:00+00:00',
    'message_text' => 'Gewoon **bericht** met [Ctrl].',
    'attachments' => [],
], 'index.php');
assertContains('Normaal bericht rendert Markdown', '<strong>bericht</strong>', $normalHtml);
assertContains('Normaal bericht houdt toets-iconen', 'class="shortcut-key"', $normalHtml);

$emailHtml = formatTicketMessageTextForEmail("**Belangrijk**\n[Ctrl] + [S]");
assertContains('E-mail krijgt Markdown', '<strong>Belangrijk</strong>', $emailHtml);
assertContains('E-mail houdt toets-iconen', 'class="shortcut-key"', $emailHtml);

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
