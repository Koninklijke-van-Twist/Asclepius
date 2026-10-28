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

Die fallback hoort in `web/odata.php`. Tim Falken heeft op 2026-09-28 goedgekeurd dat dit bestand daarvoor gewijzigd mag worden; verder blijft de richtlijn om `odata.php` niet aan te passen.

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

## Vertaling (Copilot-flow, optioneel)

Zet in `web/auth.php` (niet in git), naast de bestaande `$laraTranslate`:

```php
$copilotTranslate = [
    'flowUrl' => 'https://…',
    'timeout' => 120, // optioneel, seconden; standaard 120
];
```

Staat `flowUrl` op een `https`-adres, dan gaat ticketvertaling via die Power Automate/Copilot-flow. Ontbreekt de instelling of is het adres ongeldig, dan blijft Lara de vertaaldienst. Zie `web/auth_TEMPLATE.php`.

Een nieuw ticket of nieuw bericht start de vertaling meteen nadat het antwoord naar de browser is, voor alle ondersteunde talen (`nl`, `en`, `fr`, `de`). Plaatsen wacht daar niet op. De flow krijgt die talen in één verzoek en antwoordt met één JSON-object (`source` optioneel, plus een string per taal). Alles uit dat antwoord wordt bewaard. Lara blijft één taal per aanroep doen. Bestaande berichten worden niet achteraf allemaal vertaald; `translate_ticket` blijft het vangnet. Een gewijzigde titel of berichttekst gooit de oude vertalingen weg en vraagt ze opnieuw.

## Grok-webhooks

De centrale bot staat in `web/auth.php` als `$grokBot` (niet in git). Die webhook wordt bij elke ticketgebeurtenis (`new-ticket`, `ticket-solved`, `user-reply`, `ticket-reopened`, `re-evaluate-ticket-and-advise`) aangeroepen (behalve in het geval hieronder), met `sender_email`, `default_name` en `default_title` uit die config.

Daarnaast kan elke beheerder onder **Voorkeuren** een eigen webhook-URL en verzendsleutel zetten. Die staat in de gebruikersvoorkeuren (`web/data/user_prefs/`, niet in git), niet in `auth.php`. Dezelfde gebeurtenis gaat óók naar die persoonlijke webhook wanneer:

- het ticket aan die gebruiker is toegewezen, of
- die gebruiker AI-advies aanvraagt.

Is dat dezelfde URL én dezelfde sleutel als de centrale webhook, dan volgt geen tweede aanroep.

Onder de persoonlijke webhook staat het vinkje *Nog steeds additioneel de standaard Grok-bot webhook gebruiken naast mijn eigen webhook* (`also_default` in `grok_webhook`). Het kan alleen gezet worden met een geldige webhook en verzendsleutel; zonder eigen webhook geldt altijd de standaard-bot. Staat het uit (standaard bij een eigen webhook, ook voor bestaande webhooks zonder opgeslagen keuze), dan wordt de centrale webhook overgeslagen voor tickets die aan die gebruiker zijn toegewezen, mits de persoonlijke URL vlak voor verzending nog geldig is. Bij **AI-advies** (`re-evaluate-ticket-and-advise`) beslist alleen het vinkje van de **aanvrager**: heeft die een geldige eigen webhook met het vinkje uit, dan gaat het advies naar die eigen bot en niet naar de centrale; met het vinkje aan naar beide; zonder (geldige) eigen webhook naar de centrale. Het vinkje van de behandelaar telt bij AI-advies niet. De persoonlijke webhooks van behandelaar en aanvrager krijgen het advies zoals voorheen (dezelfde persoon één keer). Bij alle andere gebeurtenissen telt alleen de behandelaar; tickets zonder behandelaar (of met een behandelaar zonder eigen webhook) houden het huidige gedrag. De api-key van de persoonlijke webhook gebruikt het e-mailadres en de naam van die gebruiker, met titel `Assistent`. De bot mag naam en titel in `add_ticket_message` nog steeds zelf zetten.

Zowel de centrale URL als een persoonlijke URL moet `http` of `https` zijn. Een host die naar loopback, een privénetwerk, link-local, CGNAT of een metadata-adres wijst, wordt geweigerd bij het opslaan en opnieuw vlak voor verzending. Een redirect wordt niet gevolgd. Zie `web/docs/api.md`.

## Tests

```bash
php tests/run-tests.php
```
