# BOS-Score

**Übungsteilnahme-Tracker für Behörden und Organisationen mit Sicherheitsaufgaben**

BOS-Score ist eine Webanwendung zur Verwaltung von Übungsteilnahmen, Fristen, Anwesenheit und Team-Kasse für BOS-Organisationen wie Freiwillige Feuerwehr, THW, DRK, DLRG und andere.

Die Anwendung ist **DSGVO-konform**, **passwortlos** (Magic Links) und läuft auf einfachem **Shared Webspace** — kein Root-Server nötig.

---

## Features

### Für Teilnehmer
- **Dashboard** mit Frist-Countdown, Wetter-Anzeige und persönlichem Fortschritt
- **Selbst-Entschuldigung** direkt in der Terminliste (mit automatischer Kurzfristigkeits-Warnung)
- **Eigene Detailseite** mit Donut-Diagramm, Fortschrittsbalken und Strafen-Übersicht
- **Profil-Verwaltung** mit Session-Übersicht, Datenexport (DSGVO Art. 15/20) und Account-Löschung

### Für Event-Admins
- **Anwesenheits-Erfassung** per Klick (aufklappbare Panels pro Termin)
- **Team-Kasse** mit Strafenkatalog, Zuweisungs-Formular und Donut/Balken-Charts
- **Termin-Verwaltung** mit Einzelerstellung, Bearbeitung und Bulk-Import
- **Teilnehmer-Verwaltung** (Name, Funktion, E-Mail, Aktiv-Status)
- **Einladungs-System** per Link oder direkte E-Mail-Einladung
- **Dashboard-Ankündigungen** — zeitlich begrenzte Hinweise für Teilnehmer
- **Rollen-System** (z.B. Gruppenführer, Maschinist) mit Verfügbarkeits-Anzeige
- **Audit-Log** mit Filter und CSV-Export
- **Einstellungen** mit Sub-Tabs (Allgemein / Strafenkatalog / Standort)
- **Event-Theming** (eigene Primärfarbe pro Event)

### Für Server-Admins
- **5-Tab-Verwaltung**: Übersicht, Events, Server-Logs, Einstellungen, Benutzerverwaltung
- **Multi-Admin**: Mehrere Server-Administratoren mit Einladungssystem
- **Event-Übersicht** mit aggregierten Statistiken und Event-Admin-Anzeige
- **Globales Audit-Log** mit Filter und CSV-Export
- **Benutzerverwaltung** mit Rollen-Übersicht aller registrierten Accounts

---

## Voraussetzungen

- PHP 8.0 oder höher
- MySQL 5.7+ oder MariaDB 10.3+
- SMTP-fähiges E-Mail-Postfach
- FTP-Zugang zum Webspace

**Getestet mit:** IONOS (1&1) Shared Webspace, PHP 8.2, MariaDB 10.6

---

## Installation

### 1. Dateien hochladen

Lade alle Dateien per FTP auf deinen Webspace hoch.

### 2. PHPMailer installieren

```bash
chmod +x install_phpmailer.sh && ./install_phpmailer.sh
```

Oder manuell von [github.com/PHPMailer/PHPMailer](https://github.com/PHPMailer/PHPMailer/releases/tag/v6.9.2) die drei Dateien `PHPMailer.php`, `SMTP.php`, `Exception.php` in den Ordner `lib/phpmailer/` kopieren.

### 3. Konfiguration

```bash
cp config.example.php config.php
```

Zugangsdaten in `config.php` eintragen:

```php
// Datenbank (bei IONOS ist der Host NICHT localhost!)
define('DB_HOST', 'db12345678.hosting-data.io');
define('DB_NAME', 'db12345678');
define('DB_USER', 'dbo12345678');
define('DB_PASS', 'dein_db_passwort');

// SMTP (Beispiel: IONOS)
define('SMTP_HOST', 'smtp.ionos.de');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@deine-domain.de');
define('SMTP_PASS', 'dein_email_passwort');
define('SMTP_FROM', 'noreply@deine-domain.de');
```

### 4. Setup ausführen

`https://deine-domain.de/bos-score/setup.php` im Browser öffnen:
1. **Schritt 1:** „Datenbank erstellen" — alle 20 Tabellen werden automatisch angelegt
2. **Schritt 2:** Name, E-Mail und Organisation eingeben
3. Du erhältst einen Anmeldelink per E-Mail

### 5. Setup sperren

In `config.php`:
```php
define('SETUP_COMPLETE', true);
```

---

## Erste Schritte

### Event erstellen

1. Anmelden → Server-Administration → Tab „Events"
2. Eventname, Hauptfrist und Teilnahmen-Anzahl eingeben
3. Optional: Zwischenfrist, Event-Farbe, Event-Admins einladen

### Teilnehmer einladen

**Weg 1 — Einladungslink:** Event-Admin → Tab „Einladungen" → Link erstellen → per WhatsApp/E-Mail verteilen

**Weg 2 — Direkte E-Mail:** Event-Admin → Tab „Einladungen" → E-Mail eingeben → „Senden"

In beiden Fällen registriert sich der Teilnehmer **selbst** und stimmt der Datenschutzerklärung zu. Kein Teilnehmer wird ohne Einwilligung angelegt.

### Strafenkatalog einrichten

Event-Admin → Tab „Einstellungen" → Sub-Tab „Strafenkatalog" → Straftypen anlegen (z.B. „Unentschuldigtes Fehlen" — 5,00 €)

---

## Datenschutz (DSGVO)

- **Keine Passwörter** — Anmeldung nur per Magic Link
- **IP-Adressen als SHA-256-Hash** — Originale werden nie gespeichert
- **Einwilligungs-Protokollierung** — Zeitstempel + Version
- **Datenexport** (Art. 15/20) — JSON-Download aller eigenen Daten
- **Account-Löschung** (Art. 17) — Soft-Delete mit konfigurierbarer Aufbewahrungsfrist
- **Session-Verwaltung** — Nutzer sehen alle aktiven Sessions
- **Versionierte Einwilligung** — Erneute Zustimmung bei Änderung

---

## Architektur

```
index.php              Router
api.php                API-Endpunkte (JSON, CSRF-geschützt)
db.php                 PDO-Datenbankfunktionen
config.php             Konfiguration + Hilfsfunktionen
setup.php              Ersteinrichtung
admin.php              Server-Admin-Router
profile.php            Profil-Router

lib/auth.php           Sessions, Magic Links, Rate-Limiting, DSGVO
lib/mail.php           PHPMailer-Wrapper, E-Mail-Templates

views/
  server_admin.php       Server-Administration (5 Tabs)
  admin.php              Event-Admin (10 Tabs)
  dashboard.php          Event-Dashboard
  member.php             Teilnehmer-Detailseite
  home.php               Persönliches Dashboard
  ...
```

### Technologie-Stack

| Komponente | Technologie |
|---|---|
| Backend | PHP 8.0+ (kein Framework, kein Composer) |
| Datenbank | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | Tailwind CSS 3.x (CDN), Chart.js 4.x (CDN) |
| E-Mail | PHPMailer 6.9.2 (LGPL 2.1) |
| Auth | Magic Links + persistente Sessions |
| Wetter | Open-Meteo API (kostenlos) |

### Rollenmodell

```
Server-Admin     → Globale Verwaltung, alle Events, Benutzerverwaltung
Event-Admin      → Event-Verwaltung (Anwesenheit, Strafen, Einladungen)
Teilnehmer       → Dashboard, Selbst-Entschuldigung, Profil
```

### Sicherheit

- SQL-Injection: 100% PDO Prepared Statements
- XSS: Alle Ausgaben über `e()` escaped
- CSRF: Token-basierter Schutz auf allen POST-Requests
- Session-Cookies: HttpOnly, Secure, SameSite=Strict
- Rate-Limiting: Magic Links und Registrierungen

---

## Häufige Probleme

**HTTP 500 / Weiße Seite:** `DEBUG_MODE` in `config.php` auf `true` setzen. Häufigste Ursache: Hilfsfunktionen fehlen in `config.php` — den Abschnitt ab `// Hilfsfunktionen` aus `config.example.php` kopieren.

**E-Mail kommt nicht an:** Bei IONOS: `smtp.ionos.de`, Port `587`, TLS. Benutzername = vollständige E-Mail-Adresse.

**DB-Host ist nicht localhost:** Bei IONOS/1&1 steht der Host im Kundencenter unter „Datenbanken" (z.B. `db12345678.hosting-data.io`).

---

## Lizenz

MIT License — siehe [LICENSE](LICENSE)

PHPMailer steht unter LGPL 2.1.

---

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md)
