<?php
/**
 * Codehekken in ticketberichten: niet vertalen, wel veilig blijven renderen.
 */

echo "=== TEST: codehekken blijven broncode ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';
$_SESSION = [];

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/helpers.php';
require __DIR__ . '/../web/content/TranslationProvider.php';
require __DIR__ . '/../web/content/LaraTranslationProvider.php';
require __DIR__ . '/../web/content/CoPilotTranslate.php';
require __DIR__ . '/../web/content/CopilotTranslationProvider.php';
require __DIR__ . '/../web/content/translation.php';
require __DIR__ . '/../web/content/mail.php';

$passed = 0;
$failed = 0;

function assertCode(string $label, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "  ✗ {$label}" . PHP_EOL;
    echo '      Verwacht : ' . var_export($expected, true) . PHP_EOL;
    echo '      Gekregen : ' . var_export($actual, true) . PHP_EOL;
    $failed++;
}

function assertCodeTrue(string $label, bool $condition): void
{
    assertCode($label, true, $condition);
}

$raw = "De query is:\n```sql\nSELECT naam FROM klanten;\n```\nKlaar.";
$masked = maskMessageFencedCodeBlocks($raw);
assertCodeTrue('Masker haalt de SQL uit de tekst', !str_contains($masked['text'], 'SELECT'));
assertCodeTrue('Masker zet een placeholder', str_contains($masked['text'], '⟦cb0⟧'));
assertCode('Unmask zet het hek exact terug', $raw, unmaskMessageFencedCodeBlocks($masked['text'], $masked['tokens']));

$withPlaceholderInCode = "```js\nconst mark = '⟦cb1⟧';\n```\n\n```php\necho 1;\n```";
$maskedPair = maskMessageFencedCodeBlocks($withPlaceholderInCode);
assertCodeTrue('Tweede blok is een eigen placeholder', str_contains($maskedPair['text'], '⟦cb1⟧'));
assertCodeTrue('Code met een placeholder-teken gaat niet mee', !str_contains($maskedPair['text'], 'echo 1'));
assertCode(
    'Unmask leest een placeholder in de code niet opnieuw',
    $withPlaceholderInCode,
    unmaskMessageFencedCodeBlocks($maskedPair['text'], $maskedPair['tokens'])
);

$open = "Dit is geen hek:\n```sql\nSELECT 1";
$maskedOpen = maskMessageFencedCodeBlocks($open);
assertCode('Onvoltooid hek blijft gewone tekst', $open, $maskedOpen['text']);
assertCode('Onvoltooid hek krijgt geen token', [], $maskedOpen['tokens']);

$rendered = formatTicketMessageText("Voorbeeld:\n```js\nconst x = '<script>';\n```");
assertCodeTrue('Codehek rendert een taalclass', str_contains($rendered, '<code class="language-js">'));
assertCodeTrue('Codehek blijft geëscaped', str_contains($rendered, '&lt;script&gt;'));
assertCodeTrue('Server rendert geen highlight-HTML', !str_contains($rendered, 'hljs'));
assertCodeTrue('Server voert het script niet uit', !str_contains($rendered, '<script>'));

$plainFence = formatTicketMessageText("```\ngewone tekst\n```");
assertCodeTrue('Hek zonder taal krijgt geen language-class', str_contains($plainFence, '<pre class="message-md-pre"><code>'));
assertCodeTrue('Hek zonder taal blijft zonder language-', !str_contains($plainFence, 'language-'));

$al = formatTicketMessageText("```al\nprocedure Foo()\nbegin\nend;\n```");
assertCodeTrue('AL krijgt language-al', str_contains($al, 'class="language-al"'));
assertCodeTrue('AL-code blijft letterlijk', str_contains($al, 'procedure Foo()'));

$translated = "The query is:\n```sql\nSELECT name FROM customers;\n```\nDone.";
$restored = restoreMessageFencedCodeBlocks($translated, $raw);
assertCodeTrue('Vertaalde SQL wordt de bronquery', str_contains($restored, 'SELECT naam FROM klanten;'));
assertCodeTrue('Vertaalde omringende tekst blijft', str_contains($restored, 'The query is:') && str_contains($restored, 'Done.'));
assertCodeTrue('Vertaalde SQL is weg', !str_contains($restored, 'customers'));
assertCode('Herstel een tweede keer verandert niets', $restored, restoreMessageFencedCodeBlocks($restored, $raw));

$dropped = restoreMessageFencedCodeBlocks("The query is:\nDone.", $raw);
assertCodeTrue('Weggevallen hek komt terug', str_contains($dropped, "```sql\nSELECT naam FROM klanten;\n```"));
assertCodeTrue('Weggevallen hek staat maar één keer', substr_count($dropped, 'SELECT naam FROM klanten;') === 1);

$duplicateRaw = "Voor:\n```sql\nSELECT 1;\n```\nNa:\n```sql\nSELECT 1;\n```";
$duplicateDropped = restoreMessageFencedCodeBlocks("Before:\n```sql\nSELECT 1;\n```\nAfter:", $duplicateRaw);
assertCode(
    'Tweede identiek hek komt terug als de placeholder wegvalt',
    "Before:\n```sql\nSELECT 1;\n```\nAfter:\n\n```sql\nSELECT 1;\n```",
    $duplicateDropped
);
assertCode(
    'Herstel van twee identieke hekken blijft stabiel',
    $duplicateDropped,
    restoreMessageFencedCodeBlocks($duplicateDropped, $duplicateRaw)
);
$bothDropped = restoreMessageFencedCodeBlocks('Before: After.', $duplicateRaw);
assertCodeTrue('Beide identieke hekken komen terug', substr_count($bothDropped, "```sql\nSELECT 1;\n```") === 2);
$bothPresent = "Before:\n```sql\nSELECT 1;\n```\nAfter:\n```sql\nSELECT 1;\n```";
assertCode(
    'Twee aanwezige identieke hekken blijven twee',
    $bothPresent,
    restoreMessageFencedCodeBlocks($bothPresent, $duplicateRaw)
);

$applied = ticketTranslationApplyStatusNotes(
    ['text' => $translated, 'is_translated' => true],
    $raw,
    'en'
);
assertCodeTrue('Weergave houdt de bronquery', str_contains((string) $applied['text'], 'SELECT naam FROM klanten;'));
assertCodeTrue('Proza blijft een vertaling', !empty($applied['is_translated']));

$codeOnly = "```php\necho 'hoi';\n```";
$codeOnlyApplied = ticketTranslationApplyStatusNotes(
    ['text' => "```php\necho 'hello';\n```", 'is_translated' => true],
    $codeOnly,
    'en'
);
assertCode('Alleen een codeblok telt niet als vertaling', $codeOnly, $codeOnlyApplied['text']);
assertCodeTrue('Alleen een codeblok krijgt geen vertaalknop', empty($codeOnlyApplied['is_translated']));

$statusRaw = "Klaar.\n\n```php\necho 1;\n```\n\nStatus gewijzigd naar afgehandeld.";
$statusTranslated = "Done.\n\n```php\necho one;\n```\n\nStatus changed to resolved.";
$statusApplied = ticketTranslationApplyStatusNotes(
    ['text' => $statusTranslated, 'is_translated' => true],
    $statusRaw,
    'en'
);
assertCodeTrue('Code naast een statusregel blijft broncode', str_contains((string) $statusApplied['text'], 'echo 1;'));
assertCodeTrue('Statusregel blijft in de leestaal', str_contains((string) $statusApplied['text'], 'Status changed to '));

$mailBody = mailTextForRecipient($raw, $translated, 'en');
assertCodeTrue('Mail houdt de bronquery', str_contains($mailBody, 'SELECT naam FROM klanten;'));
assertCodeTrue('Mail vertaalt de zin wel', str_contains($mailBody, 'The query is:'));

echo PHP_EOL . 'Copilot' . PHP_EOL;

$calls = [];
$scripted = [];
$transport = static function (string $url, string $jsonBody, float $timeout) use (&$calls, &$scripted): array {
    $calls[] = ['body' => $jsonBody];
    $next = array_shift($scripted);
    return is_array($next) ? $next : ['status' => 500, 'body' => '', 'error' => 'geen script'];
};

$provider = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => json_encode([
        'source' => 'nl',
        'nl' => "De query is:\n⟦cb0⟧\nKlaar.",
        'en' => "The query is:\n⟦cb0⟧\nDone.",
        'fr' => "La requête est :\n⟦cb0⟧\nTerminé.",
        'de' => "Die Abfrage ist:\n⟦cb0⟧\nFertig.",
    ], JSON_UNESCAPED_UNICODE),
    'error' => '',
];
$copilot = $provider->translateText($raw, 'en');
$payload = json_decode((string) ($calls[0]['body'] ?? ''), true);
assertCodeTrue('Flow krijgt de SQL niet', is_array($payload) && !str_contains((string) ($payload['text'] ?? ''), 'SELECT'));
assertCodeTrue('Flow krijgt de placeholder', is_array($payload) && str_contains((string) ($payload['text'] ?? ''), '⟦cb0⟧'));
assertCode(
    'Copilot-vertaling houdt het hek',
    "The query is:\n```sql\nSELECT naam FROM klanten;\n```\nDone.",
    $copilot['translated_text']
);

$droppedProvider = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => json_encode([
        'source' => 'nl',
        'nl' => 'De query is: Klaar.',
        'en' => 'The query is: Done.',
        'fr' => 'La requête est terminée.',
        'de' => 'Die Abfrage ist fertig.',
    ], JSON_UNESCAPED_UNICODE),
    'error' => '',
];
$droppedCopilot = $droppedProvider->translateText($raw, 'en');
assertCodeTrue(
    'Weggevallen placeholder komt als bronhek terug',
    str_contains($droppedCopilot['translated_text'], "```sql\nSELECT naam FROM klanten;\n```")
);

$keyAndCode = "Druk op [Enter].\n```php\necho \"[Enter]\";\n```";
$keyProvider = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => json_encode([
        'source' => 'nl',
        'nl' => "Druk op ⟦kb0⟧.\n⟦cb0⟧",
        'en' => "Press ⟦kb0⟧.\n⟦cb0⟧",
        'fr' => "Appuyez sur ⟦kb0⟧.\n⟦cb0⟧",
        'de' => "Drücken Sie ⟦kb0⟧.\n⟦cb0⟧",
    ], JSON_UNESCAPED_UNICODE),
    'error' => '',
];
$keyed = $keyProvider->translateText($keyAndCode, 'en');
$keyPayload = json_decode((string) ($calls[count($calls) - 1]['body'] ?? ''), true);
assertCodeTrue('Toets in de code gaat niet naar de flow', is_array($keyPayload) && !str_contains((string) ($keyPayload['text'] ?? ''), 'echo'));
assertCode(
    'Toets buiten de code en codeblok komen allebei terug',
    "Press [Enter].\n```php\necho \"[Enter]\";\n```",
    $keyed['translated_text']
);

echo PHP_EOL . 'Lara' . PHP_EOL;

$laraClient = new class {
    public mixed $payload = null;

    public function translate(mixed $payload, mixed $source, string $target): object
    {
        $this->payload = $payload;

        return new class {
            public function getTranslation(): string
            {
                return "The query is:\n⟦cb0⟧\nDone.";
            }

            public function getSourceLanguage(): string
            {
                return 'nl-NL';
            }
        };
    }
};
$lara = new LaraTranslationProvider($laraClient);
$laraResult = $lara->translateText($raw, 'en');
assertCodeTrue('Lara krijgt de SQL niet', is_string($laraClient->payload) && !str_contains($laraClient->payload, 'SELECT'));
assertCodeTrue('Lara krijgt de placeholder', is_string($laraClient->payload) && str_contains($laraClient->payload, '⟦cb0⟧'));
assertCode(
    'Lara-vertaling houdt het hek',
    "The query is:\n```sql\nSELECT naam FROM klanten;\n```\nDone.",
    $laraResult['translated_text']
);

if (!class_exists('Lara\\TextBlock')) {
    class_exists('Lara\\TextBlock');
}

eval('namespace Lara; class TextBlock { public function __construct(public string $text, public bool $translatable) {} }');

$blockClient = new class {
    /** @var list<Lara\TextBlock>|string|null */
    public mixed $payload = null;

    public function translate(mixed $payload, mixed $source, string $target): object
    {
        $this->payload = $payload;
        $parts = [];
        if (is_array($payload)) {
            foreach ($payload as $block) {
                if ($block instanceof \Lara\TextBlock && !$block->translatable) {
                    $parts[] = $block->text;
                    continue;
                }
                $parts[] = $block instanceof \Lara\TextBlock ? 'Done ' : '';
            }
        }

        $translation = implode('', $parts);

        return new class($translation) {
            public function __construct(private string $translation)
            {
            }

            public function getTranslation(): string
            {
                return $this->translation;
            }

            public function getSourceLanguage(): string
            {
                return 'nl-NL';
            }
        };
    }
};
$blockLara = new LaraTranslationProvider($blockClient);
$blockRaw = "Kijk [Enter].\n```php\necho '<script>';\n```";
$blockResult = $blockLara->translateText($blockRaw, 'en');
assertCodeTrue('TextBlock-payload is een lijst', is_array($blockClient->payload));
$codeWasTranslatable = false;
$sawPlaceholder = false;
if (is_array($blockClient->payload)) {
    foreach ($blockClient->payload as $block) {
        if (!$block instanceof \Lara\TextBlock) {
            continue;
        }
        if (str_contains($block->text, 'echo') || str_contains($block->text, '<script>')) {
            $codeWasTranslatable = $codeWasTranslatable || $block->translatable;
        }
        if ($block->text === '⟦cb0⟧' && !$block->translatable) {
            $sawPlaceholder = true;
        }
    }
}
assertCodeTrue('Codeblok is geen vertaalbaar TextBlock', !$codeWasTranslatable && $sawPlaceholder);
assertCodeTrue('TextBlock-resultaat houdt de broncode', str_contains($blockResult['translated_text'], "echo '<script>';"));
assertCodeTrue('TextBlock-resultaat houdt de toets', str_contains($blockResult['translated_text'], '[Enter]'));

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
