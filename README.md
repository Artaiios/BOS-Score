# 🔥 LAZ Übungs-Tracker

Webanwendung zur Verwaltung von Leistungsabzeichen-Übungen (LAZ) für Freiwillige Feuerwehren.

![PHP](https://img.shields.io/badge/PHP-8.0+-blue) ![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange) ![Tailwind](https://img.shields.io/badge/Tailwind_CSS-3.x-38bdf8) ![License](https://img.shields.io/badge/License-MIT-green)

## Funktionen

### Dashboard
- Gruppenfortschritt mit Fortschrittsbalken
- Frist-Countdown-Karten für zwei konfigurierbare Fristen
- Nächster Termin mit Wetter-Vorhersage (Open-Meteo API)
- „Mein Status"-Widget – Teilnehmer wählt seinen Namen, sieht persönliche Ampel + Strafkasse
- Diagramme: Teilnahmen pro Teilnehmer, Teilnahmen-Entwicklung über Zeit
- Sortierbare Teilnehmer-Tabelle mit Frist-Ampelsystem (✅ / ⚠️ / ❌)
- Terminliste mit farblicher Hervorhebung (nächster Termin, vergangene Termine)

  <img width="1274" height="899" alt="Bildschirmfoto 2026-03-25 um 00 30 01" src="https://github.com/user-attachments/assets/6037e5f1-a948-43ae-948a-79e87aed77ab" />

### Teilnehmer-Detailseite
- Persönliche Fortschrittsbalken für beide Fristen
- Donut-Diagramm (Anwesend / Entschuldigt / Fehlend / Ausstehend)
- Entschuldigungs-Button mit automatischer Kurzfristig-Warnung (< 1h vor Übungsbeginn)
- Entschuldigung zurückziehen (solange Termin nicht begonnen hat)
- Persönliche Strafenliste

<img width="1269" height="919" alt="Bildschirmfoto 2026-03-25 um 00 36 25" src="https://github.com/user-attachments/assets/302e06e9-0944-4af0-9dfa-75c9cecc7d20" />

### Admin-Bereich
- Event-Verwaltung (Name, Status, Fristen, Übungsdauer, Wetter-Standort)
- Teilnehmer verwalten (Einzeln + Bulk-Import)
- Termine verwalten (Einzeln + Bulk-Import)
- Anwesenheit eintragen – aufklappbare Terminliste mit Live-Save pro Teilnehmer
- Strafenkatalog mit Inline-Bearbeitung und Sortierung
- Strafen zuweisen und verwalten (Soft-Delete)
- Strafkasse-Statistik (Kreisdiagramm + Balkendiagramm)
- Audit-Log mit Filtern und CSV-Export
- Neue Jahrgänge erstellen

<img width="1286" height="799" alt="Bildschirmfoto 2026-03-25 um 00 29 10" src="https://github.com/user-attachments/assets/0f976c85-f57a-4038-9b01-3266a6e9375b" />

<img width="1309" height="682" alt="Bildschirmfoto 2026-03-25 um 00 28 31" src="https://github.com/user-attachments/assets/02997895-a4d8-42aa-aedf-772e1b793455" />

## Technische Details

| Komponente | Technologie |
|---|---|
| Backend | PHP 8.0+ (reines PHP, kein Framework) |
| Datenbank | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | Tailwind CSS 3.x (CDN), Chart.js 4.x (CDN) |
| Wetter | Open-Meteo API (kostenlos, kein API-Key) |
| Hosting | Shared Webspace (z.B. IONOS/1&1), kein Node.js nötig |

**Sicherheit:** PDO Prepared Statements, CSRF-Tokens, XSS-Schutz, Token-basierter Admin-Zugang (kein Login).

## Installation

### 1. Dateien hochladen

Lade alle Dateien per FTP auf deinen Webspace hoch.

### 2. Konfiguration

Kopiere `config.example.php` zu `config.php` und trage deine Datenbank-Zugangsdaten ein:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'laz_tracker');
define('DB_USER', 'dein_user');
define('DB_PASS', 'dein_passwort');
```

### 3. Ersteinrichtung

Rufe `https://deine-domain.de/setup.php` im Browser auf. Das Setup erstellt:
- Alle Datenbanktabellen
- Deinen ersten Jahrgang (Name und Fristen wählst du im Formular)
- Einen Standard-Strafenkatalog

**Speichere die generierten URLs (öffentlich + Admin) sicher ab!**

### 4. Setup sperren

In `config.php` setzen:
```php
define('SETUP_COMPLETE', true);
```

### 5. Loslegen

Über den Admin-Bereich kannst du nun Teilnehmer, Termine und Strafen verwalten. Die öffentliche URL gibst du an deine Teilnehmer weiter.

## Dateistruktur

```
├── config.example.php     # Konfigurations-Template
├── db.php                 # Datenbankverbindung & Funktionen
├── setup.php              # Ersteinrichtung
├── index.php              # Router
├── api.php                # AJAX-Endpunkte
├── views/
│   ├── dashboard.php      # Öffentliches Dashboard
│   ├── member.php         # Teilnehmer-Detailseite
│   ├── admin.php          # Admin-Bereich
│   └── partials/
│       ├── header.php     # Gemeinsamer Header
│       ├── footer.php     # Gemeinsamer Footer
│       └── error.php      # Fehlerseite
├── assets/
│   └── style.css          # Ergänzende Styles
├── exports/               # Temporäre CSV-Exporte
├── .htaccess              # Apache-Konfiguration
└── CHANGELOG.md           # Versionshistorie
```

## URL-Struktur

| Seite | URL |
|---|---|
| Dashboard | `index.php?event={public_token}` |
| Teilnehmer-Detail | `index.php?event={public_token}&member={id}` |
| Admin-Bereich | `index.php?event={public_token}&admin={admin_token}` |

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md) für die vollständige Versionshistorie.

## Lizenz

MIT License – siehe [LICENSE](LICENSE)
