<?php

const DATABASE_FILE = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'asclepius.sqlite';
const UPLOAD_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ticket_uploads';
const MAX_ATTACHMENT_BYTES = 20971520;
const LONG_OPEN_NOTIFICATION_FALLBACK_DAYS = 7;
const DEFAULT_TICKETS_PER_PAGE = 20;
const TICKETS_PER_PAGE_OPTIONS = [5, 10, 15, 20, 25, 30, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100];
const TEMPLATE_TICKET_CATEGORY = 'Laptop Klaarmaken';
const TEMPLATE_TICKET_CATEGORIES = [
    TEMPLATE_TICKET_CATEGORY,
    'Telefoon Klaarmaken',
];
const TICKET_CATEGORIES = [
    'hardware bestellen',
    'software bestellen',
    'Printerproblemen',
    'licentie aanvragen',
    'Business Central',
    'Hardwareproblemen',
    'Softwareproblemen',
    'MagazijnApp',
    'ServiceApp',
    'sleutels.kvt.nl web-applicatieproblemen',
    TEMPLATE_TICKET_CATEGORY,
    'Telefoon Klaarmaken',
    'Anders',
];
const TICKET_STATUSES = [
    'ingediend',
    'in behandeling',
    'afwachtende op gebruiker',
    'afwachtende op bestelling',
    'afwachtende op derde partij',
    'afgehandeld',
];
const CUSTOM_TICKET_STATUS_MAX_LENGTH = 40;
const STATUS_COLORS = [
    'ingediend' => '#2563eb',
    'in behandeling' => '#d97706',
    'afwachtende op gebruiker' => '#7c3aed',
    'afwachtende op bestelling' => '#b45309',
    'afwachtende op derde partij' => '#0d9488',
    'afgehandeld' => '#15803d',
];
const ADMIN_EMAIL_NOTIFICATION_TYPES = [
    'new_ticket',
    'assigned',
    'user_reply',
    'escalation',
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
    'Printerproblemen' => '#00aaaa',
    'licentie aanvragen' => '#2ba512',
    'Business Central' => '#7c3aed',
    'Hardwareproblemen' => '#dc2626',
    'Softwareproblemen' => '#eaa40c',
    'MagazijnApp' => '#be478d',
    'ServiceApp' => '#045db1',
    'sleutels.kvt.nl web-applicatieproblemen' => '#b2087f',
    'Anders' => '#475569',
    TEMPLATE_TICKET_CATEGORY => '#0f766e',
    'Telefoon Klaarmaken' => '#0369a1',
];

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Europe/Amsterdam');
}

date_default_timezone_set(APP_TIMEZONE);
