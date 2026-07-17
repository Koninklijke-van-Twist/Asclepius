<?php

function getApiDocsFilePath(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'api.md';
}

function loadApiDocsMarkdown(): string
{
    $path = getApiDocsFilePath();
    if (!is_file($path)) {
        return '';
    }

    return (string) file_get_contents($path);
}

function formatApiDocsHtml(string $markdown): string
{
    $normalized = trim(str_replace(["\r\n", "\r"], "\n", $markdown));
    if ($normalized === '') {
        return '';
    }

    $parts = preg_split('/^```([a-zA-Z0-9_-]*)\s*$/m', $normalized, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts) || $parts === []) {
        return formatChangelogBodyHtml($normalized);
    }

    $htmlParts = [];
    $index = 0;
    $partCount = count($parts);

    while ($index < $partCount) {
        $chunk = (string) ($parts[$index] ?? '');
        if ($chunk !== '') {
            $htmlParts[] = formatChangelogBodyHtml($chunk);
        }

        $index++;
        if ($index >= $partCount) {
            break;
        }

        $language = trim((string) ($parts[$index] ?? ''));
        $index++;
        $code = (string) ($parts[$index] ?? '');
        $index++;

        $htmlParts[] = '<pre class="api-docs-code"><code'
            . ($language !== '' ? ' class="language-' . h($language) . '"' : '')
            . '>' . h(rtrim($code, "\n")) . '</code></pre>';
    }

    return implode('', $htmlParts);
}
