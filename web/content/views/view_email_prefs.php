<?php if ($canManageTickets && $view === 'preferences'):
    $appearance = is_array($ticketAppearancePreferences ?? null)
        ? $ticketAppearancePreferences
        : getDefaultTicketAppearancePreferences();
    $sortFieldOptions = [
        'status' => __('appearance.sort_field_status'),
        'priority' => __('appearance.sort_field_priority'),
        'ticket_age' => __('appearance.sort_field_ticket_age'),
        'category' => __('appearance.sort_field_category'),
        'open_state' => __('appearance.sort_field_open_state'),
        'in_progress_started' => __('appearance.sort_field_in_progress_started'),
        'assignee' => __('appearance.sort_field_assignee'),
        'updated_at' => __('appearance.sort_field_updated_at'),
        'due_date' => __('appearance.sort_field_due_date'),
        'title' => __('appearance.sort_field_title'),
        'ticket_number' => __('appearance.sort_field_ticket_number'),
        'requester' => __('appearance.sort_field_requester'),
        'message_count' => __('appearance.sort_field_message_count'),
        'attachment_count' => __('appearance.sort_field_attachment_count'),
    ];
    $sortFieldHelp = [
        'status' => __('appearance.sort_help_status'),
        'priority' => __('appearance.sort_help_priority'),
        'ticket_age' => __('appearance.sort_help_ticket_age'),
        'category' => __('appearance.sort_help_category'),
        'open_state' => __('appearance.sort_help_open_state'),
        'in_progress_started' => __('appearance.sort_help_in_progress_started'),
        'assignee' => __('appearance.sort_help_assignee'),
        'updated_at' => __('appearance.sort_help_updated_at'),
        'due_date' => __('appearance.sort_help_due_date'),
        'title' => __('appearance.sort_help_title'),
        'ticket_number' => __('appearance.sort_help_ticket_number'),
        'requester' => __('appearance.sort_help_requester'),
        'message_count' => __('appearance.sort_help_message_count'),
        'attachment_count' => __('appearance.sort_help_attachment_count'),
    ];
    $sortDirectionOptions = [
        'status' => ['asc' => __('appearance.sort_dir_status_asc'), 'desc' => __('appearance.sort_dir_status_desc')],
        'priority' => ['asc' => __('appearance.sort_dir_priority_asc'), 'desc' => __('appearance.sort_dir_priority_desc')],
        'ticket_age' => ['asc' => __('appearance.sort_dir_ticket_age_asc'), 'desc' => __('appearance.sort_dir_ticket_age_desc')],
        'category' => ['asc' => __('appearance.sort_dir_category_asc'), 'desc' => __('appearance.sort_dir_category_desc')],
        'open_state' => ['asc' => __('appearance.sort_dir_open_state_asc'), 'desc' => __('appearance.sort_dir_open_state_desc')],
        'in_progress_started' => ['asc' => __('appearance.sort_dir_in_progress_started_asc'), 'desc' => __('appearance.sort_dir_in_progress_started_desc')],
        'assignee' => ['asc' => __('appearance.sort_dir_assignee_asc'), 'desc' => __('appearance.sort_dir_assignee_desc')],
        'updated_at' => ['asc' => __('appearance.sort_dir_updated_at_asc'), 'desc' => __('appearance.sort_dir_updated_at_desc')],
        'due_date' => ['asc' => __('appearance.sort_dir_due_date_asc'), 'desc' => __('appearance.sort_dir_due_date_desc')],
        'title' => ['asc' => __('appearance.sort_dir_title_asc'), 'desc' => __('appearance.sort_dir_title_desc')],
        'ticket_number' => ['asc' => __('appearance.sort_dir_ticket_number_asc'), 'desc' => __('appearance.sort_dir_ticket_number_desc')],
        'requester' => ['asc' => __('appearance.sort_dir_requester_asc'), 'desc' => __('appearance.sort_dir_requester_desc')],
        'message_count' => ['asc' => __('appearance.sort_dir_message_count_asc'), 'desc' => __('appearance.sort_dir_message_count_desc')],
        'attachment_count' => ['asc' => __('appearance.sort_dir_attachment_count_asc'), 'desc' => __('appearance.sort_dir_attachment_count_desc')],
    ];
    $previewUsers = [
        'marit@kvt.nl',
        'sven@kvt.nl',
        strtolower(trim((string) $userEmail)) !== '' ? strtolower(trim((string) $userEmail)) : 'voorbeeld@kvt.nl',
        'ict@kvt.nl',
    ];
    $previewTickets = [
        [
            'id' => 102,
            'title' => __('appearance.preview_title_p2'),
            'status' => 'in behandeling',
            'priority' => 2,
            'category' => 'AFAS',
            'created_label' => __('appearance.preview_created_recent'),
            'time_open' => __('appearance.preview_time_open_short'),
            'created_at' => '2026-08-18T09:48:00+02:00',
            'updated_at' => '2026-08-18T09:56:00+02:00',
            'due_date' => '2026-08-18',
            'assigned_email' => $previewUsers[0],
            'user_email' => 'financien@kvt.nl',
            'message_count' => 6,
            'attachment_count' => 1,
        ],
        [
            'id' => 87,
            'title' => __('appearance.preview_title_p1'),
            'status' => 'ingediend',
            'priority' => 1,
            'category' => 'Hardwareproblemen',
            'created_label' => __('appearance.preview_created_day'),
            'time_open' => __('appearance.preview_time_open_day'),
            'created_at' => '2026-08-17T08:20:00+02:00',
            'updated_at' => '2026-08-18T08:10:00+02:00',
            'due_date' => '2026-08-20',
            'assigned_email' => '',
            'user_email' => 'werkplaats@kvt.nl',
            'message_count' => 2,
            'attachment_count' => 3,
        ],
        [
            'id' => 64,
            'title' => __('appearance.preview_title_p0'),
            'status' => 'afwachtende op gebruiker',
            'priority' => 0,
            'category' => 'software bestellen',
            'created_label' => __('appearance.preview_created_week'),
            'time_open' => __('appearance.preview_time_open_week'),
            'created_at' => '2026-08-15T11:00:00+02:00',
            'updated_at' => '2026-08-17T16:45:00+02:00',
            'due_date' => '',
            'assigned_email' => $previewUsers[2],
            'user_email' => 'marketing@kvt.nl',
            'message_count' => 4,
            'attachment_count' => 0,
        ],
        [
            'id' => 41,
            'title' => __('appearance.preview_title_closed'),
            'status' => 'afgehandeld',
            'priority' => 0,
            'category' => 'Printerproblemen',
            'created_label' => __('appearance.preview_created_old'),
            'time_open' => __('appearance.preview_time_open_closed'),
            'created_at' => '2026-08-09T14:10:00+02:00',
            'updated_at' => '2026-08-13T12:00:00+02:00',
            'due_date' => '2026-08-14',
            'assigned_email' => $previewUsers[1],
            'user_email' => 'magazijn@kvt.nl',
            'message_count' => 1,
            'attachment_count' => 2,
        ],
        [
            'id' => 118,
            'title' => __('appearance.preview_title_waiting_order'),
            'status' => 'afwachtende op bestelling',
            'priority' => 1,
            'category' => 'licentie aanvragen',
            'created_label' => __('appearance.preview_created_two_days'),
            'time_open' => __('appearance.preview_time_open_two_days'),
            'created_at' => '2026-08-16T10:30:00+02:00',
            'updated_at' => '2026-08-18T07:42:00+02:00',
            'due_date' => '2026-08-22',
            'assigned_email' => $previewUsers[3],
            'user_email' => 'hr@kvt.nl',
            'message_count' => 3,
            'attachment_count' => 0,
        ],
        [
            'id' => 73,
            'title' => __('appearance.preview_title_waiting_third_party'),
            'status' => 'afwachtende op derde partij',
            'priority' => 0,
            'category' => 'Business Central',
            'created_label' => __('appearance.preview_created_five_days'),
            'time_open' => __('appearance.preview_time_open_five_days'),
            'created_at' => '2026-08-13T09:00:00+02:00',
            'updated_at' => '2026-08-18T06:15:00+02:00',
            'due_date' => '',
            'assigned_email' => $previewUsers[0],
            'user_email' => 'planning@kvt.nl',
            'message_count' => 8,
            'attachment_count' => 4,
        ],
        [
            'id' => 134,
            'title' => __('appearance.preview_title_open_webapp'),
            'status' => 'in behandeling',
            'priority' => 2,
            'category' => 'sleutels.kvt.nl web-applicatieproblemen',
            'created_label' => __('appearance.preview_created_day'),
            'time_open' => __('appearance.preview_time_open_day'),
            'created_at' => '2026-08-17T07:35:00+02:00',
            'updated_at' => '2026-08-18T09:15:00+02:00',
            'due_date' => '2026-08-19',
            'assigned_email' => $previewUsers[1],
            'user_email' => 'verkoop@kvt.nl',
            'message_count' => 9,
            'attachment_count' => 5,
        ],
        [
            'id' => 96,
            'title' => __('appearance.preview_title_phone_setup'),
            'status' => 'ingediend',
            'priority' => 0,
            'category' => 'Telefoon Klaarmaken',
            'created_label' => __('appearance.preview_created_recent'),
            'time_open' => __('appearance.preview_time_open_short'),
            'created_at' => '2026-08-18T09:20:00+02:00',
            'updated_at' => '2026-08-18T09:22:00+02:00',
            'due_date' => '2026-08-21',
            'assigned_email' => '',
            'user_email' => 'receptie@kvt.nl',
            'message_count' => 0,
            'attachment_count' => 1,
        ],
        [
            'id' => 58,
            'title' => __('appearance.preview_title_closed_hardware'),
            'status' => 'afgehandeld',
            'priority' => 1,
            'category' => 'hardware bestellen',
            'created_label' => __('appearance.preview_created_old'),
            'time_open' => __('appearance.preview_time_open_closed'),
            'created_at' => '2026-08-10T10:00:00+02:00',
            'updated_at' => '2026-08-12T15:30:00+02:00',
            'due_date' => '2026-08-12',
            'assigned_email' => $previewUsers[3],
            'user_email' => 'inkoop@kvt.nl',
            'message_count' => 2,
            'attachment_count' => 1,
        ],
        [
            'id' => 52,
            'title' => __('appearance.preview_title_closed_afas'),
            'status' => 'afgehandeld',
            'priority' => 0,
            'category' => 'AFAS',
            'created_label' => __('appearance.preview_created_five_days'),
            'time_open' => __('appearance.preview_time_open_five_days'),
            'created_at' => '2026-08-13T08:00:00+02:00',
            'updated_at' => '2026-08-15T11:05:00+02:00',
            'due_date' => '',
            'assigned_email' => $previewUsers[0],
            'user_email' => 'personeel@kvt.nl',
            'message_count' => 5,
            'attachment_count' => 0,
        ],
        [
            'id' => 47,
            'title' => __('appearance.preview_title_closed_serviceapp'),
            'status' => 'afgehandeld',
            'priority' => 0,
            'category' => 'ServiceApp',
            'created_label' => __('appearance.preview_created_week'),
            'time_open' => __('appearance.preview_time_open_week'),
            'created_at' => '2026-08-15T06:50:00+02:00',
            'updated_at' => '2026-08-16T14:18:00+02:00',
            'due_date' => '2026-08-16',
            'assigned_email' => $previewUsers[2],
            'user_email' => 'service@kvt.nl',
            'message_count' => 1,
            'attachment_count' => 2,
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

                    <div class="appearance-sorter" data-appearance-sorter
                        data-sort-options="<?= h((string) json_encode($sortFieldOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                        data-sort-help="<?= h((string) json_encode($sortFieldHelp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                        data-sort-directions="<?= h((string) json_encode($sortDirectionOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                        data-sort-rules="<?= h((string) json_encode(array_values($appearance['sort_rules'] ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
                        data-sort-default="<?= h((string) json_encode(getDefaultTicketSortPreferences(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
                        <div class="appearance-pref-copy">
                            <strong><?= h(__('appearance.sort_heading')) ?></strong>
                            <span><?= h(__('appearance.sort_intro')) ?></span>
                        </div>
                        <div class="appearance-sorter-list" data-appearance-sorter-list></div>
                        <div class="appearance-sorter-actions">
                            <button type="button" class="secondary-button appearance-sorter-add" data-appearance-sorter-add>+</button>
                            <button type="button" class="secondary-button appearance-sorter-reset" data-appearance-sorter-reset><?= h(__('appearance.sort_reset')) ?></button>
                        </div>
                    </div>
                </div>

                <div class="appearance-prefs-preview" aria-label="<?= h(__('appearance.preview_label')) ?>">
                    <?php foreach ($previewTickets as $previewTicket):
                        $previewPriority = (int) $previewTicket['priority'];
                        $previewStatus = (string) $previewTicket['status'];
                        $previewAssignee = strtolower(trim((string) ($previewTicket['assigned_email'] ?? '')));
                        $previewAssigneeLabel = $previewAssignee !== '' ? formatUserDisplayName($previewAssignee) : __('filter.unassigned');
                        $previewAssigneeColor = $previewAssignee !== '' ? emailToHexColor($previewAssignee) : '#94a3b8';
                        $previewRequesterEmail = strtolower(trim((string) ($previewTicket['user_email'] ?? '')));
                        $previewRequesterLabel = formatUserDisplayName($previewRequesterEmail);
                        $previewStatusColor = getStatusColor($previewStatus);
                        $previewPriorityColor = getPriorityColor($previewPriority);
                        $previewCategoryColor = getCategoryColor((string) $previewTicket['category']);
                        $showPreviewMarker = $previewStatus !== 'afgehandeld' && $previewPriority > 0;
                        $previewSortPayload = [
                            'status' => $previewStatus,
                            'priority' => $previewPriority,
                            'category' => (string) $previewTicket['category'],
                            'created_at' => (string) ($previewTicket['created_at'] ?? ''),
                            'updated_at' => (string) ($previewTicket['updated_at'] ?? ''),
                            'due_date' => (string) ($previewTicket['due_date'] ?? ''),
                            'assigned_email' => $previewAssignee,
                            'user_email' => $previewRequesterEmail,
                            'title' => (string) $previewTicket['title'],
                            'ticket_number' => (int) $previewTicket['id'],
                            'message_count' => (int) ($previewTicket['message_count'] ?? 0),
                            'attachment_count' => (int) ($previewTicket['attachment_count'] ?? 0),
                        ];
                        ?>
                        <details class="ticket-card appearance-preview-card<?= $showPreviewMarker ? ' has-priority-marker' : '' ?>"
                            data-preview-ticket
                            data-preview-sort="<?= h((string) json_encode($previewSortPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
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
                                            <span><?= h($previewRequesterLabel) ?></span>
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

            <div class="appearance-sorter-confirm" data-appearance-sorter-confirm hidden>
                <div class="appearance-sorter-confirm-card" role="dialog" aria-modal="true" aria-labelledby="appearance_sorter_confirm_title">
                    <h3 id="appearance_sorter_confirm_title"><?= h(__('appearance.sort_remove_title')) ?></h3>
                    <p><?= h(__('appearance.sort_remove_message')) ?></p>
                    <div class="appearance-sorter-confirm-actions">
                        <button type="button" class="secondary-button" data-appearance-sorter-cancel><?= h(__('appearance.sort_remove_cancel')) ?></button>
                        <button type="button" class="primary-button" data-appearance-sorter-confirm-delete><?= h(__('appearance.sort_remove_confirm')) ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="prefs-reset-tips">
            <button type="button" class="secondary-button" id="reset-tips-btn" data-reset-tips>
                <?= h(__('tips.reset_btn')) ?>
            </button>
            <span class="hint" id="reset-tips-feedback" hidden><?= h(__('tips.reset_feedback')) ?></span>
        </div>
    </section>
<?php endif; ?>
