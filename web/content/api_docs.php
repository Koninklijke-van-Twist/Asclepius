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

    $htmlParts = [];
    $offset = 0;
    $length = strlen($normalized);

    while (
        preg_match(
            '/^```([a-zA-Z0-9_-]*)[ \t]*\n([\s\S]*?)^```[ \t]*$/m',
            $normalized,
            $match,
            PREG_OFFSET_CAPTURE,
            $offset
        ) === 1
    ) {
        $matchStart = (int) $match[0][1];
        $matchText = (string) $match[0][0];

        if ($matchStart > $offset) {
            $before = substr($normalized, $offset, $matchStart - $offset);
            if (trim($before) !== '') {
                $htmlParts[] = formatChangelogBodyHtml($before);
            }
        }

        $language = trim((string) $match[1][0]);
        $code = rtrim((string) $match[2][0], "\n");
        $htmlParts[] = '<pre class="api-docs-code"><code'
            . ($language !== '' ? ' class="language-' . h($language) . '"' : '')
            . '>' . h($code) . '</code></pre>';

        $offset = $matchStart + strlen($matchText);
    }

    if ($offset < $length) {
        $after = substr($normalized, $offset);
        if (trim((string) $after) !== '') {
            $htmlParts[] = formatChangelogBodyHtml((string) $after);
        }
    }

    return implode('', $htmlParts);
}
