<?php if (!$isAdminPortal || ($isAdminPortal && $view === 'overview')): ?>
    <?php
    $ticketPollPayload = [
        'current_page' => $currentPage,
        'viewer_email' => $userEmail,
        'can_manage_tickets' => $canManageTickets,
        'user_is_admin' => $userIsAdmin,
        'is_admin_portal' => $isAdminPortal,
        'csrf_token' => $csrfToken,
        'open_ticket_id' => $openTicketId,
        'view' => $view,
        'assigned_filter' => $assignedFilter,
        'status_filters' => $effectiveStatusFilters,
        'category_filters' => $effectiveCategoryFilters,
    ];
    ?>
    <section class="panel" data-live-ticket-section data-ticket-signature="<?= h($ticketSnapshotSignature) ?>"
        data-ticket-poll-payload="<?= h((string) json_encode($ticketPollPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
        data-ticket-poll-interval="15000">
        <h2><?= $isAdminPortal ? h(__('tickets.heading_admin')) : h(__('tickets.heading_user')) ?></h2>

        <?php if ($isAdminPortal): ?>
            <form method="get" class="filters-form">
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
                                <input type="checkbox" name="category[]" value="<?= h($category) ?>" <?= $categorySelected ? 'checked' : '' ?> onchange="this.form.submit()">
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
                            <option value="<?= h($ictUser) ?>" <?= $assignedFilter === $ictUser ? 'selected' : '' ?>><?= h($ictUser) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="button-row">
                    <a class="secondary-button"
                        href="<?= h(buildCurrentPageUrl($currentPage, ['reset_filters' => '1', 'open' => null], ['_partial', '_tickets_poll', 'reset_filters', 'status_filter_mode', 'status', 'category_filter_mode', 'category', 'assigned'])) ?>"><?= h(__('filter.reset')) ?></a>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($tickets === []): ?>
            <div class="empty-state">
                <?= $isAdminPortal ? h(__('tickets.empty_admin')) : h(__('tickets.empty_user')) ?>
            </div>
        <?php else: ?>
            <div class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <?php $ticketDetail = $store instanceof TicketStore ? $store->getTicket((int) $ticket['id'], $canManageTickets, $userEmail) : null; ?>
                    <?= renderTicketCardHtml($ticket, $ticketDetail, [
                        'currentPage' => $currentPage,
                        'canManageTickets' => $canManageTickets,
                        'userIsAdmin' => $userIsAdmin,
                        'isAdminPortal' => $isAdminPortal,
                        'ictUsers' => $ictUsers,
                        'csrfToken' => $csrfToken,
                        'openTicketId' => $openTicketId,
                        'view' => $view,
                    ]) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>