<?php if (!$isAdminPortal && $view !== 'all_tickets'): ?>
    <section class="panel">
        <h2><?= h(__('new_ticket.heading')) ?></h2>
        <p class="panel-intro"><?= h(__('new_ticket.intro')) ?></p>
        <form method="post" action="<?= h($currentPage) ?>" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="form_action" value="create_ticket">
            <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">

            <div class="form-grid two-columns">
                <label>
                    <?= h(__('new_ticket.title_label')) ?>
                    <input type="text" id="title" name="title" maxlength="150"
                        placeholder="<?= h(__('new_ticket.title_placeholder')) ?>" required>
                </label>
                <label>
                    <?= h(__('new_ticket.category_label')) ?>
                    <select id="category" name="category" required>
                        <option value=""><?= h(__('new_ticket.category_option')) ?></option>
                        <?php foreach (getSelectableTicketCategories() as $category): ?>
                            <option value="<?= h($category) ?>"><?= h(translateCategory($category)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label>
                <?= h(__('new_ticket.description_label')) ?>
                <div class="textarea-wrapper">
                    <textarea id="description" name="description" placeholder="<?= h(__('new_ticket.description_placeholder')) ?>"
                        required></textarea>
                    <button type="button" class="key-picker-toggle" title="<?= h(__('ticket.key_picker_tooltip')) ?>"
                        aria-label="<?= h(__('ticket.key_picker_tooltip')) ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="2" y="6" width="20" height="12" rx="2" />
                            <path d="M6 10h.01M10 10h.01M14 10h.01M18 10h.01M6 14h.01M18 14h.01M10 14h4" />
                        </svg>
                    </button>
                    <div class="key-picker-popup" hidden aria-label="<?= h(__('ticket.key_picker_tooltip')) ?>"></div>
                </div>
            </label>

            <div class="checkbox-stack">
                <label class="checkbox-line">
                    <input type="checkbox" name="priority_blocked" id="priority_blocked" value="1">
                    <span><?= h(__('new_ticket.blocked')) ?></span>
                </label>
                <label class="checkbox-line" id="priority_fully_blocked_wrap" hidden>
                    <input type="checkbox" name="priority_fully_blocked" id="priority_fully_blocked" value="1">
                    <span><?= h(__('new_ticket.fully_blocked')) ?></span>
                </label>
            </div>

            <?php if ($userIsAdmin): ?>
                <label>
                    <?= h(__('new_ticket.requester_list_label')) ?>
                    <input type="text" name="requester_emails" maxlength="1200" data-email-chip-input="1"
                        placeholder="<?= h(__('new_ticket.requester_placeholder')) ?>">
                    <span class="hint"><?= h(__('new_ticket.requester_list_hint')) ?></span>
                </label>
            <?php else: ?>
                <label>
                    <?= h(__('new_ticket.participants_label')) ?>
                    <input type="text" name="participant_emails" maxlength="1200" data-email-chip-input="1"
                        placeholder="<?= h(__('new_ticket.participants_placeholder')) ?>">
                    <span class="hint"><?= h(__('new_ticket.participants_hint')) ?></span>
                </label>
            <?php endif; ?>

            <label>
                <?= h(__('new_ticket.attachments_label')) ?>
                <input type="file" name="ticket_attachments[]" multiple data-accumulate-files="1">
                <ul class="draft-attachments-list" data-draft-attachments-list hidden></ul>
                <span class="hint"><?= h(__('ticket.file_hint')) ?></span>
            </label>

            <div class="button-row">
                <button type="submit"><?= h(__('new_ticket.btn_submit')) ?></button>
            </div>
        </form>
    </section>
    <aside class="tips-sidebar" id="new-ticket-tips-sidebar" hidden>
        <div class="tip-card" id="tip-clipboard-paste" data-tip-id="clipboard_paste" hidden>
            <p class="tips-sidebar-heading"><?= h(__('tips.heading')) ?></p>
            <p class="tip-card-body"><?= __('tips.clipboard_paste') ?></p>
            <button type="button" class="tip-dismiss-btn" data-tip-dismiss="clipboard_paste"><?= h(__('tips.dismiss')) ?></button>
        </div>
        <div class="tip-card" id="tip-category-match" data-tip-id="category_match" hidden>
            <p class="tips-sidebar-heading"><?= h(__('tips.heading')) ?></p>
            <p class="tip-card-body" data-tip-template="<?= h(__('tips.category_match')) ?>"></p>
            <button type="button" class="tip-dismiss-btn" data-tip-dismiss="category_match"><?= h(__('tips.dismiss')) ?></button>
        </div>
        <div class="tip-card" id="tip-priority-high" data-tip-id="priority_high" hidden>
            <p class="tips-sidebar-heading"><?= h(__('tips.heading')) ?></p>
            <p class="tip-card-body"><?= h(__('tips.priority_high')) ?></p>
            <button type="button" class="tip-dismiss-btn" data-tip-dismiss="priority_high"><?= h(__('tips.dismiss')) ?></button>
        </div>
        <div class="tip-card" id="tip-possible-duplicate" data-tip-id="possible_duplicate" hidden>
            <p class="tips-sidebar-heading"><?= h(__('tips.heading')) ?></p>
            <p class="tip-card-body"><?= h(__('tips.possible_duplicate')) ?></p>
            <button type="button" class="tip-dismiss-btn" data-tip-dismiss="possible_duplicate"><?= h(__('tips.dismiss')) ?></button>
        </div>
    </aside>
    <aside class="related-tickets-sidebar" id="related-tickets-sidebar" hidden>
        <h2 class="related-tickets-heading"><?= h(__('new_ticket.related_heading')) ?></h2>
        <p class="related-tickets-intro"><?= h(__('new_ticket.related_intro')) ?></p>
        <ul class="related-tickets-list" data-role="related-tickets-list"></ul>
    </aside>
    <div class="ticket-participants-modal" data-role="related-ticket-modal" hidden>
        <div class="ticket-participants-modal-card related-ticket-modal-card">
            <div class="ticket-participants-modal-head">
                <h3><?= h(__('new_ticket.related_heading')) ?></h3>
                <button type="button" class="participant-modal-close" data-role="related-ticket-close"
                    aria-label="<?= h(__('ticket.preview_close')) ?>">&times;</button>
            </div>
            <div class="related-ticket-modal-body" data-role="related-ticket-modal-body">
                <p class="related-ticket-modal-loading"><?= h(__('new_ticket.related_loading')) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>
