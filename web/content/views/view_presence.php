<?php
if (!empty($isBigscreen)) {
    return;
}
$presenceRows = is_array($janusPresenceRows ?? null) ? $janusPresenceRows : [];
$presenceConnected = !empty($janusPresenceConnected);
?>
<aside class="presence-sidebar" id="presence-sidebar" aria-label="<?= h(__('presence.heading')) ?>"
    data-presence-connected="<?= $presenceConnected ? '1' : '0' ?>">
    <h2 class="presence-heading"><?= h(__('presence.heading')) ?></h2>
    <?php if (!$presenceConnected): ?>
        <p class="presence-empty" data-presence-empty><?= h(__('presence.unavailable')) ?></p>
        <ul class="presence-list" id="presence-list" hidden></ul>
    <?php elseif ($presenceRows === []): ?>
        <p class="presence-empty" data-presence-empty><?= h(__('presence.empty')) ?></p>
        <ul class="presence-list" id="presence-list" hidden></ul>
    <?php else: ?>
        <p class="presence-empty" data-presence-empty hidden><?= h(__('presence.empty')) ?></p>
        <ul class="presence-list" id="presence-list">
            <?php foreach ($presenceRows as $row):
                $email = strtolower((string) ($row['email'] ?? ''));
                $status = (string) ($row['status'] ?? '');
                $label = janusPresenceStatusLabel($status);
                $detail = janusPresenceStatusDetail($row);
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
        </ul>
    <?php endif; ?>
</aside>
