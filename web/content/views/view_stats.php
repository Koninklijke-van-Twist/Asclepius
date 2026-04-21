<?php if ($canManageTickets && $view === 'stats'): ?>
    <section class="panel">
        <h2><?= h(__('stats.heading')) ?></h2>
        <p class="panel-intro"><?= __('stats.intro') ?></p>

        <?php if ($isBigscreen ?? false): ?>
            <div class="stats-layout">
                <div class="stats-main">
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stats-card">
                        <span><?= h(__('stats.total_tickets')) ?></span>
                        <strong id="stat-total"><?= (int) ($overallStats['total_tickets'] ?? 0) ?></strong>
                    </div>
                    <div class="stats-card">
                        <span><?= h(__('stats.open_tickets')) ?></span>
                        <strong id="stat-open"><?= (int) ($overallStats['open_tickets'] ?? 0) ?></strong>
                    </div>
                    <div class="stats-card">
                        <span><?= h(__('stats.resolved_tickets')) ?></span>
                        <strong id="stat-resolved"><?= (int) ($overallStats['resolved_tickets'] ?? 0) ?></strong>
                    </div>
                    <div class="stats-card">
                        <span><?= h(__('stats.waiting_order')) ?></span>
                        <strong id="stat-waiting"><?= (int) ($overallStats['waiting_order_tickets'] ?? 0) ?></strong>
                    </div>
                </div>

                <h3 class="stats-section-title"><?= h(__('stats.per_ict')) ?></h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th><?= h(__('stats.col_ict_employee')) ?></th>
                                <th><?= h(__('stats.col_handled')) ?></th>
                                <th><?= h(__('stats.col_avg_open')) ?></th>
                                <th><?= h(__('stats.col_max_open')) ?></th>
                                <th><?= h(__('stats.col_outstanding')) ?></th>
                                <th><?= h(__('stats.col_waiting_order')) ?></th>
                            </tr>
                        </thead>
                        <tbody id="stats-ict-tbody">
                            <?php foreach ($ictStats as $statsRow): ?>
                                <tr>
                                    <td class="user-color-cell"
                                        style="--assignee-color: <?= h(emailToHexColor((string) $statsRow['user_email'])) ?>;">
                                        <?php $statsUserEmail = strtolower((string) $statsRow['user_email']); ?>
                                        <span
                                            class="assignee-badge <?= empty($availabilityByIctUser[$statsUserEmail]) ? 'vacation-badge is-away' : '' ?>"
                                            style="--assignee-color: <?= h(!empty($availabilityByIctUser[$statsUserEmail]) ? emailToHexColor($statsUserEmail) : '#94a3b8') ?>;">
                                            <?= h($statsUserEmail) ?>
                                            <?= empty($availabilityByIctUser[$statsUserEmail]) ? ' 🌴' : '' ?>
                                        </span>
                                    </td>
                                    <td><?= (int) ($statsRow['handled_count'] ?? 0) ?></td>
                                    <td><?= h(formatDurationSeconds($statsRow['average_open_seconds'] ?? null)) ?>
                                    </td>
                                    <td><?= h(formatDurationSeconds($statsRow['max_open_seconds'] ?? null)) ?></td>
                                    <td><?= (int) ($statsRow['open_count'] ?? 0) ?></td>
                                    <td><?= (int) ($statsRow['waiting_order_count'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 class="stats-section-title"><?= h(__('stats.per_user')) ?></h3>
                <div id="stats-requester-wrap">
                    <?php if ($requesterStats === []): ?>
                        <div class="empty-state"><?= h(__('stats.no_user_stats')) ?></div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?= h(__('stats.col_user')) ?></th>
                                        <th><?= h(__('stats.col_submitted')) ?></th>
                                        <th><?= h(__('stats.col_avg_wait')) ?></th>
                                        <th><?= h(__('stats.col_max_wait')) ?></th>
                                    </tr>
                                </thead>
                                <tbody id="stats-requester-tbody">
                                    <?php foreach ($requesterStats as $statsRow): ?>
                                        <tr>
                                            <td><?= h((string) $statsRow['user_email']) ?></td>
                                            <td><?= (int) ($statsRow['submitted_count'] ?? 0) ?></td>
                                            <td><?= h(formatDurationSeconds($statsRow['average_wait_seconds'] ?? null)) ?>
                                            </td>
                                            <td><?= h(formatDurationSeconds($statsRow['max_wait_seconds'] ?? null)) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="stats-note"><?= __('stats.wait_note') ?></p>
                    <?php endif; ?>
                </div><!-- /#stats-requester-wrap -->

                <?php if ($isBigscreen ?? false): ?>
                </div><!-- /.stats-main -->
                <aside class="stats-sidebar" id="stats-sidebar">
                    <h3><?= h(__('stats.sidebar_heading')) ?></h3>
                    <div id="stats-sidebar-list">
                        <?php if ($statsOpenTickets === []): ?>
                            <p style="color:var(--muted);font-size:13px;"><?= h(__('stats.sidebar_empty')) ?></p>
                        <?php else: ?>
                            <?php foreach ($statsOpenTickets as $sideTicket): ?>
                                <?php $sideColor = getStatusColor((string) $sideTicket['status']); ?>
                                <?php $sidePrio = (int) ($sideTicket['priority'] ?? 0); ?>
                                <div class="stats-ticket-item" style="--ticket-color: <?= h($sideColor) ?>;">
                                    <div class="sti-body">
                                        <span class="sti-title">#<?= (int) $sideTicket['id'] ?>
                                            <?= h((string) $sideTicket['title']) ?></span>
                                        <span class="sti-meta"><?= h(translateStatus((string) $sideTicket['status'])) ?> &middot;
                                            <?= h((string) $sideTicket['user_email']) ?></span>
                                        <span
                                            class="sti-meta"><?= h((string) (($sideTicket['assigned_email'] ?? '') !== '' ? $sideTicket['assigned_email'] : __('stats.no_assigned'))) ?></span>
                                    </div>
                                    <span class="sti-prio sti-prio-<?= $sidePrio ?>"><?= $sidePrio ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </aside>
            </div><!-- /.stats-layout -->
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($canManageTickets && $view === 'stats'): ?>
        <div id="bs-updates" aria-live="polite" aria-relevant="additions"></div>

        <div id="bigscreen-overlay" aria-live="assertive" aria-atomic="true">
            <div class="bs-hazard-strip bs-hazard-top" id="bs-hazard-top"></div>
            <span class="bs-warning-emoji" id="bs-warning-emoji" aria-hidden="true">⚠️</span>
            <div class="bs-max-prio-label" id="bs-max-prio-label" hidden>MAXIMUM PRIORITEIT TICKET</div>
            <div class="bs-hazard-strip bs-hazard-bottom" id="bs-hazard-bottom"></div>
            <div class="bs-ticket-info" id="bs-ticket-info" hidden>
                <div class="bs-headline" id="bs-headline"></div>
                <div class="bs-title" id="bs-title"></div>
                <div id="bs-assignee"></div>
            </div>
        </div>
    <?php endif; ?>