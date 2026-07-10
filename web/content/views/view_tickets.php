<?php
$showMyTicketsSection = !$isAdminPortal && !$isAllTicketsView;
$showAllTicketsSection = $isAllTicketsView;
$showAdminOverviewSection = $isAdminPortal && $view === 'overview';
?>
<?php if ($showMyTicketsSection || $showAllTicketsSection || $showAdminOverviewSection): ?>
    <?php
    $ticketCardBaseContext = [
        'currentPage' => $currentPage,
        'canManageTickets' => $canManageTickets,
        'userIsAdmin' => $userIsAdmin,
        'isAdminPortal' => $isAdminPortal,
        'ictUsers' => $ictUsers,
        'csrfToken' => $csrfToken,
        'openTicketId' => $openTicketId,
        'view' => $view,
        'viewerEmail' => $userEmail,
        'isReadOnlyTicket' => $isAllTicketsView,
    ];
    $ticketPollPayload = [
        'current_page' => $currentPage,
        'current_language' => getCurrentLanguage(),
        'viewer_email' => $userEmail,
        'can_manage_tickets' => $canManageTickets,
        'user_is_admin' => $userIsAdmin,
        'is_admin_portal' => $isAdminPortal,
        'csrf_token' => $csrfToken,
        'open_ticket_id' => $openTicketId,
        'view' => $view,
        'browse_mode' => $ticketBrowseMode,
        'assigned_filter' => $assignedFilter,
        'search_query' => $searchQuery,
        'status_filters' => $effectiveStatusFilters,
        'status_filters_selected' => $statusFilters,
        'status_filter_active' => $statusFilterRequestActive,
        'category_filters' => $effectiveCategoryFilters,
        'category_filters_selected' => $categoryFilters,
        'category_filter_active' => $categoryFilterRequestActive,
        'page' => $ticketPage,
        'per_page' => TICKETS_PER_PAGE,
        'total_pages' => $ticketTotalPages,
        'total_count' => $ticketTotalCount,
        'last_signature' => $ticketSnapshotSignature,
    ];
    $ticketHeading = $isAdminPortal
        ? __('tickets.heading_admin')
        : ($isAllTicketsView ? __('tickets.heading_all') : __('tickets.heading_user'));
    $ticketEmptyMessage = $isAdminPortal
        ? __('tickets.empty_admin')
        : ($isAllTicketsView ? __('tickets.empty_all') : __('tickets.empty_user'));
    ?>
    <section class="panel" data-live-ticket-section data-ticket-signature="<?= h($ticketSnapshotSignature) ?>"
        data-ticket-poll-payload="<?= h((string) json_encode($ticketPollPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"
        data-ticket-poll-interval="15000">
        <h2><?= h($ticketHeading) ?></h2>
        <?php if ($isAllTicketsView): ?>
            <p class="panel-intro"><?= h(__('tickets.intro_all')) ?></p>
        <?php endif; ?>

        <?php if ($canUseTicketOverviewFilters): ?>
            <form method="get" class="filters-form">
                <?php if ($isAllTicketsView): ?>
                    <input type="hidden" name="view" value="all_tickets">
                <?php endif; ?>
                <?php if (!$isAllTicketsView): ?>
                    <input type="hidden" name="status_filter_mode" value="manual">
                <?php endif; ?>
                <input type="hidden" name="category_filter_mode" value="manual">

                <?php if (!$isAllTicketsView): ?>
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
                <?php endif; ?>

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
                    <?= h(__('filter.search_label')) ?>
                    <input type="search" name="search" value="<?= h($searchQuery) ?>"
                        placeholder="<?= h(__('filter.search_placeholder')) ?>">
                </label>

                <label>
                    <?= h(__('filter.ict_employee')) ?>
                    <select name="assigned" onchange="this.form.submit()">
                        <option value=""><?= h(__('filter.all_assigned')) ?></option>
                        <option value="__unassigned__" <?= $assignedFilter === '__unassigned__' ? 'selected' : '' ?>>
                            <?= h(__('filter.unassigned')) ?>
                        </option>
                        <?php foreach ($ictUsers as $ictUser):
                            $ictUser = strtolower($ictUser); ?>
                            <option value="<?= h($ictUser) ?>" <?= $assignedFilter === $ictUser ? 'selected' : '' ?>><?= h($ictUser) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <div class="button-row">
                    <a class="secondary-button"
                        href="<?= h(buildCurrentPageUrl($currentPage, array_merge($isAllTicketsView ? ['view' => 'all_tickets'] : [], ['reset_filters' => '1', 'open' => null]), ['_partial', '_tickets_poll', 'reset_filters', 'status_filter_mode', 'status', 'category_filter_mode', 'category', 'assigned', 'search'])) ?>"><?= h(__('filter.reset')) ?></a>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($ticketPaginationHtml !== ''): ?>
            <div data-role="ticket-pagination"><?= $ticketPaginationHtml ?></div>
        <?php endif; ?>

        <?php if ($tickets === []): ?>
            <div class="empty-state">
                <?= h($ticketEmptyMessage) ?>
            </div>
        <?php else: ?>
            <div class="ticket-list">
                <?php foreach ($tickets as $ticket): ?>
                    <?php $ticketDetail = $ticketDetailsById[(int) ($ticket['id'] ?? 0)] ?? null; ?>
                    <?= renderTicketCardHtml($ticket, $ticketDetail, buildTicketCardRenderContext($ticketCardBaseContext, $ticket, $openTicketId)) ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($ticketPaginationHtml !== ''): ?>
            <div data-role="ticket-pagination"><?= $ticketPaginationHtml ?></div>
        <?php endif; ?>
    </section>
<?php endif; ?>
