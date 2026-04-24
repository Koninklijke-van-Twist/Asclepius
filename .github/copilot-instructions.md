# Copilot-instructies voor Asclepius

## Scope en architectuur
- Deze applicatie draait volledig vanuit de map `/web/` en wordt gepubliceerd onder `https://sleutels.kvt.nl/asclepius`.
- Houd links daarom relatief (`index.php`, `odata.php?...`) of vanaf `/asclepius/...` als absolute paden nodig zijn.
- De app is mobile-first. Nieuwe UI-wijzigingen moeten eerst op telefoonscherm goed leesbaar zijn.

## Niet wijzigen
- Bestand `web/logincheck.php` niet aanpassen.
- Bestand `web/odata.php` niet aanpassen.
- Bestand `web/auth.php` alleen aanpassen na expliciete gebruikersvraag.

## Data en logica werkorders
- `web/index.php` is een ~50-regelige controller die alleen `require`-statements en HTML-output bevat.
- Alle PHP-logica is verdeeld over de volgende bestanden onder `web/content/`:

| Bestand | Verantwoordelijkheid |
|---|---|
| `bootstrap.php` | PHP ini-instellingen, session_start(), require auth/logincheck/TicketStore, polyfills |
| `constants.php` | Alle PHP-constanten (categorieën, statussen, kleuren, limieten, paden) |
| `localization.php` | Alle user-facing teksten in alle ondersteunde talen (nl, en, de, fr); bevat `__($key, ...$args)` en `getCurrentLanguage()` |
| `helpers.php` | Pure hulpfuncties: opmaak, filters, CSRF, URL-opbouw, berichtformattering |
| `mail.php` | SMTP-functies en e-mailnotificaties (`sendTicketEmail`, `sendTicketNotification`) |
| `variables.php` | Request-parsing, sessiegebruiker, store-initialisatie, filterstate, baseQuery |
| `actions.php` | POST-handlers (create_ticket, reply_ticket, save_settings) + downloadhandler; eindigt altijd met `exit` of redirect |
| `data.php` | Data ophalen voor views + bigscreen-poll (JSON response + exit); stelt `$isBigscreen` in |

- HTML-partials staan in `web/content/views/`: `head.php`, `header.php`, `view_tickets.php`, `view_new_ticket.php`, `view_settings.php`, `view_stats.php`, `page_js.php`, `bigscreen_js.php`.
- Nieuwe functionaliteit hoort in een eigen bestand onder `web/content/`; voeg het toe aan de `require`-keten in `index.php`.
- Views (`web/content/views/`) bevatten alleen HTML/presentatielogica — geen DB-calls, geen redirects, geen exits.
- Incrementele updates op de overzichtspagina, of andere asynchrone calls, halen hun data uit `web/api.php`.
- `web/api.php` mag daarvoor uitgebreid worden, maar mag nooit direct of indirect afhankelijk worden van `web/logincheck.php`.

## UI-regels
- Geen zware frameworks introduceren zonder expliciet verzoek.
- Gebruik bestaande favicon/manifest-bestanden op elke nieuwe HTML-pagina.
- Gebruik op de hoofdpagina altijd `logo-website.png`.

## Veiligheid en kwaliteit
- Vang OData-fouten af en toon een korte gebruikersvriendelijke melding.
- Gebruik op pagina's die odata informatie tonen cache-widget via `injectTimerHtml(...)` uit `odata.php`; endpoint-acties blijven:
  - `odata.php?action=cache_status`
  - `odata.php?action=cache_delete`
  - `odata.php?action=cache_clear`

## Bij toekomstige uitbreidingen ODATA calls
- Extra velden eerst verifiëren in `BC Webservices.txt`.
- Alleen benodigde kolommen opvragen via `$select` voor performance.
- Gebruik `KVT_Extended_Text` als beschrijvingstekst in planningregels; `Description` blijft de naam.

## Code-structuur en refactorregels (PHP en JS)
- Pas bij refactors in PHP/JS altijd dezelfde sectievolgorde toe, en alleen als de sectie inhoud heeft:
  - `Includes/requires` (of vergelijkbare naam zoals `Imports`)
  - `Constants`
  - `Variabelen`
  - `Functies`
  - `Page load` (alle top-level uitvoerbare code die niet in functies staat)
- Gebruik voor secties een duidelijke blokcomment-stijl, bijvoorbeeld:
  - `/**` + `* Functies` + `*/`
- Voeg geen lege secties toe. Een ontbrekende sectie betekent: niet opnemen.
- Functioneel gedrag mag niet wijzigen door een refactor:
  - geen wijziging in logica, filters, output, routes, sessiegedrag of side-effects
  - alleen herordenen/annoteren en waar nodig veilig opsplitsen zonder gedragswijziging
- Houd top-level uitvoerbare code geconcentreerd in de `Page load`-sectie.
- Classes moeten altijd in een eigen bestand staan:
  - maximaal 1 class per bestand
  - bestandsnaam sluit aan op classnaam
  - geen class-definities tussen page-load code in gecombineerde scriptbestanden
- Respecteer altijd bestaande uitzonderingen uit deze instructies:
  - `web/logincheck.php` niet aanpassen
  - `web/odata.php` niet aanpassen
  - `web/auth.php` alleen aanpassen na expliciete gebruikersvraag

## Lokalisatie (meertaligheid)
- De app ondersteunt Nederlands (nl), English (en), Deutsch (de) en Français (fr). Nederlands is de leidende taal.
- **Alle** user-facing tekst (labels, koppen, placeholders, hints, foutmeldingen, flashberichten) moet via `__('sleutel')` worden opgeroepen. Hardcoded Nederlandse of andere tekst in views, actions of helpers is niet toegestaan.
- Vertalingen worden beheerd in `web/content/localization.php` in de constante `TRANSLATIONS`. Voeg nieuwe sleutels altijd toe aan **alle vier talen** tegelijk.
- Gebruik `sprintf`-stijl voor dynamische waarden: `__('flash.ticket_created', $ticketId)` waarbij de string `'Ticket #%d is aangemaakt.'` bevat.
- Na het toevoegen van nieuwe sleutels: controleer dekking met `php tests/check_translations.php` (exitcode 0 = OK).
- HTML in vertalingen (bijv. `<strong>`) is toegestaan voor `stats.intro` e.d.; gebruik dan geen `h()` om de string te escapen, maar wel voor losse waarden.