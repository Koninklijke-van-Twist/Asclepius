# Asclepius

Intern ticketingssysteem voor KVT op sleutels.kvt.nl/asclepius (meldingen, aanvragen, Grok/Magnum-webhooks, Graph-users).

## Structuur

- `web/` — app (tickets UI, API, webhooks, Graph user directory)
- `web/odata.php` — OData-client + lokale filecache-widget + optionele Mímir-proxy
- `web/nightly.php` — theevraagje-onderhoud (geen BC-OData)
- `web/hourly.php` — open-ticket trend snapshots (geen BC-OData)
- `web/auth.php` — credentials (niet in git)

## Mímir (optioneel)

Zet in `web/auth.php` (niet in git):

```php
$mimirApi  = 'mimir_…';
// optioneel:
$mimirBase = 'https://sleutels.kvt.nl/mimir/api';
```

Met `$mimirApi` gezet proberen company-discovery en alle OData-fetches via `odata_get_all` eerst Mímir. Faalt die aanroep (verbinding/timeout, non-2xx, ongeldige JSON of een Mímir-foutpayload), dan haalt Asclepius dezelfde gegevens op via het oude Business Central-pad (`$baseUrl`, `$auth` / `$auth_list`, `$environment`, lokale odata-filecache) en slaat Mímir voor de rest van dat PHP-verzoek over. Laat die BC-credentials in `auth.php` naast `$mimirApi` staan; ontbreken ze, dan gaat de oorspronkelijke Mímir-fout door. Zonder `$mimirApi` blijft het bestaande directe BC-pad + lokale filecache ongewijzigd.

Asclepius mixt tickets / Magnum / Grok / Graph met een gedeelde `odata.php`. Alleen OData/discovery loopt via Mímir (met directe BC-fallback); ticket-, webhook- en Graph-paden blijven onaangetast. Live pagina's (`index.php` / `admin.php` via `content/bootstrap.php`, `api.php`) én cron-scripts (`nightly.php`, `hourly.php`) laden `auth.php` volledig, ook als `$mimirApi` gezet is, zodat de fallback de BC-credentials heeft. Een CLI-run (`php nightly.php`, `PHP_SAPI=cli`) houdt de lange Mímir-timeout; webverzoeken gebruiken een kortere.

**max_age-beleid**

| Soort fetch | `max_age` naar Mímir |
| --- | --- |
| `nightly.php` | doet géén BC-OData vandaag — constant `ASCLEPIUS_NIGHTLY_MAX_AGE` (**14400**, 4u) gereserveerd in `odata.php` |
| `hourly.php` | doet géén BC-OData vandaag — constant `ASCLEPIUS_HOURLY_MAX_AGE` (**1800**, ≤30 min) gereserveerd in `odata.php` |
| UI / on-demand | bestaande `odata_get_all`-TTL (default **300** s) |

Tim moet `$mimirApi` (en optioneel `$mimirBase`) lokaal/op de server zetten, mét de BC-credentials ernaast; `auth.php` wordt niet gecommit. Zie [Mímir Implementatie](https://wiki.kvt.nl/books/mimir/page/implementatie) en `web/auth_TEMPLATE.php`.

## auth.php

Geen `auth.php` in deze repository (staat in `.gitignore`). Lokaal/op de server de Mímir-sleutel zetten zoals hierboven, en `$baseUrl`, `$auth` / `$auth_list` en `$environment` daarnaast laten staan voor de directe BC-fallback. Graph-credentials (`$graphCredentials`), mail, web-push, API-keys en Grok blijven zoals voorheen.

## Tests

```bash
php tests/run-tests.php
```
