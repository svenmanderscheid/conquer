# Alpha: Funktionsabgleich und Implementierungsplan

Stand: 26.09.2026, lokaler Arbeitsbaum, einschließlich uncommitteter Änderungen. Keine Veröffentlichung. Ergänzt nach den Korrekturläufen: `ALPHA_FIX_SYSTEMS_2026-09-26.md`; Rohprotokolle in `artifacts/alpha-audit-2026-09-26/`.

## Vergleichsgrundlage

Die Suche im Projektordner einschließlich versteckter Anhänge und Dateiendungen hat kein separat bezeichnetes „Arbeitsheft“ ergeben. Als im Ordner vorliegende Arbeitsgrundlage werden deshalb **docs/SPEC.md**, die jüngeren Statusdokumente und insbesondere **docs/planning/IMPLEMENTATION_PLAN.md** mit **docs/planning/ROADMAP_AGENT.md** verwendet. Letztere dokumentieren die Annahme von A01/A02 und E01–E42. Die beiden XLSX-Dateien unter outputs sind Reliktkataloge, keine Gesamtfunktionsspezifikation. Die Anhänge enthalten JPGs. Es wird kein Abgleich mit einem unbekannten externen Heft behauptet.

SPEC v1.6 ist historisch: „nur PWA“, pixeliger Stil und zahlreiche MVP-Angaben widersprechen neueren Entscheidungen. Maßgeblich für Gestaltung und mobile Zukunft sind die aktuellen Nutzeranweisungen, UI_STYLE_GUIDE und MOBILE_APP_STRATEGY. Die Entfernung der eigenständigen 3D-Ansicht ist der aktuelle Auftrag und keine fehlende Spielfunktion.

**Status:** „Vorhanden“ bedeutet Code mit angeschlossenem Fachablauf; „Teilweise“ bedeutet ein belegtes Fundament mit fehlendem Sollumfang; „Offen“ bedeutet kein vollständiger Ablauf im geprüften Bestand nachgewiesen. Keine Zeile behauptet damit vollständige Produktionsabnahme. Testnamen beziehen sich auf `tests/`; **✓** ist ein neuer erfolgreicher Lauf am 26.09., **!** ein neuer Fehllauf, **—** keine erneute gezielte Abnahme. Alle 44 Plan-IDs sind genau einmal enthalten.

## Angenommene 44 Pakete

| ID / Soll | Ist und Codeevidenz | Prüfung | Fehlender Umfang / nächste Umsetzung |
|---|---|---|---|
| A01 Dropverwaltung | Teilweise: `src/Game/Rewards/RewardCatalog.php`, `src/Admin/RewardEditor.php`, Solo-/Rally-Auszahlung | reward_preview ✓; reward_admin ✓ | Alle Instanzquellen vereinheitlichen; Revision pro Auftrag und Auszahlung belegen. |
| A02 Charm je Monster | Teilweise: `src/Game/Charm/MonsterCharmLifecycle.php`, `CharmCollectionService.php` | charm_lifecycle ✓ | Weltmonsterweg vorhanden; Dungeon-/Abenteueranker und genau ein Charm je Instanzkill ergänzen. |
| E01 Bestiarium | Teilweise: `src/Game/Map/MapSearchService.php::catalog`, MonsterData; kein vollständiges Bestiarium nachgewiesen | map_search ✓ | Familien, Regionen, aktiv/inaktiv und echte Beutevorschau als eigenes Katalogfenster. |
| E02 Itemquellensuche | Offen: RewardCatalog/RewardPreview liefern Vorwärtsvorschau | reward_preview ✓ | Rückwärtsindex Item → aktive Monster, Truhen, Shop, Dungeon und Feldzug; verlinkte Bedingungen. |
| E03 Einführung | Teilweise: `src/Game/Tutorial/TutorialService.php::STEPS`, `assets/js/beginner-guide.js` | guide_progression ✓ | Bestehende zwölf Schritte um Charm-Sammlung und Allianzhilfe erweitern; neuen Account komplett durchspielen. |
| E04 Hospital | Vorhanden: `src/Game/Hospital/HospitalService.php` | hospital_healing ✓ | Überlauf/Kampfverlust auch browserseitig zeigen und Grenzfälle mit aktuellem Kampfmodell abnehmen. |
| E05 Allianzshop | Offen: TreasuryService und TradingShopService sind Grundlagen, kein persönlicher Allianzmünzen-Shop belegt | trading_shop ✓ (normaler Shop) | Persönliches Münzkonto, Quellen, Limits, atomarer Kauf und UI. |
| E06 Allianzgeschenke | Vorhandene Grundlage: `src/Game/Alliance/AllianceGiftService.php`, Gemeinschaftsansicht | — | Vollständigen sichtbaren Claim-/Ablauf-/Weltwechselweg erneut testen; Sichtbarkeit allein reicht nicht. |
| E07 Weltweite Monstersuche | Teilweise: `src/Game/Map/MapSearchService.php::search` | map_search ✓ | Kategorie/Stufe vorhanden; Filter nach Familie, Biom/Region und tatsächlichem Drop ergänzen. |
| E08 Wochenziele | Offen: `src/Game/Quest/DailyQuestService.php` deckt tägliche Aufgaben ab | — | Getrennte Wochenperiode, endliche Ziele, Ereignisbelege, einmalige Abholung. |
| E09 Lord-Meilensteine | Teilweise: `src/Game/Player/LordLevel.php`, MasteryService | lord_talents ✓; full_progression ✓ | Explizite einmalige Meilensteinbelohnungen mit Claimbeleg; Talente sind kein Ersatz. |
| E10 VIP/Truhenregeln | Vorhanden: `src/Game/Vip/VipService.php`, `src/Game/Treasure/ChestService.php` | vip_system ✓; daily_chests ✓ | Zeitzonen-/Resetwechsel und sichtbare Sperrgründe browserseitig abnehmen. |
| E11 Allianzaufträge | Offen: AllianceHelpService und Spenden existieren | — | Gemeinsame Aufträge aus realen Ereignissen, Fortschrittsgrenzen und Mitgliederberechtigung. |
| E12 Allianzkalender | Teilweise: EventService hat Weltereignis-Zeitpläne | — | Von Mitgliedern planbare Allianztermine, Anmeldung/Abmeldung, Kapazität und Rollen. |
| E13 Kartenmarker | Offen: Weltkarte/Koordinaten vorhanden; dauerhafte persönliche/Allianzmarker nicht belegt | — | Marker-CRUD, Sichtbarkeitsrechte, Ablauf und Deep Links. |
| E14 Erkundung/Ruinen | Teilweise: `src/Game/Map/FrontierService.php`, NeutralVillageService | — | Individuell persistente Erkundung und ein kompletter Ruinenlauf mit Rewardbeleg. |
| E15 Allianzgebiet | Teilweise: `src/Game/Alliance/AllianceTerritoryService.php` bietet Zentrum/Außenposten/Garnison/Radiusboni | — | Zusammenhängende Besitzgeometrie, Grenzkonflikte und Abrissregeln nach Soll nachweisen/ergänzen. |
| E16 Landstufen/Kartenphasen | Vorhandene Grundlage: `src/Game/World/LandProgressService.php`, LandUnlockService, LandAccessPolicy | land_progression ✓; map_land_access ✓; regional_rewards ✓ | Ausdrückliche Zonensperren werden jetzt bewahrt; Kristallspenden bleiben bis zur dokumentierten Entscheidung aus. |
| E17 Außenbauplätze | Teilweise: `src/Game/City/BuildingPlotService.php` | building_plots ✓ | Spezialisierte Außenfunktionen gegenüber normalen Stadtbauplätzen abgrenzen und vollständig anbinden. |
| E18 Solo-Abenteuer | Offen: DungeonService/ExpeditionService liefern wiederverwendbare Grundlagen | dungeon_lifecycle ✓ | Endliche Kampagne mit Kapiteln, Ankern, Erstabschluss und wiederaufnehmbarem Zustand. |
| E19 Regionale Bosse | Vorhandene Grundlage: `src/Game/World/RegionalSpawns.php`, `src/Game/Rally/MonsterRally.php` | regional_bosses ✓; regional_rewards ✓ | Unterschiedliche Mechaniken je Boss, stabile Beute-/Regionstests und verständliche Anzeigen. |
| E20 Dungeon-Gruppenfinder | Teilweise: `src/Game/Dungeon/DungeonService.php` | dungeon_lifecycle ✓ | Offene Suche, Rollen/Plätze, Beitritt, Abbruch, Start und Rückkehr als vollständiger Gruppenfinder. |
| E21 Gezielte Fragmentwahl | Teilweise: `src/Game/Treasure/TreasureService.php::exchangeUniversalFragments` | inventory_bulk ✓ | Universalfragmente und konkrete Reliktfragmente sind vorhanden und bilanziert; eine eigene einmalige Wahlbelohnung bleibt offen. |
| E22 Rezepte/Materialien | Offen: Inventar und Fragmentumtausch vorhanden | inventory_rewards ✓ | Kleiner Rezeptkatalog, Materialverbrauch, Ergebnisbeleg, Herstellung und Parallelaufruftest. |
| E23 Allianzturniere | Offen: Conquest/Arena sind andere Modi | — | Freiwillige Anmeldung, Paarung, abgesicherte Regeln, Resultat und einmalige Preise. |
| E24 PvE-Wertung | Teilweise: `src/Game/Kingdom/KingdomService.php::standings`, EventService | — | Begrenzte PvE-Wertungsperiode mit manipulationsfesten Ereignissen und Gleichstandsregel. |
| E25 Saison ohne Reset | Offen: EventService ist Grundlage | — | Versionierter Saisonvertrag, Start/Ende/Abschluss, Belohnungen, Stadtfortbestand. |
| E26 Helden | Offen: Lord-Talente vorhanden, kein Helden-Lebenszyklus belegt | lord_talents ✓ (anderes System) | Kleines Rekrutieren→Formation→Kampf→Fortschritt-MVP nach stabiler Balance. |
| E27 Begleiter | Offen | — | Separates MVP nach Helden, mit Quellen, Ausrüstung und nachweisbarer Kampf-/Wirtschaftswirkung. |
| E28 Spielerhandel | Offen: `src/Game/Trading/MarketService.php` und CaravanService sind kein Festpreis-Spielermarkt | trading_shop ✓ (NPC-Shop) | Angebote, Reservierungen, Kontenbuch, Ablauf und atomare Käufer-/Verkäuferabrechnung. |
| E29 Opt-in-Push | Offen: `src/Game/Notification/NotificationService.php` pollt Spielnachrichten; kein Push-Abonnement belegt | — | Geräteschnittstelle, Zustimmung, Tokenwiderruf, Deduplizierung, Web-/Capacitor-Adapter. |
| E30 DE/FR/LU | Teilweise: `src/Game/Locale.php::SUPPORTED` enthält DE/FR/EN | — | Luxemburgisch fehlt im unterstützten Katalog; vollständige Texte und Rohschlüsseltest, EN erhalten. |
| E31 Rückkehrerpfad | Offen: ReturnSummary ist Rückkehrzusammenfassung, kein Belohnungspfad | return_summary nicht neu ausgeführt | Abwesenheitsgrenze, begrenzte Ziele, einmalige Berechtigung, Anti-Farming-Regeln. |
| E32 Kontakte/Block/Melden | Teilweise: CommunityService, MailboxService, BugReportService | mailbox ✓; world_chat ✓ | Kontakte und Blockierung durch alle Chat-/Postkanäle, Spielermeldung und Moderationsfolge. |
| E33 Wirtschaftsstatistik | Teilweise: Operation/Reward-Belege vorhanden | army_receipts ✓ | Gemeinsames Quellen-/Senkenledger, reale Aggregation, Limits, berechtigter Admin-Drilldown. |
| E34 Inhaltseditor | Teilweise: RewardEditor und versionierte Dropregeln | reward_admin ✓ | Allgemeiner Entwurf→Simulation→Veröffentlichung-Vertrag, Audit und Rücknahme. |
| E35 Weltenwettbewerb | Offen: `src/Game/World/WorldService.php` verwaltet mehrere Welten | — | Erst nach E23: zwei Welten, isolierte Teilnahme und sichere Ergebnis-/Preiszuordnung. |
| E36 Bevölkerung/Versorgung | Offen: Ressourcenproduktion und Gebäude sind Grundlage | mvp_rules ✓ | Eigene Bestände, Kapazitäten, Versorgungstakt, Verluste und sichtbare Auswirkungen. |
| E37 Allianz-Wellenlauf | Teilweise: DefenseService und EventService mit Invasionen | — | Allianzbezogene Wellen, Anmeldung, Verstärkung, Fortschritt und Abschlussbelohnungen. |
| E38 Prüfungsstufen/Raid | Teilweise: DungeonService mit Schwierigkeiten | dungeon_lifecycle ✓ | Persistenter Stufenpfad, Erstabschlüsse und freigeschalteter Raid mit eindeutigen Kosten. |
| E39 Allgemeine Abstimmungen | Offen: Rollen/Diplomatie vorhanden | — | Endliche Abstimmung, genau eine Stimme, Quorum, Rollen und unveränderliches Ergebnis. |
| E40 Jagdserie | Offen: manuelle Märsche und Monstersuche vorhanden | map_search ✓; army_receipts ✓ | Begrenzte serverseitige Serie, Stop jederzeit, erneute Ziel-/Bestandsprüfung pro Marsch. |
| E41 Ereignismarken | Offen: Ereignispreise und NPC-Shop vorhanden | — | Eigene Quellen, Konten, Limits/Verfall und Ereignisshop ohne Echtgeldkopplung. |
| E42 Zusatzausrüstung | Teilweise: ausrüstbare Relikte und Presets in TreasureService | — | Abgrenzung zum bestehenden Reliktsystem festlegen; Material-/Upgrade-/Wirkungsweg erst danach. |

## Kernspiel aus der Spezifikation

| Bereich | Nachweis und aktueller Abnahmestand |
|---|---|
| Anmeldung/Alpha-Schlüssel/Wiederherstellung/Admin | Auth-Verzeichnis, ApiGuard, AdminController; Sicherheitsbericht separat. Keine neue vollständige E-Mail-/Zielhostabnahme in diesem Teilbericht. |
| Bauen/Produktion | BuildingUpgrader/ResourceTick/BuildingPlotService; building_plots erfolgreich; mvp_rules nach kontrolliertem Kampfvergleich vollständig erfolgreich. |
| Ausbildung/Tiers/Forschung | TroopTrainer/ResearchProcessor/BuffEngine; training_costs, training_durations, training_unlocks, research_snapshot erfolgreich. |
| Märsche/Sammeln/Rückkehr | MarchDispatcher/MarchTick/GatherService; army_receipts und gathering_lifecycle erfolgreich. |
| Monster/Rally/PvP/Spähen | BattleEngine/MonsterRally/CityCombat; monster_rallies und combat_reports erfolgreich. Kampfpreview und Dungeon-Lifecycle im finalen Backendlauf erfolgreich; vollständige Mehrspielerabnahme bleibt gesondert. |
| Relikte/Charms/Inventar | TreasureService/CharmCollectionService/InventoryService; Charm, Belohnungen, Fragmentbilanz, Reliktsterne, Kapazitätsboni und Legacy-Besitz erfolgreich geprüft. |
| Allianz/Gemeinschaft | CommunityService, AllianceResearch/Help/Gift/Territory, Diplomacy; Post und Weltchat erfolgreich; vollständige Mehrspielerabnahme ausstehend. |
| Schrein/Kongress/Events | ShrineService/CongressService/EventService implementiert; full_progression nach Reliktkorrektur einschließlich der späteren Eventprüfungen vollständig bestanden. |
| Premium | ThemeBundleService mit PreviewPaymentGateway/UnavailablePaymentGateway; kein produktiver Zahlungsanbieter nachgewiesen, keine Echtgeldfreigabe. |
| Mehrwelt/Betrieb/PWA | WorldContext/WorldService, cron, service-worker.js; reale Dauerlast, Workerbetrieb und Geräteabnahme sind separate Releasegates. |

## Priorisierter konkreter Umsetzungsplan

1. **P0 – Freigabebaseline:** Die acht ersten Abbrüche sind durch dokumentierte Produkt- und Testkorrekturen aufgelöst und neu geprüft. Gesamt-App-Abnahme, reale Erstspielerrunde sowie Sicherheits-/Host-/Backup-Abnahme zusammenführen. Keine Funktionsmenge rechtfertigt eine Freigabe mit ungeklärten Geld-/Beute-/Zugriffsfehlern.
2. **P1 – Belohnungsfundament:** A01/A02 abschließen und E33 aufbauen. Migrationen für Quellenbelege/Instanzanker, Adapter für Solo/Rally/Dungeon/Feldzug, genau-einmal-Auszahlung, Adminsimulation und Wirtschaftsübersicht. Gate: jede aktive Quelle von Vorschau bis tatsächlicher Buchung rückverfolgbar; Parallelabschluss erzeugt keine zweite Beute.
3. **P1 – Einstieg und Finden:** E01/E02/E03/E06/E07, E04/E09/E10/E30/E32. Rückwärtsquellenindex vor Suchfiltern; Tutorial konsumiert echte Ereignisse; Locale und Moderation folgen jedem UI-Paket. Gate: neuer Spieler findet ein benötigtes Item und schließt Tutorial einschließlich Charm/Allianzhilfe ohne Admin ab.
4. **P2 – Allianzorganisation:** E05/E08/E11/E12/E13/E39, danach E15/E17 und E14. Gemeinsame Ereignisbelege aus P1 verwenden. Gate: Rollen, Kündigung/Wechsel, Zeitablauf, Retry und zwei gleichzeitige Teilnehmer getestet; kein Mitglied erhält fremde Belohnungen.
5. **P2 – PvE-Tiefe:** E18–E21/E24/E37/E38/E40/E41. Zuerst ein abgeschlossener Abenteuerbogen und ein Boss mit echter Sondermechanik, danach Gruppenfinder/Wellen/Raid/Serien. Gate: Teilnahme→Kampf→Abbruch oder Abschluss→Rückkehr→Belohnung auf Handy komplett spielbar.
6. **P3 – Inhalte und Ausbau:** E22/E25/E31/E34, dann E26/E42. Saisonruntime vor Inhaltseditor-Publish, Materialien vor Ausrüstung. Je Paket kleines MVP und eigene Abnahme statt gleichzeitiger Balanceänderungen.
7. **P4 – eigenständige Großpakete:** E27, E36, E28 nacheinander; E23/E35 zuletzt. Markt erst mit belastbarem Ledger und Betrugs-/Konkurrenztests; Weltenwettbewerb erst nach lokalem Turnier.
8. **Mobile Veröffentlichung nach Web-Stabilität:** gemeinsame Weboberfläche, PHP/MySQL als Server; E29 über kleine Geräteadapter. Zuerst Login/Session/Zurück/Netzwechsel/Retry als Capacitor-Durchstich auf Android und iPhone, dann Signierung, Push und Storeabnahme. Keine native Neuentwicklung der Spiellogik.

Die Reihenfolge definiert Abhängigkeiten, keine erfundenen Zeitschätzungen. Für eine geschlossene Alpha kann der Umfang bewusst auf die bewiesenen Kernszenarien begrenzt werden; die 44 angenommenen Pakete werden dadurch nicht stillschweigend gestrichen.
