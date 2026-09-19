**Conquer – vollständiger Monster- und Dropkatalog: Rechercheanhang**

**Einordnung nach deiner Durchsicht:** Die 89 Zeilen sind Katalogdefinitionen, keine Liste tatsächlich aktiver Weltmonster. Deathkar, Magdar sowie grüne, goldene und rote Drachen sind laut deiner Klarstellung noch nicht aktiv und dürfen durch die Dropumstellung nicht automatisch freigeschaltet werden. Die grundsätzliche Droptabelle ist angenommen; ungültige historische Item-IDs bleiben zu bereinigen. Der weitere Auftrag steht in [IMPLEMENTATION_PLAN.md](planning/IMPLEMENTATION_PLAN.md).

**Historischer Auszahlungsstand:** Die Spalten „Heutiger Beuteweg“ und „Karten-Charm“ beschreiben die ursprüngliche Prüfung. Inzwischen wurden `RewardCatalog`, die Admin-Seite `/admin/rewards`, gespeicherte Überschreibungen und Solo-Item-/Edelsteinziehungen ergänzt. Diese Tabelle bleibt als Quelldatenprüfung erhalten; sie ist keine aktuelle Funktionsabnahme. Der neue Plan baut auf diesen Ergänzungen auf und schließt insbesondere den garantierten Karten-Charm für alle Monster und dessen Abholung durch Truppen ein.

**Katalogfortschreibung:** Inzwischen enthält `monsters.json` 99 Definitionen unter 14 Namen einschließlich zehn neuer Grumwald-Stufen. Die nachfolgende 89er-Tabelle ist der historische Export; den aktuellen vollständigen Familienabgleich enthält [LOOT_CHARMS_AGENT.md](planning/LOOT_CHARMS_AGENT.md). Der Itemkatalog enthält weiterhin 166 Definitionen.

Stand: 12. September 2026. Automatisch aus den aktuellen JSON-Katalogen erfasst; die Zuordnung der Belohnungswege folgt dem gelesenen Quellcode. Keine Kämpfe ausgeführt und keine Spielerguthaben geändert. Dies ist keine bereits veröffentlichte neue Droptabelle.

**89 Weltmonsterdefinitionen**

Die Katalogstufe stammt direkt aus monsters.json. Die Spielstufe berücksichtigt die historischen Aliasregeln in MonsterData. Die Spalte „fehlende IDs“ betrifft die alte konfigurierte Tabelle; sie behauptet keine tatsächliche Auszahlung. Rallys nutzen stattdessen die drei gültigen separaten Beutepools. Der Karten-Charm ist derzeit nur bei den drei Standardfamilien im Backend angeschlossen; die neue Haupt-App bietet noch keinen Sammeldialog.

| Code | Name | Katalogstufe → Spielstufe | Modus | Heutiger Beuteweg | Karten-Charm | Fehlende IDs in alter Tabelle |
|---|---|---|---|---|---|---|
| 20209901 | Ork-Späher | 1 → 1 | solo | Grundressourcen; keine allgemeine Itemziehung | Nein | – |
| 20200101 | Orc | 0 → 1 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101013, 10103042, 10104024 |
| 20200102 | Orc | 1 → 2 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101014, 10103042, 10104024, 10601001 |
| 20200103 | Orc | 2 → 3 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101015, 10103042, 10104024, 10601001 |
| 20200104 | Orc | 3 → 4 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101016, 10103042, 10104024, 10601001, 10602002 |
| 20200105 | Orc | 4 → 5 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101017, 10103042, 10104024, 10601001, 10602002 |
| 20200106 | Orc | 5 → 6 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101017, 10103042, 10104024, 10601001, 10602002 |
| 20200107 | Orc | 6 → 7 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101018, 10103042, 10104024, 10601001, 10602002 |
| 20200108 | Orc | 7 → 8 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101018, 10103042, 10104024, 10601001, 10602002 |
| 20200109 | Orc | 8 → 9 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101019, 10103042, 10104024, 10601001, 10602002 |
| 20200110 | Orc | 9 → 10 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101019, 10103042, 10104024, 10601001, 10602002 |
| 20200111 | Orc | 10 → 10 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101020, 10103042, 10104024, 10601001, 10602002 |
| 20200201 | Skeleton | 1 → 1 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101014, 10103042, 10104024, 10601001 |
| 20200202 | Skeleton | 2 → 2 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101015, 10103042, 10104024, 10601001 |
| 20200203 | Skeleton | 3 → 3 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101016, 10103042, 10104024, 10601001, 10602002 |
| 20200204 | Skeleton | 4 → 4 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101017, 10103042, 10104024, 10601001, 10602002 |
| 20200205 | Skeleton | 5 → 5 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101017, 10103042, 10104024, 10601001, 10602002 |
| 20200206 | Skeleton | 6 → 6 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101018, 10103042, 10104024, 10601001, 10602002 |
| 20200207 | Skeleton | 7 → 7 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101018, 10103042, 10104024, 10601001, 10602002 |
| 20200208 | Skeleton | 8 → 8 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101019, 10103042, 10104024, 10601001, 10602002 |
| 20200209 | Skeleton | 9 → 9 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101019, 10103042, 10104024, 10601001, 10602002 |
| 20200210 | Skeleton | 10 → 10 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101020, 10103042, 10104024, 10601001, 10602002 |
| 20200301 | Golem | 1 → 1 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101025, 10103042, 10104024, 10601001 |
| 20200302 | Golem | 2 → 2 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101026, 10103042, 10104024, 10601001 |
| 20200303 | Golem | 3 → 3 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101027, 10103042, 10104024, 10601001, 10602002 |
| 20200304 | Golem | 4 → 4 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101028, 10103042, 10104024, 10601001, 10602002 |
| 20200305 | Golem | 5 → 5 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101029, 10103042, 10104024, 10601001, 10602002 |
| 20200306 | Golem | 6 → 6 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101029, 10103042, 10104024, 10601001, 10602002 |
| 20200307 | Golem | 7 → 7 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101030, 10103042, 10104024, 10601001, 10602002 |
| 20200308 | Golem | 8 → 8 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10101030, 10103042, 10104024, 10601001, 10602002 |
| 20200309 | Golem | 9 → 9 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10103042, 10104024, 10601001, 10602002 |
| 20200310 | Golem | 10 → 10 | solo | Grundressourcen; keine allgemeine Itemziehung | Ja | 10103042, 10104024, 10601001, 10602002 |
| 20200401 | Treasure Goblin | 1 → 1 | solo | Goblin-Sonderweg | Nein | 10101039, 10602002 |
| 20200402 | Treasure Goblin | 2 → 2 | solo | Goblin-Sonderweg | Nein | 10101040, 10602002, 10603023 |
| 20200403 | Treasure Goblin | 3 → 3 | solo | Goblin-Sonderweg | Nein | 10103014, 10103024, 10603023 |
| 20200404 | Treasure Goblin | 4 → 4 | solo | Goblin-Sonderweg | Nein | 10103014, 10103024, 10603023 |
| 20200405 | Treasure Goblin | 5 → 5 | solo | Goblin-Sonderweg | Nein | 10103015, 10103025, 10603023, 10604001 |
| 20200501 | Deathkar | 1 → 1 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20200502 | Deathkar | 2 → 2 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20200503 | Deathkar | 3 → 3 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200504 | Deathkar | 4 → 4 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200505 | Deathkar | 5 → 5 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200506 | Deathkar | 6 → 6 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200507 | Deathkar | 7 → 7 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200508 | Deathkar | 8 → 8 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200509 | Deathkar | 9 → 9 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200510 | Deathkar | 10 → 10 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20200601 | Green Dragon | 1 → 1 | rally | rally_rewards.json: dragon | Nein | 10101014, 10104022, 10603023 |
| 20200602 | Green Dragon | 2 → 2 | rally | rally_rewards.json: dragon | Nein | 10101015, 10104022, 10104023, 10603023 |
| 20200603 | Green Dragon | 3 → 3 | rally | rally_rewards.json: dragon | Nein | 10101016, 10104022, 10104023, 10603023 |
| 20200701 | Red Dragon | 1 → 1 | rally | rally_rewards.json: dragon | Nein | 10103034, 10103044, 10104022, 10603023 |
| 20200702 | Red Dragon | 2 → 2 | rally | rally_rewards.json: dragon | Nein | 10101008, 10103034, 10103044, 10104022, 10603023 |
| 20200703 | Red Dragon | 3 → 3 | rally | rally_rewards.json: dragon | Nein | 10101008, 10103034, 10103044, 10104022, 10104023, 10603023 |
| 20200801 | Gold Dragon | 1 → 1 | rally | rally_rewards.json: dragon | Nein | 10104022, 10603023 |
| 20200802 | Gold Dragon | 2 → 2 | rally | rally_rewards.json: dragon | Nein | 10104022, 10104023, 10603023 |
| 20200803 | Gold Dragon | 3 → 3 | rally | rally_rewards.json: dragon | Nein | 10104022, 10104023, 10603023, 10604001 |
| 20200901 | Magdar | 1 → 1 | rally | rally_rewards.json: magdar | Nein | 10104022, 10104023, 10104024, 10104100, 10104104, 10604001 |
| 20200902 | Magdar | 2 → 2 | rally | rally_rewards.json: magdar | Nein | 10104022, 10104023, 10104024, 10104100, 10104104, 10604001, 10605001 |
| 20200903 | Magdar | 3 → 3 | rally | rally_rewards.json: magdar | Nein | 10104022, 10104023, 10104024, 10104100, 10104104, 10604001, 10605001 |
| 20202101 | Frostgrimm | 1 → 1 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20202102 | Frostgrimm | 2 → 2 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20202103 | Frostgrimm | 3 → 3 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202104 | Frostgrimm | 4 → 4 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202105 | Frostgrimm | 5 → 5 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202106 | Frostgrimm | 6 → 6 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202107 | Frostgrimm | 7 → 7 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202108 | Frostgrimm | 8 → 8 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202109 | Frostgrimm | 9 → 9 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202110 | Frostgrimm | 10 → 10 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202201 | Sandmaul | 1 → 1 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20202202 | Sandmaul | 2 → 2 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20202203 | Sandmaul | 3 → 3 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202204 | Sandmaul | 4 → 4 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202205 | Sandmaul | 5 → 5 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202206 | Sandmaul | 6 → 6 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202207 | Sandmaul | 7 → 7 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202208 | Sandmaul | 8 → 8 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202209 | Sandmaul | 9 → 9 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202210 | Sandmaul | 10 → 10 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202301 | Glutramm | 1 → 1 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20202302 | Glutramm | 2 → 2 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022 |
| 20202303 | Glutramm | 3 → 3 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202304 | Glutramm | 4 → 4 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202305 | Glutramm | 5 → 5 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202306 | Glutramm | 6 → 6 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202307 | Glutramm | 7 → 7 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202308 | Glutramm | 8 → 8 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202309 | Glutramm | 9 → 9 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |
| 20202310 | Glutramm | 10 → 10 | rally | rally_rewards.json: deathkar | Nein | 10101007, 10101040, 10103033, 10103043, 10104022, 10604001 |

**Zusätzliche Begegnungskontexte**

Feldzugsbosse: Aschenfürst, Frostwächter und Dornenkönigin; jeweils normal, heroisch oder legendär. Sie stammen aus EncounterCatalog.php und haben persönliche Erfolgsbeute. Individuelle Weltkarten-Spawnpunkte für das Charm-Einsammeln fehlen bisher.

| Dungeon | Reguläre Begegnungen | Optionale Begegnung/Raum | Boss |
|---|---|---|---|
| Glutgewölbe | Aschewache, Feuerkoloss | Glutschatzhüter | Schmiedefürst |
| Frosthöhle | Eiswolf, Runenwächter | Kristallbrut | Frostwyrm |
| Dornenlabyrinth | Rankenläufer, Moosbestie | Verwunschener Hain | Dornenkönigin |
| Versunkener Tempel | Tempelwache, Flutrufer | Perlenkammer | Leviathanpriester |
| Sturmspitze | Windharpyie, Gewittergolem | Wolkenkammer | Sturmtitan |
| Schattenkrypta | Grabritter, Seelenweber | Verbotene Gruft | Schattenkönig |

Dungeons vergeben derzeit Belohnungen für den erfolgreichen Lauf, keine individuell konfigurierbaren Todesdrops je Begegnung. Die optionale Begegnung ist teilweise ein Raum statt eines Monsters. Ihre Orte und die Darstellung von zurückbleibenden Charms müssen für die weiter gefasste Regel gesondert definiert werden.

**Fehlende Inventar-IDs**

37 unterschiedliche IDs fehlen im aktuellen Inventarkatalog. Insgesamt sind 447 der 537 alten Monster-Dropslots betroffen. Auch eine existierende ID garantiert noch nicht, dass ihr alter Beschriftungstext zur heutigen Wirkung passt.

10101007, 10101008, 10101013, 10101014, 10101015, 10101016, 10101017, 10101018, 10101019, 10101020, 10101025, 10101026, 10101027, 10101028, 10101029, 10101030, 10101039, 10101040, 10103014, 10103015, 10103024, 10103025, 10103033, 10103034, 10103042, 10103043, 10103044, 10104022, 10104023, 10104024, 10104100, 10104104, 10601001, 10602002, 10603023, 10604001, 10605001

**Inventarumfang für die neue Auswahl**

| Kategorie | Einträge |
|---|---|
| ap_refill | 4 |
| boost | 35 |
| chest | 3 |
| fragment_pack | 10 |
| resource_box | 5 |
| resource_pack | 48 |
| speedup | 52 |
| teleport | 2 |
| vip_point | 7 |

**Vorhandene Gegenstände zur Zuordnung**

Diese Liste umfasst alle 166 auswählbaren Inventaritems, nicht eine neue Verteilung auf Monster. Charmtypen und die 82 direkten Reliktdefinitionen sind eigene Kataloge. Itemnamen werden unverändert aus dem aktuellen Inventarkatalog übernommen.

| Itemcode | Gegenstand | Kategorie |
|---|---|---|
| 10104001 | Energieflasche · 50 AP | ap_refill |
| 10104002 | Energieflasche · 200 AP | ap_refill |
| 10204001 | Energieflasche · 100 AP | ap_refill |
| 10300001 | Energieflasche · 10 AP | ap_refill |
| 10102001 | Rohstoffproduktion +25 % · 8 Stunden | boost |
| 10102002 | Rohstoffproduktion +25 % · 1 Tag | boost |
| 10102011 | Sammelgeschwindigkeit +50 % · 8 Stunden | boost |
| 10102021 | Baugeschwindigkeit +25 % · 8 Stunden | boost |
| 10102031 | Forschungsgeschwindigkeit +25 % · 8 Stunden | boost |
| 10102041 | Ausbildungsgeschwindigkeit +25 % · 8 Stunden | boost |
| 10102051 | Spähschutz · 8 Stunden | boost |
| 10102061 | Königsschild · 8 Stunden | boost |
| 10202001 | Nahrungsproduktion +25 % · 8 Stunden | boost |
| 10202002 | Nahrungsproduktion +25 % · 1 Tag | boost |
| 10202003 | Holzproduktion +25 % · 8 Stunden | boost |
| 10202004 | Holzproduktion +25 % · 1 Tag | boost |
| 10202005 | Steinproduktion +25 % · 8 Stunden | boost |
| 10202006 | Steinproduktion +25 % · 1 Tag | boost |
| 10202007 | Goldproduktion +25 % · 8 Stunden | boost |
| 10202008 | Goldproduktion +25 % · 1 Tag | boost |
| 10202009 | Sammelgeschwindigkeit +50 % · 1 Tag | boost |
| 10202010 | Baugeschwindigkeit +25 % · 1 Tag | boost |
| 10202011 | Forschungsgeschwindigkeit +25 % · 1 Tag | boost |
| 10202012 | Ausbildungsgeschwindigkeit +25 % · 1 Tag | boost |
| 10202013 | Truppenangriff +10 % · 1 Stunde | boost |
| 10202014 | Truppenangriff +20 % · 1 Stunde | boost |
| 10202015 | Truppenverteidigung +10 % · 1 Stunde | boost |
| 10202016 | Truppenverteidigung +20 % · 1 Stunde | boost |
| 10202017 | Truppen-Lebenspunkte +10 % · 1 Stunde | boost |
| 10202018 | Truppen-Lebenspunkte +20 % · 1 Stunde | boost |
| 10202019 | Marschkapazität +10 % · 1 Stunde | boost |
| 10202020 | Marschkapazität +20 % · 1 Stunde | boost |
| 10202021 | Angriff gegen Monster +10 % · 1 Stunde | boost |
| 10202022 | Angriff gegen Monster +20 % · 1 Stunde | boost |
| 10202023 | Marschgeschwindigkeit +25 % · 1 Stunde | boost |
| 10202024 | Marschgeschwindigkeit +50 % · 1 Stunde | boost |
| 10202025 | Spähschutz · 1 Tag | boost |
| 10202026 | Königsschild · 1 Tag | boost |
| 10300004 | Großes Kriegshorn · 1 Stunde | boost |
| 10105001 | Silbertruhe | chest |
| 10105002 | Goldtruhe | chest |
| 10105003 | Platintruhe | chest |
| 10207001 | Reliktfragmente · Gewöhnlich | fragment_pack |
| 10207002 | Reliktfragmente · Selten | fragment_pack |
| 10207003 | Reliktfragmente · Episch | fragment_pack |
| 10207004 | Reliktfragmente · Legendär | fragment_pack |
| 10207005 | Reliktfragmente · Mythisch | fragment_pack |
| 10207021 | Smaragd-Drachenei | fragment_pack |
| 10207022 | Glut-Drachenei | fragment_pack |
| 10207023 | Goldenes Drachenei | fragment_pack |
| 10300002 | Legendäres Reliktfragment | fragment_pack |
| 10300003 | Portalsphäre · Fragment | fragment_pack |
| 10205001 | Rohstoffkiste Stufe 1 | resource_box |
| 10205002 | Rohstoffkiste Stufe 2 | resource_box |
| 10205003 | Rohstoffkiste Stufe 3 | resource_box |
| 10205004 | Rohstoffkiste Stufe 4 | resource_box |
| 10205005 | Rohstoffkiste Stufe 5 | resource_box |
| 10101001 | 50.000 Nahrung | resource_pack |
| 10101002 | 200.000 Nahrung | resource_pack |
| 10101003 | 500.000 Nahrung | resource_pack |
| 10101011 | 50.000 Holz | resource_pack |
| 10101012 | 200.000 Holz | resource_pack |
| 10101021 | 50.000 Stein | resource_pack |
| 10101022 | 200.000 Stein | resource_pack |
| 10101031 | 25.000 Gold | resource_pack |
| 10101032 | 100.000 Gold | resource_pack |
| 10101041 | 50 Edelsteine | resource_pack |
| 10101042 | 150 Edelsteine | resource_pack |
| 10101043 | 500 Edelsteine | resource_pack |
| 10201001 | 1.000 Nahrung | resource_pack |
| 10201002 | 5.000 Nahrung | resource_pack |
| 10201003 | 10.000 Nahrung | resource_pack |
| 10201004 | 100.000 Nahrung | resource_pack |
| 10201005 | 1.000.000 Nahrung | resource_pack |
| 10201006 | 5.000.000 Nahrung | resource_pack |
| 10201007 | 10.000.000 Nahrung | resource_pack |
| 10201008 | 1.000 Holz | resource_pack |
| 10201009 | 5.000 Holz | resource_pack |
| 10201010 | 10.000 Holz | resource_pack |
| 10201011 | 100.000 Holz | resource_pack |
| 10201012 | 500.000 Holz | resource_pack |
| 10201013 | 1.000.000 Holz | resource_pack |
| 10201014 | 5.000.000 Holz | resource_pack |
| 10201015 | 10.000.000 Holz | resource_pack |
| 10201016 | 1.000 Stein | resource_pack |
| 10201017 | 5.000 Stein | resource_pack |
| 10201018 | 10.000 Stein | resource_pack |
| 10201019 | 100.000 Stein | resource_pack |
| 10201020 | 500.000 Stein | resource_pack |
| 10201021 | 1.000.000 Stein | resource_pack |
| 10201022 | 5.000.000 Stein | resource_pack |
| 10201023 | 10.000.000 Stein | resource_pack |
| 10201024 | 1.000 Gold | resource_pack |
| 10201025 | 5.000 Gold | resource_pack |
| 10201026 | 10.000 Gold | resource_pack |
| 10201027 | 50.000 Gold | resource_pack |
| 10201028 | 500.000 Gold | resource_pack |
| 10201029 | 1.000.000 Gold | resource_pack |
| 10201030 | 5.000.000 Gold | resource_pack |
| 10201031 | 10.000.000 Gold | resource_pack |
| 10201032 | 10 Edelsteine | resource_pack |
| 10201033 | 100 Edelsteine | resource_pack |
| 10201034 | 1.000 Edelsteine | resource_pack |
| 10201035 | 5.000 Edelsteine | resource_pack |
| 10201036 | 10.000 Edelsteine | resource_pack |
| 10103001 | Universell · 5 Minuten | speedup |
| 10103002 | Universell · 15 Minuten | speedup |
| 10103003 | Universell · 1 Stunde | speedup |
| 10103004 | Universell · 3 Stunden | speedup |
| 10103005 | Universell · 8 Stunden | speedup |
| 10103006 | Universell · 1 Tag | speedup |
| 10103011 | Bauen · 1 Stunde | speedup |
| 10103012 | Bauen · 3 Stunden | speedup |
| 10103013 | Bauen · 8 Stunden | speedup |
| 10103021 | Forschung · 1 Stunde | speedup |
| 10103022 | Forschung · 3 Stunden | speedup |
| 10103023 | Forschung · 8 Stunden | speedup |
| 10103031 | Ausbildung · 1 Stunde | speedup |
| 10103032 | Ausbildung · 3 Stunden | speedup |
| 10103041 | Heilung · 1 Stunde | speedup |
| 10203001 | Universell · 1 Minuten | speedup |
| 10203002 | Universell · 10 Minuten | speedup |
| 10203003 | Universell · 30 Minuten | speedup |
| 10203004 | Universell · 3 Tage | speedup |
| 10203005 | Universell · 7 Tage | speedup |
| 10203006 | Universell · 30 Tage | speedup |
| 10203007 | Bauen · 1 Minuten | speedup |
| 10203008 | Bauen · 5 Minuten | speedup |
| 10203009 | Bauen · 10 Minuten | speedup |
| 10203010 | Bauen · 30 Minuten | speedup |
| 10203011 | Bauen · 1 Tag | speedup |
| 10203012 | Bauen · 3 Tage | speedup |
| 10203013 | Bauen · 7 Tage | speedup |
| 10203014 | Forschung · 1 Minuten | speedup |
| 10203015 | Forschung · 5 Minuten | speedup |
| 10203016 | Forschung · 10 Minuten | speedup |
| 10203017 | Forschung · 30 Minuten | speedup |
| 10203018 | Forschung · 1 Tag | speedup |
| 10203019 | Forschung · 3 Tage | speedup |
| 10203020 | Forschung · 7 Tage | speedup |
| 10203021 | Ausbildung · 1 Minuten | speedup |
| 10203022 | Ausbildung · 5 Minuten | speedup |
| 10203023 | Ausbildung · 10 Minuten | speedup |
| 10203024 | Ausbildung · 30 Minuten | speedup |
| 10203025 | Ausbildung · 8 Stunden | speedup |
| 10203026 | Ausbildung · 1 Tag | speedup |
| 10203027 | Ausbildung · 3 Tage | speedup |
| 10203028 | Ausbildung · 7 Tage | speedup |
| 10203029 | Heilung · 1 Minuten | speedup |
| 10203030 | Heilung · 5 Minuten | speedup |
| 10203031 | Heilung · 10 Minuten | speedup |
| 10203032 | Heilung · 30 Minuten | speedup |
| 10203033 | Heilung · 3 Stunden | speedup |
| 10203034 | Heilung · 8 Stunden | speedup |
| 10203035 | Heilung · 1 Tag | speedup |
| 10203036 | Heilung · 3 Tage | speedup |
| 10203037 | Heilung · 7 Tage | speedup |
| 10208001 | Gebietsteleporter | teleport |
| 10208002 | Zufallsteleporter | teleport |
| 10106001 | Prestigemedaille · 100 Punkte | vip_point |
| 10106002 | Prestigemedaille · 500 Punkte | vip_point |
| 10106003 | Prestigemedaille · 2.000 Punkte | vip_point |
| 10206001 | Prestigemedaille · 10 Punkte | vip_point |
| 10206002 | Prestigemedaille · 1.000 Punkte | vip_point |
| 10206003 | Prestigemedaille · 5.000 Punkte | vip_point |
| 10206004 | Prestigemedaille · 10.000 Punkte | vip_point |

Quelle: data/monsters.json, data/world_spawn.json, data/items.json, data/dungeons.json sowie MonsterData.php, MonsterRally.php und MarchTick.php. Die Hauptbewertung und auswählbaren Erweiterungen stehen in FEATURE_REVIEW_2026-09-12.md.
