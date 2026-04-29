<?php

class TicketStore
{
    private const REQUESTER_WAIT_STATUSES = [
        'ingediend',
        'in behandeling',
        'afwachtende op bestelling',
    ];

    private const REQUESTER_RESPONSE_STATUS = 'afwachtende op gebruiker';

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
                    SUM(CASE WHEN status = 'afwachtende op bestelling' THEN 1 ELSE 0 END) AS waiting_order_tickets
             FROM tickets"
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_tickets' => (int) ($row['total_tickets'] ?? 0),
            'open_tickets' => (int) ($row['open_tickets'] ?? 0),
            'resolved_tickets' => (int) ($row['resolved_tickets'] ?? 0),
            'waiting_order_tickets' => (int) ($row['waiting_order_tickets'] ?? 0),
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

    public function createTicket(string $title, string $category, string $userEmail, string $description, array $files = [], int $priority = 0): array
    {
        $userEmail = strtolower(trim($userEmail));
        $priority = max(0, min(2, $priority));
        $assignee = $this->pickAssignee($category);
        $now = date('c');

        $this->pdo->beginTransaction();

        try {
            $ticketStatement = $this->pdo->prepare(
                'INSERT INTO tickets (title, category, user_email, assigned_email, status, description, priority, created_at, updated_at)
                 VALUES (:title, :category, :user_email, :assigned_email, :status, :description, :priority, :created_at, :updated_at)'
            );
            $ticketStatement->execute([
                ':title' => $title,
                ':category' => $category,
                ':user_email' => $userEmail,
                ':assigned_email' => $assignee,
                ':status' => 'ingediend',
                ':description' => $description,
                ':priority' => $priority,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $ticketId = (int) $this->pdo->lastInsertId();

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

    public function updateTicket(int $ticketId, string $status, ?string $assignedEmail, int $priority = 0): void
    {
        $currentTicket = $this->pdo->prepare('SELECT status FROM tickets WHERE id = :id LIMIT 1');
        $currentTicket->execute([':id' => $ticketId]);
        $currentStatus = strtolower((string) ($currentTicket->fetchColumn() ?: ''));
        $statusChanged = $currentStatus !== strtolower($status);

        $updatedAt = date('c');
        $priority = max(0, min(2, $priority));

        $this->pdo->beginTransaction();

        try {
            $statement = $this->pdo->prepare(
                "UPDATE tickets
                 SET status = :status,
                     assigned_email = :assigned_email,
                     priority = :priority,
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

    public function addMessage(int $ticketId, string $senderEmail, string $senderRole, string $messageText, array $files = []): int
    {
        $now = date('c');
        $statement = $this->pdo->prepare(
            'INSERT INTO ticket_messages (ticket_id, sender_email, sender_role, message_text, created_at)
             VALUES (:ticket_id, :sender_email, :sender_role, :message_text, :created_at)'
        );
        $statement->execute([
            ':ticket_id' => $ticketId,
            ':sender_email' => strtolower(trim($senderEmail)),
            ':sender_role' => $senderRole,
            ':message_text' => $messageText,
            ':created_at' => $now,
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

    public function getTickets(bool $isAdmin, string $userEmail, array $statusFilters = [], ?string $assignedFilter = null, array $categoryFilters = []): array
    {
        $conditions = [];
        $parameters = [];

        if (!$isAdmin) {
            $conditions[] = 't.user_email = :user_email';
            $parameters[':user_email'] = strtolower(trim($userEmail));
        }

        if ($statusFilters !== []) {
            $statusPlaceholders = [];
            foreach ($statusFilters as $index => $status) {
                $placeholder = ':status_' . $index;
                $statusPlaceholders[] = $placeholder;
                $parameters[$placeholder] = $status;
            }
            $conditions[] = 't.status IN (' . implode(', ', $statusPlaceholders) . ')';
        }

        if ($isAdmin && $assignedFilter !== null && $assignedFilter !== '') {
            if ($assignedFilter === '__unassigned__') {
                $conditions[] = '(t.assigned_email IS NULL OR t.assigned_email = "")';
            } else {
                $conditions[] = 't.assigned_email = :assigned_email';
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
            $conditions[] = 't.category IN (' . implode(', ', $categoryPlaceholders) . ')';
        }

        $sql = 'SELECT t.*,
                       (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) AS message_count,
                       (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) AS attachment_count
                FROM tickets t';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if ($isAdmin) {
            $sql .= " ORDER BY CASE WHEN t.status = 'afgehandeld' THEN 1 ELSE 0 END ASC,
                            CASE WHEN t.status = 'afgehandeld' THEN NULL ELSE COALESCE(t.priority, 0) END DESC,
                            datetime(t.created_at) DESC,
                            t.id DESC";
        } else {
            $sql .= ' ORDER BY datetime(t.updated_at) DESC, datetime(t.created_at) DESC, t.id DESC';
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTicket(int $ticketId, bool $isAdmin, string $userEmail): ?array
    {
        $conditions = ['id = :id'];
        $parameters = [':id' => $ticketId];

        if (!$isAdmin) {
            $conditions[] = 'user_email = :user_email';
            $parameters[':user_email'] = strtolower(trim($userEmail));
        }

        $statement = $this->pdo->prepare('SELECT * FROM tickets WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1');
        $statement->execute($parameters);

        $ticket = $statement->fetch(PDO::FETCH_ASSOC);
        if ($ticket === false) {
            return null;
        }

        $ticket['messages'] = $this->getMessagesForTicket((int) $ticket['id']);

        return $ticket;
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
        $this->pdo->exec('PRAGMA foreign_keys = ON');
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

        $this->ensureColumn('tickets', 'title', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'assigned_email', 'TEXT DEFAULT NULL');
        $this->ensureColumn('tickets', 'status', 'TEXT NOT NULL DEFAULT "ingediend"');
        $this->ensureColumn('tickets', 'description', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'priority', 'INTEGER NOT NULL DEFAULT 0');
        $this->ensureColumn('tickets', 'created_at', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'updated_at', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'resolved_at', 'TEXT DEFAULT NULL');
        $this->ensureColumn('ticket_messages', 'message_text', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_attachments', 'mime_type', 'TEXT DEFAULT NULL');
        $this->ensureColumn('ticket_attachments', 'file_size', 'INTEGER NOT NULL DEFAULT 0');

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_user_email ON tickets(user_email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_assigned_email ON tickets(assigned_email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_priority ON tickets(priority)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_messages_ticket_id ON ticket_messages(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_attachments_ticket_id ON ticket_attachments(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_status_transitions_ticket_id ON ticket_status_transitions(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_status_transitions_ticket_status ON ticket_status_transitions(ticket_id, status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_browser_notifications_user_delivered ON browser_notifications(user_email, delivered_at, created_at)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_web_push_subscriptions_user_email ON web_push_subscriptions(user_email)');
        $this->pdo->exec(
            "UPDATE tickets
             SET resolved_at = COALESCE(NULLIF(resolved_at, ''), NULLIF(updated_at, ''))
             WHERE status = 'afgehandeld'
               AND (resolved_at IS NULL OR resolved_at = '')"
        );

        $this->seedStatusTransitionHistory();

        $this->syncIctUsers();
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

    private function getMessagesForTicket(int $ticketId): array
    {
        $messageStatement = $this->pdo->prepare(
            'SELECT *
             FROM ticket_messages
             WHERE ticket_id = :ticket_id
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
        }
        unset($message);

        return $messages;
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
