# Asclepius API

HTTP-JSON API voor tickets. Endpoint: `api.php` (zelfde map als de webapp).

Deze pagina is de complete specificatie: elke actie die `api.php` kent, staat hier. Geen API-keys of andere secrets.

Machine-readable bron: `docs/api.md` (ook `index.php?view=api&raw=1`).

## Authenticatie

Elk verzoek heeft een geldige API-key nodig, tenzij het vanaf de server zelf komt (`REMOTE_ADDR` = `SERVER_ADDR`, of `127.0.0.1` / `::1`).

Geef de key mee via:

- header `X-API-Key: <key>`
- of query/body-parameter `api_key`

Vier soorten keys:

- **Service-key** — vast, in `auth.php` (`$apiKeys`). Bedoeld voor bots en integraties. Heeft geen sessie-e-mail; stuur `user_email` / `sender_email` / `viewer_email` mee waar een actor nodig is.
- **Sessie-key** — tijdelijke rotating key van de web-UI (hex, 64 tekens). Koppeling aan de ingelogde gebruiker (`email`, `is_admin`).
- **Persoonlijke login-key** — de tijdelijke key die de gedeelde login (`login/session_user.php`) bij het inloggen per gebruiker uitgeeft: `sha256(oid|d-m-Y)` (UTC-datum), in `$_SESSION['user']['api_key']`. Geldig vandaag en gisteren. Asclepius accepteert hem **alleen voor ticket aanmaken** (POST zonder `action`), met `oid` en `user_email` in de body (of headers `X-User-Oid` / `X-User-Email`). Het ticket komt altijd op naam van die gebruiker (`user_email` wordt genegeerd als het afwijkt). Kent Asclepius het oid al, dan moet het e-mailadres overeenkomen. Bedoeld voor andere sleutels-apps (bijv. Argus "Rapporteer aan ICT") die server-side namens de ingelogde gebruiker melden.
- **Webhook-key** — hex, 64 tekens, zit in de uitgaande ticket-webhook (`new-ticket`, `ticket-solved`, `user-reply`, `ticket-reopened`, `re-evaluate-ticket-and-advise`). ICT-rechten, maximaal **1 uur** geldig, daarna `401`.

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

Berichtvelden o.a.: `id`, `ticket_id`, `sender_email`, `sender_role` (`admin` of `user`), `message_text`, `created_at`, `is_ghost`, `attachments`, `reactions`. Reacties sturen geen mail, melding of webhook.

`reactions` op een bericht (wiki-signaal voor wie het antwoord beoordeelt):

- `likes` — aantal 👍. Iemand vond dit het **juiste** antwoord.
- `dislikes` — aantal 👎. Iemand vond dit het **onjuiste** antwoord.
- `mine` — stem van de kijker: `1` (like), `-1` (dislike) of `0`. Zonder kijker (service-key) is dit `0`.
- `like_users` / `dislike_users` — wie die stem zette. Zelfde identiteit als andere gebruikers: `{ "email", "name" }`.
- `plus`, `minus`, `plus_users`, `minus_users` — dezelfde totalen; `plus_users` en `minus_users` zijn e-mailadressen. `plus` = `likes`, `minus` = `dislikes`. Blijven staan naast de like/dislike-velden.

Een gebruiker heeft hooguit één stem per bericht (nooit 👍 én 👎). Bestaande +1/−1-rijen blijven dezelfde stem (`1` of `-1`).

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
  "ticket_url": "https://sleutels.kvt.nl/asclepius/index.php?open=123",
  "ticket": { }
}
```

`ticket_url` is dezelfde link als de knop "Ticketlink kopiëren" in de UI (`index.php?open=<id>`).

Tip voor sleutels-apps die namens een gebruiker een ticket melden: gebruik de service-key (`asclepius_api_key` uit `login/cfg.php`), zet `user_email` op de ingelogde gebruiker en kies categorie `sleutels.kvt.nl web-applicatieproblemen`.

Validatiefouten → `422` met `errors` (array van strings).

## POST — `add_ticket_message`

Plaats een bericht op een bestaand ticket. Ghost-berichten zijn alleen zichtbaar voor ICT op het overzicht (niet in e-mail naar de aanvrager).

Een bot (service-key) kan zelf bepalen hoe het bericht in de thread staat: **weergavenaam** (zoals “Tim Falken”) en **functietitel** (het blauwe label ernaast, zoals “ICT”).

Optioneel kun je in **hetzelfde verzoek** de status en/of de toegewezen medewerker meenemen — hetzelfde als het ICT-antwoordformulier. `change_ticket_status` en `change_ticket_assignee` blijven bestaan.

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

Gecombineerd bericht + status + toewijzing (ICT-Bot):

```json
{
  "action": "add_ticket_message",
  "ticket_id": 776,
  "message": "Printer opnieuw ingesteld; testafdruk is gelukt.",
  "status": "afgehandeld",
  "assigned_email": "ict@kvt.nl",
  "sender_email": "grok-bot@kvt.nl",
  "sender_name": "ICT-Bot",
  "sender_title": "Assistent"
}
```

Velden:

- `ticket_id` of `id` — verplicht
- `message` of `message_text` — verplicht, niet leeg (ook als je status of assignee meegeeft), behalve als je `attachments` meestuurt
- `attachments` — optioneel; bijlagen en inline afbeeldingen, zie **Bijlagen** hieronder
- `ghost` / `is_ghost` / `ghost_mode` — optioneel, default `false`
- `sender_email` / `viewer_email` / `user_email` — actor; bij service-key verplicht voor een herkenbare afzender, anders `ict@kvt.nl`
- `sender_name` / `display_name` / `sender_display_name` — optioneel; weergavenaam in de ticketthread. Alleen ICT-sessie, service-key of trusted localhost. Anders de naam bij het e-mailadres.
- `sender_title` / `role_title` / `function_title` / `sender_role_title` — optioneel; blauwe functietitel naast de naam. Zelfde rechten als `sender_name`. Anders `ICT` of `Gebruiker`.
- `status` / `ticket_status` — optioneel; alleen meenemen om de status te wijzigen. Zelfde regels als `change_ticket_status` (vaste waarde uit `ticket_lookups.statuses` of eigen label).
- `assigned_email` / `assignee` / `assigned` — optioneel; alleen meenemen om de toewijzing te wijzigen. Lege string = niet toegewezen. Zelfde regels als `change_ticket_assignee`.

Volgorde (gelijk aan het ICT-antwoordformulier): eerst validatie (geen bericht als status/assignee ongeldig is), daarna ticket bijwerken, daarna berichten. Bij `ghost: true` én een statuswijziging komt de systeemnotitie als gewoon bericht in de thread; de tekst van de bot blijft ghost.

Rechten:

- Ticket lezen: ICT/service-key ziet elk ticket; een sessie-user alleen als deelnemer
- `ghost: true` alleen met ICT-sessie, service-key of trusted localhost → anders `403` `ghost_forbidden`
- Eigen naam/titel alleen met dezelfde rechten als ghost; andere callers worden stil genegeerd
- `status` / `assigned_email` alleen met dezelfde autorisatie als `change_ticket_status` / `change_ticket_assignee`: service-key, webhook-key (`apiClient.is_admin`), ICT-rechten van de sessie, of trusted localhost. Anders `403` `forbidden`. Een `user_is_admin` in de body geeft geen extra rechten.

Succes → `200` met `ticket_id`, `message_id`, `is_ghost`, `sender_email`, `sender_name`, `sender_role`, `sender_title`, `message`. Met bijlagen ook `attachments` en `ticket_url`.

Ontbrak `sender_email` / `viewer_email` / `user_email`, `sender_name` / `display_name` / `sender_display_name` en/of `sender_title` / `role_title` / `function_title` / `sender_role_title`, en is dat veld aangevuld met een standaardwaarde, dan bevat het succesantwoord ook `hints` (zie **hints** hieronder). Stonden alle drie expliciet in het verzoek, dan ontbreekt die identiteitshint. Het verzoek blijft slagen; bestaande velden veranderen niet.

Als `status` en/of `assigned_email` (of hun aliassen) in het verzoek stonden, extra velden:

- `status`, `status_changed`
- `assigned_email`, `assignee_changed`
- `unchanged` — `true` als status en toewijzing allebei hetzelfde bleven
- `status_message_id` — alleen bij ghost + echte statuswijziging (aparte zichtbare systeemnotitie)

Fouten: `422` (`ticket_id_required`, `message_required`, `invalid_user`, `invalid_status`, `invalid_employee`, `self_assignment_not_allowed`, `employee_away`), `404` (`ticket_not_found`), `403` (`ghost_forbidden`, `forbidden`).

### Bijlagen (`attachments`) en inline afbeeldingen

Optioneel veld `attachments`: een lijst van objecten `{ "filename", "mime", "data_base64" }` (plus optioneel `"inline": true`). Werkt in een JSON-body en als formulierveld (dan als JSON-string, of als `attachments[0][filename]` enz.). Daarnaast mag je bij `multipart/form-data` gewone bestanden meesturen als `attachments[]`; die komen in de volgorde ná de base64-bijlagen.

- `filename` (of `name`) — verplicht; wordt opgeschoond (geen pad, geen besturingstekens, geen `[ ] { } < > : " | ? *`, max 120 tekens). `../../x.png` wordt `x.png`. Twee keer dezelfde naam in één bericht → de tweede wordt `naam-2.png`.
- `mime` (of `mime_type`) — optioneel; als je hem meegeeft moet hij bij de echte inhoud passen (`image/jpg` = `image/jpeg`, `application/octet-stream` mag altijd).
- `data_base64` (of `data`) — verplicht; gewone base64, base64url of een data-URL (`data:image/png;base64,...`).
- `inline` — optioneel; `true` zet de bijlage als inline afbeelding onderaan het bericht (als hij nog niet via een verwijzing in de tekst staat).

Bijlagen worden opgeslagen **precies zoals een upload vanuit het antwoordformulier**: via `TicketStore::addMessage()` in `data/ticket_uploads/<ticket_id>/ticket_<uniqid>.<ext>`, met een rij in `ticket_attachments` (mime via `finfo`, `file_size`, `uploaded_by_email` = afzender) en dezelfde bestandsrechten.

**Inline in de tekst.** De UI zet een geplakte/ingevoegde afbeelding als marker `[[attachment:bestandsnaam]]` op een eigen regel in het bericht. Op die plek toont de thread de afbeelding (klik = vergroten). Inline bijlagen staan niet nog eens in de bijlagenlijst onder het bericht. In e-mail wordt de marker `📎 bestandsnaam`. De API gebruikt hetzelfde formaat:

- `{{attachment:0}}` — 0-based index in `attachments` — of `{{attachment:bestandsnaam}}` ergens in `message`. Dit wordt de marker op een eigen regel. Tekst ervoor en erna op dezelfde regel wordt een eigen regel.
- `"inline": true` op de bijlage — marker onderaan het bericht.
- `[[attachment:bestandsnaam]]` op een eigen regel mag ook direct (de opgeschoonde naam).

Een verwijzing naar een index of naam die niet bestaat → `422` `attachment_reference_invalid`. Met bijlagen mag `message` leeg zijn.

```json
{
  "action": "add_ticket_message",
  "ticket_id": 123,
  "sender_email": "ict-bot@kvt.nl",
  "sender_name": "Metis",
  "sender_title": "Assistent",
  "message": "Hoi Ivan, zo ziet de Retourlijst in Consus er nu uit:\n{{attachment:0}}\nDe details staan in de pdf.",
  "attachments": [
    { "filename": "retourlijst.png", "mime": "image/png", "data_base64": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==" },
    { "filename": "details.pdf", "mime": "application/pdf", "data_base64": "JVBERi0xLjQK..." },
    { "filename": "extra.png", "mime": "image/png", "data_base64": "iVBORw0KGgo...", "inline": true }
  ]
}
```

Opgeslagen tekst: `Hoi Ivan, zo ziet de Retourlijst in Consus er nu uit:\n[[attachment:retourlijst.png]]\nDe details staan in de pdf.\n[[attachment:extra.png]]`. `details.pdf` staat in de bijlagenlijst onder het bericht.

Limieten en controle (alles wordt gecontroleerd **vóórdat** het ticket of een bericht wordt aangepast, dus ook vóór een `status` of `assigned_email` in hetzelfde verzoek):

- max **10** bijlagen per bericht, max **10 MB** per bestand, max **40 MB** samen. Ook de `post_max_size` van de server geldt (base64 is ongeveer 33% groter).
- toegestane extensies: `png`, `jpg`, `jpeg`, `gif`, `webp`, `pdf`, `txt`, `log`, `csv`, `docx`, `xlsx`, `pptx`. De inhoud wordt met `finfo` gecontroleerd en moet bij de extensie passen. Een `.png` met html-inhoud wordt geweigerd. `svg`, `html` en scripts mogen niet (die zouden via de directe upload-URL uitgevoerd kunnen worden).
- Het bericht en de bijlagen worden in één databasetransactie opgeslagen. Mislukt het opslaan van een bijlage, dan wordt het bericht teruggedraaid en worden al verplaatste bestanden verwijderd.

Bijlagefouten (met `error_code`, een leesbare `error_message`, waar mogelijk `attachment_index` / `attachment_filename`, en `hints` met een voorbeeld):

- `422`: `invalid_attachments`, `invalid_attachment`, `invalid_attachment_filename`, `attachment_data_required`, `invalid_attachment_data`, `too_many_attachments`, `attachment_type_not_allowed`, `attachment_content_mismatch`, `attachment_mime_mismatch`, `attachment_upload_error`, `attachment_reference_invalid`
- `413`: `attachment_too_large`, `attachments_too_large`
- `500`: `attachment_store_failed` (er is dan geen bericht geplaatst)

Bij succes heeft het antwoord ook:

```json
"attachments": [
  {
    "id": 4711,
    "filename": "retourlijst.png",
    "mime_type": "image/png",
    "size": 48213,
    "inline": true,
    "marker": "[[attachment:retourlijst.png]]",
    "url": "https://sleutels.kvt.nl/asclepius/data/ticket_uploads/123/ticket_6704f1c2a1b3c4.12345678.png",
    "download_url": "https://sleutels.kvt.nl/asclepius/index.php?download=4711"
  }
],
"ticket_url": "https://sleutels.kvt.nl/asclepius/index.php?open=123"
```

`url` is de directe bestands-URL (die gebruikt de thread ook voor de afbeelding). Voor `download_url` moet je ingelogd zijn. Zonder `attachments` blijft het antwoord precies zoals hiervoor.

Notificaties: een bericht met bijlagen volgt dezelfde regels als een bericht zonder bijlagen via de API. E-mail gaat alleen bij een status- of toewijzingswijziging in hetzelfde verzoek (bestaand gedrag). In die mail staan inline markers als `📎 bestandsnaam`, net als bij een UI-bericht. Bij `ghost: true` horen de bijlagen bij het ghost-bericht.

Bij mutatiefouten is `error_code` de machineleesbare code; `error` is die code of een gelokaliseerde flash-tekst (zelfde als de `change_*`-acties). Bestaande callers die alleen een bericht sturen blijven werken: zonder status/assignee-velden verandert er niets aan het ticket.

### `hints`

Alleen op een geslaagd antwoord, en alleen als er iets aan te bevelen is. Anders ontbreekt `hints` (geen lege lijst). Het veld is extra; bestaande clients kunnen het negeren.

```json
"hints": [
  {
    "hint": "Korte aanbeveling",
    "explanation": "Hoe je dat via de API doet"
  }
]
```

Identiteit — username, title en/of email zijn weggelaten en aangevuld:

- `hint`: `Geef username, title en email expliciet mee.`
- `explanation`: stuur `sender_email` (email), `sender_name` (username) en `sender_title` (title), in JSON of als formulierveld. Voorbeeld: `{"action":"add_ticket_message","ticket_id":123,"message":"Tekst","sender_email":"naam@kvt.nl","sender_name":"Naam","sender_title":"ICT"}`.

Status los van het bericht — een echte statuswijziging via de API (`change_ticket_status`, of `add_ticket_message` mét een status die verandert) en een bericht via `add_ticket_message` op **hetzelfde ticket** vallen binnen ongeveer **één minuut**, in welke volgorde dan ook. De hint staat op het antwoord van de latere aanroep. Een statuswijziging in dezelfde POST als het bericht krijgt deze hint niet.

- `hint`: `Met voorkeur je statuswijziging in dezelfde POST als je bericht plaatsen`
- `explanation`: één POST met `action` `add_ticket_message`, `ticket_id` (of `id`), `message` (of `message_text`) en `status` (of `ticket_status`). Voorbeeld: `{"action":"add_ticket_message","ticket_id":123,"message":"Tekst","status":"in behandeling"}`.

Meerdere hints kunnen samen in `hints` staan. Een ongewijzigde status (`unchanged: true`) telt niet als statuswijziging.

## Uitgaande webhook — ticket

Als `$grokBot['enabled']` aan staat en `webhook_url` is gezet, POST’t Asclepius naar die URL bij:

- **elk nieuw ticket** (UI, API, sjabloon, pagina-toegang) — `type: "new-ticket"`
- **elke overgang naar Afgehandeld** — `type: "ticket-solved"`
- **elke overgang vanuit Afgehandeld naar een andere status** (UI of API) — `type: "ticket-reopened"`
- **bericht van de aanvrager terwijl het ticket op `afwachtende op gebruiker` staat** — `type: "user-reply"`
- **ICT vraagt opnieuw AI-advies via de knop AI Advies** — `type: "re-evaluate-ticket-and-advise"`

`user-reply` geldt voor een niet-leeg bericht van de aanvrager (UI of API), geen ghost en geen ICT-/botbericht. De status op dat moment is `afwachtende op gebruiker`, ongeacht of ICT of de bot die status zette. In het ticketoverzicht zet zo'n antwoord de status daarna op **in behandeling**; de webhook gaat uit nadat het bericht is opgeslagen en houdt `type: "user-reply"`. Alleen de status wijzigen, zonder gebruikersbericht, vuurt `user-reply` niet.

`re-evaluate-ticket-and-advise` wordt getriggerd vanuit het ICT-overzicht (knop **AI Advies**). De body bevat naast `type` / `ticket_id` / `api_key` ook `advice_prompt` (optionele vrije tekst uit de modal; mag leeg zijn). De knop blijft uit tot de bot een (ghost)antwoord heeft geplaatst en daarna een menselijk (ICT- of gebruikers)bericht volgt.

Dit zit **niet** in `hourly.php`. Geen instructies in de body: die heeft de bot zelf. Bestaande events (`new-ticket`, `ticket-solved`) houden dezelfde body.

Timeout: 5 seconden. Een mislukte webhook houdt het ticket of de statuswijziging niet tegen.

Headers:

- `Content-Type: application/json`
- `Authorization: Bearer <send_key>` — `send_key` van de webhook die wordt aangeroepen (centraal: `$grokBot['send_key']` in `auth.php`; persoonlijk: de verzendsleutel uit Voorkeuren)

### Welke webhook

De centrale webhook uit `auth.php` (`$grokBot`) blijft bij elke gebeurtenis hierboven aangeroepen worden, zolang `enabled` aan staat en `webhook_url` geldig is. Afzender, weergavenaam en titel blijven `sender_email`, `default_name` en `default_title` uit die config. Een URL is geldig als het `http` of `https` is en de host niet naar loopback, een privénetwerk, link-local, CGNAT of een metadata-adres wijst. Die controle gebeurt opnieuw vlak voor verzending; het verzoek gaat naar het dan gecontroleerde adres en volgt geen redirect.

Daarnaast kan een beheerder onder **Voorkeuren** een eigen webhook-URL en verzendsleutel zetten (`save_grok_webhook`). Die persoonlijke webhook krijgt **dezelfde gebeurtenis erbij** wanneer:

- het ticket aan die gebruiker is toegewezen, of
- die gebruiker AI-advies aanvraagt (`re-evaluate-ticket-and-advise`; dan ook als die persoon niet de behandelaar is)

Dezelfde persoon wordt één keer aangeroepen. Zijn URL én verzendsleutel gelijk aan de centrale webhook, dan volgt geen tweede aanroep: de centrale afzender blijft dan gelden.

De `api_key` van een persoonlijke webhook hoort bij het e-mailadres van die gebruiker. Zonder `sender_name` / `sender_title` in `add_ticket_message` wordt de weergavenaam de naam van die gebruiker en de titel `Assistent`. De bot mag naam en titel in dat verzoek nog steeds zelf zetten. Een gewoon bericht van dezelfde gebruiker (niet via deze webhook-key) krijgt die titel niet.

Staat de centrale webhook uit en heeft de behandelaar wél een persoonlijke webhook, dan gaat alleen die persoonlijke webhook uit.

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
| `type` | `new-ticket`, `ticket-solved`, `user-reply`, `ticket-reopened` of `re-evaluate-ticket-and-advise` |
| `ticket_id` | Ticketnummer. Ticket ophalen: `GET api.php?id=123` (optioneel `&include_ghosts=1`) |
| `api_key` | Webhook-key, max. 1 uur. De bot stuurt die terug als `X-API-Key` of `api_key` |
| `advice_prompt` | Alleen bij `re-evaluate-ticket-and-advise`: optionele toelichting uit de AI Advies-modal (mag `""` zijn) |

Met die `api_key` kan de bot o.a. het ticket lezen, `add_ticket_message` (inclusief `sender_name` / `sender_title` / `ghost`, en optioneel `status` / `assigned_email` in hetzelfde verzoek), `change_ticket_status`, `change_ticket_category`, `change_ticket_assignee`, `change_ticket_priority`, `change_ticket_due_date`, `publish_ghost_message` en `ticket_lookups`.

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

`manage_ticket_participants` — `operation`: `add` | `remove` | `apply`. Velden: `ticket_id`, `participant_emails` (toevoegen), `participant_email` of `remove_participant_emails` (verwijderen). Minimaal één deelnemer. Elke ingelogde kijker die het ticket mag openen (deelnemer, ICT of trusted). Een meegestuurde `user_is_admin` geeft geen extra rechten. De laatste deelnemer blijft staan (`flash.ticket_participant_minimum`).

`change_ticket_category` — `ticket_id`, `category` (moet in `ticket_lookups.categories` zitten), optioneel `reassign` (bool). Zet een systeemnotitie. ICT, service-key, webhook-key of trusted.

De vier mutaties hieronder (`change_ticket_status`, `change_ticket_assignee`, `change_ticket_priority`, `change_ticket_due_date`) gebruiken dezelfde autorisatie als `change_ticket_category`: geldige **service-key**, **webhook-key** (`apiClient.is_admin`), ICT-rechten van de sessie, of trusted localhost. Een client-meegegeven `user_is_admin` in de body wordt **genegeerd**.

`publish_ghost_message` — zelfde autorisatie. Haalt een bestaand ghost-bericht uit ghost-modus en stuurt dezelfde updatemail naar deelnemers als een nieuw ICT-bericht (zonder statuswijziging). Optioneel `message` of `message_text` vervangt de concepttekst vóór publiceren en vóór die mail.

Gemeenschappelijke foutvorm (`success: false`):

| HTTP | `error_code` | Wanneer |
| --- | --- | --- |
| `403` | `forbidden` | Geen service-key / webhook-key / ICT-rechten / trusted localhost |
| `404` | `ticket_not_found` | Ticket bestaat niet of valt buiten de ICT-categorieën van een beperkte rol |
| `404` | `message_not_found` | Alleen bij `publish_ghost_message`: bericht-id bestaat niet |
| `422` | `ticket_id_required` | `ticket_id` / `id` ontbreekt of is geen positief geheel getal |
| `422` | `message_id_required` | Alleen bij `publish_ghost_message`: `message_id` ontbreekt |
| `422` | `not_ghost` | Alleen bij `publish_ghost_message`: bericht is geen ghost (meer) |

```json
{
  "success": false,
  "error": "Alleen admins kunnen instellingen aanpassen.",
  "error_code": "forbidden"
}
```

`error` is meestal een gelokaliseerde flash-tekst; bij `ticket_id_required` is `error` gelijk aan de `error_code`.

#### `change_ticket_status`

`ticket_id` (alias `id`), `status` (alias `ticket_status`). `status` is een vaste waarde uit `ticket_lookups.statuses` of een eigen label (zoals in de UI, max. 40 tekens). Zet een systeemnotitie, werkt `resolved_at` bij, en stuurt dezelfde meldingen als het ICT-overzicht. Overgang naar `afgehandeld` vuurt de `ticket-solved`-webhook. Overgang vanuit `afgehandeld` naar een andere status vuurt de `ticket-reopened`-webhook.

```json
{
  "action": "change_ticket_status",
  "ticket_id": 776,
  "status": "in behandeling"
}
```

Succes na wijziging → `200`:

```json
{
  "success": true,
  "unchanged": false,
  "message": "Status bijgewerkt.",
  "ticket_id": 776,
  "status": "in behandeling",
  "status_label": "in behandeling",
  "status_color": "#d97706",
  "resolved_at": null,
  "message_id": 123,
  "message_html": "<article class=\"ticket-message\">…</article>"
}
```

Al dezelfde status → `200` met `"unchanged": true`. Dezelfde velden als hierboven, **zonder** `message_id` en `message_html`. Dat telt niet als statuswijziging voor `hints`.

Is er op hetzelfde ticket via `add_ticket_message` een bericht geplaatst binnen ongeveer een minuut (ervoor of erna), dan bevat het succesantwoord van de latere aanroep `hints` met het advies om `status` in dezelfde POST als het bericht te zetten. Zie **hints** bij `add_ticket_message`.

Extra fout: `422` `invalid_status` (leeg of ongeldig label).

#### `change_ticket_assignee`

`ticket_id`, `assigned_email` (aliassen `assignee` / `assigned`; lege string = niet toegewezen). Zelfde toewijzingsregels als de UI (categorie, afwezigheid, geen toewijzing aan de aanvrager behalve bij template-tickets of zelf toewijzen). Meldingen naar aanvrager en nieuwe medewerker. Open tickets zonder assignee worden bij het laden weer automatisch toegewezen (zelfde als de UI).

```json
{
  "action": "change_ticket_assignee",
  "ticket_id": 776,
  "assigned_email": "colleague@kvt.nl"
}
```

Succes → `200`:

```json
{
  "success": true,
  "unchanged": false,
  "message": "Toewijzing bijgewerkt.",
  "ticket_id": 776,
  "assigned_email": "colleague@kvt.nl",
  "assigned_label": "Colleague",
  "assigned_color": "#0f766e"
}
```

Al dezelfde toewijzing → `200` met `"unchanged": true` en dezelfde velden.

Extra fouten (`422`): `invalid_employee`, `self_assignment_not_allowed`, `employee_away`.

#### `change_ticket_priority`

`ticket_id`, `priority`. Alleen een geheel getal `0`, `1` of `2` (JSON-integer of string `"0"` / `"1"` / `"2"`). Waarden als `"invalid"`, `"1x"` of `1.9` worden geweigerd. Tickets mét due-date krijgen hun prioriteit uit die datum. Geen e-mail bij alleen een prioriteitswijziging.

```json
{
  "action": "change_ticket_priority",
  "ticket_id": 776,
  "priority": 2
}
```

Succes → `200`:

```json
{
  "success": true,
  "unchanged": false,
  "message": "Prioriteit bijgewerkt.",
  "ticket_id": 776,
  "priority": 2,
  "priority_label": "2 · Geblokkeerd"
}
```

Al dezelfde prioriteit → `200` met `"unchanged": true` en dezelfde velden.

Extra fouten (`422`): `invalid_priority`, `priority_follows_due_date`.

#### `change_ticket_due_date`

`ticket_id`, `due_date` (alias `due`). Alleen een echte kalenderdatum `YYYY-MM-DD` (geen `2026-02-31`, geen suffix zoals `2026-09-15T14:30:00`). Past de afgeleide prioriteit aan zoals in de UI.

```json
{
  "action": "change_ticket_due_date",
  "ticket_id": 776,
  "due_date": "2026-09-16"
}
```

Succes → `200`:

```json
{
  "success": true,
  "unchanged": false,
  "message": "Due-date bijgewerkt.",
  "ticket_id": 776,
  "due_date": "2026-09-16",
  "priority": 2
}
```

Al dezelfde due-date → `200` met `"unchanged": true` en dezelfde velden.

Extra fout: `422` `invalid_due_date` (leeg, verkeerd formaat of onmogelijke kalenderdatum).

#### `publish_ghost_message`

`message_id` — verplicht. Zet `is_ghost` op `0` voor dat bericht (alleen als het nu een ghost is). Stuurt daarna dezelfde updatemail naar deelnemers als een nieuw ICT-bericht zonder statuswijziging (`email.subject_update` / `email.intro_update` + `email.intro_update_no_status`), zodat de eindgebruiker het niet kan onderscheiden van een net gepost bericht. Lege berichten zonder bijlagen worden wel gepubliceerd, maar zonder mail.

Optioneel `message` of `message_text`: als een van beide meekomt, vervangt die string de opgeslagen concepttekst vóór het publiceren en vóór de mail. Ontbreekt het veld, dan blijft de bestaande tekst staan. Een lege string is geldig (bijvoorbeeld alleen bijlagen). `message_text` heeft voorrang als beide velden meekomen.

```json
{
  "action": "publish_ghost_message",
  "message_id": 4421,
  "message_text": "Aangepaste tekst die de gebruiker te zien krijgt."
}
```

Succes → `200`:

```json
{
  "success": true,
  "ticket_id": 776,
  "message_id": 4421,
  "is_ghost": false,
  "notified": true,
  "message_text": "Aangepaste tekst die de gebruiker te zien krijgt.",
  "message_html": "Aangepaste tekst die de gebruiker te zien krijgt."
}
```

`message_html` is de gerenderde Markdown van `message_text` (veilig geëscaped), inclusief inline bijlagen van dat bericht. Zonder opmaak is dat de geëscapete tekst zelf.

`change_ticket_title` — `ticket_id`, `title` (niet leeg). Admin of trusted. Wist titelvertalingen.

`update_ticket_private` — `ticket_id`, `is_private`. ICT-overzicht (`is_admin_portal` + admin) of trusted.

`update_ticket_message_checkbox` — vink een markdown-checkbox in een bericht aan/uit. `ticket_id`, `message_id`, `line_index`, `checked`, `csrf_token`. Admin of trusted + geldige sessie-CSRF. Response: `message_text`.

`set_message_reaction` — zet 👍 (`value` `1`), 👎 (`value` `-1`) of wis (`value` `0`) de stem van de ingelogde kijker op een bericht. `ticket_id`, `message_id`, `value` (`1`, `-1` of `0`), `csrf_token`. Zelfde tickettoegang als het ticket openen. Eén stem per gebruiker: dezelfde waarde nog eens wist de stem, de andere waarde verplaatst hem. `1` = juist antwoord (like), `-1` = onjuist antwoord (dislike). Response: `value`, `likes`, `dislikes`, `like_users`, `dislike_users` (`{ "email", "name" }`), plus `plus`, `minus`, `plus_users`, `minus_users` (e-mailadressen). Geen mail, geen melding, geen webhook. `403` `csrf`, `404` `ticket_not_found` / `message_not_found`, `422` `invalid_reaction`.

```json
{
  "success": true,
  "ticket_id": 776,
  "message_id": 4421,
  "value": 1,
  "likes": 2,
  "dislikes": 5,
  "like_users": [{ "email": "jan@kvt.nl", "name": "Jan" }],
  "dislike_users": [{ "email": "piet@kvt.nl", "name": "Piet" }],
  "plus": 2,
  "minus": 5,
  "plus_users": ["jan@kvt.nl"],
  "minus_users": ["piet@kvt.nl"]
}
```

`translate_ticket` — vertaal titel en berichten. `ticket_id`, `language` (`nl`/`en`/`de`/`fr`), `viewer_email`, optioneel `user_is_admin`, `is_admin_portal`. Response: `title`, `title_raw`, `title_is_translated`, `messages[]` met `message_text` / `message_text_raw` en gerenderde HTML `message_text_html` / `message_text_raw_html` (Markdown, veilig geëscaped; inline bijlagen en toets-iconen blijven intact). Ghosts volgen gewone `getTicket`-regels (niet inbegrepen tenzij admin-overzicht).

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

`save_ask_resolution_note` — admin. `enabled`. Of bij afhandelen de modal voor de technische oplossing getoond wordt. Standaard aan. Response: `ask_resolution_note`.

`save_grok_webhook` — ingelogde beheerder (sessie). Slaat de persoonlijke webhook van **die** sessie-gebruiker op; `viewer_email` in de body wijst niet naar iemand anders. `csrf_token` verplicht. Velden: `webhook_url` (http of https; geen loopback, privénetwerk, link-local, CGNAT of metadata-adres, ook niet via DNS), `send_key` (verplicht bij de eerste keer; leeg laten houdt de bestaande sleutel). `clear: true` verwijdert de persoonlijke webhook. De verzendsleutel komt niet terug in het antwoord. Dezelfde adrescontrole geldt voor de centrale `$grokBot`-URL en wordt vlak voor verzending herhaald; redirects worden niet gevolgd.

```json
{
  "success": true,
  "grok_webhook": {
    "configured": true,
    "webhook_url": "https://example.invalid/mijn-webhook",
    "has_send_key": true
  }
}
```

Fouten: `csrf`, `invalid_user`, `invalid_webhook_url`, `send_key_required`. Zie **Welke webhook** bij de uitgaande ticket-webhook.

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

Bericht + status + toewijzing in één call (ICT-Bot):

```bash
curl -X POST "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: JOUW_KEY" \
  -d "{\"action\":\"add_ticket_message\",\"ticket_id\":776,\"message\":\"Printer opnieuw ingesteld; testafdruk is gelukt.\",\"status\":\"afgehandeld\",\"assigned_email\":\"ict@kvt.nl\",\"sender_email\":\"grok-bot@kvt.nl\",\"sender_name\":\"ICT-Bot\",\"sender_title\":\"Assistent\"}"
```

Status wijzigen (bijv. ticket #776 naar in behandeling):

```bash
curl -X POST "https://sleutels.kvt.nl/asclepius/api.php" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: JOUW_KEY" \
  -d "{\"action\":\"change_ticket_status\",\"ticket_id\":776,\"status\":\"in behandeling\"}"
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
