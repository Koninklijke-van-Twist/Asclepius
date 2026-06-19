<?php

function getChangelogFilePath(string $lang): string
{
    $normalizedLang = array_key_exists($lang, SUPPORTED_LANGUAGES) ? $lang : 'nl';

    return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'changelog' . DIRECTORY_SEPARATOR . 'changelog-' . $normalizedLang . '.md';
}

function splitChangelogContent(string $content): array
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $content);
    $normalized = trim($normalized);
    if ($normalized === '') {
        return [];
    }

    $blocks = preg_split('/\n---\n/', $normalized) ?: [];

    return array_values(array_filter(array_map('trim', $blocks), static fn(string $block): bool => $block !== ''));
}

function parseChangelogBlock(string $block): ?array
{
    $block = trim($block);
    if ($block === '') {
        return null;
    }

    $lines = preg_split('/\R/', $block) ?: [];
    $meta = [];
    $bodyLines = [];
    $inBody = false;

    foreach ($lines as $line) {
        if (!$inBody && preg_match('/^([a-z_]+):\s*(.*)$/i', $line, $match) === 1) {
            $meta[strtolower((string) $match[1])] = trim((string) $match[2]);
            continue;
        }

        if (!$inBody && trim($line) === '') {
            $inBody = true;
            continue;
        }

        $inBody = true;
        $bodyLines[] = $line;
    }

    $id = trim((string) ($meta['id'] ?? ''));
    if ($id === '') {
        return null;
    }

    $date = trim((string) ($meta['date'] ?? ''));
    $timestamp = strtotime($date) ?: 0;

    return [
        'id' => $id,
        'date' => $date,
        'date_ts' => $timestamp,
        'title' => trim((string) ($meta['title'] ?? '')),
        'author' => trim((string) ($meta['author'] ?? '')),
        'body' => trim(implode("\n", $bodyLines)),
    ];
}

function loadChangelogEntries(string $lang): array
{
    $path = getChangelogFilePath($lang);
    if (!is_file($path)) {
        return [];
    }

    $content = (string) file_get_contents($path);
    $blocks = splitChangelogContent($content);
    $entries = [];

    foreach ($blocks as $block) {
        $parsed = parseChangelogBlock($block);
        if ($parsed === null) {
            continue;
        }

        $entries[$parsed['id']] = $parsed;
    }

    uasort($entries, static function (array $left, array $right): int {
        $leftTs = (int) ($left['date_ts'] ?? 0);
        $rightTs = (int) ($right['date_ts'] ?? 0);
        if ($leftTs !== $rightTs) {
            return $rightTs <=> $leftTs;
        }

        return strcmp((string) ($right['id'] ?? ''), (string) ($left['id'] ?? ''));
    });

    return array_values($entries);
}

function loadChangelogReadIds(string $email): array
{
    $saved = loadUserPrefs($email)['changelog_read_ids'] ?? [];
    if (!is_array($saved)) {
        return [];
    }

    $normalized = [];
    foreach ($saved as $entryId) {
        $id = trim((string) $entryId);
        if ($id !== '') {
            $normalized[$id] = $id;
        }
    }

    return array_values($normalized);
}

function saveChangelogReadIds(string $email, array $entryIds): void
{
    $normalized = [];
    foreach ($entryIds as $entryId) {
        $id = trim((string) $entryId);
        if ($id !== '') {
            $normalized[$id] = $id;
        }
    }

    saveUserPref($email, 'changelog_read_ids', array_values($normalized));
}

function markChangelogEntryRead(string $email, string $entryId): array
{
    $entryId = trim($entryId);
    if ($entryId === '') {
        return loadChangelogReadIds($email);
    }

    $readIds = loadChangelogReadIds($email);
    if (!in_array($entryId, $readIds, true)) {
        $readIds[] = $entryId;
        saveChangelogReadIds($email, $readIds);
    }

    return $readIds;
}

function markAllChangelogEntriesRead(string $email, array $entryIds): array
{
    $readIds = loadChangelogReadIds($email);
    $lookup = array_fill_keys($readIds, true);

    foreach ($entryIds as $entryId) {
        $id = trim((string) $entryId);
        if ($id !== '') {
            $lookup[$id] = $id;
        }
    }

    $merged = array_values($lookup);
    saveChangelogReadIds($email, $merged);

    return $merged;
}

function formatChangelogDate(string $isoDate, string $lang): string
{
    $timestamp = strtotime($isoDate);
    if ($timestamp === false) {
        return $isoDate;
    }

    $day = (int) date('j', $timestamp);
    $year = (int) date('Y', $timestamp);
    $monthIndex = (int) date('n', $timestamp) - 1;

    $monthNames = [
        'nl' => ['jan.', 'feb.', 'mrt.', 'apr.', 'mei', 'jun.', 'jul.', 'aug.', 'sep.', 'okt.', 'nov.', 'dec.'],
        'en' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        'de' => ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.'],
        'fr' => ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'],
    ];

    $months = $monthNames[$lang] ?? $monthNames['en'];
    $month = $months[$monthIndex] ?? $months[0];

    return $day . ' ' . $month . ' ' . $year;
}

function linkifyChangelogText(string $escapedText): string
{
    $linked = preg_replace_callback(
        '~(?:(https?://|www\.)[^\s<]+)~i',
        static function (array $matches): string {
            $displayValue = $matches[0];
            $href = str_starts_with(strtolower($displayValue), 'www.') ? 'https://' . $displayValue : $displayValue;

            return '<a href="' . h($href) . '" target="_blank" rel="noopener noreferrer">' . h($displayValue) . '</a>';
        },
        $escapedText
    ) ?? $escapedText;

    return preg_replace(
        '/(?<![\w.@])([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})(?![^<]*>)/i',
        '<a href="mailto:$1">$1</a>',
        $linked
    ) ?? $linked;
}

function formatChangelogInlineMarkdown(string $text): string
{
    $escaped = h($text);

    $escaped = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);
    $escaped = (string) preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped);
    $escaped = (string) preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped);
    $escaped = (string) preg_replace_callback(
        '/\[([^\]]+)\]\(([^)]+)\)/',
        static function (array $match): string {
            $label = h((string) $match[1]);
            $url = h((string) $match[2]);

            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $label . '</a>';
        },
        $escaped
    );

    return linkifyChangelogText($escaped);
}

function formatChangelogBodyHtml(string $body): string
{
    $normalized = trim(str_replace(["\r\n", "\r"], "\n", $body));
    if ($normalized === '') {
        return '';
    }

    $lines = explode("\n", $normalized);
    $htmlParts = [];
    $index = 0;
    $lineCount = count($lines);

    while ($index < $lineCount) {
        $line = (string) $lines[$index];
        $trimmed = trim($line);

        if ($trimmed === '') {
            $index++;
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $headingMatch) === 1) {
            $level = strlen((string) $headingMatch[1]);
            $tag = $level === 1 ? 'h3' : ($level === 2 ? 'h4' : 'h5');
            $htmlParts[] = '<' . $tag . ' class="changelog-heading changelog-heading-' . $level . '">'
                . formatChangelogInlineMarkdown((string) $headingMatch[2])
                . '</' . $tag . '>';
            $index++;
            continue;
        }

        if (preg_match('/^[-*]\s+/', $trimmed) === 1) {
            $items = [];
            while ($index < $lineCount) {
                $listLine = trim((string) $lines[$index]);
                if ($listLine === '' || preg_match('/^[-*]\s+/', $listLine) !== 1) {
                    break;
                }

                $items[] = '<li>' . formatChangelogInlineMarkdown((string) preg_replace('/^[-*]\s+/', '', $listLine)) . '</li>';
                $index++;
            }

            $htmlParts[] = '<ul class="changelog-body-list">' . implode('', $items) . '</ul>';
            continue;
        }

        if (preg_match('/^\d+\.\s+/', $trimmed) === 1) {
            $items = [];
            while ($index < $lineCount) {
                $listLine = trim((string) $lines[$index]);
                if ($listLine === '' || preg_match('/^\d+\.\s+/', $listLine) !== 1) {
                    break;
                }

                $items[] = '<li>' . formatChangelogInlineMarkdown((string) preg_replace('/^\d+\.\s+/', '', $listLine)) . '</li>';
                $index++;
            }

            $htmlParts[] = '<ol class="changelog-body-list changelog-body-list-ordered">' . implode('', $items) . '</ol>';
            continue;
        }

        $paragraphLines = [];
        while ($index < $lineCount) {
            $paragraphLine = (string) $lines[$index];
            $paragraphTrimmed = trim($paragraphLine);
            if ($paragraphTrimmed === '') {
                break;
            }

            if (
                preg_match('/^(#{1,3})\s+/', $paragraphTrimmed) === 1
                || preg_match('/^[-*]\s+/', $paragraphTrimmed) === 1
                || preg_match('/^\d+\.\s+/', $paragraphTrimmed) === 1
            ) {
                break;
            }

            $paragraphLines[] = $paragraphTrimmed;
            $index++;
        }

        $htmlParts[] = '<p>' . formatChangelogInlineMarkdown(implode(' ', $paragraphLines)) . '</p>';
    }

    return implode('', $htmlParts);
}

function renderChangelogEntryHtml(array $entry, string $lang, bool $isRead): string
{
    $entryId = (string) ($entry['id'] ?? '');
    $title = (string) ($entry['title'] ?? '');
    $dateLabel = formatChangelogDate((string) ($entry['date'] ?? ''), $lang);
    $author = (string) ($entry['author'] ?? '');
    $bodyHtml = formatChangelogBodyHtml((string) ($entry['body'] ?? ''));

    ob_start();
    ?>
    <details class="changelog-entry<?= $isRead ? ' is-read' : ' is-unread' ?>" data-changelog-entry
        data-changelog-id="<?= h($entryId) ?>" data-changelog-read="<?= $isRead ? '1' : '0' ?>">
        <summary class="changelog-entry-summary">
            <span class="changelog-entry-title"><?= h($title) ?></span>
            <span class="changelog-entry-date"><?= h($dateLabel) ?></span>
            <?php if (!$isRead): ?>
                <span class="changelog-entry-badge" aria-hidden="true"></span>
            <?php endif; ?>
        </summary>
        <div class="changelog-entry-body">
            <?= $bodyHtml ?>
            <?php if ($author !== ''): ?>
                <p class="changelog-entry-author"><?= h(__('changelog.author', $author)) ?></p>
            <?php endif; ?>
        </div>
    </details>
    <?php

    return trim((string) ob_get_clean());
}
