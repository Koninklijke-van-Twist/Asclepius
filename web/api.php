<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TicketStore.php';

/**
 * Constants
 */
const DATABASE_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'asclepius.sqlite';
const UPLOAD_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ticket_uploads';
const TICKET_CATEGORIES = [
    'hardware bestellen',
    'software bestellen',
    'Business Central',
    'Hardwareproblemen',
    'Softwareproblemen',
    'sleutels.kvt.nl web-applicatieproblemen',
    'Anders',
];

/**
 * Functions
 */
function sendJson(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getApiKeyFromRequest(): string
{
    $headerKey = trim((string) ($_SERVER['HTTP_X_API_KEY'] ?? ''));
    if ($headerKey !== '') {
        return $headerKey;
    }

    $requestKey = trim((string) ($_REQUEST['api_key'] ?? ''));
    if ($requestKey !== '') {
        return $requestKey;
    }

    return '';
}

function isValidApiKey(string $providedKey, array $apiKeys): bool
{
    if ($providedKey === '') {
        return false;
    }

    foreach ($apiKeys as $apiKey) {
        if (!is_string($apiKey)) {
            continue;
        }

        if (hash_equals($apiKey, $providedKey)) {
            return true;
        }
    }

    return false;
}

function getRequestBody(): array
{
    $contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));
    if (str_contains($contentType, 'application/json')) {
        $rawBody = file_get_contents('php://input');
        if (!is_string($rawBody) || trim($rawBody) === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

/**
 * Page load
 */
$providedApiKey = getApiKeyFromRequest();
if (!isValidApiKey($providedApiKey, $apiKeys ?? [])) {
    sendJson(401, [
        'success' => false,
        'error' => 'Ongeldige API-key.',
    ]);
}

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers ?? [], TICKET_CATEGORIES);
} catch (Throwable $exception) {
    sendJson(500, [
        'success' => false,
        'error' => 'Database kon niet worden geopend.',
        'details' => $exception->getMessage(),
    ]);
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $ticketId = max(0, (int) ($_GET['id'] ?? 0));

    if ($ticketId > 0) {
        $ticket = $store->getTicket($ticketId, true, '');
        if ($ticket === null) {
            sendJson(404, [
                'success' => false,
                'error' => 'Ticket niet gevonden.',
            ]);
        }

        sendJson(200, [
            'success' => true,
            'ticket' => $ticket,
        ]);
    }

    $tickets = $store->getTickets(true, '', [], null, []);
    sendJson(200, [
        'success' => true,
        'count' => count($tickets),
        'tickets' => $tickets,
    ]);
}

if ($method === 'POST') {
    $payload = getRequestBody();

    $title = trim((string) ($payload['title'] ?? ''));
    $category = trim((string) ($payload['category'] ?? ''));
    $description = trim((string) ($payload['description'] ?? ''));
    $userEmail = strtolower(trim((string) ($payload['user_email'] ?? '')));
    $priority = max(0, min(2, (int) ($payload['priority'] ?? 0)));

    if ($userEmail === '') {
        $userEmail = strtolower(trim((string) ($payload['requester_email'] ?? '')));
    }

    $errors = [];
    if ($title === '') {
        $errors[] = 'Titel is verplicht.';
    }
    if (!in_array($category, TICKET_CATEGORIES, true)) {
        $errors[] = 'Categorie is ongeldig.';
    }
    if ($description === '') {
        $errors[] = 'Beschrijving is verplicht.';
    }
    if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'user_email moet een geldig e-mailadres zijn.';
    }

    if ($errors !== []) {
        sendJson(422, [
            'success' => false,
            'errors' => $errors,
        ]);
    }

    try {
        $result = $store->createTicket($title, $category, $userEmail, $description, [], $priority);
        $ticketId = (int) ($result['ticket_id'] ?? 0);
        $ticket = $store->getTicket($ticketId, true, '');

        sendJson(201, [
            'success' => true,
            'ticket_id' => $ticketId,
            'assigned_email' => $result['assigned_email'] ?? null,
            'ticket' => $ticket,
        ]);
    } catch (Throwable $exception) {
        sendJson(500, [
            'success' => false,
            'error' => 'Ticket kon niet worden aangemaakt.',
            'details' => $exception->getMessage(),
        ]);
    }
}

sendJson(405, [
    'success' => false,
    'error' => 'Method niet toegestaan.',
]);
