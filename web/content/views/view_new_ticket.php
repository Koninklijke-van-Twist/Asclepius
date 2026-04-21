<?php if (!$isAdminPortal): ?>
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
                    <input type="text" name="title" maxlength="150" placeholder="<?= h(__('new_ticket.title_placeholder')) ?>"
                        required>
                </label>
                <label>
                    <?= h(__('new_ticket.category_label')) ?>
                    <select name="category" required>
                        <option value=""><?= h(__('new_ticket.category_option')) ?></option>
                        <?php foreach (TICKET_CATEGORIES as $category): ?>
                            <option value="<?= h($category) ?>"><?= h(translateCategory($category)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label>
                <?= h(__('new_ticket.description_label')) ?>
                <textarea name="description" placeholder="<?= h(__('new_ticket.description_placeholder')) ?>"
                    required></textarea>
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
                    <?= h(__('new_ticket.requester_label')) ?>
                    <input type="email" name="requester_email" maxlength="200"
                        placeholder="<?= h(__('new_ticket.requester_placeholder')) ?>">
                    <span class="hint"><?= h(__('new_ticket.requester_hint')) ?></span>
                </label>
            <?php endif; ?>

            <label>
                <?= h(__('new_ticket.attachments_label')) ?>
                <input type="file" name="ticket_attachments[]" multiple>
                <span class="hint"><?= h(__('ticket.file_hint')) ?></span>
            </label>

            <div class="button-row">
                <button type="submit"><?= h(__('new_ticket.btn_submit')) ?></button>
            </div>
        </form>
    </section>
<?php endif; ?>