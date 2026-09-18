<?php
/**
 * Tests voor hoge prioriteit op de updatemail naar de aanvrager
 * wanneer de status naar "afwachtende op gebruiker" gaat.
 */

echo "=== TEST: waiting-on-user mail priority ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/mail.php';

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
    if (!str_contains($haystack, $needle)) {
        echo "      Gezocht : {$needle}" . PHP_EOL;
        echo "      Headers : {$haystack}" . PHP_EOL;
    }
}

function assertNotContains(string $label, string $needle, string $haystack): void
{
    assertTrue($label, !str_contains($haystack, $needle));
    if (str_contains($haystack, $needle)) {
        echo "      Ongewenst: {$needle}" . PHP_EOL;
        echo "      Headers : {$haystack}" . PHP_EOL;
    }
}

assertSame(
    'Canonieke statuswaarde is afwachtende op gebruiker',
    'afwachtende op gebruiker',
    TICKET_STATUS_WAITING_ON_USER
);
assertTrue(
    'Canonieke status staat in TICKET_STATUSES',
    in_array(TICKET_STATUS_WAITING_ON_USER, TICKET_STATUSES, true)
);
assertTrue(
    'ticketStatusIsWaitingOnUser herkent de canonieke waarde',
    ticketStatusIsWaitingOnUser('afwachtende op gebruiker')
);
assertTrue(
    'ticketStatusIsWaitingOnUser is case-insensitive',
    ticketStatusIsWaitingOnUser('Afwachtende op gebruiker')
);
assertFalse(
    'ticketStatusIsWaitingOnUser wijst andere statussen af',
    ticketStatusIsWaitingOnUser('in behandeling')
);
assertFalse(
    'ticketStatusIsWaitingOnUser wijst afwachtende op bestelling af',
    ticketStatusIsWaitingOnUser('afwachtende op bestelling')
);
assertFalse(
    'ticketStatusIsWaitingOnUser wijst gelokaliseerde labels af',
    ticketStatusIsWaitingOnUser('awaiting user')
);

assertTrue(
    'Hoge prioriteit bij overgang naar wachten op gebruiker',
    requesterUpdateMailHasHighImportance(true, 'afwachtende op gebruiker')
);
assertTrue(
    'Hoge prioriteit bij overgang vanuit afgehandeld naar wachten op gebruiker',
    requesterUpdateMailHasHighImportance(true, TICKET_STATUS_WAITING_ON_USER)
);
assertFalse(
    'Geen hoge prioriteit als de status niet wijzigt',
    requesterUpdateMailHasHighImportance(false, 'afwachtende op gebruiker')
);
assertFalse(
    'Geen hoge prioriteit bij overgang naar in behandeling',
    requesterUpdateMailHasHighImportance(true, 'in behandeling')
);
assertFalse(
    'Geen hoge prioriteit bij overgang naar afgehandeld',
    requesterUpdateMailHasHighImportance(true, 'afgehandeld')
);
assertFalse(
    'Geen hoge prioriteit bij overgang naar afwachtende op derde partij',
    requesterUpdateMailHasHighImportance(true, 'afwachtende op derde partij')
);
assertFalse(
    'Geen hoge prioriteit zonder statuswijziging, ook bij andere status',
    requesterUpdateMailHasHighImportance(false, 'ingediend')
);

[$normalHeaders] = buildMimeParts(
    'kvtbot@kvt.nl',
    'KVT Bot',
    ['aanvrager@kvt.nl'],
    'Update op ticket #1',
    'Gewone update',
    null,
    false
);
assertNotContains('Normale mail heeft geen Importance', 'Importance:', $normalHeaders);
assertNotContains('Normale mail heeft geen X-Priority', 'X-Priority:', $normalHeaders);
assertNotContains('Normale mail heeft geen Priority: urgent', 'Priority: urgent', $normalHeaders);

[$highHeaders] = buildMimeParts(
    'kvtbot@kvt.nl',
    'KVT Bot',
    ['aanvrager@kvt.nl'],
    'Update op ticket #1',
    'Graag reageren',
    null,
    true
);
assertContains('Wachten-op-gebruiker-mail heeft Importance: high', 'Importance: high', $highHeaders);
assertContains('Wachten-op-gebruiker-mail heeft X-Priority: 1', 'X-Priority: 1', $highHeaders);
assertNotContains('Priority: urgent wordt niet toegevoegd', 'Priority: urgent', $highHeaders);

[$htmlHighHeaders] = buildMimeParts(
    'kvtbot@kvt.nl',
    'KVT Bot',
    ['aanvrager@kvt.nl'],
    'Update op ticket #1',
    'Graag reageren',
    '<p>Graag reageren</p>',
    true
);
assertContains('HTML-mail met hoge prioriteit heeft Importance: high', 'Importance: high', $htmlHighHeaders);
assertContains('HTML-mail met hoge prioriteit heeft X-Priority: 1', 'X-Priority: 1', $htmlHighHeaders);

echo PHP_EOL;
echo "Geslaagd: {$passed}" . PHP_EOL;
echo "Gefaald : {$failed}" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo PHP_EOL . "Alle waiting-on-user mail-priority tests geslaagd." . PHP_EOL;
