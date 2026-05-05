<?php if ($canManageTickets && $view === 'template_tickets'): ?>
    <section class="panel">
        <h2><?= h(__('template_ticket.heading')) ?></h2>
        <p class="panel-intro"><?= h(__('template_ticket.intro')) ?></p>

        <div class="template-ticket-layout">
            <div class="template-ticket-left">
                <form method="post" action="admin.php?view=template_tickets" class="form-grid"
                    id="template-ticket-create-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="form_action" value="create_template_ticket">
                    <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                    <input type="hidden" name="selected_template_ids" id="selected_template_ids" value="">

                    <label>
                        <?= h(__('template_ticket.assignee_label')) ?>
                        <select name="assigned_email" id="template_ticket_assigned_email">
                            <option value=""><?= h(__('template_ticket.assignee_auto')) ?></option>
                            <?php foreach ($ictUsers as $ictUser):
                                $ictUser = strtolower((string) $ictUser); ?>
                                <option value="<?= h($ictUser) ?>"><?= h($ictUser) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <?= h(__('template_ticket.due_date_label')) ?>
                        <input type="date" name="due_date" id="template_ticket_due_date" required
                            min="<?= h(date('Y-m-d')) ?>">
                    </label>

                    <label>
                        <?= h(__('template_ticket.title_label')) ?>
                        <input type="text" name="title" id="template_ticket_title" maxlength="150"
                            placeholder="<?= h(__('template_ticket.title_placeholder')) ?>" required>
                    </label>

                    <label>
                        <?= h(__('template_ticket.preview_label')) ?>
                        <div id="template_ticket_preview_rendered" class="template-preview-rendered"></div>
                        <span class="hint"><?= h(__('template_ticket.preview_hint')) ?></span>
                    </label>

                    <div class="button-row">
                        <button type="submit"><?= h(__('template_ticket.create_button')) ?></button>
                    </div>
                </form>
            </div>

            <div class="template-ticket-right">
                <div class="template-ticket-right-header">
                    <h3><?= h(__('template_ticket.templates_heading')) ?></h3>
                    <button type="button" class="secondary-button"
                        id="template_fragment_new_btn"><?= h(__('template_ticket.new_template_button')) ?></button>
                </div>

                <?php if ($templateFragments === []): ?>
                    <p class="hint" id="template_fragment_empty"><?= h(__('template_ticket.empty_templates')) ?></p>
                <?php else: ?>
                    <p class="hint" id="template_fragment_empty" hidden><?= h(__('template_ticket.empty_templates')) ?></p>
                <?php endif; ?>

                <div class="template-fragment-list" id="template_fragment_list">
                    <?php foreach ($templateFragments as $templateFragment):
                        $templateId = (int) ($templateFragment['id'] ?? 0);
                        $templateName = (string) ($templateFragment['name'] ?? '');
                        $templateBody = (string) ($templateFragment['body'] ?? '');
                        ?>
                        <label class="template-fragment-item">
                            <span class="template-drag-handle" aria-hidden="true">&#8597;</span>
                            <input type="checkbox" class="template-fragment-checkbox" value="<?= $templateId ?>"
                                data-template-body="<?= h((string) json_encode($templateBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                data-template-name="<?= h($templateName) ?>">
                            <span class="template-fragment-name"><?= h($templateName) ?></span>
                            <button type="button" class="secondary-button template-fragment-edit-btn"
                                data-template-id="<?= $templateId ?>" data-template-name="<?= h($templateName) ?>"
                                data-template-body="<?= h($templateBody) ?>"><?= h(__('template_ticket.edit_template_button')) ?></button>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="template-fragment-modal" id="template_fragment_modal" hidden>
        <div class="template-fragment-modal-card" role="dialog" aria-modal="true"
            aria-labelledby="template_fragment_modal_title">
            <div class="template-fragment-modal-head">
                <h3 id="template_fragment_modal_title"><?= h(__('template_ticket.create_template_heading')) ?></h3>
                <button type="button" class="participant-modal-close" id="template_fragment_modal_close"
                    aria-label="<?= h(__('template_ticket.modal_close')) ?>">&times;</button>
            </div>
            <div id="template_fragment_modal_error" class="flash error" hidden></div>
            <label>
                <?= h(__('template_ticket.template_name_label')) ?>
                <input type="text" id="template_fragment_modal_name" maxlength="120"
                    placeholder="<?= h(__('template_ticket.template_name_placeholder')) ?>">
            </label>
            <label>
                <?= h(__('template_ticket.template_body_label')) ?>
                <div class="textarea-wrapper">
                    <textarea id="template_fragment_modal_body"
                        placeholder="<?= h(__('template_ticket.template_body_placeholder')) ?>"></textarea>
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
            <div class="button-row">
                <button type="button" id="template_fragment_modal_save"
                    data-label-create="<?= h(__('template_ticket.create_template_button')) ?>"
                    data-label-save="<?= h(__('template_ticket.save_template_button')) ?>"
                    data-csrf="<?= h($csrfToken) ?>"><?= h(__('template_ticket.create_template_button')) ?></button>
                <button type="button" id="template_fragment_modal_delete" class="danger-button" hidden
                    data-confirm="<?= h(__('template_ticket.delete_confirm')) ?>"><?= h(__('template_ticket.delete_template_button')) ?></button>
            </div>
        </div>
    </div>
<?php endif; ?>