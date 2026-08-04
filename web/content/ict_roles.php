<?php

/**
 * Helpers for full vs limited ICT roles.
 */

function getFullIctAdminEmailsFromConfig(array $ictUsers): array
{
    return extractIctUserEmails($ictUsers);
}

/**
 * @return array{
 *   is_full_ict_admin: bool,
 *   is_limited_ict: bool,
 *   can_manage_tickets: bool,
 *   can_manage_ict_roles: bool,
 *   role: array{role_id: int, role_name: string, role_color: string}|null,
 *   access_categories: list<string>|null
 * }
 */
function resolveIctAccessContext(?TicketStore $store, array $ictUsers, string $userEmail, bool $isAdminPortal): array
{
    $userEmail = strtolower(trim($userEmail));
    $fullAdmins = getFullIctAdminEmailsFromConfig($ictUsers);
    $isFullIctAdmin = in_array($userEmail, $fullAdmins, true);
    $membership = ($store instanceof TicketStore && !$isFullIctAdmin)
        ? $store->getIctRoleMembership($userEmail)
        : null;
    $isLimitedIct = $membership !== null;
    $isIctCapable = $isFullIctAdmin || $isLimitedIct;
    $accessCategories = null;
    if ($isLimitedIct && $store instanceof TicketStore) {
        $accessCategories = $store->getIctAccessCategoriesForEmail($userEmail);
        if (!is_array($accessCategories)) {
            $accessCategories = [];
        }
    }

    return [
        'is_full_ict_admin' => $isFullIctAdmin,
        'is_limited_ict' => $isLimitedIct,
        'can_manage_tickets' => $isAdminPortal && $isIctCapable,
        'can_manage_ict_roles' => $isAdminPortal && $isFullIctAdmin,
        'role' => $membership,
        'access_categories' => $accessCategories,
    ];
}

/**
 * @param list<string>|null $accessCategories null = all
 * @return list<string>
 */
function filterAssignableIctEmails(TicketStore $store, string $ticketCategory, ?array $accessCategories = null): array
{
    $eligible = $store->getEmailsEligibleForCategory($ticketCategory);
    if ($accessCategories === null) {
        return $eligible;
    }

    // Viewer who can only see certain categories still assigns among people eligible for this ticket.
    return array_values($eligible);
}

/**
 * Nav label helpers for limited ICT.
 */
function formatRoleScopedNavLabel(string $roleName, string $suffixKey): string
{
    $roleName = trim($roleName);
    $suffix = (string) __($suffixKey);
    if ($roleName === '') {
        return $suffix;
    }

    return $roleName . '-' . $suffix;
}

/**
 * Recipients for ICT notifications when a ticket has no assignee:
 * everyone eligible for the ticket category (full admins + matching role members).
 *
 * @param list<string> $fallbackIctUsers
 * @return list<string>
 */
function resolveIctNotifyRecipients(TicketStore $store, string $assignedEmail, string $category, array $fallbackIctUsers = []): array
{
    $assignedEmail = strtolower(trim($assignedEmail));
    if ($assignedEmail !== '') {
        return [$assignedEmail];
    }

    $eligible = $store->getEmailsEligibleForCategory($category);
    if ($eligible !== []) {
        return $eligible;
    }

    return array_values(array_filter(array_map(
        static fn($email): string => strtolower(trim((string) $email)),
        $fallbackIctUsers
    ), static fn(string $email): bool => $email !== ''));
}

/**
 * @return list<string>|null
 */
function resolveTicketAccessCategoriesForActor(bool $isLimitedIct, ?array $ictAccessCategories): ?array
{
    if (!$isLimitedIct) {
        return null;
    }

    return is_array($ictAccessCategories) ? $ictAccessCategories : [];
}

/**
 * Resolve ICT access for an API/session actor email.
 *
 * @return array{
 *   is_full_ict_admin: bool,
 *   is_limited_ict: bool,
 *   can_manage_tickets: bool,
 *   can_manage_ict_roles: bool,
 *   role: array{role_id: int, role_name: string, role_color: string}|null,
 *   access_categories: list<string>|null
 * }
 */
function resolveIctAccessContextForEmail(?TicketStore $store, array $ictUsers, string $userEmail, bool $isAdminPortal = true): array
{
    return resolveIctAccessContext($store, $ictUsers, $userEmail, $isAdminPortal);
}

/**
 * Stats bundle for a limited ICT role (overall + role members + filtered requesters).
 *
 * @param list<string> $accessCategories
 * @return array{
 *   overall: array<string, int>,
 *   ict: list<array<string, mixed>>,
 *   requester: list<array<string, mixed>>,
 *   tickets: list<array<string, mixed>>
 * }
 */
function buildLimitedRoleStatsBundle(TicketStore $store, int $roleId, array $accessCategories): array
{
    $roleMemberEmails = $roleId > 0 ? $store->listIctRoleMemberEmails($roleId) : [];
    $accessCategories = array_values(array_filter(array_map('strval', $accessCategories), static fn(string $c): bool => $c !== ''));
    $scopedTickets = $accessCategories === []
        ? []
        : $store->getTickets(true, '', [], null, [], null, 'default', null, null, $accessCategories);

    $overallStats = [
        'total_tickets' => count($scopedTickets),
        'open_tickets' => 0,
        'resolved_tickets' => 0,
        'waiting_order_tickets' => 0,
        'waiting_user_tickets' => 0,
        'waiting_third_party_tickets' => 0,
    ];
    $statsByAssignee = [];
    foreach ($roleMemberEmails as $memberEmail) {
        $statsByAssignee[$memberEmail] = [
            'user_email' => $memberEmail,
            'handled_count' => 0,
            'open_count' => 0,
            'waiting_order_count' => 0,
            'waiting_user_count' => 0,
            'waiting_third_party_count' => 0,
            'average_open_seconds' => null,
            'max_open_seconds' => null,
        ];
    }

    $scopedRequesters = [];
    foreach ($scopedTickets as $scopedTicket) {
        $status = strtolower((string) ($scopedTicket['status'] ?? ''));
        if ($status === 'afgehandeld') {
            $overallStats['resolved_tickets']++;
        } else {
            $overallStats['open_tickets']++;
        }
        if ($status === 'afwachtende op bestelling') {
            $overallStats['waiting_order_tickets']++;
        }
        if ($status === 'afwachtende op gebruiker') {
            $overallStats['waiting_user_tickets']++;
        }
        if ($status === 'afwachtende op derde partij') {
            $overallStats['waiting_third_party_tickets']++;
        }

        $req = strtolower(trim((string) ($scopedTicket['user_email'] ?? '')));
        if ($req !== '') {
            $scopedRequesters[$req] = true;
        }

        $assignee = strtolower(trim((string) ($scopedTicket['assigned_email'] ?? '')));
        if ($assignee !== '' && isset($statsByAssignee[$assignee])) {
            if ($status === 'afgehandeld') {
                $statsByAssignee[$assignee]['handled_count']++;
            } else {
                $statsByAssignee[$assignee]['open_count']++;
            }
            if ($status === 'afwachtende op bestelling') {
                $statsByAssignee[$assignee]['waiting_order_count']++;
            }
            if ($status === 'afwachtende op gebruiker') {
                $statsByAssignee[$assignee]['waiting_user_count']++;
            }
            if ($status === 'afwachtende op derde partij') {
                $statsByAssignee[$assignee]['waiting_third_party_count']++;
            }
        }
    }

    $requesterStats = array_values(array_filter(
        $store->getRequesterStats(),
        static fn(array $row): bool => isset($scopedRequesters[strtolower((string) ($row['user_email'] ?? ''))])
    ));

    return [
        'overall' => $overallStats,
        'ict' => array_values($statsByAssignee),
        'requester' => $requesterStats,
        'tickets' => $scopedTickets,
    ];
}
