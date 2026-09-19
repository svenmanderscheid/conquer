# Loot, Karten-Charms und anschließende Ausbauwellen

Stand: 12. September 2026. Dieses Dokument ist ein Umsetzungs- und Orchestrierungsplan. Es ändert keine Spielregel und keine laufenden Daten. Es ergänzt den aktuellen Arbeitsstand; insbesondere sind `RewardCatalog`, `/admin/rewards`, Migration 0083 und die Solo-Itemziehung bereits vorhanden und werden nicht neu erfunden.

## Verbindlicher Auftrag

- A01: Ein Administrator kann die wirksame Monsterbeute mit gültigen Gegenständen, Mengen, Chancen, Vorschau, Versionen und Weltabweichungen verwalten. Vorschau, Kartenanzeige und Auszahlung lesen dieselbe Regel.
- A02: Jedes tatsächlich getötete aktive Weltmonster erzeugt genau einen Karten-Charm an seinem persistent gespeicherten Spawnort. Truppen müssen zum Einsammeln entsandt werden. Instanzgegner erhalten später einen getrennten Anchor-Adapter und einen eigenen Vollständigkeitsstatus.
- Die akzeptierten Erweiterungen E01–E42 werden in abhängigen, abnehmbaren Wellen umgesetzt.
- Geografische Landteile besitzen je Welt einen eigenen Entwicklungsstand von 1 bis 9. Jedes Landteil kann Stufe 9 erreichen. Die Entfernung zum Zentrum beeinflusst nur den Startwert, niemals eine Obergrenze.
- Die Karte wird in drei Zonen freigeschaltet: `outer`, `middle`, `center`. Alte Ziele werden bei einer Freigabe oder Regeländerung nicht verschoben.
- Loot- und Charm-Regeln verwenden den beim Ziel gespeicherten Landteilstand und Freigabekontext. Laufende Begegnungen behalten ihren Snapshot.
- Deathkar, Magdar sowie grüner, roter und goldener Drache sind inaktive Vorlagen. Ihre Datenpräsenz darf nicht als aktive Spielverfügbarkeit dargestellt werden.
- Für delegierte Umsetzung dürfen nur SOL oder kleinere Modelle verwendet werden; Astra ist ausgeschlossen.

## Tatsächlicher Ausgangspunkt

### Bereits vorhanden

- `src/Game/Rewards/RewardCatalog.php` liest die ausgelieferten Kataloge und globale Datensätze aus `reward_overrides`. Es validiert Itemcodes und rollt unabhängige Itemchancen.
- `src/Admin/RewardEditor.php`, `views/admin/rewards.php` und `src/Admin/AdminService.php` bieten eine serverseitige Bearbeitung mit Superadmin-Prüfung, CSRF, Vorgangs-ID, Audit, globaler Sperre und optimistischer `revision`.
- Migration `0083_reward_overrides.sql` speichert genau einen veränderlichen Override je `source_type/source_key`. Der Scope ist global. Es gibt weder unveränderliche Versionen noch Weltabweichungen.
- `MarchTick::resolveMonster()` zieht für Solo-Siege inzwischen Items und Edelsteine aus der effektiven `MonsterData`-Definition. Der frühere separate Goblin-Pfad ist entfernt.
- `MonsterRally::start()` speichert Monsterdefinition und Drops in `rallies.result_json`; eine spätere Adminänderung verändert diese Rally nicht. Solo-Märsche besitzen diesen Beute-Snapshot noch nicht und bestimmen Beute erst bei Ankunft.
- `CharmSpawner` erzeugt bei Orc/Skeleton/Golem einen Charm und kann einen Admin-Override auswerten. Rally-Siege rufen ihn nicht auf. Ork-Späher, Goblins und Regionalbosse fallen ohne individuellen Override aus der Familienprüfung.
- `MarchDispatcher::dispatchCharm()`, `/api/march/dispatch-charm` und `MarchTick::resolveCharmCollect()` bilden bereits einen serverseitigen Sammelmarsch ab.
- Die alte `MapHandler`-Ansicht liefert Charms, verwendet dabei aber noch feste Welt-1-Abfragen. `/map` wird zur Haupt-App umgeleitet. Die Haupt-App liest `GameHandler`; dort fehlen Charms im State, und `assets/js/world-map.js` kennt kein Charm-Ziel. Damit ist der aktuelle Sammelweg für Spieler nicht vollständig erreichbar.
- `map_charms` ist weltbezogen und sperrt bei der Sammlung die Zeile. Ein Quell-Kill, Eigentümer, Exklusivzeitraum, Regelversion sowie gesnapshottete Bonus- und Laufzeitwerte fehlen.
- `player_charms_active` besitzt keinen `world_id`. Ein auf einer Welt eingesammelter Effekt kann deshalb in andere Welten ausstrahlen.
- `GameHandler` filtert Monster und Rohstofffelder nach Welt, filtert die dort eingebetteten Kampfberichte jedoch nicht nach Welt. Das ist beim Umbau der Beuteberichte mitzubeheben.

### Katalog und aktiver Bestand

Der aktuelle `data/monsters.json` enthält 99 Definitionen und 14 Namen. Die frühere Zahl 89 ist durch die zehn Grumwald-Definitionen überholt.

Die beabsichtigte aktive Auswahl umfasst 77 Rohdefinitionen. Die vier aktiven Regionalbosse heißen im aktuellen Code eindeutig **Grumwald, Frostgrimm, Sandmaul und Glutramm**; eine aktive Vorlage namens Berserker wurde im Katalog nicht gefunden:

- Ork-Späher: 1
- Orc: 11 historische Definitionen beziehungsweise Codes
- Skeleton: 10
- Golem: 10
- Treasure Goblin: 5
- Grumwald, Frostgrimm, Sandmaul und Glutramm: je 10

Die 22 Definitionen für Deathkar, Magdar und die drei Drachenfarben bleiben als `inactive_template` im Katalog. Deathkar ist gegenwärtig zusätzlich ein interner Auswahlname: `WorldSpawnService::regionalMonsterCode()` wandelt Codes 20200501–20200510 anhand des Bioms in einen der vier Regionalbosse um. Dieser technische Platzhalter darf weder Bestiarium noch Admin als aktiven Deathkar ausgeben. Die Standard-Spawngewichte enthalten weiterhin `dragon` und `Magdar`; auch ein vorhandener alter Karten-Datensatz darf den Produktstatus nicht auf „aktiv“ drehen. Vor der Aktivierung der neuen Registry werden solche Altziele als Bestand erfasst, nicht umbenannt oder verschoben, und nach Ende laufender Märsche/Rallys regulär auslaufen gelassen.

Der aktuelle Katalog enthält 595 alte Monster-Dropslots. Davon zeigen 505 Slots auf 37 verschiedene Codes, die im heutigen Inventar mit 166 Items fehlen. Diese Zeilen dürfen nicht automatisch aktiviert und nicht still ersetzt werden. Die Admin-Seite braucht eine explizite Migrationsliste mit den Zuständen `unmapped`, `mapped`, `disabled_with_reason`.

### Historische Identitätsabweichung

`MonsterData` überschreibt Definitionen anhand von Name und Stufe aus `world_spawn.json`. Besonders Orc ist historisch verschoben: Spawncode 20200101 wird zur Definition Orc Stufe 1 aufgelöst, obwohl derselbe Code in `monsters.json` die Katalogstufe 0 trägt. Die bestehende Admin-Seite listet Rohcodes aus `monsters.json`; Laufzeit und Editor können deshalb bei derselben Zahl verschiedene Stufen meinen. Rohcodes dürfen nach der Migration nur noch Aliase sein.

## Vor paralleler Umsetzung einzufrierende Verträge

Diese Verträge werden zuerst als PHP-Wertobjekte, JSON-Schemas und Migrationen festgelegt. Erst danach dürfen Admin, Karte und Kampfadapter parallel arbeiten.

### 1. Monsteridentität

Jede Laufzeitdefinition liefert mindestens:

```json
{
  "template_key": "regional.frostgrimm",
  "family_key": "regional_boss",
  "legacy_code": 20202104,
  "display_name": "Frostgrimm",
  "encounter_kind": "field_monster",
  "attack_mode": "rally",
  "availability": "active",
  "land_part_id": 17,
  "zone_key": "middle",
  "regional_level_at_spawn": 4,
  "effective_monster_level": 4
}
```

`template_key` ist der fachliche Primärschlüssel. `legacy_code` bleibt lesbar und importierbar, entscheidet aber weder Verfügbarkeit noch Stufe. Eine einzige Alias-Tabelle löst jeden bekannten Altcode in Template und Altstufe auf. Unbekannte Codes erzeugen einen sichtbaren Betriebsfehler und keine generische, belohnbare Ersatzdefinition.

Die aktive Registry enthält ein ausdrückliches `availability`. Admin, Spawnworker, Bestiarium, Suche und Kampfstart verwenden dieselbe Registry. Inaktive Vorlagen sind im Admin unter „Inaktive Vorlagen“ sichtbar, erscheinen Spielern aber erst nach einer bewusst veröffentlichten Aktivierung.

### 2. Landteil- und Zonen-Snapshot

Die Geometrie-Implementierung des Landentwicklungsmoduls ist die einzige Quelle für `land_part_id` und `zone_key`. Lootcode berechnet keine eigenen Grenzen und definiert keinen zweiten Landdienst. Ein Landteil umfasst 8×8 Kartenfelder. Auf der aktuellen 256er-Karte gilt `parcel_x = intdiv(x, 8)` und `parcel_y = intdiv(y, 8)`; die fachliche Identität ist `(world_id, parcel_x, parcel_y, geometry_version)`, ergänzt durch eine interne `land_part_id`. Die Lösung muss trotzdem die tatsächliche `map_size` einer Welt verwenden. `zone_key` entsteht serverseitig aus einem versionierten Chebyshev-Ring um die Kartenmitte (`outer`, `middle`, `center`). Benötigte Schnittstellen aus der Landplanung:

```php
LandGeometry::at(int $worldId, int $x, int $y): LandGeometryResult
LandProgressService::snapshot(int $worldId, int $landPartId): LandProgressSnapshot
LandUnlockService::zoneState(int $worldId, string $zoneKey): ZoneState
LandAccessPolicy::assertTargetOpen(int $worldId, int $x, int $y): void
```

Der daraus zusammengesetzte Kontext enthält `world_id`, `geometry_version`, `land_part_id`, `parcel_x`, `parcel_y`, `zone_key`, `regional_level` und `progression_rule_version`. Ein optionaler `biome_key` beschreibt nur die Darstellung beziehungsweise Familienzulassung und darf nicht mit Landteil oder Zone verwechselt werden.

Jedes persistente Kartenmonster speichert beim Spawn `land_part_id`, `zone_key_at_spawn`, `regional_level_at_spawn`, `spawn_rule_revision` und `effective_monster_level`. Alle Landteile haben Level 1–9 und Maximallevel 9. Distanz/Zonenlage wird ausschließlich beim erstmaligen Anlegen eines Landteils in `initial_level` umgesetzt: außen beginnt die Entwicklung auf 1, nach innen höher, der kleine Kern auf 9. Danach gelten für alle Landteile dieselben Schwellen und kein Distanz-Cap.

Landstufe und Monsterstufe sind verschiedene Werte. Die konkrete Zuordnung liefert eine versionierte `MonsterScalingPolicy::effectiveLevel(templateKey, regionalLevel, zoneKey, spawnContext)`. Der Rückgabewert muss im vorhandenen Stufenbereich der aktiven Vorlage liegen. Adminvorschau, Spawnworker, Kampf-Read-Model und Lootresolver rufen dieselbe Funktion auf. `zone_key` bleibt daneben ein Pflichtfeld für Spawnfreigabe und eventuelle zonenspezifische Modifikatoren. Alte Level-10-Ziele behalten ihren gespeicherten Legacywert bis Sieg oder Ablauf und werden nicht verschoben.

Die neuen Snapshotspalten sind für Altziele zunächst nullable. Sie werden beim ersten sicheren Lesen anhand von Welt, Koordinate und Altdefinition einmalig vervollständigt, ohne das Ziel zu verschieben, zu ersetzen oder nachträglich stärker zu machen. Bereits bespielte Livewelten starten aus Kompatibilitätsgründen mit allen drei Zonen offen; neue Welten durchlaufen die Freigaben.

Die Gateformel, Testwerte und Zeitoptionen werden ausschließlich in `docs/planning/LAND_PROGRESSION_AGENT.md` festgelegt. Loot speichert nur den gelieferten Zonenstatus und darf weder eigene Schwellen noch einen automatischen Zeit-Catch-up einführen. Gates liegen versioniert in Daten, werden beim ersten Erfüllen irreversibel als `unlocked_at` gespeichert und nicht nachträglich zurückgenommen.

### 3. Versionierte Lootpolicy

Die aktuelle veränderliche `reward_overrides`-Zeile wird durch unveränderliche Revisionen plus aktiven Zeiger ergänzt:

- `reward_policy_revisions`: `id`, `schema_version`, `source_kind`, `template_key`, `level_min`, `level_max`, `scope_world_id` (`0` = global), `config_json`, `status`, `created_by`, `reason`, `created_at`.
- `reward_policy_heads`: eindeutiger aktiver Zeiger je Scope/Quelle/Stufenbereich mit monotoner `revision`.
- Die vorhandenen Overrides werden einmalig als globale Revision importiert. `reward_overrides` bleibt während einer Kompatibilitätsphase lesbar und wird danach nur archiviert.
- Auflösungsreihenfolge: Welt + Template + Stufe, global + Template + Stufe, Welt + Familie + Stufe, global + Familie + Stufe, ausgelieferter Default. Die aufgelöste Antwort nennt `policy_revision_id` und `resolution_trace`.
- Eine Policy kann `draft`, `published` oder `retired` sein. Nur `published` ist zur Laufzeit sichtbar. Veröffentlichen erfordert `expected_head_revision`, eine Begründung und die vorhandene Superadmin-/Audit-/Idempotenzkette.

Normalisierte Policy Version 1:

```json
{
  "schema_version": 1,
  "resources": {"food": 0, "lumber": 0, "stone": 0, "gold": 0},
  "gems": {"mode": "independent", "chance": 0.0, "min": 0, "max": 0},
  "item_groups": [
    {"group_id": "guaranteed", "mode": "guaranteed", "draws": 1, "entries": []},
    {"group_id": "independent", "mode": "independent", "draws": 1, "entries": []},
    {"group_id": "weighted", "mode": "weighted_pool", "draws": 1, "entries": []}
  ],
  "charm": {
    "count": 1,
    "category_weights": {},
    "grade_weights": {},
    "map_ttl_seconds": 3600,
    "exclusive_seconds": 600
  }
}
```

Ein Itemeintrag besitzt `item_code`, `min_count`, `max_count` und je nach Modus `chance` oder `weight`. `charm.count` ist serverseitig unveränderlich 1 und wird im Admin als Regel angezeigt, nicht als abschaltbares Zahlenfeld. Kategorien und Grade müssen jeweils eine positive, normalisierbare Summe besitzen. Veröffentlichung scheitert, sobald ein Itemcode, Charmcode oder Regelbereich ungültig ist.

Alle Zahlen werden serverseitig ohne implizite Vorzeichen- oder Stringumwandlung geprüft. Mengen und Gewichte sind ganze Zahlen `>= 0`, auszahlbare `min_count`-Werte mindestens 1, `max_count >= min_count`, Chancen endlich und im Bereich 0–100 Prozent, Lebensdauern positiv und jede Summe gegen Überlauf begrenzt. Negative Werte, `NaN`, Exponentialtexte außerhalb des erlaubten Formats, unbekannte Felder und doppelte `group_id`-/Itemkombinationen werden abgewiesen. Die Grenze von höchstens 80 sichtbaren Dropzeilen pro Quelle kann aus dem bestehenden Editor übernommen werden.

### 4. Gemeinsamer transaktionaler Kill-Resolver

Solo und Rally dürfen keine eigenen Würfelpfade mehr besitzen. Ein Dienst erhält einen vollständigen Snapshot und schreibt genau ein Settlement:

```php
MonsterLootResolver::settleKill(
    Connection $db,
    MonsterKillContext $kill,
    list<RewardRecipient> $recipients
): MonsterKillSettlement
```

`MonsterKillContext` enthält `world_id`, `source_kind`, `source_id`, `source_slot`, `template_key`, `legacy_code`, gespeicherte Koordinaten, `land_part_id`, `zone_key`, `regional_level_at_spawn`, `effective_monster_level`, `policy_revision_id` und den Start-Snapshot. Empfänger enthalten Spieler, Stadt, Anteil und Allianz zum Kampfzeitpunkt.

`monster_kill_settlements` hat einen eindeutigen Schlüssel aus `(world_id, source_kind, source_id, source_slot)`. Das Settlement speichert die ausgewürfelte Gesamtbeute, Teilnehmeranteile und genau eine `charm_spec`. `map_charms.source_settlement_id` ist eindeutig. Wiederholte Worker, doppelte HTTP-Aufrufe und konkurrierende Siege lesen dasselbe Settlement und erzeugen weder Beute noch Charm erneut.

Der Resolver läuft innerhalb derselben Transaktion, die das gesperrte Ziel als getötet markiert. Koordinaten stammen ausschließlich aus der gesperrten Zielzeile beziehungsweise dem Instanz-Snapshot, nie aus dem Request. Der eigentliche Kontozuwachs darf weiterhin bei Marschrückkehr erfolgen; dafür werden unveränderliche Empfängeransprüche mit eindeutiger Auszahlungs-ID gespeichert.

### Vollständiger Lebenszyklus eines Weltmonsters

1. Der Spawnworker prüft aktive Registry, offene Zone und freie Platzierung. Er speichert Identität, Landteil, Zone, regionale Stufe, effektive Monsterstufe und Spawnregelrevision zusammen mit den Kampfwerten.
2. Solo- oder Rally-Start sperrt das konkrete Ziel, prüft Welt/Zone/Modus und speichert den Encounter- und Lootpolicy-Snapshot. Direkte Requests können keinen inaktiven Templatecode oder gesperrten Kartenbereich erzwingen.
3. Ankunft sperrt Ziel und Auftrag. Nur der atomar bestätigte Todesübergang ruft `settleKill()` auf. Schaden ohne Tod erzeugt weder Loot noch Charm.
4. Das Settlement würfelt einmal, teilt Rallybeute nach der festgeschriebenen Beitragsregel, legt genau einen Charm an und schreibt Berichts-/Fortschrittsbelege. Eine Rally erhält nie einen Charm pro Teilnehmer.
5. Direkte Beute bleibt im unveränderlichen Rückkehranspruch und wird beim Heimkommen genau einmal gutgeschrieben. Der Charm bleibt als eigenes Weltziel liegen, unabhängig davon, ob alle Angreifer gefallen sind.
6. `WorldPlacement` behandelt einen sammelbaren Charm als belegtes Feld. Kein neuer Spawn, Teleport oder Stadtplatz darf ihn bis Sammlung/Ablauf überdecken.
7. Die Haupt-App liefert und zeichnet das Ziel. Der Sammelstart prüft Eigentum, Server-ETA und Welt; die Ankunft entscheidet das Rennen global und aktiviert den gesnapshotteten Effekt.
8. Sammlung oder Ablauf setzt einen terminalen Status. Ein begrenzter Cleanup entfernt alte Darstellungsdaten erst, nachdem Märsche, Settlement, Audit und Statistik sie nicht mehr als aktive Ziele benötigen.

### 5. Begegnungs-Snapshot

Jeder neu gestartete Solo-Marsch, jede Rally und jede Instanz speichert dieselbe Struktur `encounter_snapshot_json`:

- Identität, Kampfwerte und effektives Monsterlevel.
- Landteil, Zone und deren Stand beim Start.
- `policy_revision_id` und normalisierte Policy.
- Teilnehmer-/Eigentumskontext.
- Persistenter Spawnort.

Neue Solo-Märsche dürfen ohne Snapshot nicht starten. Bereits laufende Solo-Märsche werden beim ersten Abschluss einmalig aus dem damaligen Ziel und der aktuellen Legacyregel aufgelöst und als `legacy_snapshot` protokolliert. Eine Adminänderung, ein Landteil-Level-up oder eine Zonenfreigabe verändert nie einen laufenden Kampf oder ein bereits gespawntes Ziel.

### 6. Charm-Eigentum, Verfall und Wirkung

`map_charms` wird um `source_settlement_id`, `owner_player_id`, `owner_alliance_id`, `exclusive_until`, `bonus_pct`, `effect_duration_seconds`, `policy_revision_id` und `status` ergänzt.

Empfohlener Startwert, ausdrücklich noch keine Nutzerfestlegung:

- Solo: zehn Minuten exklusiv für den Sieger, danach öffentlich.
- Rally: zehn Minuten exklusiv für die Allianz der siegreichen Rally, danach öffentlich. Es entsteht insgesamt ein Charm.
- Kartenlebensdauer: 60 Minuten ab Kill, serverzeitbasiert.
- Ein Versand wird abgelehnt, wenn die berechnete Ankunft nicht vor `expires_at` liegt oder die Armee im Exklusivzeitraum nicht berechtigt ist.
- Es gibt keine Reservierung beim Versand. Unter mehreren berechtigten Märschen gewinnt die kleinste `(arrival_time, march_id)`-Kombination.

Die heutige spielerweise Lazy-Verarbeitung kann einen späteren Marsch vor einem früheren Marsch eines anderen Spielers bearbeiten. `CharmCollectionService::settleDue(charmId, now)` muss deshalb unter Zeilensperre alle fälligen Kandidaten global ordnen und den Gewinner bestimmen. Verlierer sowie Märsche zu einem abgelaufenen Charm kehren mit allen reservierten Truppen und einem eindeutigen Ergebniscode zurück.

Bonus und Effektdauer werden aus `map_charms` übernommen, nicht nachträglich aus `CharmSpawner` rekonstruiert. Aktive Effekte werden weltbezogen gespeichert. Vor der Implementierung ist noch eine Produktregel festzuschreiben: Umgang mit einem schwächeren Charm derselben Kategorie. Empfehlung: Einsammeln darf den laufenden Bonus nie verringern; gleich starke Funde verlängern bis zu einem konfigurierten Cap, stärkere ersetzen den Wert und starten ihre eigene Dauer.

### 7. Instanzgegner ohne Koordinaten

Feldzugs- und Dungeongegner haben heute keinen individuellen, dauerhaft adressierbaren Weltkartenort. Ein Stadt-Ersatzpunkt erfüllt „am exakten Spawnort“ nicht. Vor ihrer A02-Anbindung erhält jede Instanz einen persistenten `encounter_anchor` mit Welt, Landteil, Zone und Koordinaten. Jeder tötbare Begegnungsslot referenziert diesen Ort beziehungsweise einen gespeicherten Unterpunkt. Die Karte muss mehrere Charms am gleichen Anchor als Stapel darstellen können.

Bis ein Instanztyp diesen Vertrag erfüllt, darf er nicht fälschlich als vollständig A02-konform markiert werden. Räume ohne Gegner, etwa eine reine Schatzkammer, erzeugen keinen Monster-Kill und damit keinen Charm. Der Abschluss eines ganzen Dungeons erzeugt keinen zusätzlichen vierten oder fünften Charm neben den tatsächlichen Kills.

## HTTP- und Read-Model-Verträge

### Haupt-App

`GET /api/game/state` ergänzt `charms` und `land_progression`. Ein Charm-DTO enthält:

```json
{
  "id": 812,
  "x": 123,
  "y": 88,
  "charm_code": 10700011,
  "stat_category": "troops_attack",
  "grade": "epic",
  "bonus_pct": 5,
  "effect_duration_seconds": 7200,
  "expires_at": "...",
  "exclusive_until": "...",
  "claimable": true,
  "claim_reason": null,
  "source": {"template_key": "solo.orc", "effective_monster_level": 4},
  "land": {"land_part_id": 17, "zone_key": "middle", "level": 4}
}
```

Monster-DTOs nennen `template_key`, `availability`, `land`, `effective_monster_level`, `policy_revision_id` und eine vom gemeinsamen Resolver erzeugte `loot_preview`. Die UI leitet keine Stufe mehr aus `% 100` ab.

`POST /api/march/dispatch-charm` behält `charm_id`, `target_x`, `target_y` und `troops`, erhält zusätzlich eine eindeutige `request_id`. Ergebnis: `march_id`, `arrival_at`, `expires_at`, `collection_state`. Stabile Fehlercodes: `CHARM_GONE`, `CHARM_RESERVED`, `CHARM_EXPIRES_BEFORE_ARRIVAL`, `ZONE_LOCKED`, `MARCH_SLOT_FULL`.

Die Hauptkarte zeichnet Charmziele, Stapel, Restzeit, Eigentumsstatus und Sammelaktion. `/city#city` darf dabei keine zweite Karten-/3D-Navigation erhalten. Der alte `MapHandler` wird entweder auf dieselben Query-Dienste umgestellt oder nach nachgewiesener Nichtnutzung entfernt; feste `world_id = 1`-Pfade bleiben nicht parallel bestehen.

### Admin und Vorschau

Der bestehende serverseitige Weg bleibt die Oberfläche. Fachlich benötigt er diese Operationen:

- `listSources(scopeWorldId, availability, family, level, context)` mit klarer Trennung aktiv/inaktiv und effektiver Aliasstufe.
- `resolvePolicy(scopeWorldId, templateKey, effectiveLevel, zoneKey)`; exakt dieselbe Methode nutzt die Laufzeit.
- `validateDraft(config)` ohne Schreiben.
- `preview(config, context, samples, seed)` ohne Spawn, Auszahlung oder Fortschrittsereignis. Antwort enthält garantierte Werte, Erwartungswerte, Min/Max, normalisierte Wahrscheinlichkeiten und simulierte Häufigkeiten.
- `publish(expectedHeadRevision, reason, requestId)` und `retire(...)` mit unveränderlicher Revision.
- `migrationIssues()` für alle ungültigen Altzuordnungen.

Der Admin-Kontext bietet `global` oder eine konkrete Welt, dazu Landteil-Level 1–9 und Zone. Die Vorschau, das Spieler-Read-Model und `MonsterLootResolver` müssen denselben `ResolvedLootPolicy` inklusive `policy_revision_id` liefern. Moderatoren lesen und simulieren, Superadmins veröffentlichen.

Pflichtfelder der Monsteransicht sind: fachlicher Templateschlüssel, Legacycodes/Aliase, Verfügbarkeitsstatus, Angriffstyp, Familie, wirksame Stufe, Scope, geerbte Regelquelle, aktive Revision und betroffene Welten. Im Editor folgen Ressourcen, Edelsteinregel, garantierte/ unabhängige/gewichtete Itemgruppen, Mengenbereiche, Itempicker aus dem gültigen Katalog, feste Charmanzahl 1, Kategorie-/Gradverteilung, Kartenlebensdauer und Exklusivzeit. Speichern und Veröffentlichen zeigen eine Vorher-/Nachher-Differenz; Reset erzeugt eine neue Revision, löscht keine Historie.

## Erster vertikaler Schnitt

Der erste sichere Schnitt wird hinter einem weltbezogenen Feature-Flag nur in einer isolierten Testwelt aktiviert:

1. Canonical Registry und Aliasauflösung für Ork-Späher und Orc.
2. Die effektive Monsterstufe wird über die Canonical Registry statt über `% 100` aufgelöst. Bis E16 liefert der Kontext `land_part_id = null`, `zone_key = legacy_open`; der Vertrag ist bereits auf den späteren Land-Snapshot vorbereitet.
3. Eine globale, versionierte Orc-Policy plus eine Weltabweichung; bestehender Override wird importiert.
4. Gemeinsamer Resolver für einen Solo-Kill, unveränderlicher Marsch-Snapshot und genau ein Settlement.
5. Genau ein besitzgebundener Charm an den aus der gesperrten Monsterzeile gelesenen Koordinaten.
6. `GameHandler` liefert den Charm; `world-map.js` zeigt ihn; ein Truppensammelmarsch aktiviert den weltbezogenen Effekt.
7. Admin zeigt dieselbe Policy und eine schreibfreie Vorschau. Eine konkurrierende alte Formularrevision wird abgelehnt.

Abnahme: zwei gleichzeitige Kill-Worker erzeugen ein Settlement/einen Charm; zwei Sammler liefern genau einen Gewinner; Weltabweichung, Snapshot nach Adminänderung, Verfall, Rückruf und vollständige Truppenrückgabe sind nachgewiesen. Erst danach werden Skeleton, Golem, Goblin und die vier aktiven Regionalbosse zugeschaltet. Inaktive Vorlagen bleiben inaktiv.

## Einordnung in den Gesamtplan

Die Reihenfolge aller E01–E42 steht ausschließlich in `docs/planning/IMPLEMENTATION_PLAN.md` und die Paketmatrix in `docs/planning/ROADMAP_AGENT.md`. Dieses Dokument setzt keine konkurrierende Roadmap.

Für Loot gelten vier fachliche Integrationspunkte: Zuerst wird in der ersten Umsetzungswelle der Weltmonsterkreislauf A01/A02 samt E33 abgeschlossen. Danach liefert E16 die Landteil-, Zonen- und Scaling-Snapshots für neue Spawns; vorhandene Ziele behalten ihre alten Snapshots. E01/E02/E03/E06/E07 konsumieren anschließend die stabilen Read Models. E18 führt später persistente Instanzanker ein; erst dann wird der gesonderte Status „Instanzgegner mit Karten-Charm“ abgeschlossen. Dieser Adapter blockiert weder den ersten vertikalen Schnitt noch die vollständige A02-Abnahme für Weltmonster.

## Dateieigentum bei späterer paralleler Umsetzung

Ein Integrationsverantwortlicher besitzt exklusiv `migrations/run.php`, `index.php`, `views/game.php`, `assets/js/game.js`, gemeinsame i18n-Dateien und die Vertragsklassen. Agenten liefern für diese Dateien kleine Integrationshinweise statt eigener paralleler Edits.

| Bereich | Exklusive bestehende Dateien | Bevorzugte neue Dateien |
|---|---|---|
| Verträge/Migration | `MonsterData.php`, `RewardCatalog.php`, `migrations/run.php` | `MonsterRegistry.php`, `MonsterIdentity.php`, `ResolvedLootPolicy.php`, nummerierte Migrationen |
| Kill/Settlement | `MarchTick.php`, `MonsterRally.php` | `MonsterLootResolver.php`, `MonsterKillSettlement.php`, Integrationstests |
| Charm/Sammeln | `CharmSpawner.php`, `MarchDispatcher.php`, `MarchHandler.php` | `CharmCollectionService.php`, Charm-DTO/Query-Service |
| Admin | `RewardEditor.php`, `AdminService.php`, `AdminController.php`, `views/admin/rewards.php`, Admin-JS/CSS | Policy-/Preview-Service und Admin-Tests |
| Karte | `GameHandler.php`, `world-map.js`, `march-panel.js` | gemeinsamer Map-Entity-Query-Service und Browsertests |
| Landentwicklung | `WorldSpawnService.php`, `WorldTerrain.php`, `WorldSettings.php` | `LandGeometry.php`, `LandProgressService.php`, `LandUnlockService.php`, `LandAccessPolicy.php` |
| Instanzen | `DungeonService.php`, `ExpeditionService.php` | `EncounterAnchorService.php`, Encounter-Adapter |

Nach Abschluss des Weltmonsterkreislaufs werden die E-Pakete nach neuen Services, Handlern, Panels, Styles und Tests geschnitten. Gemeinsame Navigation/Routen werden einmal pro Integrationsfenster gebündelt. Kein Agent bearbeitet gleichzeitig einen Kampfprozessor oder eine zentrale Bootstrapdatei.

## Migration und Rollout

1. Nur additive Tabellen/Spalten; Backfill in begrenzten Batches. Neue Leser akzeptieren alte Zeilen, neue Schreiber erzeugen vollständige Snapshots.
2. Registry-Audit erzeugt eine maschinenlesbare Liste aller Rohcodes, Aliasziele, aktiven/inaktiven Vorlagen und ungültigen Items. Veröffentlichung bleibt gesperrt, solange ein aktiver Pfad `unmapped` ist.
3. Aktuelle `reward_overrides` werden als globale Revisionen importiert und bytegenau gegengeprüft. Erst danach schaltet das Read-Feature-Flag auf die neue Auflösung.
4. Inaktive Spawngewichte werden auf null gesetzt. Bereits vorhandene Ziele werden weder verschoben noch in Regionalbosse umgeschrieben. Ziele ohne laufende Aktion laufen regulär aus; Ziele mit Referenzen bleiben bis zu deren Ende geschützt.
5. Write-Shadow: alter und neuer Resolver lösen bei Testkämpfen dieselbe Policy und deren Erwartungswerte auf; eine gemeinsame Test-Seedfolge macht Proberolls vergleichbar. Nur der alte Pfad zahlt aus. Abweichungen werden protokolliert.
6. Testwelt: neuer Resolver zahlt aus, alter Pfad ist nur Vergleich. Danach weltweiser Rollout je Welt-ID.
7. Nach stabiler Beobachtung werden direkte Würfelpfade aus `MarchTick`, `MonsterRally` und `CharmSpawner` entfernt. Erst dann gilt A01/A02 als abgeschlossen.

## Prüfmatrix

### Regeln und Daten

- Jeder Legacycode löst eindeutig in Template und effektive Stufe auf; Orc-Aliase sind explizit getestet.
- Aktive und inaktive Vorlagen erscheinen im richtigen Kontext; kein Spawnworker kann Magdar, Deathkar oder Drachen durch Namensgewicht aktivieren.
- Alle aktiven Policies verwenden nur vorhandene Item-/Charmcodes. Die 37 ungültigen Codes bleiben als bearbeitbare Migrationsprobleme sichtbar.
- Landteile starten distanzabhängig, können alle Level 9 erreichen und werden nie durch ihre Zone gedeckelt.
- Gateerfüllung ist idempotent und irreversibel. Jagd, Sammlung und Spende erhöhen genau ein Landteil genau einmal pro Quellereignis.
- Lootvorschau und Settlement lösen für Welt, Template, Landteil-Level und Zone dieselbe `policy_revision_id` auf.

### Transaktionen und Rennen

- Zwei Worker für denselben Solo-Kill beziehungsweise dieselbe Rally erzeugen genau ein Settlement, einen Killfortschritt und einen Charm.
- Admin-Doppelklick wiederholt keine Veröffentlichung; eine alte `expected_head_revision` liefert Konflikt statt Überschreiben.
- Policywechsel und Landteil-Level-up während eines Marsches verändern dessen Snapshot nicht.
- Mehrere fällige Sammler werden global nach `arrival_time,id` entschieden, unabhängig davon, welcher Spieler den Lazy-Tick auslöst.
- Ablauf, Verlust des Exklusivrechts, Rückruf und bereits gesammeltes Ziel geben Truppen exakt einmal zurück.
- Auszahlungsansprüche sind bei wiederholter Rückkehrverarbeitung idempotent.

### Mehrwelt

- Policypriorität global/Welt ist isoliert; eine Weltabweichung ändert keine andere Welt.
- Kartenabfragen, Kampfberichte, Charmziele, aktive Charmeffekte, Landfortschritt, Gates und Märsche tragen dieselbe Welt-ID.
- Accountweite Items und Edelsteine werden bewusst als accountweit getestet; Stadtressourcen und aktive Charmwirkungen bleiben weltbezogen.
- Kein verbleibender aktiver Kartenpfad verwendet `world_id = 1`.

### UI und echte Ansicht

- Admin: aktive/inaktive Quellen, Aliasstufe, Welt-Scope, Landlevel 1–9, eigenständige Monsterstufe, Zone, Draft/Publish, Konflikt, Migrationsliste und schreibfreie Simulation.
- Karte: Charm sichtbar, Stapel am gleichen Ort, Eigentümer-/Allianzfenster, Restzeit, zu späte Ankunft, Sammelmarsch, Verlust eines Rennens.
- Bestiarium und Itemquellen zeigen nur veröffentlichte, für den gewählten Welt-/Landkontext wirksame Daten.
- Desktop, 390 px, 320 px und 568×320; zusätzlich echte Gesamt- und Nahansicht der Welt. `/city#city` bleibt ohne doppeltes 3D-HUD oder doppelte Navigation.
- Vor jeder sichtbaren Implementierung sind `docs/UI_STYLE_GUIDE.md` und bei Karten-/Figurenänderungen `docs/ART_DIRECTION.md` zu lesen. Neue Oberflächen verwenden die vorhandenen `--ui-*`-Variablen.

## Abschlusskriterien für A01/A02

A01 ist abgeschlossen, wenn Solo, Goblin, Rally, Adminvorschau, Kartenbeutevorschau und Berichte dieselbe veröffentlichte Policy verwenden, historische Snapshots stabil bleiben und keine ungültige ID veröffentlicht werden kann. Das spätere Bestiarium E01 und die Itemquellensuche E02 müssen denselben Read-Service konsumieren, sind aber keine Vorbedingung für den A01-Abschluss.

A02 für Weltmonster ist abgeschlossen, wenn jede aktive Solo- und Rallyfamilie genau ein Charm-Settlement am gespeicherten Feld erzeugt, die Haupt-App es anzeigt und ein realer Truppensammelmarsch es unter Mehrwelt-, Eigentums-, Ablauf- und Rennbedingungen exakt einmal einlöst. Datenzeilen für inaktive Vorlagen zählen nicht als aktive Monster. Instanzabdeckung ist ein späteres, separat sichtbares Subgate: Ein Dungeon- oder Feldzugstyp darf erst dann als charmfähig/grün erscheinen, wenn sein persistenter Anchor und der gesamte Sammelweg nachgewiesen sind; dieses Subgate blockiert den Weltmonsterabschluss nicht.
