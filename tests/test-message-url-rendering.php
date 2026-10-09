<?php
/**
 * Regressietests voor URL's in ticketberichten (ticket #1185).
 *
 * Een bol.com-URL met een lang cijferreeks in het pad werd eerst ge-autolinkt
 * en daarna matchte de telefoonnummer-regex op de cijfers BINNEN het al
 * gegenereerde href-attribuut. Resultaat: kapotte HTML zoals
 * `9300000278266509" target="_blank" rel="noopener noreferrer">https://...`.
 *
 * Getest wordt zowel de server-side renderer (PHP) als de client-side
 * renderer in page_js.php (via node, indien beschikbaar).
 */

echo "=== TEST: URL's in ticketberichten ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/helpers.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $ok, string $html = ''): void
{
    global $passed, $failed;
    if ($ok) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }
    echo "  ✗ {$label}" . PHP_EOL;
    if ($html !== '') {
        echo "      HTML: {$html}" . PHP_EOL;
    }
    $failed++;
}

/**
 * Elke gegenereerde <a>-tag moet exact een van de veilige vormen hebben, er
 * mogen geen geneste links zijn en geen ruwe tag-fragmenten in de tekst.
 */
function linksAreWellFormed(string $html): bool
{
    preg_match_all('/<a\b[^>]*>/', $html, $tags);
    foreach ($tags[0] as $tag) {
        if (preg_match('/^<a href="(?:https?:|mailto:|tel:|index\.php|admin\.php|[.#\/?])[^"<>]*"(?: target="_blank" rel="noopener noreferrer")?>$/', $tag) !== 1) {
            return false;
        }
    }
    if (substr_count($html, '<a ') !== substr_count($html, '</a>')) {
        return false;
    }
    if (preg_match('~<a\b[^>]*>(?:(?!</a>).)*<a\b~s', $html) === 1) {
        return false;
    }

    return !str_contains($html, '" target="_blank" rel="noopener noreferrer">https://www.bol.com') || preg_match('~<a href="[^"]*" target="_blank" rel="noopener noreferrer">https://www\.bol\.com~', $html) === 1;
}

function anchor(string $hrefEscaped, string $labelEscaped): string
{
    return '<a href="' . $hrefEscaped . '" target="_blank" rel="noopener noreferrer">' . $labelEscaped . '</a>';
}

$bol = 'https://www.bol.com/nl/nl/p/xiaomi-17t-pro-12gb-ram-256gb-rom-blauw/9300000278266509';

/**
 * Gedeelde cases voor PHP en JS: [label, input, verwachte fragmenten, ongewenste fragmenten].
 * 'php_only' cases gebruiken functies die alleen server-side bestaan (e-mail/telefoon).
 */
$cases = [
    ['Bol.com-URL uit ticket #1185', $bol, [anchor($bol, $bol)], ['tel:', '&quot;']],
    ['Bol.com-URL in een zin', "Kun je deze bestellen: {$bol} graag", ['bestellen: ' . anchor($bol, $bol) . ' graag'], ['tel:']],
    ['URL met punt aan einde van zin', "Zie {$bol}.", [anchor($bol, $bol) . '.'], ['9300000278266509.&quot;', '9300000278266509."']],
    ['URL met underscores', 'Zie https://example.com/a_b_c/d_e_f.html nu', [anchor('https://example.com/a_b_c/d_e_f.html', 'https://example.com/a_b_c/d_e_f.html')], ['<em>']],
    ['URL met sterretjes', 'Zie https://example.com/a*b*c/**x**/end nu', [anchor('https://example.com/a*b*c/**x**/end', 'https://example.com/a*b*c/**x**/end')], ['<em>', '<strong>']],
    ['Sterretjes aan het eind horen niet bij de URL', 'Zie https://example.com/x** nu', [anchor('https://example.com/x', 'https://example.com/x') . '**'], ['x**"']],
    ['Cursief rond een URL', '*zie https://example.com/x*', ['<em>zie ' . anchor('https://example.com/x', 'https://example.com/x') . '</em>'], []],
    ['Vet rond een URL', '**https://example.com/x**', ['<strong>' . anchor('https://example.com/x', 'https://example.com/x') . '</strong>'], []],
    ['URL met streepjes', 'https://my-shop.example.com/some-long-product-name-123-456', [anchor('https://my-shop.example.com/some-long-product-name-123-456', 'https://my-shop.example.com/some-long-product-name-123-456')], []],
    ['URL met query string en &', 'https://example.com/search?q=xiaomi&page=2&sort=asc', [anchor('https://example.com/search?q=xiaomi&amp;page=2&amp;sort=asc', 'https://example.com/search?q=xiaomi&amp;page=2&amp;sort=asc')], ['&amp;amp;']],
    ['URL tussen haakjes', 'Product (https://example.com/p/123) is op', ['(' . anchor('https://example.com/p/123', 'https://example.com/p/123') . ') is op'], ['123)"']],
    ['URL met gebalanceerde haakjes', 'https://en.wikipedia.org/wiki/Foo_(bar)', [anchor('https://en.wikipedia.org/wiki/Foo_(bar)', 'https://en.wikipedia.org/wiki/Foo_(bar)')], []],
    ['Markdown-link', "Bestel [deze telefoon]({$bol}) svp", ['Bestel ' . anchor($bol, 'deze telefoon') . ' svp'], ['tel:', '[deze telefoon]']],
    ['Markdown-link met URL als label', "[{$bol}]({$bol})", [anchor($bol, $bol)], []],
    ['Markdown-link met vet label en * in URL', '[**vet** label](https://example.com/a*b*c)', [anchor('https://example.com/a*b*c', '<strong>vet</strong> label')], ['<em>']],
    ['Markdown-link met query string', '[zoek](https://example.com/?a=1&b=2)', [anchor('https://example.com/?a=1&amp;b=2', 'zoek')], []],
    ['Twee URL\'s in één bericht', "Optie 1: {$bol} en optie 2: https://www.coolblue.nl/product/912345678/xiaomi-17t.html.", [anchor($bol, $bol), anchor('https://www.coolblue.nl/product/912345678/xiaomi-17t.html', 'https://www.coolblue.nl/product/912345678/xiaomi-17t.html') . '.'], ['tel:']],
    ['URL in inline code', "Plak `{$bol}` in de balk", ['<code class="message-md-code">' . $bol . '</code>'], ['<a ']],
    ['www-URL', 'Ga naar www.example.com/pad-1.', [anchor('https://www.example.com/pad-1', 'www.example.com/pad-1') . '.'], []],
    ['XSS: quote in URL wordt geëscaped', 'https://example.com/"onmouseover="alert(1)', ['href="https://example.com/&quot;onmouseover=&quot;alert(1)"'], ['"onmouseover="']],
    ['XSS: javascript-markdown-link blijft tekst', '[klik](javascript:alert(1))', ['[klik](javascript:alert(1))'], ['href="javascript:']],
    ['XSS: tel-markdown-link wordt geen markdown-link', '[bel](tel:0612345678)', ['[bel](tel:'], ['>bel</a>']],
    ['XSS: HTML naast URL wordt geëscaped', '<img src=x onerror=alert(1)> https://example.com', ['&lt;img src=x onerror=alert(1)&gt; ' . anchor('https://example.com', 'https://example.com')], ['<img']],
    ['Mailto-markdown-link toegestaan', '[mail](mailto:ict@kvt.nl)', ['href="mailto:ict@kvt.nl"'], []],
];

$phpOnlyCases = [
    ['Telefoonnummer los blijft tel-link', 'Bel 06-12345678 even', ['<a href="tel:0612345678">06-12345678</a>'], []],
    ['E-mail in URL-pad wordt geen mailto', 'https://example.com/u/a@b.com/x', [anchor('https://example.com/u/a@b.com/x', 'https://example.com/u/a@b.com/x')], ['mailto:']],
    ['E-mail los blijft mailto', 'Mail ict@kvt.nl of bel', ['<a href="mailto:ict@kvt.nl">ict@kvt.nl</a>'], []],
    ['URL + telefoon + e-mail samen', "{$bol} / 0612345678 / ict@kvt.nl", [anchor($bol, $bol), '<a href="tel:0612345678">0612345678</a>', 'mailto:ict@kvt.nl'], []],
];

echo "-- PHP (server-side) --" . PHP_EOL;
foreach (array_merge($cases, $phpOnlyCases) as [$label, $input, $expected, $unexpected]) {
    $html = formatTicketMessageText($input);
    $ok = linksAreWellFormed($html);
    foreach ($expected as $needle) {
        $ok = $ok && str_contains($html, $needle);
    }
    foreach ($unexpected as $needle) {
        $ok = $ok && !str_contains($html, $needle);
    }
    check($label, $ok, $html);
}

$codeBlock = formatTicketMessageText("Voorbeeld:\n```\n{$bol}\n[x](https://example.com)\n```\nKlaar.");
check('URL in codeblok wordt geen link', !str_contains($codeBlock, '<a ') && str_contains($codeBlock, $bol), $codeBlock);

$listAndQuote = formatTicketMessageText("- {$bol}\n> {$bol}\n| Link |\n| --- |\n| {$bol} |");
check('URL in lijst/quote/tabel blijft heel', linksAreWellFormed($listAndQuote) && substr_count($listAndQuote, anchor($bol, $bol)) === 3, $listAndQuote);

$ticketLink = formatTicketMessageText('Zie https://sleutels.kvt.nl/asclepius/admin.php?open=1185.');
check('Asclepius-ticketlink wordt Ticket #1185 zonder new tab', str_contains($ticketLink, '<a href="https://sleutels.kvt.nl/asclepius/admin.php?open=1185">Ticket #1185</a>.'), $ticketLink);

$email = formatTicketMessageTextForEmail("Zie {$bol}.");
check('E-mailrenderer: bol.com-URL blijft heel', str_contains($email, '<a href="' . $bol . '">' . $bol . '</a>.') && !str_contains($email, 'tel:'), $email);

$ghostHtml = renderTicketMessageHtml([
    'id' => 1185,
    'sender_email' => 'ict-bot@kvt.nl',
    'sender_display_name' => 'ICT-Bot',
    'sender_role' => 'admin',
    'sender_role_title' => 'Bot',
    'created_at' => '2026-10-09T11:36:00+00:00',
    'is_ghost' => true,
    'message_text' => "Link: {$bol}",
    'message_text_raw' => "Link: {$bol}",
    'attachments' => [],
], 'admin.php');
check('Ghost-bericht: bol.com-URL blijft heel', str_contains($ghostHtml, anchor($bol, $bol)) && !str_contains($ghostHtml, 'tel:9300000278266509'));

echo PHP_EOL . "-- JS (client-side, page_js.php) --" . PHP_EOL;
$nodeBin = trim((string) shell_exec('command -v node 2>/dev/null'));
if ($nodeBin === '') {
    echo "  (node niet gevonden; JS-tests overgeslagen)" . PHP_EOL;
} else {
    $pageJs = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
    $startMarker = '// ASCLEPIUS-INLINE-RENDERER-START';
    $endMarker = '// ASCLEPIUS-INLINE-RENDERER-END';
    $start = strpos($pageJs, $startMarker);
    $end = strpos($pageJs, $endMarker);
    check('JS-renderer-markers gevonden in page_js.php', $start !== false && $end !== false && $end > $start);
    if ($start !== false && $end !== false && $end > $start) {
        $block = substr($pageJs, $start, $end - $start);
        check('JS-blok bevat geen PHP', !str_contains($block, '<?'));
        $inputs = array_map(static fn (array $case): string => $case[1], $cases);
        $script = <<<'JS'
var escapeHtml = function (value) {
    return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
};
var extractAsclepiusTicketIdFromUrl = function (url) {
    var m = String(url || '').match(/(?:index|admin)\.php\?(?:.*&)?open=(\d+)/i);
    return m ? parseInt(m[1], 10) : 0;
};
var formatTicketRefLabel = function (id) { return 'Ticket #' + id; };
var renderShortcutMarkup = function (html) { return html; };
JS;
        $script .= "\n" . $block . "\n"
            . 'var inputs = ' . json_encode($inputs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ";\n"
            . "process.stdout.write(JSON.stringify(inputs.map(formatTicketMessageInlineHtml)));\n";
        $tmp = tempnam(sys_get_temp_dir(), 'asc-js-') . '.js';
        file_put_contents($tmp, $script);
        $output = shell_exec(escapeshellarg($nodeBin) . ' ' . escapeshellarg($tmp) . ' 2>&1');
        @unlink($tmp);
        $results = json_decode((string) $output, true);
        check('JS-renderer draait in node', is_array($results), (string) $output);
        if (is_array($results)) {
            foreach ($cases as $i => [$label, $input, $expected, $unexpected]) {
                $html = (string) ($results[$i] ?? '');
                $ok = linksAreWellFormed($html);
                foreach ($expected as $needle) {
                    // JS escapet ' als &#39; i.p.v. &#039;; geen van de cases bevat een quote-teken in de verwachting.
                    $ok = $ok && str_contains($html, $needle);
                }
                foreach ($unexpected as $needle) {
                    $ok = $ok && !str_contains($html, $needle);
                }
                check('JS: ' . $label, $ok, $html);
            }
        }
    }
}

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
