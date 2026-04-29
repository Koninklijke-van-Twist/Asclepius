<?php

const DATABASE_FILE = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'asclepius.sqlite';
const UPLOAD_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ticket_uploads';
const MAX_ATTACHMENT_BYTES = 20971520;
const LONG_OPEN_NOTIFICATION_FALLBACK_DAYS = 7;
const TICKET_CATEGORIES = [
    'hardware bestellen',
    'software bestellen',
    'licentie aanvragen',
    'Business Central',
    'Hardwareproblemen',
    'Softwareproblemen',
    'sleutels.kvt.nl web-applicatieproblemen',
    'Anders',
];
const TICKET_STATUSES = [
    'ingediend',
    'in behandeling',
    'afwachtende op gebruiker',
    'afwachtende op bestelling',
    'afgehandeld',
];
const STATUS_COLORS = [
    'ingediend' => '#2563eb',
    'in behandeling' => '#d97706',
    'afwachtende op gebruiker' => '#7c3aed',
    'afwachtende op bestelling' => '#b45309',
    'afgehandeld' => '#15803d',
];
const PRIORITY_LABELS = [
    0 => 'Normaal',
    1 => 'Belemmerd',
    2 => 'Geblokkeerd',
];
const PRIORITY_COLORS = [
    0 => '#0f766e',
    1 => '#d97706',
    2 => '#b91c1c',
];
if (!defined('WEB_PUSH_VAPID_PUBLIC_KEY')) {
    $configuredWebPushPublicKey = '';
    if (isset($webPushSettings) && is_array($webPushSettings)) {
        $configuredWebPushPublicKey = (string) ($webPushSettings['vapid_public_key'] ?? '');
    }

    define('WEB_PUSH_VAPID_PUBLIC_KEY', (string) (
        $configuredWebPushPublicKey
        ?: (
            $_ENV['ASCLEPIUS_WEB_PUSH_VAPID_PUBLIC_KEY']
            ?? $_SERVER['ASCLEPIUS_WEB_PUSH_VAPID_PUBLIC_KEY']
            ?? ''
        )
    ));
}
if (!defined('WEB_PUSH_VAPID_PRIVATE_PEM')) {
    $configuredWebPushPrivatePem = '';
    if (isset($webPushSettings) && is_array($webPushSettings)) {
        $configuredWebPushPrivatePem = (string) ($webPushSettings['vapid_private_pem'] ?? '');
    }

    define('WEB_PUSH_VAPID_PRIVATE_PEM', (string) (
        $configuredWebPushPrivatePem
        ?: (
            $_ENV['ASCLEPIUS_WEB_PUSH_VAPID_PRIVATE_PEM']
            ?? $_SERVER['ASCLEPIUS_WEB_PUSH_VAPID_PRIVATE_PEM']
            ?? ''
        )
    ));
}
if (!defined('WEB_PUSH_SUBJECT')) {
    $configuredWebPushSubject = '';
    if (isset($webPushSettings) && is_array($webPushSettings)) {
        $configuredWebPushSubject = (string) ($webPushSettings['subject'] ?? '');
    }

    define('WEB_PUSH_SUBJECT', (string) (
        $configuredWebPushSubject
        ?: (
            $_ENV['ASCLEPIUS_WEB_PUSH_SUBJECT']
            ?? $_SERVER['ASCLEPIUS_WEB_PUSH_SUBJECT']
            ?? 'mailto:ict@kvt.nl'
        )
    ));
}
const CATEGORY_COLORS = [
    'hardware bestellen' => '#0f766e',
    'software bestellen' => '#1d4ed8',
    'licentie aanvragen' => '#2ba512',
    'Business Central' => '#7c3aed',
    'Hardwareproblemen' => '#dc2626',
    'Softwareproblemen' => '#eaa40c',
    'sleutels.kvt.nl web-applicatieproblemen' => '#b2087f',
    'Anders' => '#475569',
];
