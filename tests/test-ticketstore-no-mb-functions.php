<?php
/**
 * Guard-test: TicketStore mag geen mb_ functies gebruiken op servers zonder mbstring.
 */

echo "=== TEST: TicketStore gebruikt geen mb_ functies ===" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function assertNoMbFunctions(string $label, bool $actual): void
{
    global $passed, $failed;
    if ($actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
    } else {
        echo "  ✗ {$label}" . PHP_EOL;
        echo "      Reden: De server ondersteunt geen mb_ functies; gebruik een alternatief zonder mb_ functies." . PHP_EOL;
        $failed++;
    }
}

$ticketStorePath = __DIR__ . '/../web/TicketStore.php';
$contents = file_get_contents($ticketStorePath);
if (!is_string($contents)) {
    echo "FAIL: Kon TicketStore.php niet lezen." . PHP_EOL;
    exit(1);
}

$tokens = token_get_all($contents);
$mbCalls = [];
$tokenCount = count($tokens);

for ($index = 0; $index < $tokenCount; $index++) {
    $token = $tokens[$index];
    if (!is_array($token) || $token[0] !== T_STRING) {
        continue;
    }

    $functionName = (string) $token[1];
    if (!preg_match('/^mb_[a-zA-Z0-9_]+$/', $functionName)) {
        continue;
    }

    $nextIndex = $index + 1;
    while ($nextIndex < $tokenCount) {
        $next = $tokens[$nextIndex];
        if (is_array($next) && in_array($next[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            $nextIndex++;
            continue;
        }
        break;
    }

    if ($nextIndex >= $tokenCount || $tokens[$nextIndex] !== '(') {
        continue;
    }

    $mbCalls[] = $functionName . ' op regel ' . $token[2];
}

assertNoMbFunctions('TicketStore bevat geen mb_ functie-aanroepen', $mbCalls === []);
if ($mbCalls !== []) {
    echo '      Gevonden: ' . implode(', ', $mbCalls) . PHP_EOL;
}

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
