<?php

declare(strict_types=1);

class TicketStore
{
    private PDO $pdo;
    private string $databasePath;
    private string $uploadRoot;
    private array $ictUsers;
    private array $categories;

    public function __construct(string $databasePath, string $uploadRoot, array $ictUsers, array $categories)
    {
        $this->databasePath = $databasePath;
        $this->uploadRoot = $uploadRoot;
        $this->ictUsers = array_values(array_unique(array_map('strtolower', $ictUsers)));
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

    public function saveCategoryMatrix(array $matrix): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ict_user_category_settings (user_email, category, is_enabled)
             VALUES (:user_email, :category, :is_enabled)
             ON CONFLICT(user_email, category) DO UPDATE SET is_enabled = excluded.is_enabled'
        );

        foreach ($this->ictUsers as $ictUser) {
            foreach ($this->categories as $category) {
                $statement->execute([
                    ':user_email' => $ictUser,
                    ':category' => $category,
                    ':is_enabled' => !empty($matrix[$ictUser][$category]) ? 1 : 0,
                ]);
            }
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

    public function createTicket(string $title, string $category, string $userEmail, string $description, array $files = []): array
    {
        $userEmail = strtolower(trim($userEmail));
        $assignee = $this->pickAssignee($category);
        $now = date('c');

        $this->pdo->beginTransaction();

        try {
            $ticketStatement = $this->pdo->prepare(
                'INSERT INTO tickets (title, category, user_email, assigned_email, status, description, created_at, updated_at)
                 VALUES (:title, :category, :user_email, :assigned_email, :status, :description, :created_at, :updated_at)'
            );
            $ticketStatement->execute([
                ':title' => $title,
                ':category' => $category,
                ':user_email' => $userEmail,
                ':assigned_email' => $assignee,
                ':status' => 'ingediend',
                ':description' => $description,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $ticketId = (int) $this->pdo->lastInsertId();

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

    public function updateTicket(int $ticketId, string $status, ?string $assignedEmail): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE tickets
             SET status = :status,
                 assigned_email = :assigned_email,
                 updated_at = :updated_at
             WHERE id = :id'
        );
        $statement->execute([
            ':status' => $status,
            ':assigned_email' => $assignedEmail,
            ':updated_at' => date('c'),
            ':id' => $ticketId,
        ]);
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

    public function getTickets(bool $isAdmin, string $userEmail, array $statusFilters = [], ?string $assignedFilter = null): array
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

        $sql = 'SELECT t.*,
                       (SELECT COUNT(*) FROM ticket_messages tm WHERE tm.ticket_id = t.id) AS message_count,
                       (SELECT COUNT(*) FROM ticket_attachments ta WHERE ta.ticket_id = t.id) AS attachment_count
                FROM tickets t';

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY datetime(t.updated_at) DESC, datetime(t.created_at) DESC, t.id DESC';

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

        $this->ensureColumn('tickets', 'title', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'assigned_email', 'TEXT DEFAULT NULL');
        $this->ensureColumn('tickets', 'status', 'TEXT NOT NULL DEFAULT "ingediend"');
        $this->ensureColumn('tickets', 'description', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'created_at', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('tickets', 'updated_at', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_messages', 'message_text', 'TEXT NOT NULL DEFAULT ""');
        $this->ensureColumn('ticket_attachments', 'mime_type', 'TEXT DEFAULT NULL');
        $this->ensureColumn('ticket_attachments', 'file_size', 'INTEGER NOT NULL DEFAULT 0');

        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_user_email ON tickets(user_email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_assigned_email ON tickets(assigned_email)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_tickets_status ON tickets(status)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_messages_ticket_id ON ticket_messages(ticket_id)');
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_ticket_attachments_ticket_id ON ticket_attachments(ticket_id)');

        $this->syncIctUsers();
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
        if ($this->ictUsers === [] || $this->categories === []) {
            return;
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO ict_user_category_settings (user_email, category, is_enabled)
             VALUES (:user_email, :category, 1)
             ON CONFLICT(user_email, category) DO NOTHING'
        );

        foreach ($this->ictUsers as $ictUser) {
            foreach ($this->categories as $category) {
                $statement->execute([
                    ':user_email' => $ictUser,
                    ':category' => $category,
                ]);
            }
        }
    }

    private function pickAssignee(string $category): ?string
    {
        $statement = $this->pdo->prepare(
            "SELECT settings.user_email,
                    COUNT(tickets.id) AS open_count
             FROM ict_user_category_settings settings
             LEFT JOIN tickets
                ON tickets.assigned_email = settings.user_email
               AND tickets.status <> 'afgehandeld'
             WHERE settings.category = :category
               AND settings.is_enabled = 1
             GROUP BY settings.user_email
             ORDER BY open_count ASC, settings.user_email ASC
             LIMIT 1"
        );
        $statement->execute([':category' => $category]);

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
