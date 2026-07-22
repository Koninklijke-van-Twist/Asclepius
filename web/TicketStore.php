<?php

class TicketStore
{
    private const REQUESTER_WAIT_STATUSES = [
        'ingediend',
        'in behandeling',
        'afwachtende op bestelling',
        'afwachtende op derde partij',
    ];

    private const REQUESTER_RESPONSE_STATUS = 'afwachtende op gebruiker';

    private const TICKET_LIST_COLUMNS = 't.id, t.title, t.category, t.user_email, t.assigned_email, t.status, t.priority, t.created_at, t.updated_at, t.due_date, t.resolved_at, t.is_private';

    private PDO $pdo;
    private string $databasePath;
    private string $uploadRoot;
    private array $ictUsers;
    private array $categories;

    public function __construct(string $databasePath, string $uploadRoot, array $ictUsers, array $categories)
    {
        $this->databasePath = $databasePath;
        $this->uploadRoot = $uploadRoot;

        // Normalize $ictUsers: if it's an associative array (email => color),
        // extract keys; if it's already flat, use values.
        $isAssociative = array_keys($ictUsers) !== range(0, count($ictUsers) - 1);
        $normalizedIctUsers = $isAssociative ? array_keys($ictUsers) : array_values($ictUsers);
        $this->ictUsers = array_values(array_unique(array_map('strtolower', $normalizedIctUsers)));

        $this->categories = array_values($categories);

        $this->connect();
        $this->initialize();
    }

    public function getCategorySettings(): array
    {
        $statement = $this->pdo->query('SELECT user_email, category, is_enabled FROM ict_user_category_settings ORDER BY user_email, category');
        $settings = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $settings[$row['user_email']][$row['category']] = (bool) $row['is_enabled'];
        }

        foreach ($this->ictUsers as $ictUser) {
            foreach ($this->categories as $category) {
                $settings[$ictUser][$category] = $settings[$ictUser][$category] ?? true;
            }
        }

        return $settings;
    }

    public function getIctUserAvailability(): array
    {
        $statement = $this->pdo->query('SELECT user_email, is_available FROM ict_user_availability ORDER BY user_email');
        $availability = array_fill_keys($this->ictUsers, true);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $availability[$row['user_email']] = (bool) $row['is_available'];
        }

        return $availability;
    }

    public function getTicketTemplates(): array
    {
        $statement = $this->pdo->query(
            'SELECT id, name, body, created_by_email, updated_by_email, created_at, updated_at, sort_order
             FROM ticket_templates
             ORDER BY sort_order ASC, lower(name) ASC, id ASC'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTicketTemplateById(int $templateId): ?array
    {
        if ($templateId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT id, name, body, created_by_email, updated_by_email, created_at, updated_at
             FROM ticket_templates
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $templateId]);

        $template = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($template) ? $template : null;
    }

    public function createTicketTemplate(string $name, string $body, string $authorEmail): int
    {
        $now = date('c');
        $authorEmail = strtolower(trim($authorEmail));
        $nextOrder = (int) $this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ticket_templates')->fetchColumn();
        $statement = $this->pdo->prepare(
            'INSERT INTO ticket_templates (name, body, created_by_email, updated_by_email, created_at, updated_at, sort_order)
             VALUES (:name, :body, :created_by_email, :updated_by_email, :created_at, :updated_at, :sort_order)'
        );
        $statement->execute([
            ':name' => trim($name),
            ':body' => trim($body),
            ':created_by_email' => $authorEmail,
            ':updated_by_email' => $authorEmail,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':sort_order' => $nextOrder,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateTicketTemplate(int $templateId, string $name, string $body, string $authorEmail): bool
    {
        if ($templateId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE ticket_templates
             SET name = :name,
                 body = :body,
                 updated_by_email = :updated_by_email,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            ':id' => $templateId,
            ':name' => trim($name),
            ':body' => trim($body),
            ':updated_by_email' => strtolower(trim($authorEmail)),
            ':updated_at' => date('c'),
        ]);

        return $statement->rowCount() > 0;
    }

    public function deleteTicketTemplate(int $templateId): bool
    {
        if ($templateId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare('DELETE FROM ticket_templates WHERE id = :id');
        $statement->execute([':id' => $templateId]);

        return $statement->rowCount() > 0;
    }

    public function reorderTicketTemplates(array $orderedIds): bool
    {
        $validIds = array_values(array_filter(array_map('intval', $orderedIds), static fn(int $id): bool => $id > 0));
        if ($validIds === []) {
            return false;
        }

        $statement = $this->pdo->prepare('UPDATE ticket_templates SET sort_order = :sort_order WHERE id = :id');
        foreach ($validIds as $position => $id) {
            $statement->execute([':sort_order' => $position + 1, ':id' => $id]);
        }

        return true;
    }

    public function saveCategoryMatrix(array $matrix, array $availability = []): void
    {
        $insertStatement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO ict_user_category_settings (user_email, category, is_enabled)
             VALUES (:user_email, :category, :is_enabled)'
        );
        $updateStatement = $this->pdo->prepare(
            'UPDATE ict_user_category_settings
             SET is_enabled = :is_enabled
             WHERE user_email = :user_email
               AND category = :category'
        );
        $availabilityInsertStatement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO ict_user_availability (user_email, is_available)
             VALUES (:user_email, :is_available)'
        );
        $availabilityUpdateStatement = $this->pdo->prepare(
            'UPDATE ict_user_availability
             SET is_available = :is_available
             WHERE user_email = :user_email'
        );

        $this->pdo->beginTransaction();

        try {
            foreach ($this->ictUsers as $ictUser) {
                $availabilityParameters = [
                    ':user_email' => $ictUser,
                    ':is_available' => !array_key_exists($ictUser, $availability) || !empty($availability[$ictUser]) ? 1 : 0,
                ];

                $availabilityInsertStatement->execute($availabilityParameters);
                $availabilityUpdateStatement->execute($availabilityParameters);

                foreach ($this->categories as $category) {
                    $parameters = [
                        ':user_email' => $ictUser,
                        ':category' => $category,
                        ':is_enabled' => !empty($matrix[$ictUser][$category]) ? 1 : 0,
                    ];

                    $insertStatement->execute($parameters);
                    $updateStatement->execute($parameters);
                }
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function getIctUserLoads(): array
    {
        if ($this->ictUsers === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($this->ictUsers), '?'));
        $statement = $this->pdo->prepare(
            "SELECT assigned_email, COUNT(*) AS open_count
             FROM tickets
             WHERE status <> 'afgehandeld'
               AND assigned_email IN ($placeholders)
             GROUP BY assigned_email"
        );
        $statement->execute($this->ictUsers);

        $loads = array_fill_keys($this->ictUsers, 0);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $loads[$row['assigned_email']] = (int) $row['open_count'];
        }

        return $loads;
    }

    public function getOverallStats(): array
    {
        $statement = $this->pdo->query(
            "SELECT COUNT(*) AS total_tickets,
                    SUM(CASE WHEN status <> 'afgehandeld' THEN 1 ELSE 0 END) AS open_tickets,
                    SUM(CASE WHEN status = 'afgehandeld' THEN 1 ELSE 0 END) AS resolved_tickets,
                    SUM(CASE WHEN status = 'afwachtende op bestelling' THEN 1 ELSE 0 END) AS waiting_order_tickets,
                    SUM(CASE WHEN status = 'afwachtende op gebruiker' THEN 1 ELSE 0 END) AS waiting_user_tickets,
                    SUM(CASE WHEN status = 'afwachtende op derde partij' THEN 1 ELSE 0 END) AS waiting_third_party_tickets
             FROM tickets"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_tickets' => (int) ($row['total_tickets'] ?? 0),
            'open_tickets' => (int) ($row['open_tickets'] ?? 0),
            'resolved_tickets' => (int) ($row['resolved_tickets'] ?? 0),
            'waiting_order_tickets' => (int) ($row['waiting_order_tickets'] ?? 0),
            'waiting_user_tickets' => (int) ($row['waiting_user_tickets'] ?? 0),
            'waiting_third_party_tickets' => (int) ($row['waiting_third_party_tickets'] ?? 0),
        ];
    }

    public function getIctUserStats(): array
    {
        if ($this->ictUsers === []) {
            return [];
        }

        $statsByUser = [];
        foreach ($this->ictUsers as $ictUser) {
            $statsByUser[$ictUser] = [
                'user_email' => $ictUser,
                'handled_count' => 0,
                'open_count' => 0,
                'waiting_order_count' => 0,
                'waiting_user_count' => 0,
                'waiting_third_party_count' => 0,
                'average_open_seconds' => null,
                'max_open_seconds' => null,
            ];
        }

        $placeholders = implode(', ', array_fill(0, count($this->ictUsers), '?'));
        $statement = $this->pdo->prepare(
            "SELECT lower(assigned_email) AS user_email,
                    SUM(CASE WHEN status = 'afgehandeld' THEN 1 ELSE 0 END) AS handled_count,
                    SUM(CASE WHEN status <> 'afgehandeld' THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN status = 'afwachtende op bestelling' THEN 1 ELSE 0 END) AS waiting_order_count,
                    SUM(CASE WHEN status = 'afwachtende op gebruiker' THEN 1 ELSE 0 END) AS waiting_user_count,
                    SUM(CASE WHEN status = 'afwachtende op derde partij' THEN 1 ELSE 0 END) AS waiting_third_party_count,
                    AVG(
                        (julianday(
                            CASE
                                WHEN status = 'afgehandeld' THEN COALESCE(NULLIF(resolved_at, ''), NULLIF(updated_at, ''), CURRENT_TIMESTAMP)
                                ELSE CURRENT_TIMESTAMP
                            END
                        ) - julianday(created_at)) * 86400
                    ) AS average_open_seconds,
                    MAX(
                        CAST(
                            ROUND(
                                (julianday(
                                    CASE
                                        WHEN status = 'afgehandeld' THEN COALESCE(NULLIF(resolved_at, ''), NULLIF(updated_at, ''), CURRENT_TIMESTAMP)
                                        ELSE CURRENT_TIMESTAMP
                                    END
                                ) - julianday(created_at)) * 86400
                            ) AS INTEGER
                        )
                    ) AS max_open_seconds
             FROM tickets
             WHERE lower(COALESCE(assigned_email, '')) IN ($placeholders)
             GROUP BY lower(assigned_email)
             ORDER BY open_count DESC, handled_count DESC, user_email ASC"
        );
        $statement->execute($this->ictUsers);

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ictUser = strtolower((string) ($row['user_email'] ?? ''));
            if (!isset($statsByUser[$ictUser])) {
                continue;
            }

            $statsByUser[$ictUser]['handled_count'] = (int) ($row['handled_count'] ?? 0);
            $statsByUser[$ictUser]['open_count'] = (int) ($row['open_count'] ?? 0);
            $statsByUser[$ictUser]['waiting_order_count'] = (int) ($row['waiting_order_count'] ?? 0);
            $statsByUser[$ictUser]['waiting_user_count'] = (int) ($row['waiting_user_count'] ?? 0);
            $statsByUser[$ictUser]['waiting_third_party_count'] = (int) ($row['waiting_third_party_count'] ?? 0);
            $statsByUser[$ictUser]['average_open_seconds'] = isset($row['average_open_seconds']) && $row['average_open_seconds'] !== null
                ? max(0, (float) $row['average_open_seconds'])
                : null;
            $statsByUser[$ictUser]['max_open_seconds'] = isset($row['max_open_seconds']) && $row['max_open_seconds'] !== null
                ? max(0, (int) $row['max_open_seconds'])
                : null;
        }

        return array_values($statsByUser);
    }

    public function getRequesterStats(): array
    {
        $conditions = [];
        $parameters = [];

        if ($this->ictUsers !== []) {
            $placeholders = implode(', ', array_fill(0, count($this->ictUsers), '?'));
            $conditions[] = 'lower(t.user_email) NOT IN (' . $placeholders . ')';
            $parameters = $this->ictUsers;
        }

        $waitStatusPlaceholders = implode(', ', array_fill(0, count(self::REQUESTER_WAIT_STATUSES), '?'));
        $durationParameters = self::REQUESTER_WAIT_STATUSES;
        $durationParameters[] = self::REQUESTER_RESPONSE_STATUS;

        $sql = "SELECT lower(t.user_email) AS user_email,
                       COUNT(*) AS submitted_count,
                       AVG(COALESCE(d.wait_seconds, 0)) AS average_wait_seconds,
                       MAX(CAST(ROUND(COALESCE(d.wait_seconds, 0)) AS INTEGER)) AS max_wait_seconds,
                       AVG(COALESCE(d.response_seconds, 0)) AS average_response_seconds
                FROM tickets t
                LEFT JOIN (
                    SELECT ticket_id,
                           SUM(
                               CASE
                                   WHEN status IN ($waitStatusPlaceholders)
                                   THEN MAX(0, (julianday(COALESCE(NULLIF(ended_at, ''), CURRENT_TIMESTAMP)) - julianday(started_at)) * 86400)
                                   ELSE 0
                               END
                           ) AS wait_seconds,
                           SUM(
                               CASE
                                   WHEN status = ?
                                   THEN MAX(0, (julianday(COALESCE(NULLIF(ended_at, ''), CURRENT_TIMESTAMP)) - julianday(started_at)) * 86400)
                                   ELSE 0
                               END
                           ) AS response_seconds
                    FROM ticket_status_transitions
                    GROUP BY ticket_id
                ) d ON d.ticket_id = t.id";
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' GROUP BY lower(t.user_email) ORDER BY submitted_count DESC, user_email ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_merge($durationParameters, $parameters));

        $stats = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[] = [
                'user_email' => strtolower((string) ($row['user_email'] ?? '')),
                'submitted_count' => (int) ($row['submitted_count'] ?? 0),
                'average_wait_seconds' => isset($row['average_wait_seconds']) && $row['average_wait_seconds'] !== null
                    ? max(0, (float) $row['average_wait_seconds'])
                    : null,
                'max_wait_seconds' => isset($row['max_wait_seconds']) && $row['max_wait_seconds'] !== null
                    ? max(0, (int) $row['max_wait_seconds'])
                    : null,
                'average_response_seconds' => isset($row['average_response_seconds']) && $row['average_response_seconds'] !== null
                    ? max(0, (float) $row['average_response_seconds'])
                    : null,
            ];
        }

        return $stats;
    }

    public function pickAvailableIctUser(?string $category = null, ?string $excludeEmail = null): ?string
    {
        return $this->pickAssignee($category, $excludeEmail);
    }

    public function ensureTicketAssigned(int $ticketId): ?string
    {
        if ($ticketId <= 0) {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT category, assigned_email, status
             FROM tickets
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $ticketId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $assignedEmail = strtolower(trim((string) ($row['assigned_email'] ?? '')));
        if ($assignedEmail !== '') {
            return $assignedEmail;
        }

        if (strtolower((string) ($row['status'] ?? '')) === 'afgehandeld') {
            return null;
        }

        $assignee = $this->pickAssignee((string) ($row['category'] ?? ''));
        if ($assignee === null || trim($assignee) === '') {
            return null;
        }

        $assignee = strtolower(trim($assignee));
        $updatedAt = date('c');
        $updateStatement = $this->pdo->prepare(
            'UPDATE tickets
             SET assigned_email = :assigned_email,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $updateStatement->execute([
            ':assigned_email' => $assignee,
            ':updated_at' => $updatedAt,
            ':id' => $ticketId,
        ]);

        return $assignee;
    }

    public function createTicket(
        string $title,
        string $category,
        string $userEmail,
        string $description,
        array $files = [],
        int $priority = 0,
        array $additionalParticipants = [],
        ?string $dueDate = null,
        ?string $forcedAssignee = null
    ): array {
        $userEmail = strtolower(trim($userEmail));
        $priority = max(0, min(2, $priority));
        $normalizedForcedAssignee = strtolower(trim((string) $forcedAssignee));
        $assignee = $normalizedForcedAssignee !== '' ? $normalizedForcedAssignee : $this->pickAssignee($category);
        $now = date('c');
        $normalizedDueDate = $this->normalizeDueDate($dueDate);

        $this->pdo->beginTransaction();

        try {
            $ticketStatement = $this->pdo->prepare(
                'INSERT INTO tickets (title, category, user_email, assigned_email, status, description, priority, due_date, created_at, updated_at)
                 VALUES (:title, :category, :user_email, :assigned_email, :status, :description, :priority, :due_date, :created_at, :updated_at)'
            );
            $ticketStatement->execute([
                ':title' => $title,
                ':category' => $category,
                ':user_email' => $userEmail,
                ':assigned_email' => $assignee,
                ':status' => 'ingediend',
                ':description' => $description,
                ':priority' => $priority,
                ':due_date' => $normalizedDueDate,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $ticketId = (int) $this->pdo->lastInsertId();

            $participantStatement = $this->pdo->prepare(
                'INSERT OR IGNORE INTO ticket_participants (ticket_id, user_email, added_by_email, created_at)
                 VALUES (:ticket_id, :user_email, :added_by_email, :created_at)'
            );

            $allParticipants = $this->normalizeParticipantEmails($additionalParticipants, $userEmail);
            foreach ($allParticipants as $participantEmail) {
                $participantStatement->execute([
                    ':ticket_id' => $ticketId,
                    ':user_email' => $participantEmail,
                    ':added_by_email' => $userEmail,
                    ':created_at' => $now,
                ]);
            }

            $this->insertStatusTransition($ticketId, 'ingediend', $now, null);

            $messageStatement = $this->pdo->prepare(
                'INSERT INTO ticket_messages (ticket_id, sender_email, sender_role, message_text, created_at)
                 VALUES (:ticket_id, :sender_email, :sender_role, :message_text, :created_at)'
            );
            $messageStatement->execute([
                ':ticket_id' => $ticketId,
                ':sender_email' => $userEmail,
                ':sender_role' => 'user',
                ':message_text' => $description,
                ':created_at' => $now,
            ]);

            $messageId = (int) $this->pdo->lastInsertId();
            $this->storeAttachments($ticketId, $messageId, $files, $userEmail);

            $this->pdo->commit();

            if ($assignee === null || trim((string) $assignee) === '') {
                $assignee = $this->ensureTicketAssigned($ticketId);
            }

            return [
                'ticket_id' => $ticketId,
                'assigned_email' => $assignee,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function changeTicketCategory(int $ticketId, string $category, bool $reassign = false): array
    {
        $category = trim($category);
        if ($ticketId <= 0) {
            throw new RuntimeException('Ticket niet gevonden.');
        }
        if (!in_array($category, $this->categories, true)) {
            throw new RuntimeException('Ongeldige categorie.');
        }

        $statement = $this->pdo->prepare(
            'SELECT category, assigned_email
             FROM tickets
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $ticketId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Ticket niet gevonden.');
        }

        $oldCategory = (string) ($row['category'] ?? '');
        $currentAssignee = strtolower(trim((string) ($row['assigned_email'] ?? '')));
        $newAssignee = $currentAssignee !== '' ? $currentAssignee : null;
        $assigneeChanged = false;

        if ($reassign) {
            $pickedAssignee = $this->pickAssignee($category);
            $pickedAssignee = $pickedAssignee !== null ? strtolower(trim($pickedAssignee)) : null;
            $newAssignee = $pickedAssignee;
            $assigneeChanged = $pickedAssignee !== $currentAssignee;
        }

        if ($oldCategory === $category && !$assigneeChanged) {
            return [
                'changed' => false,
                'old_category' => $oldCategory,
                'new_category' => $category,
                'assigned_email' => $currentAssignee,
                'assignee_changed' => false,
            ];
        }

        $updatedAt = date('c');
        $updateStatement = $this->pdo->prepare(
            'UPDATE tickets
             SET category = :category,
                 assigned_email = :assigned_email,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $updateStatement->execute([
            ':category' => $category,
            ':assigned_email' => $newAssignee,
            ':updated_at' => $updatedAt,
            ':id' => $ticketId,
        ]);

        return [
            'changed' => true,
            'old_category' => $oldCategory,
            'new_category' => $category,
            'assigned_email' => (string) ($newAssignee ?? ''),
            'assignee_changed' => $assigneeChanged,
        ];
    }

    public function updateTicketTitle(int $ticketId, string $title): bool
    {
        $normalizedTitle = trim($title);
        if ($ticketId <= 0 || $normalizedTitle === '') {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE tickets
             SET title = :title,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            ':title' => $normalizedTitle,
            ':updated_at' => date('c'),
            ':id' => $ticketId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function updateTicketPrivate(int $ticketId, bool $isPrivate): bool
    {
        if ($ticketId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE tickets
             SET is_private = :is_private,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            ':is_private' => $isPrivate ? 1 : 0,
            ':updated_at' => date('c'),
            ':id' => $ticketId,
        ]);

        return $statement->rowCount() > 0;
    }

    public function deleteTextTranslationsForEntity(string $entityType, int $entityId): void
    {
        $normalizedEntityType = trim($entityType);
        if ($normalizedEntityType === '' || $entityId <= 0) {
            return;
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM ticket_text_translations
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id'
        );
        $statement->execute([
            ':entity_type' => $normalizedEntityType,
            ':entity_id' => $entityId,
        ]);
    }

    /**
     * Custom statuses currently used on at least one ticket (excluding built-ins).
     *
     * @return list<array{display_label: string, created_by_email: string, created_at: string}>
     */
    public function getActiveCustomStatuses(): array
    {
        $builtInKeys = array_map(static fn(string $status): string => self::unicodeLower($status), TICKET_STATUSES);
        $placeholders = [];
        $parameters = [];
        foreach ($builtInKeys as $index => $key) {
            $placeholder = ':builtin_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $key;
        }

        $sql = 'SELECT MIN(t.status) AS ticket_status,
                       MAX(cts.display_label) AS registry_label,
                       MAX(cts.created_by_email) AS created_by_email,
                       MAX(cts.created_at) AS created_at
                FROM tickets t
                LEFT JOIN custom_ticket_statuses cts
                    ON cts.status_key = mb_lower(t.status)
                WHERE mb_lower(t.status) NOT IN (' . implode(', ', $placeholders) . ')
                GROUP BY mb_lower(t.status)
                ORDER BY COALESCE(MAX(cts.display_label), MIN(t.status)) COLLATE NOCASE ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        $rows = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $registryLabel = trim((string) ($row['registry_label'] ?? ''));
            $ticketStatus = trim((string) ($row['ticket_status'] ?? ''));
            $displayLabel = $registryLabel !== '' ? $registryLabel : $ticketStatus;
            if ($displayLabel === '') {
                continue;
            }

            $rows[] = [
                'display_label' => $displayLabel,
                'created_by_email' => strtolower(trim((string) ($row['created_by_email'] ?? ''))),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    public function getActiveCustomStatusLabels(): array
    {
        return array_values(array_map(
            static fn(array $row): string => (string) ($row['display_label'] ?? ''),
            $this->getActiveCustomStatuses()
        ));
    }

    /**
     * Resolve a sanitized custom label to a canonical display value and register it if new.
     */
    public function resolveAndRegisterCustomStatus(string $sanitizedLabel, string $createdByEmail): string
    {
        $sanitizedLabel = trim($sanitizedLabel);
        if ($sanitizedLabel === '') {
            return $sanitizedLabel;
        }

        $statusKey = self::unicodeLower($sanitizedLabel);
        $createdByEmail = strtolower(trim($createdByEmail));

        $existing = $this->pdo->prepare(
            'SELECT display_label
             FROM custom_ticket_statuses
             WHERE status_key = :status_key
             LIMIT 1'
        );
        $existing->execute([':status_key' => $statusKey]);
        $registryLabel = trim((string) ($existing->fetchColumn() ?: ''));
        if ($registryLabel !== '') {
            return $registryLabel;
        }

        $fromTicket = $this->pdo->prepare(
            'SELECT status
             FROM tickets
             WHERE mb_lower(status) = :status_key
             LIMIT 1'
        );
        $fromTicket->execute([':status_key' => $statusKey]);
        $ticketLabel = trim((string) ($fromTicket->fetchColumn() ?: ''));
        $displayLabel = $ticketLabel !== '' ? $ticketLabel : $sanitizedLabel;

        $insert = $this->pdo->prepare(
            'INSERT OR IGNORE INTO custom_ticket_statuses (status_key, display_label, created_by_email, created_at)
             VALUES (:status_key, :display_label, :created_by_email, :created_at)'
        );
        $insert->execute([
            ':status_key' => $statusKey,
            ':display_label' => $displayLabel,
            ':created_by_email' => $createdByEmail,
            ':created_at' => date('c'),
        ]);

        $existing->execute([':status_key' => $statusKey]);
        $finalLabel = trim((string) ($existing->fetchColumn() ?: ''));

        return $finalLabel !== '' ? $finalLabel : $displayLabel;
    }

    public function updateTicket(int $ticketId, string $status, ?string $assignedEmail, int $priority = 0, ?string $dueDate = null): void
    {
        $currentTicket = $this->pdo->prepare('SELECT status FROM tickets WHERE id = :id LIMIT 1');
        $currentTicket->execute([':id' => $ticketId]);
        $currentStatus = strtolower((string) ($currentTicket->fetchColumn() ?: ''));
        $statusChanged = $currentStatus !== strtolower($status);

        $updatedAt = date('c');
        $priority = max(0, min(2, $priority));
        $normalizedDueDate = $this->normalizeDueDate($dueDate);

        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                "UPDATE tickets
                 SET status = :status,
                     assigned_email = :assigned_email,
                     priority = :priority,
                     due_date = :due_date,
                     updated_at = :updated_at,
                     resolved_at = CASE
                         WHEN :status = 'afgehandeld' THEN COALESCE(NULLIF(resolved_at, ''), :updated_at)
                         ELSE NULL
                     END
                 WHERE id = :id"
            );
            $statement->execute([
                ':status' => $status,
                ':assigned_email' => $assignedEmail,
                ':priority' => $priority,
                ':due_date' => $normalizedDueDate,
                ':updated_at' => $updatedAt,
                ':id' => $ticketId,
            ]);

            if ($statusChanged) {
                $closeTransition = $this->pdo->prepare(
                    'UPDATE ticket_status_transitions
                     SET ended_at = :ended_at
                     WHERE ticket_id = :ticket_id
                       AND (ended_at IS NULL OR ended_at = "")'
                );
                $closeTransition->execute([
                    ':ended_at' => $updatedAt,
                    ':ticket_id' => $ticketId,
                ]);

                $newEndedAt = strtolower($status) === 'afgehandeld' ? $updatedAt : null;
                $this->insertStatusTransition($ticketId, $status, $updatedAt, $newEndedAt);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function addMessage(int $ticketId, string $senderEmail, string $senderRole, string $messageText, array $files = [], bool $isGhost = false): int
    {
        $now = date('c');
        $statement = $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_email, sender_role, message_text, created_at, is_ghost)
             VALUES (:ticket_id, :sender_email, :sender_role, :message_text, :created_at, :is_ghost)'
        );
        $statement->execute([
            ':ticket_id' => $ticketId,
            ':sender_email' => strtolower(trim($senderEmail)),
            ':sender_role' => $senderRole,
            ':message_text' => $messageText,
            ':created_at' => $now,
            ':is_ghost' => $isGhost ? 1 : 0,
        ]);

        $messageId = (int) $this->pdo->lastInsertId();
        $this->storeAttachments($ticketId, $messageId, $files, $senderEmail);

        $updateStatement = $this->pdo->prepare('UPDATE tickets SET updated_at = :updated_at WHERE id = :id');
        $updateStatement->execute([
            ':updated_at' => $now,
            ':id' => $ticketId,
        ]);

        return $messageId;
    }

    public function updateTicketMessageCheckboxState(int $ticketId, int $messageId, int $lineIndex, bool $checked, bool $isAdmin, string $userEmail): ?string
    {
        if ($ticketId <= 0 || $messageId <= 0 || $lineIndex < 0) {
            return null;
        }

        $ticket = $this->getTicket($ticketId, $isAdmin, $userEmail);
        if ($ticket === null) {
            return null;
        }

        $messageStatement = $this->pdo->prepare(
            'SELECT message_text
             FROM ticket_messages
             WHERE id = :message_id
               AND ticket_id = :ticket_id
             LIMIT 1'
        );
        $messageStatement->execute([
            ':message_id' => $messageId,
            ':ticket_id' => $ticketId,
        ]);
        $messageText = $messageStatement->fetchColumn();
        if (!is_string($messageText)) {
            return null;
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $messageText));
        if (!array_key_exists($lineIndex, $lines)) {
            return null;
        }

        $marker = $checked ? 'x' : ' ';
        $updatedLine = preg_replace('/^(\s*)\[(?: |x|X)\]/', '$1[' . $marker . ']', $lines[$lineIndex], 1);
        if (!is_string($updatedLine) || $updatedLine === $lines[$lineIndex]) {
            return null;
        }

        $lines[$lineIndex] = $updatedLine;
        $updatedMessageText = implode("\n", $lines);

        $updateMessageStatement = $this->pdo->prepare(
            'UPDATE ticket_messages
             SET message_text = :message_text
             WHERE id = :message_id
               AND ticket_id = :ticket_id'
        );
        $updateMessageStatement->execute([
            ':message_text' => $updatedMessageText,
            ':message_id' => $messageId,
            ':ticket_id' => $ticketId,
        ]);

        if ($updateMessageStatement->rowCount() <= 0) {
            return null;
        }

        $this->touchTicketUpdatedAt($ticketId);
        return $updatedMessageText;
    }

    public function getTextTranslation(string $entityType, int $entityId, string $targetLanguage, string $sourceHash): ?array
    {
        $normalizedEntityType = trim($entityType);
        $normalizedTargetLanguage = strtolower(trim($targetLanguage));
        $normalizedSourceHash = trim($sourceHash);

        if ($normalizedEntityType === '' || $entityId <= 0 || $normalizedTargetLanguage === '' || $normalizedSourceHash === '') {
            return null;
        }

        $statement = $this->pdo->prepare(
            'SELECT entity_type, entity_id, ticket_id, target_language, source_language, source_hash, translated_text, created_at, updated_at
             FROM ticket_text_translations
             WHERE entity_type = :entity_type
               AND entity_id = :entity_id
               AND target_language = :target_language
               AND source_hash = :source_hash
             LIMIT 1'
        );
        $statement->execute([
            ':entity_type' => $normalizedEntityType,
            ':entity_id' => $entityId,
            ':target_language' => $normalizedTargetLanguage,
            ':source_hash' => $normalizedSourceHash,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function upsertTextTranslation(
        string $entityType,
        int $entityId,
        int $ticketId,
        string $targetLanguage,
        string $sourceLanguage,
        string $sourceHash,
        string $translatedText
    ): void {
        $normalizedEntityType = trim($entityType);
        $normalizedTargetLanguage = strtolower(trim($targetLanguage));
        $normalizedSourceLanguage = strtolower(trim($sourceLanguage));
        $normalizedSourceHash = trim($sourceHash);

        if ($normalizedEntityType === '' || $entityId <= 0 || $ticketId <= 0 || $normalizedTargetLanguage === '' || $normalizedSourceHash === '') {
            return;
        }

        $now = date('c');
        $statement = $this->pdo->prepare(
            'INSERT INTO ticket_text_translations (
                entity_type,
                entity_id,
                ticket_id,
                target_language,
                source_language,
                source_hash,
                translated_text,
                created_at,
                updated_at
            ) VALUES (
                :entity_type,
                :entity_id,
                :ticket_id,
                :target_language,
                :source_language,
                :source_hash,
                :translated_text,
                :created_at,
                :updated_at
            )
            ON CONFLICT(entity_type, entity_id, target_language)
            DO UPDATE SET
                ticket_id = excluded.ticket_id,
                source_language = excluded.source_language,
                source_hash = excluded.source_hash,
                translated_text = excluded.translated_text,
                updated_at = excluded.updated_at'
        );
        $statement->execute([
            ':entity_type' => $normalizedEntityType,
            ':entity_id' => $entityId,
            ':ticket_id' => $ticketId,
            ':target_language' => $normalizedTargetLanguage,
            ':source_language' => $normalizedSourceLanguage,
            ':source_hash' => $normalizedSourceHash,
            ':translated_text' => $translatedText,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function countTickets(bool $isAdmin, string $userEmail, array $statusFilters = [], ?string $assignedFilter = null, array $categoryFilters = [], ?string $searchQuery = null, string $browseMode = 'default'): int
    {
        $filterState = $this->buildTicketListFilters($isAdmin, $userEmail, $statusFilters, $assignedFilter, $categoryFilters, $searchQuery, $browseMode);
        $sql = 'SELECT COUNT(*) FROM tickets t';

        if ($filterState['conditions'] !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $filterState['conditions']);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($filterState['parameters']);

        return (int) $statement->fetchColumn();
    }

    public function getTickets(bool $isAdmin, string $userEmail, array $statusFilters = [], ?string $assignedFilter = null, array $categoryFilters = [], ?string $searchQuery = null, string $browseMode = 'default', ?int $limit = null, ?int $offset = null): array
    {
        $filterState = $this->buildTicketListFilters($isAdmin, $userEmail, $statusFilters, $assignedFilter, $categoryFilters, $searchQuery, $browseMode);
        $conditions = $filterState['conditions'];
        $parameters = $filterState['parameters'];
        $adminList = $filterState['adminList'];
        $allCompletedPublic = $filterState['allCompletedPublic'];

        $sql = 'SELECT ' . self::TICKET_LIST_COLUMNS . ',
                       COALESCE(message_counts.message_count, 0) AS message_count,
                       COALESCE(attachment_counts.attachment_count, 0) AS attachment_count
                FROM tickets t
                LEFT JOIN (
                    SELECT ticket_id, COUNT(*) AS message_count
                    FROM ticket_messages
                    WHERE COALESCE(is_ghost, 0) = 0
                    GROUP BY ticket_id
                ) message_counts ON message_counts.ticket_id = t.id
                LEFT JOIN (
                    SELECT ticket_id, COUNT(*) AS attachment_count
                    FROM ticket_attachments
                    GROUP BY ticket_id
                ) attachment_counts ON attachment_counts.ticket_id = t.id';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($adminList || $allCompletedPublic) {
            $sql .= " ORDER BY CASE WHEN t.status = 'afgehandeld' THEN 1 ELSE 0 END ASC,
                            datetime(t.created_at) DESC,
                            t.id DESC";
        } else {
            $sql .= ' ORDER BY datetime(t.updated_at) DESC, datetime(t.created_at) DESC, t.id DESC';
        }

        $this->writeSearchDebugLog($sql, $parameters);

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        $tickets = $statement->fetchAll(PDO::FETCH_ASSOC);
        $tickets = array_map(fn(array $ticket): array => $this->applyDerivedPriorityForDueDate($ticket), $tickets);

        if ($adminList) {
            $this->sortAdminTicketList($tickets);
        }

        if ($limit !== null) {
            $offset = max(0, $offset ?? 0);
            $tickets = array_slice($tickets, $offset, max(0, $limit));
        }

        foreach ($tickets as &$ticket) {
            $assignedEmail = strtolower(trim((string) ($ticket['assigned_email'] ?? '')));
            if ($assignedEmail !== '' || strtolower((string) ($ticket['status'] ?? '')) === 'afgehandeld') {
                continue;
            }

            $ticketId = (int) ($ticket['id'] ?? 0);
            if ($ticketId <= 0) {
                continue;
            }

            $newAssignee = $this->ensureTicketAssigned($ticketId);
            if ($newAssignee !== null) {
                $ticket['assigned_email'] = $newAssignee;
            }
        }
        unset($ticket);

        return $tickets;
    }

    /**
     * @return array{conditions: list<string>, parameters: array<string, mixed>, adminList: bool, allCompletedPublic: bool}
     */
    /**
     * Extract ticket IDs that the search query names exactly (e.g. "42", "#42").
     *
     * @return list<int>
     */
    private function extractExactTicketIdsFromSearch(?string $searchQuery): array
    {
        $searchQuery = trim((string) ($searchQuery ?? ''));
        if ($searchQuery === '') {
            return [];
        }

        $ids = [];
        $candidates = preg_split('/\s+/u', $searchQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidates[] = $searchQuery;

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }
            if (preg_match('/^#?(\d+)$/u', $candidate, $matches) !== 1) {
                continue;
            }
            $ticketId = (int) ($matches[1] ?? 0);
            if ($ticketId > 0) {
                $ids[$ticketId] = $ticketId;
            }
        }

        return array_values($ids);
    }

    private function buildTicketListFilters(bool $isAdmin, string $userEmail, array $statusFilters, ?string $assignedFilter, array $categoryFilters, ?string $searchQuery, string $browseMode): array
    {
        $accessConditions = [];
        $listConditions = [];
        $parameters = [];
        $allCompletedPublic = $browseMode === 'all_completed_public';
        $adminList = $isAdmin && !$allCompletedPublic;
        $searchEnabled = $adminList || $allCompletedPublic;
        $exactTicketIds = $searchEnabled ? $this->extractExactTicketIdsFromSearch($searchQuery) : [];

        if ($allCompletedPublic) {
            $accessConditions[] = "t.status = 'afgehandeld'";
            $accessConditions[] = 'COALESCE(t.is_private, 0) = 0';
        } elseif (!$isAdmin) {
            $accessConditions[] = 'EXISTS (
                SELECT 1
                FROM ticket_participants tp
                WHERE tp.ticket_id = t.id
                  AND lower(tp.user_email) = :user_email
            )';
            $parameters[':user_email'] = strtolower(trim($userEmail));
        }

        if ($statusFilters !== [] && !$allCompletedPublic) {
            $statusPlaceholders = [];
            foreach ($statusFilters as $index => $status) {
                $placeholder = ':status_' . $index;
                $statusPlaceholders[] = $placeholder;
                $parameters[$placeholder] = $status;
            }
            $listConditions[] = 't.status IN (' . implode(', ', $statusPlaceholders) . ')';
        }

        if (($adminList || $allCompletedPublic) && $assignedFilter !== null && $assignedFilter !== '') {
            if ($assignedFilter === '__unassigned__') {
                $listConditions[] = '(t.assigned_email IS NULL OR t.assigned_email = "")';
            } else {
                $listConditions[] = 't.assigned_email = :assigned_email';
                $parameters[':assigned_email'] = strtolower(trim($assignedFilter));
            }
        }

        if ($categoryFilters !== []) {
            $categoryPlaceholders = [];
            foreach ($categoryFilters as $index => $category) {
                $placeholder = ':category_' . $index;
                $categoryPlaceholders[] = $placeholder;
                $parameters[$placeholder] = $category;
            }
            $listConditions[] = 't.category IN (' . implode(', ', $categoryPlaceholders) . ')';
        }

        if ($searchEnabled) {
            $searchQuery = trim((string) ($searchQuery ?? ''));
            if ($searchQuery !== '') {
                $searchTerms = preg_split('/\s+/u', $searchQuery, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                $searchTerms = array_values(array_filter(array_map('trim', $searchTerms), static fn(string $term): bool => $term !== ''));

                if ($searchTerms !== []) {
                    $searchConditions = [];
                    foreach ($searchTerms as $index => $term) {
                        $placeholder = ':search_' . $index;
                        $parameters[$placeholder] = self::unicodeLower($term);
                        $searchConditions[] = "(
                            instr(mb_lower(CAST(t.id AS TEXT)), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.title, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.description, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.user_email, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.assigned_email, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.status, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.category, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.created_at, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.updated_at, '')), {$placeholder}) > 0
                            OR instr(mb_lower(COALESCE(t.resolved_at, '')), {$placeholder}) > 0
                            OR instr(CAST(COALESCE(t.priority, 0) AS TEXT), {$placeholder}) > 0
                            OR EXISTS (
                                SELECT 1
                                FROM ticket_participants tp
                                WHERE tp.ticket_id = t.id
                                  AND instr(mb_lower(COALESCE(tp.user_email, '')), {$placeholder}) > 0
                            )
                            OR EXISTS (
                                SELECT 1
                                FROM ticket_messages tm
                                WHERE tm.ticket_id = t.id
                                  AND COALESCE(tm.is_ghost, 0) = 0
                                  AND (
                                      instr(mb_lower(COALESCE(tm.sender_email, '')), {$placeholder}) > 0
                                      OR instr(mb_lower(COALESCE(tm.message_text, '')), {$placeholder}) > 0
                                  )
                            )
                            OR EXISTS (
                                SELECT 1
                                FROM ticket_attachments ta
                                WHERE ta.ticket_id = t.id
                                  AND (
                                      instr(mb_lower(COALESCE(ta.original_name, '')), {$placeholder}) > 0
                                      OR instr(mb_lower(COALESCE(ta.uploaded_by_email, '')), {$placeholder}) > 0
                                  )
                            )
                        )";
                    }

                    $listConditions[] = implode(' AND ', $searchConditions);
                }
            }
        }

        $filteredConditions = array_merge($accessConditions, $listConditions);
        $conditions = $filteredConditions;

        // Exact ticket-number matches always surface, ignoring status/assignee/category filters.
        // Admins may jump to any ticket by ID; other viewers still keep access restrictions.
        if ($exactTicketIds !== []) {
            $exactPlaceholders = [];
            foreach ($exactTicketIds as $index => $ticketId) {
                $placeholder = ':exact_ticket_id_' . $index;
                $exactPlaceholders[] = $placeholder;
                $parameters[$placeholder] = $ticketId;
            }
            $exactMatchCondition = 't.id IN (' . implode(', ', $exactPlaceholders) . ')';
            $exactBranchConditions = $isAdmin
                ? [$exactMatchCondition]
                : array_merge($accessConditions, [$exactMatchCondition]);

            $filteredSql = $filteredConditions === [] ? '1=1' : implode(' AND ', $filteredConditions);
            $exactSql = implode(' AND ', $exactBranchConditions);
            $conditions = ['((' . $filteredSql . ') OR (' . $exactSql . '))'];
        }

        return [
            'conditions' => $conditions,
            'parameters' => $parameters,
            'adminList' => $adminList,
            'allCompletedPublic' => $allCompletedPublic,
        ];
    }

    /**
     * @param list<array<string, mixed>> $tickets
     */
    private function sortAdminTicketList(array &$tickets): void
    {
        usort($tickets, static function (array $left, array $right): int {
            $leftResolved = (string) ($left['status'] ?? '') === 'afgehandeld';
            $rightResolved = (string) ($right['status'] ?? '') === 'afgehandeld';
            if ($leftResolved !== $rightResolved) {
                return $leftResolved ? 1 : -1;
            }

            if (!$leftResolved) {
                $leftPriority = (int) ($left['priority'] ?? 0);
                $rightPriority = (int) ($right['priority'] ?? 0);
                if ($leftPriority !== $rightPriority) {
                    return $rightPriority <=> $leftPriority;
                }
            }

            $leftCreated = strtotime((string) ($left['created_at'] ?? '')) ?: 0;
            $rightCreated = strtotime((string) ($right['created_at'] ?? '')) ?: 0;
            if ($leftCreated !== $rightCreated) {
                return $rightCreated <=> $leftCreated;
            }

            return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
        });
    }

    public function getTicket(int $ticketId, bool $isAdmin, string $userEmail, string $browseMode = 'default', bool $includeGhostMessages = false): ?array
    {
        $conditions = ['id = :id'];
        $parameters = [':id' => $ticketId];
        $allCompletedPublic = $browseMode === 'all_completed_public';

        if ($allCompletedPublic) {
            $conditions[] = "status = 'afgehandeld'";
            $conditions[] = 'COALESCE(is_private, 0) = 0';
        } elseif (!$isAdmin) {
            $conditions[] = 'EXISTS (
                SELECT 1
                FROM ticket_participants tp
                WHERE tp.ticket_id = tickets.id
                  AND lower(tp.user_email) = :user_email
            )';
            $parameters[':user_email'] = strtolower(trim($userEmail));
        }

        $statement = $this->pdo->prepare('SELECT * FROM tickets WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1');
        $statement->execute($parameters);

        $ticket = $statement->fetch(PDO::FETCH_ASSOC);
        if ($ticket === false) {
            return null;
        }

        $ticket = $this->applyDerivedPriorityForDueDate($ticket);

        $assignedEmail = strtolower(trim((string) ($ticket['assigned_email'] ?? '')));
        if ($assignedEmail === '' && strtolower((string) ($ticket['status'] ?? '')) !== 'afgehandeld') {
            $newAssignee = $this->ensureTicketAssigned((int) $ticket['id']);
            if ($newAssignee !== null) {
                $ticket['assigned_email'] = $newAssignee;
            }
        }

        $ticket['participant_emails'] = $this->getTicketParticipants((int) $ticket['id']);
        $ticket['messages'] = $this->getMessagesForTicket((int) $ticket['id'], $includeGhostMessages);

        return $ticket;
    }

    /**
     * @param list<int> $ticketIds
     * @return array<int, list<string>>
     */
    public function getTicketParticipantsBatch(array $ticketIds): array
    {
        $ticketIds = $this->normalizeTicketIds($ticketIds);
        if ($ticketIds === []) {
            return [];
        }

        [$inClause, $parameters] = $this->buildInClause('ticket_id', $ticketIds);
        $statement = $this->pdo->prepare(
            'SELECT ticket_id, user_email
             FROM ticket_participants
             WHERE ticket_id IN (' . $inClause . ')
             ORDER BY ticket_id ASC, datetime(created_at) ASC, id ASC'
        );
        $statement->execute($parameters);

        $participantsByTicket = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $ticketId = (int) ($row['ticket_id'] ?? 0);
            $email = strtolower(trim((string) ($row['user_email'] ?? '')));
            if ($ticketId <= 0 || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $participantsByTicket[$ticketId][$email] = $email;
        }

        $normalized = [];
        foreach ($participantsByTicket as $ticketId => $participants) {
            $normalized[$ticketId] = array_values($participants);
        }

        return $normalized;
    }

    /**
     * @param list<int> $ticketIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function getTicketMessagesBatch(array $ticketIds, bool $includeGhostMessages = false): array
    {
        $ticketIds = $this->normalizeTicketIds($ticketIds);
        if ($ticketIds === []) {
            return [];
        }

        [$inClause, $parameters] = $this->buildInClause('ticket_id', $ticketIds);

        $ghostSql = $includeGhostMessages ? '' : ' AND COALESCE(is_ghost, 0) = 0';
        $messageStatement = $this->pdo->prepare(
            'SELECT *
             FROM ticket_messages
             WHERE ticket_id IN (' . $inClause . ')' . $ghostSql . '
             ORDER BY ticket_id ASC, datetime(created_at) ASC, id ASC'
        );
        $messageStatement->execute($parameters);
        $messages = $messageStatement->fetchAll(PDO::FETCH_ASSOC);

        $attachmentStatement = $this->pdo->prepare(
            'SELECT *
             FROM ticket_attachments
             WHERE ticket_id IN (' . $inClause . ')
             ORDER BY ticket_id ASC, datetime(created_at) ASC, id ASC'
        );
        $attachmentStatement->execute($parameters);

        $attachmentsByTicketAndMessage = [];
        foreach ($attachmentStatement->fetchAll(PDO::FETCH_ASSOC) as $attachment) {
            $ticketId = (int) ($attachment['ticket_id'] ?? 0);
            $messageId = (int) ($attachment['message_id'] ?? 0);
            if ($ticketId <= 0) {
                continue;
            }

            $attachmentsByTicketAndMessage[$ticketId][$messageId][] = $attachment;
        }

        $messagesByTicket = [];
        foreach ($messages as $message) {
            $ticketId = (int) ($message['ticket_id'] ?? 0);
            $messageId = (int) ($message['id'] ?? 0);
            if ($ticketId <= 0) {
                continue;
            }

            $message['attachments'] = $attachmentsByTicketAndMessage[$ticketId][$messageId] ?? [];
            $message['is_ghost'] = !empty($message['is_ghost']);
            $messagesByTicket[$ticketId][] = $message;
        }

        return $messagesByTicket;
    }

    public function getTicketParticipants(int $ticketId): array
    {
        if ($ticketId <= 0) {
            return [];
        }

        $statement = $this->pdo->prepare(
            'SELECT user_email
             FROM ticket_participants
             WHERE ticket_id = :ticket_id
             ORDER BY datetime(created_at) ASC, id ASC'
        );
        $statement->execute([':ticket_id' => $ticketId]);

        $participants = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $email = strtolower(trim((string) ($row['user_email'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $participants[$email] = $email;
        }

        return array_values($participants);
    }

    public function addTicketParticipants(int $ticketId, array $participantEmails, string $addedByEmail): int
    {
        $addedByEmail = strtolower(trim($addedByEmail));
        $participants = $this->normalizeParticipantEmails($participantEmails);
        if ($ticketId <= 0 || $participants === []) {
            return 0;
        }

        $requesterEmail = $this->getTicketRequesterEmail($ticketId);
        if ($requesterEmail === '') {
            return 0;
        }

        $participantStatement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO ticket_participants (ticket_id, user_email, added_by_email, created_at)
             VALUES (:ticket_id, :user_email, :added_by_email, :created_at)'
        );

        $now = date('c');
        $addedCount = 0;

        $this->pdo->beginTransaction();
        try {
            $participantStatement->execute([
                ':ticket_id' => $ticketId,
                ':user_email' => $requesterEmail,
                ':added_by_email' => $addedByEmail !== '' ? $addedByEmail : $requesterEmail,
                ':created_at' => $now,
            ]);

            foreach ($participants as $participantEmail) {
                if ($participantEmail === $requesterEmail) {
                    continue;
                }

                $participantStatement->execute([
                    ':ticket_id' => $ticketId,
                    ':user_email' => $participantEmail,
                    ':added_by_email' => $addedByEmail !== '' ? $addedByEmail : $requesterEmail,
                    ':created_at' => $now,
                ]);

                if ($participantStatement->rowCount() > 0) {
                    $addedCount++;
                }
            }

            if ($addedCount > 0) {
                $this->touchTicketUpdatedAt($ticketId, $now);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }

        return $addedCount;
    }

    public function removeTicketParticipant(int $ticketId, string $participantEmail): bool
    {
        $participantEmail = strtolower(trim($participantEmail));
        if ($ticketId <= 0 || $participantEmail === '' || !filter_var($participantEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $participants = $this->getTicketParticipants($ticketId);
        if (count($participants) <= 1) {
            return false;
        }

        $deleteStatement = $this->pdo->prepare(
            'DELETE FROM ticket_participants
             WHERE ticket_id = :ticket_id
               AND lower(user_email) = :user_email'
        );
        $deleteStatement->execute([
            ':ticket_id' => $ticketId,
            ':user_email' => $participantEmail,
        ]);

        if ($deleteStatement->rowCount() <= 0) {
            return false;
        }

        $remainingParticipants = $this->getTicketParticipants($ticketId);
        if (count($remainingParticipants) <= 0) {
            return false;
        }

        $this->touchTicketUpdatedAt($ticketId);
        return true;
    }

    public function getAttachment(int $attachmentId): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ticket_attachments WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $attachmentId]);

        $attachment = $statement->fetch(PDO::FETCH_ASSOC);

        return $attachment !== false ? $attachment : null;
    }

    public function queueBrowserNotifications(array $recipientEmails, int $ticketId, string $title, string $body): void
    {
        if ($ticketId <= 0) {
            return;
        }

        $normalizedRecipients = [];
        foreach ($recipientEmails as $recipientEmail) {
            $email = strtolower(trim((string) $recipientEmail));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $normalizedRecipients[$email] = $email;
        }

        if ($normalizedRecipients === []) {
            return;
        }

        $title = trim($title);
        $body = trim($body);
        $createdAt = date('c');

        $statement = $this->pdo->prepare(
            'INSERT INTO browser_notifications (user_email, ticket_id, title, body, created_at)
             VALUES (:user_email, :ticket_id, :title, :body, :created_at)'
        );

        foreach ($normalizedRecipients as $email) {
            $statement->execute([
                ':user_email' => $email,
                ':ticket_id' => $ticketId,
                ':title' => $title,
                ':body' => $body,
                ':created_at' => $createdAt,
            ]);
        }
    }

    public function pullBrowserNotifications(string $userEmail, int $limit = 25): array
    {
        $userEmail = strtolower(trim($userEmail));
        if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $statement = $this->pdo->prepare(
            'SELECT id, ticket_id, title, body, created_at
             FROM browser_notifications
             WHERE user_email = :user_email
               AND delivered_at IS NULL
             ORDER BY datetime(created_at) ASC, id ASC
             LIMIT :limit'
        );
        $statement->bindValue(':user_email', $userEmail, PDO::PARAM_STR);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        $updateParameters = [':delivered_at' => date('c')];
        $idPlaceholders = [];
        foreach ($rows as $index => $row) {
            $placeholder = ':id_' . $index;
            $idPlaceholders[] = $placeholder;
            $updateParameters[$placeholder] = (int) ($row['id'] ?? 0);
        }

        $update = $this->pdo->prepare(
            'UPDATE browser_notifications
             SET delivered_at = :delivered_at
             WHERE id IN (' . implode(', ', $idPlaceholders) . ')'
        );
        $update->execute($updateParameters);

        return array_map(
            static fn(array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'ticket_id' => (int) ($row['ticket_id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'body' => (string) ($row['body'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ],
            $rows
        );
    }

    public function saveWebPushSubscription(string $userEmail, string $endpoint, string $p256dhKey, string $authKey, string $userAgent = ''): void
    {
        $userEmail = strtolower(trim($userEmail));
        $endpoint = trim($endpoint);
        $p256dhKey = trim($p256dhKey);
        $authKey = trim($authKey);
        $userAgent = trim($userAgent);

        if ($userEmail === '' || !filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if ($endpoint === '' || $p256dhKey === '' || $authKey === '') {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO web_push_subscriptions (
                user_email,
                endpoint,
                p256dh_key,
                auth_key,
                user_agent,
                created_at,
                updated_at
            ) VALUES (
                :user_email,
                :endpoint,
                :p256dh_key,
                :auth_key,
                :user_agent,
                :created_at,
                :updated_at
            )
            ON CONFLICT(endpoint) DO UPDATE SET
                user_email = excluded.user_email,
                p256dh_key = excluded.p256dh_key,
                auth_key = excluded.auth_key,
                user_agent = excluded.user_agent,
                updated_at = excluded.updated_at'
        );

        $now = date('c');
        $statement->execute([
            ':user_email' => $userEmail,
            ':endpoint' => $endpoint,
            ':p256dh_key' => $p256dhKey,
            ':auth_key' => $authKey,
            ':user_agent' => $userAgent,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function removeWebPushSubscription(string $userEmail, string $endpoint): void
    {
        $userEmail = strtolower(trim($userEmail));
        $endpoint = trim($endpoint);
        if ($userEmail === '' || $endpoint === '') {
            return;
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM web_push_subscriptions
             WHERE user_email = :user_email
               AND endpoint = :endpoint'
        );
        $statement->execute([
            ':user_email' => $userEmail,
            ':endpoint' => $endpoint,
        ]);
    }

    public function removeWebPushSubscriptionsByEndpoints(array $endpoints): void
    {
        $normalizedEndpoints = [];
        foreach ($endpoints as $endpoint) {
            $value = trim((string) $endpoint);
            if ($value === '') {
                continue;
            }
            $normalizedEndpoints[$value] = $value;
        }

        if ($normalizedEndpoints === []) {
            return;
        }

        $placeholders = [];
        $parameters = [];
        foreach (array_values($normalizedEndpoints) as $index => $endpoint) {
            $placeholder = ':endpoint_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $endpoint;
        }

        $statement = $this->pdo->prepare(
            'DELETE FROM web_push_subscriptions
             WHERE endpoint IN (' . implode(', ', $placeholders) . ')'
        );
        $statement->execute($parameters);
    }

    public function getWebPushSubscriptionsByUserEmails(array $userEmails): array
    {
        $normalizedUsers = [];
        foreach ($userEmails as $userEmail) {
            $email = strtolower(trim((string) $userEmail));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $normalizedUsers[$email] = $email;
        }

        if ($normalizedUsers === []) {
            return [];
        }

        $placeholders = [];
        $parameters = [];
        foreach (array_values($normalizedUsers) as $index => $email) {
            $placeholder = ':user_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = $email;
        }

        $statement = $this->pdo->prepare(
            'SELECT user_email, endpoint, p256dh_key, auth_key
             FROM web_push_subscriptions
             WHERE user_email IN (' . implode(', ', $placeholders) . ')
             ORDER BY user_email ASC, id ASC'
        );
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function connect(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('De PDO SQLite-extensie is niet beschikbaar op deze server.');
        }

        $directory = dirname($this->databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('De data-map kon niet worden aangemaakt.');
        }

        $this->pdo = new PDO('sqlite:' . $this->databasePath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $sqliteCreateFunctionMethod = 'sqlite' . 'CreateFunction';
        $registerSqliteFunction = [$this->pdo, $sqliteCreateFunctionMethod];
        try {
            call_user_func($registerSqliteFunction, 'mb_lower', static fn($value): string => self::unicodeLower((string) $value), 1);
        } catch (Throwable $exception) {
            throw new RuntimeException('De SQLite-driver ondersteunt geen custom functies (sqliteCreateFunction ontbreekt).', 0, $exception);
        }
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
        $this->pdo->exec('PRAGMA temp_store = MEMORY');
        $this->pdo->exec('PRAGMA cache_size = -64000');
    }

    /**
     * @param list<int> $ticketIds
     * @return list<int>
     */
    private function normalizeTicketIds(array $ticketIds): array
    {
        $normalized = [];
        foreach ($ticketIds as $ticketId) {
            $ticketId = (int) $ticketId;
            if ($ticketId > 0) {
                $normalized[$ticketId] = $ticketId;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param list<int> $values
     * @return array{0: string, 1: array<string, int>}
     */
    private function buildInClause(string $prefix, array $values): array
    {
        $placeholders = [];
        $parameters = [];
        foreach (array_values($values) as $index => $value) {
            $placeholder = ':' . $prefix . '_' . $index;
            $placeholders[] = $placeholder;
            $parameters[$placeholder] = (int) $value;
        }

        return [implode(', ', $placeholders), $parameters];
    }

    private function getAppMeta(string $key): ?string
    {
        $statement = $this->pdo->prepare('SELECT value FROM app_meta WHERE key = :key LIMIT 1');
        $statement->execute([':key' => $key]);
        $value = $statement->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    private function setAppMeta(string $key, string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO app_meta (key, value) VALUES (:key, :value)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        $statement->execute([
            ':key' => $key,
            ':value' => $value,
        ]);
    }

    private static function unicodeLower(string $value): string
    {
        if ($value === '') {
            return '';
        }

        static $map = [
            'À' => 'à', 'Á' => 'á', 'Â' => 'â', 'Ã' => 'ã', 'Ä' => 'ä', 'Å' => 'å',
            'Æ' => 'æ', 'Ç' => 'ç', 'È' => 'è', 'É' => 'é', 'Ê' => 'ê', 'Ë' => 'ë',
            'Ì' => 'ì', 'Í' => 'í', 'Î' => 'î', 'Ï' => 'ï', 'Ð' => 'ð', 'Ñ' => 'ñ',
            'Ò' => 'ò', 'Ó' => 'ó', 'Ô' => 'ô', 'Õ' => 'õ', 'Ö' => 'ö', 'Ø' => 'ø',
            'Ù' => 'ù', 'Ú' => 'ú', 'Û' => 'û', 'Ü' => 'ü', 'Ý' => 'ý', 'Þ' => 'þ',
            'Ā' => 'ā', 'Ă' => 'ă', 'Ą' => 'ą', 'Ć' => 'ć', 'Ĉ' => 'ĉ', 'Ċ' => 'ċ',
            'Č' => 'č', 'Ď' => 'ď', 'Đ' => 'đ', 'Ē' => 'ē', 'Ĕ' => 'ĕ', 'Ė' => 'ė',
            'Ę' => 'ę', 'Ě' => 'ě', 'Ĝ' => 'ĝ', 'Ğ' => 'ğ', 'Ġ' => 'ġ', 'Ģ' => 'ģ',
            'Ĥ' => 'ĥ', 'Ħ' => 'ħ', 'Ĩ' => 'ĩ', 'Ī' => 'ī', 'Ĭ' => 'ĭ', 'Į' => 'į',
            'İ' => 'i', 'Ĳ' => 'ĳ', 'Ĵ' => 'ĵ', 'Ķ' => 'ķ', 'Ĺ' => 'ĺ', 'Ļ' => 'ļ',
            'Ľ' => 'ľ', 'Ŀ' => 'ŀ', 'Ł' => 'ł', 'Ń' => 'ń', 'Ņ' => 'ņ', 'Ň' => 'ň',
            'Ŋ' => 'ŋ', 'Ō' => 'ō', 'Ŏ' => 'ŏ', 'Ő' => 'ő', 'Œ' => 'œ', 'Ŕ' => 'ŕ',
            'Ŗ' => 'ŗ', 'Ř' => 'ř', 'Ś' => 'ś', 'Ŝ' => 'ŝ', 'Ş' => 'ş', 'Š' => 'š',
            'Ţ' => 'ţ', 'Ť' => 'ť', 'Ŧ' => 'ŧ', 'Ũ' => 'ũ', 'Ū' => 'ū', 'Ŭ' => 'ŭ',
            'Ů' => 'ů', 'Ű' => 'ű', 'Ų' => 'ų', 'Ŵ' => 'ŵ', 'Ŷ' => 'ŷ', 'Ÿ' => 'ÿ',
            'Ź' => 'ź', 'Ż' => 'ż', 'Ž' => 'ž', 'ẞ' => 'ß',
        ];

        return strtolower(strtr($value, $map));
    }

    private function writeSearchDebugLog(string $sql, array $parameters): void
    {
        if (getenv('ASCLEPIUS_DEBUG_SEARCH') !== '1') {
            return;
        }

        $hasSearchParameters = (bool) array_filter(
            array_keys($parameters),
            static fn($key): bool => str_starts_with((string) $key, ':search_')
        );

        if (!$hasSearchParameters) {
            return;
        }

        $debugPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'asclepius_search_debug.log';
        $maxDebugFileSize = 1024 * 1024;
        clearstatcache(true, $debugPath);
        if (is_file($debugPath)) {
            $size = filesize($debugPath);
            if ($size !== false && $size >= $maxDebugFileSize) {
                return;
            }
        }

        $entry = [
            'time' => gmdate('c'),
            'sql_fragment' => $sql,
            'parameters' => $this->redactSearchDebugParameters($parameters),
        ];
        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            return;
        }

        @file_put_contents($debugPath, $encoded . "\n", FILE_APPEND | LOCK_EX);
    }

    private function redactSearchDebugParameters(array $parameters): array
    {
        $redacted = [];
        foreach ($parameters as $key => $value) {
            if (is_string($value)) {
                $redacted[$key] = '[redacted:string;len=' . strlen($value) . ';sha256=' . substr(hash('sha256', $value), 0, 12) . ']';
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = '[redacted:array;count=' . count($value) . ']';
                continue;
            }

            if (is_object($value)) {
                $redacted[$key] = '[redacted:object:' . get_debug_type($value) . ']';
                continue;
            }

            $redacted[$key] = $value;
        }

        return $redacted;
    }

    private function initialize(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS app_meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS tickets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL DEFAULT "",
                category TEXT NOT NULL,
                user_email TEXT NOT NULL,
                assigned_email TEXT DEFAULT NULL,
                status TEXT NOT NULL DEFAULT "ingediend",
                description TEXT NOT NULL DEFAULT "",
                priority INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL,
                sender_email TEXT NOT NULL,
                sender_role TEXT NOT NULL,
                message_text TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                is_ghost INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_attachments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL,
                message_id INTEGER DEFAULT NULL,
                original_name TEXT NOT NULL,
                stored_name TEXT NOT NULL,
                stored_path TEXT NOT NULL,
                mime_type TEXT DEFAULT NULL,
                file_size INTEGER NOT NULL DEFAULT 0,
                uploaded_by_email TEXT NOT NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
                FOREIGN KEY(message_id) REFERENCES ticket_messages(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ict_user_category_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_email TEXT NOT NULL,
                category TEXT NOT NULL,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                UNIQUE(user_email, category)
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ict_user_availability (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_email TEXT NOT NULL UNIQUE,
                is_available INTEGER NOT NULL DEFAULT 1
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS browser_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_email TEXT NOT NULL,
                ticket_id INTEGER NOT NULL,
                title TEXT NOT NULL DEFAULT "",
                body TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                delivered_at TEXT DEFAULT NULL,
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS web_push_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_email TEXT NOT NULL,
                endpoint TEXT NOT NULL UNIQUE,
                p256dh_key TEXT NOT NULL,
                auth_key TEXT NOT NULL,
                user_agent TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_status_transitions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                started_at TEXT NOT NULL,
                ended_at TEXT DEFAULT NULL,
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS custom_ticket_statuses (
                status_key TEXT PRIMARY KEY,
                display_label TEXT NOT NULL,
                created_by_email TEXT NOT NULL,
                created_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_participants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_id INTEGER NOT NULL,
                user_email TEXT NOT NULL,
                added_by_email TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                UNIQUE(ticket_id, user_email),
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                body TEXT NOT NULL,
                created_by_email TEXT NOT NULL,
                updated_by_email TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS ticket_text_translations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                entity_type TEXT NOT NULL,
                entity_id INTEGER NOT NULL,
                ticket_id INTEGER NOT NULL,
                target_language TEXT NOT NULL,
                source_language TEXT NOT NULL DEFAULT "",
                source_hash TEXT NOT NULL,
                translated_text TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(entity_type, entity_id, target_language),
                FOREIGN KEY(ticket_id) REFERENCES tickets(id) ON DELETE CASCADE
            )'
        );

        $this->ensureColumn('tickets', 'title', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'assigned_email', 'TEXT DEFAULT NULL');
        $this->ensureColumn('tickets', 'status', 'TEXT NOT NULL DEFAULT "ingediend"');
        $this->ensureColumn('tickets', 'description', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'priority', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn('tickets', 'created_at', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'updated_at', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'resolved_at', 'TEXT DEFAULT NULL');
        $this->ensureColumn('tickets', 'due_date', 'TEXT DEFAULT NULL');
        $this->ensureColumn('tickets', 'is_private', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn('ticket_messages', 'message_text', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_messages', 'is_ghost', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn('ticket_attachments', 'mime_type', 'TEXT DEFAULT NULL');
        $this->ensureColumn('ticket_attachments', 'file_size', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn('ticket_text_translations', 'source_language', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_text_translations', 'source_hash', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_text_translations', 'translated_text', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_text_translations', 'created_at', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_text_translations', 'updated_at', 'TEXT NOT NULL DEFAULT ""');

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_user_email ON tickets(user_email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_assigned_email ON tickets(assigned_email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_priority ON tickets(priority)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_due_date ON tickets(due_date)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_updated_at ON tickets(updated_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_status_created_at ON tickets(status, created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_messages_ticket_id ON ticket_messages(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_attachments_ticket_id ON ticket_attachments(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_status_transitions_ticket_id ON ticket_status_transitions(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_status_transitions_ticket_status ON ticket_status_transitions(ticket_id, status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_custom_ticket_statuses_label ON custom_ticket_statuses(display_label)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_participants_ticket_id ON ticket_participants(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_participants_user_email ON ticket_participants(user_email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_templates_name ON ticket_templates(name)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_text_translations_lookup ON ticket_text_translations(entity_type, entity_id, target_language, source_hash)');
        $this->ensureColumn('ticket_templates', 'sort_order', 'INTEGER NOT NULL DEFAULT 0');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_browser_notifications_user_delivered ON browser_notifications(user_email, delivered_at, created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_web_push_subscriptions_user_email ON web_push_subscriptions(user_email)');
        $this->pdo->exec(
            "UPDATE tickets
             SET resolved_at = COALESCE(NULLIF(resolved_at, ''), NULLIF(updated_at, ''))
             WHERE status = 'afgehandeld'
               AND (resolved_at IS NULL OR resolved_at = '')"
        );

        $this->seedStatusTransitionHistory();
        $this->seedTicketParticipantsIfNeeded();

        $this->syncIctUsersIfNeeded();
    }

    private function seedTicketParticipantsIfNeeded(): void
    {
        $missingCount = (int) $this->pdo->query(
            'SELECT COUNT(*)
             FROM tickets t
             WHERE NOT EXISTS (
                 SELECT 1
                 FROM ticket_participants tp
                 WHERE tp.ticket_id = t.id
             )'
        )->fetchColumn();

        if ($missingCount <= 0) {
            return;
        }

        $this->seedTicketParticipants();
    }

    private function seedTicketParticipants(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO ticket_participants (ticket_id, user_email, added_by_email, created_at)
             VALUES (:ticket_id, :user_email, :added_by_email, :created_at)'
        );

        $tickets = $this->pdo->query(
            'SELECT t.id, t.user_email, t.created_at
             FROM tickets t
             LEFT JOIN (
                 SELECT ticket_id, COUNT(*) AS participant_count
                 FROM ticket_participants
                 GROUP BY ticket_id
             ) tp ON tp.ticket_id = t.id
             WHERE COALESCE(tp.participant_count, 0) = 0'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tickets as $ticket) {
            $ticketId = (int) ($ticket['id'] ?? 0);
            $requesterEmail = strtolower(trim((string) ($ticket['user_email'] ?? '')));
            $createdAt = (string) ($ticket['created_at'] ?? date('c'));

            if ($ticketId <= 0 || $requesterEmail === '' || !filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $statement->execute([
                ':ticket_id' => $ticketId,
                ':user_email' => $requesterEmail,
                ':added_by_email' => $requesterEmail,
                ':created_at' => $createdAt !== '' ? $createdAt : date('c'),
            ]);
        }
    }

    private function insertStatusTransition(int $ticketId, string $status, string $startedAt, ?string $endedAt = null): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ticket_status_transitions (ticket_id, status, started_at, ended_at)
             VALUES (:ticket_id, :status, :started_at, :ended_at)'
        );
        $statement->execute([
            ':ticket_id' => $ticketId,
            ':status' => strtolower(trim($status)),
            ':started_at' => $startedAt,
            ':ended_at' => $endedAt,
        ]);
    }

    private function seedStatusTransitionHistory(): void
    {
        $existingCount = (int) $this->pdo->query('SELECT COUNT(*) FROM ticket_status_transitions')->fetchColumn();
        if ($existingCount > 0) {
            return;
        }

        $tickets = $this->pdo->query(
            'SELECT id, status, created_at, updated_at, resolved_at
             FROM tickets
             ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);

        if ($tickets === []) {
            return;
        }

        $messageStatement = $this->pdo->prepare(
            'SELECT message_text, created_at
             FROM ticket_messages
             WHERE ticket_id = :ticket_id
             ORDER BY datetime(created_at) ASC, id ASC'
        );

        foreach ($tickets as $ticket) {
            $ticketId = (int) ($ticket['id'] ?? 0);
            if ($ticketId <= 0) {
                continue;
            }

            $currentStatus = 'ingediend';
            $currentStartedAt = (string) ($ticket['created_at'] ?? '');
            if ($currentStartedAt === '') {
                continue;
            }

            $messageStatement->execute([':ticket_id' => $ticketId]);
            $messages = $messageStatement->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages as $messageRow) {
                $messageAt = (string) ($messageRow['created_at'] ?? '');
                if ($messageAt === '') {
                    continue;
                }

                $messageStatus = $this->extractStatusFromMessage((string) ($messageRow['message_text'] ?? ''));
                if ($messageStatus === null || $messageStatus === $currentStatus) {
                    continue;
                }

                if (strtotime($messageAt) < strtotime($currentStartedAt)) {
                    continue;
                }

                $this->insertStatusTransition($ticketId, $currentStatus, $currentStartedAt, $messageAt);
                $currentStatus = $messageStatus;
                $currentStartedAt = $messageAt;
            }

            $finalStatus = strtolower(trim((string) ($ticket['status'] ?? $currentStatus)));
            $finalStatusTime = (string) ($ticket['updated_at'] ?? '');
            if ($finalStatus === 'afgehandeld') {
                $finalStatusTime = (string) ($ticket['resolved_at'] ?: $finalStatusTime);
            }

            if ($finalStatus !== $currentStatus && $finalStatusTime !== '' && strtotime($finalStatusTime) >= strtotime($currentStartedAt)) {
                $this->insertStatusTransition($ticketId, $currentStatus, $currentStartedAt, $finalStatusTime);
                $currentStatus = $finalStatus;
                $currentStartedAt = $finalStatusTime;
            }

            $endOfCurrent = $currentStatus === 'afgehandeld'
                ? ($finalStatusTime !== '' ? $finalStatusTime : $currentStartedAt)
                : null;

            $this->insertStatusTransition($ticketId, $currentStatus, $currentStartedAt, $endOfCurrent);
        }
    }

    private function extractStatusFromMessage(string $messageText): ?string
    {
        $statusByLabel = [
            'ingediend' => 'ingediend',
            'submitted' => 'ingediend',
            'eingereicht' => 'ingediend',
            'soumis' => 'ingediend',
            'in behandeling' => 'in behandeling',
            'in progress' => 'in behandeling',
            'in bearbeitung' => 'in behandeling',
            'en cours' => 'in behandeling',
            'afwachtende op gebruiker' => 'afwachtende op gebruiker',
            'awaiting user' => 'afwachtende op gebruiker',
            'wartet auf benutzer' => 'afwachtende op gebruiker',
            "en attente de l'utilisateur" => 'afwachtende op gebruiker',
            'afwachtende op bestelling' => 'afwachtende op bestelling',
            'awaiting order' => 'afwachtende op bestelling',
            'wartet auf bestellung' => 'afwachtende op bestelling',
            'en attente de commande' => 'afwachtende op bestelling',
            'afwachtende op derde partij' => 'afwachtende op derde partij',
            'awaiting third party' => 'afwachtende op derde partij',
            'wartet auf dritte partei' => 'afwachtende op derde partij',
            'en attente de tiers' => 'afwachtende op derde partij',
            'afgehandeld' => 'afgehandeld',
            'resolved' => 'afgehandeld',
            'erledigt' => 'afgehandeld',
            'résolu' => 'afgehandeld',
            'resolu' => 'afgehandeld',
        ];

        $prefixes = [
            'status gewijzigd naar ',
            'status changed to ',
            'status geändert auf ',
            'statut modifié en ',
        ];

        $normalized = str_replace(["\r\n", "\r"], "\n", trim($messageText));
        if ($normalized === '') {
            return null;
        }

        foreach (explode("\n", $normalized) as $line) {
            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $lowerLine = strtolower($trimmedLine);
            foreach ($prefixes as $prefix) {
                if (!str_starts_with($lowerLine, $prefix)) {
                    continue;
                }

                $statusLabel = trim(substr($lowerLine, strlen($prefix)), " .\t\n\r\0\x0B");
                return $statusByLabel[$statusLabel] ?? null;
            }
        }

        return null;
    }

    private function normalizeDueDate(?string $dueDate): ?string
    {
        $rawValue = trim((string) $dueDate);
        if ($rawValue === '') {
            return null;
        }

        $datePart = substr($rawValue, 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datePart) !== 1) {
            return null;
        }

        return $datePart;
    }

    private function applyDerivedPriorityForDueDate(array $ticket): array
    {
        $normalizedDueDate = $this->normalizeDueDate((string) ($ticket['due_date'] ?? ''));
        $ticket['due_date'] = $normalizedDueDate;
        $ticketStatus = strtolower(trim((string) ($ticket['status'] ?? '')));
        if ($normalizedDueDate === null || $ticketStatus === 'afgehandeld') {
            return $ticket;
        }

        $ticket['priority'] = $this->derivePriorityFromDueDate($normalizedDueDate);
        return $ticket;
    }

    private function derivePriorityFromDueDate(string $dueDate): int
    {
        try {
            $timezone = new DateTimeZone(date_default_timezone_get());
            $today = new DateTimeImmutable('today', $timezone);
            $due = DateTimeImmutable::createFromFormat('!Y-m-d', $dueDate, $timezone);
            if (!$due instanceof DateTimeImmutable) {
                return 0;
            }

            $calendarDaysRemaining = (int) $today->diff($due)->format('%r%a');
            if ($calendarDaysRemaining < 7) {
                $businessDaysRemaining = $this->countBusinessDaysBetween($today, $due);
                if ($businessDaysRemaining < 3) {
                    return 2;
                }

                return 1;
            }
        } catch (Throwable) {
            return 0;
        }

        return 0;
    }

    private function countBusinessDaysBetween(DateTimeImmutable $startDate, DateTimeImmutable $endDate): int
    {
        if ($endDate < $startDate) {
            return 0;
        }

        $dayCount = 0;
        $cursor = $startDate;
        while ($cursor <= $endDate) {
            $dayOfWeek = (int) $cursor->format('N');
            if ($dayOfWeek <= 5) {
                $dayCount++;
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $dayCount;
    }

    private function ensureColumn(string $tableName, string $columnName, string $definition): void
    {
        $statement = $this->pdo->query('PRAGMA table_info(' . $tableName . ')');
        $columns = $statement->fetchAll(PDO::FETCH_ASSOC);

        foreach ($columns as $column) {
            if (($column['name'] ?? '') === $columnName) {
                return;
            }
        }

        $this->pdo->exec('ALTER TABLE ' . $tableName . ' ADD COLUMN ' . $columnName . ' ' . $definition);
    }

    private function syncIctUsersIfNeeded(): void
    {
        if ($this->ictUsers === []) {
            return;
        }

        $configHash = sha1(json_encode([
            'users' => $this->ictUsers,
            'categories' => $this->categories,
        ], JSON_UNESCAPED_UNICODE) ?: '');

        if ($this->getAppMeta('ict_users_sync_hash') === $configHash) {
            return;
        }

        $this->syncIctUsers();
        $this->setAppMeta('ict_users_sync_hash', $configHash);
    }

    private function syncIctUsers(): void
    {
        if ($this->ictUsers === []) {
            return;
        }

        $settingsStatement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO ict_user_category_settings (user_email, category, is_enabled)
             VALUES (:user_email, :category, 1)'
        );
        $availabilityStatement = $this->pdo->prepare(
            'INSERT OR IGNORE INTO ict_user_availability (user_email, is_available)
             VALUES (:user_email, 1)'
        );

        foreach ($this->ictUsers as $ictUser) {
            $availabilityStatement->execute([
                ':user_email' => $ictUser,
            ]);

            foreach ($this->categories as $category) {
                $settingsStatement->execute([
                    ':user_email' => $ictUser,
                    ':category' => $category,
                ]);
            }
        }
    }

    private function pickAssignee(?string $category, ?string $excludeEmail = null): ?string
    {
        if ($this->ictUsers === []) {
            return null;
        }

        $excludeEmail = strtolower(trim((string) $excludeEmail));
        $excludeSettingsClause = $excludeEmail !== '' ? ' AND lower(settings.user_email) <> :exclude_email' : '';
        $excludeAvailabilityClause = $excludeEmail !== '' ? ' AND lower(availability.user_email) <> :exclude_email' : '';
        $allowedUserPlaceholders = [];
        $allowedUserParameters = [];
        foreach ($this->ictUsers as $index => $ictUser) {
            $placeholder = ':ict_user_' . $index;
            $allowedUserPlaceholders[] = $placeholder;
            $allowedUserParameters[$placeholder] = $ictUser;
        }

        $allowedUsersInClause = implode(', ', $allowedUserPlaceholders);

        if ($category !== null && trim($category) !== '') {
            $parameters = [':category' => $category];
            if ($excludeEmail !== '') {
                $parameters[':exclude_email'] = $excludeEmail;
            }

            $statement = $this->pdo->prepare(
                "SELECT settings.user_email,
                        COUNT(tickets.id) AS open_count
                 FROM ict_user_category_settings settings
                 INNER JOIN ict_user_availability availability
                    ON availability.user_email = settings.user_email
                   AND availability.is_available = 1
                 LEFT JOIN tickets
                    ON tickets.assigned_email = settings.user_email
                   AND tickets.status <> 'afgehandeld'
                 WHERE settings.category = :category
                   AND settings.is_enabled = 1
                                     AND lower(settings.user_email) IN ($allowedUsersInClause)
                   $excludeSettingsClause
                 GROUP BY settings.user_email
                 ORDER BY open_count ASC, settings.user_email ASC
                 LIMIT 1"
            );
            $statement->execute(array_merge($parameters, $allowedUserParameters));

            $row = $statement->fetch(PDO::FETCH_ASSOC);
            if ($row !== false) {
                return $row['user_email'];
            }
        }

        $parameters = [];
        if ($excludeEmail !== '') {
            $parameters[':exclude_email'] = $excludeEmail;
        }

        $statement = $this->pdo->prepare(
            "SELECT availability.user_email,
                    COUNT(tickets.id) AS open_count
             FROM ict_user_availability availability
             LEFT JOIN tickets
                ON tickets.assigned_email = availability.user_email
               AND tickets.status <> 'afgehandeld'
             WHERE availability.is_available = 1
                             AND lower(availability.user_email) IN ($allowedUsersInClause)
               $excludeAvailabilityClause
             GROUP BY availability.user_email
             ORDER BY open_count ASC, availability.user_email ASC
             LIMIT 1"
        );
        $statement->execute(array_merge($parameters, $allowedUserParameters));

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row['user_email'] : null;
    }

    private function getMessagesForTicket(int $ticketId, bool $includeGhostMessages = false): array
    {
        $ghostSql = $includeGhostMessages ? '' : ' AND COALESCE(is_ghost, 0) = 0';
        $messageStatement = $this->pdo->prepare(
            'SELECT *
             FROM ticket_messages
             WHERE ticket_id = :ticket_id' . $ghostSql . '
             ORDER BY datetime(created_at) ASC, id ASC'
        );
        $messageStatement->execute([':ticket_id' => $ticketId]);
        $messages = $messageStatement->fetchAll(PDO::FETCH_ASSOC);

        $attachmentStatement = $this->pdo->prepare(
            'SELECT *
             FROM ticket_attachments
             WHERE ticket_id = :ticket_id
             ORDER BY datetime(created_at) ASC, id ASC'
        );
        $attachmentStatement->execute([':ticket_id' => $ticketId]);

        $attachmentsByMessage = [];
        foreach ($attachmentStatement->fetchAll(PDO::FETCH_ASSOC) as $attachment) {
            $messageId = (int) ($attachment['message_id'] ?? 0);
            $attachmentsByMessage[$messageId][] = $attachment;
        }

        foreach ($messages as &$message) {
            $messageId = (int) $message['id'];
            $message['attachments'] = $attachmentsByMessage[$messageId] ?? [];
            $message['is_ghost'] = !empty($message['is_ghost']);
        }
        unset($message);

        return $messages;
    }

    public function isGhostMessage(int $messageId): bool
    {
        if ($messageId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT COALESCE(is_ghost, 0)
             FROM ticket_messages
             WHERE id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $messageId]);
        $value = $statement->fetchColumn();

        return (int) $value === 1;
    }

    public function isGhostAttachment(int $attachmentId): bool
    {
        if ($attachmentId <= 0) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'SELECT COALESCE(tm.is_ghost, 0)
             FROM ticket_attachments ta
             LEFT JOIN ticket_messages tm ON tm.id = ta.message_id
             WHERE ta.id = :id
             LIMIT 1'
        );
        $statement->execute([':id' => $attachmentId]);
        $value = $statement->fetchColumn();

        return (int) $value === 1;
    }

    private function normalizeParticipantEmails(array $participantEmails, string $primaryEmail = ''): array
    {
        $normalized = [];

        $primaryEmail = strtolower(trim($primaryEmail));
        if ($primaryEmail !== '' && filter_var($primaryEmail, FILTER_VALIDATE_EMAIL)) {
            $normalized[$primaryEmail] = $primaryEmail;
        }

        foreach ($participantEmails as $participantEmail) {
            $email = strtolower(trim((string) $participantEmail));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $normalized[$email] = $email;
        }

        return array_values($normalized);
    }

    private function getTicketRequesterEmail(int $ticketId): string
    {
        $statement = $this->pdo->prepare('SELECT user_email FROM tickets WHERE id = :id LIMIT 1');
        $statement->execute([':id' => $ticketId]);

        return strtolower(trim((string) ($statement->fetchColumn() ?: '')));
    }

    private function touchTicketUpdatedAt(int $ticketId, ?string $updatedAt = null): void
    {
        $statement = $this->pdo->prepare('UPDATE tickets SET updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            ':updated_at' => $updatedAt ?? date('c'),
            ':id' => $ticketId,
        ]);
    }

    private function storeAttachments(int $ticketId, int $messageId, array $files, string $uploadedByEmail): void
    {
        if ($files === []) {
            return;
        }

        if (!is_dir($this->uploadRoot) && !mkdir($this->uploadRoot, 0775, true) && !is_dir($this->uploadRoot)) {
            throw new RuntimeException('De upload-map kon niet worden aangemaakt.');
        }

        $ticketDirectory = $this->uploadRoot . DIRECTORY_SEPARATOR . $ticketId;
        if (!is_dir($ticketDirectory) && !mkdir($ticketDirectory, 0775, true) && !is_dir($ticketDirectory)) {
            throw new RuntimeException('De ticketmap voor bijlagen kon niet worden aangemaakt.');
        }

        $finfo = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
        $statement = $this->pdo->prepare(
            'INSERT INTO ticket_attachments (
                ticket_id,
                message_id,
                original_name,
                stored_name,
                stored_path,
                mime_type,
                file_size,
                uploaded_by_email,
                created_at
             ) VALUES (
                :ticket_id,
                :message_id,
                :original_name,
                :stored_name,
                :stored_path,
                :mime_type,
                :file_size,
                :uploaded_by_email,
                :created_at
             )'
        );

        foreach ($files as $file) {
            $tmpName = $file['tmp_name'] ?? '';
            $originalName = trim((string) ($file['name'] ?? 'bestand'));
            $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
            $storedName = uniqid('ticket_', true) . ($extension !== '' ? '.' . $extension : '');
            $storedPath = $ticketDirectory . DIRECTORY_SEPARATOR . $storedName;

            $moved = is_uploaded_file($tmpName)
                ? move_uploaded_file($tmpName, $storedPath)
                : @rename($tmpName, $storedPath);

            if (!$moved) {
                throw new RuntimeException('Een bijlage kon niet worden opgeslagen.');
            }

            $mimeType = $finfo instanceof finfo ? (string) $finfo->file($storedPath) : ((string) ($file['type'] ?? 'application/octet-stream'));

            $statement->execute([
                ':ticket_id' => $ticketId,
                ':message_id' => $messageId,
                ':original_name' => $originalName !== '' ? $originalName : 'bestand',
                ':stored_name' => $storedName,
                ':stored_path' => $storedPath,
                ':mime_type' => $mimeType,
                ':file_size' => (int) ($file['size'] ?? 0),
                ':uploaded_by_email' => strtolower(trim($uploadedByEmail)),
                ':created_at' => date('c'),
            ]);
        }
    }
}
