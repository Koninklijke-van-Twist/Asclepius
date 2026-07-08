<div class="ticket-participants-modal" data-role="ticket-share-modal" hidden>
    <div class="ticket-participants-modal-card ticket-share-modal-card">
        <div class="ticket-participants-modal-head">
            <h3><?= h(__('ticket.share_link_copied')) ?></h3>
            <button type="button" class="participant-modal-close" data-role="ticket-share-close"
                aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
        </div>
        <p class="hint"><?= h(__('ticket.share_link_hint')) ?></p>
        <label class="ticket-share-url-field">
            <?= h(__('ticket.share_link_field_label')) ?>
            <input type="text" readonly data-role="ticket-share-url-input" class="ticket-share-url-input"
                onclick="this.select()">
        </label>
    </div>
</div>
