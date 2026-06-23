<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$notifyPath = __DIR__ . DIRECTORY_SEPARATOR . 'update.notify';
$completePath = __DIR__ . DIRECTORY_SEPARATOR . 'update.complete';

$notifyExists = is_file($notifyPath);
$completeExists = is_file($completePath);

echo json_encode([
    'notify' => $notifyExists,
    'complete' => $completeExists,
    'notify_started_at' => $notifyExists ? (int) filemtime($notifyPath) * 1000 : null,
], JSON_UNESCAPED_UNICODE);
