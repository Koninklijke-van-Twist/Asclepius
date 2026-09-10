<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'logincheck.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'TicketStore.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . 'constants.php';

if (!isset($ictUsers) || !is_array($ictUsers)) {
    $ictUsers = [];
}

try {
    $store = new TicketStore(DATABASE_FILE, UPLOAD_DIRECTORY, $ictUsers, TICKET_CATEGORIES);
    $store->ensureTheevraagjeImage();
} catch (Throwable) {
    http_response_code(500);
    exit;
}

$path = THEEVRAAGJE_IMAGE_FILE;
if (!is_file($path) || filesize($path) <= 0) {
    http_response_code(404);
    exit;
}

$mtime = (int) filemtime($path);
header('Content-Type: image/png');
header('Cache-Control: private, max-age=300');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
readfile($path);
