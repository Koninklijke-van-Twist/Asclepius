<div class="ticket-participants-modal" data-role="publish-ghost-modal" hidden>
    <div class="ticket-participants-modal-card role-confirm-card publish-ghost-card">
        <div class="ticket-participants-modal-head">
            <h3><?= h(__('ticket.publish_ghost_heading')) ?></h3>
            <button type="button" class="participant-modal-close" data-role="publish-ghost-close"
                aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
        </div>
        <label>
            <?= h(__('ticket.publish_ghost_text_label')) ?>
            <textarea data-role="publish-ghost-text" rows="6"></textarea>
        </label>
        <p class="role-confirm-copy"><?= h(__('ticket.publish_ghost_confirm')) ?></p>
        <div class="role-modal-actions">
            <button type="button" class="secondary-button" data-role="publish-ghost-cancel">
                <?= h(__('ticket.publish_ghost_no')) ?>
            </button>
            <button type="button" data-role="publish-ghost-confirm">
                <?= h(__('ticket.publish_ghost_yes')) ?>
            </button>
        </div>
    </div>
</div>
