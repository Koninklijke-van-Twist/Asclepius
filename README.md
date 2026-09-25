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

Met `$mimirApi` gezet zijn `$auth_list`, `$environment`, `$baseUrl` en `$auth` ongebruikt voor Business Central — company-discovery helpers en alle OData-fetches via `odata_get_all` lopen via Mímir. Zonder `$mimirApi` blijft het bestaande directe BC-pad + lokale filecache ongewijzigd.

Asclepius mix tickets / Magnum / Grok / Graph met een gedeelde `odata.php`. Alleen OData/discovery wordt via Mímir gerouteerd; ticket-, webhook- en Graph-paden blijven onaangetast.

**max_age-beleid**

| Soort fetch | `max_age` naar Mímir |
| --- | --- |
| `nightly.php` | doet géén BC-OData vandaag — constant `ASCLEPIUS_NIGHTLY_MAX_AGE` (**14400**, 4u) gereserveerd in `odata.php` |
| `hourly.php` | doet géén BC-OData vandaag — constant `ASCLEPIUS_HOURLY_MAX_AGE` (**1800**, ≤30 min) gereserveerd in `odata.php` |
| UI / on-demand | bestaande `odata_get_all`-TTL (default **300** s) |

Tim moet `$mimirApi` (en optioneel `$mimirBase`) lokaal/op de server zetten; `auth.php` wordt niet gecommit. Zie [Mímir Implementatie](https://wiki.kvt.nl/books/mimir/page/implementatie).

## auth.php

Geen `auth.php` in deze repository (staat in `.gitignore`). Lokaal/op de server de Mímir-sleutel zetten zoals hierboven; legacy BC-credentials alleen nodig zonder `$mimirApi`. Graph-credentials (`$graphCredentials`), mail, web-push, API-keys en Grok blijven zoals voorheen.

## Tests

```bash
php tests/run-tests.php
```
