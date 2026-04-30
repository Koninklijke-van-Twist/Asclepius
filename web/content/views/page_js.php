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

        var initializeEmailChipInputs = function (scope)
        {
            (scope || document).querySelectorAll('input[data-email-chip-input="1"]').forEach(function (input)
            {
                if (input.dataset.emailChipReady === '1')
                {
                    return;
                }

                var originalName = input.getAttribute('name') || '';
                var initialValue = input.value || '';
                var hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = originalName;

                var chipField = document.createElement('div');
                chipField.className = 'email-chip-field';

                var chipInput = document.createElement('input');
                chipInput.type = 'text';
                chipInput.className = 'email-chip-input';
                chipInput.placeholder = input.getAttribute('placeholder') || '';
                chipInput.setAttribute('aria-label', input.getAttribute('aria-label') || chipInput.placeholder || 'Email');

                var chips = [];
                var addChip = function (value)
                {
                    var normalized = String(value || '').trim().toLowerCase();
                    if (!normalized)
                    {
                        return false;
                    }

                    var validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized);
                    if (!validEmail || chips.indexOf(normalized) !== -1)
                    {
                        return false;
                    }

                    chips.push(normalized);
                    return true;
                };

                var syncHiddenValue = function ()
                {
                    hiddenInput.value = chips.join(', ');
                };

                var renderChips = function ()
                {
                    chipField.querySelectorAll('.email-chip-item').forEach(function (node)
                    {
                        node.remove();
                    });

                    chips.forEach(function (email, index)
                    {
                        var chip = document.createElement('span');
                        chip.className = 'email-chip-item';
                        chip.textContent = email;

                        var removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'email-chip-remove';
                        removeButton.setAttribute('aria-label', 'Remove ' + email);
                        removeButton.textContent = '×';
                        removeButton.addEventListener('click', function ()
                        {
                            chips.splice(index, 1);
                            renderChips();
                        });

                        chip.appendChild(removeButton);
                        chipField.insertBefore(chip, chipInput);
                    });

                    syncHiddenValue();
                };

                var flushTypedEmailToChip = function ()
                {
                    var candidate = chipInput.value;
                    if (!candidate)
                    {
                        return;
                    }

                    if (addChip(candidate))
                    {
                        chipInput.value = '';
                        renderChips();
                    }
                };

                initialValue.split(/[;,\n\r]+/).forEach(function (token)
                {
                    addChip(token);
                });

                chipInput.addEventListener('keydown', function (event)
                {
                    if (event.key === ',' || event.key === 'Enter' || event.key === 'Tab')
                    {
                        if (chipInput.value.trim() !== '')
                        {
                            event.preventDefault();
                            flushTypedEmailToChip();
                        }
                    }
                });

                chipInput.addEventListener('blur', function ()
                {
                    flushTypedEmailToChip();
                });

                var parentForm = input.closest('form');
                if (parentForm)
                {
                    parentForm.addEventListener('submit', function ()
                    {
                        flushTypedEmailToChip();
                        syncHiddenValue();
                    });
                }

                input.removeAttribute('name');
                input.style.display = 'none';
                input.dataset.emailChipReady = '1';

                chipField.appendChild(chipInput);
                input.parentNode.insertBefore(hiddenInput, input);
                input.parentNode.insertBefore(chipField, input.nextSibling);

                renderChips();
            });
        };

        initializeEmailChipInputs(document);

        var parseParticipantEmails = function (value, fallbackEmail)
        {
            if (Array.isArray(value))
            {
                return value.map(function (item)
                {
                    return String(item || '').trim().toLowerCase();
                }).filter(function (email)
                {
                    return email !== '';
                });
            }

            if (typeof value === 'string' && value.trim() !== '')
            {
                try
                {
                    var parsed = JSON.parse(value);
                    if (Array.isArray(parsed))
                    {
                        return parseParticipantEmails(parsed, fallbackEmail);
                    }
                } catch (error)
                {
                    // Ignore invalid serialized value and fallback below.
                }
            }

            var fallback = String(fallbackEmail || '').trim().toLowerCase();
            return fallback ? [fallback] : [];
        };

        var refreshPendingRemoveConstraints = function (ticketCard)
        {
            if (!ticketCard)
            {
                return;
            }

            var toggles = Array.prototype.slice.call(ticketCard.querySelectorAll('[data-role="participant-remove-toggle"]'));
            if (toggles.length <= 1)
            {
                toggles.forEach(function (toggle)
                {
                    toggle.classList.add('is-lock-protected');
                });
                return;
            }

            var pending = toggles.filter(function (toggle)
            {
                return toggle.classList.contains('is-pending-remove');
            });

            toggles.forEach(function (toggle)
            {
                var lockProtected = !toggle.classList.contains('is-pending-remove') && pending.length >= toggles.length - 1;
                toggle.classList.toggle('is-lock-protected', lockProtected);
            });
        };

        var renderParticipantManagerList = function (ticketCard, participantEmails, creatorEmail)
        {
            if (!ticketCard)
            {
                return;
            }

            var list = ticketCard.querySelector('[data-role="participant-chip-list"]');
            if (!list)
            {
                return;
            }

            var ticketId = Number(ticketCard.getAttribute('data-ticket-id') || 0);
            var normalizedCreator = String(creatorEmail || '').trim().toLowerCase();
            var emails = parseParticipantEmails(participantEmails, normalizedCreator);
            list.innerHTML = '';

            emails.forEach(function (email)
            {
                var removeToggle = document.createElement('button');
                removeToggle.type = 'button';
                removeToggle.className = 'participant-chip-form';
                removeToggle.setAttribute('data-role', 'participant-remove-toggle');
                removeToggle.setAttribute('data-ticket-id', String(ticketId));
                removeToggle.setAttribute('data-participant-email', email);

                var chip = document.createElement('span');
                chip.className = 'participant-chip' + (normalizedCreator !== '' && email === normalizedCreator ? ' is-requester' : '');

                var chipLabel = document.createElement('span');
                chipLabel.className = 'participant-chip-label';
                chipLabel.textContent = email;
                chip.appendChild(chipLabel);

                var chipRemoveLabel = document.createElement('span');
                chipRemoveLabel.className = 'participant-chip-remove-text';
                chipRemoveLabel.textContent = '<?= addslashes(__('ticket.participant_remove')) ?>';
                chip.appendChild(chipRemoveLabel);

                removeToggle.appendChild(chip);
                list.appendChild(removeToggle);
            });

            refreshPendingRemoveConstraints(ticketCard);
        };

        var applyParticipantSummaryToCard = function (ticketCard, participantEmails, requesterLabel, requesterTooltip, creatorEmail)
        {
            if (!ticketCard)
            {
                return;
            }

            var requesterElement = ticketCard.querySelector('[data-role="requester-email"]');
            var normalizedCreator = String(creatorEmail || '').trim().toLowerCase();
            var emails = parseParticipantEmails(participantEmails, normalizedCreator);
            var label = String(requesterLabel || '');
            var tooltip = String(requesterTooltip || '');
            if (!label)
            {
                label = emails.length > 0 ? emails[0] + (emails.length > 1 ? ' +' + String(emails.length - 1) : '') : normalizedCreator;
            }
            if (!tooltip)
            {
                tooltip = emails.join('\n');
            }

            if (requesterElement)
            {
                requesterElement.textContent = label;
                requesterElement.title = emails.length > 1 ? tooltip : '';
                requesterElement.setAttribute('data-user-emails', JSON.stringify(emails));
                requesterElement.setAttribute('data-ticket-users-trigger', emails.length > 1 ? '1' : '0');
                requesterElement.classList.toggle('requester-multi', emails.length > 1);
            }

            var usersPopoverList = ticketCard.querySelector('[data-role="ticket-users-popover-list"]');
            if (usersPopoverList)
            {
                usersPopoverList.innerHTML = '';
                emails.forEach(function (email)
                {
                    var listItem = document.createElement('li');
                    listItem.textContent = email;
                    usersPopoverList.appendChild(listItem);
                });
            }

            renderParticipantManagerList(ticketCard, emails, normalizedCreator);
        };

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
            var openParticipantsButton = event.target.closest('[data-role="manage-participants-open"]');
            if (openParticipantsButton)
            {
                event.preventDefault();
                event.stopPropagation();
                var openCard = openParticipantsButton.closest('details.ticket-card');
                var openModal = openCard ? openCard.querySelector('[data-role="ticket-participants-modal"]') : null;
                if (openModal)
                {
                    openModal.hidden = false;
                    openModal.classList.add('is-open');
                    document.documentElement.style.overflow = 'hidden';
                    initializeEmailChipInputs(openModal);
                }
                return;
            }

            var closeParticipantsButton = event.target.closest('[data-role="manage-participants-close"]');
            if (closeParticipantsButton)
            {
                event.preventDefault();
                var closeModal = closeParticipantsButton.closest('[data-role="ticket-participants-modal"]');
                if (closeModal)
                {
                    closeModal.hidden = true;
                    closeModal.classList.remove('is-open');
                    document.documentElement.style.overflow = '';
                }
                return;
            }

            if (event.target.matches('[data-role="ticket-participants-modal"]'))
            {
                event.preventDefault();
                event.target.hidden = true;
                event.target.classList.remove('is-open');
                document.documentElement.style.overflow = '';
                return;
            }

            var removeToggle = event.target.closest('[data-role="participant-remove-toggle"]');
            if (removeToggle)
            {
                event.preventDefault();
                event.stopPropagation();

                if (removeToggle.classList.contains('is-lock-protected'))
                {
                    return;
                }

                if (removeToggle.classList.contains('is-pending-remove'))
                {
                    removeToggle.classList.remove('is-pending-remove');
                    refreshPendingRemoveConstraints(removeToggle.closest('details.ticket-card'));
                    return;
                }

                removeToggle.classList.add('is-pending-remove');
                refreshPendingRemoveConstraints(removeToggle.closest('details.ticket-card'));
                return;
            }

            var usersTrigger = event.target.closest('[data-ticket-users-trigger="1"]');
            if (usersTrigger)
            {
                var ticketCard = usersTrigger.closest('details.ticket-card');
                if (!ticketCard || !ticketCard.open)
                {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                var popover = ticketCard.querySelector('[data-role="ticket-users-popover"]');
                if (!popover)
                {
                    return;
                }

                popover.hidden = !popover.hidden;
                return;
            }

            if (!event.target.closest('[data-role="ticket-users-popover"]'))
            {
                document.querySelectorAll('[data-role="ticket-users-popover"]').forEach(function (popover)
                {
                    popover.hidden = true;
                });
            }

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
                document.querySelectorAll('[data-role="ticket-participants-modal"].is-open').forEach(function (modal)
                {
                    modal.hidden = true;
                    modal.classList.remove('is-open');
                });
                document.documentElement.style.overflow = '';
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

        var setParticipantFeedback = function (ticketCard, message, isError)
        {
            if (!ticketCard)
            {
                return;
            }

            var feedbackNode = ticketCard.querySelector('[data-role="manage-participants-feedback"]');
            if (!feedbackNode)
            {
                return;
            }

            feedbackNode.textContent = String(message || '');
            feedbackNode.classList.toggle('is-error', !!isError);
            feedbackNode.classList.toggle('is-success', !isError && String(message || '') !== '');
        };

        var syncParticipantsViaApi = function (ticketCard, operation, payload)
        {
            if (!ticketCard)
            {
                return Promise.resolve();
            }

            setParticipantFeedback(ticketCard, '', false);
            return apiFetchJson('manage_ticket_participants', Object.assign({
                operation: operation,
                ticket_id: Number(ticketCard.getAttribute('data-ticket-id') || 0),
                viewer_email: ticketPollPayload.viewer_email || '',
                user_is_admin: !!ticketPollPayload.user_is_admin
            }, payload || {})).then(function (data)
            {
                if (!data || data.success !== true)
                {
                    setParticipantFeedback(ticketCard, data && data.error ? data.error : '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                    return data || null;
                }

                applyParticipantSummaryToCard(
                    ticketCard,
                    data.participant_emails,
                    data.requester_label,
                    data.requester_tooltip,
                    data.creator_email || ''
                );
                setParticipantFeedback(ticketCard, data.message || '', false);
                return data;
            }).catch(function ()
            {
                setParticipantFeedback(ticketCard, '<?= addslashes(__('flash.db_error_prefix')) ?>', true);
                return null;
            });
        };

        var collectPendingRemoveEmails = function (ticketCard)
        {
            if (!ticketCard)
            {
                return [];
            }

            return Array.prototype.map.call(
                ticketCard.querySelectorAll('[data-role="participant-remove-toggle"].is-pending-remove'),
                function (node)
                {
                    return String(node.getAttribute('data-participant-email') || '').trim().toLowerCase();
                }
            ).filter(function (email)
            {
                return email !== '';
            });
        };

        var clearParticipantInputChips = function (form)
        {
            if (!form)
            {
                return;
            }

            var participantInput = form.querySelector('input[name="participant_emails"]');
            if (participantInput)
            {
                participantInput.value = '';
            }

            var chipContainer = form.querySelector('.email-chip-field');
            if (chipContainer)
            {
                chipContainer.querySelectorAll('.email-chip-item').forEach(function (chip)
                {
                    chip.remove();
                });
            }
        };

        var closeParticipantsModal = function (ticketCard)
        {
            if (!ticketCard)
            {
                return;
            }

            var modal = ticketCard.querySelector('[data-role="ticket-participants-modal"]');
            if (!modal)
            {
                return;
            }

            var modalCard = modal.querySelector('.ticket-participants-modal-card');
            if (modalCard)
            {
                modalCard.classList.remove('is-save-hover');
            }
            modal.hidden = true;
            modal.classList.remove('is-open');
            document.documentElement.style.overflow = '';
        };

        document.addEventListener('submit', function (event)
        {
            var addForm = event.target.closest('[data-role="participant-add-form"]');
            if (addForm)
            {
                event.preventDefault();
                var addCard = addForm.closest('details.ticket-card');
                var participantInput = addForm.querySelector('input[name="participant_emails"]');
                var participantsRaw = participantInput ? participantInput.value : '';
                var pendingRemovals = collectPendingRemoveEmails(addCard);

                syncParticipantsViaApi(addCard, 'apply', {
                    participant_emails: participantsRaw,
                    remove_participant_emails: pendingRemovals
                }).then(function (data)
                {
                    if (!data || data.success !== true)
                    {
                        return;
                    }
                    clearParticipantInputChips(addForm);
                });
                return;
            }

        });

        document.addEventListener('mouseover', function (event)
        {
            var applyButton = event.target.closest('[data-role="participants-apply-button"]');
            if (!applyButton)
            {
                return;
            }

            var modalCard = applyButton.closest('.ticket-participants-modal-card');
            if (modalCard)
            {
                modalCard.classList.add('is-save-hover');
            }
        });

        document.addEventListener('mouseout', function (event)
        {
            var applyButton = event.target.closest('[data-role="participants-apply-button"]');
            if (!applyButton)
            {
                return;
            }

            if (event.relatedTarget && applyButton.contains(event.relatedTarget))
            {
                return;
            }

            var modalCard = applyButton.closest('.ticket-participants-modal-card');
            if (modalCard)
            {
                modalCard.classList.remove('is-save-hover');
            }
        });

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
            if (section.querySelector('[data-role="ticket-participants-modal"].is-open'))
            {
                return true;
            }

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
            applyParticipantSummaryToCard(card, ticket.participant_emails, ticket.requester_label, ticket.requester_tooltip, ticket.user_email);
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
                if (typeof hydrateTicketThumbnails === 'function')
                {
                    hydrateTicketThumbnails(card);
                }
                initializeEmailChipInputs(card);
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

        // File preview modal + lazy thumbnails
        var previewModal = null;
        var previewIframe = null;

        var hydrateTicketThumbnails = function (ticketCard)
        {
            if (!ticketCard || !ticketCard.open)
            {
                return;
            }

            ticketCard.querySelectorAll('img[data-thumb-src]').forEach(function (imageThumb)
            {
                if (imageThumb.dataset.thumbLoaded === '1')
                {
                    return;
                }

                var thumbSrc = imageThumb.getAttribute('data-thumb-src') || '';
                if (!thumbSrc)
                {
                    var missingSrcButton = imageThumb.closest('.attachment-thumb-button');
                    if (missingSrcButton)
                    {
                        missingSrcButton.remove();
                    }
                    return;
                }

                imageThumb.dataset.thumbLoaded = '1';
                imageThumb.src = thumbSrc;
                imageThumb.addEventListener('error', function ()
                {
                    var brokenThumbButton = imageThumb.closest('.attachment-thumb-button');
                    if (brokenThumbButton)
                    {
                        brokenThumbButton.remove();
                    }
                }, { once: true });
            });

            ticketCard.querySelectorAll('[data-file-thumb-open]').forEach(function (thumbButton)
            {
                if (thumbButton.dataset.thumbInitialized === '1')
                {
                    return;
                }

                thumbButton.dataset.thumbInitialized = '1';
                var checkUrl = thumbButton.getAttribute('data-file-thumb-check-url') || '';
                var thumbSrc = thumbButton.getAttribute('data-file-thumb-src') || '';
                var thumbFrame = thumbButton.querySelector('.attachment-file-thumb-frame');

                if (!checkUrl || !thumbSrc || !thumbFrame)
                {
                    thumbButton.remove();
                    return;
                }

                fetch(checkUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'fetch'
                    }
                })
                    .then(function (response)
                    {
                        if (!response.ok)
                        {
                            throw new Error('thumb-check-failed');
                        }

                        return response.json();
                    })
                    .then(function (payload)
                    {
                        if (!payload || payload.ok !== true)
                        {
                            throw new Error('thumb-not-available');
                        }

                        thumbButton.hidden = false;
                        thumbFrame.src = thumbSrc;
                    })
                    .catch(function ()
                    {
                        thumbButton.remove();
                    });
            });
        };

        document.querySelectorAll('details.ticket-card[open]').forEach(function (ticketCard)
        {
            hydrateTicketThumbnails(ticketCard);
        });

        document.addEventListener('toggle', function (event)
        {
            var ticketCard = event.target;
            if (!ticketCard || !ticketCard.matches || !ticketCard.matches('details.ticket-card'))
            {
                return;
            }

            hydrateTicketThumbnails(ticketCard);
        }, true);

        var openFilePreview = function (attachmentId)
        {
            if (!attachmentId)
            {
                return;
            }

            if (!previewModal)
            {
                previewModal = document.createElement('div');
                previewModal.className = 'file-preview-modal';
                previewModal.setAttribute('aria-hidden', 'true');

                var modalContent = document.createElement('div');
                modalContent.className = 'file-preview-content';
                modalContent.setAttribute('role', 'dialog');
                modalContent.setAttribute('aria-modal', 'true');

                var closeBtn = document.createElement('button');
                closeBtn.innerHTML = '&times;';
                closeBtn.className = 'image-preview-close';
                closeBtn.setAttribute('data-file-preview-close', '1');
                closeBtn.setAttribute('aria-label', '<?= addslashes(__('ticket.preview_close')) ?>');
                closeBtn.addEventListener('click', closeFilePreview);

                previewIframe = document.createElement('iframe');
                previewIframe.className = 'file-preview-frame';
                previewIframe.setAttribute('sandbox', 'allow-scripts allow-same-origin');

                modalContent.appendChild(closeBtn);
                modalContent.appendChild(previewIframe);
                previewModal.appendChild(modalContent);
                document.body.appendChild(previewModal);
            }

            previewModal.classList.add('is-open');
            previewModal.setAttribute('aria-hidden', 'false');
            document.documentElement.style.overflow = 'hidden';
            if (previewIframe)
            {
                previewIframe.src = 'preview.php?id=' + encodeURIComponent(attachmentId);
            }
        };

        var closeFilePreview = function ()
        {
            if (previewModal)
            {
                previewModal.classList.remove('is-open');
                previewModal.setAttribute('aria-hidden', 'true');
                document.documentElement.style.overflow = '';
                if (previewIframe)
                {
                    window.setTimeout(function ()
                    {
                        if (!previewModal.classList.contains('is-open'))
                        {
                            previewIframe.src = '';
                        }
                    }, 200);
                }
            }
        };

        document.addEventListener('click', function (event)
        {
            var filePreviewTrigger = event.target.closest('[data-file-preview-trigger], [data-file-thumb-open]');
            if (filePreviewTrigger)
            {
                event.preventDefault();
                openFilePreview(filePreviewTrigger.getAttribute('data-preview-id'));
                return;
            }

            if (previewModal && event.target === previewModal)
            {
                event.preventDefault();
                closeFilePreview();
            }
        });

        document.addEventListener('keydown', function (e)
        {
            if (e.key === 'Escape' && previewModal && previewModal.classList.contains('is-open'))
            {
                closeFilePreview();
            }
        });

        /**
         * Key picker popup
         */
        var KEY_PICKER_GROUPS = [
            {
                label: 'Modifiers',
                keys: [
                    { token: 'ctrl',    label: 'Ctrl' },
                    { token: 'alt',     label: 'Alt' },
                    { token: 'shift',   label: 'Shift' },
                    { token: 'win',     label: '',  icon: 'windows' },
                    { token: 'altgr',   label: 'Alt Gr' },
                    { token: 'fn',      label: 'Fn' }
                ]
            },
            {
                label: 'Navigatie',
                keys: [
                    { token: 'esc',      label: 'Esc' },
                    { token: 'tab',      label: 'Tab' },
                    { token: 'caps',     label: 'Caps Lock' },
                    { token: 'enter',    label: 'Enter' },
                    { token: 'space',    label: 'Space' },
                    { token: 'backspace',label: 'Backspace' },
                    { token: 'delete',   label: 'Delete' },
                    { token: 'ins',      label: 'Insert' },
                    { token: 'home',     label: 'Home' },
                    { token: 'end',      label: 'End' },
                    { token: 'pageup',   label: 'Page Up' },
                    { token: 'pagedown', label: 'Page Down' }
                ]
            },
            {
                label: 'Pijltjes',
                keys: [
                    { token: 'up',    label: '', icon: 'arrow-up' },
                    { token: 'down',  label: '', icon: 'arrow-down' },
                    { token: 'left',  label: '', icon: 'arrow-left' },
                    { token: 'right', label: '', icon: 'arrow-right' }
                ]
            },
            {
                label: 'Functietoetsen',
                keys: (function () {
                    var rows = [];
                    for (var i = 1; i <= 12; i++) { rows.push({ token: 'f' + i, label: 'F' + i }); }
                    return rows;
                }())
            },
            {
                label: 'Systeem',
                keys: [
                    { token: 'prtsc',      label: 'PrtSc' },
                    { token: 'scrolllock', label: 'Scroll Lock' },
                    { token: 'pause',      label: 'Pause' },
                    { token: 'menu',       label: 'Menu' },
                    { token: 'numlock',    label: 'Num Lock' }
                ]
            },
            {
                label: 'Media',
                keys: [
                    { token: 'volup',        label: 'Vol +' },
                    { token: 'voldown',      label: 'Vol -' },
                    { token: 'mute',         label: 'Mute' },
                    { token: 'playpause',    label: 'Play/Pause' },
                    { token: 'nexttrack',    label: 'Next' },
                    { token: 'previoustrack',label: 'Prev' }
                ]
            },
            {
                label: 'Symbolen',
                keys: [
                    { token: 'minus',       label: '-' },
                    { token: 'equals',      label: '=' },
                    { token: 'comma',       label: ',' },
                    { token: 'period',      label: '.' },
                    { token: 'slash',       label: '/' },
                    { token: 'backslash',   label: '\\' },
                    { token: 'semicolon',   label: ';' },
                    { token: 'quote',       label: "'" },
                    { token: 'backtick',    label: '`' },
                    { token: 'lbracket',    label: '[' },
                    { token: 'rbracket',    label: ']' }
                ]
            }
        ];

        var KEY_PICKER_ICONS = {
            'windows': '<svg class="key-picker-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2 3.5L11 2v9H2v-7.5zm11 7.5V2l11-1.5V11H13zM2 13h9v9L2 20.5V13zm11 0h11v10.5L13 22v-9z"/></svg>',
            'arrow-up':    '<svg class="key-picker-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 4l6 6h-4v10h-4V10H6l6-6z"/></svg>',
            'arrow-down':  '<svg class="key-picker-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 20l-6-6h4V4h4v10h4l-6 6z"/></svg>',
            'arrow-left':  '<svg class="key-picker-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4 12l6-6v4h10v4H10v4l-6-6z"/></svg>',
            'arrow-right': '<svg class="key-picker-key-icon" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 12l-6 6v-4H4v-4h10V6l6 6z"/></svg>'
        };

        var buildKeyPickerPopup = function (popup)
        {
            var html = '';
            KEY_PICKER_GROUPS.forEach(function (group)
            {
                html += '<div class="key-picker-group">'
                    + '<div class="key-picker-group-label">' + group.label + '</div>'
                    + '<div class="key-picker-group-keys">';

                group.keys.forEach(function (key)
                {
                    var inner = (KEY_PICKER_ICONS[key.icon || ''] || '') + (key.label ? key.label : '');
                    html += '<button type="button" class="key-picker-key" data-key-token="' + key.token + '">'
                        + inner + '</button>';
                });

                html += '</div></div>';
            });
            popup.innerHTML = html;
        };

        var initKeyPicker = function (wrapper)
        {
            var toggle = wrapper.querySelector('.key-picker-toggle');
            var popup  = wrapper.querySelector('.key-picker-popup');
            var textarea = wrapper.querySelector('textarea');
            if (!toggle || !popup || !textarea) { return; }

            buildKeyPickerPopup(popup);

            toggle.addEventListener('click', function (e)
            {
                e.stopPropagation();
                var isOpen = !popup.hidden;
                popup.hidden = isOpen;
                toggle.classList.toggle('is-active', !isOpen);
            });

            popup.addEventListener('click', function (e)
            {
                var keyBtn = e.target.closest('.key-picker-key');
                if (!keyBtn) { return; }

                var token = keyBtn.getAttribute('data-key-token') || '';
                if (!token) { return; }

                var insertion = '[' + token + ']';

                if (typeof textarea.setRangeText === 'function')
                {
                    var start = textarea.selectionStart;
                    var end   = textarea.selectionEnd;
                    textarea.setRangeText(insertion, start, end, 'end');
                }
                else
                {
                    textarea.value += insertion;
                }

                textarea.focus();
                popup.hidden = true;
                toggle.classList.remove('is-active');
            });
        };

        document.querySelectorAll('.textarea-wrapper').forEach(initKeyPicker);

        document.addEventListener('click', function (e)
        {
            if (!e.target.closest('.textarea-wrapper'))
            {
                document.querySelectorAll('.key-picker-popup').forEach(function (p)
                {
                    p.hidden = true;
                });
                document.querySelectorAll('.key-picker-toggle.is-active').forEach(function (t)
                {
                    t.classList.remove('is-active');
                });
            }
        });
    });
</script>