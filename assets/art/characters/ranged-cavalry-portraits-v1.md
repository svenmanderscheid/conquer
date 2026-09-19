# Bogenschützen- und Kavallerieporträts T1–T10

Erstellt mit dem eingebauten ImageGen-Werkzeug am 17. September 2026. Die Reihen
verwenden die vorhandenen T10-Drachenstahlbilder als Endpunkt und die
Infanterieporträts als Stilreferenz. T1–T9 sind neue Illustrationen; T10 bleibt
die bereits freigegebene Drachenstahlfassung.

## Gemeinsamer Prompt-Rahmen

- einzelner, gut lesbarer Chibi-Fantasyheld in Dreiviertelansicht
- gleichbleibende Figur mit kastanienbraunem Haar, blauen Augen und spitzen Ohren
- dicke dunkelbraune Konturen, klare Formen und zurückhaltende Zweiton-Schattierung
- transparente Oberfläche ohne Text, Rahmen, Abzeichen, Boden oder Kulisse
- vollständige Ausrüstung und Füße/Hufe innerhalb des Bildes; lesbar bei 96 px
- sichtbare, schrittweise Entwicklung von einfachem Stoff und Leder über Eisen
  und Silber bis zu königlichem Mithril; T10 als weiß-goldener Drachenstahl-Endpunkt

Bogenschützen tragen pro Stufe einen weiterentwickelten Bogen und Köcher.
Kavallerie zeigt denselben Reiter auf demselben gedrungenen kastanienbraunen Pony;
Lanze, Reiterrüstung und Pferdeharnisch entwickeln sich gemeinsam.

## Produktionsdateien

Für beide Reihen werden je Stufe drei Dateien verwendet:

- `archer-tN-ui-v1.png` / `cavalry-tN-ui-v1.png` – 768 × 768 px
- `archer-tN-thumb-v1.webp` / `cavalry-tN-thumb-v1.webp` – 192 × 192 px
- `archer-tN-report-v1.png` / `cavalry-tN-report-v1.png` – 512 × 512 px

`tools/build-ranged-cavalry-portraits.py` erzeugt aus den UI-Bildern die mobilen
Rangbilder, Berichtsbilder und die beiden Kontaktbögen unter
`artifacts/troop-portraits/`.
