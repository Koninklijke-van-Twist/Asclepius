<?php
echo "=== Testing normalizeIctUsersConfig logic ===" . PHP_EOL . PHP_EOL;

// Simulate the function
function testNormalizeIctUsersConfig(array &$ictUsers, array &$ictUserColors = []): void
{
    echo "BEFORE:" . PHP_EOL;
    echo "  \$ictUsers: "; var_dump($ictUsers);
    echo "  \$ictUserColors: "; var_dump($ictUserColors);
    echo PHP_EOL;

    $normalizedColors = [];
    foreach ($ictUserColors as $email => $color) {
        $normalizedEmail = strtolower(trim((string) $email));
        if ($normalizedEmail === '') {
            continue;
        }

        $normalizedColors[$normalizedEmail] = trim((string) $color);
    }

    $ictUserColors = $normalizedColors;
    $isAssociativeIctUsers = array_keys($ictUsers) !== range(0, count($ictUsers) - 1);

    echo "Is \$ictUsers associative? " . ($isAssociativeIctUsers ? 'YES' : 'NO') . PHP_EOL;
    echo PHP_EOL;

    if ($isAssociativeIctUsers) {
        foreach ($ictUsers as $email => $color) {
            $normalizedEmail = strtolower(trim((string) $email));
            if ($normalizedEmail === '') {
                continue;
            }

            $ictUserColors[$normalizedEmail] = trim((string) $color);
        }

        echo "After extracting colors into \$ictUserColors:" . PHP_EOL;
        echo "  \$ictUserColors: "; var_dump($ictUserColors);
        echo PHP_EOL;

        $ictUsers = array_keys($ictUserColors);
        echo "After \$ictUsers = array_keys(\$ictUserColors):" . PHP_EOL;
        echo "  \$ictUsers: "; var_dump($ictUsers);
        return;
    }

    $ictUsers = array_values(array_filter(array_map(
        static fn(mixed $value): string => strtolower(trim((string) $value)),
        $ictUsers
    ), static fn(string $email): bool => $email !== ''));

    echo "After flat array normalization:" . PHP_EOL;
    echo "  \$ictUsers: "; var_dump($ictUsers);
}

// Test case 1: Associative array (like from auth.php)
echo "TEST 1: Associative array from auth.php" . PHP_EOL;
$ictUsers = [
    "tfalken@kvt.nl" => '#0a6ee0',
    "milanscheenloop@kvt.nl" => '#8a0ae0',
    "opesket@kvt.nl" => '#2ee00a',
];
$ictUserColors = [];
testNormalizeIctUsersConfig($ictUsers, $ictUserColors);
echo "FINAL RESULT: \$ictUsers is now a " . (array_keys($ictUsers) === range(0, count($ictUsers) - 1) ? 'FLAT' : 'ASSOCIATIVE') . " array" . PHP_EOL;
echo "Content: " . implode(', ', $ictUsers) . PHP_EOL;
echo PHP_EOL;

// Test case 2: Already flat array
echo "TEST 2: Already flat array" . PHP_EOL;
$ictUsers = ['tfalken@kvt.nl', 'milanscheenloop@kvt.nl', 'opesket@kvt.nl'];
$ictUserColors = [];
testNormalizeIctUsersConfig($ictUsers, $ictUserColors);
echo "FINAL RESULT: \$ictUsers is now a " . (array_keys($ictUsers) === range(0, count($ictUsers) - 1) ? 'FLAT' : 'ASSOCIATIVE') . " array" . PHP_EOL;
echo "Content: " . implode(', ', $ictUsers) . PHP_EOL;
