# Conquer – Große Stadt und Weltkarte

Stand: 9. September 2026. Lokal umgesetzt im Hauptprojekt und in der getrennten Spieltestkopie. Keine Veröffentlichung und kein Push.

## Stadt

Die Stadt umfasst jetzt alle 13 vorhandenen ausbaubaren Gebäudetypen: Festung, Stadtmauer, Bauernhof, Holzfällerlager, Steinbruch, Goldmine, Lagerhaus, Schatzkammer, Kaserne, Krankenhaus, Akademie, Handelsposten und Allianzhalle. Die Modelle sind weiterhin vorläufige echte 3D-Grafik. Sie greifen die Funktionen der vom Nutzer gelieferten Gebäudereferenzen auf; die Fremdspielbilder wurden nicht als neue Spielgrafiken eingebaut.

Aktualisierung vom 13. September 2026: Der vorhandene Wachturm ist mit den gelieferten Kosten, Bauzeiten, Voraussetzungen und Machtwerten bis Stufe 30 ausbaubar. Seine Aktion „Umgebung“ öffnet weiterhin die Weltkarte. Zusammen mit Schützenlager und Reiterhof sind damit 16 Gebäudetypen an die Bauverwaltung angebunden. Details: `BALANCE_IMPORT.md`.

Die größere Stadt ist verschiebbar und zoombar. Geschwungene Verbindungswege, ein Brunnen, Bänke, Laternen, Büsche und abgerundete Bäume gliedern die Viertel. Gebäudeaktionen und gespeicherte Baufortschritte sind jetzt an alle 13 Gebäudepositionen angebunden. Forschung bleibt an der Akademie, Training an der Kaserne sichtbar. In der weit entfernten Gesamtübersicht werden untätige Beschriftungen ausgeblendet; Auswahl und laufende Aufträge bleiben erkennbar. Eine zusätzliche Kollisionsprüfung verhindert überlagerte untätige Namen.

Profil → Gebäude finden zeigt die 13 Gebäude mit echten Stufen. Antippen bewegt die Kamera zum Gebäude und öffnet dessen Aktionen. Der Wachturm kann räumlich in der Stadt ausgewählt werden.

## Ressourcen

Stadt und bestehende Spielansichten verwenden Rohstoff-Icons mit Zahlen. Ressourcennamen bleiben als zugängliche Beschriftung und Tooltip verfügbar. Mengen stammen weiterhin aus dem Serverzustand. Die Icons sind zunächst Systemzeichen; ein eigener grafischer Icon-Satz ist nicht Bestandteil dieses Schrittes.

## Weltkarte

Die bisherige Monsterliste samt separaten „Angriff planen“-Schaltflächen ist entfernt. Ein Monster auf der Karte öffnet die vorhandene Truppenauswahl; erst der separate Angriff-Start entsendet Truppen. Rohstofffelder sind ebenfalls direkt auswählbar.

Die Karte zeigt X und Y des betrachteten Ausschnitts. Mausziehen, Touch-Scrollen und Tastatursteuerung verschieben den Ausschnitt; die Heimtaste zentriert die eigene Stadt. Für die bestehende Testwelt werden Koordinaten 0–255 verwendet. Nach dem Verschieben werden Monster und Rohstofffelder rund um den betrachteten Mittelpunkt nachgeladen, mit begrenzten Abfragen. Es gibt noch keine neue Darstellung fremder Spielerstädte, Shrines, Rally-Menüs oder eine Weltübersicht wie in allen Referenzscreenshots.

Ausgesandte Truppen erscheinen als animierte Truppensymbole auf einer gestrichelten Route. Helle Routen führen zum Ziel; goldene Routen und die Beschriftung „Rückkehr“ kennzeichnen den Rückweg. Die Positionsberechnung verwendet gespeicherte Abfahrt, Ankunft und Rückkehr sowie die synchronisierte Serverzeit. Nach Erreichen des Ziels wartet die Anzeige auf den serverbestätigten Status; nach abgeschlossenem Marsch verschwinden Route und Truppensymbol. Das sind erste Marschsymbole, noch keine finalen laufenden Soldatenmodelle.

## Prüfung und Grenzen

- PHP- und JavaScript-Syntaxprüfungen bestanden; Git-Diff ohne Whitespacefehler.
- Alle zehn bestehenden MVP-Regelprüfungen bestanden.
- Neue begrenzte HTTP-Prüfung gegen die isolierte Testkopie: Anmeldung erforderlich; vier Kartenmittelpunkte einschließlich Randkoordinaten; Ziele im angefragten Gebiet; Stadtidentität und 13 Gebäude bleiben erhalten; reines Betrachten startet keinen Marsch; ungültige Koordinaten werden abgewiesen. Ergebnis: alle Prüfungen bestanden (`MapCheck232047`).
- Browserprüfung im schmalen Hochformat: Rohstoffleiste, Krankenhaus-Ausbauinformationen, Gebäudeübersicht, Kamerafokus zum Bauernhof, Gesamtübersicht und Navigation zwischen Stadt und Welt.
- Scrollprüfung: Kartenmittelpunkt wechselte von X 56 / Y 56 zu X 56 / Y 71 und die Zielmenge wurde aktualisiert.
- Zwei echte, kurze Angriffe mit je zwölf Truppen ausschließlich aus dem zuvor angelegten UI-Testkonto `BauKlick504530`: Hinmarsch und Rückkehr sichtbar; anschließend keine aktive Marschanzeige mehr. Neuladen erfolgte ebenfalls, die sehr kurzen Märsche waren bei den anschließenden Abfragen bereits abgeschlossen. Eine separate längere visuelle Prüfung mitten im laufenden Marsch nach Neuladen steht daher noch aus.

Die Originaldatenbank wurde für diese Tests nicht verändert. Die neue größere Szene muss auf Svens S23 Ultra erneut praktisch auf Flüssigkeit geprüft werden; die frühere positive Gerätemessung bezog sich auf eine kleinere Szene.

Referenzgrundlage: die in dieser Nachricht gelieferten Kartenbilder unter `Z:/LOK/LOK/MAP/`, Gebäudebilder unter `Z:/LOK/LOK/Buildings/` und `Z:/LOK/LOK/Screenshot 2026-05-02 210233.png`. Texte und Zahlen in diesen Bildern ändern keine vereinbarten Spielregeln.

## Lockerere Anordnung – 10. September 2026
Die Insel wurde gegenüber dem vorherigen Stand auf etwa die 2,8-fache Fläche erweitert (Breite und Tiefe jeweils rund 1,7-fach). Die Gebäudemodelle wurden nicht vergrößert. Festung, Akademie, Allianzhalle und Krankenhaus bilden den westlichen Stadtbereich; Handel, Versorgung und Produktion sind über weiter außen liegende Standorte verteilt. Wege, Zufahrten, Brunnenplatz, Sitzbereiche und Baumgruppen wurden passend neu angeordnet. Alte Wege und lose Dekoration der kleinen Ausgangsszene sind ausgeblendet.
Kamera-Schwenkgrenzen, minimale Zoomstufe, Gesamtübersicht und Schattenbereich wurden an die neue Ausdehnung angepasst. JavaScript-Syntaxprüfung bestanden; im Browser Zentrum, Gesamtübersicht und gezielter Kamerafokus zum äußeren Holzfällerlager geprüft. Gebäudestufen und Ausbauaktionen bleiben an die neu platzierten Modelle gebunden. Keine Datenbank-, Regel- oder Hostingänderungen.

## Umlaufende Mauer und unterscheidbare Gebäude – 10. September 2026
Die Stadtmauer verläuft jetzt um sämtliche Stadt- und Produktionsgebäude. Ein offenes Haupttor am südlichen Rand ist mit dem Wegenetz verbunden. Umlaufende Zinnen und verteilte Türme ergänzen den Mauerzug; alle Mauerabschnitte gehören zum vorhandenen Gebäude Stadtmauer. Auswahl führt zum Haupttor mit den tatsächlichen Ausbauinformationen. Inselrand, Baumring und Kameragrenzen wurden passend erweitert. Wiederholte Mauerelemente verwenden drei InstancedMesh-Gruppen, um Zeichenaufrufe zu begrenzen.
Akademie: Bibliotheksflügel, Kristall und Umlaufring. Kaserne: rote Wachtürme, Übungshof, Zielscheiben und Waffenständer. Krankenhaus: türkisfarbenes Dach, helle Seitenflügel und Kreuz. Lagerhaus: zwei hohe Silos, Ladetore und Kisten. Allianzhalle: hohes blaues Dach, Säulen, Wappen und Banner. Steinbruch: offene Steinterrassen und Kran, sichtbar verschieden vom Stollen der Goldmine. Die bestehenden Modelle für Festung, Bauernhof, Markt, Holzfällerlager und Schatzkammer bleiben erkennbar erhalten.
JavaScript-Syntax geprüft. Browserprüfung: gesamter Mauerverlauf mit eingeschlossenen Gebäuden, Auswahl und korrekte Ausbaukosten am Haupttor, Krankenhaus-/Akademieformen im Hochformat 390 × 844. Kein Ausbau ausgelöst und keine Spielregeln oder Datenbankdaten geändert.

## Freie Gebäudesicht, Aktionen darunter und detaillierte Festung – 10. September 2026
In der Nahansicht wird die gesamte Stadtmauer auf 14 Prozent Deckkraft ausgeblendet. Dadurch bleiben auch Gebäude am Rand sichtbar; bei der Auswahl werden Gebäudetreffer hinter der transparenten Mauer bevorzugt. In der Gesamtübersicht und bei ausgewählter Stadtmauer ist sie vollständig sichtbar. Die transparente Mauer wirft keine verdeckenden Schattenstreifen. Dies ist eine zoomabhängige Lösung für den kompletten Mauerzug, keine individuelle Berechnung einzelner verdeckender Abschnitte.

Info, Ausbau und Schließen stehen jetzt auf einer eigenen dunklen, goldgerahmten Leiste unter dem Gebäudefuß. Die runden Schaltflächen sind 52 Pixel groß und beschriftet. Gebäudename und Stufe bleiben oben. Bei Bedarf zentriert die Auswahl die Kamera, um darunter Platz zu schaffen. Ausbauinformationen öffnen unter der Aktionsleiste und sind bei begrenzter Bildschirmhöhe scrollbar; ein Antippen des Gebäudes startet weiterhin keinen Ausbau.

Die Festung wurde als detaillierteres Stilmodell ausgearbeitet: einzelne abgerundete Dachschindeln, zusätzliche Steinreihen, Fensterrahmen und Fensterstreben, goldene Dachringe, Balkon mit Balustrade, Torbeschläge, verzierte Eingangssäulen, Laternen, Treppengeländer, Vorplatzpflaster und Blumenbeete. Wiederholte Kleinteile werden instanziert, Dachschindeln in zwei Geometrien zusammengefasst. Der Detailstand ist ausgebaut, aber noch keine Freigabe als endgültiges Produktionsmodell für alle Geräte.

Geprüft: JavaScript-Syntax der drei geänderten Module, PHP-Syntax der Ansicht und Diff-Whitespaceprüfung bestanden. Alle fünf Laufzeitdateien sind identisch in Hauptprojekt und Spieltestkopie. Im Browser bei 390 × 844: Festung, drei Aktionen darunter, Ausbaukosten mit scrollbar erreichbaren weiteren Inhalten, Schließen und Steinbruch hinter transparenter Mauer geprüft. Auf breitem Bildschirm vollständigen Mauerverlauf in der Übersicht und detaillierte Festung geprüft. Es wurde kein Ausbau gestartet. Die eingeblendeten Messwerte zeigten in der schmalen Desktop-Browseransicht rund 112 Bilder/s, 182.972 Dreiecke und 609 Zeichenaufrufe; dies ersetzt keine Prüfung auf dem physischen S23 Ultra.

## Gleichmäßigere Dorfverteilung und Abstand zur Mauer – 10. September 2026
Die Standorte wurden erneut räumlich überarbeitet, weil Goldmine und Steinbruch in der Gesamtansicht von der vorderen Mauer verdeckt wurden und das Produktionsviertel zu gedrängt war. Die Kaserne steht jetzt im Westen bei (-15, 9), die Goldmine weiter innen bei (-6, 19), der Steinbruch bei (16, 16). Festung, Akademie, Krankenhaus, Schatzkammer, Allianzhalle, Handel, Lager, Bauernhof, Holzfällerlager und Wachturm wurden ebenfalls auf die vorhandene Dorffläche verteilt. Der Mauerverlauf und die Inselgröße bleiben erhalten. Ein breiter Grünstreifen trennt die südlichen Gebäude von der Mauer.

Wege und Gebäudezufahrten wurden an alle neuen Positionen angepasst; Brunnen, Sitzbereiche und innere Baumgruppen wurden dazu versetzt. Gebäudeanker und Kamerafokus folgen den tatsächlichen neuen Modellpositionen. Die bereits vorhandene Transparenz der Mauer in der Nahansicht bleibt bestehen; die Gesamtansicht zeigt nun auch bei undurchsichtiger Mauer sämtliche Gebäudemodelle frei sichtbar.

Geprüft: Gesamtansicht im Browser, Steinbruch-Auswahl mit Aktionen unter dem neuen Standort, JavaScript-Syntax beider geänderter Szenenmodule, PHP-Syntax und Diff-Whitespaceprüfung bestanden. Hauptprojekt und getrennte Spieltestkopie aktualisiert. Keine Spielregeln oder Datenbankdaten geändert, kein Ausbau gestartet.

## Gebäudefunktionen und Truppenausbildung – 10. September 2026
Ein Klick beziehungsweise kurzer Tap auf freie Kartenfläche schließt die Gebäudeauswahl einschließlich offener Details oder Ausbauinformationen. Kameraziehen bleibt davon getrennt. Die kleinen drei Symbolaktionen lauten nun Details, Ausbau und eine gebäudespezifische Funktion; Schließen erfolgt über die Karte. Tooltip und zugängliche Beschriftung benennen die Aktionen.

Die Kaserne öffnet eine neue Trainingsansicht direkt über der 3D-Stadt: vorhandene Charakterillustration, echte Kampfwerte, fünf vorhandene Truppenstufen, Bestand, Nahrung-/Holz-/Stein-/Goldkosten, Mengenregler, Zahlenfeld und Ausbildungsdauer. Infanterie, Bogenschützen und Kavallerie sind auswählbar. Gesperrte Stufen lassen sich ansehen, aber nicht trainieren. Die Freischaltung entspricht der bestehenden Serverregel (Kaserne ab Stufe 1 und je Truppenstufe notwendige Akademiestufe). Keine neuen Balancewerte oder Freischaltungsregeln. Training verwendet den vorhandenen CSRF-geschützten Endpunkt und einen Ausbildungsplatz. Doppelklicks werden während des Speicherns gesperrt; bei ungewisser Netzwerkantwort wird nicht automatisch wiederholt. Die vorhandenen Illustrationen sind keine neuen animierten 3D-Truppenmodelle.

Weitere Funktionen: Akademie öffnet die vorhandene Forschungsseite; Rohstoffgebäude zeigen Produktion, Vorrat und Produktionslager; Lagerhaus zeigt Ressourcenbestände und Grenzen; Festung zeigt Stadtübersicht; Mauer zeigt ihren gespeicherten Zustand; Krankenhaus öffnet die Heilungsübersicht; Wachturm führt zur Weltkarte. Handel, Schätze und Allianzverwaltung sind weiterhin ausdrücklich als noch nicht verfügbar gekennzeichnet. Diese Runde implementiert dafür keine neuen Wirtschaftssysteme.

Die Heilungsübersicht hatte bisher eine city_id im Login-Session-Datensatz vorausgesetzt. Ihr GET-Endpunkt löst jetzt die eigene Stadt über den authentifizierten Spieler auf. Zugriffe und bestehende Spielregeln bleiben serverseitig kontrolliert.

Prüfung: JavaScript- und PHP-Syntax bestanden; alle zehn bestehenden MVP-Regelprüfungen bestanden. Browserprüfung im Format 390 × 844: Trainingsdialog, Gattungs-/Stufenwechsel, gesperrte Stufe, Menge 0, gültige Kosten/Dauer, Kartenklick zum Schließen sowohl der Symbole als auch offener Details, Goldproduktion und Krankenhausstatus. Im bereits vorhandenen isolierten Testkonto BauAnsicht374484 wurden vier Bogenschützen trainiert: 160 Nahrung, 80 Holz und 40 Gold, zwölf Sekunden, anschließend Bestand von null auf vier erhöht und Ausbildungsplatz frei. Kein Gebäudekauf, kein Push und keine Veröffentlichung.

## Burg im Inselzentrum mit erweiterten Flügeln – 10. September 2026
Die Festung steht jetzt auf dem Inselmittelpunkt (0, 0). Das Modell ist einheitlich um 28 Prozent vergrößert; zwei zusätzliche Seitenflügel, ein verbindender rückwärtiger Saal und zwei weitere Türme erweitern außerdem seine Grundfläche. Neue Satteldächer besitzen instanzierte Dachziegel und goldene Firstleisten. Zusätzliche Fenster, Steinreihen, Balustraden, Leuchten, Fahnen, Beete und Vorplatzpflaster ergänzen die Architektur. Wiederholte Dekoration bleibt instanziert. Gebäudestufe, Kosten und Spielregeln wurden nicht verändert.

Ein ringförmiger Weg hält die Gebäudefläche frei. Die Hauptachse führt von der Burg zum südlichen Stadttor. Allianzhalle, Lagerhaus und Schatzkammer wurden mit ihren Zufahrten neu platziert; Brunnen und Sitzbereich stehen seitlich vor der Burg. Beschriftung, Aktionsanker, Startansicht und Kamerafokus wurden auf die größere Burg abgestimmt.

Geprüft: Syntax der drei geänderten JavaScript-Module und der PHP-Ansicht; Browser-Gesamtansicht, Festungsfokus und Handyformat 390 × 844. Alle Gebäude stehen innerhalb der Mauer mit freien Abständen. Die Gebäudeaktionen bleiben unter dem Burgeingang. Die vier Laufzeitdateien wurden in Hauptprojekt und isolierter Spieltestkopie synchronisiert. Kein Gebäudekauf oder Datenbankeingriff und keine Veröffentlichung.


## Gebäudeverteilung und ausgearbeitete Stadtgebäude – 10. September 2026

Die Burg bleibt bei X 0 / Z 0. Alle übrigen Stadtgebäude wurden auf größere Abstände verteilt, mit eigenen Vorplätzen und neu verbundenen Wegen. Der vorhandene Inselumfang und die umlaufende Mauer bleiben erhalten.

Akademie, Kaserne, Krankenhaus, Lager, Schatzkammer, Allianzhalle, Markt, Bauernhof, Holzfällerlager, Goldmine, Steinbruch, Wachturm und Stadttor erhalten zusätzliche 3D-Details entsprechend ihrer Funktion. Dazu gehören Dachziegel, Fensterfassungen, Silobänder und Leitern, gestreifte Marktstände mit Waren, eine offene Glockenstube, Medizinbrunnen, Waffengestelle, Holzstapel und Säge sowie Förder- und Ladeeinrichtungen. Wiederholte Teile werden pro Gebäude und Material instanziert.

Das kompakte Aktionsmenü orientiert sich jetzt an der projizierten Grundfläche des Gebäudes und wird horizontal mittig darunter angezeigt. Beim Stadttor zählt nur die Torgrundfläche. Ein freier Kartenklick schließt die Auswahl samt Details weiterhin.

Geprüft: JavaScript- und PHP-Syntax, Gesamtansicht, Burg- und Marktmenü, Smartphoneansicht 390 × 844, Detailkarte und Schließen per Kartenklick. Keine Browserfehler bei der Prüfung. Reale Gebäudestufen und Ressourcen wurden durch diese visuellen Änderungen nicht verändert.

## Ausbildungsablauf und laufende Aufträge – 10. September 2026

Die Kaserne zeigt ihren vorhandenen Ausbildungsplatz jetzt oben im Dialog mit Truppengattung, Stufe, Menge, Fortschrittsbalken und Restzeit. Nach Ablauf wartet die Anzeige auf die serverseitige Gutschrift. Die letzten drei tatsächlich verarbeiteten Trainingsaufträge erscheinen mit Abschlusszeit unter „Zuletzt abgeschlossen“, auch nach einem Neuladen. Neue bestätigte Abschlüsse erzeugen eine schließbare Meldung in der Stadt. Ein verschwundener oder nur zeitlich abgelaufener Auftrag reicht dafür nicht aus: Die Meldung verwendet ausschließlich `recent_training` aus dem Serverzustand. Abgebrochene Aufträge werden in der bestehenden API gelöscht und gehören nicht zu diesem Verlauf.

Kosten und Vorrat sind eindeutig beschriftet. Die Mengenwahl zeigt die mit den vorhandenen Ressourcen mögliche Anzahl und bietet „Maximal“. Leere Eingaben, Bruchzahlen, null, negative und überhöhte Mengen starten kein Training; die API nimmt für Truppencode, Menge und Ausbildungsplatz nur echte JSON-Ganzzahlen an. Bestehende 2D-Clients bleiben kompatibel, ein ausgelassener Ausbildungsplatz bedeutet weiterhin Platz 1. Die drei Gattungen und fünf Stufen mit ihren bisherigen Kosten, Zeiten und Akademieanforderungen bleiben erhalten.

Ein erfolgreicher Trainingsstart fordert einen neuen Spielstand an, auch wenn zuvor schon eine ältere Abfrage lief. Ohne aktuelle Verbindung ist weiteres Training gesperrt. Netzwerkfehler und unerwartete Serverfehler gelten als unbestätigt; die Sperre und ihre Erklärung bleiben beim Schließen und Wiederöffnen erhalten. Es gibt keine automatische Wiederholung eines Trainingsauftrags. Die Anzeige bietet die Prüfung des Spielstands und anschließendes Neuladen an.

Die bisherige Aufgaben-Vorschau heißt jetzt „Aufträge“. Sie listet laufende Bau-, Forschungs- und Trainingsaufträge mit Restzeit und Fortschritt auf; ein Antippen öffnet das zugehörige Gebäude beziehungsweise seine vorhandene Funktion. Die Anzahl steht in der unteren Navigation. Tastaturfokus bleibt bei Statusabfragen und beim Wechsel von Gattung oder Stufe erhalten. Profil → Truppen ansehen führt direkt zur Kaserne in der 3D-Stadt.

Prüfung:
- JavaScript-/PHP-Syntax und alle zehn bestehenden MVP-Regelprüfungen bestanden.
- Neue wiederholbare Prüfung: `C:/xampp/php/php.exe tests/training_smoke.php http://localhost/conquer-3d-playtest`. Sie akzeptiert ausschließlich die lokale Playtestadresse und erzeugt eine eigene Teststadt. Geprüft werden Anmeldung/CSRF, falsche Datentypen und Grenzen, gesperrte Stufen, fehlende Ressourcen, zwei gleichzeitige Sitzungen, genau ein belegter Platz, korrekte Kosten und echte zwölf Sekunden Ausbildungszeit, Wiederladen, Offline-Abschluss, bestätigter Verlauf und einmalige Gutschrift. Abschließender Lauf erfolgreich: `Train3D653a7c9f6716` (erster Prüflauf: `Train3Db6172602ffa0`). Je Teststadt wurden vier Bogenschützen ausgebildet.
- Unabhängige lokale JS-/DOM-Prüfungen für Eingaben, unklare Antworten inklusive HTTP 500, fehlgeschlagene Zustandsabfragen, frische Abfrage nach Start, Wiederöffnen, Gebäudewechsel während einer Anfrage und Meldungen ohne doppelte Anzeige bestanden.
- Browserprüfung bei 390 × 844: leerer Auftragsdialog, Profilzugang, gesperrte Stufe, Null-/Bruchzahl, Maximalmenge, Fokus und kein horizontaler Überlauf. Im bestehenden isolierten Konto `BauAnsicht374484` vier Bogenschützen trainiert: 160 Nahrung, 80 Holz, 40 Gold; zwölf Sekunden. Auftrag nach Neuladen in der Übersicht sichtbar, Kaserne direkt erreichbar, danach Bestand vier → acht und bestätigte Abschlussmeldung. Keine Browserwarnungen oder -fehler bei der Prüfung.

Der aktuelle lokale Quellstand wurde zuerst aus `C:/xampp/htdocs/conquer` in den neuen Worktree übernommen, einschließlich der bisherigen uncommitteten Entwicklung; lokale Konfigurationen blieben ausgeschlossen. Die Änderungen dieser Runde sind im Hauptprojekt und in der isolierten Playtestkopie synchronisiert. Keine Änderungen am Stadtmodell, an Gebäudestufen, an der Originaldatenbank oder am Hosting; kein Commit und kein Push.

Weiterhin getrennte Folgearbeiten: Ausbildungsboni aus Forschung/VIP, Forschungsfreischaltungen zusätzlich zu Akademiestufen und mehrere Kasernen. Abbrechen, Beschleunigen und Befördern werden in diesem 3D-Menü weiterhin nicht angeboten. Vor einer Anbindung sind insbesondere der verlorene fertige Anteil beim alten Abbruch-Endpunkt, der Itemverbrauch bei bereits abgelaufenen Aufträgen und die fehlenden Freischaltungs-/Platzprüfungen der alten Beförderungs-API zu korrigieren. Die Machtanzeige heißt weiterhin ausdrücklich Gebäudemacht.

## Kompakte Kaserne ohne Scrollen – 10. September 2026

Die Kasernenfunktion besitzt jetzt die beiden Ansichten „Ausbilden“ und „Verlauf“. Die Trainingsansicht enthält nur den aktuellen Ausbildungsplatz, Gattung, Truppenbild mit Bestand und Basiswerten, fünf Stufen, Kosten/Vorrat, Menge und Startknopf. Der letzte Trainingsverlauf steht in einer eigenen umschaltbaren Ansicht. Auf breiten Bildschirmen stehen Truppenbild und Formular nebeneinander; im Hochformat werden Bild und Werte zu einer kleinen gemeinsamen Zeile. Laufende Aufträge behalten ihre Restzeit und Fortschrittsanzeige, ohne denselben Status mehrfach zu erklären.

Die Hauptansicht und der Verlauf bleiben im DOM, damit Liveupdates und ausstehende Anfragen beim Wechsel erhalten bleiben. Erneutes Öffnen zeigt die Ausbildung von oben. Mengenprüfung, Netzfehlersperre, gespeicherte Truppenvorauswahl und serverseitige Regeln bleiben erhalten. Für vergrößerte Schrift oder außergewöhnlich kleine Restflächen bleibt Scrollen technisch möglich, damit nichts abgeschnitten wird.

Geprüft im Browser: 1280 × 720, 390 × 844 und 360 × 640. Auf den geprüften Größen passen die Bedienelemente ohne Scrollen; auch bei belegtem Platz mit zusätzlichem Hinweis für eine gesperrte Stufe (360 × 640: Inhaltshöhe und sichtbare Höhe jeweils 607 Pixel). Verlaufwechsel, Fokus, Nullmenge, Wiederöffnen, laufender Countdown und anschließend bestätigter Abschluss des bereits vorhandenen Auftrags über 167 Kavallerieeinheiten funktionieren. Keine neue Ausbildung oder sonstige Kaufaktion gestartet. JavaScript-/PHP-Syntax bestanden, keine Browserwarnungen oder -fehler.

Geändert sind `building-menus.js`, `hud.css` und die Versionsverweise in `play.js` und `views/city3d.php` (`compact3`). Worktree, Hauptprojekt und isolierter Playtest sind synchronisiert; kein Commit, Push oder Deployment.


## Gebäudestil aus dem 2D-Artwork in echtem 3D – 10. September 2026

Auf ausdrücklichen Wunsch des Nutzers dient `assets/art/village2.png` jetzt als Form- und Farbvorlage für die 3D-Gebäude. Die Burg besitzt vier helle Rundtürme, große Zinnen, blaue geschwungene Dächer, Rundbogentor und blau-goldene Banner. Die Akademie erhält ein violettes Zauberdach, Mondbanner und Kugelstab; die Kaserne ein rotes Walmdach, Schwerterwappen und einen offenen Übungshof. Der Bauernhof ist ein orangefarbenes Haus mit Gaube, Heuballen und Weizenfeld. Das Holzfällerlager ist eine braune Fachwerk-Wassermühle mit eigenständig animiertem Rad. Die Goldmine besitzt einen zusammenhängenden Felsbogen, einen dunklen räumlichen Tunnel, Förderwagen, Schienen, Laterne und Eimerzug.

Gemeinsame Materialien in `storybook-style.js` sorgen für kräftige Farben, breite Toon-Schattierung und dunkle Konturen an den großen Formen. Die Lichtantwort dieser Materialien wird auf das vorhandene helle Landschaftslicht abgestimmt. Die übrigen Gebäude verwenden dieselbe Palette, einfache Dachreihen und warme Bogenfenster. Kleine Wiederholungen bleiben instanziert. Die ausblendbare Stadtmauer erhält keine zusätzlichen Konturhüllen, damit bei reduzierter Deckkraft keine dunklen Vollflächen entstehen. Der Wachturm behält seine offene Glockenstube, nun mit geschwungenem Dach und klaren Konturen.

Neue Module: `storybook-style.js`, `storybook-core.js`, `storybook-production.js`, `storybook-mill.js`. Integration in `scene.js`, `full-city.js`, `district-details.js`, `fortress-cartoon.js` und Versionsverweis der Ansicht (`storybook1`). Gebäude-IDs und Positionen bleiben erhalten. Der Akademieweg reicht an die neue Treppe; Kasernen- und Minenbeschriftung liegen höher. Das Abschlusszeichen der Burg sitzt wieder sichtbar vor der neuen Fassade. Das kompakte Kasernenmenü und die serverseitigen Spielregeln sind unverändert.

Geprüft: Syntax aller acht beteiligten JS-Module und der PHP-Ansicht; Erzeugung der Modelle mit der tatsächlichen lokalen Three.js-Version, endliche Geometrien und passende Abmessungen; Browser-Gesamtansicht und Nahansichten der sechs Vorlagengebäude; direkter Klick auf das Burgmodell sowie Schließen per freiem Kartenklick. Desktop 1280 × 720 und Handy 390 × 844 geprüft. Das Kasernenmenü passt auf dem Handy weiterhin ohne Scrollen (sichtbare und tatsächliche Dialoghöhe jeweils 620 Pixel, kein horizontaler Überlauf). Keine Browserwarnungen oder -fehler. Keine Ausbildung, Bau-, Forschungs- oder Kaufaktion gestartet.

Der geprüfte Stand ist im Worktree, im lokalen Hauptprojekt und in der isolierten Playtestkopie synchronisiert. Die vorherigen Modell- und Szenendateien liegen unter `C:/Users/svenm/Documents/Codex/Conquer-Backups/before-storybook-20260910-124246`. Kein Commit, Push oder Hosting-Deployment.

## Belebte Stadt und freiere Gebäudeplätze – 10. September 2026

Der Steinbruch steht weiter im Stadtinneren bei X 19 / Z 16 und bleibt dadurch auch in der Gesamtansicht vollständig vor der Mauer sichtbar. Die Allianzhalle wurde von X -9 / Z -14 nach X -14 / Z -20 versetzt. Beide Zufahrtswege folgen den neuen Standorten; die Allianzhalle besitzt nun einen deutlich eigenen Vorplatz außerhalb des Burgkreises.

Das neue Modul `city-life.js` belebt die freien Grünflächen. Zehn Fußgänger laufen auf sechs geschlossenen Wegschleifen. Sie tragen unterschiedliche Kleidung und bewegen Arme, Beine und Körper beim Gehen. Sieben Arbeiter bleiben an ihren Einsatzorten: zwei Bauern bearbeiten das Feld, zwei Steinbrucharbeiter schwingen Hämmer, weitere Figuren arbeiten an Mine, Mühle und Markt. Drei blau gekleidete Wachen patrouillieren auf getrennten Abschnitten der südlichen Stadtmauer und meiden die Toröffnung.

Blumenbeete, Grasbüschel, einzelne Felsen, Handkarren und Wegweiser füllen freie Flächen, ohne Straßen oder Gebäudeaktionen zu verdecken. Sieben Schmetterlinge kreisen und flattern über mehreren Beeten. Diese Bewegungen ergänzen die vorhandenen Banner, das Mühlrad und den Mühlenrauch. Der Pauseknopf hält auch die neuen Animationen an. Wiederholte Pflanzen und Steine werden als Instanzen gezeichnet.

Geprüft wurden JavaScript- und PHP-Syntax, die Gesamtansicht, die versetzten Gebäude, Nahansichten von Steinbruch und Allianzhalle, wandernde Fußgänger, arbeitende Figuren sowie die Mauerwachen. Ein zunächst an der geschlossenen Wegkurve auftretender Übergangsfehler wurde auf direkte Kurveninterpolation umgestellt und mit der neuen Cacheversion `life3` korrigiert. Seitdem entstehen keine neuen Browserwarnungen oder Laufzeitfehler. Es wurde keine Bau-, Ausbildungs-, Forschungs- oder Kaufaktion ausgeführt.

## Kollisionsfreie Fußwege und begehbarer Wehrgang – 10. September 2026

Die Fußgängerwege verwenden ab `life4` geradlinige Abschnitte entlang der tatsächlich sichtbaren Straßen. Die vorherigen geglätteten Kurven konnten zwischen Stützpunkten nach innen ausschlagen und dadurch Gebäude oder Dekoration schneiden. Acht festgelegte Wegfolgen verbinden nun den Burgring und die Zufahrten zu Akademie, Krankenhaus, Bauernhof, Mühle, Markt, Steinbruch und Mine. Hin- und Rückwege folgen denselben Straßen. Drei leicht versetzte Laufspuren verhindern, dass Fußgänger exakt ineinander laufen. Handkarren wurden aus den Laufspuren auf freie Rasenflächen verschoben.

Die umlaufende Stadtmauer besitzt jetzt einen durchgehenden, kontrastierenden Laufstreifen. Die großen Zinnen stehen in einer äußeren und einer inneren Reihe mit freiem Raum in der Mitte. Die drei Wachen laufen auf dieser Ebene statt über den bisherigen mittig stehenden Zinnenblöcken; ihre Fußhöhe entspricht der Oberkante des Wehrgangs. Die Toröffnung bleibt frei, und die Patrouillen wenden vor ihr.

Geprüft wurden Syntax, Gesamtansicht, Straßenläufe, die Laufhöhe der Wachen und der Wehrgang in der Nahansicht bei ausgewählter Mauer. Die Wachen wechseln sichtbar ihre Position, Fußgänger bleiben auf den Straßen, und die Browserprüfung zeigt für `life4` keine Warnungen oder Fehler. Spielzustand und Gebäudefunktionen bleiben unverändert.

## Illustrierte Wege, Bäume und Bewohner – 11. September 2026

Die Straßen bestehen ab `village2` aus einer warmen Erdfläche mit unregelmäßig versetzten Pflasterplatten, kleinen Gebrauchsspuren und locker gesetzten Randsteinen. Die Details werden in drei Instanzgruppen gezeichnet und bleiben flach, damit Fußgänger ihre bisherigen kollisionsfreien Routen weiter nutzen können.

Die alten symmetrischen Nadelbäume wurden durch zwei gemalte Silhouetten ersetzt: krumme Stämme mit sichtbaren Wurzelansätzen tragen entweder asymmetrische Wolkenkronen mit einzelnen roten Früchten oder versetzte, gedrungene Nadelkronen. Toonfarben und gezielte Konturen verbinden sie optisch mit den Gebäuden.

Bewohner haben größere Köpfe, kürzere Körper, ausgestellte Tuniken, Gürtel, Hände und breite Stiefel. Augen, Nase und Haarsträhne machen sie auch aus der Spielkamera lesbar. Überzeichnete Strohhüte, Mützen, Helme, Schilde und Werkzeuge unterscheiden Spaziergänger, Bauern, Handwerker und Wachen. Beim Gehen bewegen sie sich mit stärkerem Armschwung, kurzen Schritten, leichtem Wippen und seitlicher Körperneigung.

Geprüft wurden JavaScript- und PHP-Syntax, die Spielansicht mit warmen Pflasterwegen, beide Baumformen, Fußgänger auf den Wegmitten und die laufende Animation. Der beim ersten Browserlauf entdeckte lokale Batcher-Verweis wurde behoben; der anschließende vollständige Aufbau erzeugt keine neuen Browserwarnungen oder Laufzeitfehler. Keine Spielaktion wurde ausgelöst.

Mit `village3` ersetzt eine nahtlos wiederholte, handgemalte Canvas-Textur die großen sichtbaren Pflasterrechtecke. Erdige Farbflecken, feine Körnung und eingelassene unregelmäßige Steine bilden eine zusammenhängende Oberfläche; dieselbe Textur liefert eine sehr leichte Höhenstruktur. Nur vereinzelte 3D-Steine bleiben an den Rändern. Dadurch wirken die Wege organischer und benötigen zugleich weniger Geometrie und Zeichenaufrufe.

## Organischere Gebäude und Bewohner – 11. September 2026

Ab `storybook3` erzeugt das gemeinsame Materialsystem für jede Farbe eine eigene kleine handgemalte Oberflächenvariation. Neue Storybook-Materialien und ältere, über `shadeStorybookRoot()` angeglichene Gebäudeteile erhalten diese Oberfläche automatisch. Der direkte Vergleich mit `village2.png` zeigte anschließend, dass die zunächst stärkere Körnung zu realistisch wirkte. Ab `storybook5` bleiben nur noch sehr weiche großflächige Abweichungen und ein kaum sichtbares Relief erhalten; Wände sind heller und wärmer, während die kräftigen Dachfarben und dunklen Konturen bestehen bleiben.

Die Bewohner verwenden ab `village5` gerundete Kapseln für Arme und Beine sowie geformte statt rechteckige Schuhe. Weichere Nasen, Wangen, seitliche Haarsträhnen, leicht geneigte Köpfe und gewölbte Schilde lockern die identischen Grundkörper auf. Der Abgleich mit den sechs Charakterillustrationen führte in `village7` zu Köpfen mit etwa 40–50 Prozent der Gesamthöhe, kürzeren Körpern, größeren Augen und überzeichneten Hüten. Zivilisten und Arbeiter bleiben gut lesbar; Mauerwachen behalten ihre sichere Laufposition.

Die Wege wurden im selben Referenzabgleich beruhigt: helle warme Flächen, wenige große Tonabweichungen und vereinzelte flache ovale Spuren ersetzen die zuvor dichte helle Kieszeichnung. Das Verhältnis von ruhiger Fläche zu einzelnen Details entspricht nun stärker `village2.png`. Gleichzeitig verteilen ein stärkeres Umgebungslicht und schwächeres Sonnenlicht die Schatten weicher.

## Vollständige Vorschau in der Haupt-App – 11. September 2026

Die Haupt-App bindet die 3D-Stadt unter `/city#city` über `/city/3d?embed=1` ein. Dieser Modus besitzt ab `village8` eine eigene Darstellung: Profil, Ressourcen, Statusfuß und untere Navigation des inneren Frames werden ausgeblendet, weil die Haupt-App diese Elemente bereits bereitstellt. Die Kameratasten bleiben erreichbar und halten Abstand zu den äußeren Aktionsknöpfen.

Die eingebettete Kamera startet in einer vollständigen Übersicht. Dadurch sind alle 14 Gebäude, die Stadtmauer, Wege, Bäume und Bewohner gleichzeitig sichtbar; Nahansichten bleiben über Vergrößern und Verschieben möglich. Die eigenständige Playtestseite behält ihre nähere Startansicht.

Bei der Prüfung zeigte die lokale Haupt-App nach einem Neuladen zunächst eine fehlende Datenbankspalte. Die bereits vorhandene Migration `0072_multiworld_context.sql` wurde lokal angewendet. Danach laden Haupt-App und eingebettete 3D-Stadt wieder vollständig. Künftige Sichtprüfungen müssen sowohl die eigenständige 3D-Seite als auch `/city#city` umfassen.

### Mobiles Umland und Touch-Steuerung

Im Hochformat startet die eingebettete Stadt näher am bespielten Bereich und leicht nach unten versetzt, damit das Dorf zwischen Ressourcenleiste und Navigation lesbar bleibt. Die Home-Taste stellt weiterhin die vollständige Gesamtansicht her. Außerhalb der Mauer werden eine gemalte Wiesenstruktur, zusätzliche Bäume und kleine Felsen gerendert, damit die freien Flächen auf hohen Displays zur Szene gehören.

Gebäudenamen nehmen an der gemeinsamen Pointer-Gestensteuerung teil. Eine Wisch- oder Zwei-Finger-Geste darf deshalb auch dann schwenken beziehungsweise zoomen, wenn ein Finger auf einem Gebäudenamen beginnt. Nur eine Bewegung ohne nennenswerten Versatz gilt als Gebäudeklick.

### Ressourcen im mobilen HUD

Unter 700 Pixel Breite stehen die vier Ressourcen in einem Raster aus zwei Spalten. Jede Kachel zeigt ein großes Symbol, den vollständigen formatierten Wert und die Ressourcenbezeichnung. Fünfstellige Werte dürfen weder abgeschnitten werden noch mit dem Nachbarwert kollidieren. Bei sehr schmalen Geräten werden Schrift und Symbol moderat verkleinert, das 2×2-Raster bleibt erhalten. Im Querformat steht der Block rechts neben dem verkleinerten Profil; Wert und Bezeichnung stehen dort platzsparend untereinander. So bleibt die Kartenmitte frei.

### Zielauswahl auf der Weltkarte

Ein einfacher Klick auf ein Dorf, ein Monster, eine Rohstoff-Farm oder ein freies Feld öffnet immer das kleine kontextbezogene Popup direkt auf der Karte. Der Klick darf kein allgemeines Modalmenü öffnen. Bei Dörfern, Monstern und Rohstoff-Farmen zentriert die Karte zuerst das ausgewählte Ziel. Name, Stufe und Koordinaten stehen in einem rotgoldenen Banner darüber; die sechseckigen Schnellaktionen bilden einen flachen Bogen mit sichtbarem Abstand unterhalb des Ziels. Das ausgewählte Dorf, Monster oder Farmgebäude bleibt in der Mitte vollständig sichtbar und erhält statt eines harten Feldrahmens einen weichen goldenen Fokus. Dunkle Beschriftungskapseln halten die Aktionen auf jedem Kartenuntergrund lesbar. Ein fremdes Dorf zeigt Profil, Solo-Attacke, Debuff, Rally und Spähen. Das eigene Schloss zeigt Profil, Skin und Dorfübersicht. Monster zeigen Details, Angreifen und Teilen; Rohstoff-Farmen zeigen Details, Sammeln und Teilen. Ausführliche Ansichten oder Marschdialoge öffnen erst nach einer ausdrücklichen Auswahl im Popup.

Monster und Rohstoff-Farmen verwenden einen besonders knappen vertikalen Abstand zwischen Zielgrafik, Banner und Aktionsbogen. Beim Angriff auf ein normales Monster öffnet sich ein dreigeteiltes Angriffsfenster nach dem Vorbild der Referenz: links Monsterbild, Stufe, Koordinaten, Lebenspunkte und mögliche Beute; in der Mitte die verfügbaren Truppen mit Reglern; rechts Auswahl, Marschkapazität, Laufzeit und Aktionspunkte. Die Hauptaktion ist ein großer roter „Angreifen“-Knopf. Für Monsterdefinitionen vom Typ Rally verwendet dieselbe Ansicht zusätzlich die Rally-Dauer und den roten „Rally starten“-Knopf.

Der Chat ist als feste, flache Leiste am linken unteren Kartenrand in der Weltkarte und in der Dorfübersicht sichtbar. Er bleibt auch während eines Ziel-Popups geöffnet und wird nur ausgeblendet, wenn eine bildschirmfüllende Detailansicht oder ein Dialog die Karte ersetzt. Das Ziel-Popup hält Abstand zum oberen HUD, zum Chat und zur unteren Navigation, damit Titel, Schließen-Schaltfläche und alle Aktionen erreichbar bleiben.

### Spielerprofile

Das eigene Profil und fremde Spielerprofile folgen einer gemeinsamen dunkelblauen Fantasyoberfläche. Links steht ein hoher rotgoldener Herrscherbanner mit Chibi-Porträt, Name und Burgstufe. Rechts erscheinen Name, Macht, besiegte Gegner, Allianz und Welt als breite Informationszeilen. Das eigene Profil ergänzt Edelsteine, Prestige, Lord-Stufe, Aktionspunkte sowie die Bereiche Geschichte, Truppen, Meisterschaft, Schätze und Rangliste. Ein fremdes Profil ist kompakter, zeigt keine privaten Bestände und bietet Nachricht, Chat, Geschichte, Allianz, Meisterschaft und Schätze. Beide Ansichten müssen ohne horizontales Scrollen funktionieren und auf kleinen Bildschirmen in dieselbe kompakte Anordnung wechseln.

Geprüft wurden Syntax, vollständiger Szenenaufbau, Gesamtansicht und Nahansicht an Burg und mehreren laufenden Bewohnern. Die Browserkonsole bleibt ohne Warnungen oder Fehler. Laufwege, Gebäudeauswahl und Spielzustand wurden nicht verändert.

### Mobile Sammlungen, Ränge und Bauschleifen – 12. September 2026

VIP und Hunter-Stufe stehen als kompakte Rangschilder direkt beim Profil. Die frühere Bezeichnung Lord-Stufe heißt in allen sichtbaren Profil- und Talentansichten Hunter-Stufe. Unter den Aktionspunkten zeigt die linke Stadtleiste zwei getrennte Bauschleifen. Bauen II liest die echte VIP-Freischaltung; vor VIP 4 führt der gesperrte Platz zur VIP-Ansicht, danach zeigt er den zweiten laufenden Bauauftrag oder „Bereit“.

Inventar und Reliktsammlung verwenden keine Seiten mehr. Die vorhandenen Kategorien und Reliktansichten bleiben erhalten, während die kleineren Karten in einer durchgehenden, vertikal scrollbaren Sammlung stehen. Vorhandene Gegenstände und Relikte erscheinen zuerst und sind innerhalb dieser Gruppe nach Stufe absteigend geordnet. Noch nicht vorhandene Inhalte folgen gedimmt und entsättigt, bleiben aber für ihre Detailinformationen auswählbar.

Die Profilbereiche und oberen Profilreiter passen im mobilen Hochformat gemeinsam in die Fensterbreite und benötigen kein horizontales Scrollen. Auf der Weltkarte sitzen die kompakteren Aktionsschilder dichter unter Dorf, Monster oder Farm; dunkle Emailleflächen, helle Innenflächen und goldene Kanten verbinden sie optisch mit dem übrigen HUD. Das gewählte Kartenobjekt bleibt dabei sichtbar.

Nach dem kostenlosen Öffnen einer Schatztruhe bleibt die erhaltene Beute direkt unter den Truhen sichtbar. Jede Gutschrift zeigt Menge und Namen; Reliktfragmente werden ausdrücklich als Fragmente bezeichnet. Die Anzeige liest sowohl die verschachtelte als auch die direkte Antwortform des Endpunkts.

Bei der Liveprüfung blockierte eine bereits im Hauptprojekt vorhandene, aber noch nicht ausgeführte Sammelmigration den Spielzustand. Die ausstehenden Migrationen 0078 bis 0080 wurden lokal angewendet; dadurch laden Stadt und Weltkarte wieder und angekommene Sammelzüge besitzen die vom Server erwartete Abschlusszeit.
