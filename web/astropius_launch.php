<?php
/**
 * Hand off an Asclepius login to Astropius so the preview uses live TicketStore data.
 * Sets a short-lived Path=/ cookie the Astro UI can send as X-API-Key.
 */
declare(strict_types=1);

require_once __DIR__ . '/content/bootstrap.php';

$user = is_array($_SESSION['user'] ?? null) ? $_SESSION['user'] : [];
$apiKey = strtolower(trim((string) ($user['api_key'] ?? '')));
$email = strtolower(trim((string) ($user['email'] ?? '')));

if ($apiKey === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.php');
    exit;
}

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');

setcookie('asclepius_astropius_key', $apiKey, [
    'expires' => time() + 12 * 60 * 60,
    'path' => '/',
    'secure' => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
]);

setcookie('asclepius_astropius_email', $email, [
    'expires' => time() + 12 * 60 * 60,
    'path' => '/',
    'secure' => $secure,
    'httponly' => false,
    'samesite' => 'Lax',
]);

$target = '/astropius/';
$query = [];
if (!empty($_GET['view'])) {
    $query['view'] = (string) $_GET['view'];
}
if ($query !== []) {
    $target .= '?' . http_build_query($query);
}

header('Location: ' . $target);
exit;
