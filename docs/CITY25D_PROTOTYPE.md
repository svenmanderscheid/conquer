# Optionales 2,5D-Dorf

Der Prototyp ergänzt die bestehende 3D-Dorfansicht. Er ersetzt weder die
Weltkarte noch die Serverlogik und ist nicht standardmäßig aktiviert.

## Einschalten und zurückwechseln

Unter Einstellungen → Dorfansicht „2,5D (Prototyp)“ wählen und speichern.
„3D (Standard)“ stellt die bisherige Ansicht wieder her. Die Wahl liegt nur
im lokalen Browser-Speicher (`conquer:city-renderer:v1`), nicht im Spielstand.
Der vorhandene Dorf-Iframe wird beim Wechsel neu geladen; es laufen nicht
beide Darstellungen parallel.

Direkte Vorschau: `/city/3d?renderer=2.5d`, eingebettet zusätzlich `&embed=1`.
Ein unbekannter Renderer-Wert verwendet weiterhin 3D.

## Darstellung

Die Bilder werden aus den bestehenden Modellen und der festen Spielkamera
exportiert, nicht durch KI neu gezeichnet. Kleine Geländekacheln und separate
Gebäudebilder ersetzen die laufende 3D-Berechnung. Die Canvas2D-Ansicht lädt
keine Three.js-Engine. Verschieben, Vergrößern und Gebäudeauswahl bleiben
erhalten. `play.js` und `hud.js` verwenden weiterhin dieselben authentifizierten
Serverendpunkte und Gebäudeaktionen.

Die erste Version ist bewusst statisch: keine laufenden Figuren, bewegten
Wasserräder oder animierten Premium-Burg-Skins. Dargestellt wird die normale
Burg; die ausgerüstete Skin und ihre Spielwerte werden nicht geändert.
Die Kamera lässt sich nicht drehen. Sehr starkes Zoomen wird durch die
Bildauflösung begrenzt. Das ist ein Vergleichsprototyp, keine vollständige
Umstellung sämtlicher Spielgrafik.

## Export und Prüfung

`node tools/export-city25d.cjs` erzeugt die Bilder und das Manifest unter
`assets/art/city25d/` aus einer isolierten Vorschau der echten Dorfszene.
Dafür werden Playwright, Chromium, Sharp und PHP benötigt. Der Exportzugriff
auf die 3D-Szene wird nur durch den vom Werkzeug gesetzten Fixture-Schalter
freigegeben; es gibt keinen öffentlichen Export-URL-Parameter.

Nach Änderungen an Gebäuden, Materialien, Gelände oder der Kamera müssen die
Bilder neu exportiert werden. JavaScript-Änderungen allein aktualisieren die
vorberechneten Bilder nicht.

Das Manifest verwendet projizierte Pixel um den Weltursprung. Bei Geländekacheln
ist `x/y` die linke obere Ecke. Bei Gebäuden ist `x/y` der projizierte Fußpunkt,
`anchor.x/y` dessen Position innerhalb des beschnittenen Bildes. Diese beiden
Koordinatenkonventionen dürfen nicht vertauscht werden. Die Dateien tragen
Inhaltshashes; das Manifest wird ohne dauerhaften Browsercache gelesen.

Der erste Export enthält 12 Geländekacheln und 15 Gebäudebilder. Die Stadtmauer
liegt im Gelände, ihre Auswahl folgt weiterhin dem gemeinsamen Mauerumriss.
Die Bilddateien umfassen rund 554 KB; dekodiert sind es rund 41 MB RGBA, ohne
die zusätzliche Zeichenfläche und Auswahlmasken. Diese Größen müssen bei
späteren Exporten erneut geprüft werden.

Prüfungen:

- `node tests/city_renderer.cjs`: lokale Renderer-Wahl und Speicherfehler.
- `node tests/city_renderer_settings.cjs`: Einstellungsfeld und Speichern in
  Desktop-, Handy- und Querformat, ohne echte Kontodaten zu ändern.
- `node tests/city25d.cjs`: echte PHP-Ansicht mit isolierten Spielstandantworten,
  Grafikressourcen, Auswahl, Navigation und Ruheverhalten.

Bildrate und Stromverbrauch müssen vor einer Standardaktivierung zusätzlich
auf echten schwächeren Handys geprüft werden. Ein entfallener 3D-Renderpfad
ist keine Garantie für eine bestimmte Bildrate auf sämtlichen Geräten.
