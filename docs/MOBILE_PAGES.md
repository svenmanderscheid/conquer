# Mobile Spielbereiche

Stand: 26. September 2026.

Auf schmalen Bildschirmen (bis 700 px) und im kurzen Querformat (bis 1100 × 520 px) verwenden große Spielbereiche die vollständige sichtbare Fläche. Desktop behält die bisherigen Fenster. Die gemeinsame PHP-/Web-Codebasis und die serverseitigen Spielaktionen bleiben bestehen.

## Vollbildbereiche

Alle Bereiche des gemeinsamen `panel-dialog`: Ausbildung und Hospital, Forschung, Schatzkammer mit Relikten und Truhen, Shop/Handelsposten, Inventar, Post, Aufgaben, Talente, Allianz, Community, Verteidigung/Formationen, Events, Dungeons, Expeditionen, Landübersicht, Ranglisten, Arena, Profil, Einstellungen, Konto, Spielhilfe, Welten und Fehlermeldung.

Zusätzlich werden Forschungseinzelheiten, Marschvorbereitung, Rally-Liste und -Details, Skin-Sammlung, Marsch-Skin-Details, Paketübersicht zur Kaufprüfung, VIP, Briefe und Berichte, Profilbearbeitung sowie das Spielmenü als mobile Detailseiten dargestellt. Der geöffnete Chat füllt ebenfalls den Bildschirm; die kompakte Chatvorschau bleibt in der Spielwelt.

Gebäudeausbau, kurze Bestätigungen, Beschleuniger und andere kurze Aktionen bleiben höhenangepasste Fenster am unteren Rand. Reliktfragmente bleiben eine kompakte Unteransicht. Neue umfangreiche Dialoge müssen in `mobile-pages.js` ausdrücklich als Detailseite registriert werden.

## Verhalten

- Gemeinsamer dunkler violetter Kopf, beigefarbener Inhalt und eine 44 px große Zurück-Taste. Die Gestaltung verwendet die gemeinsamen `--ui-*`-Variablen.
- Kein zusätzliches Spiel-HUD über den mobilen Seiten. Relevante Preise, Kosten und Statuswerte bleiben im jeweiligen Bereich.
- Die Hauptbereiche bleiben über ihre bisherigen URL-Fragmente erreichbar. Zurück schließt zunächst Dialog/Chat beziehungsweise Reliktdetails und kehrt anschließend zum vorherigen Bereich zurück. Direkte Bereichsaufrufe können über Zurück zur Spielwelt wechseln.
- Browsernavigation führt keine Spielaktion erneut aus. Geschlossene Formulare werden bei Vorwärtsnavigation nicht aus veraltetem HTML rekonstruiert.
- Vorhandene Bereichszustände bleiben bei den Funktionsmodulen; Scrollpositionen werden für die registrierten Inhaltsflächen gemerkt.
- `visualViewport` berücksichtigt die beim Tippen verfügbare Höhe. Sichere Bildschirmränder werden über `safe-area-inset-*` berücksichtigt. Pinch-Zoom bleibt möglich.
- Gespeicherte frei verschiebbare Fensterpositionen des Layout-Editors überschreiben den mobilen Seitenrahmen nicht. Desktop und HUD-Anordnung behalten ihre Profile.
- Die mobile Reliktübersicht zeigt Ausrüstungsplätze über der Sammlung; Gesamtboni sind einklappbar. Reliktdetails nutzen den gesamten Inhaltsbereich, ihre Aktion bleibt am unteren Rand erreichbar.

## Prüfung

`tests/mobile_pages_app.cjs` startet eine isolierte PHP-App mit synthetischen Konten und eigener Testdatenbank. Es prüft 23 Hauptbereiche bei 390 × 844, 320 × 568, 844 × 390, 568 × 320 und 1280 × 800, Trainingsaktionen, Forschung/Scrollposition, Browser-Zurück, Chat mit verkleinerter Ansicht, Relikt- und Fragmentdetails, Hospital, Profilbearbeitung, Post und VIP. Screenshots liegen unter `artifacts/mobile-pages/`.

Die Browserprüfung ersetzt keinen Test der tatsächlichen iOS-/Android-Bildschirmtastatur und sicheren Bildschirmränder auf einem realen Gerät.
