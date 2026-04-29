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
        var browserNotificationPollTimer = null;
        var browserNotificationInFlight = false;
        var browserNotificationRequestAttempted = false;
        var apiUrl = document.body ? (document.body.getAttribute('data-api-url') || 'api.php') : 'api.php';
        var apiKey = document.body ? (document.body.getAttribute('data-api-key') || '') : '';
        var browserNotificationPollUrl = apiUrl;
        var browserNotificationOpenTemplate = document.body ? (document.body.getAttribute('data-browser-notification-open-template') || '') : '';
        var webPushSubscribeUrl = apiUrl;
        var webPushVapidPublicKey = document.body ? (document.body.getAttribute('data-webpush-vapid-public-key') || '') : '';
        var webPushServiceWorkerUrl = document.body ? (document.body.getAttribute('data-webpush-sw-url') || '') : '';
        var csrfToken = document.body ? (document.body.getAttribute('data-csrf-token') || '') : '';
        var webPushSyncInFlight = false;
        var sessionExpiredHandled = false;
        var sessionExpiredCountdownTimer = null;
        var ticketPollPayload = {};
        if (liveTicketSection)
        {
            try
            {
                ticketPollPayload = JSON.parse(liveTicketSection.getAttribute('data-ticket-poll-payload') || '{}');
            } catch (error)
            {
                ticketPollPayload = {};
            }
        }
        var imagePreviewModal = document.createElement('div');
        imagePreviewModal.className = 'image-preview-modal';
        imagePreviewModal.setAttribute('aria-hidden', 'true');
        imagePreviewModal.innerHTML = '' +
            '<div class="image-preview-content" role="dialog" aria-modal="true">' +
            '<button type="button" class="image-preview-close" data-image-preview-close aria-label="<?= addslashes(__('ticket.preview_close')) ?>">&times;</button>' +
            '<img class="image-preview-full" src="" alt="">' +
            '</div>';

        if (document.body)
        {
            document.body.appendChild(imagePreviewModal);
        }

        var imagePreviewFull = imagePreviewModal.querySelector('.image-preview-full');
        var imagePreviewCloseButton = imagePreviewModal.querySelector('[data-image-preview-close]');

        var sessionExpiredModal = document.createElement('div');
        sessionExpiredModal.className = 'session-expired-modal';
        sessionExpiredModal.setAttribute('aria-hidden', 'true');
        sessionExpiredModal.innerHTML = '' +
            '<div class="session-expired-card" role="alertdialog" aria-modal="true">' +
            '<h2 class="session-expired-title"><?= addslashes(__('session.expired_popup')) ?></h2>' +
            '<p class="session-expired-countdown" data-session-expired-countdown></p>' +
            '</div>';

        if (document.body)
        {
            document.body.appendChild(sessionExpiredModal);
        }

        var sessionExpiredCountdown = sessionExpiredModal.querySelector('[data-session-expired-countdown]');

        var closeImagePreview = function ()
        {
            if (!imagePreviewModal.classList.contains('is-open'))
            {
                return;
            }

            imagePreviewModal.classList.remove('is-open');
            imagePreviewModal.setAttribute('aria-hidden', 'true');
            document.documentElement.style.overflow = '';

            window.setTimeout(function ()
            {
                if (!imagePreviewModal.classList.contains('is-open') && imagePreviewFull)
                {
                    imagePreviewFull.src = '';
                    imagePreviewFull.alt = '';
                }
            }, 250);
        };

        var openImagePreview = function (previewSrc, previewAlt)
        {
            if (!imagePreviewFull || !previewSrc)
            {
                return;
            }

            imagePreviewFull.src = previewSrc;
            imagePreviewFull.alt = previewAlt || '';
            imagePreviewModal.classList.add('is-open');
            imagePreviewModal.setAttribute('aria-hidden', 'false');
            document.documentElement.style.overflow = 'hidden';
        };

        document.addEventListener('click', function (event)
        {
            var trigger = event.target.closest('[data-image-preview-trigger]');
            if (trigger)
            {
                event.preventDefault();
                openImagePreview(trigger.getAttribute('data-preview-src') || '', trigger.getAttribute('data-preview-alt') || '');
                return;
            }

            if (!imagePreviewModal.classList.contains('is-open'))
            {
                return;
            }

            if ((imagePreviewCloseButton && event.target === imagePreviewCloseButton) || event.target === imagePreviewModal)
            {
                event.preventDefault();
                closeImagePreview();
            }
        });

        document.addEventListener('keydown', function (event)
        {
            if (event.key === 'Escape')
            {
                closeImagePreview();
            }
        });

        var base64UrlToUint8Array = function (base64String)
        {
            var normalized = String(base64String || '').replace(/-/g, '+').replace(/_/g, '/');
            var padding = normalized.length % 4;
            if (padding)
            {
                normalized += '='.repeat(4 - padding);
            }

            var rawData = window.atob(normalized);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i)
            {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        };

        var updateSessionExpiredCountdown = function (secondsRemaining)
        {
            if (!sessionExpiredCountdown)
            {
                return;
            }

            sessionExpiredCountdown.textContent = '<?= addslashes(__('session.auto_refresh_in', 0)) ?>'.replace('0', String(Math.max(0, secondsRemaining)));
        };

        var clearTicketHtml = function ()
        {
            if (!liveTicketSection)
            {
                return;
            }

            liveTicketSection.querySelectorAll('.ticket-list, .empty-state').forEach(function (node)
            {
                node.remove();
            });
            liveTicketSection.setAttribute('data-ticket-signature', '');
        };

        var handleSessionExpired = function ()
        {
            if (sessionExpiredHandled)
            {
                return;
            }

            sessionExpiredHandled = true;
            closeImagePreview();
            clearTicketHtml();

            if (liveTicketPollTimer !== null)
            {
                window.clearInterval(liveTicketPollTimer);
                liveTicketPollTimer = null;
            }

            if (browserNotificationPollTimer !== null)
            {
                window.clearInterval(browserNotificationPollTimer);
                browserNotificationPollTimer = null;
            }

            if (sessionExpiredModal)
            {
                sessionExpiredModal.classList.add('is-open');
                sessionExpiredModal.setAttribute('aria-hidden', 'false');
            }

            document.documentElement.style.overflow = 'hidden';

            var secondsRemaining = 60;
            updateSessionExpiredCountdown(secondsRemaining);
            sessionExpiredCountdownTimer = window.setInterval(function ()
            {
                secondsRemaining -= 1;
                updateSessionExpiredCountdown(secondsRemaining);
                if (secondsRemaining <= 0)
                {
                    window.clearInterval(sessionExpiredCountdownTimer);
                    window.location.replace(window.location.href);
                }
            }, 1000);
        };

        window.asclepiusHandleSessionExpired = handleSessionExpired;

        var apiFetchJson = function (action, payload)
        {
            var requestPayload = Object.assign({}, payload || {});
            requestPayload.action = action;

            var headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'fetch'
            };
            if (apiKey)
            {
                headers['X-API-Key'] = apiKey;
            }

            return fetch(apiUrl, {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin',
                body: JSON.stringify(requestPayload)
            }).then(function (response)
            {
                if (response.status === 401)
                {
                    return response.json().catch(function ()
                    {
                        return {};
                    }).then(function (data)
                    {
                        if (data && data.reason === 'session_expired_refresh_required')
                        {
                            handleSessionExpired();
                            throw new Error('refresh-required');
                        }

                        throw new Error('unauthorized');
                    });
                }

                if (!response.ok)
                {
                    throw new Error('api-request-failed');
                }

                return response.json();
            });
        };

        var postWebPushSubscription = function (action, subscription)
        {
            if (!webPushSubscribeUrl)
            {
                return Promise.resolve();
            }

            return apiFetchJson('webpush_subscription', {
                csrf_token: csrfToken,
                subscription_action: action,
                subscription: subscription || null
            }).catch(function ()
            {
                // Retry happens on next refresh/poll cycle.
            });
        };

        var syncWebPushSubscription = function ()
        {
            if (webPushSyncInFlight)
            {
                return;
            }

            if (!webPushSubscribeUrl || !webPushVapidPublicKey || !webPushServiceWorkerUrl)
            {
                return;
            }

            if (!('serviceWorker' in navigator) || !('PushManager' in window))
            {
                return;
            }

            webPushSyncInFlight = true;

            navigator.serviceWorker.register(webPushServiceWorkerUrl)
                .then(function (registration)
                {
                    return registration.pushManager.getSubscription().then(function (existingSubscription)
                    {
                        return {
                            registration: registration,
                            subscription: existingSubscription
                        };
                    });
                })
                .then(function (context)
                {
                    if (Notification.permission !== 'granted')
                    {
                        if (!context.subscription)
                        {
                            return Promise.resolve();
                        }

                        var existingPayload = context.subscription.toJSON ? context.subscription.toJSON() : null;
                        return context.subscription.unsubscribe().then(function ()
                        {
                            return postWebPushSubscription('unsubscribe', existingPayload);
                        });
                    }

                    if (context.subscription)
                    {
                        var payload = context.subscription.toJSON ? context.subscription.toJSON() : null;
                        return postWebPushSubscription('subscribe', payload);
                    }

                    var appServerKey = base64UrlToUint8Array(webPushVapidPublicKey);
                    return context.registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: appServerKey
                    }).then(function (newSubscription)
                    {
                        var newPayload = newSubscription.toJSON ? newSubscription.toJSON() : null;
                        return postWebPushSubscription('subscribe', newPayload);
                    });
                })
                .catch(function ()
                {
                    // Browser/policy may block silent subscription attempts.
                })
                .finally(function ()
                {
                    webPushSyncInFlight = false;
                });
        };

        var requestBrowserNotificationPermission = function ()
        {
            if (!('Notification' in window) || browserNotificationRequestAttempted)
            {
                return;
            }

            if (Notification.permission !== 'default')
            {
                browserNotificationRequestAttempted = true;
                return;
            }

            browserNotificationRequestAttempted = true;
            Notification.requestPermission()
                .then(function (permission)
                {
                    if (permission === 'granted')
                    {
                        syncWebPushSubscription();
                    }
                })
                .catch(function ()
                {
                    // Intentionally ignored: browser decides whether prompts are allowed.
                });
        };

        var resolveNotificationOpenUrl = function (notification)
        {
            if (notification && notification.open_url)
            {
                return String(notification.open_url);
            }

            if (browserNotificationOpenTemplate && notification && notification.ticket_id)
            {
                return browserNotificationOpenTemplate.replace('__TICKET_ID__', String(notification.ticket_id));
            }

            return window.location.pathname;
        };

        var showDesktopNotification = function (notification)
        {
            if (!('Notification' in window) || Notification.permission !== 'granted')
            {
                return;
            }

            var title = (notification && notification.title ? String(notification.title) : '<?= addslashes(__('header.title')) ?>');
            var body = (notification && notification.body ? String(notification.body) : '');
            var tag = 'ticket-' + String(notification && notification.ticket_id ? notification.ticket_id : 'general');
            var desktopNotification = new Notification(title, {
                body: body,
                tag: tag,
                renotify: true
            });

            desktopNotification.onclick = function ()
            {
                window.focus();
                window.location.href = resolveNotificationOpenUrl(notification);
                desktopNotification.close();
            };

            window.setTimeout(function ()
            {
                desktopNotification.close();
            }, 12000);
        };

        var pollBrowserNotifications = function ()
        {
            if (!browserNotificationPollUrl || browserNotificationInFlight || document.hidden)
            {
                return;
            }

            if (!('Notification' in window))
            {
                return;
            }

            if (Notification.permission === 'default')
            {
                requestBrowserNotificationPermission();
                return;
            }

            if (Notification.permission !== 'granted')
            {
                return;
            }

            browserNotificationInFlight = true;
            apiFetchJson('browser_notifications_poll', {})
                .then(function (data)
                {
                    (data && Array.isArray(data.notifications) ? data.notifications : []).forEach(function (notification)
                    {
                        showDesktopNotification(notification);
                    });
                })
                .catch(function (error)
                {
                    if (error && (error.message === 'unauthorized' || error.message === 'refresh-required'))
                    {
                        return;
                    }

                    // Silently ignore: next poll can recover.
                })
                .finally(function ()
                {
                    browserNotificationInFlight = false;
                });
        };

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
            apiFetchJson('ticket_poll', ticketPollPayload)
                .then(function (data)
                {
                    applyIncrementalTicketUpdate(data);
                })
                .catch(function (error)
                {
                    if (error && (error.message === 'unauthorized' || error.message === 'refresh-required'))
                    {
                        return;
                    }

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

            apiFetchJson('ticket_poll', ticketPollPayload)
                .then(function (data)
                {
                    var currentSignature = liveTicketSection.getAttribute('data-ticket-signature') || '';
                    if (data && data.signature && data.signature !== currentSignature)
                    {
                        refreshLiveTicketSection();
                    }
                })
                .catch(function (error)
                {
                    if (error && (error.message === 'unauthorized' || error.message === 'refresh-required'))
                    {
                        return;
                    }

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

        if (browserNotificationPollUrl)
        {
            var browserNotificationIntervalMs = parseInt((document.body && document.body.getAttribute('data-browser-notification-poll-interval')) || '15000', 10);
            window.setTimeout(requestBrowserNotificationPermission, 900);
            window.setTimeout(syncWebPushSubscription, 950);
            window.setTimeout(pollBrowserNotifications, 1200);
            browserNotificationPollTimer = window.setInterval(pollBrowserNotifications, Math.max(browserNotificationIntervalMs, 5000));
            document.addEventListener('visibilitychange', function ()
            {
                if (!document.hidden)
                {
                    requestBrowserNotificationPermission();
                    syncWebPushSubscription();
                    pollBrowserNotifications();
                }
            });
        }

        // File preview modal
        var previewModal = null;
        var previewIframe = null;

        document.querySelectorAll('[data-file-preview-trigger]').forEach(function (button)
        {
            button.addEventListener('click', function (e)
            {
                e.preventDefault();
                var attachmentId = button.getAttribute('data-preview-id');
                openFilePreview(attachmentId);
            });
        });

        var openFilePreview = function (attachmentId)
        {
            if (!previewModal)
            {
                previewModal = document.createElement('div');
                previewModal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;display:flex;align-items:center;justify-content:center;';

                var modalContent = document.createElement('div');
                modalContent.style.cssText = 'background:white;border-radius:8px;width:90%;height:90%;max-width:1200px;max-height:800px;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,0.3);';

                var closeBtn = document.createElement('button');
                closeBtn.innerHTML = '✕';
                closeBtn.style.cssText = 'position:absolute;top:12px;right:12px;background:none;border:none;font-size:24px;cursor:pointer;width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:#999;';
                closeBtn.addEventListener('click', closeFilePreview);

                previewIframe = document.createElement('iframe');
                previewIframe.style.cssText = 'flex:1;border:none;border-radius:8px;';
                previewIframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

                modalContent.appendChild(closeBtn);
                modalContent.appendChild(previewIframe);
                previewModal.appendChild(modalContent);
                document.body.appendChild(previewModal);
            }

            previewModal.style.display = 'flex';
            if (previewIframe)
            {
                previewIframe.src = 'preview.php?id=' + encodeURIComponent(attachmentId);
            }
        };

        var closeFilePreview = function ()
        {
            if (previewModal)
            {
                previewModal.style.display = 'none';
                if (previewIframe)
                {
                    previewIframe.src = '';
                }
            }
        };

        document.addEventListener('keydown', function (e)
        {
            if (e.key === 'Escape' && previewModal && previewModal.style.display !== 'none')
            {
                closeFilePreview();
            }
        });
    });
</script>