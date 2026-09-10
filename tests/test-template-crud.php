<?php
/**
 * Tests voor TicketStore template CRUD-methoden.
 *
 * Methoden die getest worden:
 *  - createTicketTemplate(name, body, authorEmail): int
 *  - getTicketTemplates(): array
 *  - getTicketTemplateById(id): ?array
 *  - updateTicketTemplate(id, name, body, authorEmail): bool
 *  - deleteTicketTemplate(id): bool
 *
 * Gebruikt een in-memory SQLite-database zodat de test zelfstandig draait
 * zonder bestaande applicatiedata te raken.
 */

echo "=== TEST: TicketStore template CRUD ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/TicketStore.php';

if (!extension_loaded('pdo_sqlite')) {
    echo "FAIL: pdo_sqlite-extensie is niet beschikbaar." . PHP_EOL;
    echo "      Zorg ervoor dat de SQLite PDO-extensie actief is." . PHP_EOL;
    echo "      Lokaal: gebruik C:\\xampp\\php\\php.exe in plaats van de systeem-PHP." . PHP_EOL;
    echo "      CI: voeg pdo_sqlite toe aan de PHP-extensies in de workflow." . PHP_EOL;
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
function assertFalse(string $label, bool $actual): void
{
    assertSame($label, false, $actual);
}
function assertNull(string $label, mixed $actual): void
{
    assertSame($label, null, $actual);
}

/**
 * Maakt een TicketStore die op een in-memory SQLite-database draait.
 * dirname(':memory:') = '.' wat altijd bestaat, en 'sqlite::memory:' is
 * een geldige PDO-DSN voor een tijdelijke in-memory database.
 */
function makeStore(): TicketStore
{
    return new TicketStore(
        ':memory:',
        sys_get_temp_dir() . '/asclepius_test_uploads',
        ['ict@kvt.nl'],
        TICKET_CATEGORIES
    );
}

// -----------------------------------------------------------------------
// Setup
// -----------------------------------------------------------------------
$store = makeStore();

// -----------------------------------------------------------------------
// 1. Lege lijst op een verse store
// -----------------------------------------------------------------------
echo "--- 1. getTicketTemplates() op lege store ---" . PHP_EOL;

$templates = $store->getTicketTemplates();
assertSame('Verse store heeft 0 templates', [], $templates);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 2. Template aanmaken
// -----------------------------------------------------------------------
echo "--- 2. createTicketTemplate() ---" . PHP_EOL;

$id1 = $store->createTicketTemplate('Standaard Setup', 'Laptop installeren + Windows activeren.', 'ict@kvt.nl');
assertTrue('createTicketTemplate() geeft een integer-ID > 0', $id1 > 0);

$id2 = $store->createTicketTemplate('Netwerk instellen', 'WiFi en VPN configureren.', 'ICT@KVT.NL');
assertTrue('Tweede createTicketTemplate() geeft een ander ID', $id2 > 0 && $id2 !== $id1);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 3. Lijst na aanmaken
// -----------------------------------------------------------------------
echo "--- 3. getTicketTemplates() na aanmaken ---" . PHP_EOL;

$templates = $store->getTicketTemplates();
assertSame('Lijst bevat precies 2 templates', 2, count($templates));

// Controleer veldstructuur van eerste template
$first = $templates[0];
assertSame('Template heeft veld "id"', true, array_key_exists('id', $first));
assertSame('Template heeft veld "name"', true, array_key_exists('name', $first));
assertSame('Template heeft veld "body"', true, array_key_exists('body', $first));
assertSame('Template heeft veld "created_by_email"', true, array_key_exists('created_by_email', $first));
assertSame('Template heeft veld "updated_by_email"', true, array_key_exists('updated_by_email', $first));
assertSame('Template heeft veld "created_at"', true, array_key_exists('created_at', $first));
assertSame('Template heeft veld "updated_at"', true, array_key_exists('updated_at', $first));
assertSame('Template heeft veld "sort_order"', true, array_key_exists('sort_order', $first));

// Controleer sortering: op aanmaakvolgorde (sort_order), Standaard voor Netwerk
assertSame('Eerste template is in aanmaakvolgorde (Standaard voor Netwerk)', 'Standaard Setup', $templates[0]['name']);
assertSame('Tweede template is Netwerk instellen', 'Netwerk instellen', $templates[1]['name']);

// Controleer dat e-mailadres genormaliseerd is (lowercase)
$byId1 = $store->getTicketTemplateById($id1);
assertSame('Auteur-e-mail wordt opgeslagen als lowercase', 'ict@kvt.nl', $byId1['created_by_email']);

$byId2 = $store->getTicketTemplateById($id2);
assertSame('Auteur-e-mail (uppercase invoer) wordt opgeslagen als lowercase', 'ict@kvt.nl', $byId2['created_by_email']);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 4. getTicketTemplateById()
// -----------------------------------------------------------------------
echo "--- 4. getTicketTemplateById() ---" . PHP_EOL;

$found = $store->getTicketTemplateById($id1);
assertSame('Bestaand template gevonden', true, is_array($found));
assertSame('Template-naam correct', 'Standaard Setup', $found['name']);
assertSame('Template-body correct', 'Laptop installeren + Windows activeren.', $found['body']);

assertNull('Niet-bestaand ID (99999) geeft null', $store->getTicketTemplateById(99999));
assertNull('ID = 0 geeft null (beveiligingscheck)', $store->getTicketTemplateById(0));
assertNull('Negatief ID geeft null', $store->getTicketTemplateById(-1));

echo PHP_EOL;

// -----------------------------------------------------------------------
// 5. updateTicketTemplate()
// -----------------------------------------------------------------------
echo "--- 5. updateTicketTemplate() ---" . PHP_EOL;

$updated = $store->updateTicketTemplate($id1, 'Standaard Setup v2', 'Laptop + extra software.', 'beheer@kvt.nl');
assertTrue('Update van bestaand template retourneert true', $updated);

$afterUpdate = $store->getTicketTemplateById($id1);
assertSame('Naam is bijgewerkt', 'Standaard Setup v2', $afterUpdate['name']);
assertSame('Body is bijgewerkt', 'Laptop + extra software.', $afterUpdate['body']);
assertSame('updated_by_email is bijgewerkt (lowercase)', 'beheer@kvt.nl', $afterUpdate['updated_by_email']);
assertSame('created_by_email is NIET veranderd', 'ict@kvt.nl', $afterUpdate['created_by_email']);

// Update van niet-bestaand ID moet false teruggeven
assertFalse('Update van niet-bestaand ID (99999) retourneert false', $store->updateTicketTemplate(99999, 'X', 'Y', 'a@b.nl'));

// Update met ID = 0 moet false teruggeven (beveiligingscheck)
assertFalse('Update met ID = 0 retourneert false', $store->updateTicketTemplate(0, 'X', 'Y', 'a@b.nl'));

echo PHP_EOL;

// -----------------------------------------------------------------------
// 6. deleteTicketTemplate()
// -----------------------------------------------------------------------
echo "--- 6. deleteTicketTemplate() ---" . PHP_EOL;

assertFalse('Verwijderen van niet-bestaand ID (99999) retourneert false', $store->deleteTicketTemplate(99999));
assertFalse('Verwijderen met ID = 0 retourneert false (beveiligingscheck)', $store->deleteTicketTemplate(0));
assertFalse('Verwijderen met negatief ID retourneert false', $store->deleteTicketTemplate(-1));

$deleted = $store->deleteTicketTemplate($id1);
assertTrue('Verwijderen van bestaand template retourneert true', $deleted);

assertNull('Verwijderd template is niet meer op te halen', $store->getTicketTemplateById($id1));

$templatesAfterDelete = $store->getTicketTemplates();
assertSame('Na verwijdering bevat lijst nog 1 template', 1, count($templatesAfterDelete));
assertSame('Overgebleven template is het tweede', 'Netwerk instellen', $templatesAfterDelete[0]['name']);

// Dubbel verwijderen van hetzelfde ID moet false geven
assertFalse('Nogmaals verwijderen van al verwijderd template retourneert false', $store->deleteTicketTemplate($id1));

echo PHP_EOL;

// -----------------------------------------------------------------------
// 7. createTicketTemplate() trimt naam en body
// -----------------------------------------------------------------------
echo "--- 7. createTicketTemplate() trimt invoer ---" . PHP_EOL;

$idTrimmed = $store->createTicketTemplate('  Spaties rondom  ', '  Tekst met spaties  ', 'trim@kvt.nl');
$trimmed = $store->getTicketTemplateById($idTrimmed);
assertSame('Naam wordt getrimd bij opslaan', 'Spaties rondom', $trimmed['name']);
assertSame('Body wordt getrimd bij opslaan', 'Tekst met spaties', $trimmed['body']);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 8. reorderTicketTemplates()
// -----------------------------------------------------------------------
echo "--- 8. reorderTicketTemplates() ---" . PHP_EOL;

// Maak een nieuwe lege store voor een schone reorder-test
$store2 = makeStore();
$rId1 = $store2->createTicketTemplate('A', 'body A', 'a@kvt.nl');
$rId2 = $store2->createTicketTemplate('B', 'body B', 'b@kvt.nl');
$rId3 = $store2->createTicketTemplate('C', 'body C', 'c@kvt.nl');

// Originele volgorde: A (sort_order=1), B (2), C (3)
$before = $store2->getTicketTemplates();
assertSame('Aanmaakvolgorde: A als eerste', 'A', $before[0]['name']);
assertSame('Aanmaakvolgorde: C als laatste', 'C', $before[2]['name']);

// Herorden naar C, A, B
true === $store2->reorderTicketTemplates([$rId3, $rId1, $rId2]);
$after = $store2->getTicketTemplates();
assertSame('Na reorder: C is eerste', 'C', $after[0]['name']);
assertSame('Na reorder: A is tweede', 'A', $after[1]['name']);
assertSame('Na reorder: B is derde', 'B', $after[2]['name']);

// Lege lijst retourneert false
assertFalse('Lege ordered_ids geeft false', $store2->reorderTicketTemplates([]));

// Ongeldige IDs worden genegeerd (enkel geldige tellen)
$store2->reorderTicketTemplates([$rId3, 0, -5, $rId1]);
$afterPartial = $store2->getTicketTemplates();
// C en A krijgen sort_order 1 en 2; B behoudt sort_order 2 (was 3, nu 2 want niet gewijzigd)
assertSame('Ongeldig ID 0 wordt genegeerd', 'C', $afterPartial[0]['name']);

echo PHP_EOL;

// -----------------------------------------------------------------------
// Eindresultaat
// -----------------------------------------------------------------------
$total = $passed + $failed;
echo "=== RESULTAAT: {$passed}/{$total} tests geslaagd ===" . PHP_EOL;
if ($failed > 0) {
    echo "✗ {$failed} test(s) MISLUKT" . PHP_EOL;
    exit(1);
} else {
    echo "✓ Alle tests geslaagd" . PHP_EOL;
    exit(0);
}
