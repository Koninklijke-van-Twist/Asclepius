<?php
/**
 * Adapter van de Copilot-flow: parser, één aanroep per tekst, Lara-foutgedrag
 * en fallback als flowUrl ontbreekt.
 */

echo "=== TEST: Copilot-vertaaladapter ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';
$_SESSION = [];

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/TicketStore.php';
require __DIR__ . '/../web/content/localization.php';
require __DIR__ . '/../web/content/TranslationProvider.php';
require __DIR__ . '/../web/content/LaraTranslationProvider.php';
require __DIR__ . '/../web/content/CoPilotTranslate.php';
require __DIR__ . '/../web/content/CopilotTranslationProvider.php';
require __DIR__ . '/../web/content/translation.php';

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function assertCopilot(string $label, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "  ✗ {$label}" . PHP_EOL;
    echo "      Verwacht : " . var_export($expected, true) . PHP_EOL;
    echo "      Gekregen : " . var_export($actual, true) . PHP_EOL;
    $failed++;
}

function assertCopilotTrue(string $label, bool $condition): void
{
    assertCopilot($label, true, $condition);
}

function copilotBundle(string $source, array $translations): string
{
    return json_encode(
        ['source' => $source] + $translations,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

function resetCopilotProviderCache(): void
{
    $GLOBALS['__translationProviderCache'] = null;
}

echo "Parser" . PHP_EOL;

$fenced = "Hier is het antwoord:\n```json\n" . copilotBundle('fr', [
    'nl' => 'De printer werkt niet',
    'en' => 'The printer does not work',
    'fr' => "L'imprimante ne fonctionne pas",
    'de' => 'Der Drucker funktioniert nicht',
]) . "\n```\nKlaar.";
$parsedFence = cpt_parse_translations($fenced);
assertCopilotTrue('Fence en rommel eromheen', is_array($parsedFence));
assertCopilot('Nederlandse tekst uit fence', 'De printer werkt niet', (string) ($parsedFence['translations']['nl'] ?? ''));

$wrapped = json_encode([
    'text' => copilotBundle('en-GB', [
        'nl' => 'Hallo',
        'en' => 'Hello',
        'fr' => 'Bonjour',
        'de' => 'Hallo',
    ]),
], JSON_UNESCAPED_UNICODE);
$parsedWrapped = cpt_parse_translations((string) $wrapped);
assertCopilot('Ingepakt antwoord, bron en-GB', 'en', $parsedWrapped['source'] ?? null);

$incomplete = cpt_parse_translations(copilotBundle('nl', [
    'nl' => 'Alleen nl',
    'en' => 'Only en',
]));
assertCopilot('Onvolledig antwoord is ongeldig', null, $incomplete);

echo PHP_EOL . "Adapter" . PHP_EOL;

$calls = [];
$scripted = [];
$transport = static function (string $url, string $jsonBody, float $timeout) use (&$calls, &$scripted): array {
    $calls[] = [
        'url' => $url,
        'body' => $jsonBody,
        'timeout' => $timeout,
    ];
    $next = array_shift($scripted);
    if (!is_array($next)) {
        return ['status' => 500, 'body' => 'geen script', 'error' => ''];
    }

    return $next;
};

$provider = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$raw = 'The printer does not work';
$scripted[] = [
    'status' => 200,
    'body' => "```json\n" . copilotBundle('nl', [
        'nl' => 'De printer werkt niet',
        'en' => 'The printer does not work',
        'fr' => "L'imprimante ne fonctionne pas",
        'de' => 'Der Drucker funktioniert nicht',
    ]) . "\n```",
    'error' => '',
];

$dutch = $provider->translateText($raw, 'nl');
assertCopilot('Doeltaal nl gebruikt de nl-tekst', 'De printer werkt niet', $dutch['translated_text']);
assertCopilot('Foute flow-source nl wordt genegeerd', 'en', $dutch['source_language']);
assertCopilot('Eén HTTP-aanroep', 1, count($calls));

$payload = json_decode($calls[0]['body'], true);
assertCopilotTrue('Payload is JSON', is_array($payload));
assertCopilot('Payload-sleutels', ['id', 'text', 'languages'], array_keys((array) $payload));
assertCopilot('Talen in de aanroep', ['nl', 'en', 'fr', 'de'], $payload['languages'] ?? null);
assertCopilot('Id is de hash van de tekst', hash('sha256', $raw), $payload['id'] ?? null);
assertCopilot('Timeout blijft de gevraagde 25s', 25.0, $calls[0]['timeout']);

$french = $provider->translateText($raw, 'fr');
assertCopilot('Tweede taal hergebruikt de run', "L'imprimante ne fonctionne pas", $french['translated_text']);
assertCopilot('Nog steeds één HTTP-aanroep', 1, count($calls));
assertCopilot('Brontaal blijft en', 'en', $french['source_language']);

$alreadyDutch = 'De printer werkt niet';
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'De printer werkt niet',
        'en' => 'The printer does not work',
        'fr' => "L'imprimante ne fonctionne pas",
        'de' => 'Der Drucker funktioniert nicht',
    ]),
    'error' => '',
];
$sameLanguage = $provider->translateText($alreadyDutch, 'nl');
assertCopilot('Tekst die al Nederlands is houdt bron nl', 'nl', $sameLanguage['source_language']);
assertCopilot('Tweede tekst is een nieuwe run', 2, count($calls));

$paraphrased = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'De printer werkt niet',
        'en' => "The printer isn't working",
        'fr' => "L'imprimante ne marche pas",
        'de' => 'Der Drucker geht nicht',
    ]),
    'error' => '',
];
$paraphraseResult = $paraphrased->translateText('The printer does not work', 'nl');
assertCopilot('Herschreven brontekst blijft een vertaling', 'De printer werkt niet', $paraphraseResult['translated_text']);
assertCopilot('Zwakke overlap verzint geen brontaal', '', $paraphraseResult['source_language']);

$loanwordProvider = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Printer niet bereikbaar',
        'en' => 'Printer is offline',
        'fr' => 'Imprimante hors ligne',
        'de' => 'Drucker offline',
    ]),
    'error' => '',
];
$loanwordResult = $loanwordProvider->translateText('Printer offline', 'nl');
assertCopilot('Gedeeld leenwoord houdt de vertaling', 'Printer niet bereikbaar', $loanwordResult['translated_text']);
assertCopilot('Gedeeld leenwoord wordt niet de leestaal', '', $loanwordResult['source_language']);

$sameLanguageParaphrase = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'De printer functioneert niet',
        'en' => 'The printer does not work',
        'fr' => "L'imprimante ne fonctionne pas",
        'de' => 'Der Drucker funktioniert nicht',
    ]),
    'error' => '',
];
$sameLanguageParaphraseResult = $sameLanguageParaphrase->translateText('De printer werkt niet', 'nl');
assertCopilot('Herschreven doeltaal is niet exact gelijk', '', $sameLanguageParaphraseResult['source_language']);
assertCopilot('Herschreven doeltaal wordt wel gebruikt', 'De printer functioneert niet', $sameLanguageParaphraseResult['translated_text']);

$withKey = 'Druk op [Enter] om door te gaan';
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('de', [
        'nl' => 'Druk op ⟦kb0⟧ om door te gaan',
        'en' => 'Press ⟦kb0⟧ to continue',
        'fr' => 'Appuyez sur ⟦kb0⟧ pour continuer',
        'de' => 'Drücken Sie ⟦kb0⟧ um fortzufahren',
    ]),
    'error' => '',
];
$keyed = $provider->translateText($withKey, 'en');
assertCopilot('Toetsnaam blijft staan', 'Press [Enter] to continue', $keyed['translated_text']);
$keyPayload = json_decode($calls[2]['body'], true);
assertCopilotTrue('Ruwe toetsnaam gaat niet naar de flow', is_array($keyPayload) && !str_contains((string) $keyPayload['text'], '[Enter]'));

$filtered = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = [
    'status' => 400,
    'body' => '{"error":{"code":"ResponseContentFiltered","message":"blocked"}}',
    'error' => '',
];
$filterThrew = false;
$filterMessage = '';
try {
    $filtered->translateText('geblokkeerde tekst', 'de');
} catch (RuntimeException $exception) {
    $filterThrew = true;
    $filterMessage = $exception->getMessage();
}
assertCopilotTrue('Filter geeft een exception', $filterThrew);
assertCopilotTrue('Filterbericht bevat ResponseContentFiltered', str_contains($filterMessage, 'ResponseContentFiltered'));
$callsBeforeRetry = count($calls);
$secondFilterThrew = false;
try {
    $filtered->translateText('geblokkeerde tekst', 'fr');
} catch (RuntimeException) {
    $secondFilterThrew = true;
}
assertCopilotTrue('Tweede taal na filter gooit opnieuw', $secondFilterThrew);
assertCopilot('Tweede taal na filter start geen run', $callsBeforeRetry, count($calls));

$timedOut = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = ['status' => 0, 'body' => '', 'error' => 'Operation timed out'];
$timeoutMessage = '';
try {
    $timedOut->translateText('trage tekst', 'nl');
} catch (RuntimeException $exception) {
    $timeoutMessage = $exception->getMessage();
}
assertCopilotTrue('Timeout wordt een exception', str_contains($timeoutMessage, 'timed out'));

$garbage = new CopilotTranslationProvider('https://example.invalid/flow', 25.0, $transport);
$scripted[] = ['status' => 200, 'body' => 'Geen JSON, alleen tekst.', 'error' => ''];
$garbageThrew = false;
try {
    $garbage->translateText('onleesbaar', 'nl');
} catch (RuntimeException $exception) {
    $garbageThrew = str_contains($exception->getMessage(), 'onleesbaar antwoord');
}
assertCopilotTrue('Ongeldig antwoord wordt een exception', $garbageThrew);

$previousMax = (string) ini_get('max_execution_time');
$previousRequestStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
ini_set('max_execution_time', '30');
$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
$capped = new CopilotTranslationProvider('https://example.invalid/flow', 60.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'Kort',
        'en' => 'Short',
        'fr' => 'Court',
        'de' => 'Kurz',
    ]),
    'error' => '',
];
$capped->translateText('Short', 'nl');
$freshTimeout = (float) $calls[count($calls) - 1]['timeout'];
assertCopilotTrue('Timeout blijft onder max_execution_time', $freshTimeout <= 25.0 && $freshTimeout > 24.0);

$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 20;
$later = new CopilotTranslationProvider('https://example.invalid/flow', 60.0, $transport);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'Later',
        'en' => 'Later text',
        'fr' => 'Plus tard',
        'de' => 'Spaeter',
    ]),
    'error' => '',
];
$later->translateText('Later text', 'nl');
$laterTimeout = (float) $calls[count($calls) - 1]['timeout'];
assertCopilotTrue('Timeout trekt al verbruikte tijd af', $laterTimeout > 4.5 && $laterTimeout < 5.5);

$callsBeforeBudget = count($calls);
$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 28;
$outOfTime = new CopilotTranslationProvider('https://example.invalid/flow', 60.0, $transport);
$outOfTimeThrew = false;
try {
    $outOfTime->translateText('Geen tijd meer', 'de');
} catch (RuntimeException $exception) {
    $outOfTimeThrew = $exception->getMessage() === CopilotTranslationProvider::BUDGET_EXHAUSTED;
}
assertCopilotTrue('Te weinig resterende tijd slaat de call over', $outOfTimeThrew);
assertCopilot('Te weinig resterende tijd start geen run', $callsBeforeBudget, count($calls));
ini_set('max_execution_time', $previousMax);
if ($previousRequestStart === null) {
    unset($_SERVER['REQUEST_TIME_FLOAT']);
} else {
    $_SERVER['REQUEST_TIME_FLOAT'] = $previousRequestStart;
}

$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'Standaard',
        'en' => 'Default timeout text',
        'fr' => 'Defaut',
        'de' => 'Standard',
    ]),
    'error' => '',
];
$defaultProvider = new CopilotTranslationProvider('https://example.invalid/flow', null, $transport);
$defaultProvider->translateText('Default timeout text', 'nl');
assertCopilot('Standaardtimeout is 120 seconden', 120.0, (float) $calls[count($calls) - 1]['timeout']);

ini_set('max_execution_time', '300');
$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'Ruim',
        'en' => 'Wide budget text',
        'fr' => 'Large',
        'de' => 'Weit',
    ]),
    'error' => '',
];
$wideBudget = new CopilotTranslationProvider('https://example.invalid/flow', null, $transport);
$wideBudget->translateText('Wide budget text', 'nl');
assertCopilot('300 seconden kapt de standaardtimeout niet af', 120.0, (float) $calls[count($calls) - 1]['timeout']);
ini_set('max_execution_time', $previousMax);
if ($previousRequestStart === null) {
    unset($_SERVER['REQUEST_TIME_FLOAT']);
} else {
    $_SERVER['REQUEST_TIME_FLOAT'] = $previousRequestStart;
}

echo PHP_EOL . "Config en bestaande opslag" . PHP_EOL;

$apiSource = (string) file_get_contents(__DIR__ . '/../web/api.php');
$jsSource = (string) file_get_contents(__DIR__ . '/../web/content/views/page_js.php');
assertCopilotTrue(
    'translate_ticket krijgt 300 seconden',
    str_contains($apiSource, 'set_time_limit(300)')
);
assertCopilotTrue(
    'translate_ticket geeft pending door',
    str_contains($apiSource, "'translation_pending'") && str_contains($apiSource, "'title_translation_pending'")
);
assertCopilotTrue(
    'De pagina laat een pending bericht staan en vraagt opnieuw',
    str_contains($jsSource, 'msg.translation_pending')
        && str_contains($jsSource, 'TRANSLATION_RETRY_MAX = 10')
        && str_contains($jsSource, 'TRANSLATION_RETRY_DELAYS_MS = [5000, 15000, 30000, 60000]')
        && str_contains($jsSource, 'triggerLazyTranslations(currentCard)')
        && str_contains($jsSource, 'data-translation-retry-due')
        && str_contains($jsSource, 'data-translation-dirty')
        && str_contains($jsSource, 'data-translation-awaiting-trigger')
        && str_contains($jsSource, 'resumePausedTranslations')
        && str_contains($jsSource, 'TRANSLATION_PAUSE_COOLDOWN_MS = 300000')
        && str_contains($jsSource, 'armTranslationPauseCooldown')
        && !preg_match('/if\s*\(\s*!data\s*\|\|\s*data\.unchanged\s*\)\s*\{[^}]*resumePausedTranslations/s', $jsSource)
        && str_contains($jsSource, 'settlePendingTranslationDisplay')
        && str_contains($jsSource, 'collectTicketCards')
        && str_contains($jsSource, 'cardHasPendingTranslation')
        && str_contains($jsSource, 'translationRequestsInFlight[ticketId]')
        && str_contains($jsSource, 'document.hidden')
);

resetCopilotProviderCache();
$GLOBALS['copilotTranslate'] = null;
unset($GLOBALS['laraTranslate']);
assertCopilot('Zonder config geen provider', null, createTranslationProvider());

resetCopilotProviderCache();
$GLOBALS['copilotTranslate'] = ['flowUrl' => 'http://example.invalid/flow'];
assertCopilot('http-adres valt terug', null, createTranslationProvider());

resetCopilotProviderCache();
$GLOBALS['copilotTranslate'] = ['flowUrl' => ''];
assertCopilot('Lege flowUrl valt terug', null, createTranslationProvider());

CopilotTranslationProvider::useTransportForTests($transport);
resetCopilotProviderCache();
$GLOBALS['copilotTranslate'] = [
    'flowUrl' => 'https://example.invalid/flow',
    'timeout' => 12,
];
$selected = createTranslationProvider();
assertCopilotTrue('https flowUrl kiest Copilot', $selected instanceof CopilotTranslationProvider);

$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_uploads_copilot_translate',
    ['ict@kvt.nl'],
    TICKET_CATEGORIES
);
$created = $store->createTicket(
    'Printer stuk',
    'hardware bestellen',
    'user@kvt.nl',
    'The printer does not work',
    []
);
$ticketId = (int) ($created['ticket_id'] ?? 0);
$ticket = $store->getTicket($ticketId, true, 'ict@kvt.nl');
$messageId = (int) (($ticket['messages'][0]['id'] ?? 0));
$callsBeforeStore = count($calls);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'De printer werkt niet',
        'en' => 'The printer does not work',
        'fr' => "L'imprimante ne fonctionne pas",
        'de' => 'Der Drucker funktioniert nicht',
    ]),
    'error' => '',
];

$storedDutch = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $messageId,
    $ticketId,
    'The printer does not work',
    'nl'
);
assertCopilot('Bestaande regel toont de Nederlandse tekst', 'De printer werkt niet', $storedDutch['text']);
assertCopilot('Bestaande regel markeert een vertaling', true, $storedDutch['is_translated']);
assertCopilot('Opgeslagen brontaal is niet de foute flow-source', 'en', $storedDutch['source_language']);
assertCopilot('Opslag kost één run', $callsBeforeStore + 1, count($calls));
$cached = $store->getTextTranslation('ticket_message', $messageId, 'nl', hash('sha256', 'The printer does not work'));
assertCopilot('Vertaling staat in de bestaande cache', 'De printer werkt niet', (string) ($cached['translated_text'] ?? ''));
$cachedEnglish = $store->getTextTranslation('ticket_message', $messageId, 'en', hash('sha256', 'The printer does not work'));
$cachedGerman = $store->getTextTranslation('ticket_message', $messageId, 'de', hash('sha256', 'The printer does not work'));
assertCopilot('Engelse tekst uit hetzelfde antwoord staat meteen in de cache', 'The printer does not work', (string) ($cachedEnglish['translated_text'] ?? ''));
assertCopilot('Duitse tekst uit hetzelfde antwoord staat meteen in de cache', 'Der Drucker funktioniert nicht', (string) ($cachedGerman['translated_text'] ?? ''));

$storedFrench = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $messageId,
    $ticketId,
    'The printer does not work',
    'fr'
);
assertCopilot('Franse tekst uit dezelfde run', "L'imprimante ne fonctionne pas", $storedFrench['text']);
assertCopilot('Tweede doeltaal kost geen extra run', $callsBeforeStore + 1, count($calls));
$cachedFrench = $store->getTextTranslation('ticket_message', $messageId, 'fr', hash('sha256', 'The printer does not work'));
assertCopilot('Franse vertaling apart opgeslagen', "L'imprimante ne fonctionne pas", (string) ($cachedFrench['translated_text'] ?? ''));

$callsBeforeFilter = count($calls);
$filterMessageId = $store->addMessage($ticketId, 'user@kvt.nl', 'user', 'Dit mag de flow niet zien');
$scripted[] = [
    'status' => 400,
    'body' => '{"error":{"code":"ResponseContentFiltered"}}',
    'error' => '',
];
$filterResult = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $filterMessageId,
    $ticketId,
    'Dit mag de flow niet zien',
    'de'
);
assertCopilot('Filter gedraagt zich als een Lara-fout', 'provider_error', $filterResult['translation_error']);
assertCopilot('Bij een fout blijft de oorspronkelijke tekst', 'Dit mag de flow niet zien', $filterResult['text']);
assertCopilotTrue(
    'Foutdetail noemt het filter',
    str_contains((string) ($filterResult['translation_error_detail'] ?? ''), 'ResponseContentFiltered')
);
assertCopilot(
    'Een fout wordt niet gecachet als vertaling',
    null,
    $store->getTextTranslation('ticket_message', $filterMessageId, 'de', hash('sha256', 'Dit mag de flow niet zien'))
);
assertCopilot('Filter kost één run', $callsBeforeFilter + 1, count($calls));

$callsBeforeTitle = count($calls);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'Printer stuk',
        'en' => 'Printer broken',
        'fr' => 'Imprimante en panne',
        'de' => 'Drucker kaputt',
    ]),
    'error' => '',
];
$sameLanguageStored = translateTicketTextForViewer(
    $store,
    'ticket_title',
    $ticketId,
    $ticketId,
    'Printer stuk',
    'nl'
);
assertCopilot('Titel die al in de doeltaal staat blijft het origineel', 'Printer stuk', $sameLanguageStored['text']);
assertCopilot('Geen vertaalmarkering als de tekst al in de doeltaal is', false, $sameLanguageStored['is_translated']);
assertCopilot('Geen fout als de tekst al in de doeltaal staat', '', $sameLanguageStored['translation_error']);
assertCopilot('Titelvertaling is wel opgehaald', $callsBeforeTitle + 1, count($calls));

$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Printer niet bereikbaar',
        'en' => 'Printer is offline',
        'fr' => 'Imprimante hors ligne',
        'de' => 'Drucker offline',
    ]),
    'error' => '',
];
$loanwordMessageId = $store->addMessage($ticketId, 'user@kvt.nl', 'user', 'Printer offline');
$loanwordStored = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $loanwordMessageId,
    $ticketId,
    'Printer offline',
    'nl'
);
assertCopilot('Leenwoord bewaart de vertaling', 'Printer niet bereikbaar', $loanwordStored['text']);
assertCopilot('Leenwoord is geen fout', '', $loanwordStored['translation_error']);
$loanwordCache = $store->getTextTranslation('ticket_message', $loanwordMessageId, 'nl', hash('sha256', 'Printer offline'));
assertCopilot('Cache bevat de vertaling en niet het origineel', 'Printer niet bereikbaar', (string) ($loanwordCache['translated_text'] ?? ''));
assertCopilot('Cache zet de leestaal niet als bron', '', (string) ($loanwordCache['source_language'] ?? 'x'));
$loanwordEnglish = $store->getTextTranslation('ticket_message', $loanwordMessageId, 'en', hash('sha256', 'Printer offline'));
assertCopilot('Ook de andere talen van het leenwoord-antwoord zijn bewaard', 'Printer is offline', (string) ($loanwordEnglish['translated_text'] ?? ''));

$callsBeforeSkip = count($calls);
$skipMax = (string) ini_get('max_execution_time');
$skipStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
ini_set('max_execution_time', '30');
$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 28;
$retryMessageId = $store->addMessage($ticketId, 'user@kvt.nl', 'user', 'Nog een zin om te vertalen');
$skipped = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $retryMessageId,
    $ticketId,
    'Nog een zin om te vertalen',
    'nl'
);
assertCopilot('Zonder resterende tijd blijft de tekst pending', true, $skipped['translation_pending']);
assertCopilot('Overslaan is geen fout', '', $skipped['translation_error']);
assertCopilot('Overslaan bewaart het origineel in het antwoord', 'Nog een zin om te vertalen', $skipped['text']);
assertCopilot('Overslaan start geen run', $callsBeforeSkip, count($calls));
assertCopilot(
    'Overslaan wordt niet opgeslagen',
    null,
    $store->getTextTranslation('ticket_message', $retryMessageId, 'nl', hash('sha256', 'Nog een zin om te vertalen'))
);
ini_set('max_execution_time', $skipMax);
if ($skipStart === null) {
    unset($_SERVER['REQUEST_TIME_FLOAT']);
} else {
    $_SERVER['REQUEST_TIME_FLOAT'] = $skipStart;
}
resetCopilotProviderCache();
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('en', [
        'nl' => 'Nog een zin die vertaald is',
        'en' => 'Nog een zin om te vertalen',
        'fr' => 'Encore une phrase',
        'de' => 'Noch ein Satz',
    ]),
    'error' => '',
];
$retried = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $retryMessageId,
    $ticketId,
    'Nog een zin om te vertalen',
    'nl'
);
assertCopilot('Later verzoek haalt de vertaling alsnog op', 'Nog een zin die vertaald is', $retried['text']);
assertCopilot('Later verzoek is niet pending', false, $retried['translation_pending']);
assertCopilot('Later verzoek kost alsnog een run', $callsBeforeSkip + 1, count($calls));

echo PHP_EOL . "Alle talen, lock en achtergrond" . PHP_EOL;

assertCopilot('Verzoek noemt elke ondersteunde taal', ['nl', 'en', 'fr', 'de'], cpt_request_languages());
$translationSource = (string) file_get_contents(__DIR__ . '/../web/content/translation.php');
assertCopilotTrue(
    'Achtergrond sluit het antwoord vóór de flow',
    str_contains($translationSource, 'fastcgi_finish_request')
        && str_contains($translationSource, 'finishHttpResponseForBackgroundWork')
);
assertCopilot('CLI sluit het antwoord niet vroeg', false, finishHttpResponseForBackgroundWork());

discardQueuedTicketTranslations();
$lockMessageId = $store->addMessage($ticketId, 'user@kvt.nl', 'user', 'Tekst achter de lock');
$lockHash = hash('sha256', 'Tekst achter de lock');
$lockToken = $store->tryClaimTextTranslation('ticket_message', $lockMessageId, $lockHash, 60);
assertCopilotTrue('Eerste claim lukt', is_string($lockToken) && $lockToken !== '');
$secondClaim = $store->tryClaimTextTranslation('ticket_message', $lockMessageId, $lockHash, 60);
assertCopilot('Zelfde tekst krijgt geen tweede claim', null, $secondClaim);
$callsBeforeLock = count($calls);
$lockedResult = translateTicketTextForViewer($store, 'ticket_message', $lockMessageId, $ticketId, 'Tekst achter de lock', 'de');
assertCopilot('Lopende aanvraag blijft pending', true, $lockedResult['translation_pending']);
assertCopilot('Lopende aanvraag start geen tweede run', $callsBeforeLock, count($calls));
$store->releaseTextTranslationClaim('ticket_message', $lockMessageId, (string) $lockToken);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Tekst achter de lock',
        'en' => 'Text behind the lock',
        'fr' => 'Texte derrière le verrou',
        'de' => 'Text hinter der Sperre',
    ]),
    'error' => '',
];
$unlocked = translateTicketTextForViewer($store, 'ticket_message', $lockMessageId, $ticketId, 'Tekst achter de lock', 'de');
assertCopilot('Na de lock komt de Duitse tekst', 'Text hinter der Sperre', $unlocked['text']);
assertCopilot('Na de lock is het niet pending', false, $unlocked['translation_pending']);
assertCopilot('Na de lock één run', $callsBeforeLock + 1, count($calls));
$unlockedFrench = $store->getTextTranslation('ticket_message', $lockMessageId, 'fr', $lockHash);
assertCopilot('De lock-run bewaart ook Frans', 'Texte derrière le verrou', (string) ($unlockedFrench['translated_text'] ?? ''));

$takeoverA = hash('sha256', 'versie-a');
$takeoverB = hash('sha256', 'versie-b');
$firstToken = $store->tryClaimTextTranslation('ticket_message', 4242, $takeoverA, 10, 1000);
assertCopilotTrue('Claim op een vast tijdstip', is_string($firstToken));
assertCopilot('Verse claim blijft van de eerste', null, $store->tryClaimTextTranslation('ticket_message', 4242, $takeoverA, 10, 1005));
$taken = $store->tryClaimTextTranslation('ticket_message', 4242, $takeoverB, 10, 1006);
assertCopilotTrue('Andere tekst neemt de lock over', is_string($taken) && $taken !== $firstToken);
$stale = $store->tryClaimTextTranslation('ticket_message', 4242, $takeoverB, 10, 1020);
assertCopilotTrue('Verlopen claim mag opnieuw', is_string($stale) && $stale !== $taken);
$store->releaseTextTranslationClaim('ticket_message', 4242, (string) $stale);

$liveEditId = $store->addMessage($ticketId, 'user@kvt.nl', 'user', '[ ] Live');
$liveHash = hash('sha256', '[ ] Live');
$liveToken = $store->tryClaimTextTranslation('ticket_message', $liveEditId, $liveHash, 60);
$store->updateTicketMessageCheckboxState($ticketId, $liveEditId, 0, true, true, 'ict@kvt.nl');
$savedStale = $store->saveTextTranslationsIfClaimed(
    'ticket_message',
    $liveEditId,
    $ticketId,
    $liveHash,
    (string) $liveToken,
    'nl',
    ['nl' => 'oude vertaling']
);
assertCopilot('Bewerkte tekst weigert de oude vertaling', false, $savedStale);
assertCopilot(
    'Oude hash staat niet in de cache',
    null,
    $store->getTextTranslation('ticket_message', $liveEditId, 'nl', $liveHash)
);
$store->releaseTextTranslationClaim('ticket_message', $liveEditId, (string) $liveToken);

discardQueuedTicketTranslations();
$callsBeforeCreate = count($calls);
$backgroundCreated = $store->createTicket(
    'Nieuwe titel',
    'hardware bestellen',
    'user@kvt.nl',
    'Eerste bericht',
    []
);
$backgroundTicketId = (int) ($backgroundCreated['ticket_id'] ?? 0);
$backgroundTicket = $store->getTicket($backgroundTicketId, true, 'ict@kvt.nl');
$backgroundMessageId = (int) (($backgroundTicket['messages'][0]['id'] ?? 0));
assertCopilot('Aanmaken wacht niet op de flow', $callsBeforeCreate, count($calls));
assertCopilot(
    'Wachtrij bevat titel en eerste bericht',
    ['ticket_title:' . $backgroundTicketId, 'ticket_message:' . $backgroundMessageId],
    queuedTicketTranslationKeys()
);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Nieuwe titel',
        'en' => 'New title',
        'fr' => 'Nouveau titre',
        'de' => 'Neuer Titel',
    ]),
    'error' => '',
];
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Eerste bericht',
        'en' => 'First message',
        'fr' => 'Premier message',
        'de' => 'Erste Nachricht',
    ]),
    'error' => '',
];
flushQueuedTicketTranslations();
assertCopilot('Achtergrond vertaalt titel en bericht', $callsBeforeCreate + 2, count($calls));
assertCopilot(
    'Titel in het Engels staat klaar',
    'New title',
    (string) ($store->getTextTranslation('ticket_title', $backgroundTicketId, 'en', hash('sha256', 'Nieuwe titel'))['translated_text'] ?? '')
);
assertCopilot(
    'Bericht in het Frans staat klaar',
    'Premier message',
    (string) ($store->getTextTranslation('ticket_message', $backgroundMessageId, 'fr', hash('sha256', 'Eerste bericht'))['translated_text'] ?? '')
);
flushQueuedTicketTranslations();
assertCopilot('Tweede flush kost geen run', $callsBeforeCreate + 2, count($calls));

$callsBeforeReply = count($calls);
$replyId = $store->addMessage($backgroundTicketId, 'user@kvt.nl', 'user', 'Tweede bericht');
assertCopilot('Een nieuw bericht wacht niet op de flow', $callsBeforeReply, count($calls));
assertCopilotTrue('Nieuw bericht staat in de wachtrij', in_array('ticket_message:' . $replyId, queuedTicketTranslationKeys(), true));
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Tweede bericht',
        'en' => 'Second message',
        'fr' => 'Deuxième message',
        'de' => 'Zweite Nachricht',
    ]),
    'error' => '',
];
flushQueuedTicketTranslations();
assertCopilot('Achtergrond vertaalt het nieuwe bericht', $callsBeforeReply + 1, count($calls));
assertCopilot(
    'Nieuw bericht in het Duits staat klaar',
    'Zweite Nachricht',
    (string) ($store->getTextTranslation('ticket_message', $replyId, 'de', hash('sha256', 'Tweede bericht'))['translated_text'] ?? '')
);

$callsBeforeGhost = count($calls);
$ghostId = $store->addMessage($backgroundTicketId, 'ict@kvt.nl', 'admin', 'Concept oud', [], true);
assertCopilot('Ghost komt niet in de wachtrij', false, in_array('ticket_message:' . $ghostId, queuedTicketTranslationKeys(), true));
flushQueuedTicketTranslations();
assertCopilot('Ghost start geen run', $callsBeforeGhost, count($calls));
$store->upsertTextTranslation('ticket_message', $ghostId, $backgroundTicketId, 'en', 'nl', 'hash-oud', 'Oud');
$store->publishGhostMessage($ghostId, 'Nu zichtbaar');
assertCopilot(
    'Publiceren met andere tekst wist de oude vertaling',
    null,
    $store->getTextTranslation('ticket_message', $ghostId, 'en', 'hash-oud')
);
assertCopilotTrue('Gepubliceerd bericht staat in de wachtrij', in_array('ticket_message:' . $ghostId, queuedTicketTranslationKeys(), true));
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Nu zichtbaar',
        'en' => 'Now visible',
        'fr' => 'Maintenant visible',
        'de' => 'Jetzt sichtbar',
    ]),
    'error' => '',
];
flushQueuedTicketTranslations();
assertCopilot('Publiceren vertaalt daarna', $callsBeforeGhost + 1, count($calls));
assertCopilot(
    'Nieuwe ghost-tekst in het Engels',
    'Now visible',
    (string) ($store->getTextTranslation('ticket_message', $ghostId, 'en', hash('sha256', 'Nu zichtbaar'))['translated_text'] ?? '')
);

$sameGhostId = $store->addMessage($backgroundTicketId, 'ict@kvt.nl', 'admin', 'Blijft gelijk', [], true);
$sameGhostHash = hash('sha256', 'Blijft gelijk');
$store->upsertTextTranslation('ticket_message', $sameGhostId, $backgroundTicketId, 'en', 'nl', $sameGhostHash, 'Same text');
$store->publishGhostMessage($sameGhostId, 'Blijft gelijk');
assertCopilot(
    'Ongewijzigd publiceren houdt de vertaling',
    'Same text',
    (string) ($store->getTextTranslation('ticket_message', $sameGhostId, 'en', $sameGhostHash)['translated_text'] ?? '')
);
discardQueuedTicketTranslations();

$callsBeforeCheck = count($calls);
$checkId = $store->addMessage($backgroundTicketId, 'user@kvt.nl', 'user', '[ ] Punt');
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => '[ ] Punt',
        'en' => '[ ] Point',
        'fr' => '[ ] Point',
        'de' => '[ ] Punkt',
    ]),
    'error' => '',
];
flushQueuedTicketTranslations();
assertCopilot('Checkbox-bericht is eerst vertaald', $callsBeforeCheck + 1, count($calls));
$checkHash = hash('sha256', '[ ] Punt');
assertCopilot(
    'Oude checkbox-tekst staat in de cache',
    '[ ] Point',
    (string) ($store->getTextTranslation('ticket_message', $checkId, 'en', $checkHash)['translated_text'] ?? '')
);
$checked = $store->updateTicketMessageCheckboxState($backgroundTicketId, $checkId, 0, true, true, 'ict@kvt.nl');
assertCopilot('Aangevinkte tekst', '[x] Punt', $checked);
assertCopilot('Oude checkbox-vertaling is weg', null, $store->getTextTranslation('ticket_message', $checkId, 'en', $checkHash));
$callsBeforeRecheck = count($calls);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => '[x] Punt',
        'en' => '[x] Point',
        'fr' => '[x] Point',
        'de' => '[x] Punkt',
    ]),
    'error' => '',
];
flushQueuedTicketTranslations();
assertCopilot('Checkbox vraagt opnieuw', $callsBeforeRecheck + 1, count($calls));
assertCopilot(
    'Nieuwe checkbox-tekst is vertaald',
    '[x] Point',
    (string) ($store->getTextTranslation('ticket_message', $checkId, 'en', hash('sha256', '[x] Punt'))['translated_text'] ?? '')
);

$callsBeforeTitleEdit = count($calls);
assertCopilotTrue('Titelwijziging lukt', $store->updateTicketTitle($backgroundTicketId, 'Andere titel'));
assertCopilot(
    'Oude titelvertaling is weg',
    null,
    $store->getTextTranslation('ticket_title', $backgroundTicketId, 'en', hash('sha256', 'Nieuwe titel'))
);
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Andere titel',
        'en' => 'Other title',
        'fr' => 'Autre titre',
        'de' => 'Anderer Titel',
    ]),
    'error' => '',
];
flushQueuedTicketTranslations();
assertCopilot('Titelwijziging vertaalt opnieuw', $callsBeforeTitleEdit + 1, count($calls));
assertCopilot(
    'Nieuwe titel in het Frans',
    'Autre titre',
    (string) ($store->getTextTranslation('ticket_title', $backgroundTicketId, 'fr', hash('sha256', 'Andere titel'))['translated_text'] ?? '')
);

$callsBeforeClock = count($calls);
$clockMax = (string) ini_get('max_execution_time');
$clockStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
ini_set('max_execution_time', '30');
$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 1000;
$clockId = $store->addMessage($backgroundTicketId, 'user@kvt.nl', 'user', 'Na de klok');
$scripted[] = [
    'status' => 200,
    'body' => copilotBundle('nl', [
        'nl' => 'Na de klok',
        'en' => 'After the clock',
        'fr' => 'Après l\'horloge',
        'de' => 'Nach der Uhr',
    ]),
    'error' => '',
];
flushQueuedTicketTranslations();
assertCopilot('Achtergrond negeert de verlopen requesttijd', $callsBeforeClock + 1, count($calls));
assertCopilot(
    'Tekst na de klok is vertaald',
    'After the clock',
    (string) ($store->getTextTranslation('ticket_message', $clockId, 'en', hash('sha256', 'Na de klok'))['translated_text'] ?? '')
);
ini_set('max_execution_time', $clockMax);
if ($clockStart === null) {
    unset($_SERVER['REQUEST_TIME_FLOAT']);
} else {
    $_SERVER['REQUEST_TIME_FLOAT'] = $clockStart;
}

$laraState = (object) ['calls' => 0, 'langs' => []];
$laraProvider = new class ($laraState) implements TranslationProvider {
    public function __construct(private object $state)
    {
    }

    public function translateText(string $rawText, string $targetAppLanguage): array
    {
        $this->state->calls++;
        $this->state->langs[] = strtolower(trim($targetAppLanguage));

        return [
            'translated_text' => 'L:' . $targetAppLanguage,
            'source_language' => 'en',
        ];
    }
};
$GLOBALS['__translationProviderCache'] = ['initialized' => true, 'provider' => $laraProvider];
$laraMessageId = $store->addMessage($backgroundTicketId, 'user@kvt.nl', 'user', 'Lara alles');
flushQueuedTicketTranslations();
assertCopilot('Lara-achtergrond vraagt elke taal', 4, $laraState->calls);
assertCopilot('Lara-volgorde', ['nl', 'en', 'fr', 'de'], $laraState->langs);
assertCopilot(
    'Lara bewaart de gevraagde taal',
    'L:nl',
    (string) ($store->getTextTranslation('ticket_message', $laraMessageId, 'nl', hash('sha256', 'Lara alles'))['translated_text'] ?? '')
);
assertCopilot(
    'Lara-bron blijft de oorspronkelijke tekst',
    'Lara alles',
    (string) ($store->getTextTranslation('ticket_message', $laraMessageId, 'en', hash('sha256', 'Lara alles'))['translated_text'] ?? '')
);
$laraState->calls = 0;
$laraState->langs = [];
$laraOneId = $store->addMessage($backgroundTicketId, 'user@kvt.nl', 'user', 'Lara een');
discardQueuedTicketTranslations();
$laraOne = translateTicketTextForViewer($store, 'ticket_message', $laraOneId, $backgroundTicketId, 'Lara een', 'fr');
assertCopilot('Lara op aanvraag doet één taal', 1, $laraState->calls);
assertCopilot('Lara op aanvraag is Frans', ['fr'], $laraState->langs);
assertCopilot('Lara-antwoord is de Franse tekst', 'L:fr', $laraOne['text']);
assertCopilot(
    'Lara slaat de andere talen niet mee op',
    null,
    $store->getTextTranslation('ticket_message', $laraOneId, 'de', hash('sha256', 'Lara een'))
);

CopilotTranslationProvider::useTransportForTests(null);
resetCopilotProviderCache();

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
