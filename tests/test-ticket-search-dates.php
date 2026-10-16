<?php
/**
 * Test dat de ticketzoekopdracht Nederlandse en ISO-datums herkent.
 */

echo "=== TEST: zoeken op datum in de ticketlijst ===" . PHP_EOL . PHP_EOL;

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

function storePdo(TicketStore $store): PDO
{
    $property = new ReflectionProperty(TicketStore::class, 'pdo');

    return $property->getValue($store);
}

function stampTicket(TicketStore $store, int $ticketId, string $createdAt, string $updatedAt, ?string $messageAt = null): void
{
    $pdo = storePdo($store);
    $ticket = $pdo->prepare('UPDATE tickets SET created_at = :created_at, updated_at = :updated_at WHERE id = :id');
    $ticket->execute([
        ':created_at' => $createdAt,
        ':updated_at' => $updatedAt,
        ':id' => $ticketId,
    ]);
    $messages = $pdo->prepare(
        'UPDATE ticket_messages
         SET created_at = :created_at
         WHERE ticket_id = :id AND COALESCE(is_ghost, 0) = 0'
    );
    $messages->execute([
        ':created_at' => $messageAt ?? $createdAt,
        ':id' => $ticketId,
    ]);
}

function searchIds(TicketStore $store, string $query): array
{
    $tickets = $store->getTickets(true, 'ict@kvt.nl', [], null, [], $query);

    return array_map(static fn(array $ticket): int => (int) ($ticket['id'] ?? 0), $tickets);
}

$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_search_dates',
    ['ict@kvt.nl'],
    TICKET_CATEGORIES
);

$year = (int) date('Y');
$previousYear = $year - 1;
$today = sprintf('%04d-10-16T09:15:00+00:00', $year);
$lastYearSameDay = sprintf('%04d-10-16T09:15:00+00:00', $previousYear);
$january = sprintf('%04d-01-05T11:00:00+00:00', $year);
$october2024 = '2024-10-16T08:00:00+01:00';
$september2024 = '2024-09-16T08:00:00+01:00';
$may2024 = '2024-05-02T08:00:00+01:00';
$old = '2020-03-03T08:00:00+00:00';

$printer = (int) $store->createTicket(
    'Printer vastgelopen',
    'Printerproblemen',
    'user@kvt.nl',
    'De printer doet niets',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $printer, $today, $today);

$archive = (int) $store->createTicket(
    'Printer archief',
    'Printerproblemen',
    'user@kvt.nl',
    'Oude printermelding',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $archive, $lastYearSameDay, $lastYearSameDay);

$screen = (int) $store->createTicket(
    'Beeldscherm zwart',
    'Hardwareproblemen',
    'user@kvt.nl',
    'Geen beeld',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $screen, $january, $january);

$explicit = (int) $store->createTicket(
    'Licentie verlopen',
    'licentie aanvragen',
    'user@kvt.nl',
    'Licentie uit 2024',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $explicit, $october2024, $october2024);

$september = (int) $store->createTicket(
    'Septemberstoring',
    'Softwareproblemen',
    'user@kvt.nl',
    'Alleen in september',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $september, $september2024, $september2024);

$updatedOnly = (int) $store->createTicket(
    'Later bijgewerkt',
    'Anders',
    'user@kvt.nl',
    'Aangemaakt lang geleden',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $updatedOnly, $old, $today, $old);

$messageOnly = (int) $store->createTicket(
    'Bericht op de dag',
    'Anders',
    'user@kvt.nl',
    'Het ticket zelf is ouder',
    [],
    0,
    []
)['ticket_id'];
$messageId = $store->addMessage($messageOnly, 'ict@kvt.nl', 'ict', 'Antwoord op de gezochte dag', []);
stampTicket($store, $messageOnly, $old, $old, $old);
storePdo($store)->prepare('UPDATE ticket_messages SET created_at = :created_at WHERE id = :id')->execute([
    ':created_at' => $today,
    ':id' => $messageId,
]);

$ghostOnly = (int) $store->createTicket(
    'Alleen een ghost',
    'Anders',
    'user@kvt.nl',
    'Zichtbare tekst is oud',
    [],
    0,
    []
)['ticket_id'];
$ghostId = $store->addMessage($ghostOnly, 'ict@kvt.nl', 'ict', 'Verborgen notitie', [], true);
stampTicket($store, $ghostOnly, $old, $old, $old);
storePdo($store)->prepare('UPDATE ticket_messages SET created_at = :created_at WHERE id = :id')->execute([
    ':created_at' => $today,
    ':id' => $ghostId,
]);

$may = (int) $store->createTicket(
    'Mei-melding',
    'Anders',
    'user@kvt.nl',
    'Iets in mei',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $may, $may2024, $may2024);

$expectedToday = [$printer, $updatedOnly, $messageOnly];
sort($expectedToday);

$queries = [
    '16-10-' . $year => [$printer, $updatedOnly, $messageOnly],
    '16/10/' . $year => [$printer, $updatedOnly, $messageOnly],
    $year . '-10-16' => [$printer, $updatedOnly, $messageOnly],
    '16-10' => [$printer, $updatedOnly, $messageOnly],
    '16/10' => [$printer, $updatedOnly, $messageOnly],
    '16 okt' => [$printer, $updatedOnly, $messageOnly],
    '16 oktober ' . $year => [$printer, $updatedOnly, $messageOnly],
    '16 OKTOBER ' . $year => [$printer, $updatedOnly, $messageOnly],
    'okt ' . $year => [$printer, $updatedOnly, $messageOnly],
    'oktober ' . $year => [$printer, $updatedOnly, $messageOnly],
    (string) $year => [$printer, $updatedOnly, $messageOnly, $screen],
    '16-10-' . $previousYear => [$archive],
    '16/10/' . $previousYear => [$archive],
    $previousYear . '-10-16' => [$archive],
    '16 oktober 2024' => [$explicit],
    'okt 2024' => [$explicit],
    'oktober 2024' => [$explicit],
    '2024-10' => [$explicit],
    '2024' => [$explicit, $september, $may],
    '5-1-' . $year => [$screen],
];

foreach ($queries as $query => $expected) {
    $actual = searchIds($store, (string) $query);
    sort($expected);
    sort($actual);
    assertSame('Zoekopdracht ' . $query, $expected, $actual);
}

$combined = searchIds($store, 'printer 16-10');
sort($combined);
assertSame('Tekst en datum samen: printer 16-10', [$printer], $combined);

$combinedExplicit = searchIds($store, 'printer 16 oktober ' . $previousYear);
sort($combinedExplicit);
assertSame('Tekst en volledige datum samen', [$archive], $combinedExplicit);

$textOnly = searchIds($store, 'printer');
sort($textOnly);
assertSame('Tekst zoekt nog in de titel', [$printer, $archive], $textOnly);

assertSame('Ongeldige datum 31-02-2026 matcht niets', [], searchIds($store, '31-02-2026'));
assertSame('Onmogelijke dag 32-10 matcht niets', [], searchIds($store, '32-10'));
assertSame('Ghostbericht telt niet als berichtdatum', [$printer, $updatedOnly, $messageOnly], (static function () use ($store): array {
    $ids = searchIds($store, '16-10-' . date('Y'));
    sort($ids);
    return $ids;
})());

$injection = searchIds($store, "' OR 1=1 --");
assertSame('SQL-injectie in tekst geeft geen tickets', [], $injection);
$dateInjection = searchIds($store, "16-10-2026' OR '1'='1");
assertSame('SQL-injectie achter een datum geeft geen tickets', [], $dateInjection);

$listed = searchIds($store, '16-10');
assertSame(
    'Aantal hoort bij dezelfde datumfilter',
    count($listed),
    $store->countTickets(true, 'ict@kvt.nl', [], null, [], '16-10')
);

function renumberTicket(TicketStore $store, int $from, int $to): void
{
    $pdo = storePdo($store);
    $pdo->exec('PRAGMA foreign_keys = OFF');
    foreach (['ticket_messages', 'ticket_participants', 'ticket_status_transitions'] as $table) {
        $statement = $pdo->prepare('UPDATE ' . $table . ' SET ticket_id = :to WHERE ticket_id = :from');
        $statement->execute([':to' => $to, ':from' => $from]);
    }
    $statement = $pdo->prepare('UPDATE tickets SET id = :to WHERE id = :from');
    $statement->execute([':to' => $to, ':from' => $from]);
    $pdo->exec('PRAGMA foreign_keys = ON');
}

$ticketSixteen = (int) $store->createTicket(
    'Ticket zestien',
    'Anders',
    'user@kvt.nl',
    'Dit is nummer 16 en hoort niet bij 16 oktober',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $ticketSixteen, $old, $old);
renumberTicket($store, $ticketSixteen, 16);

$ticketYearNumber = (int) $store->createTicket(
    'Ticket met jaartal als nummer',
    'Anders',
    'user@kvt.nl',
    'Dit nummer is een jaartal maar het ticket is oud',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $ticketYearNumber, $old, $old);
renumberTicket($store, $ticketYearNumber, $year);

$octoberWords = searchIds($store, '16 okt');
assertTrue('16 okt toont het ticket van die dag', in_array($printer, $octoberWords, true));
assertTrue('16 okt haalt ticketnummer 16 er niet bij', !in_array(16, $octoberWords, true));

$fullDateWords = searchIds($store, '16 oktober ' . $year);
assertTrue('16 oktober en jaartal haalt die ticketnummers er niet bij', !in_array(16, $fullDateWords, true) && !in_array($year, $fullDateWords, true));

$yearSearch = searchIds($store, (string) $year);
assertTrue('Los jaartal vindt nog wel het ticket met dat nummer', in_array($year, $yearSearch, true));
assertTrue('Los ticketnummer met hekje blijft werken', in_array(16, searchIds($store, '#16'), true));

$february = (int) $store->createTicket(
    'Melding 31',
    'Anders',
    'user@kvt.nl',
    'Staat in februari',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $february, '2026-02-10T08:00:00+00:00', '2026-02-10T08:00:00+00:00');

$ticketThirtyOne = (int) $store->createTicket(
    'Ticket eenendertig',
    'Anders',
    'user@kvt.nl',
    'Dit nummer hoort niet bij 31 februari',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $ticketThirtyOne, $old, $old);
renumberTicket($store, $ticketThirtyOne, 31);

$ticketNineteenNinetyNine = (int) $store->createTicket(
    'Ticket negentiennegentig',
    'Anders',
    'user@kvt.nl',
    'Dit nummer is een jaartal buiten het zoekbereik',
    [],
    0,
    []
)['ticket_id'];
stampTicket($store, $ticketNineteenNinetyNine, $old, $old);
renumberTicket($store, $ticketNineteenNinetyNine, 1999);

$februaryHits = searchIds($store, 'februari 2026');
assertTrue('februari 2026 vindt het februariticket', in_array($february, $februaryHits, true));
assertSame('Ongeldige datum 31 februari 2026 matcht niets', [], searchIds($store, '31 februari 2026'));
assertSame('Ongeldige datum 31 FEBRUARI 2026 matcht niets', [], searchIds($store, '31 FEBRUARI 2026'));
assertSame('Jaartal buiten bereik 16 oktober 1999 matcht niets', [], searchIds($store, '16 oktober 1999'));
assertSame('Onmogelijke dag 32 oktober 2026 matcht niets', [], searchIds($store, '32 oktober ' . $year));
assertTrue('Los ticketnummer 31 blijft werken', in_array(31, searchIds($store, '31'), true));
assertTrue('Ticket 1999 blijft op nummer vindbaar', in_array(1999, searchIds($store, '1999'), true));

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
