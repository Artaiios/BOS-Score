# Datenschutzerklärung

**{{APP_NAME}}** — Version {{PRIVACY_VERSION}}

---

## 1. Verantwortlicher

Verantwortlich für die Datenverarbeitung im Sinne der DSGVO ist:

**{{ORGANIZATION}}**

Kontakt: {{ADMIN_EMAIL}}

---

## 2. Zweck der Datenverarbeitung

{{APP_NAME}} ist eine Webanwendung zur Verwaltung von Übungen und Leistungsabzeichen für Organisationen im Bereich der Behörden und Organisationen mit Sicherheitsaufgaben (BOS). Die Verarbeitung personenbezogener Daten erfolgt ausschließlich zu folgenden Zwecken:

- Verwaltung der Teilnahme an Übungsterminen
- Kommunikation organisatorischer Informationen an Teilnehmer
- Authentifizierung und Zugangssteuerung
- Dokumentation der Anwesenheit und Teilnahmefortschritt

---

## 3. Rechtsgrundlage

Die Verarbeitung erfolgt auf Grundlage von:

- **Art. 6 Abs. 1 lit. a DSGVO** (Einwilligung): Du gibst bei der Registrierung deine ausdrückliche Einwilligung zur Verarbeitung deiner Daten. Diese Einwilligung kannst du jederzeit widerrufen.
- **Art. 6 Abs. 1 lit. b DSGVO** (Vertragserfüllung): Die Verarbeitung ist erforderlich, um die Teilnahme an Events zu organisieren und zu dokumentieren.

---

## 4. Welche Daten werden verarbeitet?

### 4.1 Account-Daten

- **Anzeigename** (von dir eingegeben)
- **E-Mail-Adresse** (für die Anmeldung per Magic Link)
- **Zeitpunkt der Registrierung**
- **Zeitpunkt und Version der Datenschutz-Einwilligung**

### 4.2 Nutzungsdaten

- **Anwesenheit bei Übungsterminen** (anwesend, entschuldigt, abwesend)
- **Entschuldigungen** (Zeitpunkt, ob durch dich oder durch einen Admin gesetzt)
- **Zugewiesene Strafen** (gemäß Strafenkatalog des jeweiligen Events)

### 4.3 Technische Daten

- **IP-Adresse** — wird ausschließlich als kryptografischer Hash (SHA-256) gespeichert. Die originale IP-Adresse wird zu keinem Zeitpunkt in der Datenbank abgelegt.
- **Browser-Informationen (User-Agent)** — werden ausschließlich als Hash gespeichert. Zusätzlich wird ein allgemeiner Gerätetyp abgeleitet (z.B. „Mobile/Android", „Desktop/Windows"), um dir eine verständliche Session-Übersicht zu ermöglichen.
- **Session-Daten** — werden serverseitig in der Datenbank gespeichert und können von dir jederzeit eingesehen und widerrufen werden.

### 4.4 Daten, die nicht verarbeitet werden

- Passwörter (es gibt kein Passwort-System)
- Standortdaten
- Tracking- oder Analyse-Cookies
- Daten für Werbezwecke

---

## 5. E-Mail-Nutzung

Deine E-Mail-Adresse wird **ausschließlich** für den Versand von Anmeldelinks (Magic Links) und systemrelevanten Benachrichtigungen verwendet. Es werden keine Newsletter, Werbemails oder sonstigen Nachrichten gesendet, die über den unmittelbaren Betrieb der Anwendung hinausgehen.

Systemrelevante Benachrichtigungen umfassen:

- Anmeldelinks (Magic Links) zur Authentifizierung
- Bestätigung der Registrierung
- Information über Hinzufügen zu einem neuen Event
- Bestätigung der Account-Löschung
- Einladungen als Event-Administrator

---

## 6. Cookies

{{APP_NAME}} verwendet **ausschließlich ein technisch notwendiges Session-Cookie** zur Authentifizierung. Dieses Cookie enthält einen zufällig generierten Token und dient dazu, dich nach der Anmeldung per Magic Link wiederzuerkennen.

- **Name:** `bos_score_session`
- **Zweck:** Authentifizierung
- **Lebensdauer:** Bis zum Schließen des Browsers (Standard) oder bis zu 30 Tage (wenn „Angemeldet bleiben" aktiviert wurde)
- **Flags:** HttpOnly, SameSite=Strict, Secure (bei HTTPS)

Es werden **keine** Tracking-, Analyse- oder Werbe-Cookies verwendet. Es findet **kein** Tracking durch Drittanbieter statt.

---

## 7. Datenweitergabe an Dritte

Personenbezogene Daten werden **nicht** an Dritte weitergegeben, mit folgenden Ausnahmen:

- **E-Mail-Versand:** Der Versand von Anmeldelinks erfolgt über einen SMTP-Server, der vom Betreiber konfiguriert wird. Dabei werden E-Mail-Adresse und Nachrichteninhalt an den jeweiligen E-Mail-Dienstleister übermittelt.
- **Wetter-API:** Für die optionale Wetteranzeige im Dashboard werden Koordinaten (keine personenbezogenen Daten) an die Open-Meteo API übermittelt.

---

## 8. Speicherdauer

- **Account-Daten:** Werden gespeichert, solange dein Account aktiv ist.
- **Nach Account-Löschung:** Deine Daten werden zunächst als „gelöscht" markiert (Soft-Delete) und nach einer Aufbewahrungsfrist von **{{RETENTION_DAYS}} Tagen** endgültig aus der Datenbank entfernt.
- **Anwesenheitsdaten:** Werden für die Dauer des jeweiligen Events gespeichert und bei Löschung des Events automatisch mit entfernt.
- **Magic-Link-Token:** Werden als Hash gespeichert und verfallen automatisch nach 30 Minuten.
- **Session-Daten:** Verfallen automatisch nach Ablauf der konfigurierten Lebensdauer.
- **Rate-Limiting-Daten:** Werden automatisch nach 24 Stunden gelöscht.

---

## 9. Deine Rechte (Betroffenenrechte)

Du hast gemäß DSGVO folgende Rechte:

### 9.1 Recht auf Auskunft (Art. 15 DSGVO)

Du kannst jederzeit eine Übersicht aller über dich gespeicherten Daten anfordern. In {{APP_NAME}} steht dir dafür eine **automatische Exportfunktion** im Profil zur Verfügung, die alle deine Daten als JSON-Datei herunterlädt.

### 9.2 Recht auf Berichtigung (Art. 16 DSGVO)

Du kannst deinen Anzeigenamen jederzeit über die Profilseite ändern.

### 9.3 Recht auf Löschung (Art. 17 DSGVO)

Du kannst deinen Account jederzeit über die Profilseite selbst löschen. Die Löschung erfolgt zunächst als Soft-Delete (Daten werden als gelöscht markiert). Nach Ablauf der Aufbewahrungsfrist von {{RETENTION_DAYS}} Tagen werden die Daten endgültig entfernt.

### 9.4 Recht auf Datenübertragbarkeit (Art. 20 DSGVO)

Du kannst alle deine Daten über die Exportfunktion im Profil in einem maschinenlesbaren Format (JSON) herunterladen.

### 9.5 Recht auf Widerruf der Einwilligung (Art. 7 Abs. 3 DSGVO)

Du kannst deine Einwilligung zur Datenverarbeitung jederzeit widerrufen, indem du deinen Account löschst. Der Widerruf berührt nicht die Rechtmäßigkeit der bis dahin erfolgten Verarbeitung.

### 9.6 Beschwerderecht (Art. 77 DSGVO)

Du hast das Recht, dich bei einer Aufsichtsbehörde zu beschweren, wenn du der Ansicht bist, dass die Verarbeitung deiner Daten gegen die DSGVO verstößt.

---

## 10. Session-Transparenz

Du kannst im Profil jederzeit alle aktiven Sessions (angemeldeten Geräte) einsehen. Zu jeder Session werden angezeigt:

- Gerätetyp (z.B. „Mobile/Android · Chrome")
- Zeitpunkt der Anmeldung
- Zeitpunkt der letzten Aktivität
- Ablaufzeitpunkt

Du kannst einzelne Sessions oder alle Sessions gleichzeitig widerrufen (abmelden).

---

## 11. Technische Sicherheitsmaßnahmen

- Alle Authentifizierungs-Token werden ausschließlich als kryptografische Hashes (SHA-256) gespeichert.
- IP-Adressen und Browser-Kennungen werden ausschließlich als Hash gespeichert — ein Rückschluss auf die originalen Daten ist nicht möglich.
- Magic Links sind einmalig verwendbar und verfallen nach 30 Minuten.
- Alle Formulare sind gegen Cross-Site Request Forgery (CSRF) geschützt.
- Datenbankzugriffe erfolgen ausschließlich über parametrisierte Abfragen (Prepared Statements).
- Session-Cookies sind mit den Flags HttpOnly und SameSite=Strict geschützt.

---

## 12. Änderungen dieser Datenschutzerklärung

Bei Änderungen der Datenschutzerklärung wird die Versionsnummer erhöht. Du wirst beim nächsten Login aufgefordert, der aktualisierten Fassung erneut zuzustimmen. Ohne erneute Zustimmung ist eine weitere Nutzung nicht möglich.

---

*Hinweis: Diese Datenschutzerklärung wurde sorgfältig erstellt, stellt jedoch keine Rechtsberatung dar. Wir empfehlen, die Erklärung vor dem produktiven Einsatz durch einen Datenschutzbeauftragten oder Rechtsanwalt prüfen zu lassen.*
