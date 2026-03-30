# Changelog

Alle nennenswerten Änderungen an BOS-Score werden hier dokumentiert.

## v1.0.1 (2026-03-30)

### Sicherheits- und Datenschutz-Update

Dieses Release behebt alle Befunde aus dem IT-Sicherheits- und Datenschutz-Audit vom 30.03.2026. Es wird dringend empfohlen, vor dem produktiven Einsatz auf diese Version zu aktualisieren.

**Kritisch behoben**
- IP-Hashing auf HMAC-SHA256 mit geheimem Schlüssel umgestellt – die bisherige SHA-256-Methode ohne Salt war per Rainbow-Table reversibel. Betrifft alle fünf Tabellen mit IP-Hashes. Erfordert eine neue Konstante `HASH_SECRET` in config.php. (SEC-01, DSGVO-01)
- CSRF-Schutz in setup.php eingebaut – beide Formulare (Datenbank erstellen, Admin anlegen) sind jetzt gegen Cross-Site-Request-Forgery geschützt. (SEC-02)

**Hoch behoben**
- Host-Header-Injection in get_base_url() behoben – Magic-Link-URLs werden jetzt aus der neuen Konstante `APP_BASE_URL` erzeugt statt aus dem manipulierbaren HTTP_HOST-Header. Fallback auf den alten Mechanismus für Entwicklung/Setup. (SEC-04)
- HSTS-Header ergänzt und automatische HTTPS-Umleitung eingebaut – schützt besonders in halboffenen Netzwerken wie Gerätehaus-WLANs. (SEC-06)
- X-XSS-Protection auf "0" gesetzt – der alte Wert "1; mode=block" war deprecated und konnte in älteren Browsern selbst zum Angriffsvektor werden. (SEC-05)
- Content-Security-Policy als Report-Only eingeführt – erlaubt gezieltes Testen im Produktivbetrieb, bevor der Header scharf geschaltet wird. Alle CDN-Quellen und die Open-Meteo-API sind hinterlegt. (SEC-03)
- Automatische Datenbereinigung implementiert – Lazy-Cleanup (1% der Requests) löscht abgelaufene Magic Links, Sessions, Rate-Limits und soft-gelöschte Accounts nach Ablauf der Aufbewahrungsfrist. (DSGVO-02)

**Mittel behoben**
- Session Fixation verhindert – session_regenerate_id(true) wird jetzt nach jedem Magic-Link-Login aufgerufen. (SEC-07)
- GET-Aktionen in der API auf eine Allowlist beschränkt – nur noch explizit als Leseoperation deklarierte Endpunkte (geocode) sind per GET erreichbar, alles andere gibt 405 zurück. (SEC-08)
- Regex-Validierung für den Datenbanknamen vor exec() in setup.php eingebaut – Defense-in-Depth gegen manipulierte config.php. (SEC-09)
- DEBUG_MODE Runtime-Guard ergänzt – loggt eine Warnung wenn DEBUG_MODE auf einem Nicht-Localhost-System aktiv ist. (SEC-10)
- Audit-Log-Aufbewahrungsfrist definiert – neue Konstante `AUDIT_LOG_RETENTION_DAYS` (Standard: 365 Tage), automatische Bereinigung über die Cleanup-Routine. (DSGVO-04)
- Datenschutzerklärung um alle externen Dienste erweitert – Open-Meteo, Tailwind CSS CDN und Chart.js CDN sind jetzt als Datenempfänger dokumentiert. (DSGVO-03)
- MySQL-Timezone von +01:00 (falsch in Sommerzeit) auf UTC (+00:00) umgestellt. (CODE-01)

**Niedrig / Informativ**
- PHP-seitige Eingabelängenprüfung mit mb_substr() und filter_var() in create_member() und create_user_account() eingebaut. (DSGVO-05)

### Upgrade-Hinweise

Beim Update von v1.0.0 auf v1.0.1 müssen drei neue Konstanten in config.php ergänzt werden:

```php
// HMAC-Secret generieren: php -r "echo bin2hex(random_bytes(32));"
define('HASH_SECRET', 'dein_generierter_wert');
define('APP_BASE_URL', 'https://deine-domain.de/bos-score');
define('AUDIT_LOG_RETENTION_DAYS', 365);
```

Nach dem Update sind bestehende IP-Hashes und Session-Hashes ungültig. Rate-Limits laufen innerhalb von 24 Stunden aus, aktive Sessions erfordern eine erneute Anmeldung per Magic Link. Es gehen keine Daten verloren.

## v1.0.0 (2026-03-30)

### Erstveröffentlichung

BOS-Score ist ein Fork des [LAZ Übungs-Tracker](https://github.com/Artaiios/Feuerwehr_LAZ_Tool), generalisiert für alle BOS-Organisationen (THW, DRK, DLRG, Feuerwehr und andere).

### Kernfunktionen

**Authentifizierung & DSGVO**
- Passwortlose Anmeldung per Magic Link (kein Passwort nötig)
- DSGVO-konform: IP-Hashing, Einwilligungs-Protokollierung, Datenexport, Account-Löschung
- Rollenmodell: Server-Admin → Event-Admin → Teilnehmer
- Rate-Limiting gegen Brute-Force
- Session-Verwaltung mit Geräte-Erkennung

**Event-Verwaltung**
- Multi-Event-Betrieb auf einem Server
- Event-Theming (eigene Primärfarbe pro Event)
- Einladungssystem: Links oder direkte E-Mail-Einladung
- Selbstregistrierung mit Datenschutz-Einwilligung (DSGVO-konform)
- Admin kann sich selbst als Teilnehmer registrieren

**Dashboard**
- Frist-Countdown mit Tagen und verbleibenden Terminen
- Wetter-Widget (Open-Meteo API, serverseitig gecacht)
- Teilnahme-Charts (pro Teilnehmer + Zeitverlauf)
- Persönlicher Fortschrittsbereich (Mein Status)
- Dashboard-Ankündigungen (zeitlich begrenzt, durch Admin konfigurierbar)
- Entschuldigung direkt in der Terminliste (kein Umweg über Profil)
- Sortierbare Teilnehmer-Tabelle mit Frist-Status

**Admin-Bereich (10 Tabs)**
- Übersicht: Statistik-Karten, Ankündigungs-Verwaltung, Event-URL
- Einladungen: Links erstellen/deaktivieren, direkte E-Mail-Einladung, Registrierungen bestätigen/ablehnen
- Anwesenheit: Aufklappbare Panels pro Termin, Ein-Klick-Erfassung
- Teilnehmer: Name, Funktion, E-Mail, Aktiv-Status bearbeiten
- Team-Kasse: Strafen zuweisen/löschen, Donut- und Balken-Charts
- Termine: Einzelerstellung, Bearbeitung, Bulk-Import (DD.MM.YYYY HH:MM Kommentar)
- Rollen: Anlegen, pro Teilnehmer zuweisen, Verfügbarkeits-Anzeige
- Admins: Event-Admins verwalten und einladen
- Einstellungen: Sub-Tabs Allgemein (Name, Status, Fristen, Farbe, Kontakt-E-Mail) / Strafenkatalog (Anlegen, inline bearbeiten, Sortierung) / Standort (Geocoding per Open-Meteo)
- Audit-Log: Filterbarer Log aller Admin-Aktionen mit CSV-Export

**Server-Administration (5 Tabs)**
- Übersicht: Globale Statistiken, Server-Admin-Liste, letzte Aktivitäten, System-Info
- Events: Event erstellen, archivieren, reaktivieren, löschen. Event-Admins pro Event sichtbar
- Server-Logs: Globales Audit-Log mit Benutzer, Event, Typ-Filter und CSV-Export
- Einstellungen: Organisationsname und Kontakt-E-Mail
- Benutzerverwaltung: Server-Admins hinzufügen/entfernen, alle registrierten Accounts mit Rollen

**Breadcrumb-Navigation**
- Klare Hierarchie im Header: 🏠 BOS-Score / Event-Name / ⚙️ Verwaltung (oder 👤 Teilnehmer)
- Kontextabhängige Links zum Wechseln zwischen Ebenen

### Technische Details

- 30+ PHP-Dateien, ~7.000 Zeilen Produktionscode
- 20 Datenbanktabellen
- PHP 8.0+ (kein Framework, kein Composer)
- Tailwind CSS 3.x und Chart.js 4.x (CDN)
- PHPMailer 6.9.2 (LGPL 2.1)
- Deployment auf IONOS Shared Webspace getestet
