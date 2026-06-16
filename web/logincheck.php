<?php

const DEBUG_EVERYONE_IS_ADMIN = false;

function is_trusted_requester(): bool
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $server = $_SERVER['SERVER_ADDR'] ?? '';
    $trusted = ['127.0.0.1', '::1'];
    if ($remote === $server && $remote !== '') {
        return true;
    }
    if (in_array($remote, $trusted, true)) {
        return true;
    }
    return false;
}

if (!is_trusted_requester()) {
    if (defined('ASCLEPIUS_SESSION_KEEPALIVE') && ASCLEPIUS_SESSION_KEEPALIVE) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
            ]);
        }

        if (empty($_SESSION['user'])) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'reason' => 'session_expired'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        require __DIR__ . "/../login/lib.php";
    }

    $_SESSION['user']['admin'] = false;

    if (
        DEBUG_EVERYONE_IS_ADMIN ||
        array_any($ictUsers, function ($email) {
            return strtolower((string) $email) === strtolower((string) ($_SESSION['user']['email'] ?? ''));
        })
    ) {
        $_SESSION['user']['admin'] = true;
    }
}