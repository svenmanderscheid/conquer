# Conquer: PvE als Spielkern

Stand: 10. September 2026. Konzeptentwurf zur Diskussion; keine bereits implementierte Funktion und kein Ersatz für die technische Spezifikation.

## Zielbild

Ein kooperatives Aufbaustrategiespiel in einer gefährlichen Fantasywelt: Spieler entwickeln ihre Städte, Allianzen organisieren Expeditionen und mehrere Allianzen verbünden sich gegen mächtige Bosse. Gemeinsame Siege erschließen die Welt und tragen den langfristigen Fortschritt. PvP bleibt als ergänzende Aktivität möglich.

Vom Nutzer vorgegeben sind der Schwerpunkt auf PvE, mächtige gemeinsame Bossgegner, die Zusammenarbeit von Allianzen und die weitere Möglichkeit zu PvP. Die folgenden Regeln sind konkrete Gestaltungsvorschläge. Teilnehmerzahlen, Zeitfenster und Belohnungen müssen im Prototyp erprobt werden.

## Der wiederkehrende Spielablauf

Stadt ausbauen → Armee und Versorgung vorbereiten → Bedrohungen erkunden → mit anderen einen Feldzug planen → Aufgaben und Bossphasen bewältigen → Beute und neue Gebiete erschließen → auf die nächste Bedrohung vorbereiten.

Der Städtebau erhält dadurch einen konkreten Zweck: Kasernen stellen Expeditionstruppen, Forschung ermöglicht passende Taktiken, die Wirtschaft versorgt Feldzüge und die Allianzhalle organisiert gemeinsame Einsätze. Bestehende Gebäude bleiben die Grundlage.

| Ebene | Aktivität | Zweck |
|---|---|---|
| Einzelspieler | Monsterlager, Erkundung, kleine Expeditionen | Einstieg, Ressourcen und verlässlicher eigener Fortschritt |
| Allianz | Festungen, Verteidigungsaufgaben, kleinere Bosse | Zusammenarbeit lernen und gemeinsame Projekte voranbringen |
| Mehrere Allianzen | Feldzug mit Nebenaufgaben und Bossphasen | Hauptinhalt des kooperativen Endgames |
| Weltgemeinschaft, später | Große Invasion oder Kapitelabschluss | Ein gemeinsames Ereignis mit sichtbaren Folgen für die Welt |

Neue Weltkapitel öffnen beispielsweise eine Region mit anderen Gegnern und taktischen Anforderungen. Bereits entwickelte Städte bleiben erhalten. Eine gescheiterte Expedition kann wiederholt werden; sie löscht keinen monatelangen Aufbau.

## Was Zusammenarbeit spielerisch notwendig macht

Ein Boss braucht verständliche Mechaniken: Schutzanlagen schwächen, Verstärkungen abfangen, eine Stellung halten oder Versorgung liefern. Die Begegnung zeigt im Voraus, was zu tun ist. Nach einer Niederlage erklärt der Bericht, welche Aufgabe fehlte.

Mehrere Aufgaben laufen innerhalb derselben, ausreichend langen Phase. Vorab erteilte Befehle werden serverseitig ausgeführt; die Spieler müssen nicht gleichzeitig auf einen Knopf drücken. Rohstärke hilft, erfüllt aber keine unerledigten Missionsziele.

Kleinere Spieler können auf geeigneten Nebenaufgaben wirksam beitragen. Unterstützung wird anhand tatsächlich erfüllter Ziele gewertet. Es zählt beispielsweise die benötigte Lieferung oder die erfolgreich abgewehrte Welle; unnötige Spenden erzeugen keine endlosen Beitragspunkte.

Allianzen schließen für einen Feldzug eine zeitlich begrenzte Koalition. Mitglieder behalten ihre Allianz. Beide Allianzleitungen bestätigen die Teilnahme, Aufgaben sind für alle Beteiligten sichtbar und jede Allianz verwaltet die eigenen Truppen. Ein bestehender einseitiger Diplomatieeintrag reicht dafür nicht aus.

## Beispiel: Der Aschenfürst

Ein gewaltiger Feuerriese bedroht eine Grenzregion. Zwei Allianzen bilden eine Expedition, legen ein Startfenster fest und verteilen ihre Aufgaben.

1. **Vorbereitung:** Spieler erkunden zwei Schutzanlagen und füllen ein begrenztes Versorgungslager. Im Expeditionsfenster stehen Aufgaben, benötigte Beiträge und die Regeln für Verluste und Beute.
2. **Schutz brechen:** Eine Allianz greift die Anlagen an, die andere hält während derselben Phase eine Passstellung gegen Verstärkungen. Beide Ziele müssen abgeschlossen sein, bevor der Boss verwundbar wird. Die Rollen sind je Versuch frei wählbar.
3. **Boss bekämpfen:** Beide Allianzen schicken Sammelangriffe gegen den gemeinsamen Lebenspunktevorrat. Die vorher gesicherte Versorgung begrenzt die Verwundungen. Bei angekündigten Phasenwechseln können vorgegebene Taktiken gewählt werden; der erste Prototyp benötigt noch keine weiteren Spezialfähigkeiten.
4. **Abschluss:** Alle qualifizierten Teilnehmer erhalten persönliche Beute. Der Erfolg öffnet einen regionalen PvE-Auftrag und wird in der gemeinsamen Chronik festgehalten.

Schon vor dem Start lassen sich Marschbefehle vorbereiten. Ein großzügiges Teilnahmefenster und später mehrere wählbare Startzeiten sollen Berufstätigen und verschiedenen Zeitzonen entgegenkommen. Der erste Prototyp prüft den Ablauf mit kurzen Testzeiten; diese sind keine späteren täglichen Anwesenheitspflichten.

## Belohnungen und Fortschritt

- Persönliche Teilnahmebelohnung für einen nachvollziehbaren Mindestbeitrag über Kampf **oder** Missionsaufgaben. Kein exklusiver Anspruch durch den letzten Treffer.
- Eine zusätzliche gemeinsame Erfolgsbelohnung und kosmetische Trophäen. Die Allianzleitung verteilt keine persönliche Beute nach Belieben.
- Planbarer Fortschritt über erspielbare Materialien; seltene Funde ergänzen diesen. Neue Ausrüstung kann unterschiedliche Taktiken unterstützen, ohne mit jedem Kapitel alles Bisherige unbrauchbar zu machen.
- Begrenzte Belohnungen pro Charakter und Begegnungszyklus. Allianzwechsel und das Wiederholen bereits vergüteter Teilaufgaben erzeugen keine zusätzliche Auszahlung.
- Teilnahme und vorhandene Ansprüche werden gespeichert. Ein späterer Ausschluss aus der Allianz oder eine Verbindungsunterbrechung löscht verdiente Beute nicht.
- Schwierigkeit und Beitragsschwellen stehen vor dem Start fest. Sie passen sich während eines laufenden Kampfes nicht überraschend an neu eintretende Spieler an.

Normale Expeditionen sollten kalkulierbare Verwundungen mit begrenzter Erholung verursachen. Die konkrete Verlustregel muss gegenüber dem heutigen Monsterkampf angepasst und vor dem Entsenden angezeigt werden. Größere Risiken sind später für ausdrücklich gewählte höhere Schwierigkeitsgrade möglich.

Solo-Spieler behalten einen vollständigen eigenen Aufbau- und Expeditionspfad. Die größten Koalitionsbosse erfordern Gruppen. Dies verändert die bisherige Aussage in `CLAUDE.md`, dass eine Allianz selbst für das Endgame nicht nötig sei: Solo-Fortschritt bleibt möglich, derselbe Gruppeninhalt ist jedoch nicht allein abschließbar.

Bezahlte zusätzliche Bossversuche, stärkere Raidbeute oder exklusive Kampfboni passen nicht zu dieser Richtung. Auch die bisher vorgesehenen käuflichen Zeitersparnisse müssten auf ihren Einfluss auf kooperative Ranglisten und Machtfortschritt geprüft werden.

## Rolle von PvP

Empfohlen sind freiwillig betretene Konfliktgebiete und angemeldete Allianzturniere. Hauptstädte und die zentralen PvE-Gebiete bleiben geschützt. Vor dem Eintritt in ein Konfliktgebiet werden Teilnahme, Truppenrisiko und Rückzug klar erklärt.

PvP bietet Prestige, kosmetische Belohnungen und eine eigene Wertung. Es darf weder den Zugang zu Hauptbossen noch Materialien blockieren, die für den normalen PvE-Fortschritt nötig sind. Während gemeinsamer Expeditionen können andere Spieler die Teilnehmer oder deren Versorgung nicht überfallen.

Zusätzlich können Allianzen ohne direkte Angriffe konkurrieren: Wer löst einen schwierigen Feldzug besonders effizient oder bewältigt zuerst einen höheren Schwierigkeitsgrad? Dieser Wettbewerb braucht begrenzte Versuche und vergleichbare Bedingungen, wenn daraus Ranglisten entstehen.

## Erster spielbarer Prototyp

Ein lokales Szenario mit einem Boss, zwei Allianzen und zunächst vier bis acht Testspielern genügt, um den Kern zu prüfen. Das ist eine Testbesetzung, keine feste Mindestgröße für sämtliche späteren Inhalte.

Benötigt werden die Allianzgründung und der Beitritt in der aktuellen Oberfläche, eine beidseitig bestätigte Expeditionseinladung, zwei gemeinsame Missionsziele, Sammelangriffe auf den Boss, sichtbare Beiträge, persönliche Belohnungen und ein vollständiger Abschlussbericht. Der Kampf läuft über die bestehenden Marschzeiten und serverseitige Auswertung.

Der erste Versuch soll beantworten:

- Hat jede Allianz eine erkennbare, notwendige Aufgabe?
- Können kleine Teilnehmer mit einer passenden Aufgabe sinnvoll beitragen?
- Sind Vorbereitung und Truppenwahl interessanter als wiederholtes Angreifen?
- Funktioniert die Teilnahme auch nach dem Schließen des Browsers?
- Verstehen die Spieler sowohl Sieg als auch Niederlage und möchten sie den Versuch wiederholen?

Ein vollständiges Invasionssystem, mehrere Bossfamilien und PvP-Turniere folgen erst, wenn dieses Szenario trägt. Für eine kleine Startgemeinschaft bleiben Einzelspieler- und Allianzmissionen verfügbar; die Alltagsprogression darf nicht auf eine große aktive Welt warten müssen.

## Anschluss an den vorhandenen Code

Der geprüfte Stand aus `docs/MVP_STATUS.md` bietet Stadtaufbau, Truppen, Forschung, Monsterkämpfe, Märsche und gespeicherten Fortschritt. Allianz- und PvP-Module sind vorhanden, aber nicht Teil des dort abgesicherten MVP-Umfangs.

`src/Game/Rally/RallyService.php` erlaubt derzeit Sammelangriffe innerhalb derselben Allianz gegen Spielerstädte. Auch `migrations/0029_create_rallies.sql` verlangt Spieler- und Stadtziele. Monster-Raids und Koalitionen sind deshalb eine echte funktionale Erweiterung.

`src/Game/March/BattleEngine.php` berechnet Monsterkämpfe einschließlich verbleibender Monster-Lebenspunkte. `src/Game/March/MarchTick.php` führt die Abrechnung und bestehende Monsterbelohnungen aus. Daraus lässt sich ein Anschluss entwickeln; ein geteilter Raidstatus mit Phasen, Beiträgen und individueller Beute fehlt noch.

Für die Umsetzung sind insbesondere folgende Eigenschaften abzusichern:

- Gemeinsamer Expeditionsstatus für Ziele, Phasen, Teilnehmer und Boss-Lebenspunkte; Zustände von Planung bis Abschluss oder Abbruch.
- Transaktionen und eine Sperre pro Begegnung für gleichzeitig ankommende Angriffe. Derselbe Angriff darf nur einmal gewertet werden.
- Genau eine Vergütung pro berechtigtem Spieler und Belohnungsart; Wiederholungen durch Polling oder Cron bleiben folgenlos.
- Sichere Reservierung und Rückgabe jeder Teilnehmerarmee, auch bei Abbruch, Ablauf, Allianzwechsel und bereits besiegtem Ziel.
- Identische Auswertung durch Browseraufrufe und Cron, einschließlich Fortschritt ohne geöffnete Spielsitzung.
- Bestehende PHP-/MySQL-Architektur, UTC-Zeiten und direkte JavaScript-Dateien beibehalten.

Erforderliche spätere Tests betreffen vor allem Koalitionsberechtigungen, gleichzeitige Angriffe, Phasenübergänge, doppelte Auszahlung, Abbrüche und vollständige Truppenrückgaben. Dieser Konzeptentwurf enthält keine Implementierung oder neue Laufzeitprüfung.

## Einordnung der Idee

Gemeinsame Bosskämpfe gibt es bereits: Die [Behemoths in Call of Dragons](https://callofdragons.fandom.com/wiki/Behemoths) sind ein Allianzinhalt; die offizielle [Whiteout-Survival-Hilfe zu Beast Hunting](https://centurygames.helpshift.com/hc/en/64-whiteout-survival/section/1260-beast-hunting/) beschreibt ebenfalls eine organisierte Allianzaktivität.

Die mögliche eigene Positionierung liegt in der Kombination aus dauerhaftem Städtebau, einem konsequenten PvE-Fortschritt, Zusammenarbeit mehrerer Allianzen und freiwilligem PvP. Ob diese Kombination im Markt tatsächlich selten ist und genügend Nachfrage findet, lässt sich aus diesen Beispielen allein nicht ableiten. Der Prototyp soll zunächst zeigen, ob die Zusammenarbeit selbst Spaß macht.
