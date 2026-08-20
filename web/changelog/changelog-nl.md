id: 2026-08-20-related-presence-narrow-layout
date: 2026-08-20
title: Gerelateerde tickets en aanwezigheid op smalle schermen
author: Tim Falken

Als het scherm te smal is om **Mogelijk gerelateerd** naast het formulier te tonen, verschijnt die sectie onder **Nieuw ticket**. De aanwezigheidstracker klapt dan in achter een **≪**-knop; met **≫** kun je hem weer verbergen.

---
id: 2026-08-19-email-autocomplete-new-ticket-users
date: 2026-08-19
title: E-mailsuggesties bij nieuw ticket en gebruikers beheren
author: Tim Falken

Bij **Nieuw ticket** (voor admins) en in **Gebruikers beheren** op het ICT-overzicht krijg je nu tijdens het typen dezelfde e-mailsuggesties als op de Rollen-pagina. Daardoor kun je bekende gebruikers sneller toevoegen zonder hun volledige adres uit het hoofd te hoeven typen.

---
id: 2026-08-19-tips-sidebar
date: 2026-08-19
title: Tip bij bijlage uploaden — direct plakken met Ctrl+V
author: Tim Falken

Bij het aanmaken van een nieuw ticket verschijnt rechts een tip zodra je een bestand uploadt als bijlage: je kunt schermafdrukken ook direct vanuit het klembord in de berichttekst plakken met **Ctrl+V**. De tip verdwijnt automatisch als je dit al een keer doet, of je kan hem permanent wegklikken met "Niet meer tonen". Dit is de basis van een generiek tips-systeem waaraan later meer contextuele tips toegevoegd kunnen worden.

Er zijn nu ook extra slimme tips toegevoegd voor een mogelijk beter passende categorie, een te hoge prioriteit en het herkennen van een mogelijk dubbel openstaand ticket van dezelfde aanvrager. Bij de categorie-tip worden ook afkortingen in hoofdletters (zoals BC) herkend. Op het **ICT-overzicht** verschijnt daarnaast een tip bij een antwoord waarin je een andere admin of rol-lid noemt, zodat je interne afstemming desnoods als paars 👻-bericht kan plaatsen.

In Voorkeuren staat onderaan een knopje **"Verborgen tips weer tonen"** waarmee je alle weggeklikte tips in één keer terugzet.

---
id: 2026-08-19-presence-ict-role-groups
date: 2026-08-19
title: Aanwezigheid gegroepeerd op ICT en rollen
author: Tim Falken

In het aanwezigheidsoverzicht staan globale ICT-admins nu onder de kop **ICT**. Als leden van een rol ook in Janus staan, verschijnt daaronder een scheiding met de rolnaam en hun aanwezigheid. Andere Janus-gebruikers zonder rol of globale adminrechten worden niet getoond.

---
id: 2026-08-19-open-ticket-related-sidebar
date: 2026-08-19
title: Open ticket blijft zichtbaar en gerelateerde tickets bij nieuw ticket
author: Tim Falken

Een ticket dat je via `?open=` in de URL opent, blijft nu staan bij de stille verversing, ook als het niet in je huidige filter of pagina valt. Bij **Nieuw ticket** verschijnt links een zijbalk **Mogelijk gerelateerd** zodra er afgehandelde tickets in **Alle tickets** overeenkomen met je titel of beschrijving. Klik op een titel om dat ticket in een venster te bekijken zonder je ingevulde tekst te wissen.

---
id: 2026-08-18-ticket-sort-preferences
date: 2026-08-18
title: Eigen ticketsortering instellen
author: Tim Falken

Onder **Voorkeuren > Uiterlijk** kun je nu zelf de volgorde van tickets samenstellen met meerdere sorteerregels. Regels zijn sleepbaar, uit te breiden, te verwijderen met bevestiging en terug te zetten naar de standaardvolgorde. Rechts zie je direct in een grotere previewset met meer dan tien tickets, inclusief extra afgehandelde voorbeelden, wat de gekozen sortering doet. De standaardvolgorde is nu **Openstaand eerst**, **Hoge prioriteit eerst**, daarna **Langst niet bijgewerkt eerst** en vervolgens **Laagste nummer eerst**. **Ticket leeftijd** zet de oudste of nieuwste tickets eerst; **Status** volgt de workflowvolgorde. Per optie verschijnt ook een korte uitleg.

---
id: 2026-08-13-open-trend-hover
date: 2026-08-13
title: Trendgrafiek: waarde bij hover
author: Tim Falken

In de grafiek **Open tickets over tijd** staan geen vaste bolletjes meer op de lijnen. Bij hoveren (of aanraken) verschijnt alleen bij het dichtstbijzijnde punt een bol met het actuele aantal.

---
id: 2026-08-13-hourly-open-tickets-trend
date: 2026-08-13
title: Open-ticketsgrafiek per uur
author: Tim Falken

De trendgrafiek op **ICT-stats** haalt openstaande tickets nu uit **uurlijkse** snapshots (`hourly.php`), niet meer uit de nightly. Onveranderde uren worden niet opnieuw opgeslagen; de lijn vult die uren met de laatste bekende stand. Op de as blijven dagen zichtbaar, met uurlijkse detail in de lijn. `nightly.php` doet alleen nog het theevraagje.

---
id: 2026-08-05-date-format
date: 2026-08-05
title: Datums als 15 jun 2026
author: Tim Falken

Datums op de ticketingpagina (aanmaak-/wijzigingsdatum, deadline, grafiekassen, vakantiedetail) staan nu in het leesbare formaat **15 jun 2026** (met tijd waar relevant: **15 jun 2026 14:30**), in de taal van de interface.

---
id: 2026-08-05-open-tickets-trend
date: 2026-08-13
title: Open tickets over tijd per categorie
author: Tim Falken

Op **ICT-stats** staat een subtabel **Open tickets over tijd**: een scherpe SVG-grafiek met openstaande tickets per categorie. Standaard zie je de afgelopen maand tot vandaag; met de datums kun je live een andere periode kiezen. Klik op een categorie in de legenda om die lijn aan of uit te zetten. Snapshots worden **uurlijks** opgeslagen wanneer het aantal open tickets per categorie verandert (`hourly.php`).

---
id: 2026-08-05-ticket-appearance-prefs
date: 2026-08-05
title: Voorkeuren met uiterlijk
author: Tim Falken

De tab **E-mailvoorkeuren** heet nu **Voorkeuren**. Bovenaan staan nog steeds de e-mailmeldingen; daaronder kun je onder **Uiterlijk** kiezen hoe tickets eruitzien: statusbolletjes, tijd open, bovenrandkleur (status/toegewezen/prioriteit/categorie) en of afgehandelde tickets subtieler worden getoond. Rechts zie je voorbeeldtickets die live meebewegen; de keuzes gelden overal in het systeem.

---
id: 2026-08-04-ict-roles-afas
date: 2026-08-04
title: ICT-rollen en categorie AFAS
author: Tim Falken

Er is een nieuwe ticketcategorie **AFAS**. Daarnaast kunnen volledige ICT-admins onder **Rollen** beperkte rollen aanmaken: een rolnaam, gekoppelde categorieën en leden (e-mailadressen).

Leden van een rol zien alleen tickets in de categorieën van hun rol, met navigatie zoals `<rol>-overzicht` en `<rol>-statistieken`. Onder Instellingen beheren zij vakantie en automatisch toewijzen voor hun rolcategorieën. Behandelaar-keuzes en meldingen volgen wie voor die categorie in aanmerking komt. Zoeken op ticketnummer en ticketlinks respecteren dezelfde categorietoegang. Een gebruiker kan tot **één rol** behoren; bij toevoegen krijg je suggesties uit de bekende gebruikerslijst. Live-verversingen (stats/ticketpoll) blijven binnen dezelfde rolrestricties. Bij categorie wijzigen naar een categorie buiten je rol krijg je een waarschuwing en wordt hertoewijzen verplicht. Beperkte ICT-gebruikers houden daarnaast de tab **Alle tickets** (zelfde rechten als een gewone gebruiker); volledige ICT-admins hebben die niet nodig. De filter **Toegewezen aan** onthoudt ook rolleden correct (niet alleen full-admins).

---
id: 2026-08-04-priority-edge-markers
date: 2026-08-04
title: Prioriteitsbolletjes op open tickets
author: Tim Falken

Op het **ICT-overzicht** tonen open tickets met prioriteit 1 of 2 een bolletje op de linker rand: oranje met `!` bij prioriteit 1, en knipperend rood met `!!` bij prioriteit 2.

---
id: 2026-08-04-ticket-sort-oldest-first
date: 2026-08-04
title: Oudere open tickets bovenaan
author: Tim Falken

Open tickets staan nog steeds eerst (niet-afgehandeld vóór afgehandeld) en daarna op prioriteit (hoog naar laag). Binnen dezelfde prioriteit komen **oudere tickets nu vóór nieuwere**, zodat langer openstaande tickets eerder in beeld zijn.

---
id: 2026-08-04-presence-sidebar
date: 2026-08-04
title: Aanwezigheid via Janus
author: Tim Falken

Aan de zijkant zie je nu een **aanwezigheidsoverzicht** met wie er vandaag op kantoor of thuis is, afwezig, ziek of op vakantie. De gegevens komen uit [Janus](../janus/) en alleen van collega’s die daar de **volledige urentracker** gebruiken.

Sta je er zelf nog niet tussen (als ICT-admin)? Klik op het overzicht voor uitleg, of open [Janus](../janus/), zet de volledige urentracker aan en gebruik die voortaan — daarna verschijn je automatisch.

---
id: 2026-07-22-ghost-messages
date: 2026-07-22
title: Ghost-berichten op ICT-overzicht
author: Tim Falken

Op het **ICT-overzicht** kun je bij een antwoord de ghost-modus inschakelen (spook-knop naast het toetsenbord). Zo'n bericht is alleen voor ICT zichtbaar op dat overzicht, met paarse styling en een golvende rand. Statuswijzigingen en andere systeeminformatie blijven normale berichten.

---
id: 2026-07-22-custom-ticket-statuses
date: 2026-07-22
title: Eigen ticketstatussen
author: Tim Falken

Als ICT-beheerder kun je bij het wijzigen van een ticketstatus op **Anders** klikken en een eigen statusnaam invoeren. Die status verschijnt in de statuskeuze en in de filterbubbels zolang er tickets mee staan. Een filter voor een status die jij hebt aangemaakt staat standaard aan. Bij een filterbubble zie je wie de status heeft aangemaakt. Andere hoofdletters worden samengevoegd tot één status, en de kleur volgt automatisch uit de naam.

---
id: 2026-07-21-exact-ticket-number-search
date: 2026-07-21
title: Ticketnummer zoeken negeert filters
author: Tim Falken

Als je in de zoekbalk een **ticketnummer** typt (bijvoorbeeld `42` of `#42`), verschijnt dat ticket altijd in de resultaten — ook als het niet overeenkomt met de actieve status-, categorie- of behandelaarfilters.

---
id: 2026-07-17-api-user-names
date: 2026-07-17
title: Gebruikersnamen in API-responses
author: Tim Falken

API-responses die een gebruikers-e-mailadres bevatten, geven nu ook de bijbehorende **weergavenaam** mee (via de Graph-gebruikerslijst). Bij deelnemerslijsten staat een `participants`-array met e-mail en naam.

---
id: 2026-07-17-api-docs
date: 2026-07-17
title: API-documentatie in Instellingen
author: Tim Falken

Onderin **Instellingen** staat een knop **API**. Daarmee open je de Asclepius API-documentatie (authenticatie, endpoints en voorbeelden) in de applicatie.

---
id: 2026-07-17-prefs-led-ticket-filters
date: 2026-07-17
title: Filters blijven bewaard in je profiel
author: Tim Falken

Ticketfilters (status, categorie, behandelaar, zoekterm) worden nu bijgehouden in je gebruikersvoorkeuren in plaats van in de URL. Na het opslaan van een ticket blijven je filters staan. Filters verschijnen alleen tijdelijk in de URL wanneer je ze aanpast; daarna wordt de URL weer schoon.

---
id: 2026-07-17-attachment-open-new-tab
date: 2026-07-17
title: Bijlagen openen in nieuw tabblad
author: Tim Falken

Als je op een bijlagenaam klikt, opent het bestand nu in een **nieuw tabblad**. De ticketpagina blijft openstaan. Voorbeeldweergaven in de modal zijn hierdoor niet veranderd.

---
id: 2026-07-14-tickets-per-page-preference
date: 2026-07-14
title: Tickets per pagina instelbaar
author: Tim Falken

Je kunt nu instellen hoeveel tickets je per pagina ziet (5 tot 100, standaard 20). De keuze staat rechts naast **Reset filters** in het filterblok, of in hetzelfde blok op **Mijn tickets**. Je instelling wordt opgeslagen en geldt overal: Mijn tickets, ICT-overzicht en Alle tickets.

---
id: 2026-07-10-ticket-pagination-filters
date: 2026-07-10
title: Ticketpaginering en opgeslagen filters
author: Tim Falken

Ticketlijsten tonen nu maximaal **20 tickets per pagina**, met paginanavigatie boven en onder de lijst. Paginanavigatie behoudt filters, zoekterm en andere URL-parameters. Na zoeken of filteren wordt de paginering opnieuw berekend op de gefilterde resultaten.

Opgeslagen filters worden weer correct geladen als je direct naar `admin.php` gaat of via het navigatiemenu terugkeert naar het ICT-overzicht of Alle tickets.

---
id: 2026-07-08-translation-assignment-fixes
date: 2026-07-08
title: Vertalingen en tickettoewijzing
author: Tim Falken

Bij vertaalde tickets zie je nu alleen de vertaalde tekst. Het origineel is beschikbaar via de knop **Toon origineel**, of wanneer de vertaling nog wordt geladen.

Tickets die via de API worden aangemaakt (zoals automatische toegangsaanvragen) worden direct toegewezen aan een beschikbare ICT-beheerder. Bestaande tickets zonder behandelaar worden bij het laden of zoeken automatisch en stil toegewezen.

---
id: 2026-07-08-ticket-ux-upload-fixes
date: 2026-07-08
title: Zoeken, zelf-toewijzing en uploads
author: Tim Falken

Het zoekveld in het ticketoverzicht ververst nu op de achtergrond zonder de pagina te herladen, zodat je kunt blijven typen.

Je kunt een ticket altijd aan **jezelf** toewijzen, ook als je als afwezig staat of normale categorie-regels anders zouden blokkeren.

Bij zeer grote uploads (zoals MP4) krijg je een duidelijke foutmelding in plaats van een verbroken sessie. Inline-afbeeldingen laden betrouwbaarder wanneer er meerdere in een bericht staan.

---
id: 2026-07-08-all-tickets-tab
date: 2026-07-08
title: Tab Alle tickets en privétickets
author: Tim Falken

Normale gebruikers hebben een nieuw tabblad **Alle tickets** met een overzicht van afgehandelde tickets. Tickets zijn daar alleen-lezen: je kunt ze bekijken maar geen berichten plaatsen of gegevens wijzigen. Het overzicht heeft dezelfde filters als het ICT-overzicht (categorie, zoeken, behandelaar).

ICT-beheerders kunnen in het ICT-overzicht een ticket als **Privé** markeren via een checkbox op het ticket. Privétickets verschijnen nooit in het tabblad Alle tickets.

Op het ICT-overzicht, Alle tickets en Mijn tickets staat links naast het ticketnummer een 🔗-icoon. Daarmee kopieer je overal dezelfde link (`index.php?open=…`). Bij openen land je op de juiste plek: **eigen tickets** in Mijn tickets, **admins** anders in het ICT-overzicht, **andere gebruikers** bij afgeronde openbare tickets in Alle tickets.

---
id: 2026-06-23-message-textarea-grow
date: 2026-06-23
title: Tekstvak groeit mee met je bericht
author: Tim Falken

Bij een nieuw ticket of een antwoord op een bestaand ticket wordt het tekstvak automatisch hoger naarmate je typt. Je hoeft niet meer in het vak te scrollen of het handmatig groter te trekken.

---
id: 2026-06-23-admin-ticket-improvements
date: 2026-06-23
title: Ticket beheren en statistieken
author: Tim Falken

ICT-beheerders kunnen de titel van een ticket wijzigen via een knop bovenaan het ticket, net als bij categorie wijzigen. De kaartjes met titel, datums, prioriteit, gebruikers en categorie zijn compacter en staan in een overzichtelijker raster.

Op de statistiekenpagina staan extra tellers voor tickets in afwachting (bestelling, gebruiker, derde partij). In de tabel per aanvrager zie je hoeveel tickets iemand heeft ingediend.

---
id: 2026-06-19-user-display-names
date: 2026-06-19
title: Namen in plaats van e-mailadressen
author: Tim Falken

Waar mogelijk zie je nu de echte naam van een gebruiker in plaats van het e-mailadres — bijvoorbeeld bij aanvragers, behandelaars, berichten en statistieken. Hover met de muis om het e-mailadres te zien. Bekende namen worden lokaal onthouden zodat het overzicht snel blijft.

---
id: 2026-06-19-changelog-tab
date: 2026-06-19
title: Changelog-tab
author: Tim Falken

Bekijk als beheerder wat er nieuw is in Asclepius. Ongelezen updates staan ingeklapt; open een item om het te lezen. Gelezen items kun je later terugvinden via de knop onderaan.

---
id: 2026-06-19-attachments
date: 2026-06-19
title: Bijlagen in berichten
author: Tim Falken

Je kunt bijlagen nu verwijderen voordat je een bericht verstuurt. Afbeeldingen van het klembord worden automatisch in de berichttekst geplaatst; andere bestanden kun je met een knop invoegen. Ingevoegde bijlagen verschijnen als apart blok in de tekst.

---
id: 2026-06-19-admin-preferences
date: 2026-06-19
title: E-mailvoorkeuren en nieuwe status
author: Tim Falken

ICT-beheerders kunnen per gebeurtenis kiezen of ze een e-mail ontvangen. Daarnaast is de status "Afwachtende op derde partij" toegevoegd voor tickets die op een externe partij wachten.

---
id: 2026-06-18-category-change
date: 2026-06-18
title: Ticketcategorie wijzigen
author: Tim Falken

Beheerders kunnen de categorie van een bestaand ticket aanpassen, met optionele herindeling naar een andere behandelaar. De aanvrager en eventuele nieuwe behandelaar ontvangen een melding.

---
id: 2026-06-17-performance
date: 2026-06-17
title: Sneller ticketoverzicht
author: Tim Falken

Het laden en verversen van grote ticketlijsten is geoptimaliseerd: berichten worden pas geladen bij uitklappen, polling stuurt minder data en de database gebruikt efficiëntere queries.

---
id: 2026-06-16-session-uploads
date: 2026-06-16
title: Langere sessies en betere uploads
author: Tim Falken

Sessies blijven langer actief tijdens het werken aan tickets. Meerdere bijlagen worden niet meer overschreven als je opnieuw bestanden kiest, en de sessie wordt gecontroleerd vóór het versturen van formulieren.

---
id: 2026-05-13-ticket-search
date: 2026-05-13
title: Zoeken in tickets
author: Omer Pesket

Het ICT-overzicht heeft een zoekveld gekregen om tickets te filteren op titel, aanvrager en andere velden.

---
id: 2026-05-07-translations
date: 2026-05-07
title: Automatische vertaling
author: Tim Falken

Ticketberichten kunnen automatisch worden vertaald naar de taal van de lezer. Ondersteuning voor meerdere vertaalproviders is voorbereid.

---
id: 2026-05-05-template-tickets
date: 2026-05-05
title: Sjabloontickets en checkboxes
author: Tim Falken

Sjabloontickets maken het aanmaken van standaardmeldingen eenvoudiger. In berichten kun je interactieve checkboxes gebruiken. Categorieën op de instellingenpagina zijn herschikbaar.

---
id: 2026-05-05-timezone
date: 2026-05-05
title: Tijdzone en datums
author: Omer Pesket

Datums en tijden in de applicatie en API volgen nu consequent de geconfigureerde tijdzone.

---
id: 2026-04-30-multi-user-keys
date: 2026-04-30
title: Meerdere deelnemers en toetsenbordsymbolen
author: Tim Falken

Tickets kunnen meerdere deelnemers hebben. In tekstvelden kun je sneltoetsen en speciale toetsen invoegen via een keuzemenu.

---
id: 2026-04-29-file-previews
date: 2026-04-29
title: Voorbeelden van bijlagen
author: Tim Falken

Afbeeldingen en diverse bestandstypen kunnen direct in het ticket worden bekeken zonder te downloaden, inclusief miniaturen en documentvoorbeelden.
