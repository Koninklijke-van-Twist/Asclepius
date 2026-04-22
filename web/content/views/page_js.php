<script>
    document.addEventListener('DOMContentLoaded', function ()
    {
        var blockedCheckbox = document.getElementById('priority_blocked');
        var fullyBlockedCheckbox = document.getElementById('priority_fully_blocked');
        var fullyBlockedWrap = document.getElementById('priority_fully_blocked_wrap');

        var syncPriorityVisibility = function ()
        {
            if (!blockedCheckbox || !fullyBlockedWrap || !fullyBlockedCheckbox)
            {
                return;
            }

            var wasHidden = fullyBlockedWrap.hidden;
            var showFullyBlocked = blockedCheckbox.checked;
            fullyBlockedWrap.hidden = !showFullyBlocked;

            if (showFullyBlocked && wasHidden)
            {
                fullyBlockedWrap.classList.remove('flash-blue');
                void fullyBlockedWrap.offsetWidth;
                fullyBlockedWrap.classList.add('flash-blue');
            }

            if (!showFullyBlocked)
            {
                fullyBlockedCheckbox.checked = false;
                fullyBlockedWrap.classList.remove('flash-blue');
            }
        };

        if (blockedCheckbox)
        {
            blockedCheckbox.addEventListener('change', syncPriorityVisibility);
            syncPriorityVisibility();
        }

        document.querySelectorAll('[data-settings-row]').forEach(function (row)
        {
            var availabilityCheckbox = row.querySelector('.availability-checkbox');
            var vacationIndicator = row.querySelector('.vacation-indicator');
            var vacationBadge = row.querySelector('.vacation-badge');

            if (!availabilityCheckbox)
            {
                return;
            }

            var syncAvailabilityState = function ()
            {
                var isAvailable = availabilityCheckbox.checked;
                row.classList.toggle('is-away', !isAvailable);
                if (vacationIndicator)
                {
                    vacationIndicator.hidden = isAvailable;
                }
                if (vacationBadge)
                {
                    vacationBadge.classList.toggle('is-away', !isAvailable);
                }
            };

            availabilityCheckbox.addEventListener('change', syncAvailabilityState);
            syncAvailabilityState();
        });

        var liveTicketSection = document.querySelector('[data-live-ticket-section]');
        var liveTicketPollTimer = null;
        var liveTicketRefreshInFlight = false;

        var captureOpenTicketIds = function (section)
        {
            return Array.prototype.map.call(
                section.querySelectorAll('details.ticket-card[open][data-ticket-id]'),
                function (detail)
                {
                    return detail.getAttribute('data-ticket-id');
                }
            );
        };

        var restoreOpenTicketIds = function (section, openTicketIds)
        {
            openTicketIds.forEach(function (ticketId)
            {
                var detail = section.querySelector('details.ticket-card[data-ticket-id="' + ticketId + '"]');
                if (detail)
                {
                    detail.open = true;
                }
            });
        };

        var ticketSectionHasActiveInput = function (section)
        {
            var activeElement = document.activeElement;
            if (!activeElement || !section.contains(activeElement))
            {
                return false;
            }

            return /INPUT|TEXTAREA|SELECT/.test(activeElement.tagName);
        };

        var refreshLiveTicketSection = function ()
        {
            if (!liveTicketSection || liveTicketRefreshInFlight || ticketSectionHasActiveInput(liveTicketSection))
            {
                return;
            }

            liveTicketRefreshInFlight = true;
            fetch(liveTicketSection.getAttribute('data-ticket-poll-url'), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'fetch'
                },
                credentials: 'same-origin'
            })
                .then(function (response)
                {
                    if (!response.ok)
                    {
                        throw new Error('ticket-refresh-failed');
                    }

                    return response.json();
                })
                .then(function (data)
                {
                    applyIncrementalTicketUpdate(data);
                })
                .catch(function ()
                {
                    window.location.reload();
                })
                .finally(function ()
                {
                    liveTicketRefreshInFlight = false;
                });
        };

        var ensureTicketList = function ()
        {
            var list = liveTicketSection.querySelector('.ticket-list');
            if (!list)
            {
                list = document.createElement('div');
                list.className = 'ticket-list';
                var emptyState = liveTicketSection.querySelector('.empty-state');
                if (emptyState)
                {
                    emptyState.remove();
                }
                liveTicketSection.appendChild(list);
            }

            return list;
        };

        var syncEmptyState = function (data)
        {
            var list = liveTicketSection.querySelector('.ticket-list');
            var emptyState = liveTicketSection.querySelector('.empty-state');

            if (data.is_empty)
            {
                if (list)
                {
                    list.remove();
                }
                if (!emptyState)
                {
                    var template = document.createElement('template');
                    template.innerHTML = (data.empty_html || '').trim();
                    emptyState = template.content.firstElementChild;
                    if (emptyState)
                    {
                        liveTicketSection.appendChild(emptyState);
                    }
                }
                return null;
            }

            if (emptyState)
            {
                emptyState.remove();
            }

            return ensureTicketList();
        };

        var setText = function (element, value)
        {
            if (element && element.textContent !== value)
            {
                element.textContent = value;
            }
        };

        var setValue = function (element, value)
        {
            if (element && element.value !== value)
            {
                element.value = value;
            }
        };

        var applyTicketCardFields = function (card, ticket)
        {
            setText(card.querySelector('[data-role="ticket-number"]'), '#' + ticket.id);
            setText(card.querySelector('[data-role="ticket-title"]'), ticket.title);
            setText(card.querySelector('[data-role="requester-email"]'), ticket.user_email);
            setText(card.querySelector('[data-role="ticket-category"]'), ticket.category_label);
            setText(card.querySelector('[data-role="ticket-created"]'), ticket.created_at_label);
            setText(card.querySelector('[data-role="message-count-badge"]'), String(ticket.message_count) + ' ' + '<?= addslashes(__('ticket.messages_count')) ?>');
            setText(card.querySelector('[data-role="meta-created-value"]'), ticket.meta_created_value);
            setText(card.querySelector('[data-role="meta-updated-value"]'), ticket.meta_updated_value);
            setValue(card.querySelector('[data-role="priority-select"]'), String(ticket.priority));
            setValue(card.querySelector('[data-role="status-select"]'), ticket.status);
            setValue(card.querySelector('[data-role="assigned-select"]'), ticket.assigned_email);

            var statusPill = card.querySelector('[data-role="status-pill"]');
            if (statusPill)
            {
                setText(statusPill, ticket.status_label);
                statusPill.style.setProperty('--ticket-color', ticket.status_color);
            }

            var priorityPill = card.querySelector('[data-role="priority-pill"]');
            if (priorityPill)
            {
                setText(priorityPill, '<?= addslashes(__('ticket.meta_priority')) ?> ' + ticket.priority + ' · ' + ticket.priority_label);
                priorityPill.style.setProperty('--ticket-color', ticket.priority_color);
            }

            var assigneeBadge = card.querySelector('[data-role="assignee-badge"]');
            if (assigneeBadge)
            {
                setText(assigneeBadge, ticket.assigned_label);
                assigneeBadge.style.setProperty('--assignee-color', ticket.assigned_color);
            }

            var timeOpenBadge = card.querySelector('[data-role="time-open-badge"]');
            if (timeOpenBadge)
            {
                setText(timeOpenBadge, '<?= addslashes(__('ticket.time_open')) ?> ' + ticket.time_open_label);
            }

            var reopenWrap = card.querySelector('[data-role="reopen-wrap"]');
            if (reopenWrap)
            {
                var reopenEnabled = reopenWrap.getAttribute('data-user-reopen-enabled') === '1';
                reopenWrap.hidden = !reopenEnabled || ticket.status !== 'afgehandeld';
            }

            card.style.setProperty('--ticket-color', ticket.status_color);
        };

        var appendNewMessages = function (card, ticket)
        {
            var messagesWrap = card.querySelector('[data-role="messages-wrap"]');
            var thread = card.querySelector('[data-role="thread"]');
            if (!messagesWrap || !thread)
            {
                return;
            }

            var existingMessageIds = {};
            thread.querySelectorAll('[data-message-id]').forEach(function (messageNode)
            {
                existingMessageIds[messageNode.getAttribute('data-message-id')] = true;
            });

            ticket.messages.forEach(function (message)
            {
                if (existingMessageIds[String(message.id)])
                {
                    return;
                }

                var template = document.createElement('template');
                template.innerHTML = message.html.trim();
                var messageNode = template.content.firstElementChild;
                if (messageNode)
                {
                    thread.appendChild(messageNode);
                    messagesWrap.hidden = false;
                }
            });
        };

        var insertOrMoveCard = function (list, card, previousCard)
        {
            if (!previousCard)
            {
                if (list.firstElementChild !== card)
                {
                    list.insertBefore(card, list.firstElementChild);
                }
                return;
            }

            if (previousCard.nextElementSibling !== card)
            {
                list.insertBefore(card, previousCard.nextElementSibling);
            }
        };

        var applyIncrementalTicketUpdate = function (data)
        {
            if (!liveTicketSection || !data)
            {
                return;
            }

            var currentSignature = liveTicketSection.getAttribute('data-ticket-signature') || '';
            if (data.signature && data.signature === currentSignature)
            {
                return;
            }

            var list = syncEmptyState(data);
            liveTicketSection.setAttribute('data-ticket-signature', data.signature || '');
            if (!list)
            {
                return;
            }

            var existingCards = {};
            list.querySelectorAll('[data-ticket-id]').forEach(function (card)
            {
                existingCards[card.getAttribute('data-ticket-id')] = card;
            });

            var seenTicketIds = {};
            var previousCard = null;

            (data.tickets || []).forEach(function (ticket)
            {
                var ticketId = String(ticket.id);
                var card = existingCards[ticketId] || null;
                seenTicketIds[ticketId] = true;

                if (!card)
                {
                    var template = document.createElement('template');
                    template.innerHTML = (ticket.card_html || '').trim();
                    card = template.content.firstElementChild;
                    if (!card)
                    {
                        return;
                    }
                } else
                {
                    applyTicketCardFields(card, ticket);
                    appendNewMessages(card, ticket);
                }

                insertOrMoveCard(list, card, previousCard);
                previousCard = card;
            });

            Object.keys(existingCards).forEach(function (ticketId)
            {
                if (!seenTicketIds[ticketId] && existingCards[ticketId].parentNode)
                {
                    existingCards[ticketId].parentNode.removeChild(existingCards[ticketId]);
                }
            });

            if (!list.children.length)
            {
                syncEmptyState({
                    is_empty: true,
                    empty_html: data.empty_html || ''
                });
            }
        };

        var pollLiveTicketSection = function ()
        {
            if (!liveTicketSection || document.hidden)
            {
                return;
            }

            fetch(liveTicketSection.getAttribute('data-ticket-poll-url'), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'fetch'
                },
                credentials: 'same-origin'
            })
                .then(function (response)
                {
                    if (!response.ok)
                    {
                        throw new Error('ticket-poll-failed');
                    }

                    return response.json();
                })
                .then(function (data)
                {
                    var currentSignature = liveTicketSection.getAttribute('data-ticket-signature') || '';
                    if (data && data.signature && data.signature !== currentSignature)
                    {
                        refreshLiveTicketSection();
                    }
                })
                .catch(function ()
                {
                    if (liveTicketPollTimer !== null)
                    {
                        window.clearInterval(liveTicketPollTimer);
                        liveTicketPollTimer = null;
                    }
                });
        };

        if (liveTicketSection)
        {
            var intervalMs = parseInt(liveTicketSection.getAttribute('data-ticket-poll-interval') || '15000', 10);
            liveTicketPollTimer = window.setInterval(pollLiveTicketSection, Math.max(intervalMs, 5000));
        }
    });
</script>