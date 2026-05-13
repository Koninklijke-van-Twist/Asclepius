<?php
/**
 * Test runner voor de Asclepius unit-tests.
 *
 * Gebruik: php tests/run-tests.php
 * Exitcode 0 = alle tests geslaagd, exitcode 1 = minstens één test gefaald.
 *
 * Elk testbestand in dezelfde map wordt uitgevoerd als apart sub-proces.
 * Bestanden die beginnen met 'run-' worden overgeslagen (deze runner zelf).
 */

$testDir = __DIR__;
$phpBin = PHP_BINARY;
$testFiles = array_merge(
    glob($testDir . '/test-*.php') ?: [],
    glob($testDir . '/check_*.php') ?: []
);
sort($testFiles);

if ($testFiles === []) {
    echo "Geen testbestanden gevonden in {$testDir}." . PHP_EOL;
    exit(0);
}

$totalFailed = 0;
$results = [];

foreach ($testFiles as $file) {
    $name = basename($file);
    echo str_repeat('-', 60) . PHP_EOL;
    echo "Testbestand: {$name}" . PHP_EOL;
    echo str_repeat('-', 60) . PHP_EOL;

    passthru("{$phpBin} " . escapeshellarg($file), $exitCode);

    echo PHP_EOL;
    $results[$name] = $exitCode === 0;
    if ($exitCode !== 0) {
        $totalFailed++;
    }
}

echo str_repeat('=', 60) . PHP_EOL;
echo "SAMENVATTING" . PHP_EOL;
echo str_repeat('=', 60) . PHP_EOL;

foreach ($results as $name => $passed) {
    $status = $passed ? '✓ GESLAAGD' : '✗ GEFAALD';
    echo "  [{$status}] {$name}" . PHP_EOL;
}

echo PHP_EOL;

if ($totalFailed === 0) {
    echo "Alle tests geslaagd." . PHP_EOL;
    exit(0);
} else {
    echo "{$totalFailed} test(bestand(en)) gefaald." . PHP_EOL;
    exit(1);
}
