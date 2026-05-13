<?php
/**
 * Test voor checkbox-state opslag in ticket message_text.
 */

echo "=== TEST: message checkbox opslag ===" . PHP_EOL . PHP_EOL;

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
    ['ict@kvt.nl'],
    TICKET_CATEGORIES
);

$result = $store->createTicket(
    'Checkbox test',
    'hardware bestellen',
    'user@kvt.nl',
    "[ ] Eerste taak\n[x] Tweede taak",
    [],
    0,
    []
);
$ticketId = (int) ($result['ticket_id'] ?? 0);
$ticket = $store->getTicket($ticketId, true, 'ict@kvt.nl');
$messages = is_array($ticket['messages'] ?? null) ? $ticket['messages'] : [];

assertTrue('Ticket heeft minimaal 1 message', count($messages) >= 1);
$messageId = (int) ($messages[0]['id'] ?? 0);
assertTrue('Eerste message heeft een geldig ID', $messageId > 0);

$updated = $store->updateTicketMessageCheckboxState($ticketId, $messageId, 0, true, true, 'ict@kvt.nl');
assertTrue('Checkbox-update geeft geactualiseerde tekst terug', is_string($updated));
assertSame('Eerste regel is aangevinkt in tekst', true, str_starts_with((string) $updated, '[x]'));

$ticketAfter = $store->getTicket($ticketId, true, 'ict@kvt.nl');
$messagesAfter = is_array($ticketAfter['messages'] ?? null) ? $ticketAfter['messages'] : [];
assertSame('Aantal messages blijft gelijk (geen extra bericht)', count($messages), count($messagesAfter));
assertSame('Message ID blijft gelijk', $messageId, (int) ($messagesAfter[0]['id'] ?? 0));

$invalidLine = $store->updateTicketMessageCheckboxState($ticketId, $messageId, 99, false, true, 'ict@kvt.nl');
assertSame('Onbestaande regelindex geeft null', null, $invalidLine);


echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
