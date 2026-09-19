# Postfach

Der Post-Knopf im Dorf und auf der Weltkarte öffnet `/city#reports`. Der bisherige Briefreiter unter Gemeinschaft führt in dasselbe Postfach.

## Bereiche und Bedienung

- **Krieg:** Angriffe, Verteidigung, Spähberichte und entsprechende Systemmeldungen.
- **Allianz:** Allianzmeldungen und abholbare Allianzgeschenke.
- **System:** Systemmeldungen und bereits gutgeschriebene Geschenke der Spielleitung.
- **Berichte:** Monsterkämpfe, abgeschlossene Arenaduelle und Feldzugbelohnungen.
- **Favoriten:** Mit einem Stern markierte Nachrichten aus allen Bereichen.
- **Privat:** Posteingang und gesendete Briefe, Schreiben und Antworten.

Die roten Zähler nennen ungelesene Nachrichten; bei Favoriten nennt der goldene Zähler die gespeicherten Nachrichten. Der Post-Knopf zeigt die gesamte ungelesene Post. Nachrichten laden beim Scrollen in Paketen von 50 nach; ältere Favoriten sind unabhängig davon zugänglich. Datum und Uhrzeit werden lokal angezeigt, in der Datenbank bleibt UTC maßgeblich.

Die sechs Reiter stehen in jedem Bildschirmformat nebeneinander. Jede kompakte Nachrichtenkarte enthält drei Textzeilen: Titel, Nachrichtenart und Sendezeit. Inhaltsvorschauen entfallen. Ein goldener Punkt markiert eine noch abholbare Belohnung.

„Alle lesen“ markiert den gewählten Bereich als gelesen, ohne Belohnungen abzuholen. Der separate Knopf „Alle einsammeln“ holt alle offenen Belohnungen des gewählten Bereichs ab, einschließlich gelesener und noch nicht nachgeladener Nachrichten. Der Punkt verschwindet nach bestätigter Abholung; Nachrichten ohne Belohnung bleiben beim Einsammeln unverändert. Eine serverseitige Snapshot-Kennung schließt Nachrichten aus, die erst nach dem angezeigten Stand eingetroffen sind. „Gelesene löschen“ blendet nur gelesene Nachrichten ohne Stern und ohne offene Belohnung für den handelnden Spieler aus. Es verändert weder den Brief des Gegenübers noch Spiel-/Kampfprotokolle. Geschenke der Spielleitung und Feldzugbelohnungen sind bereits gutgeschrieben und lösen keine erneute Gutschrift aus.

## Technik

- Migration: `0089_mailbox.sql`; lokal gezielt mit `php tools/migrate-mailbox.php`, auf weiteren Installationen über den üblichen Migrationslauf.
- `MailboxService` synchronisiert dauerhafte Empfängerkopien bestehender Briefe, Berichte, Meldungen und Geschenke. Der eindeutige Schlüssel aus Spieler, Welt, Quelle und Quellen-ID verhindert Duplikate. Die Kopien bewahren Favoriten auch nach der Aufbewahrungsfrist der ursprünglichen Benachrichtigungen.
- `GET /api/mailbox/state?category=system` liefert Liste, vollständige Zähler, Snapshot und Fortsetzungscursor. `GET /api/mailbox/message?id=…` liefert Details und verändert keinen Lesestatus.
- Schreibende Aktionen laufen über `POST /api/community/action`: `mailbox.read`, `mailbox.star` (expliziter boolescher Zielzustand), `mailbox.claim`, `mailbox.read_all`, `mailbox.claim_all` und `mailbox.delete_read`. Briefe nutzen weiterhin `mail.send`.
- Session, CSRF, aktive Welt, Empfängerprüfung und bestehende Vorgangsbelege schützen alle Änderungen. Abholungen prüfen die Originalbelohnung, aktuelle Allianzmitgliedschaft und Ablaufzeit; Abholbeleg und Inventargutschrift liegen in derselben Transaktion.
- Neue Benachrichtigungen speichern ihre Welt im Payload. Alte Meldungen ohne nachvollziehbare Welt werden nur bei genau einer Stadt zugeordnet. Spähdetails des Angreifers werden niemals an den ausgespähten Spieler ausgegeben.
- Darstellung: `assets/js/mailbox-panel.js` und `assets/css/mailbox-panel.css`. Materialien kommen aus den zentralen `--ui-*`-Variablen. Entwürfe bleiben während der laufenden App-Sitzung erhalten, auch nach Schließen des Schreibfensters; sie werden nicht auf dem Server gespeichert.

## Prüfung

`php tests/mailbox.php` prüft Datenzugriff, Welten, vollständige Listen, Favoriten, Löschschutz, Gutschriften und HTTP-Schutz in einer Wegwerfdatenbank.

Für die echte Haupt-App: `php tools/preview-feature-fixture.php --port=18964 --mailbox --hud --chat`, anschließend `node tests/mailbox_app.cjs` mit installiertem Playwright. Diese Vorschau verwendet ausschließlich synthetische Spieler und muss bei Änderungen an PHP, HTML, CSS oder JavaScript neu gestartet werden. Getestete Formate: 1280×800, 390×844, 320×568, 568×320 und 844×390.
