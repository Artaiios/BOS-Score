# Changelog

## [1.7.3] – 2026-03-26

Der Event-Admin wurde grundlegend aufgeräumt. Die Reiter sind jetzt in einer praxistauglichen Reihenfolge angeordnet (Übersicht, Anwesenheit, Strafen, Termine, Teilnehmer, Rollen, Einstellungen, Audit-Log). Den Strafenkatalog findet man ab sofort direkt in den Einstellungen, die Strafkasse-Diagramme wandern in die Übersicht — das spart zwei Klicks und zwei Tabs.

Die Übersicht zeigt jetzt den nächsten Termin mit Wetter und Rollen-Verfügbarkeit auf einen Blick. Die Einstellungen nutzen ein zweispaltiges Layout (auf Mobilgeräten einspaltig), wodurch weniger gescrollt werden muss.

Die Anwesenheitsliste und die Dashboard-Terminliste verwenden jetzt ein Accordion: vergangene Termine sind eingeklappt, der nächste Termin ist immer sichtbar, kommende Termine ebenfalls eingeklappt. Gerade bei 30+ Terminen macht das einen deutlichen Unterschied.

Außerdem behoben: die `.htaccess` hat auf Shared Hosting einen 500er verursacht (`php_flag` funktioniert nicht mit PHP-FPM). Und archivierte Events sind jetzt im Lesemodus zugänglich statt komplett gesperrt.

## [1.7.2] – 2026-03-26

Neues Rollen-Feature. Im Admin gibt es jetzt einen Reiter „Rollen", der standardmäßig deaktiviert ist. Einmal aktiviert, lassen sich beliebig viele Rollen anlegen (z.B. Gruppenführer, Maschinist, Mannschaft) und den Teilnehmern zuweisen — auch mehrere pro Person.

Die Rollen-Verfügbarkeit wird dann als kompakte Badges angezeigt: `GF 1/2 ✅ Masch 0/1 ❌`. Das funktioniert in der Anwesenheitsliste pro Termin, in der Dashboard-Terminliste und auf der Teilnehmer-Detailseite. So sieht man auf einen Blick, ob für einen Übungstag alle Rollen besetzt sind.

DB-Änderungen: neue Tabellen `roles` und `member_roles`, neue Spalte `roles_enabled`. Migration über `update_v1_7_2.php`.

## [1.7.1] – 2026-03-25

Archivierte Events waren bisher komplett gesperrt — jetzt wird stattdessen ein Lesemodus angezeigt. Auf allen Seiten erscheint ein gelbes Banner. Im Admin sind sämtliche Eingaben und Buttons ausgegraut, nur die Reaktivierung ist möglich. Die API blockiert Schreibzugriffe entsprechend.

## [1.7.0] – 2026-03-25

Größtes Update bisher: neue Server-Admin-Ebene. Es gibt jetzt eine zentrale Verwaltung über `admin.php`, die Events erstellt und die Token-URLs an die jeweiligen Event-Admins weitergibt. Der Organisationsname ist global konfigurierbar und kann pro Event überschrieben werden. Kein hardcoded "Feuerwehr Rutesheim" mehr.

Frist 1 (Zwischenziel) ist jetzt optional — kann pro Event aktiviert oder deaktiviert werden. Die Hauptfrist steht im Formular oben.

Das Setup fragt nur noch Organisationsname und E-Mail ab und erstellt den Server-Admin-Token. Keine vordefinierten Termine mehr.

Technisch mussten alle Formulare von `<form onsubmit>` auf `<div>` + `onclick` umgebaut werden, weil async-Funktionen mit `return false` nicht zuverlässig den Browser-Submit verhindert haben. Außerdem kollidierte `createEvent` mit der nativen DOM-Methode `document.createEvent()` — umbenannt zu `createNewEvent`.

DB-Änderungen: neue Tabelle `server_config`, neue Spalten `organization_name` und `deadline_1_enabled`. Migration über `update_v1_7.php`.

## [1.6.3] – 2026-03-25

Anwesenheit wird jetzt bei jedem Klick sofort einzeln gespeichert — kein Speichern-Button mehr nötig. Das löst auch das Problem, dass Selbst-Entschuldigungen versehentlich überschrieben wurden, wenn der Admin die ganze Liste auf einmal speicherte. Bei entschuldigten Teilnehmern steht jetzt „🟡 selbst entsch." oder „🔵 durch Admin".

## [1.6.2] – 2026-03-25

Status-Toggle: ein erneuter Klick auf den bereits aktiven Status-Button setzt ihn zurück. Auf API-Seite wird bei leerem Status der Eintrag gelöscht statt auf "Fehlend" gesetzt.

## [1.6.1] – 2026-03-25

Das Dropdown-Menü in der Anwesenheitsverwaltung war in der Praxis umständlich. Ersetzt durch eine aufklappbare Terminliste mit Zählern pro Termin — der nächste Termin ist automatisch aufgeklappt.

Außerdem kann der Wetter-Standort jetzt in den Einstellungen per Ortssuche konfiguriert werden (Open-Meteo Geocoding API).

DB-Änderungen: neue Spalten `weather_location`, `weather_lat`, `weather_lng`.

## [1.6.0] – 2026-03-25

Teilnehmer können Entschuldigungen jetzt zurückziehen, solange der Termin noch nicht begonnen hat. Die Übungsdauer ist konfigurierbar (Standard: 3h) und bestimmt, ab wann ein Termin als beendet gilt — der „Nächste Termin" im Dashboard wechselt erst danach. Admin-gesetzte Status können vom Teilnehmer nicht mehr überschrieben werden.

DB-Änderungen: neue Spalte `session_duration_hours`.

## [1.5.0] – 2026-03-25

Dashboard-Erweiterung: Frist-Countdown-Karten, Wetter-Vorhersage für den nächsten Termin (Open-Meteo API, kostenlos) und ein „Mein Status"-Widget, in dem Teilnehmer ihren Namen auswählen und ihre persönliche Ampel sehen können.

## [1.4.0] – 2026-03-25

Upgrade auf Tailwind CSS 3.x. Anwesenheits-Buttons sind jetzt farbcodiert (Grün/Gelb/Rot statt unsichtbare Radio-Buttons). Der nächste Termin wird im Dashboard und auf der Teilnehmerseite hervorgehoben.

## [1.3.0] – 2026-03-25

SQL-Bug in der Strafkasse-Statistik behoben — der LEFT JOIN hat bei 0 Strafen falsche Werte geliefert. Das Kreisdiagramm zeigt jetzt die Anzahl der Strafen statt Euro-Beträge. Vergangene Termine werden ausgegraut.

## [1.2.0] – 2026-03-25

Straftypen lassen sich jetzt direkt in der Liste bearbeiten (Inline-Edit) statt nur über ein separates Formular.

## [1.1.0] – 2026-03-25

Die Anzeigenamen für Frist 1 und Frist 2 sind jetzt im Admin konfigurierbar.

## [1.0.0] – 2026-03-25

Erste Version. Öffentliches Dashboard mit Diagrammen und Frist-Ampeln, Teilnehmer-Detailseite mit Entschuldigungsfunktion, Admin-Bereich mit Teilnehmer-, Termin- und Strafenverwaltung. Audit-Log mit CSV-Export. Responsive Design mit Tailwind CSS. Sicherheit über PDO Prepared Statements, CSRF-Tokens und XSS-Schutz.
