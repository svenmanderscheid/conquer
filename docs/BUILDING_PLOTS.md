# Zusätzliche Bauplätze – entfernt

Die zusätzlichen Bauplätze wurden am 12. September 2026 auf Nutzerwunsch aus dem Spiel genommen. Es gibt keine Platzschilder, Grundstücke, Erschließungswege, Kameraeinstiege oder Baumenüs mehr. Die regulären Produktionsgebäude stehen wieder im vergrößerten Dorfinneren; siehe VILLAGE_LAYOUT.md.

BuildingPlotService::snapshot() und queue() liefern leere Listen. Der bisherige Bau-Endpunkt lehnt Neubau und Ausbau ohne Kostenabzug ab. Auch ältere Clients können keine weiteren Zusatzgebäude in Auftrag geben.

Bereits gespeicherte Zeilen werden nicht gelöscht. Ihre vorhandene Produktion und Ausbildungsboni bleiben erhalten, laufende Aufträge schließen regulär ab und blockieren keine normalen Baumeister. Die Migration 0081 und die interne Abrechnung bleiben für diese Altbestände bestehen.

tests/building_plots.php prüft diese Stilllegung in einer isolierten Testdatenbank. tests/village_viewport.cjs prüft in der echten 3D-Szene, dass kein Zusatzbauplatz oder entsprechendes Bedienelement mehr erscheint und alle vier Produktionsgebäude innerhalb der neuen Mauer liegen.
