# Alpha-Korrekturen: Kampf und Dungeon · 26. September 2026

## Behobene Ursachen

- Der Kampfrechner war an drei Stellen abgeschaltet: bedingungsloser HTTP-503 vor dem Handler, deaktivierter UI-Knopf und eine auf null gesetzte Dialogfabrik. Alle drei Sperren sind entfernt. Authentifizierung, CSRF, Welt- und Zielprüfung bleiben aktiv; der Handler begrenzt JSON auf ein Objekt mit höchstens 16 KiB.
- Die Monster-Vorschau verwendete den echten Resolver und würfelte bei jedem Aufruf neues Kampfglück. Eigene Referenzmethoden verwenden jetzt fest 0 %. Echte Einzel- und Rallykämpfe würfeln weiterhin ausschließlich serverseitig zwischen −10 % und +10 %. Der Aufrufer kann diesen Würfelwurf nicht setzen. Ergebnisfenster, Marschzusammenfassung und API nennen die Referenz sowie mögliche Abweichungen; PvP bleibt eine ausdrücklich hypothetische Rechnung ohne Auslesen der Gegnergarnison.
- Der Dungeon-Lebenszyklustest verwendete eine Grenzarmee: Im reproduzierten Fehlfall blieb der Boss nach 18 Runden bei 22 LP. Sein Erfolgsfall setzt jetzt die vorhandenen 20.000 statt 10.000 T1-Truppen pro Teilnehmer ein. Kampfregeln, Zufallsstartwert und Ergebnisse bleiben unverändert. Der echte Niederlagenfall und alle Reservierungs-/Auszahlungsprüfungen bleiben bestehen.
- Regionale Bosse vergaben Gegenstand 10300005 ohne Inventardefinition. Die Allianzmünze ist nun korrekt benannt und bebildert, bleibt als bereits vergebene Belohnung sichtbar und wird nicht als direkt verwendbar ausgegeben. Es wird kein noch nicht implementierter Allianzshop versprochen.
- Die historische Monsterbalanceprüfung verwendete noch Angriff gegen LP+Verteidigung. Sie prüft jetzt die tatsächlichen Machtgrenzen, deren Skalierung mit verbleibenden LP und die neutrale Solo-Referenz für alle 102 Progressionsmonster sowie den Tutorialgegner.

## Nachweise

Alle Datenbankläufe verwenden ausschließlich `ConquerTests\FeatureDatabase`; bestehende Nutzer-/Spielstände wurden nicht verändert. Testläufe wurden mit den anderen Agenten nacheinander koordiniert.

Bestanden:

- `tests/mvp_rules.php`: Produktion, Vorräte, echte Kämpfe, stabile Referenz und begrenztes aufgezeichnetes Glück.
- `tests/battle_preview.php`: Referenzberechnung, Truppenerhaltung, ungültige Eingaben, Ziel-/Weltisolation, PvP-Beispiel und keine Spielmutationen (HTTP-Erweiterung siehe unten).
- `tests/battle_luck.php`: Grenzen/Schrittweite, 32 echte Auflösungen gegen eine exakte Siegesschwelle, keine Steuerung durch injizierte Seed-/Glücksmetadaten, stabile 0%-Referenz.
- `tests/dungeon_lifecycle.php`: 77 Prüfungen einschließlich paralleler Reservierung/Start/Auszahlung, echtem Sieg und Niederlage, automatischer Rückkehr, Offlinefortsetzung, HTTP-Authentifizierung und CSRF.
- `tests/dungeon_rules.php`, `tests/monster_reports.php`, `tests/monster_rallies.php`.
- `tests/monster_balance.php`: 102 Progressionsdefinitionen plus Tutorial.
- `tests/monster_reward_balance.php`: 221 Prüfungen.
- PHP-/JavaScript-Syntax der geänderten Module.

Die ergänzten `tests/battle_preview_http_cases.php` sind über `battle_preview.php` vollständig bestanden: echter HTTP-Handler mit Authentifizierung, CSRF, JSON-Grenzen, falscher Welt, fremdem/gesperrtem Ziel, ignorierten Client-Glücksparametern und unverändertem Spielzustand. Öffentliche Spielerprofile liefern keine fremden Garnisonen mehr; verborgene, gesperrte und weltfremde Städte bleiben verborgen. Ungültige Spieler-IDs werden abgelehnt. Der isolierte Integrationslauf `tests/battle_preview_ui.cjs` ist in 1280×720, 390×844, 320×568 und 844×390 bestanden. Er lädt auch die echte mobile Navigation und prüft Browser-Zurück/Escape, unveränderte Marschauswahl, erneute Berechnung und ausbleibende Entsendung. Ein zusätzlich im vollständigen App-Lauf gefundener Fehler wurde behoben: Das Schließen des Rechners konsumiert nur seinen eigenen Verlaufseintrag, statt zugleich den Elternmarsch zu schließen. Der vollständige App-Lauf `alpha_final_app.cjs` ist anschließend ebenfalls bestanden (zentraler Runner, 26.09.2026 12:47:55 UTC; `artifacts/alpha-audit-2026-09-26/final/alpha_final_app.result.json`). Ein weiterer gezielter Kartenregressionstest stellt sicher, dass Eingaben in einem geöffneten Dialog nicht den darunterliegenden Kartenverlauf schließen.

## Nachprüfung weiterer Kampfsuiten

`congress_lifecycle.php` und `research_live_services.php` sind bestanden. Beide alten Erwartungen beruhten auf früheren Truppenwerten. Aktuelle T1-Infanterie hat Angriff 1, Verteidigung 6 und LP 6: 1.000 T1-Angreifer schlagen 100 Verteidiger daher nicht. Der gezielte Erfolgsfall verwendet 2.000 Angreifer; der spätere Offlinefall eine vorhandene 5.000er-T5-Armee innerhalb der tatsächlichen Marschkapazität. Niederlage, Verwundete, Rückkehr und Wechsel der Besatzung bleiben real geprüft. Arena-Forschung wird unabhängig mit aktuellen Truppenwerten und den in `battle.json` belegten 15 % Angriff auf Stufe 5 nachgerechnet; beide Armeen bleiben unverändert. Auch dieser Test verwendet jetzt ausschließlich FeatureDatabase.
