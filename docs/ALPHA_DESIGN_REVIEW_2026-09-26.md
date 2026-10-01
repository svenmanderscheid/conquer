# Designprüfung vom 26.09.2026

## Ergebnis und Korrekturen

Die gemeinsame Oberfläche besteht den aktualisierten breiten Browsertest: **110 Kombinationen aus 22 Spielbereichen und fünf Bildschirmgrößen**, zusätzlich Anmeldung, Passwortwiederherstellung, Weltkarte und Backoffice. Keine Browserfehler, fehlenden Assets, fehlgeschlagenen Menü-API-Lesezugriffe oder verbleibenden automatischen Erscheinungsfehler in diesem Lauf. Insgesamt wurden 2.345 sichtbare Textproben auf CSS-Flächen geprüft.

Vorher gab es helle Lavendelköpfe auf Desktop, dunkle mobile Köpfe, abweichende Basisfarben und zu helle grüne Aktionsverläufe mit weißem Text. Die Abschlussänderung stellt die dokumentierten gemeinsamen Beige-/Violettvariablen wieder her und verwendet dunkle Fensterköpfe mit heller Schrift. Aktionsgrün wurde für lesbare Beschriftungen abgedunkelt. Orange Kristallaktionen tragen dunkle Schrift. Der laufende Forschungsstatus bleibt blau und erhält ausreichenden Kontrast.

Schatten, Lichtkanten, gerundete Rahmen und leicht erhabene Knöpfe bleiben erhalten. Damit bleibt die gewünschte plastische Wirkung ohne 3D-Spielszene erhalten. Truppenstufen, Reliktseltenheiten, Angriffs-/Verteidigungsfarben und die Kristallidentität wurden nicht vereinheitlicht oder ersetzt.

Der Kontrastprüfer wurde auf die tatsächlich verwendete moderne CSS-Farbdarstellung aktualisiert: `color(srgb ...)` aus `color-mix()` wird nun vom Browser korrekt in RGB umgerechnet. Der alte Parser deutete normierte Kanäle fälschlich als Werte von 0 bis 255; dadurch waren einige Truppenkarten falsch beanstandet worden. Echte Kontrastfehler wurden zusätzlich im Stylesheet korrigiert. Die Menüprüfung kontrolliert auf Handyseiten die sichtbare Zurück-Taste statt des dort absichtlich ausgeblendeten Desktop-Schließen-Knopfs.

## Umfang und Belege

- Größen: 1280×800, 390×844, 320×568, 844×390 und 568×320.
- Bereiche: Profil, Aufgaben, Armee, Forschung, Inventar, Relikte, Meisterschaft, Markt, Verteidigung, Land, Dungeons, Feldzüge, Gemeinschaft, Ereignisse, Rangliste, Arena, Einstellungen, Welten, Konto, Hilfe, Allianz und Post.
- Geprüft: Fensterränder, erreichbare Schließen-/Zurück-Aktion, kein waagerechter Fensterüberlauf, gemeinsame Oberflächenfarben, tatsächliche lokale Almendra-/Lora-Schriftglyphen, antippbare Relikte, Textkontrast und fehlende Bilder/API-Antworten.
- Screenshots von Ausbildung auf Desktop und im Querformat, Forschung und Inventar auf schmalen Handys wurden zusätzlich visuell betrachtet: klare Trennung, zentrierte Beschleunigungsaktion, passende Charakterbilder und erhaltene Tiefenwirkung.
- Lauf: `tests/fantasy_theme_app.cjs`, Ergebnis und Screenshots unter `artifacts/fantasy-theme/`; `report.json` endet mit leeren Listen für `errors`, `badAssets`, `badApis`, `appearanceFailures`.
- Weitere bestandene Tests: `painted_city.cjs`, `layered_village.cjs`, `world_painted.cjs`. Sie prüfen aktive Stadt-/Weltbilder, Gebäude- und Zielaktionen, Arbeitsanimationen, Bewegungsreduktion, Skins und Unterordnerpfade.

Aktive Bilder bleiben erhalten, auch wenn ihr Verzeichnis historisch `2.5d` heißt. Das allein ist keine 3D-Abhängigkeit. Exklusive Modelle, Renderer und deren Hilfsmittel stehen im [Löschmanifest](REMOVED_3D_2026-09-26.md).

Ein zusätzlicher Menücheck fand noch ein schlichtes Kronenzeichen am VIP-Eintrag. Dieser verwendet jetzt dieselbe vorhandene Illustration `assets/art/ui-hud/vip.svg` wie das HUD. Es wurde kein neues Bild erzeugt.

## Grenzen

Das ist eine breite automatisierte Stichprobe mit ausgewählten visuellen Kontrollen, keine manuelle Abnahme jedes Knopfs in jeder Datenkonstellation. Zentrierung einzelner Icons, extrem lange übersetzte Texte, sämtliche verschachtelten Dialoge und alle Rollen im Backoffice sind nicht lückenlos geprüft. Die Kontrastmessung erfasst Text auf CSS-Flächen und ersetzt keine vollständige Barrierefreiheitsprüfung. Reale Bildschirmtastaturen, Safe Areas, Android-/iOS-WebViews und schwache Mobilgeräte bleiben im Releaseplan zu testen.

Die Prüfung verwendet synthetische Konten und eine getrennte lokale Datenbank. Screenshots enthalten keine echten Nutzerkonten. Lokale Prüfartefakte sind nun vom Git-Release ausgeschlossen; zur Freigabe ausgewählte Nachweise separat aufbewahren.

## Ergänzende Korrekturrunde

Zusätzlich korrigiert: abgeschnittener Marschfooter im kurzen Querformat, mobile Reliktplätze und mythische Karten, weiße Beschriftung auf orangefarbenem Skin-Kaufknopf, Heilungsaktionen unter 44 px und Lebensleisten verletzter Monster im Marschdialog. Historischer Skinbesitz verwendet weiterhin die vorhandenen passenden Bilder. Aktuelle Nachweise und Grenzen der vollständigen App-Läufe stehen im zentralen Alpha-Abnahmebericht.

Die letzte Bedienrunde ergänzt eine begrenzte, scrollbar bleibende Marschliste vor Koordinatenleiste, Aufgabenhinweis und Chat. Die Marschkarte misst im Querformat die sichtbaren HUD-Knöpfe statt ihrer gestreckten Container. Optionale Aufgabenhinweise treten hinter aktive Gebäude-/Marschaktionen zurück. Im leeren Hospital führt eine sichtbare Aktion zur Ausbildung; ein verdecktes doppeltes Leerformular wurde entfernt.

Die rechte HUD-Leiste hatte durch gleichzeitiges top/bottom im Querformat unsichtbar gestreckte Klickflächen. Ihre Höhe folgt jetzt den sichtbaren Knöpfen. Im sehr kurzen Querformat ersetzt die ausgewählte Marschkarte vorübergehend die Marschliste und Chatvorschau; nach Schließen kehren beide zurück, und der Chat bleibt über die untere Navigation erreichbar.

Abschließend bestanden: sämtliche 33 App-Suiten. Die letzten Kartenprüfungen umfassen fünf Bildschirmgrößen einschließlich568×320, sichtbare Koordinatensteuerung, ein-/ausklappbare Marschliste, eigene/fremde Sammeltrupps und echten Marschrückruf. Während der Rückrufbestätigung ersetzt die gemeinsame Ladeanzeige kurz den Knopfinhalt; das laufende Kartenrendering toleriert diesen Zustand jetzt ohne Browserfehler. Im sehr kurzen Querformat erhalten Zielaktionen Vorrang vor der rechten HUD-Leiste und Chatvorschau.
