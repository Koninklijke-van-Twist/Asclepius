<?php

const DATABASE_FILE = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'asclepius.sqlite';
const UPLOAD_DIRECTORY = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'ticket_uploads';
const MAX_ATTACHMENT_BYTES = 20971520;
const LONG_OPEN_NOTIFICATION_FALLBACK_DAYS = 7;
const TICKET_CATEGORIES = [
    'hardware bestellen',
    'software bestellen',
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
const CATEGORY_COLORS = [
    'hardware bestellen' => '#0f766e',
    'software bestellen' => '#1d4ed8',
    'Business Central' => '#7c3aed',
    'Hardwareproblemen' => '#dc2626',
    'Softwareproblemen' => '#ea580c',
    'sleutels.kvt.nl web-applicatieproblemen' => '#0891b2',
    'Anders' => '#475569',
];
