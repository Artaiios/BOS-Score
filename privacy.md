# Datenschutzerklärung

**{{APP_NAME}}** — Version {{PRIVACY_VERSION}}

---

## 1. Verantwortlicher

Verantwortlich für die Datenverarbeitung im Sinne der DSGVO ist:

**{{ORGANIZATION}}**  
{{ORGANIZATION_ADDRESS}}

Kontakt: {{ADMIN_EMAIL}}

Sofern für die verantwortliche Stelle ein Datenschutzbeauftragter bestellt ist, ist dieser unter derselben Adresse erreichbar.

---

## 2. Zweck der Datenverarbeitung

{{APP_NAME}} ist eine Webanwendung zur Verwaltung von Übungsteilnahmen, Anwesenheiten und dem organisationsinternen Strafenkatalog für Organisationen im Bereich der Behörden und Organisationen mit Sicherheitsaufgaben (BOS). Die Verarbeitung personenbezogener Daten erfolgt ausschließlich zu folgenden Zwecken:

- Verwaltung der Teilnahme an Übungsterminen und Erfassung von Anwesenheiten
- Verwaltung des Strafenkatalogs und der Teamkasse
- Kommunikation organisatorischer Informationen an Teilnehmer
- Authentifizierung und Zugangssteuerung
- Dokumentation des Teilnahmefortschritts und Nachvollziehbarkeit von Verwaltungsaktionen (Audit-Log)

---

## 3. Rechtsgrundlage

Die Verarbeitung erfolgt auf Grundlage von:

- **Art. 6 Abs. 1 lit. a DSGVO** (Einwilligung): Du gibst bei der Registrierung deine ausdrückliche Einwilligung zur Verarbeitung deiner Daten. Diese Einwilligung kannst du jederzeit widerrufen, ohne dass dir daraus Nachteile entstehen. Der Widerruf berührt nicht die Rechtmäßigkeit der bis dahin erfolgten Verarbeitung.

- **Art. 6 Abs. 1 lit. f DSGVO** (Berechtigte Interessen): Soweit die Verarbeitung über die eingewilligten Zwecke hinausgeht — insbesondere die Führung eines Audit-Logs zur Nachvollziehbarkeit von Verwaltungsaktionen — erfolgt sie auf Grundlage des berechtigten Interesses der verantwortlichen Stelle an der ordnungsgemäßen und nachvollziehbaren Dokumentation des Organisationsbetriebs.

*Hinweis für Betreiber:* Für öffentlich-rechtlich organisierte Feuerwehren kann ergänzend oder alternativ Art. 6 Abs. 1 lit. e DSGVO (Wahrnehmung einer Aufgabe im öffentlichen Interesse gemäß dem jeweils geltenden Landes-Feuerwehrgesetz) als Rechtsgrundlage in Betracht kommen. Dieser Hinweis ist vor dem produktiven Einsatz durch einen Datenschutzbeauftragten oder Rechtsanwalt zu prüfen und gegebenenfalls anzupassen.

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
- **Zugewiesene Systemrolle** (Teilnehmer, Event-Administrator, Server-Administrator)
- **Zugewiesene Funktionsrolle innerhalb eines Events**, sofern vom Betreiber konfiguriert (z.B. Gruppenführer, Maschinist, Atemschutzträger)

### 4.3 Technische Daten

- **IP-Adresse** — wird ausschließlich als kryptografischer HMAC-Hash (SHA-256 mit geheimem Schlüssel) gespeichert. Die originale IP-Adresse wird zu keinem Zeitpunkt in der Datenbank abgelegt. Durch die Verwendung eines geheimen Schlüssels ist eine Rückrechnung der IP-Adresse aus dem Hash ohne Kenntnis dieses Schlüssels technisch nicht möglich.
- **Browser-Informationen (User-Agent)** — werden ausschließlich als Hash gespeichert. Zusätzlich wird ein allgemeiner Gerätetyp als Klartextbezeichnung abgeleitet (z.B. „Mobile/Android · Chrome"), um dir eine verständliche Session-Übersicht zu ermöglichen.
- **Session-Daten** — werden serverseitig in der Datenbank gespeichert und können von dir jederzeit eingesehen und widerrufen werden.
- **Aktionsprotokoll (Audit-Log)** — Verwaltungsaktionen (z.B. Anwesenheitsänderungen, Einladungen, Strafenzuweisungen) werden mit Zeitstempel, Aktionstyp, Aktionsbeschreibung und dem zugehörigen Benutzerkonto protokolliert. Das Protokoll dient der Nachvollziehbarkeit und wird nach 365 Tagen automatisch gelöscht.

### 4.4 Daten, die nicht verarbeitet werden

- Passwörter (es gibt kein Passwort-System)
- Standortdaten der Nutzer
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
- **Lebensdauer des Cookies:** Bis zum Schließen des Browsers (Standard, die serverseitige Session verfällt dabei nach maximal 24 Stunden) oder bis zu 30 Tage (wenn „Angemeldet bleiben" aktiviert wurde)
- **Flags:** HttpOnly, SameSite=Strict, Secure (bei HTTPS)

Es werden **keine** Tracking-, Analyse- oder Werbe-Cookies verwendet. Es findet **kein** Tracking durch Drittanbieter statt.

---

## 7. Datenweitergabe an Dritte

Personenbezogene Daten werden **nicht** an Dritte weitergegeben, mit folgenden Ausnahmen:

- **E-Mail-Versand:** Der Versand von Anmeldelinks erfolgt über einen SMTP-Server, der vom Betreiber konfiguriert wird. Dabei werden E-Mail-Adresse und Nachrichteninhalt an den jeweiligen E-Mail-Dienstleister übermittelt.
- **Wetter-API:** Für die optionale Wetteranzeige im Dashboard werden GPS-Koordinaten des konfigurierten Standorts (keine nutzerbezogenen Daten) serverseitig an die Open-Meteo API übermittelt. Die IP-Adresse des Nutzers wird dabei nicht übertragen.

---

## 7a. Externe Dienste und CDN-Ressourcen

{{APP_NAME}} lädt bei jedem Seitenaufruf technisch notwendige Bibliotheken von externen Servern (Content Delivery Networks). Beim Laden dieser Ressourcen durch deinen Browser wird deine IP-Adresse an die jeweiligen CDN-Betreiber übermittelt. Die Abfrage von Wetterdaten (Open-Meteo) erfolgt hingegen **serverseitig** — deine IP-Adresse wird dabei nicht an Open-Meteo weitergegeben.

### Im Browser geladene Dienste (IP-Adresse wird übermittelt)

- **Tailwind CSS CDN** (cdn.tailwindcss.com) — CSS-Framework für die Benutzeroberfläche. Der CDN-Betreiber ist Cloudflare, Inc. (101 Townsend St, San Francisco, CA 94107, USA). Cloudflare verarbeitet Daten auf Grundlage des EU-US Data Privacy Framework (Angemessenheitsbeschluss der EU-Kommission vom 10. Juli 2023). Datenschutzerklärung: [cloudflare.com/privacypolicy](https://www.cloudflare.com/privacypolicy/).

- **Chart.js / jsDelivr CDN** (cdn.jsdelivr.net) — JavaScript-Bibliothek für Diagramme. jsDelivr nutzt unter anderem Cloudflare als CDN-Infrastruktur. Datenschutzerklärung jsDelivr: [jsdelivr.com/terms/privacy-policy-jsdelivr-net](https://www.jsdelivr.com/terms/privacy-policy-jsdelivr-net).

### Serverseitig abgefragte Dienste (deine IP-Adresse wird nicht übermittelt)

- **Open-Meteo** (api.open-meteo.com, geocoding-api.open-meteo.com) — Wetterdaten und Standortsuche. Open-Meteo ist ein europäischer Dienst (Österreich) mit DSGVO-konformer Datenverarbeitung. Es werden ausschließlich die GPS-Koordinaten des konfigurierten Veranstaltungsorts übermittelt — keine nutzerbezogenen Daten. Datenschutzerklärung: [open-meteo.com/en/terms](https://open-meteo.com/en/terms).

### Hinweis zur Datenübermittlung in Drittländer

Die CDN-Betreiber Cloudflare und jsDelivr haben ihren Hauptsitz in den USA. Die Datenverarbeitung durch Cloudflare erfolgt auf Grundlage des EU-US Data Privacy Framework. Für den Fall, dass Daten außerhalb der EU/des EWR verarbeitet werden, bestehen geeignete Garantien in Form von EU-Standardvertragsklauseln gemäß Art. 46 Abs. 2 lit. c DSGVO.

Die Nutzung externer CDNs erfolgt aus technischen Gründen. Eine lokale Bereitstellung dieser Bibliotheken ist in einer zukünftigen Version geplant, um die Datenübermittlung an Dritte vollständig zu vermeiden.

---

## 8. Speicherdauer

| Datenkategorie | Aufbewahrungsfrist |
|---|---|
| Account-Daten (aktiver Account) | Bis zur Löschung durch den Nutzer |
| Account-Daten nach Löschung (Soft-Delete) | {{RETENTION_DAYS}} Tage, dann endgültige Löschung |
| Anwesenheitsdaten | Bis zur Löschung des zugehörigen Events |
| Strafen und Rollen | Bis zur Löschung des zugehörigen Events |
| Aktionsprotokoll (Audit-Log) | 365 Tage |
| Magic-Link-Token (als Hash) | Verfallen nach 30 Minuten, endgültige Löschung nach 1 Tag |
| Session-Daten | Verfallen nach 24 Stunden (Standard) oder 30 Tagen (Angemeldet bleiben) |
| Rate-Limiting-Daten | Automatische Löschung nach 48 Stunden |
| Einwilligungsprotokoll | Bis zur endgültigen Löschung des Accounts |

---

## 9. Deine Rechte (Betroffenenrechte)

Du hast gemäß DSGVO folgende Rechte:

### 9.1 Recht auf Auskunft (Art. 15 DSGVO)

Du kannst jederzeit eine Übersicht aller über dich gespeicherten Daten anfordern. In {{APP_NAME}} steht dir dafür eine **automatische Exportfunktion** im Profil zur Verfügung, die alle deine Daten als JSON-Datei herunterlädt.

### 9.2 Recht auf Berichtigung (Art. 16 DSGVO)

Du kannst deinen Anzeigenamen jederzeit über die Profilseite ändern. Für die Berichtigung weiterer gespeicherter Daten (z.B. Anwesenheitseinträge) wende dich bitte an den Event-Administrator oder die unter Abschnitt 1 genannte Kontaktadresse.

### 9.3 Recht auf Löschung (Art. 17 DSGVO)

Du kannst deinen Account jederzeit über die Profilseite selbst löschen. Die Löschung erfolgt zunächst als Soft-Delete (Daten werden als gelöscht markiert und sind nicht mehr zugänglich). Nach Ablauf der Aufbewahrungsfrist von {{RETENTION_DAYS}} Tagen werden die Daten endgültig und unwiderruflich aus der Datenbank entfernt.

### 9.4 Recht auf Einschränkung der Verarbeitung (Art. 18 DSGVO)

Du hast das Recht, unter bestimmten Voraussetzungen die Einschränkung der Verarbeitung deiner Daten zu verlangen — insbesondere wenn du die Richtigkeit der Daten bestreitest, die Verarbeitung unrechtmäßig ist oder du Widerspruch eingelegt hast. Wende dich hierzu an die unter Abschnitt 1 genannte Kontaktadresse.

### 9.5 Recht auf Datenübertragbarkeit (Art. 20 DSGVO)

Du kannst alle deine Daten über die Exportfunktion im Profil in einem maschinenlesbaren Format (JSON) herunterladen.

### 9.6 Recht auf Widerspruch (Art. 21 DSGVO)

Soweit die Verarbeitung auf Art. 6 Abs. 1 lit. f DSGVO (berechtigte Interessen) gestützt wird — insbesondere die Führung des Audit-Logs — hast du das Recht, aus Gründen, die sich aus deiner besonderen Situation ergeben, Widerspruch gegen die Verarbeitung einzulegen. Wende dich hierzu an die unter Abschnitt 1 genannte Kontaktadresse.

### 9.7 Recht auf Widerruf der Einwilligung (Art. 7 Abs. 3 DSGVO)

Du kannst deine Einwilligung zur Datenverarbeitung jederzeit widerrufen, indem du deinen Account löschst oder die unter Abschnitt 1 genannte Kontaktadresse verwendest. Der Widerruf berührt nicht die Rechtmäßigkeit der bis dahin erfolgten Verarbeitung. Aus dem Widerruf entstehen dir keine Nachteile.

### 9.8 Beschwerderecht (Art. 77 DSGVO)

Du hast das Recht, dich bei einer Datenschutz-Aufsichtsbehörde zu beschweren, wenn du der Ansicht bist, dass die Verarbeitung deiner Daten gegen die DSGVO verstößt.

Zuständige Aufsichtsbehörde richtet sich nach dem Sitz des Verantwortlichen. Für Organisationen in **Baden-Württemberg**:

> **Der Landesbeauftragte für den Datenschutz und die Informationsfreiheit Baden-Württemberg (LfDI BW)**  
> Königstraße 10a · 70173 Stuttgart  
> Telefon: +49 711 615541-0  
> E-Mail: poststelle@lfdi.bwl.de  
> Web: [www.lfd.bwl.de](https://www.lfd.bwl.de)

*Hinweis für Betreiber aus anderen Bundesländern:* Bitte passen Sie die zuständige Aufsichtsbehörde entsprechend dem Sitz Ihrer Organisation an.

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

- **HMAC-geschütztes Hashing:** Alle gespeicherten Hashes (IP-Adressen, Browser-Kennungen, Authentifizierungs-Token) werden mit einem geheimen, ausschließlich serverseitig bekannten Schlüssel (HMAC-SHA-256) berechnet. Eine Rückrechnung ist ohne diesen Schlüssel nicht möglich.
- **Passwortlose Anmeldung:** Es werden keine Passwörter gespeichert oder verarbeitet. Die Anmeldung erfolgt ausschließlich über einmalig verwendbare Magic Links.
- **Einmalig verwendbare Tokens:** Magic Links sind nach einmaliger Nutzung ungültig und verfallen zusätzlich nach 30 Minuten.
- **CSRF-Schutz:** Alle zustandsändernden Formulare und API-Endpunkte sind gegen Cross-Site Request Forgery (CSRF) geschützt.
- **SQL-Injection-Schutz:** Alle Datenbankzugriffe erfolgen ausschließlich über parametrisierte Abfragen (Prepared Statements).
- **Session-Sicherheit:** Session-Cookies sind mit den Flags HttpOnly, SameSite=Strict und Secure (bei HTTPS) geschützt. Nach jeder Anmeldung wird die Session-ID erneuert (Session Fixation-Schutz).
- **Transportverschlüsselung:** Die Anwendung erzwingt HTTPS (TLS). Alle Verbindungen werden über das HTTP Strict Transport Security (HSTS)-Protokoll abgesichert.
- **Automatische Datenlöschung:** Abgelaufene Token, Sessions und Rate-Limiting-Daten werden automatisch bereinigt.

---

## 12. Hinweis zur Datenschutzfolgenabschätzung (DSFA)

Je nach Größe der Organisation und Anzahl der verarbeiteten Datensätze kann nach Art. 35 DSGVO eine Datenschutzfolgenabschätzung erforderlich sein — insbesondere wenn neben Anwesenheitsdaten auch finanzielle Sanktionen (Strafenkatalog) für eine größere Anzahl von Personen verarbeitet werden. Betreiber dieser Anwendung sind gebeten, dies vor dem produktiven Einsatz mit ihrem Datenschutzbeauftragten oder einem Rechtsanwalt zu klären.

---

## 13. Änderungen dieser Datenschutzerklärung

Bei wesentlichen Änderungen der Datenschutzerklärung wird die Versionsnummer erhöht. Du wirst beim nächsten Login aufgefordert, der aktualisierten Fassung erneut zuzustimmen. Ohne erneute Zustimmung ist eine weitere Nutzung nicht möglich. Geringfügige redaktionelle Korrekturen können ohne Versionserhöhung vorgenommen werden.

---

*Hinweis: Diese Datenschutzerklärung wurde sorgfältig erstellt, stellt jedoch keine Rechtsberatung dar. Wir empfehlen, die Erklärung vor dem produktiven Einsatz durch einen Datenschutzbeauftragten oder Rechtsanwalt prüfen zu lassen.*
