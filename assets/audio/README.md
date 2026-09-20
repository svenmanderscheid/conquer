# Dorfmusik und Spieleffekte

Aktuell spielt `village-meadow-v1.wav`: 2:08 Minuten zurückhaltende Dorfmusik,
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

Zweite Fassung: `village-daylight-v1.wav`, eine hellere Melodie in G-Dur,
84 BPM, 4/4-Takt, rund 45,7 Sekunden. Weiche glöckchenartige Töne, sparsame
Harfenbegleitung und leise Flötenantworten ersetzen die dichtere erste Melodie.
Erzeugung: `python tools/generate-village-daylight.py` mit NumPy. Mono,
22.050 Hz, 16-Bit PCM, etwa 2 MB. Spitzenpegel 32 %, RMS etwa 6,7 % gegenüber
16,4 % bei der ersten Version: durchschnittlich rund 7,8 dB leiser.
Gespeicherte Lautstärkeeinstellungen und Spieleffekte bleiben unverändert.
Auch diese Komposition ist vollständig synthetisiert, ohne fremde Samples.
Die erste Version bleibt als Vergleich erhalten:

`village-morning-v1.wav` ist eine für Conquer erzeugte Originalmelodie: D-Dur,
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
