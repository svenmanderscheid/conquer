# Dorfmusik und Spieleffekte

## Aktionssounds (5. Oktober 2026)

Die gemeinsame Audio-Engine bietet 21 kurze Hörproben unter **Menü → Optionen
→ Musik & Klänge**. Zusätzlich zu Bestätigung, Ausbildung, Bau, Forschung,
Abschlüssen, Belohnung, Rally und Fehler gibt es eigene Klänge für entsandte
Truppen, Angriffe, Sammeln, Spähen, Rückruf, Heilung, Truhen, Käufe,
Beschleuniger, Reliktausrüstung/-aufwertung und Teleportation.

Die vom Nutzer gewählte Richtung verbindet **episch & kräftig** mit
**verspielt & gemütlich**: Armee, Angriff, Bau und Reliktaufwertung verwenden
tiefe Trommelimpulse, warme Hornharmonien und satte Schlagtexturen. Bestätigungen,
Sammeln und Einkäufe bekommen federnde Plopps und gezupfte Klänge; Forschung,
Heilung und Belohnungen märchenhafte Glöckchen mit leisen Obertönen.
Truhen, fertige Gebäude und Teleporter verbinden beide Klangwelten.
Alle Signale bleiben kurz (unter 1,2 Sekunden); gespeicherte Lautstärken gelten
weiter. Ein Effekt verwendet höchstens 24 gleichzeitig geplante Tonstimmen,
ergänzt um seine kurzen Rauschtexturen.

Alle Effekte werden im vorhandenen AudioContext synthetisiert. Kurze gefilterte
Rauschimpulse ergänzen Schritte, Holz, Metall und magisches Rauschen. Dafür
wird genau ein 0,8 Sekunden langer Mono-Puffer erzeugt und wiederverwendet;
es gibt keine zusätzlichen Audiodownloads, externen Samples oder Dienste.
Beendete Klangquellen und Filter werden getrennt und entfernt.

Die Zuordnung liegt in `assets/js/game-audio.js` bei `confirmed()`. Sie wird
von der zentralen API-Funktion erst nach erfolgreicher Serverantwort aufgerufen.
Vorschauen, Zustandsabfragen, Chat und Benachrichtigungs-Lesebestätigungen bleiben
stumm. Ergebnisfelder unterscheiden Teleporter, Beschleuniger und Pakete bei
`inventory.use`; bestätigte Käufe haben Vorrang vor enthaltenen Drops.
Die letzten 128 bestätigten Vorgangskennungen werden pro laufender Engine
und Welt abgegrenzt, damit dieselbe Antwort keinen weiteren Erfolgston erzeugt.
Diese Begrenzung ist nur Audiofeedback; die Spielregeln und dauerhaften
Vorgangsbelege bleiben auf dem Server.

Stummschaltung, Effektregler, erste Benutzereingabe und Hintergrundpause gelten
für alle Effekte. Beim Zurückkehren beginnt die Abschlussbeobachtung mit einem
stillen Ausgangsstand. Hörproben und Beschriftungen sind vollständig Englisch,
Deutsch und Französisch; Englisch bleibt Standard und Rückfall.

Prüfungen: `tests/action_audio.cjs` (46 Aktionszuordnungen, Vorschauen,
Wiederholungen, Einstellungen, Lebenszyklus), `tests/rally_audio.cjs` und
`tests/game_audio_app.cjs` (echte Haupt-App mit isoliertem Spielstand, alle
Hörproben, fünf Bildschirmformate, Übersetzungen, Pause, Musikfehler).
`tests/action_audio_render.cjs` rendert die echten Effektgraphen mit dem nativen
OfflineAudioContext des Browsers: 21 verschiedene Wellenformen, keine
Übersteuerung bei 100 % Effektlautstärke und freigegebene Klangquellen.
Es erzeugt `artifacts/action-audio/sound-preview-epic-storybook-v2.wav`
(sowie `sound-preview.wav` als aktuelle Fassung) mit allen Hörproben in
derselben Reihenfolge wie in den Optionen (je 1,2 Sekunden einschließlich Pause)
sowie ein Pegelprotokoll. Die Abnahme auf echten iOS-/Android-Geräten steht aus.

Seit dem 1. Oktober 2026 spielt Union of Kingdoms die bereits vorhandene
hellere Fassung `village-daylight-v1.wav`: eine Melodie in G-Dur,
84 BPM, 4/4-Takt, rund 45,7 Sekunden. Weiche glöckchenartige Töne, sparsame
Harfenbegleitung und leise Flötenantworten ersetzen die langsamere Meadow-Fassung.
Erzeugung: `python tools/generate-village-daylight.py` mit NumPy. Mono,
22.050 Hz, 16-Bit PCM, etwa 2 MB. Spitzenpegel 32 %, RMS etwa 6,7 % gegenüber
16,4 % bei der ersten Version: durchschnittlich rund 7,8 dB leiser.
Gespeicherte Lautstärkeeinstellungen und Spieleffekte bleiben unverändert.
Auch diese Komposition ist vollständig synthetisiert, ohne fremde Samples.
Die anderen Versionen bleiben als Vergleich erhalten.

Zuvor aktiv: `village-meadow-v1.wav`, 2:08 Minuten zurückhaltende Dorfmusik,
60 BPM, weiche Flötenphrasen, einzelne Harfentöne und leise gehaltene Akkorde.
Kein Schlagzeug, keine Glöckchenspitzen, keine Einleitung oder Schluss-Pause.
Die Harmonie an der Schleifengrenze bleibt G-add9; ausklingende Instrumente
und Raumreflexionen werden bereits beim Rendern über die Dateigrenze gelegt.
Dadurch entsteht eine periodische Wellenform ohne einen Fade zu Stille.
Erzeugung: `python tools/generate-village-meadow.py` mit NumPy. Mono,
22.050 Hz, 16-Bit PCM, etwa 5,6 MB, einmaliger Download. RMS 4,3 %, also
nochmals rund 3,8 dB unter der zweiten Fassung. Vorige Versionen bleiben erhalten.
Die Hörprobe `artifacts/audio-meadow/loop-transition.wav` enthält zwölf Sekunden
vor und zwölf Sekunden nach dem Übergang. Die Browserprüfung rendert zwei
vollständige Schleifen mit Web Audio und vergleicht beide sampleweise.

`village-morning-v1.wav` ist die erste für Union of Kingdoms erzeugte Originalmelodie: D-Dur,
75 BPM, 3/4-Takt, 16 Takte / 38,4 Sekunden. Harfenartige Arpeggien und eine
flötenartige Melodie werden vollständig synthetisiert. Es werden keine fremden
Aufnahmen, Samples oder externen Musikdienste verwendet.

Erzeugung: `python tools/generate-village-music.py` mit NumPy. Die WAV-Datei
ist bereits enthalten; Python wird im Spiel nicht benötigt. Format: mono,
22.050 Hz, 16-Bit PCM, etwa 1,7 MB. Ausklänge werden zum Schleifenanfang
zurückgeführt. Der maximale Pegel liegt bei 65 Prozent, ohne Clipping.

`assets/js/game-audio.js` lädt und dekodiert die Musik einmal nach der ersten
Benutzereingabe. Der Browser wiederholt den Audiopuffer ohne JavaScript-Timer.
Die kurzen Spieleffekte werden bei Bedarf im selben AudioContext erzeugt;
beendete Oszillatoren werden entfernt. Stummschaltung, ausgeblendete Seiten
und `pagehide` stoppen die Wiedergabe und suspendieren den AudioContext.

Einstellungen befinden sich im Spiel unter **Menü → Optionen → Musik &
Klänge**. Musik und Effekte sind getrennt schaltbar und regelbar. Die Wahl
wird lokal pro Gerät/Browser gespeichert. Leise Startwerte: Musik 20 Prozent,
Effekte 45 Prozent. Vor der ersten Interaktion wird kein Ton geladen oder
abgespielt. Musikwiedergabe ist unabhängig von den Spielregeln und API-Aktionen.

Diese erste Klanggestaltung ist bewusst zurückhaltend. Klangfarbe, Lautheit
auf Handylautsprechern sowie Unterbrechungen durch Anrufe und andere Apps
sind zusätzlich auf echten iOS- und Android-Geräten abzunehmen.
