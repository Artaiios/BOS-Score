# LAZ Übungs-Tracker v1.6.0
## Freiwillige Feuerwehr Rutesheim

---

### Funktionsübersicht

**Öffentliches Dashboard:**
- Gruppenfortschritt mit Fortschrittsbalken
- Frist-Countdown-Karten für beide Fristen (konfigurierbare Namen)
- Nächster Termin mit Wetter-Vorhersage (Open-Meteo API)
- "Mein Status"-Widget (Cookie-basiert, persönliche Ampel + Strafkasse)
- Statistik-Karten (Teilnehmer, Ø Teilnahmen, absolvierte Termine, Strafkasse)
- Balkendiagramm: Teilnahmen pro Teilnehmer
- Liniendiagramm: Teilnahmen-Entwicklung über Zeit
- Terminliste mit Hervorhebung (nächster Termin orange, vergangene grau)
- Sortierbare Teilnehmer-Tabelle mit Frist-Ampeln

**Teilnehmer-Detailseite:**
- Persönliche Fortschrittsbalken für beide Fristen
- Donut-Diagramm (Anwesend / Entschuldigt / Fehlend / Ausstehend)
- Terminliste mit Hervorhebung und Entschuldigungs-Button
- Kurzfristig-Warnung bei Absage < 1h vor Termin
- Persönliche Strafenliste

**Admin-Bereich (über geheimen URL-Token):**
- Event-Verwaltung (Name, Status, Fristen mit Anzeigenamen)
- Teilnehmer verwalten (Einzeln + Bulk-Import)
- Termine verwalten (Einzeln + Bulk-Import)
- Anwesenheit eintragen (farbcodierte Buttons: Grün/Gelb/Rot)
- Strafenkatalog mit Inline-Bearbeitung und Sortierung
- Strafen zuweisen und verwalten (Soft-Delete)
- Strafkasse-Statistik (Kreisdiagramm nach Anzahl, Balkendiagramm nach Teilnehmer)
- Audit-Log mit Filter und CSV-Export
- Neue Jahrgänge erstellen

---

### Installationsanleitung

#### 1. Dateien hochladen
Lade alle Dateien per FTP auf deinen Webspace hoch:

```
/
├── .htaccess
├── config.php          ← DB-Zugangsdaten anpassen!
├── db.php
├── setup.php
├── index.php
├── api.php
├── views/
│   ├── dashboard.php
│   ├── member.php
│   ├── admin.php
│   └── partials/
│       ├── header.php
│       ├── footer.php
│       └── error.php
├── assets/
│   └── style.css
└── exports/
```

#### 2. Datenbank-Zugangsdaten eintragen
Öffne `config.php` und trage deine MySQL-Zugangsdaten ein:

```php
define('DB_HOST', 'dein-host.db.1and1.com');
define('DB_NAME', 'db123456789');
define('DB_USER', 'dbo123456789');
define('DB_PASS', 'dein-passwort');
```

#### 3. Ersteinrichtung ausführen
Rufe `https://deine-domain.de/setup.php` im Browser auf und klicke "Einrichtung starten".

Das Setup erstellt automatisch:
- Alle Datenbanktabellen (inkl. Frist-Anzeigenamen)
- Den Jahrgang "LAZ Bronze 2026" mit 35 Terminen
- Den vollständigen Strafenkatalog (7 Straftypen)

**Speichere die generierten URLs sicher ab!**

#### 4. Setup sperren
In `config.php` ändern:
```php
define('SETUP_COMPLETE', true);
```

#### 5. Teilnehmer anlegen
Öffne die Admin-URL → Tab "Teilnehmer" → Einzeln oder per Bulk-Import hinzufügen.

---

### Update von einer bestehenden Installation

Falls du bereits eine ältere Version im Einsatz hast:

1. **Backup machen** (Dateien + Datenbank)
2. **Alle Dateien überschreiben** (außer `config.php` — dort nur die Version anpassen)
3. Falls du von v1.0 kommst: `update_v1_1.php` einmalig ausführen (fügt `deadline_1_name` und `deadline_2_name` hinzu)
4. Falls du von v1.0–v1.5 kommst: `update_v1_6.php` einmalig ausführen (fügt `session_duration_hours` hinzu)
5. Migrations-Dateien nach Ausführung vom Server löschen
6. `SETUP_COMPLETE` in `config.php` muss auf `true` bleiben
7. `APP_VERSION` auf `'1.6.0'` setzen

---

### URL-Struktur

| Seite | URL |
|-------|-----|
| Dashboard | `index.php?event={public_token}` |
| Teilnehmer-Detail | `index.php?event={public_token}&member={id}` |
| Admin-Bereich | `index.php?event={public_token}&admin={admin_token}` |

---

### Technische Details

- **PHP:** 8.0+ mit PDO-MySQL
- **Datenbank:** MySQL 5.7+ / MariaDB 10.3+
- **Frontend:** Tailwind CSS 3.x (CDN), Chart.js 4.x (CDN)
- **Wetter:** Open-Meteo API (kostenlos, kein API-Key, 1h Cache)
- **Sicherheit:** PDO Prepared Statements, CSRF-Tokens, XSS-Schutz, Token-basierter Admin-Zugang

---

### Changelog

**v1.6.0** – Entschuldigungs-Logik + Übungsdauer
- Entschuldigung zurückziehen: Teilnehmer können selbst gesetzte Entschuldigungen zurückziehen, solange der Termin nicht begonnen hat
- Entschuldigen/Zurückziehen nur vor Übungsbeginn möglich (nicht mehr bei laufenden oder vergangenen Terminen)
- Vom Admin gesetzte Status (Anwesend/Fehlend) können vom Teilnehmer nicht überschrieben werden
- Konfigurierbare Übungsdauer (Standard: 3h) – bestimmt ab wann ein Termin als beendet gilt
- "Nächster Termin" wechselt erst nach Ablauf der Übungsdauer zum Folge-Termin
- Neue DB-Spalte `session_duration_hours` (Migration: `update_v1_6.php`)

**v1.5.0** – Dashboard-Erweiterung
- Frist-Countdown-Karten für beide Fristen
- Wetter-Vorhersage für nächsten Termin (Open-Meteo)
- "Mein Status"-Widget mit Cookie-basierter Teilnehmerauswahl

**v1.4.0** – Tailwind 3 Upgrade + Anwesenheits-UI
- Upgrade von Tailwind CSS 2.x auf 3.x (CDN)
- Farbcodierte Anwesenheits-Buttons (Grün/Gelb/Rot)
- Nächster-Termin-Hervorhebung im Dashboard und Teilnehmerseite

**v1.3.0** – Strafkasse-Fix + Termin-Styling
- SQL-Bug in Strafkasse-Statistik behoben
- Kreisdiagramm zeigt Anzahl statt Euro-Betrag
- Vergangene Termine ausgegraut, nächster Termin hervorgehoben

**v1.2.0** – Strafenkatalog Inline-Edit
- Straftypen direkt in der Liste bearbeiten
- Sortierfeld mit sichtbarem Label

**v1.1.0** – Konfigurierbare Frist-Namen
- Anzeigenamen für Frist 1 und Frist 2 im Admin konfigurierbar

**v1.0.0** – Erstveröffentlichung
- Vollständige LAZ-Übungsverwaltung mit Dashboard, Teilnehmer-Detail, Admin-Bereich
