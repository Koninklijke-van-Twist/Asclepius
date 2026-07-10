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
