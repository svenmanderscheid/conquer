# Union of Kingdoms – Namen der Eroberungsstufen

Verbindliche Nutzerentscheidung vom 29. September 2026: **Commune → Shrine → Royal Castle**. In Übersichten heißen die Kategorien **Communes · Shrines · Royal Castle**. Die übrigen deutschen Erläuterungen bleiben deutsch.

| Ebene | Sichtbarer Name | Beispiel |
|---|---|---|
| Gemeindeziel | Commune | Commune Vianden |
| Eroberungsziel eines Kantons | Shrine | Shrine of Mersch |
| Oberstes Kontinentziel | Royal Castle | Royal Castle |

Ein **Kanton** bleibt die geografische Region mit mehreren Communes. Nach der Mehrheit dieser Communes darf seine Allianz den Shrine herausfordern. Ein gehaltener Shrine eröffnet die Teilnahme am Kampf um das Royal Castle. Besitzgrenzen, Boni, Bilder und Eroberungsregeln bleiben unverändert.

Die Bezeichnung Congress wird für die neue Luxemburg-Eroberung nicht verwendet. Das bestehende Schrein-/Kongresssystem der bisherigen Weltkarte wird durch diese Namensänderung nicht aktiviert oder verändert.

Neue Welten erhalten die Namen aus `data/luxembourg_landmarks.json`. Die Anzeige übersetzt bisher gespeicherte Standardnamen (Vogtei, Markfeste, Krounbuerg) beim Lesen. Stabile Zielkennungen wie `canton:01`, `crown:krounbuerg`, technische Arten `commune/canton/crown` sowie Bildpfade bleiben erhalten. Eine Datenbankmigration ist dafür nicht erforderlich. Bereits gespeicherte historische Kampfberichte werden nicht umgeschrieben.

Die Namen werden als feste Spielbegriffe vor der automatischen Oberflächenübersetzung geschützt. Das bisherige Schrein-System behält seine vorhandenen Übersetzungen. Die HTTP-Prüfung deckt gespeicherte Altnamen ab; die Haupt-App-Prüfung kontrolliert Namen in Gebietsfenstern, Kartenmarkern, Kartenaktionen und im Adminbereich sowie die Bedienung in fünf Bildschirmgrößen.
