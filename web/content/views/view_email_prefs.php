<?php if ($canManageTickets && $view === 'preferences'):
    $appearance = is_array($ticketAppearancePreferences ?? null)
        ? $ticketAppearancePreferences
        : getDefaultTicketAppearancePreferences();
    $previewAssignee = strtolower(trim((string) $userEmail));
    if ($previewAssignee === '') {
        $previewAssignee = 'voorbeeld@kvt.nl';
    }
    $previewAssigneeLabel = formatUserDisplayName($previewAssignee);
    $previewAssigneeColor = emailToHexColor($previewAssignee);
    $previewTickets = [
        [
            'id' => 102,
            'title' => __('appearance.preview_title_p2'),
            'status' => 'in behandeling',
            'priority' => 2,
            'category' => 'AFAS',
            'created_label' => __('appearance.preview_created_recent'),
            'time_open' => __('appearance.preview_time_open_short'),
        ],
        [
            'id' => 87,
            'title' => __('appearance.preview_title_p1'),
            'status' => 'ingediend',
            'priority' => 1,
            'category' => 'Hardwareproblemen',
            'created_label' => __('appearance.preview_created_day'),
            'time_open' => __('appearance.preview_time_open_day'),
        ],
        [
            'id' => 64,
            'title' => __('appearance.preview_title_p0'),
            'status' => 'afwachtende op gebruiker',
            'priority' => 0,
            'category' => 'software bestellen',
            'created_label' => __('appearance.preview_created_week'),
            'time_open' => __('appearance.preview_time_open_week'),
        ],
        [
            'id' => 41,
            'title' => __('appearance.preview_title_closed'),
            'status' => 'afgehandeld',
            'priority' => 0,
            'category' => 'Printerproblemen',
            'created_label' => __('appearance.preview_created_old'),
            'time_open' => __('appearance.preview_time_open_closed'),
        ],
    ];
    ?>
    <section class="panel" data-preferences-section data-email-prefs-section
        data-viewer-email="<?= h($userEmail) ?>"
        data-user-is-admin="<?= $userIsAdmin ? '1' : '0' ?>">
        <h2><?= h(__('email_prefs.heading')) ?></h2>
        <p class="panel-intro"><?= h(__('email_prefs.intro')) ?></p>
        <p class="hint email-prefs-feedback" data-email-prefs-feedback hidden></p>
        <ul class="email-prefs-list">
            <?php foreach (ADMIN_EMAIL_NOTIFICATION_TYPES as $notificationType): ?>
                <li class="email-prefs-item">
                    <label class="email-prefs-label">
                        <input type="checkbox" data-email-pref-type="<?= h($notificationType) ?>"
                            <?= !empty($adminEmailPreferences[$notificationType]) ? 'checked' : '' ?>>
                        <span><?= h(__('email_prefs.type_' . $notificationType)) ?></span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="appearance-prefs" data-appearance-prefs>
            <h2 class="appearance-prefs-heading"><?= h(__('appearance.heading')) ?></h2>
            <p class="panel-intro"><?= h(__('appearance.intro')) ?></p>
            <p class="hint email-prefs-feedback" data-appearance-prefs-feedback hidden></p>

            <div class="appearance-prefs-layout">
                <div class="appearance-prefs-options">
                    <label class="appearance-pref-row">
                        <span class="appearance-pref-copy">
                            <strong><?= h(__('appearance.show_priority_markers')) ?></strong>
                            <span><?= h(__('appearance.show_priority_markers_help')) ?></span>
                        </span>
                        <input type="checkbox" data-appearance-key="show_priority_markers"
                            <?= !empty($appearance['show_priority_markers']) ? 'checked' : '' ?>>
                    </label>

                    <label class="appearance-pref-row">
                        <span class="appearance-pref-copy">
                            <strong><?= h(__('appearance.show_time_open')) ?></strong>
                            <span><?= h(__('appearance.show_time_open_help')) ?></span>
                        </span>
                        <input type="checkbox" data-appearance-key="show_time_open"
                            <?= !empty($appearance['show_time_open']) ? 'checked' : '' ?>>
                    </label>

                    <label class="appearance-pref-row appearance-pref-row-select">
                        <span class="appearance-pref-copy">
                            <strong><?= h(__('appearance.border_color')) ?></strong>
                            <span><?= h(__('appearance.border_color_help')) ?></span>
                        </span>
                        <select data-appearance-key="border_color">
                            <?php foreach (TICKET_APPEARANCE_BORDER_OPTIONS as $borderOption): ?>
                                <option value="<?= h($borderOption) ?>" <?= ($appearance['border_color'] ?? '') === $borderOption ? 'selected' : '' ?>>
                                    <?= h(__('appearance.border_' . $borderOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="appearance-pref-row appearance-pref-row-select">
                        <span class="appearance-pref-copy">
                            <strong><?= h(__('appearance.closed_style')) ?></strong>
                            <span><?= h(__('appearance.closed_style_help')) ?></span>
                        </span>
                        <select data-appearance-key="closed_style">
                            <?php foreach (TICKET_APPEARANCE_CLOSED_OPTIONS as $closedOption): ?>
                                <option value="<?= h($closedOption) ?>" <?= ($appearance['closed_style'] ?? '') === $closedOption ? 'selected' : '' ?>>
                                    <?= h(__('appearance.closed_' . $closedOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>

                <div class="appearance-prefs-preview" aria-label="<?= h(__('appearance.preview_label')) ?>">
                    <?php foreach ($previewTickets as $previewTicket):
                        $previewPriority = (int) $previewTicket['priority'];
                        $previewStatus = (string) $previewTicket['status'];
                        $previewStatusColor = getStatusColor($previewStatus);
                        $previewPriorityColor = getPriorityColor($previewPriority);
                        $previewCategoryColor = getCategoryColor((string) $previewTicket['category']);
                        $showPreviewMarker = $previewStatus !== 'afgehandeld' && $previewPriority > 0;
                        ?>
                        <details class="ticket-card appearance-preview-card<?= $showPreviewMarker ? ' has-priority-marker' : '' ?>"
                            data-preview-ticket
                            data-priority="<?= $previewPriority ?>"
                            data-status="<?= h($previewStatus) ?>"
                            ontoggle="if (this.open) { this.open = false; }"
                            style="--ticket-color-status: <?= h($previewStatusColor) ?>; --ticket-color-assignee: <?= h($previewAssigneeColor) ?>; --ticket-color-priority: <?= h($previewPriorityColor) ?>; --ticket-color-category: <?= h($previewCategoryColor) ?>; --ticket-color: <?= h($previewStatusColor) ?>;">
                            <summary>
                                <span class="ticket-priority-marker" data-role="ticket-priority-marker"
                                    data-priority="<?= $previewPriority ?>"
                                    <?= $showPreviewMarker ? '' : 'hidden' ?>
                                    aria-hidden="<?= $showPreviewMarker ? 'false' : 'true' ?>"><?= $previewPriority === 2 ? '!!' : '!' ?></span>
                                <div class="ticket-summary">
                                    <div>
                                        <p class="ticket-main-title"><strong><span data-role="ticket-number">#<?= (int) $previewTicket['id'] ?></span>
                                                · <span data-role="ticket-title"><?= h((string) $previewTicket['title']) ?></span></strong></p>
                                        <div class="ticket-subtitle">
                                            <span><?= h($previewAssigneeLabel) ?></span>
                                            <span><?= h(translateCategory((string) $previewTicket['category'])) ?></span>
                                            <span><?= h((string) $previewTicket['created_label']) ?></span>
                                        </div>
                                    </div>
                                    <div class="ticket-subtitle">
                                        <span class="status-pill" style="--ticket-color: <?= h($previewStatusColor) ?>;"><?= h(translateStatus($previewStatus)) ?></span>
                                        <span class="assignee-badge" style="--assignee-color: <?= h($previewAssigneeColor) ?>;"><?= h($previewAssigneeLabel) ?></span>
                                        <span class="status-pill" style="--ticket-color: <?= h($previewPriorityColor) ?>;"><?= h(__('ticket.meta_priority')) ?>
                                            <?= $previewPriority ?> · <?= h(formatPriorityLabel($previewPriority)) ?></span>
                                        <span class="count-badge" data-role="time-open-badge"><?= h(__('ticket.time_open')) ?>:
                                            <?= h((string) $previewTicket['time_open']) ?></span>
                                    </div>
                                </div>
                            </summary>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>
