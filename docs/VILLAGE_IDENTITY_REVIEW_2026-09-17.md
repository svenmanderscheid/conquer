# Dorf und Insel: Erkennbarkeit und Detailprüfung

Die bestehende gezeichnete Fantasygestaltung wurde beibehalten. Größere funktionale Merkmale unterscheiden die Gebäude bereits in der normalen Spielperspektive:

- Kaserne: gekreuzte Schwerter und Schildübungen; Schützenlager: Bogen, Pfeil und große Zielscheiben.
- Reiterhof: Pferdekopf, Hufeisen und größere Ponys; Akademie: geöffnetes Buch und deutlicherer Zauberstab.
- Bauernhof: Ährenbeschlag; Holzfällerlager: offene Sägestation und Axt.
- Steinbruch: geschnittene Blöcke und Werkstein am Kran; Goldmine: sichtbare Goldadern und Erzknollen.
- Lagerhaus: zwei Silos; Schatzkammer: große Truhe über dem Eingang; Allianzhalle: gemeinsames Wappen aus zwei Schilden.

Der Inselboden folgt nun derselben unregelmäßigen Kontur wie die abgestuften Ufer. Felsen, Schilf, Seerosen und lockere Baumgruppen ergänzen den Rand. Wiederholte Uferteile und Wasserbewegungen sind instanziert. Die flache Spielfläche, Gebäudeplätze und Straßen bleiben erhalten.

Die Subagent-Ergebnisse wurden in Gesamt- und Nahansichten kontrolliert. Eine zunächst vom Torbalken verdeckte Goldader wurde auf die freie Felsschulter versetzt. Beim Test auf 320 px Breite fiel zudem eine Gebäudeaktion hinter der rechten Hauptnavigation auf; Fensterbreite und Position lassen diese Navigation jetzt frei.

## Prüfung

- JavaScript-Syntax der geänderten Module und neun Prüfungen der gemeinsamen Modulversion bestanden.
- `tests/village_viewport.cjs`: 150 Prüfungen bei 1280 × 720, 390 × 844, 844 × 390 und 320 × 700 bestanden. Geprüft wurden unter anderem Zoom, Wischen, Animation, Gebäudeauswahl und fehlende doppelte HUD-Elemente.
- Echte Haupt-App `/city#city` mit Wegwerf-Testkonto: Übersicht und Nahansichten aller betroffenen Gebäude; vier Bildschirmformate, Figuren, beide Baumformen, Wege, Ufer und Bedienelemente geprüft. Der abschließende Vierformatlauf verwendete die 3D-Version `79588d52eaa03238`. Alle Geometrie-/Bedienprüfungen und Prüfungen auf JavaScriptfehler bestanden; die zusätzliche HTTP-Prüfung meldete einmal `409 BUSY` beim Spielstandabruf.
- Gezielter Abschlusstest derselben Version auf Desktop und 320 px: Goldmine, Bauernhof und Schatzkammer, tatsächlich freie Knopfmittelpunkte, HUD, Animationen und Asset-/API-Abrufe bestanden. Kein JavaScript- oder HTTP-Fehler in diesem Lauf. Rohprotokolle und Aufnahmen: `artifacts/village-identity/review.json` und `final-check.json`.

Die lokale Testumgebung teilt sich MySQL-Sperren mit anderen Prozessen; `BUSY` ist ein dokumentierter kurzfristiger Serverbefund, keine behobene Netzwerk- oder Lastgrenze. Diese Gestaltungskontrolle ersetzt weder einen Serverlasttest noch eine Leistungsprüfung auf einem echten iPhone oder Android-Gerät. Die Browseransichten wurden mit Software-WebGL gerendert; daraus lässt sich keine reale Handy-Bildrate ableiten.
