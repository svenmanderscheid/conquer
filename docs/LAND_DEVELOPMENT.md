# Landentwicklung und Weltcharms

Stand: 12. September 2026

Die erste technische Grundlage für **E16 Landentwicklung**, **A01 Monsterbeute** und **A02 Kartencharms** ist umgesetzt. Sie umfasst dauerhafte Landstufen, drei Kartenphasen, serverseitige Zielsperren, versionierte Beuteregeln und den vollständigen Charm-Weg für Weltmonster. Das ist noch nicht die Umsetzung aller Erweiterungen E01–E42. Von E33 „Beute- und Wirtschaftsstatistik“ sind nur Killbelege und die Reward-Revisionsgrundlage vorhanden.

## Landmodell

Jede Welt wird anhand ihrer eigenen `worlds.map_size` in feste Landteile von **8 × 8 Kartenfeldern** geteilt. Eine Karte mit 256 × 256 Feldern besitzt damit genau **32 × 32 = 1.024 Landteile**. Karten, deren Kantenlänge nicht durch acht teilbar ist, erhalten am rechten und unteren Rand entsprechend kleinere Restflächen.

Jedes Land besitzt eine dauerhafte Stufe von 1 bis 9. Die Entfernung zum Kartenmittelpunkt bestimmt nur die Startstufe:

- Außenbereich: Stufe 1
- Mittlerer Bereich: Stufen 2 bis 6
- Kleines Zentrum: Stufen 7 bis 9

Jedes Land kann durch Beiträge Stufe 9 erreichen. Landstufe und Monsterstufe sind getrennte Werte. Ein Aufstieg verändert keine vorhandenen Monster, Rohstofffelder oder laufenden Aufträge; nur neue Spawns verwenden den neuen Stand.

Die Zuordnung ist deterministisch. Der fachliche Schlüssel ist `world_id + geometry_version + parcel_x + parcel_y`. `LandProgressService::at()` liefert den gespeicherten Vertrag mit Land-ID, Stufe, Zone, Startstufe, Offenstatus und aktueller Regelrevision.

## Entwicklungspunkte

Punkte entstehen ausschließlich aus bestätigten Ergebnissen:

- ein wirklich besiegtes Weltmonster,
- die tatsächlich eingesammelte Rohstoffmenge,
- eine atomar abgebuchte Ressourcenspende.

Das Absenden oder Zurückrufen allein gibt keine Punkte; tatsächlich bis zum Rückruf gesammelte Rohstoffe zählen. Jeder Kill, Sammelabschluss und Spendenrequest besitzt einen eindeutigen Beleg. Ein identischer Retry liefert das gespeicherte Ergebnis; eine wiederverwendete Kennung mit anderen Angaben wird abgewiesen.

Die ausgelieferten Startwerte sind pro Welt änderbar:

| Regel | Startwert |
|---|---:|
| Punkte Stufe 1 → 2 | 1.000 |
| Danach je Stufe | 2.000, 4.000, 8.000, 16.000, 32.000, 64.000, 128.000 |
| Jagdpunkte | 100 je tatsächlicher Monsterstufe |
| Ressourcen je Basispunkt | 100 gewichtete Einheiten |
| Gewicht Nahrung / Holz / Stein / Gold | 1 / 1 / 2 / 4 |
| Sammeln mit voller Wirkung | bis 10 % der aktuellen Stufenschwelle je Spieler, Land und Tag |
| Sammelwirkung danach | 25 % |
| Spendenlimit | 10 % der aktuellen Stufenschwelle je Spieler, Land und Tag |

Die Grenzwerte sind Balancing-Defaults. Änderungen setzen Landstufen und Fortschritt nicht zurück. Bereits ausgestellte Belege bleiben auch nach einer Regeländerung wiederholbar.

**Kristallspenden sind nicht aktiv.** Die Produktentscheidung zwischen einer neuen weltgebundenen Entwicklungswährung und vorhandenen Edelsteinen ist weiterhin offen. Aktuell können Nahrung, Holz, Stein und Gold gespendet werden.

## Drei Kartenphasen

Neue Welten beginnen mit geöffnetem Außenbereich. Mitte und Zentrum werden weltweit und dauerhaft freigegeben, wenn ihre Entwicklungsvorgaben und die eingestellte Mindestlaufzeit erfüllt sind.

| Öffnung | Ausgelieferter Startwert |
|---|---|
| Mitte | 10 % der entwickelbaren Außenländer, mindestens 32, erreichen Stufe 3; frühestens 14 Tage nach Weltstart |
| Zentrum | 15 % der entwickelbaren Länder der Mitte, mindestens 24, erreichen Stufe 7; frühestens 14 Tage nach Öffnung der Mitte |

Nur trockene, für Spawns geeignete Länder der bereits geöffneten Vorgängerzone zählen. Ein Gate mit null geeigneten Ländern ist ungültig. Eine reine Zeitfreigabe kann je Welt als optionaler Fallback eingestellt werden; sie ist im Startzustand ausgeschaltet.

Bestehende Welten werden schonend übernommen: Alle drei Bereiche bleiben offen, vorhandene Ziele werden nicht versetzt oder neu bewertet. Neue Welten starten mit der Außenphase. Am 12. September 2026 wurde die vorhandene Welt 1 lokal mit **1.024 Ländern** initialisiert; ihre drei Zonen blieben offen.

## Serverregeln und Kartenansicht

Gesperrte Bereiche werden serverseitig für neue Ziele und Aktionen abgewiesen. Das gilt für Spawns, Stadtplatzierung und Teleport, Monster- und Spielermärsche, Sammeln, Rallys sowie geschützte Weltaktionen. Feste Landmarken dürfen beim Erstellen einer Welt bereits im Zentrum stehen; ihre Anzeige und Benutzung bleiben bis zur Freigabe gesperrt.

Die Karten-APIs verwenden die aktive Session-Welt und deren echte Kartengröße. Monster, Rohstofffelder, Städte, Schreine, Charms und Rallyziele in gesperrten Zonen werden nicht als erreichbare Ziele ausgeliefert. Fremde `world_id`-Werte werden abgewiesen. Die Reise zwischen zwei offenen Zielen bleibt im ersten Ausbau abstrakt; eine gezeichnete Gerade durch eine gesperrte Zone blockiert den Marsch nicht.

Neue Monster und Rohstofffelder speichern `land_part_id`, `regional_level_at_spawn` und `spawn_rule_revision`. Monster speichern zusätzlich ihre wirksame Monsterstufe und den bei ihrem Spawn geltenden Wert für Landpunkte. Dadurch bleiben laufende Kämpfe trotz späterer Land- oder Adminänderungen stabil.

## Öffentliche API

Alle Antworten verwenden den üblichen Umschlag `{ok, data, error}`.

- `GET /api/land/state` liefert einschließlich `map_size` die 8×8-Geometrie, alle kompakten Länder und den Fortschritt beider Zonengates. Dieser Abruf gehört zur eigenen Landübersicht und nicht zum normalen Game-Polling.
- Der normale Spielzustand enthält nur den kompakten Landstand der eigenen Stadt sowie `map_size`, Zonengates und deren aus derselben Geometrie berechnete Begrenzungsrechtecke für die Kartendarstellung.
- `GET /api/land/{id}` liefert Fortschritt, Beitragsquellen, heutige eigene Beiträge, letzte Ereignisse, die aktuell möglichen Monster und `donation` mit Ressourcenwerten, Einheiten je Punkt, Tageslimit und verbleibenden Punkten.
- `POST /api/land/{id}/donate` verlangt Anmeldung, CSRF-Token, `expected_world_id`, Landrevision, eine 16–80 Zeichen lange `request_id` und `resources`.
- `GET /api/map/info`, `/api/map/tiles`, `/api/map/tile/{x}/{y}` und `/api/map/field-object/{id}` sind auf die aktive Welt und deren offene Bereiche begrenzt.

Stufe 9 liefert `next_threshold: null` und `progress_pct: 100`. `own_land` bezeichnet den Landteil mit der Stadt des angemeldeten Spielers in dieser Welt.

## Administration

Unter `/admin/lands` stehen je Welt zur Verfügung:

- Stufenverteilung und Status der drei Kartenbereiche,
- Schwellen für Stufe 1–9,
- Jagd-, Sammel- und Ressourcengewichte,
- Tagesquoten,
- Zielstufe, Anteil, Mindestanzahl, Mindestlaufzeit und optionaler Zeitfallback je Gate.

Speichern verwendet eine optimistische Revision und serverseitige Validierung. Bestehender Fortschritt bleibt erhalten.

Unter `/admin/rewards` lassen sich globale Grundregeln und Abweichungen für eine Welt bearbeiten. Neue Kämpfe übernehmen die aktuelle Revision; laufende Kämpfe behalten ihren gespeicherten Beutestand. Monster, Dungeons, Truhen und Feldzüge verwenden dieselbe Belohnungsgrundlage. Eine gemeinsame Vorschau zeigt Erwartungswerte für 100 Siege, und die letzten 20 Regelrevisionen bleiben sichtbar. Die Adminansicht unterscheidet aktive Monster von vorbereiteten Katalogeinträgen. Deathkar, Magdar sowie grüner, goldener und roter Drache bleiben inaktiv.

## Weltcharms und Monsterbeute

Ein bestätigter Solo- oder Rally-Kill eines aktiven Weltmonsters erzeugt genau einen öffentlichen Charm am gespeicherten Spawnort. Eine Rally erzeugt einen Charm für den Kill, nicht einen je Teilnehmer. Direkte Ressourcen, Edelsteine und Gegenstände werden aus der beim Start gespeicherten Belohnungsrevision ermittelt.

Spieler senden Truppen zum Charm. Bei mehreren Sammlern gewinnt die früheste gültige Ankunft; bei Zeitgleichheit entscheidet die kleinere Marsch-ID. Verlierer kehren mit ihren Truppen zurück. Sammlung, Effektaktivierung, Ablauf und Heimkehr sind wiederholsicher. Der Charm blockiert seinen Kartenpunkt bis zur Sammlung oder bis zum Ablauf.

Für Dungeons ist noch kein gespeicherter Weltanker definiert. Dungeonabschlüsse erzeugen daher in diesem Ausbaustand keinen Kartencharm.

## Datenbank und Prüfung

Das lokale Installationswerkzeug wurde erfolgreich ausgeführt:

```powershell
php tools/migrate-land-charms.php --apply
```

Es wendet die additiven Migrationen `0083` bis `0087` an und initialisiert die Landteile vorhandener Welten. Es verschiebt oder löscht keine aktiven Ziele. Der Befehl ohne `--apply` zeigt den Status.

Die wichtigsten isolierten Prüfungen sind:

```powershell
php tests/land_progression.php
php tests/map_land_access.php
php tests/reward_admin.php
php tests/monster_rallies.php
php tests/charm_lifecycle.php
php tests/multiworld_integration.php
```

Die Featuretests arbeiten mit Wegwerf-Datenbanken und wenden `0083` bis `0087` dort automatisch an. Geprüft werden unter anderem 1.024 Landteile auf einer 256er-Karte, abweichende Kartengrößen, Aufstieg aller Länder bis Stufe 9, Gates, Retries nach Regeländerungen, Welttrennung, gesperrte Zieltypen, 19 Reward-/Vorschaufälle, Solo- und Rally-Beute sowie konkurrierende Charm-Sammler.

## Bewusste Grenzen dieses Ausbaustands

- E01–E42 bleiben bis auf das erste Fundament von E16 offen.
- E33 „Beute- und Wirtschaftsstatistik“ besitzt bisher Killbelege und die Reward-Revisionsgrundlage. Aggregationen über reale Auszahlungen, Quellen und Verbrauch fehlen; die Vorschau mit Erwartungswerten ist kein Statistikdashboard.
- Es gibt noch keine vollständige Registry für alle Template-Schlüssel und keine allgemeine Draft/Preview/Publish-Richtlinie für sämtliche Inhalte.
- Dungeon-Weltanker für Kartencharms fehlen.
- Die Bedeutung und Finanzierung von Kristallspenden ist ungeklärt; der Pfad bleibt inaktiv.

Die vollständige Reihenfolge und die noch offenen Pakete stehen in [planning/IMPLEMENTATION_PLAN.md](planning/IMPLEMENTATION_PLAN.md). Die ursprünglichen Fachentscheidungen und verworfenen Alternativen bleiben in [planning/LAND_PROGRESSION_AGENT.md](planning/LAND_PROGRESSION_AGENT.md) nachvollziehbar.
