<?php if ($canManageTickets && $view === 'stats'): ?>
    <section class="panel">
        <h2>ICT-statistieken</h2>
        <p class="panel-intro">Bekijk hier de totalen, prestaties per ICT-medewerker en wachttijden per normale
            gebruiker. Voor afgehandelde tickets meten we van <strong>aangemaakt</strong> tot
            <strong>afgehandeld</strong>; open tickets tellen mee tot <strong>nu</strong>.
        </p>

        <?php if ($isBigscreen ?? false): ?>
            <div class="stats-layout">
                <div class="stats-main">
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stats-card">
                        <span>Totaal aantal tickets</span>
                        <strong id="stat-total"><?= (int) ($overallStats['total_tickets'] ?? 0) ?></strong>
                    </div>
                    <div class="stats-card">
                        <span>Openstaande tickets</span>
                        <strong id="stat-open"><?= (int) ($overallStats['open_tickets'] ?? 0) ?></strong>
                    </div>
                    <div class="stats-card">
                        <span>Afgehandelde tickets</span>
                        <strong id="stat-resolved"><?= (int) ($overallStats['resolved_tickets'] ?? 0) ?></strong>
                    </div>
                    <div class="stats-card">
                        <span>Wacht op bestelling</span>
                        <strong id="stat-waiting"><?= (int) ($overallStats['waiting_order_tickets'] ?? 0) ?></strong>
                    </div>
                </div>

                <h3 class="stats-section-title">Per ICT-medewerker</h3>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ICT-medewerker</th>
                                <th>Afgehandeld</th>
                                <th>Gemiddelde tijd open</th>
                                <th>Langste open tijd</th>
                                <th>Openstaand</th>
                                <th>Wacht op bestelling</th>
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

                <h3 class="stats-section-title">Per normale gebruiker</h3>
                <div id="stats-requester-wrap">
                    <?php if ($requesterStats === []): ?>
                        <div class="empty-state">Er zijn nog geen statistieken voor normale gebruikers beschikbaar.
                        </div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Gebruiker</th>
                                        <th>Tickets ingediend</th>
                                        <th>Gemiddelde wachttijd</th>
                                        <th>Langste wachttijd</th>
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
                        <p class="stats-note">Wachttijden bij gebruikers worden berekend op basis van tickets met
                            status
                            <strong>afgehandeld</strong>.
                        </p>
                    <?php endif; ?>
                </div><!-- /#stats-requester-wrap -->

                <?php if ($isBigscreen ?? false): ?>
                </div><!-- /.stats-main -->
                <aside class="stats-sidebar" id="stats-sidebar">
                    <h3>Openstaande tickets</h3>
                    <div id="stats-sidebar-list">
                        <?php if ($statsOpenTickets === []): ?>
                            <p style="color:var(--muted);font-size:13px;">Geen openstaande tickets.</p>
                        <?php else: ?>
                            <?php foreach ($statsOpenTickets as $sideTicket): ?>
                                <?php $sideColor = getStatusColor((string) $sideTicket['status']); ?>
                                <?php $sidePrio = (int) ($sideTicket['priority'] ?? 0); ?>
                                <div class="stats-ticket-item" style="--ticket-color: <?= h($sideColor) ?>;">
                                    <div class="sti-body">
                                        <span class="sti-title">#<?= (int) $sideTicket['id'] ?>
                                            <?= h((string) $sideTicket['title']) ?></span>
                                        <span class="sti-meta"><?= h((string) $sideTicket['status']) ?> &middot;
                                            <?= h((string) $sideTicket['user_email']) ?></span>
                                        <span
                                            class="sti-meta"><?= h((string) (($sideTicket['assigned_email'] ?? '') !== '' ? $sideTicket['assigned_email'] : 'Niet toegewezen')) ?></span>
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