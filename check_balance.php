<?php
$code = file_get_contents(__DIR__ . '/web/index.php');
$tokens = token_get_all($code);
$stack = [];
foreach ($tokens as $t) {
    if (!is_array($t)) continue;
    if ($t[0] === T_IF || $t[0] === T_FOREACH) {
        $stack[] = token_name($t[0]) . ':' . $t[2];
    }
    if ($t[0] === T_ENDIF || $t[0] === T_ENDFOREACH) {
        if (empty($stack)) {
            echo 'EXTRA ' . token_name($t[0]) . ' at line ' . $t[2] . PHP_EOL;
        } else {
            array_pop($stack);
        }
    }
}
if (!empty($stack)) {
    echo 'UNCLOSED: ';
    print_r($stack);
} else {
    echo 'Balanced' . PHP_EOL;
}
