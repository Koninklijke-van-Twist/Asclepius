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
- Hoofdflow staat in `web/index.php`.
- Helpers staan in hun eigen bestand.

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