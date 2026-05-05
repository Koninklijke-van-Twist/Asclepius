<?php if ($canManageTickets && $view === 'template_tickets'): ?>
    <section class="panel">
        <h2><?= h(__('template_ticket.heading')) ?></h2>
        <p class="panel-intro"><?= h(__('template_ticket.intro')) ?></p>

        <div class="template-ticket-layout">
            <div class="template-ticket-left">
                <form method="post" action="admin.php?view=template_tickets" class="form-grid" id="template-ticket-create-form">
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
                        <input type="date" name="due_date" id="template_ticket_due_date" required min="<?= h(date('Y-m-d')) ?>">
                    </label>

                    <label>
                        <?= h(__('template_ticket.title_label')) ?>
                        <input type="text" name="title" id="template_ticket_title" maxlength="150"
                            placeholder="<?= h(__('template_ticket.title_placeholder')) ?>" required>
                    </label>

                    <label>
                        <?= h(__('template_ticket.preview_label')) ?>
                        <textarea id="template_ticket_preview" readonly></textarea>
                        <span class="hint"><?= h(__('template_ticket.preview_hint')) ?></span>
                    </label>

                    <div class="button-row">
                        <button type="submit"><?= h(__('template_ticket.create_button')) ?></button>
                    </div>
                </form>
            </div>

            <div class="template-ticket-right">
                <form method="post" action="admin.php?view=template_tickets" class="form-grid template-editor-form">
                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                    <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">

                    <?php if ($editingTemplateFragment !== null): ?>
                        <input type="hidden" name="form_action" value="update_ticket_template">
                        <input type="hidden" name="template_id" value="<?= (int) ($editingTemplateFragment['id'] ?? 0) ?>">

                        <h3><?= h(__('template_ticket.edit_heading')) ?></h3>

                        <label>
                            <?= h(__('template_ticket.template_name_label')) ?>
                            <input type="text" name="template_name" maxlength="120" required
                                value="<?= h((string) ($editingTemplateFragment['name'] ?? '')) ?>">
                        </label>

                        <label>
                            <?= h(__('template_ticket.template_body_label')) ?>
                            <textarea name="template_body" required><?= h((string) ($editingTemplateFragment['body'] ?? '')) ?></textarea>
                        </label>

                        <div class="button-row">
                            <button type="submit"><?= h(__('template_ticket.save_template_button')) ?></button>
                            <button type="submit" name="form_action" value="delete_ticket_template"
                                onclick="return confirm('<?= h(__('template_ticket.delete_confirm')) ?>');"
                                class="danger-button"><?= h(__('template_ticket.delete_template_button')) ?></button>
                            <a class="secondary-button" href="admin.php?view=template_tickets"><?= h(__('template_ticket.back_to_list')) ?></a>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="form_action" value="create_ticket_template">

                        <h3><?= h(__('template_ticket.create_template_heading')) ?></h3>

                        <label>
                            <?= h(__('template_ticket.template_name_label')) ?>
                            <input type="text" name="template_name" maxlength="120" required
                                placeholder="<?= h(__('template_ticket.template_name_placeholder')) ?>">
                        </label>

                        <label>
                            <?= h(__('template_ticket.template_body_label')) ?>
                            <textarea name="template_body" required
                                placeholder="<?= h(__('template_ticket.template_body_placeholder')) ?>"></textarea>
                        </label>

                        <div class="button-row">
                            <button type="submit"><?= h(__('template_ticket.create_template_button')) ?></button>
                        </div>
                    <?php endif; ?>
                </form>

                <h3><?= h(__('template_ticket.templates_heading')) ?></h3>
                <?php if ($templateFragments === []): ?>
                    <p class="hint"><?= h(__('template_ticket.empty_templates')) ?></p>
                <?php else: ?>
                    <div class="template-fragment-list" id="template_fragment_list">
                        <?php foreach ($templateFragments as $templateFragment):
                            $templateId = (int) ($templateFragment['id'] ?? 0);
                            $templateName = (string) ($templateFragment['name'] ?? '');
                            $templateBody = (string) ($templateFragment['body'] ?? '');
                            ?>
                            <label class="template-fragment-item">
                                <input type="checkbox" class="template-fragment-checkbox"
                                    value="<?= $templateId ?>"
                                    data-template-body="<?= h((string) json_encode($templateBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                                    data-template-name="<?= h($templateName) ?>">
                                <span class="template-fragment-name"><?= h($templateName) ?></span>
                                <a class="secondary-button"
                                    href="admin.php?view=template_tickets&amp;edit_template=<?= $templateId ?>"><?= h(__('template_ticket.edit_template_button')) ?></a>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
