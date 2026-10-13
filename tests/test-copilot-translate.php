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
$scripted[] = [
    'status' => 400,
    'body' => '{"error":{"code":"ResponseContentFiltered"}}',
    'error' => '',
];
$filterResult = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $messageId,
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
    $store->getTextTranslation('ticket_message', $messageId, 'de', hash('sha256', 'Dit mag de flow niet zien'))
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
$loanwordStored = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $messageId,
    $ticketId,
    'Printer offline',
    'nl'
);
assertCopilot('Leenwoord bewaart de vertaling', 'Printer niet bereikbaar', $loanwordStored['text']);
assertCopilot('Leenwoord is geen fout', '', $loanwordStored['translation_error']);
$loanwordCache = $store->getTextTranslation('ticket_message', $messageId, 'nl', hash('sha256', 'Printer offline'));
assertCopilot('Cache bevat de vertaling en niet het origineel', 'Printer niet bereikbaar', (string) ($loanwordCache['translated_text'] ?? ''));
assertCopilot('Cache zet de leestaal niet als bron', '', (string) ($loanwordCache['source_language'] ?? 'x'));

$callsBeforeSkip = count($calls);
$skipMax = (string) ini_get('max_execution_time');
$skipStart = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
ini_set('max_execution_time', '30');
$_SERVER['REQUEST_TIME_FLOAT'] = microtime(true) - 28;
$skipped = translateTicketTextForViewer(
    $store,
    'ticket_message',
    $messageId,
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
    $store->getTextTranslation('ticket_message', $messageId, 'nl', hash('sha256', 'Nog een zin om te vertalen'))
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
    $messageId,
    $ticketId,
    'Nog een zin om te vertalen',
    'nl'
);
assertCopilot('Later verzoek haalt de vertaling alsnog op', 'Nog een zin die vertaald is', $retried['text']);
assertCopilot('Later verzoek is niet pending', false, $retried['translation_pending']);
assertCopilot('Later verzoek kost alsnog een run', $callsBeforeSkip + 1, count($calls));

CopilotTranslationProvider::useTransportForTests(null);
resetCopilotProviderCache();

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
