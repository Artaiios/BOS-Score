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

Trage anschließend alle erforderlichen Werte in `config.php` ein. Die folgende Tabelle beschreibt **alle verfügbaren Parameter** — Pflichtfelder sind mit ⚠️ markiert:

#### 3.1 Datenbank-Zugangsdaten

| Parameter | Beschreibung | Beispiel |
|---|---|---|
| `DB_HOST` | ⚠️ Hostname des Datenbankservers. Bei IONOS **nicht** `localhost`! | `db12345678.hosting-data.io` |
| `DB_NAME` | ⚠️ Name der Datenbank | `db12345678` |
| `DB_USER` | ⚠️ Datenbankbenutzer | `dbo12345678` |
| `DB_PASS` | ⚠️ Datenbankpasswort | `geheimes_passwort` |
| `DB_CHARSET` | Zeichensatz (nicht ändern) | `utf8mb4` |

> **IONOS/1&1:** Der Datenbankhost steht im Kundencenter unter *Hosting → Datenbanken* (z.B. `db12345678.hosting-data.io`).

#### 3.2 Anwendungs-Einstellungen

| Parameter | Beschreibung | Standard |
|---|---|---|
| `APP_NAME` | Name der Anwendung, erscheint im Browser-Tab und in E-Mails | `BOS-Score` |
| `APP_VERSION` | Interne Versionsnummer (nicht manuell ändern) | `1.0.2` |
| `TIMEZONE` | PHP-Zeitzone | `Europe/Berlin` |

#### 3.3 Setup-Steuerung

| Parameter | Beschreibung | Werte |
|---|---|---|
| `SETUP_COMPLETE` | ⚠️ Muss nach der Ersteinrichtung auf `true` gesetzt werden, um `setup.php` dauerhaft zu sperren | `false` / `true` |
| `DEBUG_MODE` | Aktiviert PHP-Fehlermeldungen im Browser. **Niemals `true` auf einem Produktivsystem!** | `false` |

#### 3.4 SMTP E-Mail-Versand

| Parameter | Beschreibung | Beispiel (IONOS) |
|---|---|---|
| `SMTP_HOST` | ⚠️ Hostname des SMTP-Servers | `smtp.ionos.de` |
| `SMTP_PORT` | SMTP-Port (587 für TLS/STARTTLS, 465 für SSL) | `587` |
| `SMTP_USER` | ⚠️ SMTP-Benutzername (meist die vollständige E-Mail-Adresse) | `noreply@deine-domain.de` |
| `SMTP_PASS` | ⚠️ SMTP-Passwort | `geheimes_passwort` |
| `SMTP_FROM` | ⚠️ Absender-E-Mail-Adresse | `noreply@deine-domain.de` |
| `SMTP_FROM_NAME` | Anzeigename des Absenders in E-Mails | `BOS-Score` |
| `SMTP_ENCRYPTION` | Verschlüsselungsprotokoll | `tls` |

#### 3.5 Authentifizierung & Sessions

| Parameter | Beschreibung | Standard |
|---|---|---|
| `SESSION_LIFETIME_DAYS` | Gültigkeitsdauer einer persistenten Session (Angemeldet-bleiben) in Tagen | `30` |
| `MAGIC_LINK_EXPIRY_MINUTES` | Ablaufzeit eines Magic Links in Minuten | `30` |
| `RATE_LIMIT_MAGIC_LINKS_PER_HOUR` | Maximale Anzahl Magic-Link-Anfragen pro E-Mail-Adresse pro Stunde | `3` |
| `RATE_LIMIT_REGISTRATIONS_PER_HOUR` | Maximale Anzahl Registrierungsversuche pro Stunde | `10` |

#### 3.6 Datenschutz (DSGVO)

| Parameter | Beschreibung | Standard |
|---|---|---|
| `PRIVACY_VERSION` | ⚠️ Versionsnummer der Datenschutzerklärung. Bei Änderung der `privacy.md` erhöhen — löst erneute Zustimmung aller Nutzer aus | `1.1` |
| `PRIVACY_FILE` | Pfad zur Datenschutzerklärung (nicht ändern) | `__DIR__ . '/privacy.md'` |
| `SOFT_DELETE_RETENTION_DAYS` | Aufbewahrungsfrist gelöschter Accounts in Tagen (DSGVO Art. 17) | `30` |
| `AUDIT_LOG_RETENTION_DAYS` | Aufbewahrungsfrist für Audit-Log-Einträge in Tagen | `365` |
| `ORGANIZATION_ADDRESS` | ⚠️ Postanschrift der verantwortlichen Organisation — Pflichtangabe nach Art. 13 DSGVO, erscheint in der Datenschutzerklärung | `Hauptstraße 1, 71277 Rutesheim` |

#### 3.7 Sicherheit ⚠️

Diese beiden Parameter sind **sicherheitskritisch** und müssen vor dem produktiven Betrieb zwingend gesetzt werden.

| Parameter | Beschreibung | Hinweis |
|---|---|---|
| `HASH_SECRET` | ⚠️ Geheimer Schlüssel für HMAC-SHA-256-Hashing von IP-Adressen und Browser-Kennungen (DSGVO-konforme Pseudonymisierung). **Niemals in Git committen!** | Einmalig generieren: `php -r "echo bin2hex(random_bytes(32));"` |
| `APP_BASE_URL` | ⚠️ Absolute Basis-URL der Anwendung. Verhindert Host-Header-Injection bei der Generierung von Magic-Link-URLs. | `https://deine-domain.de/bos-score` |

> **Wichtig:** `HASH_SECRET` und `APP_BASE_URL` dürfen **ausschließlich in `config.php`** (nicht in `config.example.php`) mit echten Werten belegt werden. `config.php` muss in `.gitignore` eingetragen sein und darf niemals in ein öffentliches Repository übertragen werden.

**HASH_SECRET generieren:**
```bash
php -r "echo bin2hex(random_bytes(32));"
# Ausgabe: z.B. a3f8c2d1e9b47506f2a1c8e3d5b09f7a2e4c6d8f1b3a5e7c9d2f4b6a8c0e2f4
```

#### 3.8 Session-Cookie

| Parameter | Beschreibung | Standard |
|---|---|---|
| `AUTH_COOKIE_NAME` | Name des Session-Cookies im Browser (nur ändern wenn Konflikte entstehen) | `bos_score_session` |

---

#### Vollständiges Konfigurationsbeispiel

```php
<?php
// ── Datenbank ───────────────────────────────────────────────
define('DB_HOST',    'db12345678.hosting-data.io');
define('DB_NAME',    'db12345678');
define('DB_USER',    'dbo12345678');
define('DB_PASS',    'dein_db_passwort');
define('DB_CHARSET', 'utf8mb4');

// ── Anwendung ───────────────────────────────────────────────
define('APP_NAME',    'BOS-Score');
define('APP_VERSION', '1.0.2');
define('TIMEZONE',    'Europe/Berlin');

// ── Setup ───────────────────────────────────────────────────
define('SETUP_COMPLETE', true);   // nach Ersteinrichtung auf true!
define('DEBUG_MODE',     false);  // niemals true auf Produktivsystem!

// ── SMTP ────────────────────────────────────────────────────
define('SMTP_HOST',       'smtp.ionos.de');
define('SMTP_PORT',       587);
define('SMTP_USER',       'noreply@deine-domain.de');
define('SMTP_PASS',       'dein_smtp_passwort');
define('SMTP_FROM',       'noreply@deine-domain.de');
define('SMTP_FROM_NAME',  'BOS-Score');
define('SMTP_ENCRYPTION', 'tls');

// ── Auth & Sessions ─────────────────────────────────────────
define('SESSION_LIFETIME_DAYS',          30);
define('MAGIC_LINK_EXPIRY_MINUTES',      30);
define('RATE_LIMIT_MAGIC_LINKS_PER_HOUR', 3);
define('RATE_LIMIT_REGISTRATIONS_PER_HOUR', 10);

// ── DSGVO ───────────────────────────────────────────────────
define('PRIVACY_VERSION',          '1.1');
define('PRIVACY_FILE',             __DIR__ . '/privacy.md');
define('SOFT_DELETE_RETENTION_DAYS', 30);
define('AUDIT_LOG_RETENTION_DAYS', 365);
define('ORGANIZATION_ADDRESS',     'Hauptstraße 1, 71277 Rutesheim');

// ── Sicherheit ──────────────────────────────────────────────
// NIEMALS in Git committen!
define('HASH_SECRET',   'hier_dein_generierter_32byte_hex_schluessel');
define('APP_BASE_URL',  'https://deine-domain.de/bos-score');

// ── Cookie ──────────────────────────────────────────────────
define('AUTH_COOKIE_NAME', 'bos_score_session');
```

### 4. Setup ausführen

`https://deine-domain.de/bos-score/setup.php` im Browser öffnen:

1. **Schritt 1:** „Datenbank erstellen" — alle Tabellen werden automatisch angelegt
2. **Schritt 2:** Name, E-Mail und Organisation eingeben
3. Du erhältst einen Anmeldelink per E-Mail

### 5. Setup sperren

In `config.php` nach erfolgreicher Einrichtung:

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

- **Keine Passwörter** — Anmeldung ausschließlich per einmaligem Magic Link
- **IP-Adressen als HMAC-SHA-256-Hash** — Originale werden nie gespeichert; Rückrechnung ohne den geheimen Schlüssel nicht möglich
- **Einwilligungs-Protokollierung** — Zeitstempel + Version nach Art. 7 DSGVO
- **Datenexport** (Art. 15/20) — JSON-Download aller eigenen Daten
- **Account-Löschung** (Art. 17) — Soft-Delete mit konfigurierbarer Aufbewahrungsfrist
- **Automatische Datenlöschung** — Abgelaufene Sessions, Tokens und Audit-Log-Einträge werden automatisch bereinigt
- **Session-Verwaltung** — Nutzer sehen alle aktiven Sessions und können diese widerrufen
- **Versionierte Einwilligung** — Erneute Zustimmung bei Änderung der Datenschutzerklärung

Die mitgelieferte `privacy.md` ist eine vollständige Datenschutzerklärung gemäß Art. 13 DSGVO und enthält Platzhalter, die durch die Anwendungskonfiguration ersetzt werden (`{{APP_NAME}}`, `{{ORGANIZATION}}`, `{{ORGANIZATION_ADDRESS}}`, `{{ADMIN_EMAIL}}`, `{{RETENTION_DAYS}}`).

---

## Architektur

```
index.php              Router + Lazy-Cleanup-Trigger
api.php                API-Endpunkte (JSON, CSRF-geschützt)
db.php                 PDO-Datenbankfunktionen + Cleanup-Routine
config.php             Konfiguration + Hilfsfunktionen (nicht in Git!)
config.example.php     Konfigurationsvorlage (in Git)
setup.php              Ersteinrichtung (CSRF-geschützt)
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
| Wetter | Open-Meteo API (kostenlos, serverseitig abgefragt) |

### Rollenmodell

```
Server-Admin     → Globale Verwaltung, alle Events, Benutzerverwaltung
Event-Admin      → Event-Verwaltung (Anwesenheit, Strafen, Einladungen)
Teilnehmer       → Dashboard, Selbst-Entschuldigung, Profil
```

### Sicherheit

| Maßnahme | Details |
|---|---|
| SQL-Injection | 100% PDO Prepared Statements |
| XSS | Alle Ausgaben über `e()` (htmlspecialchars) escaped |
| CSRF | Token-basierter Schutz auf allen POST-Requests und `setup.php` |
| IP-Pseudonymisierung | HMAC-SHA-256 mit geheimem Schlüssel (`HASH_SECRET`) |
| Session-Sicherheit | HttpOnly, Secure, SameSite=Strict; Session-ID-Rotation nach Login |
| Host-Header-Injection | Feste `APP_BASE_URL` statt `$_SERVER['HTTP_HOST']` |
| HSTS | `Strict-Transport-Security` mit 1 Jahr Laufzeit |
| Content-Security-Policy | Als `Report-Only` aktiv; nach Testphase auf Enforcement umzustellen |
| Rate-Limiting | Magic Links (3/h) und Registrierungen (10/h) |
| Automatischer Cleanup | Abgelaufene Tokens, Sessions und alte Logs werden regelmäßig gelöscht |

---

## Deployment-Checkliste

Vor dem ersten produktiven Einsatz prüfen:

- [ ] `HASH_SECRET` mit echtem Zufallswert gesetzt (`php -r "echo bin2hex(random_bytes(32));"`)
- [ ] `HASH_SECRET` ist **nicht** in `config.example.php` und **nicht** in Git
- [ ] `APP_BASE_URL` auf die echte Produktiv-URL gesetzt
- [ ] `ORGANIZATION_ADDRESS` mit der Postanschrift der Organisation belegt
- [ ] `SETUP_COMPLETE = true` nach der Ersteinrichtung
- [ ] `DEBUG_MODE = false` auf dem Produktivsystem
- [ ] HTTPS aktiv und Zertifikat gültig
- [ ] `privacy.md` Platzhalter durch den Betreiber geprüft (insbesondere Aufsichtsbehörde für das jeweilige Bundesland anpassen)
- [ ] Nach 1–2 Wochen Betrieb: Browser-Konsole auf CSP-Verletzungen prüfen → `Content-Security-Policy-Report-Only` in `.htaccess` auf `Content-Security-Policy` umstellen

---

## Häufige Probleme

**HTTP 500 / Weiße Seite**
`DEBUG_MODE` in `config.php` vorübergehend auf `true` setzen. Häufigste Ursache: `HASH_SECRET` oder `APP_BASE_URL` fehlen in `config.php` — den Abschnitt aus `config.example.php` vollständig kopieren.

**E-Mail kommt nicht an**
Bei IONOS: `smtp.ionos.de`, Port `587`, `SMTP_ENCRYPTION = 'tls'`. Benutzername = vollständige E-Mail-Adresse. TLS und SSL/465 sind häufige Fehlerquellen.

**DB-Host ist nicht `localhost`**
Bei IONOS/1&1 steht der Host im Kundencenter unter *Hosting → Datenbanken* (z.B. `db12345678.hosting-data.io`).

**Magic Link kommt nicht an**
Prüfen ob der SMTP-Server die Absender-Domain erlaubt. Manche Provider erlauben nur das Versenden von E-Mails der eigenen Domain als Absender.

**Datenschutzerklärung zeigt `{{ORGANIZATION_ADDRESS}}`**
Den Parameter `ORGANIZATION_ADDRESS` in `config.php` mit der echten Postanschrift belegen und sicherstellen, dass der Placeholder in der Rendering-Logik ersetzt wird.

**Nutzer werden nach Update zur Zustimmung aufgefordert**
Das ist gewollt: Bei Erhöhung von `PRIVACY_VERSION` werden alle bestehenden Nutzer beim nächsten Login zur erneuten Zustimmung aufgefordert.

---

## Lizenz

MIT License — siehe [LICENSE](LICENSE)

PHPMailer steht unter LGPL 2.1.

---

## Changelog

Siehe [CHANGELOG.md](CHANGELOG.md)
