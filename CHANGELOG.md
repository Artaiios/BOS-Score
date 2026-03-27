# Changelog

Alle wichtigen Änderungen an BOS-Score werden hier dokumentiert.

## [0.9.1] – 2026-03-27

### Bugfixes

**Kritisch — HTTP 500 auf allen Seiten**
- `lib/auth.php`: Funktionsname `get_current_user()` kollidiert mit gleichnamiger PHP Built-in-Funktion (vorhanden seit PHP 4). Umbenannt zu `get_logged_in_user()` in allen betroffenen Dateien: `lib/auth.php`, `index.php`, `views/partials/header.php`, `views/consent_required.php`, `views/accept_admin_invite.php`

**Kritisch — PHP Parse Error**
- `lib/mail.php` Zeilen 146 + 198: Typografische Anführungszeichen (`„` `"`) in interpolierten PHP-Strings verursachen Parse Error. Auf sichere String-Konkatenation umgestellt
- `views/server_admin.php` Zeilen 67, 78, 90: Gleicher Fehler in `$_SESSION`-Success-Messages. Ebenfalls korrigiert

**Fehlende Datenbanktabelle**
- `user_consents` Tabelle fehlte in der initialen Migration (setup.php Schritt 1 muss erneut ausgeführt werden bei Neuinstallationen)

---

## [0.9.0] – 2026-03-27

### Neu (Fork von LAZ Übungs-Tracker v1.7.3)

**Projekt**
- Fork als eigenständiges Projekt unter dem Namen "BOS-Score"
- Universell einsetzbar für alle BOS-Organisationen (Feuerwehr, THW, DRK, DLRG, etc.)
- "Strafkasse" → "Team-Kasse" (geschlechtsneutral)

**Authentifizierungssystem**
- Magic-Link-basierte Authentifizierung (kein Passwort nötig)
- Langlebige Sessions mit konfigurierbarer Lebensdauer (Standard: 30 Tage)
- Session-Verwaltung: alle aktiven Geräte einsehen und einzeln/gesamt widerrufen
- "Angemeldet bleiben"-Checkbox (explizites Opt-in)
- Rate-Limiting: max. 3 Magic Links / Stunde / E-Mail
- DSGVO-konform: IPs nur als SHA-256-Hash gespeichert

**Rollen & Berechtigungen**
- Rollenmodell: `server_admin`, `admin` (Event), `member` (Event)
- Event-Einladungssystem mit konfigurierbarem Registrierungsmodus
- Admin-Einladung per E-Mail mit Token-basierter Annahme

**DSGVO**
- Einwilligungsprotokollierung mit Versionierung
- Datenexport (Art. 15/20 DSGVO) als JSON-Download
- Soft-Delete mit konfigurierbarer Aufbewahrungsfrist
- Datenschutzerklärung als Markdown-Template mit Platzhaltern

**Setup**
- 2-Schritt-Setup: Datenbank → Server-Admin per Magic Link
- `config.example.php` als Vorlage für neue Installationen
