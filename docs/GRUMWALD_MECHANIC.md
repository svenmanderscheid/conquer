# Grumwald: Root Regrowth

Stand: 1. Oktober 2026. Die erste eigene Regionalboss-Regel verwendet das
bestehende, einmalig abgerechnete Kampfmodell von Union of Kingdoms.

Nach einem Angriff, den Grumwald überlebt, regeneriert er **12 % des gerade
erlittenen Schadens**, auf ganze Lebenspunkte abgerundet. Ein tödlicher Treffer
bleibt tödlich. Die Heilung erhöht weder die vorherigen Lebenspunkte noch die
Verwundetenquote und startet keinen zusätzlichen Hintergrundtimer.

**Mindestens 50 % Fernkampfanteil an der Grund-Truppenmacht** unterdrückt die
Regeneration. Entscheidend ist die im Truppenkatalog angegebene Macht, nicht die
Truppenanzahl und nicht Forschungs-, Talent- oder Ausrüstungsboni. Bei einer
Rally zählen alle tatsächlich zum Kampf angekommenen Truppen zusammen. Viele
niedrige Fernkämpfer neben einer sehr starken Nahkampfarmee umgehen die Grenze
dadurch nicht allein über die Anzahl.

Die Schwelle wurde am 1. Oktober von 30 % auf 50 % erhöht. Bereits gestartete
Rallies behalten die beim Erstellen gespeicherte Schwelle.

Die 0%-Glück-Vorschau berücksichtigt dieselbe Regel wie der tatsächliche Kampf.
Ihre vorhandene Grenze bleibt: Sie rechnet nur den ausgewählten eigenen Beitrag.
Andere Teilnehmer können den Fernkampfanteil erhöhen oder senken; tatsächliches
Kampfglück und Zustand bei Ankunft können das Ergebnis ebenfalls ändern.

## Gespeicherte Regeln und historische Aufträge

`MonsterData::definition()` ergänzt ausschließlich den aktuellen Grumwald-Katalog
(Codes 20202401 bis 20202410) um `boss_mechanic`, Version 1. Der vorhandene
Rally-Start speichert die vollständige Monsterdefinition. `BattleEngine` wertet
nur diese übergebene Definition aus und ergänzt bei der Abrechnung keine neue
Regel. Bereits gestartete Aufträge ohne `boss_mechanic` bleiben deshalb bei der
bisherigen Berechnung. Die anschließende Erweiterung um eigene Fähigkeiten für
Frostgrimm, Sandmaul, Glutramm und Dämmerhorn steht in
`RALLY_BOSS_SKILLS.md`; Grumwalds bestehende Regel bleibt dabei unverändert.
PvP, Dungeons, Beutetabellen und AP-Kosten werden nicht geändert.

Karte und Suche liefern die Regel in `definition.boss_mechanic`. Die Vorschau
liefert sie unter `boss_mechanic`, historische Berichte unter
`details.boss_mechanic`, zusätzlich mit:

- `countered`, `counter_power`, `total_base_power`, `counter_power_share_percent`
- `hp_before_regeneration`, `hp_restored`, `hp_after_regeneration`
- `damage_before_regeneration`, `damage_after_regeneration`

Die Oberfläche verwendet den serverseitigen Wert `countered`, nicht den für die
Anzeige gerundeten Prozentanteil. `monster_hp_after`, die gespeicherten
Monster-Lebenspunkte und die Schadensquote enthalten bereits die Regeneration.

## Prüfung

`tests/boss_mechanics.php` prüft ohne Datenbank die Schwelle, unterschiedliche
Truppenstufen, gemeinsame Rallys, Forschungsboni, echte Kampfauflösung,
deterministische Vorschauen, Heilung ohne Wiederbelebung, Rundung, historische
Snapshots und unveränderte andere Monster.

`tests/grumwald_rally.php` verwendet eine isolierte Wegwerf-Datenbank für
Rally-Start, gespeicherte Regel, Vorschau, echte Abrechnung und Berichte,
wiederholte Ticks, rechtzeitig angekommene Fernkämpfer sowie einen alten Auftrag
ohne Regel. Bestehende Spieler und Welten werden nicht verändert.

Am 30. September lokal bestanden: 44 reine Mechanikprüfungen, 13 neue
Rally-Lebenszyklusprüfungen, 75 bestehende Monster-Rally-Prüfungen, die
Kampfprognose einschließlich authentifizierter HTTP-Negativfälle sowie die
Regionalboss-Suite. Zwei ältere Testannahmen wurden an den bereits bestehenden
Stand angepasst: Der Eigentümerbonus-Test verwendet den aktuellen
Truppenkatalog statt des früheren T1-Angriffs 1; der isolierte HTTP-Server
übernimmt wie `.user.ini` die abgeschaltete Fehlerausgabe, damit ein
Windows-PHP-Startup-Hinweis beim Puffern großer Anfragen JSON und HTTP-Status
nicht vor dem Handler verfälscht. Die fachlichen Prüfungen bleiben unverändert.
