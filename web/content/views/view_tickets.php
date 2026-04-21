            <?php if (!$isAdminPortal || ($isAdminPortal && $view === 'overview')): ?>
                <section class="panel">
                    <h2><?= $isAdminPortal ? h(__('tickets.heading_admin')) : h(__('tickets.heading_user')) ?></h2>

                    <?php if ($isAdminPortal): ?>
                        <form method="get" class="filters-form">
                            <?php if ($view === 'settings'): ?>
                                <input type="hidden" name="view" value="settings">
                            <?php endif; ?>

                            <input type="hidden" name="status_filter_mode" value="manual">
                            <input type="hidden" name="category_filter_mode" value="manual">

                            <div>
                                <label><?= h(__('filter.status_label')) ?></label>
                                <div class="checkbox-group">
                                    <?php foreach (TICKET_STATUSES as $status): ?>
                                        <?php $statusSelected = isStatusFilterSelected($status, $statusFilters, $statusFilterRequestActive); ?>
                                        <label class="checkbox-chip <?= $statusSelected ? 'is-active' : 'is-inactive' ?>"
                                            style="--status-color: <?= h(getStatusColor($status)) ?>;">
                                            <input type="checkbox" name="status[]" value="<?= h($status) ?>" <?= $statusSelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <span><?= h(translateStatus($status)) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <label><?= h(__('filter.category_label')) ?></label>
                                <div class="checkbox-group">
                                    <?php foreach (TICKET_CATEGORIES as $category): ?>
                                        <?php $categorySelected = isCategoryFilterSelected($category, $categoryFilters, $categoryFilterRequestActive); ?>
                                        <label class="checkbox-chip <?= $categorySelected ? 'is-active' : 'is-inactive' ?>"
                                            style="--status-color: <?= h(getCategoryColor($category)) ?>;">
                                            <input type="checkbox" name="category[]" value="<?= h($category) ?>"
                                                <?= $categorySelected ? 'checked' : '' ?> onchange="this.form.submit()">
                                            <span><?= h(translateCategory($category)) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <label>
                                <?= h(__('filter.ict_employee')) ?>
                                <select name="assigned" onchange="this.form.submit()">
                                    <option value=""><?= h(__('filter.all_assigned')) ?></option>
                                    <option value="__unassigned__" <?= $assignedFilter === '__unassigned__' ? 'selected' : '' ?>>
                                        <?= h(__('filter.unassigned')) ?></option>
                                    <?php foreach ($ictUsers as $ictUser):
                                        $ictUser = strtolower($ictUser); ?>
                                        <option value="<?= h($ictUser) ?>" <?= $assignedFilter === $ictUser ? 'selected' : '' ?>>
                                            <?= h($ictUser) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div class="button-row">
                                <a class="secondary-button"
                                    href="<?= h($currentPage) ?><?= $view !== 'overview' ? '?view=' . h($view) : '' ?>"><?= h(__('filter.reset')) ?></a>
                            </div>
                        </form>
                    <?php endif; ?>

                    <?php if ($tickets === []): ?>
                        <div class="empty-state">
                            <?= $isAdminPortal ? h(__('tickets.empty_admin')) : h(__('tickets.empty_user')) ?>
                        </div>
                    <?php else: ?>
                        <div class="ticket-list">
                            <?php foreach ($tickets as $index => $ticket): ?>
                                <?php
                                $ticketColor = getStatusColor((string) $ticket['status']);
                                $ticketDetail = $store instanceof TicketStore ? $store->getTicket((int) $ticket['id'], $canManageTickets, $userEmail) : null;
                                $shouldOpen = $openTicketId > 0 && (int) $ticket['id'] === $openTicketId;
                                $ticketOpenDuration = getTicketOpenDurationSeconds($ticket);
                                $replyFormId = 'reply-form-' . (int) $ticket['id'];
                                ?>
                                <details class="ticket-card" style="--ticket-color: <?= h($ticketColor) ?>;" <?= $shouldOpen ? 'open' : '' ?>>
                                    <summary>
                                        <div class="ticket-summary">
                                            <div>
                                                <p class="ticket-main-title"><strong>#<?= (int) $ticket['id'] ?> ·
                                                        <?= h((string) $ticket['title']) ?></strong></p>
                                                <div class="ticket-subtitle">
                                                    <span><?= h((string) $ticket['user_email']) ?></span>
                                                    <span><?= h(translateCategory((string) $ticket['category'])) ?></span>
                                                    <span><?= h(formatDateTime((string) $ticket['created_at'])) ?></span>
                                                </div>
                                            </div>
                                            <div class="ticket-subtitle">
                                                <?php if ($isAdminPortal): ?>
                                                    <span class="status-pill"
                                                        style="--ticket-color: <?= h($ticketColor) ?>;"><?= h(translateStatus((string) $ticket['status'])) ?></span>
                                                <?php endif; ?>
                                                <?php if ($userIsAdmin && $isAdminPortal): ?>
                                                    <span class="status-pill"
                                                        style="--ticket-color: <?= h(getPriorityColor((int) ($ticket['priority'] ?? 0))) ?>;"><?= h(__('ticket.meta_priority')) ?>
                                                        <?= (int) ($ticket['priority'] ?? 0) ?> ·
                                                        <?= h(formatPriorityLabel((int) ($ticket['priority'] ?? 0))) ?></span>
                                                <?php endif; ?>
                                                <span class="assignee-badge"
                                                    style="--assignee-color: <?= h(emailToHexColor((string) ($ticket['assigned_email'] ?? 'onbekend@kvt.nl'))) ?>;">
                                                    <?= h((string) (($ticket['assigned_email'] ?? '') !== '' ? $ticket['assigned_email'] : __('ticket.unassigned'))) ?>
                                                </span>
                                                <span class="count-badge"><?= (int) ($ticket['message_count'] ?? 0) ?>
                                                    <?= h(__('ticket.messages_count')) ?></span>
                                                <?php if ($isAdminPortal): ?>
                                                    <span class="count-badge"><?= h(__('ticket.time_open')) ?>
                                                        <?= h(formatDurationSeconds($ticketOpenDuration)) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </summary>

                                    <div class="ticket-body">
                                        <div class="meta-grid">
                                            <div class="meta-item">
                                                <span class="meta-label"><?= h(__('ticket.meta_created')) ?></span>
                                                <?= h(formatDateTime((string) $ticket['created_at'])) ?> ·
                                                <?= h(formatDurationSeconds($ticketOpenDuration)) ?>
                                            </div>
                                            <div class="meta-item">
                                                <span class="meta-label"><?= h(__('ticket.meta_updated')) ?></span>
                                                <?= h(formatDateTime((string) $ticket['updated_at'])) ?>
                                            </div>
                                            <?php if ($userIsAdmin && $isAdminPortal): ?>
                                                <div class="meta-item">
                                                    <span class="meta-label"><?= h(__('ticket.meta_priority')) ?></span>
                                                    <select name="priority" form="<?= h($replyFormId) ?>">
                                                        <option value="0" <?= (int) ($ticket['priority'] ?? 0) === 0 ? 'selected' : '' ?>><?= h(__('ticket.priority_0')) ?></option>
                                                        <option value="1" <?= (int) ($ticket['priority'] ?? 0) === 1 ? 'selected' : '' ?>><?= h(__('ticket.priority_1')) ?></option>
                                                        <option value="2" <?= (int) ($ticket['priority'] ?? 0) === 2 ? 'selected' : '' ?>><?= h(__('ticket.priority_2')) ?></option>
                                                    </select>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($ticketDetail !== null && !empty($ticketDetail['messages'])): ?>
                                            <div>
                                                <h3><?= h(__('ticket.messages_heading')) ?></h3>
                                                <div class="thread">
                                                    <?php foreach ($ticketDetail['messages'] as $message): ?>
                                                        <article
                                                            class="message <?= ($message['sender_role'] ?? '') === 'admin' ? 'admin' : 'user' ?>">
                                                            <div class="message-meta">
                                                                <strong><?= h((string) $message['sender_email']) ?></strong>
                                                                <span
                                                                    class="message-role"><?= ($message['sender_role'] ?? '') === 'admin' ? h(__('ticket.role_admin')) : h(__('ticket.role_user')) ?></span>
                                                                <span><?= h(formatDateTime((string) $message['created_at'])) ?></span>
                                                            </div>

                                                            <?php if (trim((string) ($message['message_text'] ?? '')) !== ''): ?>
                                                                <div class="message-text">
                                                                    <?= formatTicketMessageText((string) $message['message_text']) ?>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if (!empty($message['attachments'])): ?>
                                                                <ul class="attachment-list">
                                                                    <?php foreach ($message['attachments'] as $attachment): ?>
                                                                        <li>
                                                                            <a
                                                                                href="<?= h($currentPage) ?>?download=<?= (int) $attachment['id'] ?>">
                                                                                <?= h((string) $attachment['original_name']) ?>
                                                                            </a>
                                                                            (<?= number_format(((int) $attachment['file_size']) / 1024 / 1024, 2, ',', '.') ?>
                                                                            MB)
                                                                        </li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            <?php endif; ?>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <form method="post"
                                            action="<?= h($currentPage) ?><?= $isAdminPortal && $view === 'settings' ? '?view=settings' : '' ?>"
                                            enctype="multipart/form-data" class="reply-form" id="<?= h($replyFormId) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                            <input type="hidden" name="form_action" value="reply_ticket">
                                            <input type="hidden" name="return_page" value="<?= h($currentPage) ?>">
                                            <input type="hidden" name="ticket_id" value="<?= (int) $ticket['id'] ?>">

                                            <?php if ($canManageTickets): ?>
                                                <div class="admin-grid">
                                                    <label>
                                                        <?= h(__('ticket.status_label')) ?>
                                                        <select name="status">
                                                            <?php foreach (TICKET_STATUSES as $status): ?>
                                                                <option value="<?= h($status) ?>" <?= (string) $ticket['status'] === $status ? 'selected' : '' ?>><?= h(translateStatus($status)) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label>
                                                        <?= h(__('ticket.assigned_to')) ?>
                                                        <select name="assigned_email">
                                                            <option value=""><?= h(__('ticket.unassigned')) ?></option>
                                                            <?php foreach ($ictUsers as $ictUser):
                                                                $ictUser = strtolower($ictUser); ?>
                                                                <option value="<?= h($ictUser) ?>" <?= strtolower((string) ($ticket['assigned_email'] ?? '')) === $ictUser ? 'selected' : '' ?>>
                                                                    <?= h($ictUser) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                </div>
                                            <?php endif; ?>

                                            <label>
                                                <?= h(__('ticket.new_message')) ?>
                                                <textarea name="message"
                                                    placeholder="<?= h(__('ticket.new_message_placeholder')) ?>"></textarea>
                                            </label>

                                            <?php if (!$canManageTickets && (string) $ticket['status'] === 'afgehandeld'): ?>
                                                <label class="checkbox-line">
                                                    <input type="checkbox" name="reopen_ticket" value="1">
                                                    <span><?= h(__('ticket.reopen')) ?></span>
                                                </label>
                                            <?php endif; ?>

                                            <label>
                                                <?= h(__('ticket.add_attachments')) ?>
                                                <input type="file" name="reply_attachments[]" multiple>
                                                <span class="hint"><?= h(__('ticket.file_hint')) ?></span>
                                            </label>

                                            <div class="button-row">
                                                <button
                                                    type="submit"><?= $canManageTickets ? h(__('ticket.btn_save')) : h(__('ticket.btn_reply')) ?></button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
