# Asclepius API

HTTP-JSON API voor tickets. Endpoint: `api.php` (zelfde map als de webapp).

Deze pagina is de complete specificatie: elke actie die `api.php` kent, staat hier. Geen API-keys of andere secrets.

Machine-readable bron: `docs/api.md` (ook `index.php?view=api&raw=1`).

## Authenticatie

Elk verzoek heeft een geldige API-key nodig, tenzij het vanaf de server zelf komt (`REMOTE_ADDR` = `SERVER_ADDR`, of `127.0.0.1` / `::1`).

Geef de key mee via:

- header `X-API-Key: <key>`
- of query/body-parameter `api_key`

Drie soorten keys:

- **Service-key** — vast, in `auth.php` (`$apiKeys`). Bedoeld voor bots en integraties. Heeft geen sessie-e-mail; stuur `user_email` / `sender_email` / `viewer_email` mee waar een actor nodig is.
- **Sessie-key** — tijdelijke rotating key van de web-UI (hex, 64 tekens). Koppeling aan de ingelogde gebruiker (`email`, `is_admin`).
- **Webhook-key** — hex, 64 tekens, zit in de uitgaande ticket-webhook (`new-ticket` / `ticket-solved`). ICT-rechten, maximaal **1 uur** geldig, daarna `401`.

Bij een ongeldige key:

```json
{
  "success": false,
  "error": "Ongeldige API-key.",
  "reason": null
}
```

HTTP-status: `401`. `reason` kan `session_expired_refresh_required` zijn als een verlopen UI-sessiekey wordt herkend.

## Request-formaat

- **GET** — queryparameters
- **POST** — JSON (`Content-Type: application/json`) of form-data
- Acties via veld `action` (body of query). Lege/ontbrekende `action` op POST maakt een ticket aan.
- Onbekende niet-lege `action` → `422` met `"error": "unknown_action"`

Antwoorden zijn JSON met UTF-8.

Waar een gebruikers-e-mail in de response staat (`user_email`, `assigned_email`, `sender_email`, enz.), bevat het antwoord ook de bijbehorende weergavenaam (`user_name`, `assigned_name`, `sender_name`, …). Bij `participant_emails` staat daarnaast een `participants`-array met objecten `{ "email", "name" }`.

Boolean query/body-flags accepteren `1`, `true`, `yes`, `on` (en JSON `true`).

## Ticketobject

### Lijst (`GET api.php`)

Geen beschrijving en geen berichten. Velden o.a.:

- `id`, `title`, `category`, `user_email`, `assigned_email`, `status`, `priority` (`0`–`2`)
- `created_at`, `updated_at`, `due_date`, `resolved_at`, `is_private`
- `message_count`, `attachment_count`

### Detail (`GET api.php?id=`)

Alle ticketkolommen plus:

- `description`
- `participant_emails` — array van e-mailadressen
- `messages` — array, standaard **zonder** ghost-berichten

Berichtvelden o.a.: `id`, `ticket_id`, `sender_email`, `sender_role` (`admin` of `user`), `message_text`, `created_at`, `is_ghost`, `attachments`.

Optioneel, als de afzender ze gezet heeft (bots via `add_ticket_message`):

- `sender_display_name` / `sender_name` — weergavenaam in de thread (anders de naam bij het e-mailadres)
- `sender_role_title` / `sender_title` — blauwe functietitel naast de naam (anders `ICT` of `Gebruiker`)

`status` is een vaste status of een **eigen status** (vrije tekst).

## GET — tickets ophalen

### Alle tickets

`GET api.php`

Service-keys en trusted localhost zien alle tickets (admin-lijst).

```json
{
  "success": true,
  "count": 12,
  "tickets": [ ]
}
```

### Eén ticket

`GET api.php?id=123`

Optioneel: `include_ghosts=1` (alias `ghosts=1`) om ICT-only ghost-berichten mee te nemen.

```json
{
  "success": true,
  "ticket": { }
}
```

Niet gevonden → `404`.

## POST — ticket aanmaken

POST **zonder** `action` maakt een nieuw ticket.

Verplicht:

- `title` — string
- `category` — geldige categorie (zie onder)
- `description` — string
- `user_email` of `requester_email` — e-mail van de aanvrager

Optioneel:

- `priority` — `0` (normaal), `1` of `2`
- `participant_emails` — string (kommagescheiden) of array van e-mailadressen

Voorbeeld:

```json
{
  "title": "Printer werkt niet",
  "category": "Printerproblemen",
  "description": "Op de 2e verdieping geen afdruk.",
  "user_email": "naam@kvt.nl",
  "priority": 1
}
```

Succes → `201`:

```json
{
  "success": true,
  "ticket_id": 123,
  "assigned_email": "ict@kvt.nl",
  "ticket": { }
}
```

Validatiefouten → `422` met `errors` (array van strings).

## POST — `add_ticket_message`

Plaats een bericht op een bestaand ticket. Ghost-berichten zijn alleen zichtbaar voor ICT op het overzicht (niet in e-mail naar de aanvrager).

Een bot (service-key) kan zelf bepalen hoe het bericht in de thread staat: **weergavenaam** (zoals “Tim Falken”) en **functietitel** (het blauwe label ernaast, zoals “ICT”).

```json
{
  "action": "add_ticket_message",
  "ticket_id": 123,
  "message": "Interne notitie voor ICT.",
  "ghost": true,
  "sender_email": "grok-bot@kvt.nl",
  "sender_name": "Grok",
  "sender_title": "Assistent"
}
```

Velden:

- `ticket_id` of `id` — verplicht
- `message` of `message_text` — verplicht, niet leeg
- `ghost` / `is_ghost` / `ghost_mode` — optioneel, default `false`
- `sender_email` / `viewer_email` / `user_email` — actor; bij service-key verplicht voor een herkenbare afzender, anders `ict@kvt.nl`
- `sender_name` / `display_name` / `sender_display_name` — optioneel; weergavenaam in de ticketthread. Alleen ICT-sessie, service-key of trusted localhost. Anders de naam bij het e-mailadres.
- `sender_title` / `role_title` / `function_title` / `sender_role_title` — optioneel; blauwe functietitel naast de naam. Zelfde rechten als `sender_name`. Anders `ICT` of `Gebruiker`.

Rechten:

- Ticket lezen: ICT/service-key ziet elk ticket; een sessie-user alleen als deelnemer
- `ghost: true` alleen met ICT-sessie, service-key of trusted localhost → anders `403` `ghost_forbidden`
- Eigen naam/titel alleen met dezelfde rechten als ghost; andere callers worden stil genegeerd

Succes → `200` met `ticket_id`, `message_id`, `is_ghost`, `sender_email`, `sender_name`, `sender_role`, `sender_title`, `message`.

Fouten: `422` (`ticket_id_required`, `message_required`, `invalid_user`), `404` (`ticket_not_found`), `403` (`ghost_forbidden`).

## Uitgaande webhook — ticket

Als `$grokBot['enabled']` aan staat en `webhook_url` is gezet, POST’t Asclepius naar die URL bij:

- **elk nieuw ticket** (UI, API, sjabloon, pagina-toegang) — `type: "new-ticket"`
- **elke overgang naar Afgehandeld** — `type: "ticket-solved"`

Dit zit **niet** in `hourly.php`. Geen instructies in de body: die heeft de bot zelf.

Timeout: 5 seconden. Een mislukte webhook houdt het ticket of de statuswijziging niet tegen.

Headers:

- `Content-Type: application/json`
- `Authorization: Bearer <send_key>` — `send_key` uit de serverconfig (`$grokBot['send_key']`)

Body:

```json
{
  "type": "new-ticket",
  "ticket_id": 123,
  "api_key": "64-teken-hex-sleutel"
}
```

| Veld | Betekenis |
| --- | --- |
| `type` | `new-ticket` of `ticket-solved` |
| `ticket_id` | Ticketnummer. Ticket ophalen: `GET api.php?id=123` (optioneel `&include_ghosts=1`) |
| `api_key` | Webhook-key, max. 1 uur. De bot stuurt die terug als `X-API-Key` of `api_key` |

Met die `api_key` kan de bot o.a. het ticket lezen, `add_ticket_message` (inclusief `sender_name` / `sender_title` / `ghost`), `change_ticket_category` en `ticket_lookups`.

## GET/POST — `ticket_lookups`

Ingebouwde categorieën en vaste statussen. Geen custom statussen.

- **GET** `api.php?action=ticket_lookups`
- **POST** `{ "action": "ticket_lookups" }`

Aliassen: `categories` (alleen categorieën) en `statuses` (alleen vaste statussen).

```json
{
  "success": true,
  "categories": [
    "hardware bestellen",
    "software bestellen",
    "Printerproblemen",
    "licentie aanvragen",
    "Business Central",
    "BC Verbeteringen",
    "AFAS",
    "Hardwareproblemen",
    "Softwareproblemen",
    "MagazijnApp",
    "ServiceApp",
    "sleutels.kvt.nl web-applicatieproblemen",
    "Laptop Klaarmaken",
    "Telefoon Klaarmaken",
    "Anders"
  ],
  "statuses": [
    "ingediend",
    "in behandeling",
    "afwachtende op gebruiker",
    "afwachtende op bestelling",
    "afwachtende op derde partij",
    "afgehandeld"
  ]
}
```

## POST — `request_page_access`

Maakt (of hergebruikt) een ticket voor toegang tot een pagina op sleutels.kvt.nl.

```json
{
  "action": "request_page_access",
  "page_name": "Magazijn",
  "user_email": "naam@kvt.nl"
}
```

- `page_name` — verplicht
- Gebruiker via `user_email` / `viewer_email`, of via de e-mail van de API-client

Antwoord (`200`): `success`, `created` (bool), `message`, `ticket` (`id`, `title`, `status`, `category`), `messages`, `ticket_url`. Als er al een open ticket met dezelfde titel bestaat, is `created` `false`.

## Openstaande tickets over tijd (`category_open_snapshots`)

Uurlijkse sparse snapshots (`hourly.php`); ontbrekende uren worden vooruitgevuld.

- **GET** `api.php?action=category_open_snapshots&from_date=YYYY-MM-DD[&to_date=YYYY-MM-DD]`
- **POST** JSON met `action: "category_open_snapshots"`

Velden: `from_date` (verplicht; aliassen `start_date`, `from`, `start`), `to_date` (optioneel, default vandaag; aliassen `end_date`, `to`, `end`).

Response: `from_date`, `to_date`, `timestamps`, `dates` (zelfde als timestamps), `series[]` met `category`, `label`, `color`, `points` (`null` = nog geen snapshot om vooruit te vullen).

Beperkte ICT-rollen zien alleen hun categorieën als `viewer_email` bekend is.

### Uurlijkse snapshot (`hourly.php`)

Externe scheduler (GET, elk uur): **GET** `hourly.php`

Slaat per categorie alleen een rij op als het aantal open tickets is veranderd. Response o.a. `snapshot_at`, `counts`, `written`, `skipped`.

`nightly.php` doet geen ticket-snapshots (alleen theevraagje). De Grok-bot hangt niet aan deze taak; zie **Uitgaande webhook — ticket**.

## Overige POST-acties

### Tickets beheren (ICT / trusted)

`manage_ticket_participants` — `operation`: `add` | `remove` | `apply`. Velden: `ticket_id`, `participant_emails` (toevoegen), `participant_email` of `remove_participant_emails` (verwijderen). Minimaal één deelnemer. Admin of trusted.

`change_ticket_category` — `ticket_id`, `category` (moet in `ticket_lookups.categories` zitten), optioneel `reassign` (bool). Zet een systeemnotitie. ICT, service-key, webhook-key of trusted.

`change_ticket_title` — `ticket_id`, `title` (niet leeg). Admin of trusted. Wist titelvertalingen.

`update_ticket_private` — `ticket_id`, `is_private`. ICT-overzicht (`is_admin_portal` + admin) of trusted.

`update_ticket_message_checkbox` — vink een markdown-checkbox in een bericht aan/uit. `ticket_id`, `message_id`, `line_index`, `checked`, `csrf_token`. Admin of trusted + geldige sessie-CSRF. Response: `message_text`.

`translate_ticket` — vertaal titel en berichten. `ticket_id`, `language` (`nl`/`en`/`de`/`fr`), `viewer_email`, optioneel `user_is_admin`, `is_admin_portal`. Response: `title`, `title_raw`, `title_is_translated`, `messages[]` met `message_text` / `message_text_raw`. Ghosts volgen gewone `getTicket`-regels (niet inbegrepen tenzij admin-overzicht).

### Live UI (browser)

`ticket_poll` — vernieuw de ticketlijst. Zelfde filtervelden als de UI: `current_page`, `viewer_email`, `can_manage_tickets`, `user_is_admin`, `is_admin_portal`, `csrf_token`, `open_ticket_id`, `view`, `browse_mode`, `assigned_filter`, `search_query`, `status_filters`, `status_filters_selected`, `status_filter_active`, `category_filters`, `category_filters_selected`, `category_filter_active`, `page`, `per_page`, `last_signature`, `current_language`. Ongewijzigd: `{ "success": true, "unchanged": true, "signature" }`. Anders HTML-kaarten, paginatie, `empty_html`.

`ticket_thread` — berichten van één ticket als HTML. `ticket_id`, plus dezelfde viewer/view/browse-context als `ticket_poll`. Ghosts alleen op ICT-overzicht. `404` `ticket_not_found`.

`related_completed_tickets` — suggesties bij nieuw ticket. `title`, `description`, `current_language`. Max. 8 afgehandelde publieke tickets: `{ id, title }`.

`related_ticket_preview` — HTML-kaart van een afgehandeld publiek ticket. `ticket_id`, `current_page`, `viewer_email`, `csrf_token`, `current_language`.

`open_ticket_duplicate_tip` — lijkt-op-open-ticket voor dezelfde aanvrager. `title`, `viewer_email`. `match` of `null`.

`presence_poll` — Janus-aanwezigheid ICT. `{ connected, groups, rows }`.

`browser_notifications_poll` — haalt browsernotificaties op voor `viewer_email`. Items: `id`, `ticket_id`, `title`, `body`, `open_url`, `created_at`.

`webpush_subscription` — `viewer_email` verplicht. `subscription_action`: `subscribe` (default) of `unsubscribe`. Bij subscribe: `subscription.endpoint`, `subscription.keys.p256dh`, `subscription.keys.auth`.

`user_profile_stats` — statistieken van een gebruiker. Alleen ICT-overzicht (`is_admin_portal` + admin). `email` van het profiel. `403` `forbidden` / `422` `invalid_email`. Response: `ticket_count`, `open_ticket_count`, `average_response_seconds`, `average_wait_seconds` (+ labels).

### Voorkeuren en changelog (sessie + CSRF)

Vereisen geldige `csrf_token` uit de browsersessie.

`save_admin_email_preferences` — admin. `notification_type`, `enabled`. Response: `preferences`.

`save_ticket_appearance_preferences` — admin. `appearance` (of losse velden). Response: `appearance`.

`save_ticket_overview_search` — `search_query`. Slaat de zoekterm in de overzichtsfilters van de gebruiker op.

`mark_changelog_read` — admin. `entry_id`. Response: `read_ids`.

`mark_all_changelogs_read` — admin. `entry_ids` (array). Response: `read_ids`.

### Templates (ICT-admin + CSRF)

`manage_ticket_template` — admin of trusted, plus `csrf_token`. `operation`:

- `list` — alleen lijst terug
- `create` — `name`, `body`
- `update` — `id`, `name`, `body`
- `delete` — `id`
- `reorder` — `ordered_ids`

Response: `{ "success": true, "templates": [ ] }`.

### Theevraagje

`theevraagje_state` — `viewer_email` verplicht. `has_image`, `image_url`, `fetched`, `image_error`, `messages[]`.

`theevraagje_send` — sessie-CSRF. `message_text` of `text`, `viewer_email`. `422` bij `csrf` / `invalid_user` / `empty_message` / `save_failed`.

### Bigscreen / stats (ICT)

`bigscreen_poll` — admin-sessie of trusted. Open tickets, stats, snapshot. `403` `forbidden` anders.

`bigscreen_version` — zelfde rechten. Versiehash van bigscreen-bestanden.

## Geldige categorieën

Bron: `TICKET_CATEGORIES` in `content/constants.php`.

- `hardware bestellen`
- `software bestellen`
- `Printerproblemen`
- `licentie aanvragen`
- `Business Central`
- `BC Verbeteringen`
- `AFAS`
- `Hardwareproblemen`
- `Softwareproblemen`
- `MagazijnApp`
- `ServiceApp`
- `sleutels.kvt.nl web-applicatieproblemen`
- `Laptop Klaarmaken`
- `Telefoon Klaarmaken`
- `Anders`

## Geldige vaste statussen

Bron: `TICKET_STATUSES`. Daarnaast kan `status` een eigen (custom) label zijn.

- `ingediend`
- `in behandeling`
- `afwachtende op gebruiker`
- `afwachtende op bestelling`
- `afwachtende op derde partij`
- `afgehandeld`

## Fouten

- `401` — ongeldige of ontbrekende API-key
- `403` — geen rechten (o.a. ghost, bigscreen, profielstats)
- `404` — ticket niet gevonden
- `405` — methode niet GET of POST
- `422` — validatiefout of `unknown_action`
- `500` — server-/databasefout

## Voorbeelden (curl)

Ticket aanmaken:

```bash
curl -X POST "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: JOUW_KEY" \
  -d "{\"title\":\"Test\",\"category\":\"Anders\",\"description\":\"Via API\",\"user_email\":\"naam@kvt.nl\"}"
```

Tickets lijst:

```bash
curl "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "X-API-Key: JOUW_KEY"
```

Ticket lezen (inclusief ghost-berichten):

```bash
curl "https://sleutels.kvt.nl/asclepius/api.php?id=123&include_ghosts=1" \
  -H "X-API-Key: JOUW_KEY"
```

Categorieën en vaste statussen:

```bash
curl "https://sleutels.kvt.nl/asclepius/api.php?action=ticket_lookups" \
  -H "X-API-Key: JOUW_KEY"
```

Ghost-bericht plaatsen:

```bash
curl -X POST "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: JOUW_KEY" \
  -d "{\"action\":\"add_ticket_message\",\"ticket_id\":123,\"message\":\"Interne notitie\",\"ghost\":true,\"sender_email\":\"grok-bot@kvt.nl\",\"sender_name\":\"Grok\",\"sender_title\":\"Assistent\"}"
```

Open tickets per categorie over tijd:

```bash
curl "https://sleutels.kvt.nl/asclepius/api.php?action=category_open_snapshots&from_date=2026-07-01" \
  -H "X-API-Key: JOUW_KEY"
```

Pagina-toegang aanvragen:

```bash
curl -X POST "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: JOUW_KEY" \
  -d "{\"action\":\"request_page_access\",\"page_name\":\"Magazijn\",\"user_email\":\"naam@kvt.nl\"}"
```
