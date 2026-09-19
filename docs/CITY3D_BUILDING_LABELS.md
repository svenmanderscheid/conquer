# Conquer – Informationen direkt am Gebäude

Stand: 9. September 2026. Lokaler Spieltest unter http://192.168.178.96/conquer-3d-playtest/city/3d.

Die große untere Gebäudekarte und das modale Ausbau-Fenster wurden ersetzt. Festung, Akademie und Kaserne tragen freistehende Namen mit goldenen Stufenabzeichen. Antippen des Gebäudes oder seines Namens öffnet runde Aktionen für Info, Ausbau und Schließen. Erst die Auswahl von Ausbau zeigt Kosten, Bauzeit und Voraussetzungen direkt am Gebäude; der kostenpflichtige Start bleibt eine separate Schaltfläche. Die Stadt bleibt bedienbar; es gibt keinen abgedunkelten Hintergrund. Die Bedienung greift auf Wunsch des Nutzers die Richtung von Kingshot auf, ist aber eine eigene Gestaltung.

Die Anzeigen werden aus den 3D-Gebäudepositionen in Bildschirmkoordinaten projiziert und folgen Kamera, Verschieben und Zoom. Einfache Kollisionsvermeidung verschiebt überlappende Anzeigen in freie Bereiche in Gebäudenähe. Im Hochformat liegen die Kameratasten unten, damit sie die Ausbauinfos nicht überdecken.

## Auftragsbalken

- Grün: Gebäudeausbau an Festung, Akademie oder Kaserne.
- Violett: Forschung an der Akademie.
- Ocker: Truppentraining an der Kaserne.

Mehrere Aufträge werden als eigene Balken mit Restzeit dargestellt. Ohne Auftrag bleibt die Fläche unter dem Namen frei. Die 3D-Schnittstelle liefert nun auch die tatsächlich gespeicherten Forschungs- und Trainingsaufträge des angemeldeten Spielers. Abgeschlossene Forschung wird vor der Zustandsantwort über den vorhandenen ResearchProcessor verarbeitet; Training und Ausbau verwenden weiterhin CityState.

Akademie und Kaserne sind als auswählbare 3D-Modelle ergänzt. Ihre Grafik ist vorläufig. Das Starten von Forschung und Training bleibt in der vorhandenen Spielübersicht; in diesem Schritt wurden deren Fortschrittsanzeigen integriert. Es gibt weiterhin nur für die drei genannten Gebäude eigene räumliche Anzeigen, nicht für sämtliche 13 Gebäudetypen des bisherigen Spiels.

## Prüfung

PHP- und JavaScript-Syntaxprüfungen bestanden. Im Browser wurden Hochformat 390 × 844, Gebäudeauswahl, ausgeklappte Infos, parallel sichtbare Ausbau-/Forschungsbalken und der Trainingsbalken geprüft. Keine erfassten Browserwarnungen oder Fehler beim Laden.

Für die gleichzeitige Sichtprüfung wurden ausschließlich im früher angelegten Testkonto `BauKlick504530` der isolierten Datenbank drei ausdrücklich synthetische, gespeicherte Aufträge angelegt. Die Testzeiten wurden anschließend nur für diese aufgezeichneten Auftrags-IDs auf fällig gesetzt. Nach dem Neuladen waren die Balken entfernt und Akademie Stufe 2 sichtbar. Diese Prüfung bestätigt Darstellung und Abschlussverarbeitung, nicht die Kosten oder Zugangsvoraussetzungen der synthetischen Testaufträge. Andere Konten und die Originaldatenbank wurden nicht dafür verändert.

Die vorhandene Ausbau-HTTP-Prüfung wird weiterhin gegen die getrennte Testkopie ausgeführt. Die vorherige reine Grafikvorschau auf Port 8766 ist von dieser Änderung nicht betroffen. Kein Commit, Push oder Deployment.
Ergebnis der erneuten Ausbau-HTTP-Prüfung: vollständig bestanden, einschließlich gleichzeitigem Start, Offline-Abschluss und erneuter Anmeldung.

Die anschließende Anpassung der Schnellaktionen wurde im Browser auch bei 390 × 844 geprüft: Gebäudeauswahl, Info, Ausbaukosten, gesperrter Start bei fehlenden Voraussetzungen und Schließen. Das Öffnen der Aktionen verbrauchte keine Ressourcen. Diese Runde änderte ausschließlich die Darstellung und Bedienung, nicht die Serverregeln.
