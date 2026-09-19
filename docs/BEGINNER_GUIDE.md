# Anfangsguide

Der Anfangsguide ist über **Menü → Anfangsguide** und weiterhin über `/city#help` erreichbar. Er ersetzt die alte kurze Spielhilfe. Neue Spieler mit Burgstufe 1, ohne abgeschlossene Ausbildung und ohne Forschung erhalten einmal pro Spieler und Browser einen schließbaren Willkommenshinweis. Bereits fortgeschrittene Spieler werden nicht unterbrochen.

- **Einstieg:** sechs frei anwählbare Kapitel zu Bedienung, Versorgung, Ausbau, Armee, Welt und gemeinsamen Zielen. „Gelesen · weiter“ merkt den Lesestand auf diesem Gerät.
- **Gebäude:** alle 16 regulären Gebäude mit Zweck, Tipps, aktueller Stufe, Gruppenfilter und direkten Verknüpfungen zu Ausbau und Funktion. Angezeigte Stufen kommen aus dem Spielstand.
- **Ziele:** Burgstufe 2, vier Rohstoffgebäude auf Stufe 2, 20 abgeschlossene Truppenausbildungen, eine abgeschlossene Forschung und aktuelle Allianzmitgliedschaft. Fortschritt kommt ausschließlich aus den authentifizierten Serverdaten. Diese Orientierung vergibt keine eigene Belohnung; die regulären Aufgaben bleiben unverändert.
- **Wissen:** neun aufklappbare Erklärungen einschließlich Schutz/PvP, Beschleunigern, Kontosicherheit und kooperativen Inhalten.

`assets/js/beginner-guide.js` liefert das Modul; `game.js` bindet Navigation und Aktualisierung ein. Die Oberflächen verwenden die gemeinsamen Variablen aus `village-theme.css`. Hauptreiter bleiben sichtbar, der Inhalt scrollt senkrecht. Laufende Aktualisierungen erhalten Leseposition, Fokus und offene Erklärungen. Verknüpfungen öffnen nur einen Bereich oder eine Vorschau und starten keine Spielaktion.

Nur Kapitel, gelesene Abschnitte und der bereits gezeigte Einstiegshinweis liegen im lokalen Speicher (`conquer:beginner-guide:v1:<base>:<player_id>`). Sie sind kein Spielfortschritt und werden nicht zwischen Geräten synchronisiert. Ohne verfügbaren lokalen Speicher bleibt der Guide nutzbar; nach einem Neuladen kann der Einstiegshinweis erneut erscheinen. Das ältere serverseitige `TutorialService` wird nicht verwendet oder verändert.

## Prüfung

`tests/beginner_guide_app.cjs` läuft gegen eine isolierte Vorschau von `tools/preview-feature-fixture.php --port=18976 --hud --chat`. Die Prüfung deckt alle Gebäude, Kapitel und Zielanzeigen, Aktualisierungen ohne Fokusverlust, Wiederaufnahme, Willkommenshinweis, blockierten lokalen Speicher, Escape und Browser-Zurück sowie fünf Bildschirmformate ab: 1280 × 800, 390 × 844, 320 × 568, 568 × 320 und 844 × 390. Neue-Spieler-Zustände und abgeschlossene Ausbildungsziele werden ausschließlich in gelesenen Testantworten simuliert. Der Test prüft, dass keine schreibende Spielaktion gesendet wird. Screenshots stehen unter `artifacts/beginner-guide/`.
