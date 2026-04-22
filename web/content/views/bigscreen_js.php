<?php if ($canManageTickets && $view === 'stats'): ?>
    <?php
    $isMockAlert = isset($_GET['mock-alert']);
    $bsAllTickets = $store instanceof TicketStore ? $store->getTickets(true, '') : [];
    $bsMaxId = 0;
    $bsMockList = [];
    foreach ($bsAllTickets as $t) {
        $tid = (int) $t['id'];
        if ($tid > $bsMaxId) {
            $bsMaxId = $tid;
        }
        if ($isMockAlert) {
            $bsMockList[] = [
                'id' => $tid,
                'title' => (string) ($t['title'] ?? ''),
                'user_email' => (string) ($t['user_email'] ?? ''),
                'assigned_email' => (string) ($t['assigned_email'] ?? ''),
                'assigned_color' => emailToHexColor((string) ($t['assigned_email'] ?? '')),
            ];
        }
    }
    ?>
    <script>
            (function ()
            {
                var POLL_URL = 'admin.php?view=stats&_bigscreen_poll=1';
                var VERSION_URL = 'version';
                var CURRENT_VER = null;
                var versionChangedAt = null;
                var VERSION_RELOAD_DELAY_MS = 600000; // 10 minuten
                var reloadScheduled = false;

                /* ---- Auto-herstel: herlaad na vertraging, maximaal 1x gepland ---- */
                function scheduleReload (delayMs)
                {
                    if (reloadScheduled) { return; }
                    reloadScheduled = true;
                    setTimeout(function () { location.reload(); }, delayMs || 60000);
                }

                /* Vang onverwachte JS-fouten op de pagina zelf op */
                window.onerror = function () { scheduleReload(60000); return false; };
                window.onunhandledrejection = function () { scheduleReload(60000); };

                var MOCK_ALERT = <?= $isMockAlert ? 'true' : 'false' ?>;
                var MOCK_TICKETS = <?= json_encode($bsMockList, JSON_UNESCAPED_UNICODE) ?>;
                var currentMaxId = <?= $bsMaxId ?>;
                var alertActive = false;
                var ticketSnapshot = {};

                /* Vertaalstrings ingebakken via PHP zodat de juiste taal gebruikt wordt */
                var BS = <?= json_encode([
                    'noUserStats'  => __('bs.no_user_stats'),
                    'colUser'      => __('bs.col_user'),
                    'colSubmitted' => __('bs.col_submitted'),
                    'colAvgWait'   => __('bs.col_avg_wait'),
                    'colMaxWait'   => __('bs.col_max_wait'),
                    'waitNote'     => __('bs.wait_note'),
                    'sidebarEmpty' => __('stats.sidebar_empty'),
                    'notAssigned'  => __('stats.no_assigned'),
                    'cardChanged'  => __('bs.card_changed'),
                    'cardStatus'   => __('bs.card_status'),
                    'cardAssigned' => __('bs.card_assigned'),
                    'cardUnassigned' => __('bs.card_unassigned'),
                    'cardNewMsg'   => __('bs.card_new_message'),
                    'newTicketFrom' => __('bs.new_ticket_from'),
                    'assignedTo'   => __('bs.assigned_to'),
                    'notYetAssigned' => __('bs.not_yet_assigned'),
                ], JSON_UNESCAPED_UNICODE) ?>;
                var snapshotReady = false;

                var CARD_LIFETIME_MS = 70000;

                /* ---- Update-kaarten links ---- */
                var updatesContainer = document.getElementById('bs-updates');

                function pushUpdateCard (lines)
                {
                    var card = document.createElement('div');
                    card.className = 'bs-update-card';
                    var main = document.createElement('span');
                    main.textContent = lines[0];
                    card.appendChild(main);
                    for (var i = 1; i < lines.length; i++)
                    {
                        var sub = document.createElement('div');
                        sub.className = 'bsuc-sub';
                        sub.textContent = lines[i];
                        card.appendChild(sub);
                    }
                    updatesContainer.appendChild(card);
                    // Auto-verwijder na CARD_LIFETIME_MS
                    setTimeout(function ()
                    {
                        if (card.parentNode) { card.parentNode.removeChild(card); }
                    }, CARD_LIFETIME_MS);
                }

                /* ---- Bigscreen alert overlay ---- */
                function showPhase1 ()
                {
                    alertActive = true;
                    var overlay = document.getElementById('bigscreen-overlay');
                    var hazTop = document.getElementById('bs-hazard-top');
                    var hazBot = document.getElementById('bs-hazard-bottom');
                    var emoji = document.getElementById('bs-warning-emoji');
                    var label = document.getElementById('bs-max-prio-label');
                    var info = document.getElementById('bs-ticket-info');
                    overlay.className = 'bs-phase1';
                    hazTop.hidden = false;
                    hazBot.hidden = false;
                    emoji.hidden = false;
                    label.hidden = false;
                    info.hidden = true;
                }

                function showPhase2 (ticket)
                {
                    var overlay = document.getElementById('bigscreen-overlay');
                    var hazTop = document.getElementById('bs-hazard-top');
                    var hazBot = document.getElementById('bs-hazard-bottom');
                    var emoji = document.getElementById('bs-warning-emoji');
                    var label = document.getElementById('bs-max-prio-label');
                    var info = document.getElementById('bs-ticket-info');
                    var headline = document.getElementById('bs-headline');
                    var titleEl = document.getElementById('bs-title');
                    var assigneeEl = document.getElementById('bs-assignee');

headline.textContent = BS.newTicketFrom.replace('%s', ticket.user_email);
                        titleEl.textContent = ticket.title;

                        assigneeEl.innerHTML = '';
                        if (ticket.assigned_email)
                        {
                            var pill = document.createElement('span');
                            pill.className = 'bs-assignee-pill';
                            pill.style.background = ticket.assigned_color || '#0b65c2';
                            pill.textContent = BS.assignedTo.replace('%s', ticket.assigned_email);
                            assigneeEl.appendChild(pill);
                        } else
                        {
                            assigneeEl.textContent = BS.notYetAssigned;
                    }

                    overlay.className = 'bs-phase2';
                    hazTop.hidden = true;
                    hazBot.hidden = true;
                    emoji.hidden = true;
                    label.hidden = true;
                    info.hidden = false;
                }

                function runAlert (ticket)
                {
                    var isMaxPrio = (ticket.priority || 0) >= 2;
                    if (isMaxPrio)
                    {
                        showPhase1();
                        setTimeout(function ()
                        {
                            showPhase2(ticket);
                            setTimeout(function ()
                            {
                                alertActive = false;
                                hideOverlay();
                            }, 10000);
                        }, 5000);
                    } else
                    {
                        alertActive = true;
                        showPhase2(ticket);
                        setTimeout(function ()
                        {
                            alertActive = false;
                            hideOverlay();
                        }, 10000);
                    }
                }

                function hideOverlay ()
                {
                    var overlay = document.getElementById('bigscreen-overlay');
                    if (overlay) { overlay.className = ''; }
                }

                /* ---- Live DOM-updates voor stats ---- */
                function setText (id, val)
                {
                    var el = document.getElementById(id);
                    if (el) { el.textContent = val; }
                }

                function updateStatsDOM (data)
                {
                    var os = data.overall_stats;
                    if (os)
                    {
                        setText('stat-total', os.total_tickets || 0);
                        setText('stat-open', os.open_tickets || 0);
                        setText('stat-resolved', os.resolved_tickets || 0);
                        setText('stat-waiting', os.waiting_order_tickets || 0);
                    }

                    var ictTbody = document.getElementById('stats-ict-tbody');
                    if (ictTbody && data.ict_stats)
                    {
                        var rows = '';
                        data.ict_stats.forEach(function (r)
                        {
                            var color = r.available ? r.user_color : '#94a3b8';
                            var badge = r.available ? '' : ' vacation-badge is-away';
                            var palm = r.available ? '' : ' 🌴';
                            rows += '<tr>'
                                + '<td class="user-color-cell" style="--assignee-color:' + esc(r.user_color) + ';">'
                                + '<span class="assignee-badge' + badge + '" style="--assignee-color:' + esc(color) + ';">'
                                + esc(r.user_email) + palm + '</span></td>'
                                + '<td>' + r.handled_count + '</td>'
                                + '<td>' + esc(r.average_open) + '</td>'
                                + '<td>' + esc(r.max_open) + '</td>'
                                + '<td>' + r.open_count + '</td>'
                                + '<td>' + r.waiting_order_count + '</td>'
                                + '</tr>';
                        });
                        ictTbody.innerHTML = rows;
                    }

                    var reqWrap = document.getElementById('stats-requester-wrap');
                    if (reqWrap && data.requester_stats)
                    {
                        if (data.requester_stats.length === 0)
                        {
                            reqWrap.innerHTML = '<div class="empty-state">' + esc(BS.noUserStats) + '</div>';
                        } else
                        {
                            var rrows = '';
                            data.requester_stats.forEach(function (r)
                            {
                                rrows += '<tr>'
                                    + '<td>' + esc(r.user_email) + '</td>'
                                    + '<td>' + r.submitted_count + '</td>'
                                    + '<td>' + esc(r.average_wait) + '</td>'
                                    + '<td>' + esc(r.max_wait) + '</td>'
                                    + '</tr>';
                            });
                            reqWrap.innerHTML = '<div class="table-wrap"><table>'
                                + '<thead><tr><th>' + esc(BS.colUser) + '</th><th>' + esc(BS.colSubmitted) + '</th><th>' + esc(BS.colAvgWait) + '</th><th>' + esc(BS.colMaxWait) + '</th></tr></thead>'
                                + '<tbody>' + rrows + '</tbody></table></div>'
                                + '<p class="stats-note">' + BS.waitNote + '</p>';
                        }
                    }

                    var sideList = document.getElementById('stats-sidebar-list');
                    if (sideList && data.open_tickets)
                    {
                        if (data.open_tickets.length === 0)
                        {
                            sideList.innerHTML = '<p style="color:var(--muted);font-size:13px;">' + esc(BS.sidebarEmpty) + '</p>';
                        } else
                        {
                            var sitems = '';
                            data.open_tickets.forEach(function (t)
                            {
                                var assigned = t.assigned_email || BS.notAssigned;
                                var statusLabel = t.status_label || t.status;
                                sitems += '<div class="stats-ticket-item" style="--ticket-color:' + esc(t.status_color) + ';">' 
                                    + '<div class="sti-body">'
                                    + '<span class="sti-title">#' + t.id + ' ' + esc(t.title) + '</span>'
                                    + '<span class="sti-meta">' + esc(statusLabel) + ' · ' + esc(t.user_email) + '</span>'
                                    + '<span class="sti-meta">' + esc(assigned) + '</span>'
                                    + '</div>'
                                    + '<span class="sti-prio sti-prio-' + t.priority + '">' + t.priority + '</span>'
                                    + '</div>';
                            });
                            sideList.innerHTML = sitems;
                        }
                    }
                }

                function esc (str)
                {
                    return String(str)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                /* ---- Wijzigingsdetectie via snapshot ---- */
                function applySnapshot (snapshotArr)
                {
                    if (!snapshotArr) { return; }
                    snapshotArr.forEach(function (t)
                    {
                        var id = t.id;
                        var prev = ticketSnapshot[id];
                        if (!prev)
                        {
                            ticketSnapshot[id] = t;
                            return;
                        }
                        if (!snapshotReady) { return; }
                        var changes = [];
                        if (t.status !== prev.status)
                        { changes.push(BS.cardStatus.replace('%s', t.status)); }
                        if (t.assigned_email !== prev.assigned_email)
                        { changes.push(BS.cardAssigned.replace('%s', t.assigned_email || BS.cardUnassigned)); }
                        if (t.message_count !== prev.message_count)
                        { changes.push(BS.cardNewMsg); }
                        if (changes.length > 0)
                        { pushUpdateCard([BS.cardChanged.replace('%d', id)].concat(changes)); }
                        ticketSnapshot[id] = t;
                    });
                    snapshotReady = true;
                }

                /* ---- Poll ---- */
                function poll ()
                {
                    if (alertActive) { return; }
                    fetch(POLL_URL, { credentials: 'same-origin' })
                        .then(function (r)
                        {
                            if (!r.ok)
                            {
                                // Ongeldige state (bijv. sessie verlopen, 500)
                                scheduleReload(60000);
                                return null;
                            }
                            return r.json();
                        })
                        .then(function (data)
                        {
                            if (!data) { return; }
                            applySnapshot(data.snapshot || null);
                            updateStatsDOM(data);
                            if (data.max_id > currentMaxId && data.latest)
                            {
                                currentMaxId = data.max_id;
                                runAlert(data.latest);
                            }
                        })
                        .catch(function () { scheduleReload(60000); });
                }

                function pollVersion ()
                {
                    fetch(VERSION_URL, { credentials: 'same-origin', cache: 'no-store' })
                        .then(function (r) { return r.ok ? r.text() : null; })
                        .then(function (ver)
                        {
                            if (!ver) { return; }
                            ver = ver.trim();
                            if (CURRENT_VER === null) { CURRENT_VER = ver; return; }
                            if (ver !== CURRENT_VER)
                            {
                                if (versionChangedAt === null)
                                {
                                    versionChangedAt = Date.now();
                                }
                                if (Date.now() - versionChangedAt >= VERSION_RELOAD_DELAY_MS)
                                {
                                    scheduleReload(0);
                                }
                            } else
                            {
                                // Versie weer hetzelfde — reset timer
                                versionChangedAt = null;
                            }
                        })
                        .catch(function () { scheduleReload(60000); });
                }

                setInterval(poll, 2000);
                setInterval(pollVersion, 10000);

                if (MOCK_ALERT && MOCK_TICKETS.length > 0)
                {
                    setTimeout(function ()
                    {
                        if (!alertActive)
                        {
                            var t = MOCK_TICKETS[Math.floor(Math.random() * MOCK_TICKETS.length)];
                            runAlert(t);
                        }
                    }, 3000);
                }
            }());
    </script>
<?php endif; ?>