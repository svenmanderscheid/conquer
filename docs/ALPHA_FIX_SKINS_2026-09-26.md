# Alpha-Korrekturen: Skins und Bestandsschutz

## Ursache und Verhalten

Die Oberfläche zeigte fünf Elementarskins, während Server und Echtgeldpakete weiterhin 17 alte Motive verkauften. Erworbene Alt-Skins wurden als Standardbild dargestellt oder ließen sich nicht mehr auswählen. Das war ein echter Vertragsfehler zwischen Server und Oberfläche.

Der aktive Katalog bleibt `default`, `forest`, `fire`, `water`, `wind`. Kleine Kompatibilitätskataloge erkennen historische IDs in Profilen und unveränderlichen Marsch-Snapshots; die Sammlungen ergänzen nur eigenen Altbesitz. Burgen, Märsche und Namensrahmen behalten ihre vorhandenen Originalbilder bzw. Motive und ihre Ausrüstungsrechte. Alle 88 aktiven/historischen Bild- und Animationspfade wurden geprüft. Diese Dateien sind weiterhin benutzt und wurden deshalb nicht gelöscht.

Neue Käufe archivierter Marsch-Skins und Themenreihen werden vor Belastung/Bestellerstellung serverseitig abgelehnt. Wiederholung und signierte Erfüllung bereits bestehender Bestellungen bleiben möglich und idempotent; Besitz und Spielstände werden nicht migriert oder überschrieben. Neue Nutzer sehen keine unbenutzbaren Altangebote. Lokale Entwicklungsgeschenke beziehen sich nur auf den aktiven Katalog.

Der aktuelle Echtgeldkatalog enthält ausschließlich archivierte Motive. Neue Elementarpakete werden erst mit ausdrücklich festgelegten Inhalten und Besitzregeln veröffentlicht; diese Prüfung erfindet keine neuen Preise oder Paketleistungen. Die Shopanzeige kann aktuelle gültige Serverangebote weiterhin darstellen. Ihr UI-Test nutzt dafür ausdrücklich synthetische aktuelle Angebote.

Die Sammlung verwendet bereits ein scrollbares Kartenraster. Alte Tests mit 18 Einträgen und fünf festen Seiten wurden auf den tatsächlichen Fünferkatalog, alle per Scrollen erreichbaren Karten und zusätzlich gekauften Altbesitz umgestellt. Die Vierer-Tabbar erhielt bei kurzem Querformat kleinere innere Abstände gegen Überlauf.

## Abnahme

Bestanden: `theme_bundles.php` (16 Prüfungen), `march_skins.php` (24), `local_free_skins.php` (18), jeweils isolierte FeatureDatabase; `march_skin_app.cjs` (44 echte App-/API-Prüfungen), `skin_picker.cjs`, `march_skin_collection.cjs`, `name_frames.cjs`, `theme_bundle_shop.cjs`, `march_motion_contract.cjs`, `premium_march_assets.cjs`, `world_skin_effects.cjs` und `world_march_skins.cjs`. Browserläufe umfassen Desktop, schmale Handys und Querformat. Syntaxprüfung der betroffenen PHP-/JavaScript-Dateien bestanden.

`march_skin_world_app.cjs` ist jetzt vollständig bestanden: Kauf, Bonus, exakte ETA, animierte Darstellung, unveränderter Snapshot nach Wechsel/Neuladen, Kameraverfolgung und erreichbare Aktionen in fünf Größen bis568×320 sowie tatsächlicher Rückruf mit unverändert leerer Browserfehlerliste. Auch historische Marschbilder und alle88 Projektpfade bleiben geprüft.
