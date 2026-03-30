# Changelog

Alle nennenswerten Änderungen an BOS-Score werden hier dokumentiert.

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
