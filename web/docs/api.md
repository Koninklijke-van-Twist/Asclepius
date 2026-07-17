# Asclepius API

HTTP-JSON API voor tickets. Endpoint: `api.php` (zelfde map als de webapp).

## Authenticatie

Elk verzoek heeft een geldige API-key nodig, tenzij het vanaf de server zelf komt (localhost / `SERVER_ADDR`).

Geef de key mee via:

- header `X-API-Key: <key>`
- of query/body-parameter `api_key`

Service-keys staan in `auth.php` (`$apiKeys`). De web-UI gebruikt daarnaast tijdelijke sessie-keys.

Bij een ongeldige key:

```json
{
  "success": false,
  "error": "Ongeldige API-key."
}
```

HTTP-status: `401`.

## Request-formaat

- **GET** — queryparameters
- **POST** — JSON (`Content-Type: application/json`) of form-data
- Acties via veld `action` in body (of query)

Antwoorden zijn JSON met UTF-8.

Waar een gebruikers-e-mail in de response staat (`user_email`, `assigned_email`, `sender_email`, enz.), bevat het antwoord ook de bijbehorende weergavenaam (`user_name`, `assigned_name`, `sender_name`, …) op basis van de Microsoft Graph-gebruikerslijst. Bij `participant_emails` staat daarnaast een `participants`-array met objecten `{ "email", "name" }`.

## GET — tickets ophalen

### Alle tickets

`GET api.php`

```json
{
  "success": true,
  "count": 12,
  "tickets": [ /* ticketobjecten */ ]
}
```

### Eén ticket

`GET api.php?id=123`

```json
{
  "success": true,
  "ticket": { /* volledig ticket incl. berichten */ }
}
```

Niet gevonden → `404`.

## POST — ticket aanmaken

Zonder `action` (of lege action) maakt een POST een nieuw ticket.

Verplicht:

- `title` — string
- `category` — geldige categorie (zie hieronder)
- `description` — string
- `user_email` of `requester_email` — e-mailadres van de aanvrager

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
  "assigned_name": "ICT Medewerker",
  "ticket": { /* ticketobject met o.a. user_email/user_name */ }
}
```

Validatiefouten → `422` met `errors` (array van strings).

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

Antwoord (`200`):

```json
{
  "success": true,
  "created": true,
  "message": "Uw aanvraag is ingediend. …",
  "ticket": {
    "id": 123,
    "title": "Aanvraag toegang tot Magazijn",
    "status": "ingediend",
    "category": "sleutels.kvt.nl web-applicatieproblemen"
  },
  "messages": [ /* … */ ],
  "ticket_url": "https://…/index.php?open=123"
}
```

Als er al een open ticket met dezelfde titel bestaat, is `created` `false` en wordt dat ticket teruggegeven.

## Geldige categorieën

- `hardware bestellen`
- `software bestellen`
- `Printerproblemen`
- `licentie aanvragen`
- `Business Central`
- `Hardwareproblemen`
- `Softwareproblemen`
- `MagazijnApp`
- `ServiceApp`
- `sleutels.kvt.nl web-applicatieproblemen`
- `Laptop Klaarmaken`
- `Telefoon Klaarmaken`
- `Anders`

## Interne acties (UI)

Deze acties gebruikt Asclepius zelf in de browser. Ze vereisen vaak een sessie-clientkey, admin-rechten en/of `csrf_token`. Niet bedoeld voor externe integraties, tenzij je weet wat je doet.

- `ticket_poll` — live ticketlijst vernieuwen
- `ticket_thread` — berichten van één ticket laden
- `browser_notifications_poll` — browsernotificaties ophalen
- `webpush_subscription` — Web Push abonnement
- `manage_ticket_participants` — deelnemers beheren
- `change_ticket_category` — categorie wijzigen
- `change_ticket_title` — titel wijzigen
- `update_ticket_private` — privé-markering
- `save_admin_email_preferences` — e-mailvoorkeuren
- `save_ticket_overview_search` — zoekterm in gebruikersvoorkeuren
- `mark_changelog_read` / `mark_all_changelogs_read` — changelog gelezen
- `manage_ticket_template` — sjablonen beheren
- `update_ticket_message_checkbox` — checkbox in berichttekst
- `bigscreen_poll` / `bigscreen_version` — bigscreen / stats
- `translate_ticket` — ticketteksten vertalen

## Fouten

- `401` — ongeldige of ontbrekende API-key
- `403` — geen rechten voor deze actie
- `404` — ticket niet gevonden
- `405` — methode niet toegestaan
- `422` — validatiefout
- `500` — server-/databasefout

## Voorbeelden (curl)

Ticket aanmaken:

```bash
curl -X POST "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: JOUW_KEY" \
  -d "{\"title\":\"Test\",\"category\":\"Anders\",\"description\":\"Via API\",\"user_email\":\"naam@kvt.nl\"}"
```

Ticket ophalen:

```bash
curl "https://sleutels.kvt.nl/asclepius/api.php?id=123" \
  -H "X-API-Key: JOUW_KEY"
```

Pagina-toegang aanvragen:

```bash
curl -X POST "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: JOUW_KEY" \
  -d "{\"action\":\"request_page_access\",\"page_name\":\"Magazijn\",\"user_email\":\"naam@kvt.nl\"}"
```
