id: 2026-08-19-email-autocomplete-new-ticket-users
date: 2026-08-19
title: E-Mail-Vorschläge bei neuem Ticket und Benutzerverwaltung
author: Tim Falken

Unter **Neues Ticket** (für Admins) und in **Benutzer verwalten** in der ICT-Übersicht werden beim Tippen jetzt dieselben E-Mail-Vorschläge angezeigt wie auf der Rollen-Seite. Dadurch lassen sich bekannte Benutzer schneller hinzufügen, ohne die komplette Adresse auswendig eingeben zu müssen.

---
id: 2026-08-19-tips-sidebar
date: 2026-08-19
title: Tipp beim Hochladen eines Anhangs — Screenshots mit Strg+V einfügen
author: Tim Falken

Beim Erstellen eines neuen Tickets erscheint rechts ein Tipp, sobald Sie eine Datei als Anhang hochladen: Sie können Screenshots auch direkt aus der Zwischenablage mit **Strg+V** in den Nachrichtentext einfügen. Der Tipp verschwindet automatisch, wenn Sie dies bereits getan haben, oder Sie können ihn dauerhaft mit "Nicht mehr anzeigen" ausblenden. Dies ist die Grundlage eines generischen Tipps-Systems, dem später weitere kontextbezogene Tipps hinzugefügt werden können.

Zusätzliche intelligente Tipps weisen jetzt auch auf eine möglicherweise besser passende Kategorie hin, warnen vor einer zu hohen Priorität und erkennen ein mögliches doppeltes offenes Ticket desselben Anfragenden. Die Kategorie-Vorschläge erkennen außerdem Abkürzungen in Großbuchstaben (wie BC). In der **ICT-Übersicht** erscheint jetzt außerdem ein Tipp, wenn Ihre Antwort einen anderen Admin oder ein Rollenmitglied erwähnt, damit Sie solche Abstimmung bei Bedarf als violette 👻-interne Nachricht senden können.

In den Einstellungen gibt es unten die Schaltfläche **"Ausgeblendete Tipps wieder anzeigen"**, mit der alle ausgeblendeten Tipps auf einmal zurückgesetzt werden können.

---
id: 2026-08-19-presence-ict-role-groups
date: 2026-08-19
title: Anwesenheit nach ICT und Rollen gruppiert
author: Tim Falken

In der Anwesenheitsübersicht stehen globale ICT-Admins jetzt unter **ICT**. Wenn Mitglieder einer Rolle auch in Janus stehen, erscheint darunter eine Trennlinie mit dem Rollennamen und ihrer Anwesenheit. Andere Janus-Benutzer ohne Rolle oder globale Adminrechte werden nicht angezeigt.

---
id: 2026-08-19-open-ticket-related-sidebar
date: 2026-08-19
title: Verlinktes Ticket bleibt sichtbar und verwandte Tickets beim Erstellen
author: Tim Falken

Ein Ticket, das du über `?open=` in der URL öffnest, bleibt beim stillen Aktualisieren sichtbar, auch wenn es nicht zu Filter oder Seite passt. Unter **Neues Ticket** erscheint links die Seitenleiste **Möglicherweise verwandt**, sobald erledigte Tickets in **Alle Tickets** zu Titel oder Beschreibung passen. Ein Klick auf den Titel öffnet das Ticket in einem Fenster, ohne deine Eingaben zu löschen.

---
id: 2026-08-18-ticket-sort-preferences
date: 2026-08-18
title: Eigene Ticketsortierung festlegen
author: Tim Falken

Unter **Einstellungen > Darstellung** kannst du die Reihenfolge der Tickets jetzt mit mehreren Sortierregeln selbst festlegen. Regeln lassen sich ziehen, hinzufügen, mit Bestätigung entfernen und auf die Standardsortierung zurücksetzen. Rechts zeigt eine größere Vorschau mit mehr als zehn Tickets, darunter zusätzliche erledigte Beispiele, sofort, was die gewählte Sortierung bewirkt. Die Standardsortierung ist jetzt **Offene zuerst**, **Hohe Priorität zuerst**, danach **Am längsten nicht aktualisiert zuerst** und **Niedrigste Nummer zuerst**. **Ticketalter** stellt die ältesten oder neuesten Tickets nach vorn; **Status** folgt der Workflow-Reihenfolge. Zu jeder Option erscheint außerdem eine kurze Erklärung.

---
id: 2026-08-13-open-trend-hover
date: 2026-08-13
title: Trenddiagramm: Wert beim Hover
author: Tim Falken

Im Diagramm **Offene Tickets im Zeitverlauf** gibt es keine festen Punkte mehr auf den Linien. Beim Hover (oder Tippen) erscheint nur am nächsten Punkt ein Punkt mit der aktuellen Anzahl.

---
id: 2026-08-13-hourly-open-tickets-trend
date: 2026-08-13
title: Offene-Tickets-Diagramm stündlich
author: Tim Falken

Das Trenddiagramm unter **ICT-Statistiken** nutzt jetzt **stündliche** Snapshots (`hourly.php`) statt nächtlicher. Unveränderte Stunden werden nicht erneut gespeichert; die Linie füllt sie mit dem letzten bekannten Wert. Die Achse zeigt weiter Tage, die Linie den Stundenverlauf. `nightly.php` erledigt nur noch die Tee-Frage.

---
id: 2026-08-05-date-format
date: 2026-08-05
title: Daten als 15 Jun 2026
author: Tim Falken

Daten auf den Ticketing-Seiten (Erstellt/Aktualisiert, Fälligkeit, Diagrammachsen, Urlaubsdetail) erscheinen jetzt im lesbaren Format **15 Jun 2026** (mit Uhrzeit wo relevant: **15 Jun 2026 14:30**), in der Sprache der Oberfläche.

---
id: 2026-08-05-open-tickets-trend
date: 2026-08-13
title: Offene Tickets im Zeitverlauf je Kategorie
author: Tim Falken

Unter **ICT-Statistiken** gibt es den Untertab **Offene Tickets im Zeitverlauf**: ein scharfes SVG-Diagramm offener Tickets pro Kategorie. Standard ist der letzte Monat bis heute; mit den Datumsfeldern kannst du den Zeitraum live anpassen. Klicke auf eine Kategorie in der Legende, um die Linie ein- oder auszublenden. Snapshots werden **stündlich** gespeichert, wenn sich die offenen Tickets pro Kategorie ändern (`hourly.php`).

---
id: 2026-08-05-ticket-appearance-prefs
date: 2026-08-05
title: Einstellungen mit Darstellung
author: Tim Falken

Der Tab **E-Mail-Einstellungen** heißt jetzt **Präferenzen**. Oben bleiben die E-Mail-Benachrichtigungen; darunter kannst du unter **Darstellung** festlegen, wie Tickets aussehen: Prioritätspunkte, Offen-seit, obere Randfarbe (Status/Zugewiesen/Priorität/Kategorie) und ob erledigte Tickets dezenter wirken. Rechts aktualisieren sich Beispieltickets live; die Wahl gilt überall im System.

---
id: 2026-08-04-ict-roles-afas
date: 2026-08-04
title: ICT-Rollen und Kategorie AFAS
author: Tim Falken

Es gibt eine neue Ticketkategorie **AFAS**. Vollständige ICT-Admins können unter **Rollen** eingeschränkte Rollen verwalten: Rollenname, verknüpfte Kategorien und Mitglieder (E-Mail-Adressen).

Rollenmitglieder sehen nur Tickets in den Kategorien ihrer Rolle, mit Navigation wie `<Rolle>-Übersicht` und `<Rolle>-Statistiken`. Unter Einstellungen verwalten sie Urlaub und automatische Zuweisung für ihre Rollenkategorien. Bearbeiterauswahl und Benachrichtigungen folgen, wer für die Kategorie infrage kommt. Suche nach Ticketnummer und Ticketlinks respektieren dieselbe Kategorieberechtigung. Ein Benutzer darf nur **einer Rolle** angehören; beim Hinzufügen gibt es Vorschläge aus der bekannten Benutzerliste. Live-Aktualisierungen (Stats/Ticket-Poll) bleiben innerhalb derselben Rollenbeschränkungen. Beim Wechsel in eine Kategorie außerhalb der eigenen Rolle erscheint eine Warnung und die Neuvergabe wird erzwungen. Eingeschränkte ICT-Nutzer behalten zusätzlich den Tab **Alle Tickets** (gleiche Rechte wie normale Nutzer); vollständige ICT-Admins brauchen ihn nicht. Der Filter **Zugewiesen an** merkt sich auch Rollenmitglieder korrekt (nicht nur Full-Admins).

---
id: 2026-08-04-priority-edge-markers
date: 2026-08-04
title: Prioritäts-Punkte an offenen Tickets
author: Tim Falken

In der **ICT-Übersicht** zeigen offene Tickets mit Priorität 1 oder 2 einen Punkt am linken Rand: orange mit `!` bei Priorität 1 und blinkend rot mit `!!` bei Priorität 2.

---
id: 2026-08-04-ticket-sort-oldest-first
date: 2026-08-04
title: Ältere offene Tickets zuerst
author: Tim Falken

Offene Tickets stehen weiterhin zuerst (nicht erledigt vor erledigt) und danach nach Priorität (hoch nach niedrig). Innerhalb derselben Priorität erscheinen **ältere Tickets jetzt vor neueren**, damit länger offene Tickets früher in der Liste stehen.

---
id: 2026-08-04-presence-sidebar
date: 2026-08-04
title: Anwesenheit über Janus
author: Tim Falken

Seitlich siehst du jetzt eine **Anwesenheitsübersicht**: wer heute im Büro oder zu Hause ist, abwesend, krank oder im Urlaub. Die Daten kommen aus [Janus](../janus/) und nur von Kolleginnen und Kollegen, die dort den **vollständigen Stundentracker** nutzen.

Stehst du selbst noch nicht darin (als ICT-Admin)? Klicke auf die Übersicht für eine Erklärung, oder öffne [Janus](../janus/), aktiviere den vollständigen Stundentracker und nutze ihn weiter — danach erscheinst du automatisch.

---
id: 2026-07-22-ghost-messages
date: 2026-07-22
title: Ghost-Nachrichten in der ICT-Übersicht
author: Tim Falken

In der **ICT-Übersicht** kannst du beim Antworten den Ghost-Modus aktivieren (Geister-Schaltfläche neben der Tastatur). Solche Nachrichten sind nur für ICT in dieser Übersicht sichtbar, mit lila Styling und einem wellenförmigen Rand. Statusänderungen und andere Systemhinweise bleiben normale Nachrichten.

---
id: 2026-07-22-custom-ticket-statuses
date: 2026-07-22
title: Eigene Ticketstatusse
author: Tim Falken

Als ICT-Admin kannst du beim Ändern eines Ticketstatus auf **Sonstiges** klicken und einen eigenen Statusnamen eingeben. Dieser Status erscheint in der Statusauswahl und in den Filterchips, solange Tickets ihn verwenden. Ein Filter für einen von dir erstellten Status ist standardmäßig aktiv. Beim Hover über einen eigenen Filterchip siehst du, wer ihn erstellt hat. Unterschiedliche Groß-/Kleinschreibung wird zusammengeführt, und die Farbe ergibt sich aus dem Namen.

---
id: 2026-07-21-exact-ticket-number-search
date: 2026-07-21
title: Ticketnummersuche ignoriert Filter
author: Tim Falken

Wenn du in der Suchleiste eine **Ticketnummer** eingibst (zum Beispiel `42` oder `#42`), erscheint dieses Ticket immer in den Ergebnissen — auch wenn es nicht zu den aktiven Status-, Kategorie- oder Bearbeiterfiltern passt.

---
id: 2026-07-17-api-user-names
date: 2026-07-17
title: Benutzernamen in API-Antworten
author: Tim Falken

API-Antworten mit einer Benutzer-E-Mail enthalten jetzt auch den zugehörigen **Anzeigenamen** (aus dem Graph-Benutzerverzeichnis). Teilnehmerlisten enthalten ein `participants`-Array mit E-Mail und Name.

---
id: 2026-07-17-api-docs
date: 2026-07-17
title: API-Dokumentation in den Einstellungen
author: Tim Falken

Unten in den **Einstellungen** gibt es eine Schaltfläche **API**. Darüber öffnen Sie die Asclepius-API-Dokumentation (Authentifizierung, Endpunkte und Beispiele) in der Anwendung.

---
id: 2026-07-17-prefs-led-ticket-filters
date: 2026-07-17
title: Filter bleiben in Ihrem Profil gespeichert
author: Tim Falken

Ticketfilter (Status, Kategorie, Bearbeiter, Suche) werden jetzt in Ihren Benutzereinstellungen statt in der URL gespeichert. Nach dem Speichern eines Tickets bleiben Ihre Filter erhalten. Filter erscheinen nur vorübergehend in der URL, wenn Sie sie ändern; danach wird die URL wieder bereinigt.

---
id: 2026-07-17-attachment-open-new-tab
date: 2026-07-17
title: Anhänge öffnen in neuem Tab
author: Tim Falken

Ein Klick auf einen Anhangsnamen öffnet die Datei jetzt in einem **neuen Tab**. Die Ticketseite bleibt geöffnet. Modal-Vorschauen sind unverändert.

---
id: 2026-07-14-tickets-per-page-preference
date: 2026-07-14
title: Tickets pro Seite einstellbar
author: Tim Falken

Sie können jetzt festlegen, wie viele Tickets pro Seite angezeigt werden (5 bis 100, Standard 20). Die Auswahl steht rechts neben **Filter zurücksetzen** im Filterblock oder im gleichen Block unter **Meine Tickets**. Ihre Einstellung wird gespeichert und gilt überall: Meine Tickets, ICT-Überblick und Alle Tickets.

---
id: 2026-07-10-ticket-pagination-filters
date: 2026-07-10
title: Ticket-Paginierung und gespeicherte Filter
author: Tim Falken

Ticketlisten zeigen jetzt maximal **20 Tickets pro Seite**, mit Seitennavigation ober- und unterhalb der Liste. Die Seitenlinks behalten Filter, Suchbegriffe und andere URL-Parameter. Nach Suche oder Filterung wird die Paginierung anhand der gefilterten Ergebnisse neu berechnet.

Gespeicherte Filter werden wieder korrekt geladen, wenn Sie direkt zu `admin.php` gehen oder über das Navigationsmenü zum ICT-Überblick oder Alle Tickets zurückkehren.

---
id: 2026-07-08-translation-assignment-fixes
date: 2026-07-08
title: Übersetzungen und Ticketzuweisung
author: Tim Falken

Bei übersetzten Tickets sehen Sie nur den übersetzten Text. Das Original ist über **Original anzeigen** verfügbar oder solange die Übersetzung noch lädt.

Über die API erstellte Tickets (z. B. automatische Zugriffsanfragen) werden sofort einem verfügbaren ICT-Administrator zugewiesen. Bestehende Tickets ohne Bearbeiter werden beim Laden oder Suchen automatisch und still zugewiesen.

---
id: 2026-07-08-ticket-ux-upload-fixes
date: 2026-07-08
title: Suche, Selbstzuweisung und Uploads
author: Tim Falken

Das Suchfeld in der Ticketübersicht aktualisiert sich jetzt im Hintergrund ohne die Seite neu zu laden, sodass Sie weiter tippen können.

Sie können ein Ticket immer **sich selbst** zuweisen, auch wenn Sie als abwesend markiert sind oder Kategorieregeln es sonst blockieren würden.

Bei sehr großen Uploads (z. B. MP4) erscheint eine klare Fehlermeldung statt einer unterbrochenen Sitzung. Inline-Bilder laden zuverlässiger, wenn mehrere in einer Nachricht stehen.

---
id: 2026-07-08-all-tickets-tab
date: 2026-07-08
title: Tab „Alle Tickets“ und private Tickets
author: Tim Falken

Normale Benutzer haben einen neuen Tab **Alle Tickets** mit einer Übersicht abgeschlossener Tickets. Tickets sind dort schreibgeschützt: Sie können sie ansehen, aber keine Nachrichten senden oder Daten ändern. Die Übersicht hat dieselben Filter wie die ICT-Übersicht (Kategorie, Suche, Bearbeiter).

ICT-Administratoren können in der ICT-Übersicht ein Ticket per Checkbox als **Privat** markieren. Private Tickets erscheinen nie im Tab Alle Tickets.

In der ICT-Übersicht, unter Alle Tickets und Meine Tickets steht links neben der Ticketnummer ein 🔗-Symbol. Es kopiert überall denselben Link (`index.php?open=…`). Beim Öffnen landen Sie am richtigen Ort: **eigene Tickets** unter Meine Tickets, **Admins** sonst in der ICT-Übersicht, **andere Benutzer** bei abgeschlossenen öffentlichen Tickets unter Alle Tickets.

---
id: 2026-06-23-message-textarea-grow
date: 2026-06-23
title: Textfeld wächst mit Ihrer Nachricht
author: Tim Falken

Bei einem neuen Ticket oder einer Antwort auf ein bestehendes Ticket wird das Textfeld automatisch höher, während Sie tippen. Sie müssen nicht mehr im Feld scrollen oder es manuell vergrößern.

---
id: 2026-06-23-admin-ticket-improvements
date: 2026-06-23
title: Ticketverwaltung und Statistiken
author: Tim Falken

ICT-Administratoren können den Titel eines Tickets über eine Schaltfläche oben am Ticket ändern, ähnlich wie bei der Kategorieänderung. Die Karten für Titel, Daten, Priorität, Benutzer und Kategorie sind kompakter und in einem übersichtlicheren Raster angeordnet.

Auf der Statistikseite gibt es zusätzliche Zähler für wartende Tickets (Bestellung, Benutzer, Drittanbieter). In der Tabelle pro Antragsteller sehen Sie, wie viele Tickets jemand eingereicht hat.

---
id: 2026-06-19-user-display-names
date: 2026-06-19
title: Namen statt E-Mail-Adressen
author: Tim Falken

Wo möglich sehen Sie jetzt den echten Namen eines Benutzers statt der E-Mail-Adresse — z. B. bei Antragstellern, Bearbeitern, Nachrichten und Statistiken. Mit der Maus darüberfahren, um die E-Mail-Adresse zu sehen. Bekannte Namen werden lokal gespeichert, damit die Übersicht schnell bleibt.

---
id: 2026-06-19-changelog-tab
date: 2026-06-19
title: Changelog-Tab
author: Tim Falken

Administratoren sehen, was in Asclepius neu ist. Ungelesene Einträge sind eingeklappt; zum Lesen aufklappen. Gelesene Einträge können unten wieder eingeblendet werden.

---
id: 2026-06-19-attachments
date: 2026-06-19
title: Anhänge in Nachrichten
author: Tim Falken

Anhänge können vor dem Senden entfernt werden. Bilder aus der Zwischenablage werden automatisch in den Nachrichtentext eingefügt; andere Dateien können per Schaltfläche eingefügt werden. Eingebettete Anhänge erscheinen als eigener Block im Text.

---
id: 2026-06-19-admin-preferences
date: 2026-06-19
title: E-Mail-Einstellungen und neuer Status
author: Tim Falken

ICT-Administratoren können wählen, bei welchen Ereignissen sie eine E-Mail erhalten. Der neue Status „Wartet auf Dritte Partei“ wurde für Tickets hinzugefügt, die auf eine externe Partei warten.

---
id: 2026-06-18-category-change
date: 2026-06-18
title: Ticketkategorie ändern
author: Tim Falken

Administratoren können die Kategorie eines bestehenden Tickets ändern, optional mit Neuzuweisung an einen anderen Bearbeiter. Der Antragsteller und ein neuer Bearbeiter erhalten eine Benachrichtigung.

---
id: 2026-06-17-performance
date: 2026-06-17
title: Schnellere Ticketübersicht
author: Tim Falken

Das Laden und Aktualisieren großer Ticketlisten wurde optimiert: Nachrichten werden erst beim Aufklappen geladen, Polling sendet weniger Daten und die Datenbank nutzt effizientere Abfragen.

---
id: 2026-06-16-session-uploads
date: 2026-06-16
title: Längere Sitzungen und bessere Uploads
author: Tim Falken

Sitzungen bleiben länger aktiv während der Ticketbearbeitung. Mehrere Anhänge werden nicht mehr überschrieben, wenn Dateien erneut ausgewählt werden, und die Sitzung wird vor dem Absenden von Formularen geprüft.

---
id: 2026-05-13-ticket-search
date: 2026-05-13
title: Tickets suchen
author: Omer Pesket

Die ICT-Übersicht hat ein Suchfeld zum Filtern von Tickets nach Titel, Antragsteller und weiteren Feldern erhalten.

---
id: 2026-05-07-translations
date: 2026-05-07
title: Automatische Übersetzung
author: Tim Falken

Ticketnachrichten können automatisch in die Sprache des Lesers übersetzt werden. Die Unterstützung mehrerer Übersetzungsanbieter wurde vorbereitet.

---
id: 2026-05-05-template-tickets
date: 2026-05-05
title: Vorlagen-Tickets und Checkboxen
author: Tim Falken

Vorlagen-Tickets erleichtern das Erstellen von Standardmeldungen. Nachrichten unterstützen interaktive Checkboxen. Kategorien auf der Einstellungsseite können neu sortiert werden.

---
id: 2026-05-05-timezone
date: 2026-05-05
title: Zeitzone und Datumsangaben
author: Omer Pesket

Datums- und Zeitangaben in Anwendung und API folgen nun konsequent der konfigurierten Zeitzone.

---
id: 2026-04-30-multi-user-keys
date: 2026-04-30
title: Mehrere Teilnehmer und Tastensymbole
author: Tim Falken

Tickets können mehrere Teilnehmer haben. In Textfeldern lassen sich Tasten und Sonderzeichen über ein Auswahlmenü schnell einfügen.

---
id: 2026-04-29-file-previews
date: 2026-04-29
title: Anhangsvorschau
author: Tim Falken

Bilder und verschiedene Dateitypen können direkt im Ticket ohne Download angezeigt werden, einschließlich Miniaturansichten und Dokumentvorschau.
