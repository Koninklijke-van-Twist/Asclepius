<?php if ($canManageTickets && $view === 'changelog'): ?>
    <?php
    $changelogReadLookup = array_fill_keys($changelogReadIds, true);
    $changelogUnreadEntries = [];
    $changelogReadEntries = [];

    foreach ($changelogEntries as $changelogEntry) {
        $entryId = (string) ($changelogEntry['id'] ?? '');
        if (isset($changelogReadLookup[$entryId])) {
            $changelogReadEntries[] = $changelogEntry;
        } else {
            $changelogUnreadEntries[] = $changelogEntry;
        }
    }
    ?>
    <section class="panel" data-changelog-section
        data-changelog-ids="<?= h((string) json_encode(array_map(static fn(array $entry): string => (string) ($entry['id'] ?? ''), $changelogEntries), JSON_UNESCAPED_UNICODE)) ?>"
        data-viewer-email="<?= h($userEmail) ?>"
        data-user-is-admin="<?= $userIsAdmin ? '1' : '0' ?>">
        <div class="changelog-toolbar">
            <div>
                <h2><?= h(__('changelog.heading')) ?></h2>
                <p class="panel-intro"><?= h(__('changelog.intro')) ?></p>
            </div>
            <button type="button" class="changelog-mark-all-button" data-changelog-mark-all
                <?= $changelogUnreadEntries === [] ? 'hidden' : '' ?>>
                <?= h(__('changelog.mark_all_read')) ?>
            </button>
        </div>
        <p class="hint changelog-feedback" data-changelog-feedback hidden></p>

        <div class="changelog-unread-list" data-changelog-unread-list>
            <?php if ($changelogUnreadEntries === []): ?>
                <p class="changelog-empty" data-changelog-empty><?= h(__('changelog.no_unread')) ?></p>
            <?php else: ?>
                <?php foreach ($changelogUnreadEntries as $changelogEntry): ?>
                    <?= renderChangelogEntryHtml($changelogEntry, getCurrentLanguage(), false) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="changelog-read-section" data-changelog-read-section hidden>
            <div class="changelog-read-list" data-changelog-read-list>
                <?php foreach ($changelogReadEntries as $changelogEntry): ?>
                    <?= renderChangelogEntryHtml($changelogEntry, getCurrentLanguage(), true) ?>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="button-row changelog-footer-actions" data-changelog-footer-actions
            <?= $changelogReadEntries === [] ? 'hidden' : '' ?>>
            <button type="button" class="changelog-toggle-read-button" data-changelog-toggle-read
                data-label-show="<?= h(__('changelog.show_read')) ?>"
                data-label-hide="<?= h(__('changelog.hide_read')) ?>">
                <?= h(__('changelog.show_read')) ?>
            </button>
        </div>
    </section>
<?php endif; ?>
