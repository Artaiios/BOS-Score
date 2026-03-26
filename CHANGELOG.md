# Changelog

Alle relevanten Änderungen am LAZ Übungs-Tracker werden in dieser Datei dokumentiert.

## [1.7.2] – 2026-03-26

### Hinzugefügt
- **Rollen-Feature:** Neuer Admin-Tab „Rollen" mit Toggle zum Aktivieren/Deaktivieren
  - Rollenkatalog: beliebig viele Rollen definierbar (z.B. Gruppenführer, Maschinist, Mannschaft)
  - Mehrere Rollen pro Teilnehmer zuweisbar (Toggle-Buttons, Live-Save)
  - Anwesenheits-Tab: kompakte Rollen-Badges pro Termin (z.B. `GF 1/2 ✅ Masch 0/1 ❌`)
  - Dashboard Terminliste: Rollen-Zeile unter jedem Termin
  - Dashboard Teilnehmer-Tabelle: Rollennamen neben dem Teilnehmernamen
  - Teilnehmer-Detail: Rollen als Badges unter dem Namen
  - Alle Rollen-Anzeigen nur sichtbar wenn vom Admin aktiviert
- Neue DB-Tabellen: `roles`, `member_roles`
- Neue DB-Spalte: `roles_enabled` (in `events`)
- Neue API-Endpunkte: `add_role`, `delete_role`, `set_member_roles`, `toggle_roles`

## [1.7.1] – 2026-03-25

### Geändert
- **Archiv-Lesemodus:** Archivierte Events werden im Lesemodus angezeigt statt komplett blockiert
  - Gelbes Banner „Archiviert" auf Dashboard, Teilnehmerseite und Admin
  - Admin: Reaktivierungs-Button sichtbar, alle anderen Eingaben/Buttons ausgegraut
  - API blockiert Schreibzugriffe bei archivierten Events (außer Reaktivierung und CSV-Export)
  - Teilnehmer-Detailseite: Entschuldigungs-Buttons bei archivierten Events deaktiviert

## [1.7.0] – 2026-03-25

### Hinzugefügt
- **Server-Admin:** Neue Verwaltungsebene über `admin.php?token={server_token}` mit eigener grauer Navbar
  - Events erstellen, URLs einsehen, Events löschen
  - Globaler Organisationsname (pro Event überschreibbar)
  - Administrator E-Mail (wird im Footer und auf Fehlerseiten angezeigt)
  - Öffentliche Startseite optional aktivierbar (zeigt alle aktiven Events)
  - Globales Audit-Log über alle Events hinweg
  - Event-Statistiken (Teilnehmer, Termine, Teilnahmen, Strafkasse)
- **Event-Templates:** Beim Erstellen eines neuen Events kann der Strafenkatalog von einem bestehenden Event kopiert werden
- **Frist 1 optional:** Zwischenziel (Frist 1) kann pro Event aktiviert/deaktiviert werden – wird überall ausgeblendet wenn deaktiviert
- **Öffentliche Startseite:** `index.php` ohne Event-Token zeigt optional alle aktiven Events als Kachelübersicht
- **Admin E-Mail:** Kontaktadresse wird im Footer aller Seiten und auf Fehlerseiten angezeigt
- Neue Dateien: `admin.php`, `views/server_admin.php`, `views/overview.php`
- Neue DB-Tabelle: `server_config` (Key-Value-Store für globale Einstellungen)
- Neue DB-Spalten: `organization_name`, `deadline_1_enabled` (in `events`)

### Geändert
- **Event-Erstellung:** Nur noch über Server-Admin möglich (nicht mehr über Event-Admin)
- **Einstellungen (Event-Admin):** Einspaltiges Layout, Hinweis auf Server-Admin mit E-Mail-Link oben
- **Hauptfrist oben:** Frist 2 (Abnahme/Finale) steht jetzt über dem optionalen Zwischenziel (Frist 1) im Einstellungsformular
- **Setup neu:** Fragt nur noch Organisationsname und E-Mail ab, erstellt Server-Admin-Token – keine Events/Termine mehr hardcoded
- **Dynamischer Organisationsname:** Header, Footer, Dashboard-Untertitel, Browser-Tab-Titel – kein hardcoded „Feuerwehr Rutesheim" mehr
- **Fehlerseite:** Zeigt Admin-E-Mail als Kontaktmöglichkeit
- **Strafen-Template:** „Handynutzung" gekürzt, Sortierung auf 10er-Stellen (10–70)
- Alle Formulare nutzen `onclick`-Buttons statt `<form onsubmit>` (behebt async-Probleme)

### Behoben
- **Kritisch:** `createEvent` kollidierte mit nativer DOM-Methode `document.createEvent()` – umbenannt zu `createNewEvent`
- **Kritisch:** `<form onsubmit="return asyncFunc(event)">` funktioniert nicht mit async-Funktionen – alle Formulare auf `<div>` + `onclick` umgestellt
- SQL-Query `get_event_stats_overview()` lieferte `public_token`/`admin_token` nicht mit

## [1.6.3] – 2026-03-25

### Geändert
- **Live-Save bei Anwesenheit:** Jeder Klick auf einen Status-Button speichert sofort nur diesen einen Eintrag – kein Speichern-Button mehr
- **Keine Überschreibung:** Selbst-Entschuldigungen werden beim Admin-Speichern nicht mehr überschrieben
- **Entschuldigt-Labels:** „🟡 selbst entsch." oder „🔵 durch Admin"
- Bestätigung „✓ gespeichert" nach jedem Live-Save

## [1.6.2] – 2026-03-25

### Geändert
- **Toggle-Verhalten:** Klick auf aktiven Status-Button setzt Status zurück (kein Status gewählt)
- API: Leerer Status löscht den Attendance-Eintrag aus der Datenbank

## [1.6.1] – 2026-03-25

### Hinzugefügt
- **Anwesenheits-Tab neu:** Aufklappbare Terminliste statt Dropdown, mit Zählern pro Termin
- **Wetter-Standort konfigurierbar** unter Admin → Einstellungen (Open-Meteo Geocoding)
- Neue DB-Spalten: `weather_location`, `weather_lat`, `weather_lng`

## [1.6.0] – 2026-03-25

### Hinzugefügt
- **Entschuldigung zurückziehen** (solange Termin nicht begonnen hat)
- **Konfigurierbare Übungsdauer** (Standard: 3h)
- Neue Hilfsfunktionen: `is_session_in_future()`, `is_session_ended()`, `get_next_session()`, `can_member_change_excuse()`
- Neue DB-Spalte: `session_duration_hours`

### Geändert
- Entschuldigen nur vor Übungsbeginn möglich
- Admin-gesetzte Status nicht vom Teilnehmer überschreibbar
- „Nächster Termin" wechselt erst nach Ablauf der Übungsdauer

## [1.5.0] – 2026-03-25

### Hinzugefügt
- Frist-Countdown-Karten für beide Fristen
- Wetter-Vorhersage für nächsten Termin (Open-Meteo API)
- „Mein Status"-Widget (Cookie-basiert)

## [1.4.0] – 2026-03-25

### Geändert
- Upgrade von Tailwind CSS 2.x auf 3.x (CDN)
- Farbcodierte Anwesenheits-Buttons (Grün/Gelb/Rot)
- Nächster-Termin-Hervorhebung

## [1.3.0] – 2026-03-25

### Behoben
- SQL-Bug in Strafkasse-Statistik (LEFT JOIN Summierung)

### Geändert
- Kreisdiagramm zeigt Anzahl statt Euro
- Vergangene Termine ausgegraut

## [1.2.0] – 2026-03-25

### Hinzugefügt
- Strafenkatalog Inline-Edit

## [1.1.0] – 2026-03-25

### Hinzugefügt
- Konfigurierbare Frist-Namen (`deadline_1_name`, `deadline_2_name`)

## [1.0.0] – 2026-03-25

### Hinzugefügt
- Vollständige LAZ-Übungsverwaltung mit Dashboard, Teilnehmer-Detail und Admin-Bereich
- Anwesenheitsverwaltung mit Entschuldigungsfunktion
- Strafenkatalog und Strafenverwaltung
- Diagramme (Chart.js), Frist-Tracking mit Ampelsystem
- Audit-Log mit CSV-Export
- Responsive Design (Tailwind CSS), Sicherheit (PDO, CSRF, XSS)
