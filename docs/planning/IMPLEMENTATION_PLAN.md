# Conquer – gemeinsamer Umsetzungs- und Agentenplan

Stand: 12. September 2026. Grundlage sind deine Annahme von A01/A02 und E01–E42, die Recherche einschließlich Kingshot und deine anschließende Korrektur zur Landentwicklung. Dieses Dokument hält Auftrag, Reihenfolge und den ersten erreichten Umsetzungsstand fest.

## Umsetzungsstand der ersten Grundlage

Die erste Grundlage für **A01 Monsterbeute**, **A02 Kartencharms** und **E16 Landentwicklung** ist umgesetzt. Dazu gehören versionierte globale und weltbezogene Beuteregeln, wiederholsichere Solo-/Rally-Abschlüsse, der garantierte Weltmonster-Charm mit Sammelmarsch, 8×8-Landteile mit Stufe 1–9, drei Kartenphasen, per Welt einstellbare Entwicklungsregeln, Landübersicht, Adminansichten und serverseitige Zielsperren.

Auf einer 256×256-Karte entstehen exakt 1.024 Landteile. Alle können Stufe 9 erreichen; nur die Startstufe folgt dem Gradienten Außen 1, Mitte 2–6, kleiner Kern 7–9. Bestehende Welten bleiben vollständig offen, neue Welten beginnen mit dem Außenbereich. Die vorhandene lokale Welt 1 wurde mit 1.024 Ländern initialisiert und blieb offen.

Die additive Migration `0083` bis `0087` wurde lokal erfolgreich über `php tools/migrate-land-charms.php --apply` ausgeführt. Die Bedienung, Schnittstellen, Defaults und Tests sind in [LAND_DEVELOPMENT.md](../LAND_DEVELOPMENT.md) beschrieben.

Dieser Stand schließt die übrigen Erweiterungen **E01–E42 nicht ab**. Von E33 „Beute- und Wirtschaftsstatistik“ sind Killbelege und die Reward-Revisionsgrundlage vorhanden; Aggregationen über reale Auszahlungen, Quellen und Verbrauch fehlen. Eine vollständige Template-Schlüssel-Registry, ein allgemeiner Draft/Preview/Publish-Ablauf aus E34 und Dungeon-Weltanker für Charms fehlen. Kristallspenden bleiben bis zur Produktentscheidung inaktiv.

## Deine Festlegungen

| Thema | Verbindlicher Auftrag |
|---|---|
| Gesamtumfang | Dropverwaltung A01, vollständiger Charm-Weg A02 und alle 42 Erweiterungen E01–E42. „E0–E42“ wird auf diese vorhandenen IDs bezogen; im ursprünglichen Katalog existiert kein gesondertes E00. |
| Landteile | Dauerhafte geografische Teilgebiete innerhalb einer Welt, jeweils Stufe 1–9. Sie sind weder die vier Biome noch Spielerreiche oder separate Welten. |
| Entwicklung | Spieler entwickeln das betroffene Land durch Monsterjagd, Ressourcensammeln und Spenden von Ressourcen oder Kristallen. |
| Entfernung | Bestimmt ausschließlich die Startstufe. Außen beginnt die Entwicklung auf Stufe 1, nach innen höher; ein kleiner Kern um das Zentrum beginnt auf Stufe 9. **Jedes Landteil kann später Stufe 9 erreichen.** |
| Kartenöffnung | Drei gemeinsame Phasen: Außenbereich → mittlerer Bereich → Zentrum. Entwicklungsziele und/oder verstrichene Wochen steuern die Freigabe; konkrete Zahlen und Verknüpfung sind noch auszugestalten. |
| Monster | Höher entwickelte Länder sollen stärkere Monster hervorbringen. Landstufe und Monsterstufe sind unterschiedliche Werte. |
| Drops | Die angenommene Droptabelle ist die Arbeitsgrundlage. Die Adminverwaltung umfasst sämtliche Monsterdefinitionen und unterscheidet verfügbare von vorbereiteten Inhalten. |
| Charms | Jedes besiegte Weltmonster hinterlässt genau einen Charm an seinem gespeicherten Spawnort. Truppen werden zum Einsammeln entsandt. Eine Rally erzeugt einen Charm für das besiegte Monster, keinen pro Teilnehmer. |
| Noch nicht aktiv | Deathkar, Magdar, grüner Drache, goldener Drache und roter Drache. Vorhandene Katalogdaten dürfen nicht mit aktiven Inhalten gleichgesetzt werden. |
| Agenten | Umsetzung durch dirigierte Agenten. SOL für komplexe Arbeit, LUNA für einfache abgegrenzte Aufgaben. **Keine ASTRA-Agenten.** |

Die frühere Empfehlung entfernungsabhängiger Höchststufen ist ausdrücklich verworfen.

## Einschätzung und empfohlene Spielregeln

Die Kombination ist sinnvoll: Die frühe Entwicklung findet außen statt und trägt zur gemeinsamen Erschließung der Welt bei. Später bleiben die dort aufgebauten Länder wertvoll, weil auch sie Stufe 9 erreichen können. Der Startvorteil innerer Länder schafft ein neues Ziel, ohne äußere Länder dauerhaft auf Anfängerniveau festzuhalten.

Ich empfehle die **Anzahl ausreichend entwickelter Länder als primäre Freigabebedingung**. Eine zusätzliche Mindestlaufzeit kann verhindern, dass eine sehr aktive Welt sämtliche Phasen sofort öffnet. Eine reine Zeitfreigabe oder ein automatisches „Ziel erreicht ODER Zeit abgelaufen“ bleibt eine konfigurierbare Alternative; sie würde jedoch das gemeinsame Entwicklungsziel abschwächen. Das ist eine Gestaltungsempfehlung, keine bereits von dir gewählte Regel.

| Kartenphase | Zugänglicher Bereich | Vorgeschlagene Freigaberegel |
|---|---|---|
| 1 | Außenbereich mit niedrigen Startstufen | Ab Weltstart offen. Neue Spieler werden hier angesiedelt. |
| 2 | Außenbereich und mittlerer Bereich | Mindestens X geeignete äußere Landteile erreichen Stufe Y; optional zusätzlich ein Mindestalter der Welt. |
| 3 | Ganze Karte einschließlich Zentrum | Mindestens X geeignete, bereits offene Landteile erreichen eine höhere Zielstufe; optional zusätzlich eine Mindestdauer seit Phase 2. |

X, Y, Kartenanteile, Wochenwerte und Entwicklungskosten werden im Adminbereich konfiguriert und vor Aktivierung auf Erreichbarkeit geprüft. Die Detailplanung enthält Startwerte für einen Testlauf, keine fest zugesagte Spielbalance. Ein Gate darf keine Entwicklung in noch gesperrten Ländern verlangen. Landteile werden eindeutig und nur einmal gezählt. Bei einer Freigabe bleibt der erreichte Zustand dauerhaft erhalten.

Weitere Empfehlungen für die erste Umsetzung:

- Nur bestätigte Ereignisse zählen: besiegte Monster, tatsächlich eingesammelte Rohstoffe und erfolgreich abgebuchte Spenden. Der Start oder Rückruf eines Marsches allein gibt keine Punkte. Eine Rally zählt ihren Kill einmal; persönliche Beiträge können daneben separat sichtbar sein.
- Jagd und Sammeln müssen ohne Spenden einen brauchbaren Entwicklungsweg bilden. Spendenkosten, Gewichtung und mögliche abnehmende Wirkung werden anhand von Testwelten und tatsächlichen Erträgen eingestellt.
- Landaufstiege beeinflussen neu erzeugte Monster. Bereits vorhandene Gegner, laufende Angriffe und zugesagte Belohnungen behalten ihren gespeicherten Stand.
- Die Auswahl stärkerer Monster verwendet vorhandene, aktive Familien und deren tatsächliche Stufen. Ein Landaufstieg aktiviert keine der fünf noch fehlenden Familien und erfindet keine Monsterwerte oberhalb des Katalogs.
- Ressourcenfelder besitzen derzeit eigene Stufen 1–3. Eine Landstufe 9 darf diese Grenze nicht versehentlich zu Ressourcenstufe 9 machen; mögliche Verbesserungen von Menge, Verteilung und Ertrag benötigen eigene Regeln.
- Auf stark entwickelten Karten muss ein Einstieg weiterhin möglich sein. Ein kleiner Anteil geeigneter leichter Jagdziele beziehungsweise ein geschützter Einführungsweg wird bei der Spawnkurve mitgeplant. Die Mehrzahl und die Obergrenze der Gegner wachsen mit der Landentwicklung.
- Freigaben und Landentwicklung gelten je Welt und nutzen deren tatsächliche Kartenmaße und Mittelpunkt. Sie sind nicht an eine feste 256er-Karte oder Welt 1 gebunden.

**Noch offene Währungsfrage:** „Kristalle“ können eine neue erspielbare Entwicklungswährung oder die bereits vorhandenen accountweiten Edelsteine bedeuten. Meine Empfehlung sind klar benannte, ausschließlich erspielbare und weltgebundene Entwicklungskristalle. Deine Antwort dazu steht noch aus. Jagd, Sammeln, Ressourcenspenden, Landstufen und Kartenphasen können unabhängig davon gebaut werden; der Kristall-Spendenweg wird bis zur Entscheidung nicht mit einer Währung verbunden.

## Aktueller Code als Ausgangspunkt

Die Beuteverwaltung verwendet `/admin/rewards` („Beute & Drops“), `RewardCatalog`, gespeicherte globale und weltbezogene Overrides sowie Regelrevisionen. Solo- und Rally-Kämpfe übernehmen beim Start einen unveränderlichen Beutestand. Gültige Itemreferenzen, aktive Monster und die garantierte Charm-Regel werden serverseitig geprüft. Die gemeinsame Vorschau berechnet Erwartungswerte für 100 Siege; die letzten 20 Revisionen sind im Admin sichtbar. Eine zweite konkurrierende Adminseite oder ein zweiter Belohnungskatalog würde die Regeln auseinanderlaufen lassen.

Der Weltmonster-Charm besitzt inzwischen einen vollständigen ersten Laufzeitweg: bestätigter Kill, Spawn am gespeicherten Punkt, Sammelmarsch, Konkurrenzentscheidung, Aktivierung, Ablauf und Truppenrückkehr. Noch offen sind die allgemeinere Template-Schlüssel-Registry, ein durchgängiger Draft/Preview/Publish-Ablauf für alle Inhaltsarten und ein fachlich gespeicherter Weltanker für Dungeoncharms.

Die 89 Monsterzeilen der ursprünglichen Recherche sind Definitionen, keine gemessene Zahl aktiver Monster. Bei der erneuten Prüfung enthält der Katalog inzwischen **99 Definitionen unter 14 Namen**, einschließlich zehn Grumwald-Stufen. Die vier regionalen Bossfamilien sind damit Grumwald, Frostgrimm, Sandmaul und Glutramm. Nach Abzug der 22 Definitionen der fünf von dir ausgeschlossenen Familien bleiben 77 Definitionen für die Prüfung der aktiven Auswahl; auch diese Zahl ist kein Livebestand. Der Itemkatalog enthält weiterhin 166 Definitionen. Die frühere Aussage fehlender Solo-Itemziehungen ist als historischer Stand markiert. Eine vollständige neue Abnahme aller Laufzeitpfade wurde in dieser Planungsrunde nicht durchgeführt.

## Reihenfolge der Umsetzung

Die großen späteren Erweiterungen bleiben im angenommenen Umfang. Sie erhalten jeweils einen vollständigen, begrenzten ersten Ausbaustand. Ein Platzhalter, eine leere Adminseite oder ein angelegtes Datenbankschema zählt nicht als erledigte Funktion.

Von Schritt 1 und 2 ist die oben beschriebene erste Grundlage geliefert. Die Tabelle beschreibt weiterhin die Zielreihenfolge; alle darüber hinausgehenden Inhalte bleiben offen.

| Schritt | Ergebnis | Wesentliche Pakete |
|---|---|---|
| 0 | Aktuellen Arbeitsstand sichern, gemeinsame Datenverträge festlegen, Dateieigentümer und nächste freie Migrationsnummern vergeben. | Grundlage für alle Pakete |
| 1 | Ein durchgängiger Beuteweg: Adminregel → Kampf → direkte Beute plus Charm am Spawnort → Sammelmarsch → einmalige Abholung und Bericht. | A01, A02, E33 |
| 2 | Welt in Landteile gliedern, Startgradient und Entwicklung einführen, Spawns an Landstufe binden und die drei Kartenfreigaben serverseitig durchsetzen. | E16, als frühes Fundament |
| 3 | Beute und Ziele finden, Einführungsweg abschließen und bestehende Allianzgeschenke zugänglich machen. | E01, E02, E03, E06, E07 |
| 4 | Kernregeln, Meilensteine, Kommunikation, Übersetzung und Allianzplanung ausbauen. | E04, E05, E08–E13, E29, E30, E32, E39 |
| 5 | Erkundung, Allianzgebiete und zusätzliche Bauplätze auf die neue Weltstruktur setzen. | E14, E15, E17 |
| 6 | Solo- und Gruppen-PvE vertiefen, Prüfungen, Wellen, Jagdserien und gezielte Belohnungen einführen. | E18–E21, E24, E37, E38, E40, E41 |
| 7 | Rezepte, Saisoninhalte, Rückkehreraufträge und einen versionierten Inhaltseditor ergänzen. | E22, E25, E31, E34 |
| 8 | Helden und zusätzliche Ausrüstung als zusammenhängenden Fortschrittszweig umsetzen. | E26, E42 |
| 9 | Begleiter, Bevölkerung/Versorgung und Spielerhandel jeweils als eigenes vollständiges Teilprojekt umsetzen. | E27, E36, E28 |
| 10 | Freiwillige Allianzturniere und anschließend weltübergreifende Wettbewerbe einführen. | E23, E35 |

**E16 hängt nicht von Allianzbesitz ab.** Auch ein freies Landteil entwickelt sich durch Spieleraktivität. E15 kann später Besitzrechte und lokale Allianzvorteile ergänzen, darf das gemeinsame Freigabesystem aber nicht ersetzen. Übersetzungen werden nach ihrem ersten Paket bei jeder folgenden Oberfläche mitgeführt.

Die ausführliche Matrix mit allen 44 IDs, Einzelumfang, Abhängigkeiten und Abnahmekriterien steht in [ROADMAP_AGENT.md](ROADMAP_AGENT.md).

## Agentensteuerung

Für diese Planung wurden drei SOL-Agenten mit eigenen, voneinander getrennten Dokumenten eingesetzt. Der Hauptagent gleicht ihre Vorschläge ab, entscheidet Routinefragen und führt widersprüchliche Verträge zusammen.

| Agent | Auftrag und Ergebnis |
|---|---|
| `land_progression_plan` · SOL | [Landstufen und Kartenphasen](LAND_PROGRESSION_AGENT.md): Geometrie, Entwicklung, Freigaben, Spawns, Migration und Prüfungen. |
| `loot_charms_plan` · SOL | [Drops und Charms](LOOT_CHARMS_AGENT.md): bestehende Adminbasis, Belohnungsverträge, garantierter Charm und Sammlung für alle Monster. |
| `roadmap_dependencies` · SOL | [Gesamt-Roadmap](ROADMAP_AGENT.md): alle 42 Erweiterungen plus A01/A02, Reihenfolge, Zuständigkeiten und Abnahme. |

Für die Umsetzung laufen höchstens drei Worker gleichzeitig. Transaktionen, Kampf, Weltzustand, Sicherheitsprüfungen und Integration gehen an SOL. LUNA kann danach feststehende Texte, Übersetzungen und geprüfte Katalogänderungen bearbeiten. ASTRA wird nicht eingesetzt.

### Erste Umsetzungswelle: Beute und Charm

Status: Die erste belastbare Grundlage dieser Welle ist umgesetzt. Die reale Beute- und Wirtschaftsstatistik bleibt in E33 offen; allgemeine Editor- und Templatefunktionen gehören zu E34.

| Rolle | Abgegrenzter Auftrag | Nachweis bei Übergabe |
|---|---|---|
| SOL: Belohnungsdomäne | Bestehenden RewardCatalog und Auszahlung vereinheitlichen; gültige Quellen, Revisionen, Inaktivstatus, atomare Belohnungsbelege. | Deterministische Tests für Solo/Rally und Vorschau, Aliasfälle und Wiederholungen. |
| SOL: Charm-Lebenszyklus | Garantierter Spawn nach bestätigtem Kill, geschützter Weltpunkt, Entsendung/Ankunft/Sammlung/Rückkehr, Verfall und Konkurrenz. | Genau ein Charm je Kill und genau eine erfolgreiche Sammlung auch bei gleichzeitiger Verarbeitung. |
| SOL: Admin und Spieloberfläche | Bestehende Dropseite vervollständigen; alle Definitionen und deren Status zeigen; Charm-Menü in die aktive Haupt-App einbinden. | Regeln lassen sich speichern und wieder anzeigen; vollständiger sichtbarer Spielweg auf Desktop, Handy und quer. |
| Koordination | Schnittstellen, Migrationen und gemeinsam berührte Kampf-/Routing-/Kartenstellen zusammenführen. | Gemeinsamer Integrationslauf einschließlich Weltwechsel, Altbestand und pausierter Welt. |

Ein Worker ändert keine Datei, die einem anderen gehört. Besonders `MarchTick`, `MonsterRally`, zentrale Handler, der Karten-Code und zentrale CSS-/Locale-Dateien werden pro Welle jeweils genau einem Besitzer zugeteilt. Andere Agenten liefern dafür Adapter oder konkrete Patchvorschläge. Vor Start wird die tatsächliche Pfadliste erneut aus dem Arbeitsbaum gelesen.

### Zweite Umsetzungswelle: Landentwicklung und Kartenöffnung

Status: Die E16-Grundlage einschließlich Oberfläche, Adminregeln, Beitrags-Hooks und Zielsperren ist umgesetzt. Die folgenden Punkte beschreiben den ursprünglichen Arbeitszuschnitt; Kristalle bleiben ausgenommen.

1. SOL implementiert Geometrie, Länderzustand, Startstufen, Beitragsbelege und atomare Entwicklung. Neue Kristalle bleiben ein austauschbarer, zunächst inaktiver Spendenadapter.
2. SOL implementiert Phasenregeln, Freigabeprüfung, Spawnintegration und schonende Migration vorhandener Welten. Dieser Agent besitzt die betroffenen Welt- und Platzierungsdienste.
3. SOL implementiert Landinformationen auf der Weltkarte, Fortschrittsanzeige, Spendenformular und Adminregeln. Serverantworten liefern konkrete Sperrgründe und den weltweiten Fortschritt zur nächsten Öffnung.
4. Die Koordination integriert Jagd- und Sammelereignisse aus Welle 1, verhindert doppelte Fortschrittsbuchung und prüft neue und bestehende Welten.

Die weiteren Wellen werden jeweils aus der vollständigen Roadmap als begrenzte Arbeitsaufträge verteilt. Jeder Auftrag nennt Dateien, Schnittstellen, erwartetes Verhalten, Prüfungen und Abschlusskriterien. Ein Agent darf ein eigenes Folgefeature nicht ungeplant mit verändern.

## Verbindliche Integrationsregeln

- **Gemeinsame Regelquelle:** Admin, Beutevorschau, Suche, tatsächliche Auszahlung und Statistik müssen denselben wirksamen Regelstand verwenden. Ein gespeicherter Auftrag bekommt nicht nachträglich andere Chancen.
- **Weltbezug:** Neue Länder, Spenden, Charm-Objekte und Kartenphasen tragen die gespeicherte Welt. Ein zwischenzeitlicher Weltwechsel in einem anderen Tab darf den Auftrag nicht umleiten.
- **Einmalige Buchung:** Kill, Beute, Charm, Sammlung, Landpunkte und Freigabe besitzen fachliche Belege. Wiederholte Requests und Cronläufe dürfen keinen zweiten Effekt erzeugen.
- **Serverseitige Sperren:** Geschlossene Bereiche können nicht durch direkte Requests, Zielsuche, Teleport, neue Städte, Spawns, Märsche oder Rally-Aktionen umgangen werden. Die sichtbare Kartensperre ist nur die Darstellung dieser Regel.
- **Reisen zwischen offenen Ländern:** Für den ersten Ausbau gilt die Sperre für Ziele und Aktionen innerhalb geschlossener Gebiete. Die bisher abstrakte Reisestrecke zwischen zwei offenen Zielen wird nicht allein deshalb abgelehnt, weil ihre gezeichnete Gerade eine geschlossene Zone schneidet. Eine spätere physische Wegsperre benötigt zuerst erreichbare Umwege, gespeicherte Wegpunkte und dazu passende Reisezeiten.
- **Bestandswelten:** Neue Regeln werden additiv migriert. Keine Stadt wird versetzt, keine Armee gelöscht und kein vorhandenes Ziel nachträglich stärker. Für bereits vollständig bespielte Welten bleibt die Karte zunächst offen; der gestufte Start ist für neue Welten direkt nutzbar. Eine spätere Umstellung vorhandener Welten braucht einen konkreten, verlustfreien Migrationsplan.
- **Charm-Koordinate:** Der belegte Punkt bleibt für die Lebensdauer des Charms vor erneutem Spawn oder widersprechender Platzierung geschützt. Instanzgegner brauchen einen ausdrücklich gespeicherten, erreichbaren Weltanker; eine nicht existierende Koordinate darf nicht erfunden werden. Die Detailplanung behandelt diesen Erweiterungsfall getrennt vom ersten Weltmonster-Lebenszyklus.
- **Admin:** Chancen, Mengen, gültige Item-IDs, Aktivierungsstatus, Landkurven und Freigabeziele werden serverseitig validiert. „Genau ein Charm“ ist für aktive Monster keine auf null reduzierbare Dropchance. Die Konfiguration zeigt ihre Reichweite, Revision und tatsächliche Wirkung.
- **Arbeitsbaum:** Bestehende lokale Änderungen bleiben erhalten. Kein pauschales Zurücksetzen, Löschen oder Übernehmen ganzer Dateien aus einem konkurrierenden Arbeitsstand. Migrationsnummern werden erst beim jeweiligen Start reserviert.
- **Oberfläche:** [UI_STYLE_GUIDE.md](../UI_STYLE_GUIDE.md) gilt für alle Menüs und das Backoffice; [ART_DIRECTION.md](../ART_DIRECTION.md) für sichtbare Stadt-/Weltänderungen. Die vorhandene Dorfgestaltung und `--ui-*`-Variablen bleiben verbindlich.

## Abnahme der ersten beiden Wellen

| Fall | Erwartetes Ergebnis |
|---|---|
| Jede aktive Monsterfamilie, Solo und Rally | Gültige direkte Beute; genau ein Charm am gespeicherten Punkt. |
| Zwei Ticks oder zwei Sammler gleichzeitig | Ein Killabschluss und ein Sammelgewinner; keine doppelte Beute und keine verlorenen Truppen. |
| Adminregel während eines laufenden Auftrags geändert | Bestehender Auftrag behält seinen Regelstand; Vorschau neuer Aufträge verwendet die neue Revision. |
| Weltwechsel, fremde IDs, pausierte Welt | Kein Übertrag auf die falsche Welt; unzulässige neue Aktionen werden abgewiesen, Rückkehr korrekt abgeschlossen. |
| Landentwicklung an Kartenrand und Zentrum | Startgradient stimmt; jedes Land erreicht durch Entwicklung Stufe 9. |
| Jagd, Sammeln, Spende und Retry | Nur tatsächliche Leistung zählt, genau einmal und für das richtige Land. |
| Landaufstieg während eines Angriffs | Bestehendes Ziel bleibt unverändert; neue Spawns nutzen die neue Landstufe. |
| Kartenfreigabe bei gleichzeitigem Erreichen des Ziels | Genau ein dauerhafter Phasenwechsel mit nachvollziehbarem Beleg. |
| Gesperrte Zone über alle relevanten Endpunkte | Keine Umgehung; klare Sperrinformation mit Fortschritt zum Freigabeziel. |
| Alte Welt mit Städten, Feldern und Märschen | Keine verschobenen Objekte, verlorenen Guthaben oder blockierten Rückreisen; Migration erneut ausführbar. |
| Desktop, Handy, Querformat und 3D | Bedienbare Drop-/Land-/Charm-Ansichten; keine Browserfehler, doppelten HUDs oder verdeckten Aktionen. |

Schreibende Tests verwenden isolierte Testdatenbanken und Fixtures. Zunächst laufen die spezifischen Prüfungen, danach die tatsächlich betroffenen bestehenden Integrations- und Browserprüfungen. Eine reine Dokumentationsänderung erfordert keinen kompletten Spiellauf.

## Status dieser Runde

Umgesetzt und isoliert geprüft sind die erste Beute-/Charm-Grundlage A01/A02 sowie E16 als frühes Weltfundament. Die lokale Datenbankmigration `0083` bis `0087` ist erfolgt; Welt 1 besitzt 1.024 initialisierte Länder und behält ihre geöffneten Zonen. Die spezifischen Land-, Karten-, Reward-, Rally-, Charm- und Mehrwelttests laufen gegen Wegwerf-Datenbanken.

Die Reihenfolge oben bleibt der Arbeitsplan für die noch offenen Pakete. E01–E15 und E17–E42 sind dadurch nicht pauschal erledigt. E33 ist nur durch Killbelege und die gemeinsame Reward-Revisionsgrundlage begonnen; reale Auszahlungs-, Quellen- und Verbrauchsstatistiken fehlen. Die noch fehlenden Teile und Betriebsbefehle stehen kompakt in [LAND_DEVELOPMENT.md](../LAND_DEVELOPMENT.md).

Die ID-Matrix enthält A01/A02 und E01–E42 genau einmal, insgesamt 44 Pakete. Die aktuellen Katalogzahlen 99 Monsterdefinitionen, 14 Namen und 166 Items bleiben Planungswerte; Definitionen sind nicht mit dem aktiven Laufzeitbestand gleichzusetzen.
