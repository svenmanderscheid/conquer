# Union of Kingdoms – Layout-Editor

## Benutzung

Im Backoffice unter **Oberfläche → Layout-Editor** (`/admin/layout`). Lokal: `http://localhost/conquer/admin/layout`.

1. Mit einem Administratorkonto anmelden. Moderatoren können ansehen, aber nicht speichern.
2. Die Vorschau zeigt das tatsächliche Spiel im selben Ursprung. Sie braucht zusätzlich die normale Spielanmeldung. Falls die Anmeldung erscheint: anmelden, anschließend **Vorschau neu laden** wählen.
3. Hochformat, Querformat oder Desktop und eine Vorschaugröße auswählen. Eigene Größen sind von 320 bis 3840 Pixel Breite und 320 bis 2160 Pixel Höhe möglich. „Größe anwenden“ wählt das dazugehörige Layoutprofil automatisch. Die Vorschau wird bei Platzmangel verkleinert dargestellt, verwendet intern aber die eingestellten Abmessungen.
4. Einen Bereich über die Suche, die gruppierte Auswahlliste oder direkt in der Vorschau auswählen. HUD-Gruppen, einzelne Ressourcen und Navigationsknöpfe, Profil, Statusanzeigen, Aufträge, Kartenwerkzeuge und die 22 Hauptfenster sind registriert. Zustandsabhängige Bereiche erscheinen erst, wenn sie im Spiel sichtbar sind.
5. Markierte Bereiche lassen sich mit Maus oder Touch ziehen. Die acht Rand-/Eckanfasser ändern ihre Größe; Regler und Zahlenfelder bieten dieselben Werte. Eine Verankerung richtet den Bereich am Bildschirmrand oder in der Mitte aus. Pfeiltasten bewegen um einen Pixel, Umschalt+Pfeiltaste um zehn. Optionales Raster: 4, 8 oder 16 Pixel.
6. Über „Ansicht“ Dorf, Weltkarte oder ein Hauptfenster öffnen. Die Auswahl eines Hauptfensters in der Bereichsliste öffnet es ebenfalls. Aufträge oder Ressourcen werden dadurch nicht ausgelöst.
7. **Spiel bedienen / anmelden** schaltet die Bearbeitungsmarkierungen ab. In diesem Modus ist es das echte Spiel: Aktionen werden regulär ausgeführt. Für Versuche ein Testkonto verwenden.
8. Eine kurze Änderungsnotiz eintragen und **Für alle Spieler speichern** wählen. Änderungen wirken global für alle Welten nach dem nächsten Laden des Spiels.

**Rückgängig/Wiederholen** hält bis zu 100 Bearbeitungsschritte vor. Ein Ziehvorgang ist ein Schritt. „Format kopieren nach“ übernimmt alle Bereiche in das gewählte Zielformat; vor Speichern dort prüfen. JSON-Export und -Import sichern das gesamte Layout ohne Kontodaten. Import verändert zunächst nur die Vorschau, wird auf bekannte Bereiche und Werte geprüft und kann rückgängig gemacht werden.

**Tastenkürzel und Mehrfachauswahl:** Strg/Cmd+Z macht rückgängig, Strg/Cmd+Umschalt+Z oder Strg+Y wiederholt – auch bei Fokus in der Vorschau. In Textfeldern bleibt das normale Text-Rückgängig erhalten. Esc hebt die Auswahl auf und bricht einen laufenden Ziehvorgang ab, ohne das bearbeitete Spielfenster zu schließen. Strg/Cmd- oder Umschalt-Klick fügt Bereiche zur Auswahl hinzu bzw. entfernt sie. Für Touch „Mehrfachauswahl“ einschalten, Bereiche antippen oder über die Bereichsliste hinzufügen, anschließend den Schalter zum Ziehen ausschalten. Ausgewählte Bereiche bewegen sich zusammen; ausgewählte Unterelemente werden nicht doppelt verschoben, wenn ihre Gruppe ebenfalls ausgewählt ist. Größenanfasser und Zahlenfelder bearbeiten weiterhin jeweils den einzelnen Bereich. Ein gemeinsamer Ziehvorgang wird mit einem Rückgängig-Schritt zurückgenommen; bloßes Auswählen erzeugt keinen Bearbeitungsschritt.

**Änderungen verwerfen** kehrt zum zuletzt gespeicherten Stand zurück. **Bereich zurücksetzen** und **Dieses Format zurücksetzen** stellen zunächst in der Vorschau das Standardlayout wieder her; diese Rücksetzung muss ebenfalls gespeichert werden.

## Verhalten und Grenzen

- Drei getrennte Profile: Querformat bei Breite > Höhe und Höhe ≤ 600 CSS-Pixel; sonst Hochformat bis 900 CSS-Pixel Breite; sonst Desktop.
- Werte beziehen sich auf die vorhandene responsive Anordnung, nicht auf absolute Gerätepixel. Navigation und Chat behalten den Bezug zur Unterkante, Ressourcen zur Oberkante.
- Der sichtbare Versatz wird an Bildschirmrändern einschließlich sicheren Rändern begrenzt. Mindestgrößen können verhindern, dass ein Bereich so klein wird wie der Reglerwert vorgibt. Bei 320 Pixeln bleiben die sieben Navigationsziele mindestens 44 Pixel breit.
- Der geschlossene Chat wird bei Bedarf oberhalb der Navigation gehalten. Andere Überdeckungen werden in der Vorschau als Warnung angezeigt; nicht jede denkbare Kollision mit sämtlichen Spielanzeigen wird automatisch aufgelöst.
- Gruppen nehmen ihre Unterelemente beim Verschieben und Skalieren mit. Einzelne Knöpfe können zusätzlich angepasst werden. HUD-Elemente skalieren einschließlich Inhalt; Flächen wie Chat und Fenster ändern ihre Abmessungen und lassen ihren Inhalt umbrechen oder scrollen. Spielfenster werden je Menü gespeichert; zusätzliche Dialoge teilen eine allgemeine Dialogeinstellung.
- Der Editor bearbeitet Position und Abmessungen der registrierten Oberflächenbereiche. Er ist kein Inhaltseditor: Texte, Farben, Spielregeln, Gebäude, Kartenobjekte und die innere Anordnung einzelner Formularfelder werden damit nicht neu gestaltet. Die Anmeldeseite ist kein Spiel-HUD und gehört nicht zum Katalog.
- Die verkleinerte Vorschau am PC ist kein Ersatz für die Bedienprüfung auf einem echten Handy.
- Beim Speichern wird ein Konflikt mit zwischenzeitlich gespeicherten Änderungen abgewiesen. Eine verlorene Antwort kann mit derselben Vorgangskennung erneut angefragt werden, ohne doppelte Speicherung.

## Einrichtung und Architektur

Die additive Migration `0117_ui_layout.sql` legt eine einzelne globale Layoutzeile an. Lokal wurde sie eingerichtet. Auf einem anderen Server muss sie vor Verwendung des Editors ebenfalls ausgeführt werden, beispielsweise mit `php tools/migrate-ui-layout.php`. Ohne Tabelle verwendet das Spiel weiter die Standardanordnung und der Editor bleibt schreibgeschützt.

`LayoutSettings::catalog()` ist die gemeinsame serverseitige Positivliste mit stabilen Schlüsseln, Beschriftungen, Selektoren, Mindestgrößen und Darstellungsmodus. Der Server validiert ausschließlich bekannte Profile und Bereiche, Ganzzahlen (Versatz ±4096 Pixel, Größen 25–300 Prozent) und die erlaubten Verankerungen. Der Browser begrenzt die tatsächliche Darstellung zusätzlich auf den Bildschirm. Bestehende Daten mit den ursprünglichen drei Bereichen und ohne Verankerung bleiben kompatibel. Es wird kein frei eingegebenes CSS gespeichert. Das Speichern über `/admin/layout-data` verlangt Admin-Anmeldung, Superadmin-Rolle und CSRF-Prüfung. Speicherung, Vorgangsbeleg und Änderungsprotokoll laufen in der bestehenden Admin-Transaktion. Die Versionsnummer verhindert verlorene Änderungen.

Das Spiel lädt die Einstellungen serverseitig beim Seitenaufruf. `layout-runtime.js` wendet sie auf die aktuellen HUD-Elemente an und berechnet sie bei Größenwechseln und neu erzeugten Elementen erneut. Ohne eigene Einstellungen beendet sich das Modul in der normalen Spielansicht unmittelbar. Der Vorschaumodus ist nur innerhalb eines Frames mit `layout_preview=1` aktiv. Die Bearbeitungsebene verwendet ein manuelles Popover, bei modalen Fenstern innerhalb des aktiven Dialogs, damit sie dort bedienbar bleibt. Vorschau-Nachrichten werden nach Ursprung und Absender geprüft und ändern keine gespeicherten Werte. Die normale Spielansicht enthält keine Bearbeitungsmarkierungen.

## Prüfung

- `tests/ui_layout.php`: Speicherung, Rücksetzung, Wiederholschutz, Audit, Versionskonflikt, Moderatorrechte und ungültige Nutzdaten in isolierter Datenbank.
- `tests/ui_layout_app.cjs`: echte Spielvorschau, Ziehen und Größenanfasser, skalierte HUD-Inhalte, veränderbare modale Fenster, Weltkartenwerkzeuge, eigene Tabletgröße, Rückgängig/Wiederholen, Kopieren, validierter Import/Export, verschachtelte Gruppen, Speichern/Neuladen/Rücksetzen, Übernahme außerhalb des Editors, drei Profile mit Grenzwerten, sieben erreichbare Ziele bei 320 Pixeln, CSRF und fehlende Anmeldung sowie Editor in Hoch-/Querformat.
- Aufnahmen unter `artifacts/ui-layout/` wurden am Desktop und in simulierten Handyformaten visuell geprüft. Reale Tests auf Samsung Galaxy S23 Ultra und iPhone 13 stehen noch aus.
