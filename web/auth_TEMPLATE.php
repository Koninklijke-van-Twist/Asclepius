<?php
/**
 * Fragment voor web/auth.php van Asclepius. Dit bestand vervangt auth.php niet.
 *
 * Zet $mimirApi én de Business Central-credentials naast elkaar.
 * Met $mimirApi proberen fetches eerst Mímir en vallen terug op het BC-blok hieronder.
 * Zonder $mimirApi wordt alleen het BC-blok gebruikt.
 *
 * Graph, mail, web-push, API-keys, Grok en ict-gebruikers blijven in de echte auth.php
 * op de server staan. auth.php wordt niet gecommit.
 */

// --- Mímir (aanbevolen) ---
// $mimirApi  = 'mimir_…';
// $mimirBase = 'https://sleutels.kvt.nl/mimir/api';

// --- Business Central (direct pad, én fallback als Mímir faalt) ---
// $auth_list = [
//     'Production' => ['mode' => 'basic', 'user' => 'USERNAME', 'pass' => 'PASSWORD'],
// ];
// $environment = 'Production';
// $auth = $auth_list[$environment];
// $baseUrl = 'https://my-bc-domain.com:7148/';
