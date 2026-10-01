# Burg-Skins

Stand: 26. September 2026. Die aktive Sammlung enthält `default`, `forest`, `fire`, `water` und `wind`. Katalog und Zuordnung der vorhandenen PNG-/WebP-Bilder liegen in `assets/js/castle-skins.js`. Die Spielansicht ist ausschließlich gezeichnet; die frühere Three.js-/GLB-Pipeline wurde entfernt. Das Löschmanifest steht in `REMOVED_3D_2026-09-26.md`.

Burg, Marsch und Namensrahmen lassen sich unabhängig anlegen. Die fünf aktuellen Burgen sind verfügbar; Marschbonus und Rahmenbesitz bleiben serverseitig kontrolliert. Der Skinpicker verwendet ein scrollbares Kartenraster, keine historischen Fünfseitenlisten. Reduzierte Bewegung wechselt animierte WebP-Porträts auf ihr PNG-Standbild.

## Bereits erworbene ältere Skins

Die früheren 17 Premium-IDs sind archiviert. Neue Käufe dieser Reihen sind serverseitig gesperrt. Bestehende Besitzrechte werden weder gelöscht noch auf andere Skins umgeschrieben: die Sammlung ergänzt nur tatsächlich besessene Alt-Skins; historische Profile und unveränderliche Marsch-Snapshots behalten ihre passende Darstellung. Dafür bleiben die entsprechenden vorhandenen 2D-Bilder und die kleinen Kompatibilitätskataloge erhalten. Dieselbe Regel gilt für gekaufte Namensrahmen. Bereits angelegte, bezahlbare Bestellungen dürfen weiterhin wiederaufgenommen und durch signierte Zahlungsnachrichten genau einmal erfüllt werden.

Der aktuelle Echtgeldkatalog besteht noch vollständig aus diesen archivierten Reihen. Neue Spieler erhalten daher kein nutzloses Kaufangebot. Neue Elementarpakete benötigen zuerst einen ausdrücklich festgelegten Paket- und Besitzvertrag; bestehende alte Preise werden nicht stillschweigend auf neue Motive übertragen.

## Prüfung

`tests/skin_picker.cjs`, `tests/march_skin_collection.cjs`, `tests/name_frames.cjs` und `tests/theme_bundle_shop.cjs` prüfen aktive/erworbene Skins, gültige Aktionen und Hoch-/Querformat. `tests/theme_bundles.php`, `tests/march_skins.php` und `tests/local_free_skins.php` prüfen Verkaufssperre, Besitz und Erfüllung ausschließlich mit Wegwerfdatenbanken. Die künstlichen aktuellen Angebote im UI-Test prüfen den Darstellungsadapter und sind kein Nachweis aktiver Echtgeldangebote.
