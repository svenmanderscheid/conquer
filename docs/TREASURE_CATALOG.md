# Vollständiger Schatzkatalog

Alle **77 gelieferten PNG-Schatzkarten** sind unverändert eingebunden. **19** nutzen ihre bereits bestehenden Schatzcodes, **58** ergänzen den Katalog. Die **5** eigenen bisherigen Relikte bleiben zusätzlich erhalten: insgesamt **82 Schätze**. Alle Karten wurden in vier Kontaktbögen visuell geprüft. Die Bilder liegen unter `assets/art/items/treasures/`; ihre SHA-256-Prüfsummen und die vollständige Zuordnung stehen in `tests/fixtures/treasure_reference_manifest.json`.

## Bestand und Wirkung

- Die bisherigen 24 Codes, Fragmentmengen je Stufe, Maximalstufen und sämtliche Bonuswerte sind unverändert. `tests/fixtures/treasure_legacy_balance.json` hält diesen Bestand unabhängig vom erweiterten Katalog fest. Es werden keine Fragmente oder Ausrüstungen verteilt.
- Die fünf Originalrahmen werden als Normal (grau), Selten (blau), Episch (violett), Legendär (orange) und Mythisch (türkis/gold) eingeordnet. Dies gilt auch für die 19 bereits zugeordneten Referenzen. Ihre historischen Fragmentgrenzen bleiben ausdrücklich bestehen, selbst wenn sie vom Standard der angezeigten Seltenheit abweichen.
- Neue Schätze benötigen je Stufe 10/20/40/80/150 Fragmente entsprechend ihrer Seltenheit; alle besitzen zehn Stufen. Das Fragmentkonto bleibt kontoweit, angelegte Plätze und ihre Boni gelten nur in der jeweiligen Welt.
- Die Originalbilder liefern Aussehen und Rahmen, aber keine auslesbaren verbindlichen Effektzahlen. Die neuen Werte sind daher thematisch passende Spielbalance für Conquer und keine Behauptung über identische League-of-Kingdoms-Regeln. Jeder Effekt verwendet einen bereits tatsächlich ausgewerteten Spielwert.
- Ein angelegtes Trank- oder Schriftrollenrelikt wirkt dauerhaft wie andere Schätze; es wird nicht als Verbrauchsgegenstand aufgebraucht. Es behauptet weder sofortige Heilung noch Lebensraub oder Teleportation.
- Boni gelten für neu gestartete Aufträge und Märsche; bereits laufende Aufträge behalten ihre gespeicherten Zeiten. Laufende Rohstoffproduktion wird vor Ausrüstungswechseln und relevanten Fragmentaufstiegen noch mit dem bisherigen Bonus abgerechnet.

## Echte Verbraucher

| Effektgruppe | Umsetzung |
|---|---|
| Nahrung, Holz, Stein, Gold | `BuffEngine` → `ResourceTick` / `BuildingData::getHourlyRate` |
| Bauen, Forschen, Ausbildung | `CityState` / `BuildingUpgrader`, `ResearchHandler`, `TroopTrainer` |
| Angriff, Verteidigung, Lebenspunkte | `BuffEngine::effectiveMultiplier` → Monsterkämpfe, Arena, Stadt- und Schreinkämpfe; Feldzüge verwenden die passenden Angriff-/Verteidigungswerte |
| Monsterangriff | `BattleEngine` und `ExpeditionRules::strength` |
| Marschtempo, Sammelmarschtempo | `MarchDispatcher`, `GatherService`, `CongressService`, `RallyService`, `ExpeditionRules` |
| Truppen je Marsch | `ResearchEffects::limits`, als absolute zusätzliche Truppenzahl |
| Hospitalplätze | `HospitalService::getStatus`, als absolute zusätzliche Kapazität |
| Rohstoffschutz | `DefenseService::protectionFraction`, begrenzt auf 100 % Schutz |

## Originalkarten und Zuordnung

Alle Pfade in der Spalte „Original“ beziehen sich auf `LOK/Treasures/`. `name_de` wird im Menü bevorzugt. `icon_framed: true` kennzeichnet die vollständigen Originalkarten einschließlich Rahmen. Die PNGs werden weder zugeschnitten noch neu gerahmt.

| Code | Deutscher Name | Original | Seltenheit | Fragmente/Stufe | Effekt auf Stufe 1 und Steigerung |
|---|---|---|---|---:|---|
| 60100006 | Schützenhut | `acher hat.png` | normal | 10 | Fernkampfangriff +1.5% (+0.4 Prozentpunkte/Stufe) |
| 60300101 | Amulett des Lebens | `amulet of life.png` | epic | 40 | Armee-Lebenspunkte +4% (+1 Prozentpunkte/Stufe); Hospitalplätze +500 (+150/Stufe) |
| 60500101 | Uralter Armreif | `Ancient Bracelet.png` | mythic | 150 | Armeeangriff +8% (+2 Prozentpunkte/Stufe); Armee-Lebenspunkte +8% (+2 Prozentpunkte/Stufe); Truppen je Marsch +1000 (+250/Stufe) |
| 60100002 | Holzfälleraxt | `axe.png` | normal | 10 | Holzproduktion +2% (+0.5 Prozentpunkte/Stufe) |
| 60200101 | Schwarzes Ross | `black horse.png` | rare | 20 | Reitereiangriff +3% (+0.6 Prozentpunkte/Stufe); Marschtempo +4% (+1 Prozentpunkte/Stufe) |
| 60200102 | Gesegnete Zweige | `blessed branches.png` | rare | 20 | Nahrungsproduktion +4% (+1 Prozentpunkte/Stufe); Sammelmarschtempo +2% (+0.5 Prozentpunkte/Stufe) |
| 60400101 | Segen der Göttin | `blessing of goddess.png` | legendary | 80 | Armee-Lebenspunkte +6% (+1.5 Prozentpunkte/Stufe); Armeeverteidigung +5% (+1 Prozentpunkte/Stufe); Hospitalplätze +1000 (+250/Stufe) |
| 60300102 | Knochenrüstung | `bone armor.png` | epic | 40 | Armeeverteidigung +4% (+1 Prozentpunkte/Stufe); Infanterie-Lebenspunkte +3% (+0.7 Prozentpunkte/Stufe) |
| 60300001 | Himmlischer Schild | `celestial shield.png` | legendary | 40 | Armeeverteidigung +5% (+1.2 Prozentpunkte/Stufe); Infanterie-Lebenspunkte +3% (+0.8 Prozentpunkte/Stufe) |
| 60100003 | Meißel | `chisel.png` | normal | 10 | Steinproduktion +2% (+0.5 Prozentpunkte/Stufe) |
| 60300004 | Verderbter Stab | `corrupted staff.png` | epic | 40 | Forschungsgeschwindigkeit +6% (+1.5 Prozentpunkte/Stufe); Baugeschwindigkeit +2% (+0.5 Prozentpunkte/Stufe) |
| 60400102 | Kristallflakon | `crystal flask.png` | legendary | 80 | Armee-Lebenspunkte +5% (+1.2 Prozentpunkte/Stufe); Hospitalplätze +1500 (+300/Stufe) |
| 60300003 | Verfluchtes Schwert | `cursed sword.png` | epic | 40 | Armeeangriff +4% (+1 Prozentpunkte/Stufe); Marschtempo +3% (+0.8 Prozentpunkte/Stufe) |
| 60400004 | Dunkler Kristall | `dark crystal.png` | epic | 80 | Armeeangriff +10% (+2.5 Prozentpunkte/Stufe); Monsterangriff +8% (+2 Prozentpunkte/Stufe); Armee-Lebenspunkte +5% (+1.5 Prozentpunkte/Stufe) |
| 60500102 | Dämonenbann | `Demonbane.png` | mythic | 150 | Monsterangriff +15% (+3 Prozentpunkte/Stufe); Armeeangriff +8% (+2 Prozentpunkte/Stufe); Infanterie-Lebenspunkte +6% (+1.5 Prozentpunkte/Stufe) |
| 60300103 | Drachenzahn | `dragon fang.png` | epic | 40 | Reitereiangriff +5% (+1 Prozentpunkte/Stufe); Monsterangriff +4% (+1 Prozentpunkte/Stufe) |
| 60500002 | Drachenschuppenpanzer | `dragon scale mail.png` | epic | 150 | Rohstoffschutz +20% (+3 Prozentpunkte/Stufe); Nahrungsproduktion +10% (+2 Prozentpunkte/Stufe); Holzproduktion +10% (+2 Prozentpunkte/Stufe); Hospitalplätze +2000 (+500/Stufe) |
| 60400103 | Drachentöter | `Dragon Slayer.png` | legendary | 80 | Armeeangriff +6% (+1.5 Prozentpunkte/Stufe); Monsterangriff +10% (+2 Prozentpunkte/Stufe) |
| 60300104 | Drachenseele | `dragon soul.png` | epic | 40 | Armee-Lebenspunkte +4% (+1 Prozentpunkte/Stufe); Monsterangriff +4% (+1 Prozentpunkte/Stufe) |
| 60300002 | Drachenzahnbogen | `Dragon Tooth Bow.png` | legendary | 40 | Fernkampfangriff +5% (+1.2 Prozentpunkte/Stufe); Monsterangriff +3% (+0.8 Prozentpunkte/Stufe) |
| 60300105 | Flammenblüte | `Flame flower.png` | epic | 40 | Armeeangriff +3% (+0.7 Prozentpunkte/Stufe); Monsterangriff +4% (+1 Prozentpunkte/Stufe) |
| 60400104 | Kraft der Natur | `force of nature.png` | legendary | 80 | Nahrungsproduktion +8% (+2 Prozentpunkte/Stufe); Holzproduktion +8% (+2 Prozentpunkte/Stufe); Armee-Lebenspunkte +3% (+0.7 Prozentpunkte/Stufe) |
| 60300106 | Golemfragment | `fragment of golem.png` | epic | 40 | Infanterieverteidigung +5% (+1 Prozentpunkte/Stufe); Steinproduktion +4% (+1 Prozentpunkte/Stufe) |
| 60300107 | Lebensjuwel | `gem of vital.png` | epic | 40 | Armee-Lebenspunkte +4% (+1 Prozentpunkte/Stufe); Hospitalplätze +600 (+150/Stufe) |
| 60300108 | Geschenk der Zwerge | `gift of dwarf.png` | epic | 40 | Steinproduktion +5% (+1 Prozentpunkte/Stufe); Baugeschwindigkeit +4% (+1 Prozentpunkte/Stufe) |
| 60300109 | Goldene Axt | `golden axe.png` | epic | 40 | Holzproduktion +6% (+1.5 Prozentpunkte/Stufe); Sammelmarschtempo +3% (+0.7 Prozentpunkte/Stufe) |
| 60300006 | Goldene Ähre | `golden grain.png` | epic | 40 | Nahrungsproduktion +6% (+1.5 Prozentpunkte/Stufe); Sammelmarschtempo +4% (+1 Prozentpunkte/Stufe) |
| 60300110 | Goldener Hammer | `golden hammer.png` | epic | 40 | Baugeschwindigkeit +5% (+1.2 Prozentpunkte/Stufe); Armeeangriff +2% (+0.5 Prozentpunkte/Stufe) |
| 60300111 | Goldene Spitzhacke | `golden pick.png` | epic | 40 | Goldproduktion +6% (+1.5 Prozentpunkte/Stufe); Steinproduktion +4% (+1 Prozentpunkte/Stufe) |
| 60300112 | Hammer der Zerstörung | `hammer of destruction.png` | epic | 40 | Armeeangriff +4% (+1 Prozentpunkte/Stufe); Monsterangriff +4% (+1 Prozentpunkte/Stufe) |
| 60100101 | Hammer | `hammer.png` | normal | 10 | Baugeschwindigkeit +2% (+0.5 Prozentpunkte/Stufe) |
| 60400001 | Vampirhand | `hand of vampire.png` | epic | 80 | Armeeangriff +8% (+2 Prozentpunkte/Stufe); Armeeverteidigung +5% (+1.5 Prozentpunkte/Stufe); Infanterie-Lebenspunkte +4% (+1 Prozentpunkte/Stufe) |
| 60200103 | Heiltrank | `healing potion.png` | rare | 20 | Hospitalplätze +400 (+100/Stufe); Armee-Lebenspunkte +2% (+0.5 Prozentpunkte/Stufe) |
| 60400105 | Sternenherz | `heart of star.png` | legendary | 80 | Armee-Lebenspunkte +6% (+1.5 Prozentpunkte/Stufe); Forschungsgeschwindigkeit +6% (+1.5 Prozentpunkte/Stufe) |
| 60400106 | Herzsucher | `heartseeker.png` | legendary | 80 | Fernkampfangriff +8% (+2 Prozentpunkte/Stufe); Armeeangriff +3% (+0.7 Prozentpunkte/Stufe) |
| 60400107 | Himmlische Beere | `heavenly berry.png` | legendary | 80 | Nahrungsproduktion +8% (+2 Prozentpunkte/Stufe); Hospitalplätze +1000 (+250/Stufe) |
| 60400108 | Heldengeist | `heroic spirit.png` | legendary | 80 | Armeeangriff +6% (+1.5 Prozentpunkte/Stufe); Armeeverteidigung +4% (+1 Prozentpunkte/Stufe); Ausbildungsgeschwindigkeit +4% (+1 Prozentpunkte/Stufe) |
| 60400109 | Juwel der Harmonie | `jewel of harmony.png` | legendary | 80 | Forschungsgeschwindigkeit +6% (+1.5 Prozentpunkte/Stufe); Baugeschwindigkeit +4% (+1 Prozentpunkte/Stufe); Ausbildungsgeschwindigkeit +4% (+1 Prozentpunkte/Stufe) |
| 60200001 | Langschild | `kite shield.png` | rare | 20 | Infanterieverteidigung +3% (+0.8 Prozentpunkte/Stufe); Armee-Lebenspunkte +1% (+0.3 Prozentpunkte/Stufe) |
| 60200002 | Langbogen | `long bow.png` | rare | 20 | Fernkampfangriff +3% (+0.8 Prozentpunkte/Stufe); Marschtempo +1% (+0.3 Prozentpunkte/Stufe) |
| 60200104 | Magischer Meißel | `magic chisel.png` | rare | 20 | Steinproduktion +4% (+1 Prozentpunkte/Stufe); Baugeschwindigkeit +2% (+0.5 Prozentpunkte/Stufe) |
| 60200105 | Magische Spitzhacke | `magic pick.png` | rare | 20 | Goldproduktion +4% (+1 Prozentpunkte/Stufe); Sammelmarschtempo +2% (+0.5 Prozentpunkte/Stufe) |
| 60200106 | Magischer Pflug | `magic plow.png` | rare | 20 | Nahrungsproduktion +4% (+1 Prozentpunkte/Stufe); Sammelmarschtempo +2% (+0.5 Prozentpunkte/Stufe) |
| 60200107 | Magische Säge | `magic saw.png` | rare | 20 | Holzproduktion +4% (+1 Prozentpunkte/Stufe); Baugeschwindigkeit +2% (+0.5 Prozentpunkte/Stufe) |
| 60100001 | Dünger | `manure.png` | normal | 10 | Nahrungsproduktion +2% (+0.5 Prozentpunkte/Stufe) |
| 60300113 | Zeichen des Kriegers | `mark of warrior.png` | epic | 40 | Ausbildungsgeschwindigkeit +4% (+1 Prozentpunkte/Stufe); Armeeangriff +3% (+0.7 Prozentpunkte/Stufe) |
| 60400003 | Spiegel der Wahrheit | `Mirror of Truth.png` | mythic | 80 | Forschungsgeschwindigkeit +10% (+2.5 Prozentpunkte/Stufe); Baugeschwindigkeit +6% (+1.5 Prozentpunkte/Stufe); Ausbildungsgeschwindigkeit +4% (+1 Prozentpunkte/Stufe) |
| 60300114 | Obsidianplatte | `obsidian plate.png` | epic | 40 | Armeeverteidigung +5% (+1 Prozentpunkte/Stufe); Infanterie-Lebenspunkte +2% (+0.5 Prozentpunkte/Stufe) |
| 60300115 | Portalsphäre | `orb of portal.png` | epic | 40 | Marschtempo +6% (+1.5 Prozentpunkte/Stufe); Sammelmarschtempo +3% (+0.7 Prozentpunkte/Stufe) |
| 60500103 | Orichalkumdolch | `Orichalcum Dagger.png` | mythic | 150 | Armeeangriff +12% (+2.5 Prozentpunkte/Stufe); Marschtempo +8% (+2 Prozentpunkte/Stufe); Monsterangriff +8% (+2 Prozentpunkte/Stufe) |
| 60300116 | Anhänger der Göttin | `pendant of goddess.png` | epic | 40 | Armee-Lebenspunkte +4% (+1 Prozentpunkte/Stufe); Armeeverteidigung +3% (+0.7 Prozentpunkte/Stufe) |
| 60400110 | Klinge des Verderbens | `perdition's blade.png` | legendary | 80 | Armeeangriff +8% (+2 Prozentpunkte/Stufe); Monsterangriff +6% (+1.5 Prozentpunkte/Stufe) |
| 60100102 | Spitzhacke | `pick.png` | normal | 10 | Goldproduktion +2% (+0.5 Prozentpunkte/Stufe) |
| 60100103 | Pflug | `plow.png` | normal | 10 | Nahrungsproduktion +2% (+0.5 Prozentpunkte/Stufe) |
| 60200108 | Trank der Stärke | `potion of strength.png` | rare | 20 | Armeeangriff +3% (+0.7 Prozentpunkte/Stufe) |
| 60200109 | Trank der Schnelligkeit | `potion of swiftness.png` | rare | 20 | Marschtempo +4% (+1 Prozentpunkte/Stufe); Sammelmarschtempo +2% (+0.5 Prozentpunkte/Stufe) |
| 60200110 | Trank der Widerstandskraft | `potion of toughness.png` | rare | 20 | Armeeverteidigung +3% (+0.7 Prozentpunkte/Stufe); Armee-Lebenspunkte +2% (+0.5 Prozentpunkte/Stufe) |
| 60200003 | Sattel | `saddle.png` | normal | 20 | Reitereiangriff +3% (+0.8 Prozentpunkte/Stufe); Marschtempo +2% (+0.5 Prozentpunkte/Stufe) |
| 60300117 | Weisenhut | `sage's hat.png` | epic | 40 | Forschungsgeschwindigkeit +5% (+1.2 Prozentpunkte/Stufe); Ausbildungsgeschwindigkeit +2% (+0.5 Prozentpunkte/Stufe) |
| 60500104 | Zepter des Urteils | `Scepter of Judgement.png` | mythic | 150 | Forschungsgeschwindigkeit +10% (+2.5 Prozentpunkte/Stufe); Armeeangriff +8% (+2 Prozentpunkte/Stufe); Armeeverteidigung +6% (+1.5 Prozentpunkte/Stufe) |
| 60300118 | Schriftrolle der Zehrung | `scroll of drain.png` | epic | 40 | Armee-Lebenspunkte +3% (+0.7 Prozentpunkte/Stufe); Monsterangriff +5% (+1.2 Prozentpunkte/Stufe) |
| 60400111 | Peitsche des Feldwebels | `sergeant's whip.png` | legendary | 80 | Ausbildungsgeschwindigkeit +8% (+2 Prozentpunkte/Stufe); Truppen je Marsch +800 (+200/Stufe) |
| 60300119 | Leuchtender Pfeil | `shining arrow.png` | epic | 40 | Fernkampfangriff +6% (+1.5 Prozentpunkte/Stufe); Marschtempo +3% (+0.7 Prozentpunkte/Stufe) |
| 60100104 | Kurzschwert | `shot sword.png` | normal | 10 | Armeeangriff +1.5% (+0.4 Prozentpunkte/Stufe) |
| 60100105 | Schaufel | `shovel.png` | normal | 10 | Steinproduktion +2% (+0.5 Prozentpunkte/Stufe); Sammelmarschtempo +1% (+0.25 Prozentpunkte/Stufe) |
| 60200111 | Silberapfel | `silver apple.png` | rare | 20 | Nahrungsproduktion +4% (+1 Prozentpunkte/Stufe); Armee-Lebenspunkte +2% (+0.5 Prozentpunkte/Stufe) |
| 60100106 | Speer | `spear.png` | normal | 10 | Reitereiangriff +2% (+0.5 Prozentpunkte/Stufe) |
| 60200004 | Stahlhammer | `steel hammer.png` | rare | 20 | Baugeschwindigkeit +4% (+1 Prozentpunkte/Stufe) |
| 60500105 | Sturmbrecher | `Stormbreaker.png` | mythic | 150 | Armeeangriff +12% (+2.5 Prozentpunkte/Stufe); Marschtempo +8% (+2 Prozentpunkte/Stufe); Truppen je Marsch +1200 (+300/Stufe) |
| 60400112 | Hand des Meisters | `touch of master.png` | legendary | 80 | Baugeschwindigkeit +8% (+2 Prozentpunkte/Stufe); Forschungsgeschwindigkeit +5% (+1.2 Prozentpunkte/Stufe); Ausbildungsgeschwindigkeit +3% (+0.7 Prozentpunkte/Stufe) |
| 60300005 | Kriegsbanner | `war flag.png` | rare | 40 | Truppen je Marsch +500 (+200/Stufe); Armeeangriff +2% (+0.5 Prozentpunkte/Stufe) |
| 60200006 | Kriegshorn | `war horn.png` | legendary | 20 | Ausbildungsgeschwindigkeit +4% (+1 Prozentpunkte/Stufe) |
| 60300120 | Kriegspike | `war pike.png` | epic | 40 | Reitereiangriff +6% (+1.5 Prozentpunkte/Stufe); Infanterieverteidigung +3% (+0.7 Prozentpunkte/Stufe) |
| 60100107 | Wassereimer | `water bucket.png` | normal | 10 | Nahrungsproduktion +1.5% (+0.4 Prozentpunkte/Stufe); Sammelmarschtempo +1% (+0.25 Prozentpunkte/Stufe) |
| 60400113 | Schwingen des Mutes | `wing of valor.png` | legendary | 80 | Marschtempo +10% (+2.5 Prozentpunkte/Stufe); Truppen je Marsch +800 (+200/Stufe) |
| 60100108 | Holzbogen | `wooden bow.png` | normal | 10 | Fernkampfangriff +1.5% (+0.4 Prozentpunkte/Stufe) |
| 60100109 | Holzschild | `wooden shield.png` | normal | 10 | Infanterieverteidigung +1.5% (+0.4 Prozentpunkte/Stufe) |

## Erhaltene eigene Relikte

| Code | Name | Grafik | Fragmente/Stufe | Effekte |
|---|---|---|---:|---|
| 60100004 | Münzbeutel | `pouch.svg` | 10 | Goldproduktion +2% (+0.5 Prozentpunkte/Stufe) |
| 60100005 | Lederriemen | `strap.svg` | 10 | Infanterieverteidigung +1.5% (+0.4 Prozentpunkte/Stufe) |
| 60200005 | Gelehrtenbuch | `book.svg` | 20 | Forschungsgeschwindigkeit +4% (+1 Prozentpunkte/Stufe) |
| 60400002 | Himmelskompass | `compass.svg` | 80 | Marschtempo +8% (+2 Prozentpunkte/Stufe); Truppen je Marsch +1000 (+300/Stufe); Sammelmarschtempo +6% (+1.5 Prozentpunkte/Stufe) |
| 60500001 | Krone der Ewigen Flamme | `prestige.svg` | 150 | Armeeangriff +15% (+3 Prozentpunkte/Stufe); Armeeverteidigung +10% (+2.5 Prozentpunkte/Stufe); Armee-Lebenspunkte +8% (+2 Prozentpunkte/Stufe); Truppen je Marsch +2000 (+500/Stufe) |

## Prüfungen

- `php tests/treasure_catalog.php`: alle 82 Definitionen, alle 77 unveränderten Originalkarten, eindeutige Quellen/Codes, Rahmenfarben, deutsche Metadaten, Fragmentstufen, bestehende Bonuswerte und aktive Effektverbraucher.
- `php tests/treasure_loadouts.php`: dynamischer Gesamtkatalog plus isolierte Datenbank-/HTTP-Prüfung für Ausrüstungsersatz, Verschieben, Ablegen, Welttrennung, Produktion, Kampfwerte, Kapazitäten, Bau-/Forschungs-/Ausbildungszeiten und Zugriffsschutz.
