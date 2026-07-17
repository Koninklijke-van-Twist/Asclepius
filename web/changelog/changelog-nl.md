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
