<?php
/**
 * Test dat TicketStore vertaalcaches per entiteit/taal opslaat en invalideert op source-hash.
 */

echo "=== TEST: TicketStore vertaalcache ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/TicketStore.php';

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    exit(1);
}

$passed = 0;
$failed = 0;

function assertSameTranslationCache(string $label, mixed $expected, mixed $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  ✗ {$label}" . PHP_EOL;
        echo "      Verwacht : " . var_export($expected, true) . PHP_EOL;
        echo "      Gekregen : " . var_export($actual, true) . PHP_EOL;
        $failed++;
    }
}

$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_uploads_translation_cache',
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

$hashV1 = hash('sha256', 'The printer does not work');
$hashV2 = hash('sha256', 'The printer still does not work');

assertSameTranslationCache(
    'Geen cachehit voor onbekende vertaling',
    null,
    $store->getTextTranslation('ticket_message', $messageId, 'nl', $hashV1)
);

$store->upsertTextTranslation(
    'ticket_message',
    $messageId,
    $ticketId,
    'nl',
    'en',
    $hashV1,
    'De printer werkt niet'
);

$cachedV1 = $store->getTextTranslation('ticket_message', $messageId, 'nl', $hashV1);
assertSameTranslationCache('Cachehit voor correcte source-hash', true, is_array($cachedV1));
assertSameTranslationCache('Vertaling v1 opgeslagen', 'De printer werkt niet', (string) ($cachedV1['translated_text'] ?? ''));
assertSameTranslationCache('Gedetecteerde taal opgeslagen', 'en', (string) ($cachedV1['source_language'] ?? ''));

assertSameTranslationCache(
    'Geen cachehit met afwijkende source-hash',
    null,
    $store->getTextTranslation('ticket_message', $messageId, 'nl', $hashV2)
);

$store->upsertTextTranslation(
    'ticket_message',
    $messageId,
    $ticketId,
    'nl',
    'en',
    $hashV2,
    'De printer werkt nog steeds niet'
);

assertSameTranslationCache(
    'Oude hash is overschreven bij upsert',
    null,
    $store->getTextTranslation('ticket_message', $messageId, 'nl', $hashV1)
);
$cachedV2 = $store->getTextTranslation('ticket_message', $messageId, 'nl', $hashV2);
assertSameTranslationCache('Nieuwe hash is opvraagbaar', true, is_array($cachedV2));
assertSameTranslationCache('Vertaling v2 opgeslagen', 'De printer werkt nog steeds niet', (string) ($cachedV2['translated_text'] ?? ''));

$store->upsertTextTranslation(
    'ticket_title',
    $ticketId,
    $ticketId,
    'fr',
    'nl',
    hash('sha256', 'Printer stuk'),
    'Imprimante en panne'
);

$titleCache = $store->getTextTranslation('ticket_title', $ticketId, 'fr', hash('sha256', 'Printer stuk'));
assertSameTranslationCache('Titelvertaling apart opgeslagen', 'Imprimante en panne', (string) ($titleCache['translated_text'] ?? ''));


echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
