<?php
/**
 * Guard-test: mb_ functies zijn niet toegestaan in PHP-code binnen dit project.
 */

echo "=== TEST: Geen mb_ functies in PHP-code ===" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function assertNoMbCallsProjectWide(string $label, bool $actual, array $violations = []): void
{
    global $passed, $failed;

    if ($actual) {
        echo "  ✓ {$label}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "  ✗ {$label}" . PHP_EOL;
    echo "      Reden: De server ondersteunt deze mb_ functies niet en de auteur van de code moet een alternatief gebruiken." . PHP_EOL;
    if ($violations !== []) {
        foreach ($violations as $violation) {
            echo "      - {$violation}" . PHP_EOL;
        }
    }
    $failed++;
}

$projectRoot = dirname(__DIR__);
$excludedDirectories = [
    $projectRoot . DIRECTORY_SEPARATOR . '.git',
    $projectRoot . DIRECTORY_SEPARATOR . 'vendor',
    $projectRoot . DIRECTORY_SEPARATOR . 'thirdparty',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS)
);

$guardedMbFunctions = array_fill_keys([
    'mb_check_encoding',
    'mb_chr',
    'mb_convert_case',
    'mb_convert_encoding',
    'mb_convert_kana',
    'mb_convert_variables',
    'mb_decode_mimeheader',
    'mb_decode_numericentity',
    'mb_detect_encoding',
    'mb_detect_order',
    'mb_encode_mimeheader',
    'mb_encode_numericentity',
    'mb_encoding_aliases',
    'mb_ereg',
    'mb_ereg_match',
    'mb_ereg_replace',
    'mb_ereg_replace_callback',
    'mb_ereg_search',
    'mb_ereg_search_getpos',
    'mb_ereg_search_getregs',
    'mb_ereg_search_init',
    'mb_ereg_search_pos',
    'mb_ereg_search_regs',
    'mb_ereg_search_setpos',
    'mb_eregi',
    'mb_eregi_replace',
    'mb_get_info',
    'mb_http_input',
    'mb_http_output',
    'mb_internal_encoding',
    'mb_language',
    'mb_lcfirst',
    'mb_list_encodings',
    'mb_ltrim',
    'mb_ord',
    'mb_output_handler',
    'mb_parse_str',
    'mb_preferred_mime_name',
    'mb_regex_encoding',
    'mb_regex_set_options',
    'mb_rtrim',
    'mb_scrub',
    'mb_send_mail',
    'mb_split',
    'mb_str_pad',
    'mb_str_split',
    'mb_strcut',
    'mb_strimwidth',
    'mb_stripos',
    'mb_stristr',
    'mb_strlen',
    'mb_strpos',
    'mb_strrchr',
    'mb_strrichr',
    'mb_strripos',
    'mb_strrpos',
    'mb_strstr',
    'mb_strtolower',
    'mb_strtoupper',
    'mb_strwidth',
    'mb_substitute_character',
    'mb_substr',
    'mb_substr_count',
    'mb_trim',
    'mb_ucfirst',
], true);

$projectPhpFiles = [];
$definedMbFunctions = [];

foreach ($iterator as $fileInfo) {
    if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
        continue;
    }

    $filePath = $fileInfo->getPathname();
    $normalizedPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath);

    $skip = false;
    foreach ($excludedDirectories as $excludedDirectory) {
        if (str_starts_with($normalizedPath, $excludedDirectory . DIRECTORY_SEPARATOR) || $normalizedPath === $excludedDirectory) {
            $skip = true;
            break;
        }
    }
    if ($skip) {
        continue;
    }

    if (strtolower((string) pathinfo($normalizedPath, PATHINFO_EXTENSION)) !== 'php') {
        continue;
    }

    $projectPhpFiles[] = $normalizedPath;
}

foreach ($projectPhpFiles as $phpFile) {
    $contents = file_get_contents($phpFile);
    if (!is_string($contents) || $contents === '') {
        continue;
    }

    $tokens = token_get_all($contents);
    $tokenCount = count($tokens);

    for ($index = 0; $index < $tokenCount; $index++) {
        $token = $tokens[$index];
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }

        $nameIndex = $index + 1;
        while ($nameIndex < $tokenCount) {
            $nameToken = $tokens[$nameIndex];
            if (is_array($nameToken) && in_array($nameToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $nameIndex++;
                continue;
            }
            if ($nameToken === '&') {
                $nameIndex++;
                continue;
            }
            break;
        }

        if ($nameIndex >= $tokenCount || !is_array($tokens[$nameIndex]) || $tokens[$nameIndex][0] !== T_STRING) {
            continue;
        }

        $functionName = strtolower((string) $tokens[$nameIndex][1]);
        if (isset($guardedMbFunctions[$functionName])) {
            $definedMbFunctions[$functionName] = true;
        }
    }
}

$violations = [];

foreach ($projectPhpFiles as $phpFile) {
    $contents = file_get_contents($phpFile);
    if (!is_string($contents) || $contents === '') {
        continue;
    }

    $tokens = token_get_all($contents);
    $tokenCount = count($tokens);

    for ($index = 0; $index < $tokenCount; $index++) {
        $token = $tokens[$index];
        if (!is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        $functionName = strtolower((string) $token[1]);
        if (!isset($guardedMbFunctions[$functionName])) {
            continue;
        }

        $nextIndex = $index + 1;
        while ($nextIndex < $tokenCount) {
            $nextToken = $tokens[$nextIndex];
            if (is_array($nextToken) && in_array($nextToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $nextIndex++;
                continue;
            }
            break;
        }

        if ($nextIndex >= $tokenCount || $tokens[$nextIndex] !== '(') {
            continue;
        }

        $prevIndex = $index - 1;
        while ($prevIndex >= 0) {
            $prevToken = $tokens[$prevIndex];
            if (is_array($prevToken) && in_array($prevToken[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                $prevIndex--;
                continue;
            }
            break;
        }

        if (
            $prevIndex >= 0
            && ((is_array($tokens[$prevIndex]) && in_array($tokens[$prevIndex][0], [T_FUNCTION, T_OBJECT_OPERATOR, T_DOUBLE_COLON], true))
                || $tokens[$prevIndex] === '->'
                || $tokens[$prevIndex] === '::')
        ) {
            continue;
        }

        if (isset($definedMbFunctions[$functionName])) {
            continue;
        }

        $relativePath = str_replace($projectRoot . DIRECTORY_SEPARATOR, '', $phpFile);
        $violations[] = $relativePath . ':' . (int) $token[2] . ' -> ' . $functionName . '()';
    }
}

assertNoMbCallsProjectWide('Geen mb_ functieaanroepen gevonden in PHP-bestanden', $violations === [], $violations);

echo PHP_EOL;
echo "Resultaat: {$passed} geslaagd, {$failed} gefaald." . PHP_EOL;
exit($failed > 0 ? 1 : 0);
