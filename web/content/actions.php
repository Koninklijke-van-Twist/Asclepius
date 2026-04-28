<?php

/**
 * Actions
 * Verwerkt download-verzoeken, POST-acties en bigscreen poll.
 * Vereist: variables.php, helpers.php, mail.php
 * Alle paden eindigen met exit of redirect.
 */

$returnPage = normalizeReturnPage((string) ($_POST['return_page'] ?? ($isAdminPortal ? 'admin.php' : 'index.php')));

if ($isAdminPortal && !$userIsAdmin) {
    pushFlash('error', __('flash.admin_only'));
    redirectToPage('index.php');
}

if (isset($_GET['download']) && $store instanceof TicketStore) {
    $attachmentId = max(0, (int) $_GET['download']);
    $attachment = $store->getAttachment($attachmentId);
    $inlinePreviewRequested = isset($_GET['preview']) && (string) $_GET['preview'] === '1';

    if ($attachment === null) {
        http_response_code(404);
        exit(__('flash.attachment_not_found'));
    }

    $storedPath = (string) ($attachment['stored_path'] ?? '');
    if (!is_file($storedPath)) {
        http_response_code(404);
        exit(__('flash.attachment_missing'));
    }

    $attachmentTicketId = max(0, (int) ($attachment['ticket_id'] ?? 0));
    if ($attachmentTicketId <= 0 || $store->getTicket($attachmentTicketId, $canManageTickets, $userEmail) === null) {
        http_response_code(404);
        exit(__('flash.attachment_not_found'));
    }

    $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($attachment['original_name'] ?? 'bijlage')) ?: 'bijlage';
    clearstatcache(true, $storedPath);
    $fileSize = filesize($storedPath);
    if ($fileSize === false) {
        http_response_code(500);
        exit(__('flash.attachment_missing'));
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $contentDispositionType = ($inlinePreviewRequested && isImageAttachment($attachment)) ? 'inline' : 'attachment';
    $fileMTime = filemtime($storedPath);
    $etag = '"' . sha1($storedPath . '|' . (string) $fileSize . '|' . (string) ($fileMTime === false ? 0 : $fileMTime)) . '"';

    if ($inlinePreviewRequested && isImageAttachment($attachment)) {
        header('Cache-Control: private, max-age=300, must-revalidate');
        if ($fileMTime !== false) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $fileMTime) . ' GMT');
        }
        header('ETag: ' . $etag);

        $ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        $ifModifiedSinceRaw = trim((string) ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
        $ifModifiedSince = $ifModifiedSinceRaw !== '' ? strtotime($ifModifiedSinceRaw) : false;
        if (($ifNoneMatch !== '' && hash_equals($etag, $ifNoneMatch)) || ($fileMTime !== false && $ifModifiedSince !== false && $ifModifiedSince >= $fileMTime)) {
            http_response_code(304);
            exit;
        }
    } else {
        header('Cache-Control: private, no-store');
    }

    header('Content-Description: File Transfer');
    header('Content-Type: ' . ((string) ($attachment['mime_type'] ?? '') !== '' ? $attachment['mime_type'] : 'application/octet-stream'));
    header('Content-Transfer-Encoding: binary');
    header('Content-Disposition: ' . $contentDispositionType . '; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string) $fileSize);
    readfile($storedPath);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['_webpush_subscription'])) {
    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        pushFlash('error', __('flash.session_expired'));
        redirectToPage($returnPage, $baseQuery);
    }

    if (!$store instanceof TicketStore) {
        pushFlash('error', __('flash.db_open_error', $storeError));
        redirectToPage($returnPage, $baseQuery);
    }

    $formAction = trim((string) ($_POST['form_action'] ?? ($_POST['action'] ?? '')));

    try {
        if ($formAction === 'create_ticket') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $category = trim((string) ($_POST['category'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $isWorkBlocked = !empty($_POST['priority_blocked']);
            $isFullyBlocked = !empty($_POST['priority_fully_blocked']);
            $priority = getPriorityFromFlags($isWorkBlocked, $isFullyBlocked);
            $requesterEmailInput = strtolower(trim((string) ($_POST['requester_email'] ?? '')));
            $requesterEmail = $userEmail;
            $files = normalizeUploadedFiles('ticket_attachments');
            $errors = validateUploadedFiles($files);

            if ($title === '') {
                $errors[] = __('flash.ticket_title_required');
            }
            if (!in_array($category, TICKET_CATEGORIES, true)) {
                $errors[] = __('flash.invalid_category');
            }
            if ($description === '') {
                $errors[] = __('flash.description_required');
            }
            if ($isFullyBlocked && !$isWorkBlocked) {
                $errors[] = __('flash.blocked_inconsistent');
            }
            if ($userIsAdmin && $requesterEmailInput !== '') {
                if (!filter_var($requesterEmailInput, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = __('flash.invalid_email');
                } else {
                    $requesterEmail = $requesterEmailInput;
                }
            }

            if ($errors !== []) {
                throw new RuntimeException(implode(' ', $errors));
            }

            $result = $store->createTicket($title, $category, $requesterEmail, $description, $files, $priority);
            $ticketId = (int) $result['ticket_id'];
            $ticket = $store->getTicket($ticketId, true, $userEmail);

            if ($ticket !== null) {
                $ictRecipients = !empty($result['assigned_email']) ? [$result['assigned_email']] : $ictUsers;
                $ictLang = !empty($result['assigned_email']) ? getUserMailLang((string) $result['assigned_email']) : 'nl';
                sendTicketNotification(
                    $store,
                    $ictUsers,
                    $ictRecipients,
                    __mail('email.subject_new_ticket', $ictLang, $ticketId),
                    buildNotificationBody($ticket, 'email.intro_new_ict', $description, true, $ictLang),
                    $requesterEmail,
                    (string) ($ticket['category'] ?? $category),
                    $ticketId,
                    $userEmail
                );

                $requesterLang = getUserMailLang($requesterEmail);
                $userIntroKey = strtolower($requesterEmail) === strtolower($userEmail)
                    ? 'email.intro_created_self'
                    : 'email.intro_created_other';

                sendTicketNotification(
                    $store,
                    $ictUsers,
                    [$requesterEmail],
                    __mail('email.subject_created', $requesterLang, $ticketId),
                    buildNotificationBody($ticket, $userIntroKey, $description, false, $requesterLang),
                    null,
                    (string) ($ticket['category'] ?? $category),
                    $ticketId,
                    $userEmail
                );
            }

            pushFlash('success', __('flash.ticket_created', $ticketId));
            redirectToPage($returnPage, array_merge($baseQuery, ['open' => $ticketId]));
        }

        if ($formAction === 'reply_ticket') {
            $ticketId = max(1, (int) ($_POST['ticket_id'] ?? 0));
            $ticket = $store->getTicket($ticketId, $canManageTickets, $userEmail);
            if ($ticket === null) {
                throw new RuntimeException(__('flash.ticket_not_found'));
            }

            $message = trim((string) ($_POST['message'] ?? ''));
            $messageForStorage = $message;
            $files = normalizeUploadedFiles('reply_attachments');
            $errors = validateUploadedFiles($files);

            $newStatus = (string) $ticket['status'];
            $newAssignee = (string) ($ticket['assigned_email'] ?? '');
            $newPriority = max(0, min(2, (int) ($ticket['priority'] ?? 0)));
            $statusChanged = false;
            $assigneeChanged = false;
            $priorityChanged = false;
            $reopenRequested = !empty($_POST['reopen_ticket']);

            if ($canManageTickets) {
                $requestedStatus = trim((string) ($_POST['status'] ?? $ticket['status']));
                if (!in_array($requestedStatus, TICKET_STATUSES, true)) {
                    $errors[] = __('flash.invalid_status');
                } else {
                    $newStatus = $requestedStatus;
                    $statusChanged = $newStatus !== (string) $ticket['status'];
                }

                $requestedAssignee = strtolower(trim((string) ($_POST['assigned_email'] ?? (string) ($ticket['assigned_email'] ?? ''))));
                $currentAssignee = strtolower((string) ($ticket['assigned_email'] ?? ''));
                $requesterEmail = strtolower(trim((string) ($ticket['user_email'] ?? '')));
                $availabilityByUser = $store->getIctUserAvailability();
                if ($requestedAssignee !== '' && !in_array($requestedAssignee, array_map('strtolower', $ictUsers), true)) {
                    $errors[] = __('flash.invalid_employee');
                } elseif ($requestedAssignee !== '' && $requestedAssignee === $requesterEmail) {
                    $errors[] = __('flash.self_assignment_not_allowed');
                } elseif ($requestedAssignee !== '' && empty($availabilityByUser[$requestedAssignee]) && $requestedAssignee !== $currentAssignee) {
                    $errors[] = __('flash.employee_away');
                } else {
                    $newAssignee = $requestedAssignee;
                    $assigneeChanged = $newAssignee !== $currentAssignee;
                }

                $requestedPriority = (int) ($_POST['priority'] ?? $newPriority);
                if ($requestedPriority < 0 || $requestedPriority > 2) {
                    $errors[] = __('flash.invalid_priority');
                } else {
                    $newPriority = $requestedPriority;
                    $priorityChanged = $newPriority !== (int) ($ticket['priority'] ?? 0);
                }
            }

            if (!$canManageTickets && $message !== '' && (string) $ticket['status'] === 'afwachtende op gebruiker') {
                $newStatus = 'in behandeling';
                $statusChanged = true;
            }

            if (!$canManageTickets && $reopenRequested && (string) $ticket['status'] === 'afgehandeld') {
                $newStatus = 'ingediend';
                $statusChanged = true;
            }

            if ($message === '' && $files === [] && !$statusChanged && !$assigneeChanged && !$priorityChanged) {
                $errors[] = __('flash.reply_empty');
            }

            if ($canManageTickets && $statusChanged) {
                $statusChangeNote = buildStatusChangeNote($newStatus, $userEmail);
                $messageForStorage = $message !== ''
                    ? rtrim($message) . PHP_EOL . PHP_EOL . $statusChangeNote
                    : $statusChangeNote;
            }

            if ($errors !== []) {
                throw new RuntimeException(implode(' ', $errors));
            }

            if ($statusChanged || ($canManageTickets && ($assigneeChanged || $priorityChanged))) {
                $store->updateTicket($ticketId, $newStatus, $newAssignee !== '' ? $newAssignee : null, $newPriority);
            }

            if ($messageForStorage !== '' || $files !== []) {
                $store->addMessage($ticketId, $userEmail, $canManageTickets ? 'admin' : 'user', $messageForStorage, $files);
            }

            $updatedTicket = $store->getTicket($ticketId, true, $userEmail);
            if ($updatedTicket !== null) {
                if ($canManageTickets) {
                    $shouldNotifyRequester = $statusChanged || $assigneeChanged || $messageForStorage !== '' || $files !== [];
                    if ($shouldNotifyRequester) {
                        $reqLang = getUserMailLang((string) $updatedTicket['user_email']);
                        $updateIntroSuffix = $statusChanged ? __mail('email.intro_update_status', $reqLang) : __mail('email.intro_update_no_status', $reqLang);
                        sendTicketNotification(
                            $store,
                            $ictUsers,
                            [$updatedTicket['user_email']],
                            __mail('email.subject_update', $reqLang, $ticketId),
                            buildNotificationBody($updatedTicket, 'email.intro_update', $messageForStorage, false, $reqLang, $updateIntroSuffix),
                            $userEmail,
                            (string) ($updatedTicket['category'] ?? ''),
                            $ticketId,
                            $userEmail
                        );
                    }

                    if ($assigneeChanged && $newAssignee !== '') {
                        $assigneeLang = getUserMailLang($newAssignee);
                        sendTicketNotification(
                            $store,
                            $ictUsers,
                            [$newAssignee],
                            __mail('email.subject_assigned', $assigneeLang, $ticketId),
                            buildNotificationBody($updatedTicket, 'email.intro_assigned', $message, true, $assigneeLang),
                            $userEmail,
                            (string) ($updatedTicket['category'] ?? ''),
                            $ticketId,
                            $userEmail
                        );
                    }
                } else {
                    $recipients = !empty($updatedTicket['assigned_email']) ? [$updatedTicket['assigned_email']] : $ictUsers;
                    $ictLang2 = !empty($updatedTicket['assigned_email']) ? getUserMailLang((string) $updatedTicket['assigned_email']) : 'nl';
                    sendTicketNotification(
                        $store,
                        $ictUsers,
                        $recipients,
                        __mail('email.subject_user_reply', $ictLang2, $ticketId),
                        buildNotificationBody($updatedTicket, 'email.intro_user_reply', $message, true, $ictLang2),
                        $userEmail,
                        (string) ($updatedTicket['category'] ?? ''),
                        $ticketId,
                        $userEmail
                    );

                    if ($message !== '' && ticketIsOpenLongerThanDays($updatedTicket, $longOpenNotificationDays)) {
                        $escalationRecipients = $recipients;
                        $escalationRecipients[] = 'ict@kvt.nl';
                        sendTicketNotification(
                            $store,
                            $ictUsers,
                            array_values(array_unique($escalationRecipients)),
                            __mail('email.subject_escalation', $ictLang2, $ticketId),
                            buildNotificationBody($updatedTicket, 'email.intro_escalation', $message, true, $ictLang2),
                            null,
                            (string) ($updatedTicket['category'] ?? ''),
                            $ticketId,
                            $userEmail
                        );
                    }
                }
            }

            pushFlash('success', __('flash.ticket_updated', $ticketId));
            redirectToPage($returnPage, array_merge($baseQuery, ['open' => $ticketId]));
        }

        if ($formAction === 'save_settings') {
            if (!$canManageTickets) {
                throw new RuntimeException(__('flash.settings_admin_only'));
            }

            $postedSettings = is_array($_POST['settings'] ?? null) ? $_POST['settings'] : [];
            $postedAvailability = is_array($_POST['availability'] ?? null) ? $_POST['availability'] : [];
            $postedEnabledPairs = array_filter((array) ($_POST['settings_enabled'] ?? []), static fn($value): bool => is_string($value) && $value !== '');
            $enabledLookup = [];

            foreach ($postedEnabledPairs as $postedPair) {
                $decodedPair = base64_decode((string) $postedPair, true);
                if ($decodedPair === false) {
                    continue;
                }

                $parts = explode('|', $decodedPair, 2);
                if (count($parts) !== 2) {
                    continue;
                }

                [$postedUserEmail, $postedCategory] = $parts;
                $enabledLookup[strtolower(trim($postedUserEmail))][trim($postedCategory)] = true;
            }

            $matrix = [];
            $availability = [];
            foreach ($ictUsers as $ictUser) {
                $ictUser = strtolower($ictUser);
                $availability[$ictUser] = !empty($postedAvailability[$ictUser]);
                foreach (TICKET_CATEGORIES as $category) {
                    $matrix[$ictUser][$category] = !empty($postedSettings[$ictUser][$category]) || !empty($enabledLookup[$ictUser][$category]);
                }
            }

            $store->saveCategoryMatrix($matrix, $availability);
            pushFlash('success', __('flash.settings_saved'));
            redirectToPage('admin.php', ['view' => 'settings']);
        }

        throw new RuntimeException(__('flash.unknown_action'));
    } catch (Throwable $exception) {
        if ($formAction === 'save_settings') {
            error_log('[Asclepius save_settings] ' . $exception->getMessage() . ' | db=' . DATABASE_FILE . ' | dir_writable=' . (is_writable(dirname(DATABASE_FILE)) ? '1' : '0') . ' | file_writable=' . ((is_file(DATABASE_FILE) && is_writable(DATABASE_FILE)) ? '1' : '0'));
        }

        pushFlash('error', $exception->getMessage());
        redirectToPage($returnPage, array_merge($baseQuery, $formAction === 'reply_ticket' ? ['open' => max(1, (int) ($_POST['ticket_id'] ?? 0))] : []));
    }
}
