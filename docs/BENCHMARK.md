# Local feature service benchmark

Run from the repository root:

```powershell
C:/xampp/php/php.exe tools/benchmark-feature-pack.php
```

The CLI-only script uses `tests/Support/FeatureDatabase.php` to copy schema and
the world-1 configuration into a randomly named disposable database. It checks
that database name before inserting fixtures and removes the database afterward.
No live players are copied or changed. The normal local database configuration
must permit creation/removal of temporary databases; migrations must be current.

The fixture contains 200 synthetic accounts with one city each, 100 cities in
each of two 256×256 worlds, 13 buildings and 1,000 garrison troops per city,
20 alliances with ten members each, world/alliance chat and one upcoming Conquest
event plus invasion per world. Cities use the real placement code. Histories,
active queues, combat missions, market records and private mail are empty.

Thirty distinct players, alternating worlds, each receive one warmup package.
The same 30 players then receive one measured package containing these real
calls in order: `CityState::loadForPlayer`, `KingdomService::state`,
`CommunityService::state`, `EventService::state`. Their normal lazy updates and
database locks run. Assertions check returned city/world, 100 ranking rows,
alliance membership, community/player directories, chat and event isolation;
all four responses must encode as valid JSON. Assertion and JSON-encoding time
is excluded from the timing. P95 uses nearest rank: observation 29 of 30 after
sorting; the median averages observations 15 and 16.

Measured on **2026-09-11 at 17:16:59 UTC**, Windows, PHP CLI 8.2.12,
MariaDB 10.4.32 on the same machine, CLI OPcache disabled:

| Service | Median ms | P95 ms | Maximum ms |
| --- | ---: | ---: | ---: |
| Complete four-service package | 86.127 | 138.479 | 640.917 |
| City | 9.341 | 20.346 | 554.432 |
| Kingdom | 59.774 | 83.639 | 85.704 |
| Community | 10.355 | 16.637 | 20.745 |
| Events | 7.790 | 15.709 | 28.341 |

The largest package includes a 554 ms city-read outlier. It is retained in the
results; this run did not isolate its cause. Component medians/P95 values do not
add up to package percentiles because each distribution is sorted separately.

One additional spawn pass used 100 attempts, 100% chances and caps of 50 resource
nodes plus 50 monsters in world 1. It took **257.460 ms**, created exactly
**50 nodes and 50 monsters**, and reported zero failed placements or skipped
chances. Stored counts matched the result and world 2 remained unchanged. This
single spawn measurement has no percentile estimate; random placement can make
subsequent runs differ.

All 60 warmup/measured packages and spawn assertions passed. This is a sequential
service baseline with warm PHP/catalog/database caches, **concurrency 1**. It
does not measure HTTP authentication/serialization, network or browser latency,
simultaneous players, long-lived history tables, hosting hardware, or production
throughput. Other processes on the development machine were not controlled.

## Kontrolllauf nach Verteilung des Spawnbudgets

11. September 2026, 17:21:41 UTC, gleicher Befehl und gleiche Fixture: Paket-Median 80,678 ms, P95 100,196 ms, Maximum 101,716 ms. Spawnlauf 239,341 ms mit 50 Rohstofffeldern und 50 Monstern. Der frühere Ausreißer oben bleibt dokumentiert; diese zwei lokalen Läufe belegen keine Produktionskapazität.

## Alpha-Prüfung am 17. September 2026

Erneuter Lauf um 13:04:19 UTC mit demselben Befehl, PHP 8.2.12,
MariaDB 10.4.32, Windows und deaktiviertem CLI-OPcache. Die Gebäudeprüfung
verwendet nun die vollständige aktuelle Liste von `CityState::BUILDING_CODES`
(16 Gebäude statt der historischen 13). Weiterhin 200 synthetische Spieler,
zwei Welten, 30 Aufwärm- und 30 Messpakete, **Parallelität 1**.

| Service | Median ms | P95 ms | Maximum ms |
| --- | ---: | ---: | ---: |
| Complete four-service package | 84.054 | 109.996 | 124.075 |
| City | 10.680 | 12.768 | 13.894 |
| Kingdom | 57.822 | 80.160 | 88.165 |
| Community | 9.163 | 14.960 | 16.877 |
| Events | 6.606 | 9.838 | 10.576 |

Alle 60 Pakete und die Isolation-/JSON-Prüfungen bestanden. Der einmalige
Spawnlauf benötigte 357,448 ms und erzeugte 50 Rohstofffelder sowie 50 Monster,
ohne fehlgeschlagene Platzierungen oder übersprungene Chancen. Vollständiger
Messwertsatz: `artifacts/alpha-audit-2026-09-17/benchmark-current.json`.

Am Ende lief kurzzeitig eine weitere isolierte UI-Prüfung auf demselben Rechner;
die Hintergrundlast war nicht vollständig kontrolliert. Der Lauf zeigt den
aktuellen sequenziellen Entwicklungsstand. Er belegt weder HTTP-Durchsatz
noch eine Anzahl gleichzeitig aktiver Spieler auf dem späteren Alpha-Host.
Die Freigabegrenzen stehen in `ALPHA_COMBAT_AUDIT_2026-09-17.md`.
