# Spielsysteme und Backoffice

Stand: 11. September 2026. Diese Ergänzung beschreibt den aktuellen Funktionsstand nach dem früheren PvE-MVP. Bestehende Spieldaten bleiben erhalten. Das Paket wurde in der lokalen XAMPP-Installation integriert; eine externe Veröffentlichung gehört nicht zu diesem Stand.

## Einstieg

- Spiel: `/city`, zusätzliche Bereiche über das Spielmenü.
- Verwaltung: `/admin`, mit der bestehenden separaten Admin-Anmeldung. Änderungen benötigen die Rolle `superadmin`; Moderatoren haben Lesezugriff.
- Welten: im Spielmenü unter **Weltenauswahl**, im Backoffice über die Auswahl im Kopfbereich.
- Schema: Migrationen 0063 bis 0073 sind lokal angewandt. Für eine andere Installation den normalen Migrationslauf `php migrations/run.php` verwenden.

## Umgesetzte Funktionen

| Bereich | Spielbares Verhalten |
| --- | --- |
| Weltenverwaltung | Welten anlegen, öffnen, pausieren und schließen; Geschwindigkeits-, Sammel- und Transportfaktoren bearbeiten. Neue Welten haben 256 × 256 Felder, Kongress und vier regionale Schreine. |
| Spawns | Unabhängige Dichte und Wahrscheinlichkeit für Minen/Rohstofffelder und Monster, Typverteilung, Stufenbereich, Zeitfenster in UTC, Intervall, Lebensdauer und Obergrenzen. Gemeinsames Laufbudget verteilt Versuche auf beide Populationen. Bestehende Marschziele bleiben geschützt. |
| Spieler | Suchen, sperren/entsperren, Ressourcen setzen, 13 echte Gebäudetypen aufwerten, Forschungsstufen und Garnison ändern, Stadtschutz verlängern. Aktive betroffene Warteschlangen verhindern widersprüchliche Korrekturen. |
| Geschenke | Einzelne Spieler oder eine ganze Welt erhalten Ressourcen, Edelsteine oder Gegenstände mit Nachricht. Vorgangsbelege verhindern doppelte Zustellung; der Spieler sieht die Empfangsbestätigung in seiner Post. |
| Gemeinschaft | Welt- und Allianzchat, private Post, Antworten, Allianz-Hilfe für Bau/Forschung, Rollen und Berechtigungen, laufende Allianzforschung, gegenseitig angenommene Bündnisse/Nichtangriffspakte. |
| Handel | Ressourcenlieferungen zwischen Städten mit Abzug, Reisezeit, einmaliger Zustellung oder Erstattung. Der bestehende feste Ressourcentausch bleibt verfügbar. |
| Verteidigung | Befristete Schilde, Spähschutz, Mauerzustand/Reparatur/Regeneration, geschützte Ressourcen, echte Spähberichte und Verstärkungen mit Rückreise. Schutzgegenstände sind im Inventar verwendbar und im Verteidigungsbereich für Spiel-Edelsteine erhältlich. |
| Armee | Vier benannte Formationen, Verwendung im Marschdialog, reale Beförderungswarteschlange mit Voraussetzungen, Reservierung, Kosten und Erstattung beim Abbruch. |
| Meisterschaft und Relikte | Sechs Meisterschaftszweige mit je fünf Stufen; Burgstufen geben begrenzte Punkte, Zurücksetzen ist kostenlos. Marsch- und Hospitalplätze sind feste Reliktboni, Ressourcenschutz ist ein Prozentwert. Die Boni wirken in den tatsächlichen Spielberechnungen. |
| PvE | Aschenfürst, Frostwächter und Dornenkönigin; normale, heroische und legendäre Schwierigkeit, Kapitel-/Burgfreischaltungen, eigene Ziele, Taktikboni und skalierte Beute. Aktive Feldzüge speichern ihre Regeln unveränderlich. |
| Weltereignisse | Konfigurierbare Conquest-Zyklen mit vier Phasen, C/B/A/S-Zielen, Haltepunkten, Rangliste, Abschlussbericht und persönlicher Belohnung. Weltinvasionen mit Nahrung oder realen Armeen, gemeinsamer Fortschritt und Kapitelaufstieg. Kongress und „Krieg der vier Schreine“ behalten ihre separate Ereignislogik. |
| Konto | Passwort ändern, andere Sitzungen abmelden und einmalig sichtbaren Wiederherstellungscode erzeugen. Wiederherstellung speichert nur den Code-Hash, verbraucht den Code und widerruft alte Sitzungen. |
| Installation und Sprachen | Installierbare Web-App, öffentliche Offline-Hilfeseite und DE/FR/LU-Menüs mit 290 Übersetzungsschlüsseln. Ausführliche Spieltexte sind teilweise weiter deutsch; der genaue Umfang steht in `LOCALIZATION_PWA.md`. |

## Regeln über mehrere Welten

Städte, Ressourcen, Forschung, Meisterschaft, Armeen, Allianzen, Post/Chat, Handel und Feldzüge gehören zur jeweiligen Welt. Konto, Profil, Edelsteine, Inventar, Prestige und Relikte sind gemeinsam. Formationen speichern Vorlagen und reservieren keine Truppen.

Die Sitzung hält die ausgewählte Welt. Spielanfragen senden ihre ursprüngliche Weltkennung; alte Browser-Tabs werden beim Weltwechsel abgefangen. Hintergrundverarbeitung verwendet die gespeicherte Welt/Stadt des Auftrags. Neue Käufe und Entsendungen werden in pausierten oder geschlossenen Welten blockiert; zurückkehrende Armeen und fällige Abrechnungen bleiben erhalten.

## Hintergrundbetrieb

Für regelmäßige Ausführung ohne geöffnete Spielseite auf dem Zielserver minütlich beide Befehle einplanen:

```text
php /absoluter/pfad/conquer/cron/world_spawn_tick.php
php /absoluter/pfad/conquer/cron/march_tick.php
```

Der Spawnworker prüft die gespeicherten Termine und Zeitfenster selbst. Zusätzlich prüft das Laden der Spielwelt fällige konfigurierte Spawns mit denselben Regeln; dadurch funktioniert die lokale Erprobung ohne installierten System-Cron. Wiederholte Aufrufe umgehen weder Intervalle noch Grenzen. Die Quellen installieren keinen Systemdienst und ändern keine Hosting-Cronkonfiguration.

## Prüfung

Die Backend-Suiten verwenden wegwerfbare Datenbanken beziehungsweise ausdrücklich synthetische Fixtures. Reale Spieler wurden für Tests nicht verändert.

- Backoffice: Berechtigungen, CSRF, Audit, Ressourcen/Queues, Geschenk-Wiederholungen und Spawnregeln.
- Gemeinschaft: 62 Prüfungen einschließlich HTTP, paralleler Zustellung und Diplomatie.
- Mehrere Welten: 39 Prüfungen für Join/Select, Wiederholungen, Trennung von Daten und Rückkehr in die richtige Stadt.
- Verteidigung: Kampf, Späher/Schilde, Verstärkung, Rückruf, Formationen und Beförderung; zusätzliche Welt- und HTTP-Fälle.
- Fortschritt: 41 Prüfungen für Punkte, Boni, Phasen, Besitzwertung, Invasionsarmeen, Beute, Kapitel und Kontowiederherstellung.
- Vorhandene Feldzug-, Kampf-, Forschungs-, Kongress-, Kingdom- und Treasury-Prüfungen bestanden.
- Browser: echte Anmeldung, Backoffice-Speicherung, Geschenk an ein Testkonto, Weltbeitritt und getrennte Burgstufen; zusätzlich 20 neue Panel-Layouts, 20 Verteidigungs-Layouts und 32 Gemeinschafts-Layouts. Formulare, kleine Bildschirme und verspätete Antworten sind berücksichtigt.
- Lokalisierung und Service Worker separat geprüft. Keine privaten Spiel-/Admin-/API-Antworten im Offline-Cache.
- Lokaler Service-Benchmark mit 200 Testspielern und zwei Welten: siehe `BENCHMARK.md`. Dies ersetzt keinen Lasttest auf dem späteren Produktionsserver und kein längerfristiges Balancing mit echten Spielern.

Details: `BACKOFFICE.md`, `MULTIWORLD.md`, `LOCALIZATION_PWA.md`, `BENCHMARK.md`.
