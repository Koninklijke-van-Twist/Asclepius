<?php
if (!empty($isBigscreen)) {
    return;
}
$presenceRows = is_array($janusPresenceRows ?? null) ? $janusPresenceRows : [];
$presenceGroups = mapJanusPresenceGroupsForDisplay(groupJanusPresenceRows(
    $presenceRows,
    ($store ?? null) instanceof TicketStore ? $store : null,
    is_array($ictUsers ?? null) ? $ictUsers : []
));
$presenceHasPeople = false;
foreach ($presenceGroups as $presenceGroup) {
    if (($presenceGroup['rows'] ?? []) !== []) {
        $presenceHasPeople = true;
        break;
    }
}
$presenceConnected = !empty($janusPresenceConnected);
$presenceViewerEmail = strtolower(trim((string) ($userEmail ?? '')));
$presenceViewerIsIct = !empty($userIsAdmin);
$presenceViewerListed = false;
if ($presenceViewerEmail !== '') {
    foreach ($presenceRows as $presenceRow) {
        if (strtolower(trim((string) ($presenceRow['email'] ?? ''))) === $presenceViewerEmail) {
            $presenceViewerListed = true;
            break;
        }
    }
}
$janusPresenceUrl = '../janus/';
?>
<aside class="presence-sidebar<?= ($presenceViewerIsIct && !$presenceViewerListed) ? ' is-joinable' : '' ?>"
    id="presence-sidebar"
    aria-label="<?= h(__('presence.heading')) ?>"
    data-presence-connected="<?= $presenceConnected ? '1' : '0' ?>"
    data-viewer-email="<?= h($presenceViewerEmail) ?>"
    data-viewer-is-ict="<?= $presenceViewerIsIct ? '1' : '0' ?>"
    data-viewer-listed="<?= $presenceViewerListed ? '1' : '0' ?>"
    data-join-hint-title="<?= h(__('presence.join_hint_title')) ?>"
    <?= ($presenceViewerIsIct && !$presenceViewerListed) ? ' role="button" tabindex="0" title="' . h(__('presence.join_hint_title')) . '"' : '' ?>>
    <h2 class="presence-heading"><?= h(__('presence.heading')) ?></h2>
    <?php if (!$presenceConnected): ?>
        <p class="presence-empty" data-presence-empty><?= h(__('presence.unavailable')) ?></p>
        <ul class="presence-list" id="presence-list" hidden></ul>
    <?php elseif (!$presenceHasPeople): ?>
        <p class="presence-empty" data-presence-empty><?= h(__('presence.empty')) ?></p>
        <ul class="presence-list" id="presence-list" hidden></ul>
    <?php else: ?>
        <p class="presence-empty" data-presence-empty hidden><?= h(__('presence.empty')) ?></p>
        <ul class="presence-list" id="presence-list">
            <?php foreach ($presenceGroups as $presenceGroup): ?>
                <?php if (trim((string) ($presenceGroup['name'] ?? '')) !== '' && ($presenceGroup['rows'] ?? []) !== []): ?>
                    <li class="presence-group"><?= h((string) $presenceGroup['name']) ?></li>
                <?php endif; ?>
                <?php foreach ($presenceGroup['rows'] as $row):
                    $email = strtolower((string) ($row['email'] ?? ''));
                    $status = (string) ($row['status'] ?? '');
                    $label = (string) ($row['label'] ?? '');
                    $detail = (string) ($row['detail'] ?? '');
                    $displayName = (string) ($row['name'] ?? $email);
                    ?>
                    <li class="presence-item presence-status-<?= h($status) ?>" data-presence-email="<?= h($email) ?>">
                        <span class="presence-dot" aria-hidden="true"></span>
                        <span class="presence-meta">
                            <span class="presence-name" title="<?= h($email) ?>"><?= h($displayName) ?></span>
                            <span class="presence-status"><?= h($label) ?></span>
                            <?php if ($detail !== ''): ?>
                                <span class="presence-detail"><?= h($detail) ?></span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</aside>

<?php if ($presenceViewerIsIct): ?>
    <div class="ticket-participants-modal" data-role="presence-join-modal" hidden>
        <div class="ticket-participants-modal-card role-confirm-card">
            <div class="ticket-participants-modal-head">
                <h3><?= h(__('presence.join_modal_title')) ?></h3>
                <button type="button" class="participant-modal-close" data-role="presence-join-close"
                    aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
            </div>
            <p class="role-confirm-copy"><?= h(__('presence.join_modal_intro')) ?></p>
            <p class="role-confirm-copy"><?= h(__('presence.join_modal_how')) ?></p>
            <div class="role-modal-actions">
                <button type="button" class="secondary-button" data-role="presence-join-close"><?= h(__('presence.join_modal_close')) ?></button>
                <a class="primary-button" href="<?= h($janusPresenceUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h(__('presence.join_modal_open_janus')) ?></a>
            </div>
        </div>
    </div>
<?php endif; ?>
