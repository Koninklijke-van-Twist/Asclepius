<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'logincheck.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'helpers.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'user_avatars.php';

if (!isset($ictUserColors) || !is_array($ictUserColors)) {
    $ictUserColors = [];
}
if (!isset($ictUsers) || !is_array($ictUsers)) {
    $ictUsers = [];
}
normalizeIctUsersConfig($ictUsers, $ictUserColors);
$GLOBALS['ictUserColors'] = $ictUserColors;

$email = strtolower(trim((string) ($_GET['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit;
}

if (!asclepius_ensure_user_avatar($email)) {
    http_response_code(500);
    exit;
}

$path = asclepius_user_avatar_path($email);
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=31536000, immutable');
readfile($path);
