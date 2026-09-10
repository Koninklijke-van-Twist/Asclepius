<?php if (empty($isBigscreen)): ?>
<div class="theevraagje-overlay" data-role="theevraagje-modal" hidden aria-hidden="true">
    <div class="theevraagje-dialog" role="dialog" aria-modal="true" aria-labelledby="theevraagje-title">
        <div class="theevraagje-head">
            <h2 id="theevraagje-title"><?= h(__('theevraagje.title')) ?></h2>
            <button type="button" class="theevraagje-close" data-role="theevraagje-close"
                aria-label="<?= h(__('theevraagje.close')) ?>">&times;</button>
        </div>
        <div class="theevraagje-body">
            <div class="theevraagje-image-pane">
                <img class="theevraagje-image" data-role="theevraagje-image" alt="<?= h(__('theevraagje.title')) ?>" hidden>
                <p class="theevraagje-image-fallback hint" data-role="theevraagje-image-fallback" hidden>
                    <?= h(__('theevraagje.image_missing')) ?>
                </p>
            </div>
            <div class="theevraagje-chat ponos-detail-chat">
                <h3 class="ponos-detail-chat-title"><?= h(__('theevraagje.messages')) ?></h3>
                <div class="ponos-messages" data-role="theevraagje-messages" aria-live="polite"></div>
                <p class="hint theevraagje-empty" data-role="theevraagje-empty" hidden><?= h(__('theevraagje.empty')) ?></p>
                <div class="ponos-message-compose">
                    <label class="ponos-compose-label" for="theevraagje-message-text"><?= h(__('theevraagje.message_label')) ?></label>
                    <textarea id="theevraagje-message-text" class="ponos-message-input" data-role="theevraagje-input"
                        rows="1" maxlength="4000"></textarea>
                </div>
            </div>
        </div>
        <p class="hint theevraagje-feedback" data-role="theevraagje-feedback" hidden></p>
    </div>
</div>
<?php endif; ?>
