# Regionalentwicklung (E16) – umsetzbarer Fach- und Integrationsplan

Stand: 12. September 2026. Dieses Dokument bewahrt den ursprünglichen Fach- und Integrationsplan und ergänzt den tatsächlich gelieferten Stand. Die verbindliche Betriebs- und API-Beschreibung steht in [LAND_DEVELOPMENT.md](../LAND_DEVELOPMENT.md).

## Umgesetzter Stand

Die erste E16-Grundlage ist umgesetzt und lokal migriert. Landteile sind verbindlich 8×8 Felder groß; auf 256×256 entstehen exakt 1.024 Teile. Außen startet auf 1, die Mitte auf 2–6 und der kleine Kern auf 7–9. Alle Länder können dauerhaft Stufe 9 erreichen. Bestehende Welten bleiben offen, neue Welten durchlaufen Außen → Mitte → Zentrum. Schwellen, Beitragswerte und Gates sind je Welt im Adminbereich einstellbar.

Jagd nach bestätigtem Kill, tatsächlich gesammelte Mengen und atomare Ressourcenspenden erzeugen wiederholsichere Belege. Spawns speichern Land, Regionalstufe und Regelrevision; vorhandene Ziele und laufende Aufträge werden nicht nachträglich verändert. Die zentrale Access Policy schützt Ziel-, Spawn-, Platzierungs-, Teleport-, Marsch-, Sammel- und Rallywege. Karten- und Land-APIs sind weltbezogen und filtern gesperrte Ziele.

Die Migrationen `0083` bis `0087` wurden lokal mit `php tools/migrate-land-charms.php --apply` angewandt. Welt 1 wurde mit 1.024 Ländern initialisiert; als Bestandswelt behielt sie alle drei offenen Zonen.

Abweichungen vom vollständigen Zielbild dieses Plans:

- Es gibt noch keine eigene, vollständige `world_monster_spawn_rules`-Registry mit frei gepflegten Template-Schlüsseln. Der erste Spawner verwendet aktive Katalogdefinitionen, Weltgewichte, Biom und Landstufe.
- Es gibt keinen allgemeinen Draft/Preview/Publish-Ablauf für sämtliche Inhaltsrichtlinien. Reward-Regeln besitzen globale und weltbezogene Overrides sowie Revisionen; Landregeln werden direkt validiert und revisioniert gespeichert.
- `land_progress_attributions` und persönliche Rally-Schadensanteile wurden nicht eingeführt. Der bestätigte Kill schreibt den Landwert genau einmal gut.
- Dungeonabschlüsse besitzen noch keinen gespeicherten Weltanker und erzeugen deshalb keinen Kartencharm.
- Entwicklungskristalle und Kristallspenden wurden nicht implementiert. Ihre Bedeutung bleibt die einzige offene Produktentscheidung dieses Pakets.
- E01–E42 bleiben außerhalb dieser ersten E16-Grundlage offen. Von E33 „Beute- und Wirtschaftsstatistik“ sind nur Killbelege und die Reward-Revisionsbasis vorbereitet; reale Auszahlungs-, Quellen- und Verbrauchsaggregate fehlen.

## Festes Modell

**Umgesetzt:** Die Welt wird deterministisch in Landteile von 8×8 Kartenfeldern geteilt. Für eine Welt mit `worlds.map_size = s` gilt `n = ceil(s / 8)`, `parcel_x = intdiv(x, 8)`, `parcel_y = intdiv(y, 8)` und der Landteil-Mittelpunkt `c = (n - 1) / 2`. Die Datenbank-ID ist nur ein technischer Schlüssel; der fachliche Schlüssel bleibt `(world_id, parcel_x, parcel_y, geometry_version)`. Randlandteile einer nicht durch acht teilbaren Welt werden auf die tatsächlich vorhandenen Felder beschnitten.

Jeder erreichbare Landteil hat:

- `initial_level` (1–9): ausschließlich aus seiner Entfernung zum Zentrum berechnet;
- `current_level` (1–9): persistenter Iststand;
- `progress_points`: Fortschritt bis zur nächsten Stufe;
- **keine entfernungsabhängige Maximalstufe**: jeder geöffnete Landteil kann Stufe 9 erreichen;
- `zone_key` und einen persistenten Freigabestatus.

Damit ist der Zielkonflikt sauber gelöst: Ein äußerer Landteil mit Maximalstufe 1 wäre trotz Beiträgen nie entwickelbar. Deshalb ist „außen Stufe 1“ eine Startstufe, keine Obergrenze. Ebenso ist das Zentrum nicht sofort nutzbar, obwohl es beim späteren Öffnen höher startet.

Für `q = max(abs(parcel_x - c), abs(parcel_y - c))` und `r = q / max(c, 0.5)` gilt die **umgesetzte, weltgrößenunabhängige Geometrie**: `center` bei `r < 8/31`, `middle` bei `8/31 <= r < 20/31`, sonst `outer`. In der Mitte startet der Kern bei `r <= 3/31` auf Stufe 9; der übrige Zentralbereich beginnt abgestuft auf 7 oder 8. Die Mitte startet in fünf gleich breiten Distanzbändern auf Stufe 2–6. Nur die Außenzone startet auf 1.

Die heutige 256×256-Welt ist damit folgendes konkretes Beispiel (`n = 32`, `c = 15.5`):

| Zone | Ring | Landteile vor Wasserprüfung | Startstufe |
|---|---:|---:|---:|
| `outer` | `q >= 10.5` | 624 | 1 |
| `middle` | `4.5 <= q <= 9.5` | 336 | von außen nach innen 2–6 |
| `center` | `q <= 3.5` | 64 | 7, 8; kleiner Kern `q <= 1.5` startet 9 |

Im 256er-Beispiel umfasst der Stufe-9-Kern höchstens 16 Landteile beziehungsweise 32×32 Kartenfelder und liegt um Weltensee/Kongress. Wasser wird nicht zum Fortschrittsziel: `developable_tile_count` und `has_spawn_anchor` werden mit `WorldTerrain::isDryRectangle()` ermittelt. Freischaltquoten zählen nur Landteile mit mindestens einem gültigen trockenen Spawnanker. Mittelpunkt und Grenzen werden stets aus `worlds.map_size` abgeleitet; `data/world_terrain.json` und `src/Game/Map/WorldTerrain.php` liefern die aktuelle Terrainprüfung.

## Drei Freigabephasen

Neue Welten öffnen nur `outer`. `middle` und `center` sind echte serverseitige Zustände, keine reine Nebelgrafik.

**Empfehlung:** Entwicklung ist das primäre Gate. Ein optionales `not_before` verhindert einen sofortigen Durchmarsch; ein reines Zeit-/ODER-Fallback bleibt eine abschaltbare Alternative für nachweislich schwach bevölkerte Welten. Nur bereits geöffnete und erreichbare Landteile dürfen das nächste Gate erfüllen, damit keine zyklischen Ziele entstehen.

| Öffnung | Entwicklungsziel (Balancingvorschlag) | Optionale Mindestzeit (Balancingvorschlag) |
|---|---|---|
| `middle` | `min(eligible_outer, max(32, ceil(eligible_outer * 0.10)))` äußere Landteile auf mindestens Stufe 3 | 14 Tage nach Weltstart |
| `center` | `min(eligible_middle, max(24, ceil(eligible_middle * 0.15)))` mittlere Landteile auf mindestens Stufe 7 | 14 Tage nach Öffnung der Mitte |

Auswertung im empfohlenen Profil: `not_before` und Entwicklungsziel müssen erreicht sein. Das Zentrumsgate verlangt Stufe 7, damit die auf höchstens 6 gestarteten Mittellandteile mindestens einmal tatsächlich entwickelt wurden. Optional kann `fallback_after_days` gesetzt werden; dann ersetzt die abgelaufene Fallback-Zeit nur das Entwicklungsziel, niemals `not_before`. Der Standardwert ist `NULL` (kein Zeit-Fallback). `eligible = 0` ist eine ungültige Welt-/Terrainkonfiguration und öffnet niemals automatisch eine Zone. Alle Zahlen einschließlich der beiden 14-Tage-Werte sind abstimmbare Weltregeln, keine fest beschlossenen Balancewerte. Die Öffnung erfolgt in einer Transaktion unter Welt-/Zonenlock und erzeugt genau ein Ereignis `land.zone_opened`.

Geschlossene Zonen werden an allen Schreibgrenzen geprüft:

- keine neuen Monster, Rohstofffelder, Städte, Schreine oder Ereignisziele;
- keine Ansiedlung und kein Teleportziel;
- kein Angriff, Scout, Sammelmarsch, Charm-Marsch, Verstärkung oder Rally-Ziel in einer geschlossenen Zone;
- Karten- und Such-APIs liefern dort nur Zone, Freigabefortschritt und Nebel, keine verborgenen Ziele.

**Transitentscheidung für das MVP:** Nur Zielgebiete werden gesperrt; Marschbewegung bleibt abstrakt. Eine Prüfung der heutigen geraden Darstellung würde sonst erreichbare Außen→Außen-Ziele blockieren, sobald die Linie die geschlossene Mitte schneidet. Falls geschlossene Zonen später auch physische Barrieren sind, braucht es zuerst serverseitige Wegfindung um diese Zonen, persistierte Waypoints, daraus berechnete Reisezeiten und dieselbe Route im Client.

Zentrale Policy: neue Klasse `src/Game/World/LandAccessPolicy.php` mit `isOpen()` und `assertTargetOpen()`. Aufrufer sind mindestens `WorldPlacement`, `WorldSpawnService`, `FrontierService`, OAuth-Stadtplatzierung, Teleport in `KingdomInventory`, `MarchDispatcher`, `GatherService`, `MonsterRally`, `RallyService`, `CongressService` und Ereignisplatzierungen. So kann kein alter oder zweiter Endpoint die Zielsperre umgehen.

## Fortschritt und faire Beiträge

**Empfehlung:** Stufen sind dauerhaft und werden nicht saisonal zurückgesetzt. Eine Stufe erhöht zunächst nur die lokal neu entstehenden Inhalte; Kampfwerte bestehender Ziele werden nie nachträglich geändert.

Inkrementelle Schwellen je Aufstieg sind als abstimmbare Startwerte: `1→2: 1.000`, `2→3: 2.000`, danach `4.000 / 8.000 / 16.000 / 32.000 / 64.000 / 128.000` Entwicklungspunkte. Ein innerer Landteil beginnt beim Öffnen auf seiner Startstufe mit null Punkten zur nächsten Stufe.

Beitragsregeln:

1. **Monsterjagd:** Landfortschritt entsteht in der ersten Version ausschließlich beim bestätigten Kill. Jedes neu gespawnte Monster erhält genau einen `regional_point_value`; Solo- oder Rally-Auflösung darf diesen Wert über einen eindeutigen Killbeleg nur einmal dem Landteil gutschreiben. Teiltreffer erzeugen keine vorläufigen Landpunkte. Der MVP speichert beim Rallykill die beteiligten Spieler, verteilt aber keine Landpunkte pro Person. Persönliche Assistenzstatistiken nach tatsächlichem Schaden sind ein späterer, rein statistischer Ausbau. Der Landteil erhält unabhängig von der Teilnehmerzahl genau den einen Monsterwert.
2. **Sammeln:** Punkte entstehen in `GatherService::finishOne()` aus der tatsächlich atomar vom Feld abgezogenen Menge, auch bei Rückruf nur für die wirklich gesammelte Menge. Start, Ankunft oder Rückkehr dürfen keine zweite Gutschrift auslösen.
3. **Ressourcenspende:** Nahrung/Holz, Stein und Gold erhalten konfigurierbare Wertfaktoren (Vorschlag 1/1/2/4); 100 Wertpunkte ergeben einen Entwicklungspunkt. Abbuchung, Gutschrift und Beleg liegen in derselben Transaktion. Pro Spieler, Landteil und UTC-Tag werden höchstens 10 % der aktuellen Stufenschwelle durch Spenden akzeptiert; der Server weist einen Überschuss vor der Abbuchung zurück.
4. **Kristalle:** empfohlen ist eine neue, erspielbare und nicht kaufbare, **weltgebundene** Währung `development_crystal` (Vorschlag: 100 Entwicklungspunkte je Stück). Sie liegt nicht im vorhandenen möglicherweise accountweiten Inventar, sondern in einem Saldo mit unveränderlichem Ledger je `(player_id, world_id)`. A01 kann sie als eigenen Belohnungstyp `world_currency` vergeben. Sie ist ausdrücklich weder `players.gems` noch ein A02-Karten-Charm. Wird stattdessen die bestehende Premiumwährung „Edelsteine“ gemeint, braucht sie einen erheblich niedrigeren Tagesdeckel und eine bewusst bestätigte Pay-to-progress-Regel.
5. **Dämpfung statt Mindestspielerzahl:** Sammeln zählt je Spieler/Landteil/Tag bis 10 % der aktuellen Schwelle voll, danach zu 25 %; Spenden haben den genannten harten Deckel. Killpunkte bleiben im MVP undämpft, weil AP, Spawnrate und der einmalige Killbeleg sie bereits begrenzen und eine Rally keinen eindeutigen Einzelakteur hat. Es gibt keine Mindestzahl unterschiedlicher Spieler, damit kleine Welten nicht blockieren. Rohpunkte, gutgeschriebene Punkte, beteiligte Spieler und Allianzzugehörigkeit zum Ereigniszeitpunkt bleiben sichtbar.

Die genauen Schwellen, Umrechnungen, Tagesgrenzen und Dämpfung sind **Balanceempfehlungen**, keine aus der Anforderung abgeleiteten Regeln.

## Monster- und Ressourcenstufen

Landstufe, Monsterstufe und Rohstofffeldstufe sind drei verschiedene Begriffe. `WorldSettings` erlaubt heute Rohstoffstufen nur 1–3; E16 darf daraus keine Rohstoffstufen 4–9 machen.

**Empfehlung:** Der Spawner wählt zuerst einen gültigen Punkt in einem geöffneten Landteil und liest dessen `current_level`. Erst danach wählt er aus einer expliziten Spawnfreigabe. Pro Monstercode gelten `enabled`, `min_land_level`, `max_land_level`, `weight`, `biome` und `regional_point_value`. Die tatsächliche Monsterstufe bleibt ein eigener beliebiger Integer und wird beim Spawn als `effective_monster_level` gespeichert. Damit funktionieren Stufe 0/1-Aliase, Stufe 10 und spätere Monsterstufen oberhalb 10, ohne neun Monsterstufen vorauszusetzen.

Beispiel, nur als Seedregel für ausdrücklich aktive Familien: Monsterstufen 1–2 ab Land 1, 3 ab Land 2, 4 ab Land 3, …, 9 ab Land 8, 10 und höher ab Land 9; niedrigere Gegner können über `max_land_level = 9` weiterhin vorkommen. `MonsterData` bleibt für Kampfdefinitionen zuständig, die neue Spawnregel für Verfügbarkeit.

**Verbindliche Bestandskorrektur:** Deathkar, Magdar, Green Dragon, Gold Dragon und Red Dragon sind derzeit nicht aktiv. Sie werden mit `enabled = 0` angelegt und weder aus `data/monsters.json`, `data/world_spawn.json` noch aus heutigen `WorldSettings::monster_weights` als verfügbar abgeleitet. Dass ein Eintrag im Katalog, in alten Gewichten oder in `rally_rewards.json` steht, ist kein Aktivitätsnachweis. Die Aktivliste wird aus den tatsächlichen Spawn-Schreibwegen und wirksamen Weltkonfigurationen auditiert; vorhandene Livezeilen werden lediglich als Altziele erhalten. Dasselbe Vorsichtsprinzip gilt für andere reine Katalogdefinitionen.

Rohstofffelder behalten Level 1–3. Der erste Ausbau hält ihre vorhandene weltbezogene Stufen- und Dichtekonfiguration bei, beschränkt neue Positionen aber auf offene Länder und speichert den Land-Snapshot. Neue Monster werden in `WorldSpawnService` nach offener Position, Landstufe, Biom und aktivem Katalog ausgewählt. `FrontierService` verwendet dieselbe Platzierungspolicy und stempelt seine Einstiegsspawns ebenfalls mit dem Landstand. Eine später frei pflegbare Mischung der Ressourcenstufen nach Landlevel bleibt offen.

## Persistenz, Snapshots und Belege

Das folgende ursprüngliche Zielschema enthält auch spätere Ausbaustufen. Umgesetzt sind `world_land_rules`, `world_land_zones`, `world_land_parts`, `land_progress_events` und `land_daily_contributions` sowie die nullable Spawn-Snapshotfelder. `land_progress_attributions`, eine frei pflegbare `world_monster_spawn_rules`-Registry und die Kristall-Wallet-/Ledger-Tabellen sind noch nicht vorhanden.

```sql
world_land_parts(
  id BIGINT PK, world_id INT, geometry_version SMALLINT,
  parcel_x SMALLINT, parcel_y SMALLINT, zone_key ENUM('outer','middle','center'),
  initial_level TINYINT, current_level TINYINT, progress_points BIGINT,
  developable_tile_count SMALLINT, has_spawn_anchor BOOL, revision INT,
  opened_at DATETIME NULL, updated_at DATETIME,
  UNIQUE(world_id, geometry_version, parcel_x, parcel_y)
)

world_land_zones(
  world_id INT, zone_key ENUM('outer','middle','center'),
  status ENUM('locked','open'), opened_at DATETIME NULL,
  opened_reason ENUM('initial','development','time','legacy','admin'),
  rule_revision INT, PRIMARY KEY(world_id, zone_key)
)

land_progress_events(
  id BIGINT PK, world_id INT, land_part_id BIGINT,
  event_key VARCHAR(100), source_type VARCHAR(24), source_id VARCHAR(80),
  raw_points BIGINT, credited_points BIGINT,
  level_before TINYINT, level_after TINYINT, metadata_json JSON,
  created_at DATETIME, UNIQUE(world_id, event_key)
)

land_progress_attributions(
  event_id BIGINT, player_id INT, alliance_id_snapshot INT NULL,
  raw_points BIGINT, credited_points BIGINT,
  PRIMARY KEY(event_id, player_id)
)

world_monster_spawn_rules(
  world_id INT, monster_code INT, enabled BOOL,
  min_land_level TINYINT, max_land_level TINYINT,
  weight INT, biome VARCHAR(20) NULL, regional_point_value INT,
  revision INT, PRIMARY KEY(world_id, monster_code)
)

player_world_currencies(
  player_id INT, world_id INT, currency_code VARCHAR(32), balance BIGINT,
  revision INT, updated_at DATETIME,
  PRIMARY KEY(player_id, world_id, currency_code)
)

player_world_currency_ledger(
  id BIGINT PK, player_id INT, world_id INT, currency_code VARCHAR(32),
  event_key VARCHAR(100), delta BIGINT, balance_after BIGINT,
  source_type VARCHAR(32), source_id VARCHAR(80), created_at DATETIME,
  UNIQUE(player_id, world_id, currency_code, event_key)
)
```

Schwellen, Quellenfaktoren und Gates können revisioniert in `world_spawn_settings.settings_json` unter `land_progression` liegen; `parcel_size` und `geometry_version` sind nach Welterstellung unveränderlich. Alternativ ist eine eigene Regelversionstabelle sauberer, sobald E34 den Inhaltseditor erhält.

Neue Monster speichern `land_part_id`, `regional_level_at_spawn`, `effective_monster_level`, `spawn_rule_revision` und `regional_point_value`; `field_objects` speichern Landteil, Regionalstufe und Regelversion. Die Zone bleibt über den unveränderlichen Landteil bestimmbar. A01-Beutevorschau/-auszahlung und A02-Charmregel lesen den Spawn- beziehungsweise Auftragssnapshot, nicht die später veränderte Landstufe. Laufende Kämpfe behalten zusätzlich ihre Belohnungsrevision.

Idempotente Ereignisschlüssel:

- `monster_kill:{field_monster_id}` für den einmaligen bestätigten Kill, unabhängig vom Auflösungsweg;
- `gather:{march_id}` für die tatsächlich beendete Sammelphase;
- `donate:{player_id}:{request_id}` für eine Spende;
- `zone_open:{world_id}:{zone_key}` für die Öffnung.

`LandProgressService` sperrt den Landteil mit `SELECT ... FOR UPDATE`, wendet die Tageswirkung an, führt bei Bedarf mehrere Stufenaufstiege aus und speichert den eindeutigen Ereignisbeleg in derselben Transaktion. Wiederholte Cron-, Polling- und Retry-Aufrufe liefern den gespeicherten Beleg. Adminänderungen laufen über `AdminService` mit `admin_operations` und `admin_audit_log`.

## Service- und API-Schnittstellen

Umgesetzte Kernklassen:

- `src/Game/World/LandGeometry.php`: reine Koordinate→Landteil/Zone/Startstufe-Funktion;
- `src/Game/World/LandProgressService.php`: atomare Gutschrift, Stufenwechsel und Beitragssicht;
- `src/Game/World/LandUnlockService.php`: Gateauswertung und einmalige Zonenöffnung;
- `src/Game/World/LandAccessPolicy.php`: zentrale Ziel- und Routenprüfung;
- `src/Api/Handlers/LandHandler.php`: Leseansichten und Spendenaktion.

Umgesetzte API:

- `GET /api/land/state` → Geometrie samt `map_size`, Zonenstatus/-ziel und alle kompakten Landteile mit Grenzen, Start-/Iststufe, Punkten, nächster Schwelle und Offenstatus;
- `GET /api/land/:id` → Beitragsquellen, letzte Ereignisse, eigene Tageswirkung, Spendenumrechnung/Tagesrest und die im Land möglichen aktiven Monster;
- `POST /api/land/:id/donate` mit `expected_world_id`, `request_id`, `revision` und `{resources:{food,lumber,stone,gold}}` → atomarer Beleg oder unveränderte Wiederholung. Die Bedeutung von Kristallen bleibt offen; sie werden nicht angenommen;
- Admin: `land-rules-save` speichert validierte, revisionierte Weltregeln. Eine allgemeine manuelle Zonenöffnung und die frei pflegbare Monster-Spawnregel-Registry wurden noch nicht gebaut.

`src/Api/Handlers/GameHandler.php` liefert `map_size`, den aktuellen Landteil, Zonenfortschritt und dynamisch aus der 8×8-Geometrie berechnete Zonenrechtecke kompakt mit dem Spielzustand. `MapHandler` verwendet inzwischen die aktive `WorldContext`-Welt, deren dynamische Kartengröße und die zentrale Access Policy.

## Integrationspunkte zu A01/A02 und E01–E42

E16 wurde auf den stabilen A01/A02-Verträgen aufgebaut und ist kein Unterpunkt von E15: Regionalentwicklung funktioniert weltöffentlich auch ohne Allianzgebiet.

- **A01:** `RewardCatalog` und der Reward-Editor bleiben die einzige Beutequelle. Falls Entwicklungskristalle gewählt werden, sind sie ein weltgebundener Rewardtyp `world_currency` statt eines accountweiten Itemcodes; die Spawnregel referenziert nur die Belohnungsrevision. E16 führt keine zweite Droptabelle ein.
- **A02:** Beim endgültigen Monsterkill entstehen in derselben Transaktion genau ein Fortschrittsereignis und genau ein Charm am gespeicherten Ort. Rally-Bosse brauchen denselben Hook. Der Charm ist kein Entwicklungsbeitrag; `UNIQUE(world_id, source_monster_id)` verhindert doppelte Charms.
- **E01/E02/E07:** Bestiarium, Fundortsuche und weltweite Monstersuche zeigen `benötigte Landstufe`, Zone und aktuellen Freigabestatus. Sie dürfen in geschlossenen Zonen kein angreifbares Ziel anbieten.
- **E13–E15:** Marker speichern `land_part_id`; Wachturm/Erkundung respektieren Zonen. Allianzgebiet besitzt oder fördert Landteile später, ändert aber nicht deren weltweiten Iststand und setzt ihn bei Besitzerwechsel nicht zurück.
- **E33:** Killbelege und Regelrevisionen sind eine Grundlage. Eine vollständige Beute- und Wirtschaftsstatistik muss reale Auszahlungen, Quellen und Verbrauch aggregieren; die heutige Erwartungswert-Vorschau ersetzt sie nicht.
- **E34:** Ein späterer Inhaltseditor kann revisionierte Schwellen, Gates und Spawnregeln mit Probeauswertung pflegen.
- **E37/E38/E40/E41:** Abwehrwellen, Prüfungspfad und Jagdserien zählen nur dann als Regionalquelle, wenn eine explizite Ereignisregel das bestimmt. Ereignismarken und Entwicklungskristalle bleiben unterschiedliche Währungen.

## Schonende Einführung in laufende Welten

Die additive Einführung ist erfolgt:

1. Die Migrationen `0083` bis `0087` ergänzten Regeln, Belege und nullable Snapshotspalten ohne Gameplay-Reset.
2. Welt 1 erhielt deterministisch 1.024 Landteile und behielt als Bestandswelt alle drei Zonen mit Grund `legacy` offen. Neue Welten öffnen nur `outer`.
3. Es wurde keine Historie aus `kill_count` oder alten Berichten erfunden, weil sie keinem Landteil zuverlässig zugeordnet werden kann.
4. Vorhandene Monster, Ressourcen, Charms, Städte, Märsche, Rallys und Schreine wurden nicht verschoben oder gelöscht. Neue Spawns erhalten Regional-Snapshots; vorhandene Ziele behalten ihre bisherigen Werte.
5. Die Laufzeitauswahl berücksichtigt nur aktive Katalogdefinitionen und schließt Deathkar, Magdar sowie grünen, goldenen und roten Drachen aus. Eine vollständige persistierte Allowlist-/Template-Registry bleibt ein späterer Ausbau.
6. Land- und Kartenansichten, Beitrags-Hooks, Spenden sowie die zentrale Zielsperre sind aktiv. Eine spätere Änderung der Zonen einer Bestandswelt darf weiterhin keine bestehende Stadt oder aktive Zielkette einschließen.

## Prüfplan

- PHP- und JS-Geometrie liefern für deutlich verschiedene `map_size`-Werte einschließlich 64, 255, 256, 320 und 1.024 an jeder gültigen Koordinate denselben Landteil, dieselbe Zone und Startstufe; beschnittene Randlandteile sowie Wasserfälle sind abgedeckt.
- Eine Welt ohne trockenen, erreichbaren Stufe-9-Kern oder mit `eligible = 0` in einer Gate-Zone wird mit einer konkreten Geometrie-/Terrain-Diagnose abgelehnt und niemals automatisch geöffnet.
- Jeder Landteil kann unabhängig von Entfernung Stufe 9 erreichen; gesperrte Landteile sammeln vorher keine Punkte.
- Gates zählen nur trockene, geöffnete Landteile der verlangten Vorzone; Entwicklungsziel, Fallback, `not_before` und konkurrierende Cron-Aufrufe öffnen genau einmal.
- Jeder Dispatch-, Rally-, Teleport-, Platzierungs- und Spawnweg lehnt Ziele in gesperrtem Gebiet serverseitig ab; Außen→Außen-Transit bleibt im MVP möglich.
- Wiederholung jedes Ereignisschlüssels verändert weder Punkte noch Ressourcen; Teiltreffer geben keine Landpunkte; Solo- und Rallykill schreiben denselben Monsterwert genau einmal gut.
- Sammelrückruf schreibt nur die wirklich entzogene Menge gut. Spenden-Retry liefert denselben Beleg und belastet nur einmal.
- Monster mit Stufen 0, 1, 10 und >10 werden über Spawnregeln korrekt behandelt; Landlevel wird nie als Monster- oder Rohstofflevel ausgegeben.
- Migrationsfixture mit laufendem Marsch, Rally, besetztem Feld und Charm behält IDs, Koordinaten, HP, Vorrat, Ablaufzeiten und Status bytegenau; nur neue Spawns reagieren auf spätere Landstufen.
- Kartenansicht nach `docs/UI_STYLE_GUIDE.md` auf Desktop, Handy und Querformat prüfen; echte Karte und eingebettete `/city#city` dürfen HUD/Navigation nicht doppeln.

## Entscheidung und gesetzte Startannahmen

**Einzige blockierende Produktentscheidung:** Bedeutet „Kristalle“ eine neue erspielbare, weltgebundene Währung `development_crystal` (**empfohlen**) oder die bestehende Premiumwährung `players.gems`? Schema und Rewardtyp hängen davon ab.

Für die erste Implementierung gelten ansonsten die oben prüfbaren Standardvorschläge: Entwicklungsziel plus optionale 14-Tage-Mindestzeit, kein aktiver Zeit-Fallback, dynamische 8×8-Geometrie, direkte Spenden an jeden geöffneten Landteil, Kill-only-Landpunkte, dauerhafter Fortschritt und reine Zielsperren bei abstraktem Transit. Diese Werte bleiben über Weltregeln konfigurierbar und werden durch die Abnahmefälle geprüft; sie erfordern keine weitere Vorabentscheidung. Die Monster-Allowlist entsteht durch Code-/Konfigurationsaudit unter Ausschluss der fünf bestätigten inaktiven Familien.
