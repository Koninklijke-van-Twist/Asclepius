<?php
/**
 * Tests voor due-date hulpfuncties en getPriorityFromDueDate().
 *
 * Functie die getest worden:
 *  - getSelectableTicketCategories()
 *  - normalizeDueDateInput()
 *  - isDueDateTodayOrFuture()
 *  - countBusinessDaysUntilDueDate()
 *  - getPriorityFromDueDate()
 *  - getPriorityFromFlags()
 */

echo "=== TEST: due-date helpers & prioriteitsberekening ===" . PHP_EOL . PHP_EOL;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['SERVER_ADDR'] = '127.0.0.1';
$_SERVER['PHP_SELF'] = '/asclepius/index.php';

require __DIR__ . '/../web/content/constants.php';
require __DIR__ . '/../web/content/helpers.php';

$passed = 0;
$failed = 0;

/**
 * Assertions helper.
 */
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

// -----------------------------------------------------------------------
// 1. getSelectableTicketCategories()
// -----------------------------------------------------------------------
echo "--- 1. getSelectableTicketCategories() ---" . PHP_EOL;

$selectable = getSelectableTicketCategories();

assertTrue(
    'Resultaat is een array',
    is_array($selectable)
);

assertFalse(
    'TEMPLATE_TICKET_CATEGORY ("' . TEMPLATE_TICKET_CATEGORY . '") is NIET in de lijst',
    in_array(TEMPLATE_TICKET_CATEGORY, $selectable, true)
);

assertTrue(
    'Reguliere categorie "hardware bestellen" is WEL aanwezig',
    in_array('hardware bestellen', $selectable, true)
);

assertTrue(
    'Reguliere categorie "Anders" is WEL aanwezig',
    in_array('Anders', $selectable, true)
);

assertSame(
    'Aantal selecteerbare categorieën is exact 1 minder dan alle categorieën',
    count(TICKET_CATEGORIES) - 1,
    count($selectable)
);

assertTrue(
    'Array is opnieuw geïndexeerd (geen gaten door array_values)',
    array_keys($selectable) === range(0, count($selectable) - 1)
);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 2. normalizeDueDateInput()
// -----------------------------------------------------------------------
echo "--- 2. normalizeDueDateInput() ---" . PHP_EOL;

assertSame(
    'Lege string geeft null',
    null,
    normalizeDueDateInput('')
);

assertSame(
    'Spaties geven null',
    null,
    normalizeDueDateInput('   ')
);

assertSame(
    'Geldige datum Y-m-d geeft datum terug',
    '2026-05-07',
    normalizeDueDateInput('2026-05-07')
);

assertSame(
    'Datum met tijdzone-suffix wordt afgekapt tot Y-m-d',
    '2026-05-07',
    normalizeDueDateInput('2026-05-07T14:30:00')
);

assertSame(
    'Datumformaat d-m-Y wordt afgewezen (null)',
    null,
    normalizeDueDateInput('07-05-2026')
);

assertSame(
    'Willekeurige tekst geeft null',
    null,
    normalizeDueDateInput('not-a-date')
);

assertSame(
    'Jaaraantal alleen geeft null',
    null,
    normalizeDueDateInput('2026')
);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 3. isDueDateTodayOrFuture()
// -----------------------------------------------------------------------
echo "--- 3. isDueDateTodayOrFuture() ---" . PHP_EOL;

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$yesterday = date('Y-m-d', strtotime('-1 day'));

assertFalse(
    'Gisteren is NIET vandaag of toekomst',
    isDueDateTodayOrFuture($yesterday)
);

assertTrue(
    'Vandaag IS vandaag of toekomst',
    isDueDateTodayOrFuture($today)
);

assertTrue(
    'Morgen IS vandaag of toekomst',
    isDueDateTodayOrFuture($tomorrow)
);

assertTrue(
    '30 dagen in de toekomst IS vandaag of toekomst',
    isDueDateTodayOrFuture(date('Y-m-d', strtotime('+30 days')))
);

assertFalse(
    'Ongeldige datum geeft false',
    isDueDateTodayOrFuture('not-a-date')
);

assertFalse(
    'Lege string geeft false',
    isDueDateTodayOrFuture('')
);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 4. countBusinessDaysUntilDueDate()
// -----------------------------------------------------------------------
echo "--- 4. countBusinessDaysUntilDueDate() ---" . PHP_EOL;

// Verleden datum → altijd 0
assertSame(
    'Gisteren geeft 0 werkdagen',
    0,
    countBusinessDaysUntilDueDate($yesterday)
);

assertSame(
    'Lege string geeft 0 werkdagen',
    0,
    countBusinessDaysUntilDueDate('')
);

// Vandaag: 1 als weekdag, 0 als weekend
$todayWeekday = (int) date('N'); // 1=Ma ... 7=Zo
$expectedToday = ($todayWeekday <= 5) ? 1 : 0;
assertSame(
    "Vandaag ({$today}) geeft {$expectedToday} werkdag(en)",
    $expectedToday,
    countBusinessDaysUntilDueDate($today)
);

// +5 kalenderdagen: altijd minimaal 3 werkdagen (worst-case: Sat-Thu = 4 werkdagen)
$plus5 = date('Y-m-d', strtotime('+5 days'));
$count5 = countBusinessDaysUntilDueDate($plus5);
assertTrue(
    "+5 kalenderdagen ({$plus5}) geeft minstens 3 werkdagen (got {$count5})",
    $count5 >= 3
);

// Monotoon: morgen >= vandaag
$countTomorrow = countBusinessDaysUntilDueDate($tomorrow);
assertTrue(
    "Morgen ({$tomorrow}: {$countTomorrow}) >= Vandaag ({$today}: {$expectedToday})",
    $countTomorrow >= $expectedToday
);

// Werkweek van een verre maandag: tel exact 5 werkdagen (Ma t/m Vr)
// Zoek de komende maandag die minstens 14 dagen weg is
$futureMon = new DateTimeImmutable(date('Y-m-d', strtotime('next monday +2 weeks')));
$futureFri = $futureMon->modify('+4 days');
$countFri = countBusinessDaysUntilDueDate($futureFri->format('Y-m-d'));
$countMon = countBusinessDaysUntilDueDate($futureMon->format('Y-m-d'));
assertSame(
    "Vrijdag van die week is precies 4 meer werkdagen dan maandag van die week (vri={$countFri}, ma={$countMon})",
    4,
    $countFri - $countMon
);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 5. getPriorityFromDueDate()
// -----------------------------------------------------------------------
echo "--- 5. getPriorityFromDueDate() ---" . PHP_EOL;

// Ongeldig → 0
assertSame('Lege string → prioriteit 0', 0, getPriorityFromDueDate(''));
assertSame('Ongeldige tekst → prioriteit 0', 0, getPriorityFromDueDate('onjuist'));

// Verleden datum: calendarDaysRemaining is negatief (<7), werkdagen=0 (<3) → prioriteit 2
// (Een verstreken deadline is urgenter dan een toekomstige deadline)
assertSame(
    "Gisteren ({$yesterday}) → prioriteit 2 (verstreken deadline is urgent)",
    2,
    getPriorityFromDueDate($yesterday)
);

assertSame(
    '30 dagen geleden → prioriteit 2 (verstreken deadline is urgent)',
    2,
    getPriorityFromDueDate(date('Y-m-d', strtotime('-30 days')))
);

// Exact 7 kalenderdagen → valt BUITEN het <7 blok → prioriteit 0
$plus7 = date('Y-m-d', strtotime('+7 days'));
assertSame(
    "+7 kalenderdagen ({$plus7}) → prioriteit 0 (grens: niet < 7)",
    0,
    getPriorityFromDueDate($plus7)
);

// Meer dan 7 kalenderdagen → prioriteit 0
$plus30 = date('Y-m-d', strtotime('+30 days'));
assertSame(
    "+30 kalenderdagen ({$plus30}) → prioriteit 0",
    0,
    getPriorityFromDueDate($plus30)
);

// Vandaag → altijd < 7 kalenderdagen én altijd < 3 werkdagen → prioriteit 2
assertSame(
    "Vandaag ({$today}) → prioriteit 2 (< 3 werkdagen)",
    2,
    getPriorityFromDueDate($today)
);

// +1 kalenderdag → < 7 kalenderdagen, nooit ≥ 3 werkdagen → prioriteit 2
assertSame(
    "Morgen ({$tomorrow}) → prioriteit 2 (< 3 werkdagen)",
    2,
    getPriorityFromDueDate($tomorrow)
);

// +5 kalenderdagen → < 7 kalenderdagen én altijd ≥ 3 werkdagen → prioriteit 1
assertSame(
    "+5 kalenderdagen ({$plus5}) → prioriteit 1 (≥ 3 werkdagen, maar < 7 kalenderdag.",
    1,
    getPriorityFromDueDate($plus5)
);

// Aankomende maandag die ≥ 3 werkdagen heeft: find a Friday between 2-6 calendar days out
// met nog ≥ 3 werkdagen → prioriteit 1. We zoeken een dag die < 7 kalender en ≥ 3 werkdagen is.
$plus5Days = countBusinessDaysUntilDueDate($plus5);
$expectedPrio = ($plus5Days < 3) ? 2 : 1;
assertSame(
    "+5 kalenderdagen: werkdagen={$plus5Days}, verwachte prioriteit={$expectedPrio}",
    $expectedPrio,
    getPriorityFromDueDate($plus5)
);

echo PHP_EOL;

// -----------------------------------------------------------------------
// 6. getPriorityFromFlags()
// -----------------------------------------------------------------------
echo "--- 6. getPriorityFromFlags() ---" . PHP_EOL;

assertSame('Niet geblokkeerd, niet volledig → 0', 0, getPriorityFromFlags(false, false));
assertSame('Wel geblokkeerd, niet volledig → 1', 1, getPriorityFromFlags(true, false));
assertSame('Volledig geblokkeerd (ook geblokkeerd) → 2', 2, getPriorityFromFlags(true, true));
assertSame('Volledig geblokkeerd zonder geblokkeerd-vlag → 0 (inconsistente invoer)', 0, getPriorityFromFlags(false, true));

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
