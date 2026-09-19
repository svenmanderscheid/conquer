# Conquer – Agenten-Roadmap für A01/A02 und E01–E42

Stand: 12. September 2026. Dieses Dokument plant die angenommenen Pakete **A01/A02** und **E01–E42**. Es ist kein Fertigstellungsbericht. Der aktuelle Arbeitsbaum ist stark verändert und enthält viele noch nicht eingecheckte Dateien; jede Umsetzungswelle beginnt deshalb mit einem neuen Bestandsabgleich und arbeitet ohne Aufräumen, Zurücksetzen oder Überschreiben fremder Änderungen.

## Verbindliche Leitplanken

- Höchstens vier aktive Agenten: ein koordinierender Hauptagent und drei Worker. **Kein ASTRA.** Für komplexe Domänen-, Transaktions-, Kampf-, Karten- und Integrationsarbeit wird **SOL** empfohlen. **LUNA** ist nur für einfache, eng abgegrenzte Dokumentations-, i18n- und Katalogdatenarbeit geeignet. Der Koordinator bleibt für Verträge, Migrationsreihenfolge, Integrationen und Abnahme verantwortlich.
- Vor sichtbaren Änderungen an Menüs, Dialogen, HUD, Karten-Overlays oder Backoffice ist `docs/UI_STYLE_GUIDE.md` vollständig zu lesen. Vor sichtbaren Änderungen an Stadt, Welt, Gebäuden, Figuren oder Dekoration gilt zusätzlich `docs/ART_DIRECTION.md`.
- Die bestehende Grundlage von A01 ist zu erhalten und zu prüfen: `/admin/rewards`, `src/Game/Rewards/RewardCatalog.php`, `src/Admin/RewardEditor.php`, `migrations/0083_reward_overrides.sql` und die bereits angeschlossene Solo-Item-/Edelsteinauswertung in `MarchTick`. A01 ist damit begonnen, aber noch nicht als vollständig abgenommen anzusehen.
- Jede neue persistente Entität ist weltgebunden, sofern sie nicht ausdrücklich accountweit ist. Hintergrundjobs rechnen mit dem gespeicherten `world_id`, sind wiederholbar sicher und stellen einen zuvor gebundenen Weltkontext wieder her.
- Migrationen sind ausschließlich additiv und vorwärtskompatibel. Keine produktiven Tabellen leeren, keine Bestandsmigration still korrigieren und keine bestehende Migrationsnummer wiederverwenden.
- Vorschau, Auszahlung und Statistik lesen dieselbe versionierte Regel. Ein laufender Auftrag behält die beim Start gespeicherte Regelversion. Schreibaktionen verwenden Transaktionen, Zeilensperren und eine fachliche Idempotenz-ID.
- Der aktuelle Monsterkatalog enthält **99 Definitionen unter 14 Namen**, darunter zehn Grumwald-Definitionen. Deathkar, Magdar sowie grüner, roter und goldener Drache bleiben **Katalogeinträge**. Sie werden in dieser Roadmap nicht gespawnt und nicht als aktiver Inhalt beworben. Ihre Definitionen dürfen validiert, angezeigt und für eine spätere Aktivierung vorbereitet werden. **Grumwald, Frostgrimm, Sandmaul und Glutramm** sind davon getrennt als die vier vorhandenen regionalen Bossfamilien zu behandeln.
- Eine Phase ist erst abgeschlossen, wenn ihre Abnahmegates erfüllt sind. Ein klickbarer Platzhalter, eine statische Vorschau oder ein Schema ohne spielbaren Lebenszyklus zählt nicht als fertige Funktion.

## Festlegung und offene Produktentscheidungen vor der Regionalphase

Die erste Regel ist verbindlich festgelegt. Die beiden übrigen Fragen bleiben bis zur Entscheidung konfigurierbar und dürfen nicht durch einen Agenten still festgelegt werden.

1. **Entfernung und Landstufe – festgelegt.** Entfernung bestimmt nur den **Startlevel-Gradienten**: außen niedrige Landstufen, nach innen höhere Startstufen; nur eine kleine zentrale Startfläche beginnt auf Stufe 9. Jedes Landteil kann durch Beiträge letztlich Stufe 9 erreichen; es gibt keine entfernungsabhängige Maximalstufe. Die Weltkarte wird gemeinsam in drei Freigabephasen **Außenbereich → mittlerer Bereich → Zentrum** geöffnet.
2. **Kristalle.** Empfehlung: eine neue, ausschließlich erspielbare und weltgebundene Währung „Entwicklungskristalle“ mit eigenen Quellen, Senken, Limits, Migration und eindeutigem UI-Begriff. Sie darf nicht mit den bestehenden accountweiten Edelsteinen/Gems gleichgesetzt oder aus ihnen gekauft werden. Bis zur Nutzerentscheidung bleiben Jagd-, Sammel- und Ressourcenspenden für E16 implementierbar; der Kristalladapter bleibt deaktiviert und die UI verspricht diese Beitragsquelle noch nicht.
3. **Instanzgegner und A02 – technischer Umsetzungsvorschlag.** Jeder Dungeon-/Abenteuerlauf speichert einen erreichbaren Weltanker, in der Regel den Eingang. Ein Charm aus einer Instanz entsteht an diesem gespeicherten Begegnungsanker. Falls ein späteres Inhaltsdesign einen individuellen Weltpunkt pro Gegner vorsieht, kann es diesen Anker verfeinern. `instance_only` bleibt bis zu einem erreichbaren Anker ehrlich gekennzeichnet; dies ist keine zusätzliche Produktentscheidung, die Phase 1 blockieren soll.

## Phasen und Abhängigkeiten

Die Reihenfolge ist eine Abhängigkeitsfolge, keine Terminzusage. Innerhalb einer Phase dürfen bis zu drei unabhängige Arbeitspakete parallel laufen. Große Produktzweige erhalten einen eigenen begrenzten MVP und werden nicht zwischen kleinen Aufgaben versteckt.

### Phase 0 – Baseline und Verträge

Keine neue Feature-ID wird hier als erledigt markiert. Der Koordinator inventarisiert den aktuellen Stand, hält die geänderten Dateien fest, reserviert die nächsten freien Migrationsnummern und schreibt die gemeinsamen Verträge für Belohnungsquelle, Regelrevision, Weltbezug, Währung, Ereignisbeleg und Kartenanker. Außerdem werden Katalogstatus `active`, `catalog_only` und `instance_only` verbindlich definiert.

**Gate P0:** Syntax- und bestehende Smoke-Tests laufen auf dem unveränderten Stand; bekannte Ausgangsfehler sind protokolliert. Der festgelegte Startgradient und die Kartenphasen stehen im Vertrag; der noch offene Kristalladapter bleibt deaktiviert. Es gibt eine exklusive Eigentümerliste für gemeinsam genutzte Dateien.

### Phase 1 – Belohnungs- und Charm-Fundament

Enthält **A01, A02 und E33**.

- **A01 Monster-Dropverwaltung:** Die vorhandene RewardCatalog-/RewardEditor-Grundlage wird vollständig inventarisiert. Solo, Goblin-Sonderweg, Rally, Dungeon und Feldzug werden auf einen versionierten Belohnungsvertrag umgestellt. Alt-Itemcodes erscheinen als behebbare Fehlerliste; es gibt keine stillen Ersatzitems. Vorschau, Simulation ohne Gutschrift und reale Auszahlung verwenden dieselbe Regelrevision. Katalog-only-Gegner sind sichtbar, aber klar als inaktiv markiert.
- **A02 Charm bei jedem Monster:** Jeder erfolgreiche, aktive Monster-Kill erzeugt genau einen einmalig einsammelbaren Charm an seinem gespeicherten Weltpunkt. Solo- und Rally-Abrechnung, gleichzeitige Ticks, Verfall, konkurrierende Sammler, Rückruf und Welttrennung sind abgedeckt. Der Boss erzeugt einen Charm pro besiegtem Boss, nicht pro Rally-Teilnehmer. Catalog-only-Einträge erzeugen nichts, weil sie keinen aktiven Killpfad haben. Instanzgegner folgen dem technischen Weltanker-Vorschlag oben.
- **E33 Beute- und Wirtschaftsstatistik:** Unveränderliche Auszahlungsbelege zeichnen Quelle, Regelrevision, Welt, Empfänger, Brutto-/Nettoertrag und Idempotenzschlüssel auf. Das Admin-Dashboard aggregiert reale Belege, nicht erwartete Katalogwerte; personenbezogene Drill-downs bleiben berechtigt und protokolliert.

**Gate P1:** Für jede aktive Monsterfamilie stimmen Vorschau und Auszahlung; jede referenzierte Item-ID ist gültig. Wiederholte Solo-/Rally-/Cron-Abrechnung verdoppelt weder Loot noch Charm. Ein Test weist exakt einen Charm am gespeicherten Punkt und eine einmalige Sammlung nach. Die Statistik lässt sich bis zum Auszahlungsbeleg zurückverfolgen. Deathkar, Magdar und alle drei Drachen werden nirgends aktiv gespawnt.

### Phase 1R – Öffentliches Landfundament

Enthält **E16**. Abhängigkeit: P1, insbesondere E33-Belege. E16 hängt nicht von Allianzgebiet E15 ab; Landteile sind öffentliche, weltweite Entwicklung. Die späteren E14-/E15-Systeme lesen E16-Freigaben und Landstufen.

- **E16 regionale Entwicklung:** Jedes Landteil besitzt eine tatsächliche Stufe **1–9**. Beim Erstellen oder Migrieren einer Welt bildet die Entfernung einen Startlevel-Gradienten: außen niedrig, zur Mitte höher; nur die kleine zentrale Startfläche beginnt auf Stufe 9. Danach kann **jedes** Landteil durch Beiträge bis Stufe 9 entwickelt werden. Jagden, Sammelertrag und eingezahlte Standardressourcen geben gedeckelte, nachvollziehbare Regionalpunkte. Entwicklungskristalle werden nur nach der offenen Währungsentscheidung über einen getrennten Adapter aktiviert. Die Karte wird für die ganze Welt in drei Phasen geöffnet: zuerst der Außenbereich, danach der mittlere Bereich, zuletzt das Zentrum. Serverseitige Freigabeprüfungen gelten für Spawn, Platzierung, Erkundung, Zielsuche, neue Märsche, Rally-Erstellung/-Beitritt, Teleport und sonstige Zielaktionen; Hintergrundabwicklung führt bereits zulässige Rückreisen sicher zu Ende. Bestehende Welten erhalten durch eine additive, idempotente Migration ihre Landteile, Startstufen und Phase, ohne Städte oder laufende Ziele zu verschieben. Migrationsvorschlag: Ihre Anfangsphase ist mindestens die kleinste Phase, die alle bestehenden Städte und aktiven Ziel-/Marschkoordinaten umfasst; falls dadurch das Zentrum nötig ist, startet diese Bestandswelt sicher in Phase 3. Stufenaufstieg schaltet Infrastruktur und passend schwere aktive Inhalte frei. Punkte, Gebietsstufe und Freischaltungen sind weltgebunden; Abzug/Verfall erfolgt nur nach einer später ausdrücklich angenommenen Regel.

  **Vorschlag für die Phasengates:** Das primäre Gate ist ein sichtbares Entwicklungsziel, etwa mindestens `X` freigegebene Landteile auf Stufe `Y`. Eine optionale Mindestlaufzeit in Weltwochen kann verhindern, dass eine starke Startgruppe den Inhalt zu schnell öffnet. Ein reiner Zeitablauf als `ODER`-Fallback wäre nur eine alternative, noch offene Regel für schwach bevölkerte Welten, weil er das Entwicklungsziel sonst aushebelt. Die konkreten `X/Y`-Werte, die Mindestlaufzeit und ein möglicher administrativer Ausnahmeprozess werden vor Implementierung als Balancekonfiguration festgelegt.

**Gate P1R:** Für jede Welt ist die komplette Landkarte deterministisch und lückenlos. Automatisierte Tests belegen: niedriger Start außen, steigender Startgradient zur Mitte, kleine zentrale Startfläche auf Stufe 9, aber Entwicklung **jedes** Landteils bis Stufe 9; genau einmal gezählte Jagd-/Sammel-/Ressourcenbeiträge; korrekte Entwicklungsziele und eine gegebenenfalls konfigurierte Mindestlaufzeit für alle drei Kartenphasen. In gesperrte Bereiche gelangen weder Spawns, neue Platzierungen, Suche, Märsche, Rally-Aktionen noch Teleports; zulässige Rückreisen bleiben möglich. Eine bestehende Welt wird ohne Versetzen vorhandener Objekte idempotent migriert und keine Stadt, Armee oder aktive Zielkoordinate wird durch die initiale Phase abgeschnitten. Der Kristallweg wird nur geprüft, wenn die Währungsentscheidung gefallen und sein Adapter aktiviert ist.

### Phase 2 – Finden, Verstehen und Einstieg

Enthält **E01, E02, E03, E06 und E07**. Abhängigkeit: P1R.

- **E01 Bestiarium:** Durchsuchbare Katalogansicht mit Familie, wirksamer Stufe, Region, Status, Fundort, Kampfart und echter RewardCatalog-Vorschau. Katalog-only ist deutlich erkennbar.
- **E02 Itemsuche:** Umgekehrter Index vom gültigen Item/Fragment zu allen aktiven Quellen und ihren Bedingungen. Truhen, Shops, Dungeons, Feldzüge und Monster verwenden Quelllinks; inaktive Katalogquellen werden getrennt ausgewiesen und nicht als farmbar bezeichnet.
- **E03 Einführung:** Persistente, wiederaufnehmbare Schritte Bau, Ausbildung, erster Kampf, Charm-Sammelmarsch und Allianzhilfe. Der Fortschritt entsteht aus echten Domänenereignissen und kann durch Neuladen oder Wiederholung keine Belohnung duplizieren.
- **E06 Allianzgeschenke:** Vorhandene Jagdgeschenke werden sichtbar, weltgebunden, einmalig und transaktional abholbar. Verlauf und Ablaufdatum sind verständlich.
- **E07 weltweite Monstersuche:** Serverseitige Suche über die ganze ausgewählte Welt nach aktiver Familie, Stufe, Biom/Region und tatsächlich möglichem Drop. Ergebnisse prüfen Sichtbarkeit, Ablauf und Spawnstatus; catalog-only erscheint nur im Bestiarium.

**Gate P2:** Ein neuer Testspieler kann die Einführung ohne Adminhilfe beenden. Von einem Item gelangt man zu einer aktiven Quelle und von einem Suchergebnis zum korrekten Kartenziel. Alte Alias-IDs zeigen dieselbe wirksame Stufe in Suche, Bestiarium und Kampf. Geschenkabholung und Einführung sind bei Parallelaufrufen genau einmal wirksam.

### Phase 3 – Verständliche Kernregeln und Erreichbarkeit

Enthält **E04, E09, E10, E29, E30, E32 und E39**. Abhängigkeiten: E03 für kontextuelle Hinweise; P1 für Belohnungen.

- **E04 Hospital:** Gebäude- und Forschungsstufe bestimmen eine sichtbare Kapazität und Heilwirkung. Balancingvorschlag für den MVP: Verwundete bis zur Kapazität gehen ins Hospital; ein Überlauf und seine Folgen werden vor einem riskanten Kampf prognostiziert. Ob Überlauf zu Toten, einer langsameren Reservebehandlung oder einer anderen Sanktion führt, bleibt vor Implementierung zu entscheiden und darf nicht ungefragt fest codiert werden.
- **E09 Lord-Levelbelohnungen:** Ein endlicher Meilensteinkatalog mit einmaligen Belegen und sichtbarer nächster Belohnung; keine rückwirkende Mehrfachgutschrift.
- **E10 VIP-/Truhen-Meilensteine:** Freischaltbedingungen, kostenlose Truhen und Resetzeiten werden aus einem gemeinsamen Vertrag angezeigt und serverseitig geprüft.
- **E29 Benachrichtigungen:** Opt-in je Kategorie für fertige Aufträge, Gruppenstarts und Angriffe. MVP umfasst gespeicherte Präferenzen, PWA-Push bei erteilter Browserberechtigung, Deduplizierung und Deep Link; kein Zwangs-Opt-in.
- **E30 Übersetzung:** Alle aktuell erreichbaren Regeln, Berichte, Items und Hilfen erhalten Schlüssel und vollständige DE/FR/LU-Texte. Agenten dürfen mit LUNA Katalog- und Übersetzungsdateien bearbeiten, aber ein Mensch prüft spielprägende Terminologie.
- **E32 Kontakte/Blockieren/Melden:** Weltgebundene Kontaktliste, Blockwirkung in Chat/Einladungen, Meldung mit unverändertem Beleg und moderierbarer Statusfolge. Blockieren ändert keine bereits laufenden Kämpfe.
- **E39 Allianzabstimmungen:** Leitung/Offiziere erstellen endliche Umfragen mit Optionen und Frist; Mitglieder stimmen einmal ab, können innerhalb der Frist nach klarer Regel ändern und sehen abgeschlossene Ergebnisse. Das Ergebnis führt keine Spielaktion automatisch aus.

**Gate P3:** Grenzfälle des Hospitals, rückwirkende Meilensteine, Push-Deduplizierung, Blockwirkung und Abstimmungsberechtigung sind mit isolierten Fixtures geprüft. Die i18n-Prüfung findet auf allen erreichbaren neuen Oberflächen keine Rohschlüssel; Desktop, Handy und Querformat bestehen die UI-Abnahme.

### Phase 4 – Allianzplanung und Allianzökonomie

Enthält **E05, E08, E11, E12 und E13**. Abhängigkeiten: E06, E33 und E39; E41 teilt später nur den Shopkern, nicht die Währung.

- **E05 Allianz-Münzen und -Shop:** Persönliche, weltgebundene Münzen entstehen aus bestätigter Hilfe, gedeckelten Spenden und Gruppenaufgaben. Ein rotierender Shop besitzt Kaufbelege, persönliche Limits und keine Handelbarkeit der Münzen.
- **E08 Wochenaufgaben:** Persönliche Wochenziele mit UTC-Zeitraum, Ereignisbelegen, Fortschrittsobergrenze und einmaliger Abholung.
- **E11 Allianz-Auftragsbrett:** Ein kleiner Katalog für Versorgung, Jagd und Dungeons; Beiträge stammen aus denselben fachlichen Ereignissen wie E08, werden aber separat gedeckelt. MVP umfasst Erstellen/Rotation, gemeinsamen Fortschritt und persönliche Beitragssicht.
- **E12 Allianzkalender:** Ereignisse mit mehreren vorgeschlagenen Zeitfenstern, Abstimmung/Anmeldung, Rollen, Teilnehmern und Ersatzbank. MVP verschickt Erinnerungen, startet aber Kämpfe nicht automatisch.
- **E13 Markierungen/Favoriten:** Persönliche Marker und rollenberechtigte Allianzmarker mit Notiz, Ablaufdatum, Kartenfokus und Schutz gegen verborgene/abgelaufene Ziele.

**Gate P4:** Dasselbe Ereignis wird in persönlichen und Allianzaufgaben nur nach den festgelegten Regeln gezählt; Retries erzeugen keine zusätzlichen Münzen. Shopkäufe sind atomar. Kalenderzeiten stimmen in mindestens zwei Zeitzonen, Marker lecken nicht zwischen Welten/Allianzen, und alle Rollenrechte sind geprüft.

### Phase 5 – Erkundung, Allianzgebiet und erweiterte Stadt

Enthält **E14, E15 und E17**. Abhängigkeiten: E07, E11, E13 und das abgeschlossene öffentliche Landfundament E16/P1R. Die Kristallentscheidung blockiert diese Phase nicht, solange der Adapter deaktiviert bleibt.

- **E14 Wachturm/Erkundung:** Der ausbaubare Wachturm bestimmt Reichweite oder Expeditionsplätze. Erkundungsmärsche entdecken und speichern Ruinen/Regionen nur in durch E16 freigegebenen Kartenphasen; Erstentdeckungsbelohnungen sind einmalig. Vollständige Echtzeit-Nebel-Simulation gehört nicht zum MVP.
- **E15 Allianzgebiet/Außenposten:** Ein Hauptquartier und verbundene Außenposten erzeugen zusammenhängende Gebiete innerhalb der durch E16 freigegebenen öffentlichen Landteile. Platzierung, Baukosten, Beitragsbelege, Abbruch und lokale Boni werden serverseitig geprüft. MVP begrenzt Gebäudetypen und Gebietseffekte bewusst.
- **E17 zusätzliche Bauplätze:** Die frühere Deaktivierung ist durch die Annahme aller Vorschläge überholt. Die reaktivierten Außenbauplätze erhalten ein nützliches MVP mit wenigen spezialisierten Produktionsgebäuden und/oder Kasernen, echten Bauvoraussetzungen, Warteschlange, Produktion, Auswahl und 3D-Modell. Alte Restaufträge werden migriert oder eindeutig abgeschlossen; sie dürfen nicht doppelt wirken. Die Phase erweitert die Stadt breit genug für eine spielbare Wahl, aktiviert aber nicht ungeprüft jede historische Plot-Idee.

**Gate P5:** Erkundung und Allianzplatzierung respektieren in jedem API-, Service- und Hintergrundpfad die E16-Freigaben. Gebietsbauten überlappen keine Städte, Schreine, Wege oder Sperrflächen. E17 produziert und trainiert korrekt, respektiert zwei Bauplätze/VIP-Regeln und besteht die 3D-Gesamt-, Nah-, Mobile-, Querformat- und `/city#city`-Prüfung.

### Phase 6 – PvE-Vertiefung

Enthält **E18, E19, E20, E21, E24, E37, E38, E40 und E41**. Abhängigkeiten: P1, E12, E16; E20 zusätzlich vorhandene Dungeon-Gruppen; E41 zusätzlich E05-Shopmuster.

- **E18 Einzelspieler-Abenteuer:** Ein endlicher kleiner Handlungsbogen aus Erkundungsorten, Missionen und Miniboss mit gespeichertem Fortschritt, Weltanker und einmaligen Abschlussbelohnungen. Ein Content-Editor oder beliebig viele Kapitel gehören nicht zum MVP.
- **E19 Bossmechaniken:** Zunächst erhalten die vier aktiven regionalen Bossfamilien Grumwald, Frostgrimm, Sandmaul und Glutramm je eine lesbare Schwäche, eine Nebenaufgabe und mindestens zwei serverseitig aufgelöste Phasen. Telegraphie, Kampfreport und Belohnung erklären die Mechanik; bloße HP-Erhöhung erfüllt das Paket nicht.
- **E20 Dungeon-Gruppensuche:** Offene, filterbare Gruppen nach Dungeon, Schwierigkeit und gewünschter Rolle; Beitritt validiert Welt, Kapazität, Armee und laufenden Status. MVP umfasst Lebenszyklus und Ablauf, keinen globalen Chatersatz.
- **E21 gezielte Fragmentwahl:** Durch klar begrenzte Auswahlbelege kann ein Spieler nach einer passenden Aktivität eines aus einem kleinen, gültigen Fragmentset wählen. Die Auswahl geschieht serverseitig genau einmal.
- **E24 PvE-Wettbewerbe:** Saisonale Boss-/Dungeonwertung mit vorher veröffentlichten Regeln, begrenzten wertbaren Versuchen, eigener Rangliste und Belegprüfung. MVP startet mit einem PvE-Modus pro Saison.
- **E37 Allianz-Abwehrwellen:** Ein im Kalender angemeldetes Ereignis greift Mitgliedsstädte in Wellen an. Reale Verstärkungen, Verteidigung, persönliche Beiträge, Niederlage/Rückkehr und Belohnungen werden transaktional abgewickelt. Außerhalb des Ereignisses ändert es keine Stadt.
- **E38 PvE-Prüfungspfad:** Endliche Stufen mit einmaliger Erstabschlussbeute, Meilensteinen und erst nach manueller Bewältigung freigeschalteter verkürzter Wiederholung. Raid verbraucht die gleichen Eintrittskosten und erzeugt einen prüfbaren Beleg.
- **E40 begrenzte Jagdserien:** Der Spieler setzt Familie/Stufenbereich, Höchstzahl und AP-Budget. Jeder Folgeauftrag prüft aktuelle Truppen, AP, Ziel und Schutz erneut; Stopgründe sind sichtbar. Charms werden nicht automatisch gesammelt.
- **E41 Ereignismarken/-shop:** Eigene, eindeutig benannte Ereignismarken aus bestätigter Teilnahme/Erfolg, mit sichtbaren Limits und expliziter Verfallsregel. Der Shop bietet planbare Items, Fragmente und Kosmetik mit atomaren Käufen.

**Gate P6:** Alle neuen Kampfläufe sind wiederholbar sicher, in Berichten erklärbar und bei Abbruch/Timeout vollständig rückführbar. Rankings lassen sich aus Belegen neu berechnen. Autojagd stoppt an jeder Grenze. Kein neuer Gegner wird aktiviert, bevor RewardCatalog, Charm-Ort und Bestiariumseintrag vollständig sind.

### Phase 7 – Inhaltsbetrieb, Handwerk und Rückkehr

Enthält **E22, E25, E31 und E34**. Abhängigkeiten: E02, E21, E24, E33 und E41. Die Reihenfolge innerhalb der Phase löst die Editor-/Content-Abhängigkeit: zuerst liefert E34 nur Schema, Validator und versionierten Event-Runtime-Vertrag; darauf wird E25 als erster realer Inhalt gebaut; anschließend wird E34 mit dem vollständigen Admineditor abgeschlossen.

- **E22 Handwerk/Rezepte:** Kleines MVP mit wenigen Monster-Materialien, Rezepten und relevanten Zielitems. Herstellung reserviert Material, hat klare Kosten/Zeit und bucht bei Retry genau einmal. Materialien erhalten echte Quellen und Senken.
- **E25 saisonale Geschichten/Kosmetik:** Auf dem vorher fertiggestellten E34-Runtime-Vertrag entsteht eine vollständige, zeitlich begrenzte Geschichte mit Aufgaben, Sammlung und mindestens einer rein kosmetischen Trophäe. Kein Stadtreset; verpasste Inhalte und Wiederholung haben eine dokumentierte Regel.
- **E31 Rückkehrer-/Aufholaufträge:** Serverseitig festgestellte Abwesenheitsklassen, endliche Aufgaben und gedeckelte Hilfen führen zu aktuell spielbaren Inhalten. Rewards skalieren nicht unbegrenzt und überholen keine aktive Langzeitprogression.
- **E34 Event-/Inhaltseditor:** Teil A vor E25 ist der codebasierte, versionierte Event-Runtime-Vertrag mit Schema und Validator. Teil B nach dem ersten E25-Inhalt ist der vollständige Editor: Berechtigte Admins erstellen eine Entwurfsversion, validieren Termine/Aufgaben/Belohnungen, simulieren, veröffentlichen eine Revision und können nur sichere Folgeänderungen machen. Veröffentlichung ist auditierbar; laufende Instanzen bleiben auf ihrer Revision.

**Gate P7:** Eine Saison kann als Entwurf gebaut, ohne Gutschrift simuliert, terminiert, gespielt und aus Belegen ausgewertet werden. Rezepte, Aufholpakete und Eventbelohnungen bestehen Economy-Budgets. Keine Adminänderung verändert rückwirkend einen laufenden oder abgeschlossenen Beleg.

### Phase 8 – Heldenprodukt und zusätzliche Ausrüstung

Enthält **E26 und E42**. Abhängigkeiten: stabile Kampfbalance aus P6, Itemquellen aus P7 und für E42 eine bestätigte Ausgestaltungswahl. E26 ist ein eigenes großes Produkt und erhält keine parallele zweite Kampfarchitektur; E42 wird nur an die gewählte Herrscher- oder Heldendomäne angebunden.

- **E26 Helden/Kommandanten:** Produktvorschlag für einen nützlichen MVP: kleiner startfähiger Heldenkatalog, Rekrutierung aus klaren Quellen, Stufe, eine aktive und eine passive Fähigkeit, genau ein Held je Formation und serverseitige Kampfintegration in Vorschau/Bericht. Der bestehende Lord-Talentbaum bleibt eigenständig. Die konkrete Breite wird vor Beginn bestätigt.
- **E42 zusätzliche Herrscher-/Heldenausrüstung:** Das Paket ist angenommen, die Ausgestaltung bleibt offen: eigenständige Herrscherausrüstung, Heldenausrüstung nach E26 oder beide in klar getrennten Schritten. Vor Implementierung wird eine Variante gewählt und gegen bestehende Relikte abgegrenzt. Der MVP-Vorschlag umfasst wenige Slots, definierte Werte, Verstärkung und nachvollziehbare Materialkosten; Stapelregeln sind zentral dokumentiert. Nur die Heldenvariante hängt hart von E26 ab.

**Gate P8:** Jeder Held sowie jeder Wert der gewählten E42-Ausrüstungsart besitzt Quelle, Senke, Upgradegrenze und wirksamen serverseitigen Effekt. Kampfvorschau und Ergebnis stimmen, ungültige Loadouts werden abgewiesen und Respec/Upgrade kann keine Materialien duplizieren. E26 ist erst dann „MVP fertig“, wenn Rekrutieren, einer Formation zuweisen, Kämpfen und Fortschritt durchgängig spielbar sind; E42 erst, wenn die gewählte Herrscher-/Heldenausrüstung ausrüstbar, verbesserbar und im Kampf nachweisbar wirksam ist.

### Phase 9 – Drei getrennte große Produkte

Diese Phase besteht aus drei nacheinander freizugebenden Teilphasen. Die drei Teams teilen keine gleichzeitigen Änderungen an Inventar, Wirtschaft oder Stadtzustand.

#### Phase 9A – Begleiter (**E27**)

Abhängigkeiten: E26-Kampfintegration und P7-Inhaltsquellen. MVP: kleiner Begleiterkatalog, ein verständlicher Fang-/Freischaltweg, Füttern/Entwickeln, ein aktiver Begleiter und wenige klar begrenzte Rollen. Kosmetische und Kampfrollen werden nicht vermischt.

**Gate P9A:** Fangen, Besitz, Auswahl, Bonus und Fortschritt sind persistent, weltkonform und in Vorschau/Bericht sichtbar; jede Ressource hat eine gedeckelte Quelle und Senke.

#### Phase 9B – Bevölkerung und Versorgung (**E36**)

Abhängigkeiten: E17 Außenbauplätze und stabile Ressourcenstatistik aus E33. MVP: Bewohner retten, Unterkünftekapazität, Nahrungsversorgung, Zufriedenheits-/Arbeitswirkung und sichtbare Stadtbewohner. Es gibt einen sanften Mangelzustand mit klarer Erholung; keine irreversible Bevölkerungsspirale.

**Gate P9B:** Ein neuer und ein fortgeschrittener Stadtstand können Bewohner aufnehmen, versorgen, verlieren und wieder stabilisieren. Offline-Abrechnung ist gedeckelt, wiederholbar sicher und erklärt die Änderung. 3D-Figuren bleiben auf erlaubten Wegen/Arbeitsflächen und bestehen die vorgeschriebenen Ansichtsprüfungen.

#### Phase 9C – Spielerhandel/Auktionshaus (**E28**)

Abhängigkeiten: E33 Ledger, E22 Materialökonomie, festgelegte handelbare Itemklassen. MVP: Festpreisangebote einer Positivliste, Angebotsgebühr, Steuer, Laufzeit, Kauf, Ablauf und Rückgabe. Gebundene, Premium-, Ereignis- und Fortschrittsitems bleiben ausgeschlossen. Keine Gebote im ersten MVP.

**Gate P9C:** Gleichzeitig kaufen, abbrechen und ablaufen kann weder Item noch Währung vervielfachen oder vernichten. Jede Bewegung ist über ein Ledger prüfbar; Preis-/Mengenlimits, Welttrennung und Moderationssperren greifen serverseitig.

### Phase 10 – Organisierter Wettbewerb und Crossworld-Endgame

Enthält **E23 und E35**. Abhängigkeiten: E12, E15, E24, belastbare aktive Population und Betriebsmetriken. E35 beginnt erst, wenn mehrere Welten nachweislich genügend gleichzeitige Teilnehmer tragen.

- **E23 freiwillige Allianzturniere:** MVP ist ein angemeldetes, instanziertes Allianzduell mit festem Zeitfenster, Teilnehmern/Ersatzbank, eigener Wertung und virtuellen oder vollständig rückführbaren Armeen. Regeln und Verlustrisiko stehen vor Anmeldung fest. Mehrparteienvarianten folgen später.
- **E35 weltübergreifende Wettbewerbe:** Eigenes großes Produkt. MVP umfasst Paarung zweier geeigneter Welten, Vorbereitungsphase, ein begrenztes instanziertes Ziel, normierte Wertung, sichere Belohnungsverteilung und Rückkehr in die Heimatwelt. Heimatstädte, Allianzen und Inventar bleiben eindeutig zugeordnet; globale Matchmaking- oder permanente Weltmigration gehören nicht zum MVP.

**Gate P10:** Turnierzustand kann nach Prozessabbruch aus Persistenz fortgesetzt werden; alle Armeen und Belege kehren genau einmal zurück. Crossworld-Abfragen tragen Quell- und Zielwelt explizit, scheitern geschlossen bei falschem Kontext und verändern keine Heimatdaten außerhalb des veröffentlichten Vertrags. Last- und Betriebstest belegt die vorgesehene gleichzeitige Teilnehmerzahl.

## Vollständige ID-Matrix

| ID | Primärphase | Harte Abhängigkeiten | Abschlusskern |
|---|---:|---|---|
| A01 | 1 | P0; vorhandene RewardCatalog-Basis | Versionierte gemeinsame Vorschau/Auszahlung/Adminregel |
| A02 | 1 | A01; persistierter Weltanker | Genau ein einmalig sammelbarer Charm je aktivem Kill |
| E01 | 2 | A01 | Ehrliches Bestiarium mit aktiv/catalog-only |
| E02 | 2 | A01, E01 | Umgekehrte, echte Itemquellen |
| E03 | 2 | A02, E06 | Durchgängige wiederaufnehmbare Einführung |
| E04 | 3 | Kampfabrechnung | Wirksame Hospitalgrenze und sichtbarer Überlauf |
| E05 | 4 | E06, E11, E33 | Persönliche Münzen und atomarer Allianzshop |
| E06 | 2 | A01 | Sichtbare, einmalige Allianzgeschenke |
| E07 | 2 | E01; aktive Spawns | Weltweite serverseitige Monstersuche |
| E08 | 4 | Ereignisbelege aus E33 | Endliche Wochenziele und einmalige Belohnung |
| E09 | 3 | A01 | Einmalige Lord-Meilensteine |
| E10 | 3 | A01; bestehendes VIP/Truhensystem | Gemeinsame Freischalt- und Resetregeln |
| E11 | 4 | E33 | Allianzaufträge aus echten Ereignissen |
| E12 | 4 | E39 | Kalender, Zeitwahl und Anmeldung |
| E13 | 4 | Welt-/Allianzrechte | Persönliche und Allianz-Kartenmarker |
| E14 | 5 | E07, E13, E16 | Persistente Erkundung und Ruinen-MVP |
| E15 | 5 | E11, E13, E16 | Zusammenhängendes Gebiet mit HQ/Außenposten |
| E16 | 1R | P1, E33 | Öffentliche Landteile 1–9; Startgradient und drei Kartenphasen |
| E17 | 5 | Stadt-/Bauwarteschlange | Reaktivierte spezialisierte Außenbauplätze |
| E18 | 6 | A01/A02, E14 | Endlicher Solo-Abenteuerbogen |
| E19 | 6 | A01, E16 | Mechanische Tiefe für aktive Regionalbosse |
| E20 | 6 | bestehende Dungeons, E12 | Offener Gruppenfindungs-Lebenszyklus |
| E21 | 6 | A01, E02 | Einmalige gezielte Fragmentwahl |
| E22 | 7 | E02, E21, E33 | Kleines vollständiges Rezept-/Materialsystem |
| E23 | 10 | E12, E15, E24 | Freiwilliges Allianzturnier-MVP |
| E24 | 6 | E19/E20, E33 | Prüfbare begrenzte PvE-Wertung |
| E25 | 7 | E24, E34-Runtime-Vertrag | Eine vollständige Saison ohne Stadtreset |
| E26 | 8 | stabile P6-Kampfbalance | Durchgängiges Helden-MVP |
| E27 | 9A | E26, P7-Quellen | Durchgängiges Begleiter-MVP |
| E28 | 9C | E22, E33 | Ledger-gesicherter Festpreishandel |
| E29 | 3 | stabile Ereignistypen | Opt-in-Push mit Deduplizierung |
| E30 | 3, danach querschnittlich | jeweilige finale UI-Texte | DE/FR/LU ohne Rohschlüssel |
| E31 | 7 | E33; aktuelle Inhaltsziele | Gedeckelter Rückkehrerpfad |
| E32 | 3 | Profile/Chat/Adminmoderation | Kontakte, Block und Meldungsfolge |
| E33 | 1 | A01-Belege | Reale Loot-/Economy-Statistik |
| E34 | 7 | A01-Revisionen, E24; Runtime vor E25 | Vertrag, dann Entwurf, Simulation, Veröffentlichung, Audit |
| E35 | 10 | E23; mehrere tragfähige Welten | Sicheres Zwei-Welten-Wettbewerbs-MVP |
| E36 | 9B | E17, E33 | Spielbare Bevölkerung/Versorgung |
| E37 | 6 | E12, Verteidigung/Verstärkung | Vollständiger Allianz-Wellenlauf |
| E38 | 6 | A01; Kampfsystem | Stufenpfad, Erstabschluss und freier Raid |
| E39 | 3 | Allianzrollen | Allgemeine endliche Abstimmungen |
| E40 | 6 | E07, A01/A02 | Begrenzte, jederzeit stoppende Jagdserie |
| E41 | 6 | E05-Shopmuster, E33 | Ereignismarken mit Limits/Verfall |
| E42 | 8 | Ausgestaltungswahl, E22; E26 nur bei Heldenausrüstung | Abgegrenzte Herrscher-/Heldenausrüstung |

## Agentenwellen und Dateieigentum

Der Koordinator vergibt pro Welle konkrete Pfade. Kein Worker bearbeitet eine bereits einem anderen Worker zugewiesene Datei. Querschnittsdateien werden nie „geteilt“; Änderungen kommen über kleine Adapter oder werden vom Koordinator integriert.

| Welle | Worker 1 – SOL | Worker 2 – SOL | Worker 3 | Koordinator – SOL |
|---|---|---|---|---|
| 1 / P0–P1 | RewardCatalog, Settlement-Adapter, Reward-Tests | Charm-Spawn/Sammeln, Rally-Adapter, Konkurrenztests | LUNA: Katalogaudit und Dokument-/Datenvalidierung; SOL falls Adminlogik | Verträge, neue Migrationen, `MarchTick`/Router-Konflikte, Gesamtintegration |
| 2 / P1R | Landstufen, Beiträge und Kartenphasen | Freigabeprüfungen für Spawn/Placement/Suche | Migrations- und Geometriefixtures, SOL; LUNA nur Katalogtexte | Weltvertrag, gemeinsame Handler, Bestandsmigration, Gate P1R |
| 3 / P2 | Bestiarium und Itemquellindex | Suche/API und Einführungsevents | LUNA: DE/FR/LU-Inhalte und Katalogstatus | Gemeinsame API-Form, UI-Integration, Gates |
| 4 / P3–P4 | Hospital/Meilensteine | Allianzaufträge, Münzen, Shop | SOL für Kalender/Rechte; LUNA nur für i18n | Ereignisbelege, Benachrichtigungsadapter, gemeinsame Handler |
| 5 / P5 | Erkundung und Gebiet/HQ/Außenposten | Außenbauplätze und Stadtlogik | 3D/UI, SOL wegen Szenenintegration | E16-Anbindung, Kartenhandler, visuelle Abnahme |
| 6 / P6 | Boss-/Abwehr-/Kampfdomäne | Dungeonfinder, Prüfungspfad, Jagdserie | Marken/Fragmentwahl und einfache Katalogdaten; SOL bei Transaktionen | Reward-/Charm-Integration, Rankingbelege, End-to-End |
| 7 / P7 | E34-Runtime, danach vollständiger Editor | Handwerk/Economy | LUNA: Saison-/Rückkehrerkatalog und i18n; SOL für Servicecode | Reihenfolge Vertrag → E25 → Editor, Veröffentlichung und Balancegate |
| 8 / P8–P9 | Jeweils nur der aktive große Produktkern | Zugehörige UI/3D-Schicht | Zugehörige Tests/Kataloge | Domänenvertrag und Merge; nächstes Produkt erst nach Gate |
| 9 / P10 | Turnierinstanz | Crossworld-Kontext und Rückführung | Last-/Fehlerfall-Harness | Betriebsfreigabe, Security/Economy und Rollbackplan |

**Reservierte Querschnittsdateien des Koordinators:** `index.php`, zentrale API-/GameHandler-Routen, `src/Game/March/MarchTick.php`, `src/Game/Rally/MonsterRally.php`, gemeinsam genutzte Locale-Dateien, `migrations/run.php`, globale Styles sowie alle Migrationsnummern. Ein Worker liefert für diese Dateien einen kleinen Patchvorschlag oder Adapter, ändert sie aber nicht parallel.

**Typische exklusive Workerbereiche:** neue Services unter einem Feature-Namespace, zugehörige Handler, eigene View/JS/CSS-Dateien, genau eine neue Migration und spezifische Tests. Katalogdaten erhalten pro Welle einen einzigen Besitzer. Vor jeder Übergabe nennt der Worker die tatsächlich geänderten Dateien, ausgeführten Tests, offenen Annahmen und bekannte Risiken.

## Test- und Integrationsstrategie

1. **Keine gemeinsame Live-Datenbank für schreibende Tests.** PHP-Lebenszyklustests verwenden das vorhandene Muster `tests/Support/FeatureDatabase.php`: nur lokales MySQL, zufälliger Name `conquer_feature_test_<hex>`, Schema per `CREATE TABLE ... LIKE`, nur explizite minimale Fixtures, und Löschen ausschließlich nach strenger Namens-/Pfadprüfung. Tests dürfen weder die konfigurierte Quelldatenbank noch Spielerbestände verändern.
2. **Migrationstest auf Wegwerfkopien.** Jede neue Migration wird auf (a) leerem aktuellen Schema, (b) Schema bis zur direkten Vorgängermigration und (c) einer anonymisierten Strukturkopie mit relevanten Altzuständen geprüft. Vorwärtsmigration, zweiter Lauf und Code gegen alten/neuen Zustand werden geprüft; kein automatisches Rollback gegen produktive Daten.
3. **Deterministische Domänentests.** Zufall für Loot, Matchmaking und Spawns wird über injizierbare RNG-/Clock-Schnittstellen kontrolliert. Kritisch sind Invarianten statt Momentaufnahmen: Summe der Währung, Itembesitz, Truppenreservierung/-rückkehr, Weltbezug, Regelrevision und genau einmalige Belege.
4. **Parallelitätsfälle.** Zwei Settlement-Aufrufe, zwei Käufer, zwei Sammler, Ablauf gegen Abbruch und Admin-Update gegen laufenden Auftrag werden mit getrennten DB-Verbindungen getestet. Erwartet wird genau ein Gewinner oder ein wohldefinierter, verlustfreier Ausgang.
5. **Vertrags- und Katalogtests.** Alle aktiven Quellen referenzieren gültige Items, Relikte, Gegner und Übersetzungsschlüssel. `catalog_only` darf nicht in Spawngewichten, Suche nach aktiven Zielen oder Autojagd erscheinen. Vorschau und Auszahlung werden aus derselben Revision abgeleitet.
6. **HTTP/UI-Tests.** Handlerfälle nutzen einen isolierten lokalen PHP-Server wie die bestehenden Tests. DOM-Tests decken Tastatur, Fokus, reduzierte Bewegung, leere/fehlerhafte Zustände und Übersetzungen ab. Keine UI-Prüfung ersetzt die serverseitige Berechtigungsprüfung.
7. **Visuelle Abnahme.** Jede sichtbare Phase wird in Desktop, Handy und Querformat geprüft. 3D-Änderungen zusätzlich in echter Gesamtansicht, aus der Nähe und eingebettet unter `/city#city`; Browserkonsole, doppelte HUDs, Gebäudeauswahl, Beschriftung und Laufwege sind Teil des Gates.
8. **Stufenweiser Regressionslauf.** Das Repository besitzt derzeit weder `composer.json`, `package.json` noch `tests/run.php`; es darf deshalb kein Phantom-Gesamtlauf gemeldet werden. Erst laufen `php -l` für geänderte PHP-Dateien sowie die konkret betroffenen Skripte, zum Beispiel `php tests/monster_rallies.php`, `php tests/multiworld_integration.php`, `php tests/world_placement.php` und `php tests/backoffice_integration.php`. Danach folgen angrenzende PHP-Skripte, vorhandene Node-Smokes einzeln mit `node tests/<name>.cjs` beziehungsweise `.js` und `tests/verify_security.sh` in einer passenden Shell. Der Koordinator führt pro Phase eine explizite Liste der tatsächlich vorhandenen Testdateien und protokolliert Befehl, Exitcode und Ergebnis. Ein bereits bestehender Baselinefehler wird nicht dem neuen Patch zugerechnet, aber dokumentiert; neue Fehler blockieren das Gate.
9. **Arbeitsbaum-Schutz.** Vor und nach jeder Agentenübergabe vergleicht der Koordinator `git status --short` und den Diff nur für die zugewiesenen Pfade. Kein `git reset --hard`, `git checkout --`, pauschales Formatieren oder Löschen unversionierter Dateien. Integrationskonflikte werden in der Eigentümerdatei gelöst, nicht durch Übernahme eines ganzen fremden Branchzustands.

## Kritischer Pfad und Hauptrisiken

Der kritische Pfad ist **P0 → A01 → A02/E33 → E16/P1R → E01/E02/E07 → E11/E12/E13 → E14/E15 → P6-Kampfinhalte → E34-Runtime → E25 → E34-Editor → E26/E42 → E23/E35**. E17 führt zusätzlich zu E36; E21 führt zu E22 und damit zu einem wirtschaftlich sicheren E28.

Die größten Risiken sind die noch unterschiedlichen Lootpfade, fehlende Regel-Snapshots für laufende Aufträge, historisch uneinheitliche Monster-IDs, Welt-1-Festverdrahtungen, fehlende individuelle Kartenpunkte für Instanzgegner und gleichzeitige Abrechnung. Danach folgen Economy-Inflation durch neue Münzen/Marken/Materialien, eine zu schnelle weltweite Entwicklung auf Landstufe 9, Fehlkonfiguration oder Umgehung der drei Kartenfreigaben, Transaktionsmissbrauch im Auktionshaus und unzureichende Population für E35. Diese Risiken werden durch die frühe gemeinsame Reward-/Belegschicht, E16 als frühes Serverfundament, den zunächst deaktivierten Kristalladapter, getrennte Produktphasen und messbare Gates kontrolliert.

Die Annahme aller E-Punkte ändert frühere optionale Formulierungen: E17 wird geplant und umgesetzt, obwohl Außenbauplätze derzeit bewusst deaktiviert sind. Gleichzeitig bedeutet die Annahme keine sofortige Aktivierung historischer Gegner oder unbegrenzte Produkttiefe. Jedes Paket endet mit dem hier beschriebenen, spielbaren MVP; weitere Inhalte, Katalogbreite und Balancevarianten werden erst nach seinem Gate neu geplant.
