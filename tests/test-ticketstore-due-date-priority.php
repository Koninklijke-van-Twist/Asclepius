<?php
/**
 * Test dat TicketStore admin-sortering due-date prioriteit dynamisch afleidt.
 */

echo "=== TEST: TicketStore due-date prioriteit sortering ===" . PHP_EOL . PHP_EOL;

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
    } else {
        $exp = var_export($expected, true);
        $act = var_export($actual, true);
        echo "  ✗ {$label}" . PHP_EOL;
        echo "      Verwacht : {$exp}" . PHP_EOL;
        echo "      Gekregen : {$act}" . PHP_EOL;
        $failed++;
    }
}

function assertTrue(string $label, bool $actual): void
{
    assertSame($label, true, $actual);
}

$store = new TicketStore(
    ':memory:',
    sys_get_temp_dir() . '/asclepius_test_uploads',
    ['ict@kvt.nl', 'phone@kvt.nl'],
    TICKET_CATEGORIES
);

$store->saveCategoryMatrix([
    'ict@kvt.nl' => [
        TEMPLATE_TICKET_CATEGORY => true,
        'Telefoon Klaarmaken' => false,
        'hardware bestellen' => true,
    ],
    'phone@kvt.nl' => [
        TEMPLATE_TICKET_CATEGORY => false,
        'Telefoon Klaarmaken' => true,
        'hardware bestellen' => true,
    ],
], [
    'ict@kvt.nl' => true,
    'phone@kvt.nl' => true,
]);

$dueToday = date('Y-m-d');

$resultDue = $store->createTicket(
    'Due vandaag',
    'hardware bestellen',
    'user1@kvt.nl',
    'Ticket met due-date vandaag',
    [],
    0,
    [],
    $dueToday
);
$resultNormal = $store->createTicket(
    'Normaal prioriteit 1',
    'hardware bestellen',
    'user2@kvt.nl',
    'Ticket zonder due-date',
    [],
    1,
    []
);

$tickets = $store->getTickets(true, 'ict@kvt.nl');

assertTrue('Minstens 2 tickets aanwezig', count($tickets) >= 2);
assertSame('Eerste ticket is due-date ticket (dynamische prioriteit)', (int) $resultDue['ticket_id'], (int) ($tickets[0]['id'] ?? 0));
assertSame('Due-date ticket krijgt afgeleide prioriteit 2', 2, (int) ($tickets[0]['priority'] ?? -1));
assertSame('Tweede ticket is het normale ticket', (int) $resultNormal['ticket_id'], (int) ($tickets[1]['id'] ?? 0));

$resultPhoneTemplate = $store->createTicket(
    'Telefoon klaarmaken',
    'Telefoon Klaarmaken',
    'phone@kvt.nl',
    'Template-ticket met telefooncategorie',
    [],
    0,
    [],
    $dueToday
);
assertSame('Telefoon-templatecategorie gebruikt eigen categorie-instelling', 'phone@kvt.nl', (string) ($resultPhoneTemplate['assigned_email'] ?? ''));

$dueTomorrow = date('Y-m-d', strtotime('+1 day'));
$store->updateTicket((int) $resultDue['ticket_id'], 'ingediend', 'ict@kvt.nl', 0, $dueTomorrow);
$updatedOpenTicket = $store->getTicket((int) $resultDue['ticket_id'], true, 'ict@kvt.nl');
assertSame('Due-date kan worden aangepast op bestaand due-date ticket', $dueTomorrow, (string) ($updatedOpenTicket['due_date'] ?? ''));
assertSame('Open due-date ticket houdt afgeleide prioriteit', 2, (int) ($updatedOpenTicket['priority'] ?? -1));

$store->updateTicket((int) $resultDue['ticket_id'], 'afgehandeld', 'ict@kvt.nl', 1, $dueTomorrow);
$updatedClosedTicket = $store->getTicket((int) $resultDue['ticket_id'], true, 'ict@kvt.nl');
assertSame('Afgehandeld due-date ticket behoudt opgeslagen prioriteit', 1, (int) ($updatedClosedTicket['priority'] ?? -1));


echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
