<?php
declare(strict_types=1);

/**
 * City View — Sprint 1 MVP (list-based fallback, SPEC §4.8)
 *
 * Variables provided by index.php:
 *   $session  array   — current session row (player_id, username, ...)
 *   $state    array   — CityState::loadForPlayer() result
 *                       or null (handled before include)
 */

use Conquer\Game\City\BuildingData;
use Conquer\Game\City\CityState;

$city      = $state['city'];
$buildings = $state['buildings'];
$queue     = $state['build_queue'];

// Format seconds into human-readable duration.
$formatTime = static function (int $secs): string {
    if ($secs < 60)   return $secs . 's';
    if ($secs < 3600) return floor($secs / 60) . 'm ' . ($secs % 60) . 's';
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
};
// Make available as a free function in the template scope.
function formatTime(int $secs): string {
    if ($secs < 60)   return $secs . 's';
    if ($secs < 3600) return floor($secs / 60) . 'm ' . ($secs % 60) . 's';
    $h = floor($secs / 3600);
    $m = floor(($secs % 3600) / 60);
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
}

// Build queue indexed by building_code for fast lookup.
$inQueue = [];
foreach ($queue as $entry) {
    $inQueue[$entry['building_code']] = $entry;
}

// Resource format helper.
$fmt = static fn (int|string $n): string =>
    number_format((int) $n, 0, '.', ',');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — <?= htmlspecialchars($city['name']) ?></title>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:       #0f172a;
            --surface:  #1e293b;
            --border:   #334155;
            --text:     #e2e8f0;
            --muted:    #94a3b8;
            --accent:   #0ea5e9;
            --gold:     #f59e0b;
            --green:    #22c55e;
            --red:      #ef4444;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* ── Top bar ── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 0.6rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .topbar-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--accent);
            white-space: nowrap;
        }

        .resources {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            flex: 1;
        }

        .res {
            display: flex;
            align-items: center;
            gap: 0.3rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 0.25rem 0.6rem;
            font-size: 0.82rem;
            color: var(--text);
        }

        .res-icon { font-size: 1rem; }
        .res-val  { font-variant-numeric: tabular-nums; }

        .topbar-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            font-size: 0.82rem;
            cursor: pointer;
            border: 1px solid transparent;
            text-decoration: none;
        }

        .btn-ghost {
            background: var(--bg);
            border-color: var(--border);
            color: var(--muted);
        }

        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

        /* ── Layout ── */
        .main {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.25rem 1rem;
        }

        .section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin-bottom: 0.75rem;
        }

        /* ── Building grid ── */
        .buildings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
        }

        .building-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.9rem;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            position: relative;
        }

        .building-card.in-queue {
            border-color: var(--gold);
        }

        .building-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text);
        }

        .building-level {
            font-size: 0.75rem;
            color: var(--muted);
        }

        .level-badge {
            position: absolute;
            top: 0.6rem;
            right: 0.7rem;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 0.1rem 0.4rem;
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--muted);
        }

        .building-card.in-queue .level-badge {
            border-color: var(--gold);
            color: var(--gold);
        }

        .queue-label {
            font-size: 0.7rem;
            color: var(--gold);
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }

        .upgrade-btn {
            margin-top: 0.4rem;
            width: 100%;
            padding: 0.3rem 0.5rem;
            background: var(--bg);
            border: 1px solid var(--accent);
            border-radius: 5px;
            color: var(--accent);
            font-size: 0.72rem;
            cursor: pointer;
            text-align: left;
            line-height: 1.4;
        }

        .upgrade-btn:hover:not(:disabled) {
            background: var(--accent);
            color: #fff;
        }

        .upgrade-btn:disabled {
            border-color: var(--border);
            color: var(--muted);
            cursor: not-allowed;
        }

        .upgrade-cost {
            font-size: 0.68rem;
            color: var(--muted);
            margin-top: 0.2rem;
        }

        .upgrade-cost .short { color: var(--red); }

        .toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.6rem 1.2rem;
            font-size: 0.85rem;
            z-index: 100;
            box-shadow: 0 4px 16px #0006;
        }

        .toast.ok  { border-color: var(--green); color: var(--green); }
        .toast.err { border-color: var(--red);   color: var(--red); }

        /* ── Wall info strip ── */
        .wall-bar-wrap {
            margin-bottom: 1.25rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .wall-label { font-size: 0.8rem; color: var(--muted); min-width: 60px; }

        .wall-bar-outer {
            flex: 1;
            height: 8px;
            background: var(--bg);
            border-radius: 4px;
            overflow: hidden;
            min-width: 120px;
        }

        .wall-bar-inner {
            height: 100%;
            background: var(--green);
            border-radius: 4px;
            transition: width 0.3s;
        }

        .wall-hp-text {
            font-size: 0.8rem;
            color: var(--text);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        /* ── Utility ── */
        .mt { margin-top: 1.25rem; }
        footer {
            text-align: center;
            padding: 2rem 1rem;
            font-size: 0.75rem;
            color: var(--muted);
            border-top: 1px solid var(--border);
            margin-top: 2rem;
        }
    </style>
</head>
<body>

<!-- Top bar -->
<header class="topbar">
    <div class="topbar-title">⚔ <?= htmlspecialchars($city['name']) ?></div>

    <div class="resources">
        <div class="res" title="Food">
            <span class="res-icon">🌾</span>
            <span class="res-val"><?= $fmt($city['food']) ?></span>
        </div>
        <div class="res" title="Lumber">
            <span class="res-icon">🪵</span>
            <span class="res-val"><?= $fmt($city['lumber']) ?></span>
        </div>
        <div class="res" title="Stone">
            <span class="res-icon">🪨</span>
            <span class="res-val"><?= $fmt($city['stone']) ?></span>
        </div>
        <div class="res" title="Gold">
            <span class="res-icon">💰</span>
            <span class="res-val"><?= $fmt($city['gold']) ?></span>
        </div>
    </div>

    <div class="topbar-actions">
        <span style="font-size:.82rem;color:var(--muted)">
            <?= htmlspecialchars($session['username']) ?>
        </span>
        <form method="post" action="/auth/logout">
            <button type="submit" class="btn btn-ghost">Logout</button>
        </form>
    </div>
</header>

<!-- Main content -->
<main class="main" x-data="cityApp()" x-cloak>

    <!-- Wall HP -->
    <?php
    $wallMax  = max(1, (int) $city['wall_hp_max']);
    $wallCur  = (int) $city['wall_hp_current'];
    $wallPct  = round($wallCur / $wallMax * 100);
    $wallColor = $wallPct > 50 ? 'var(--green)' : ($wallPct > 20 ? 'var(--gold)' : 'var(--red)');
    ?>
    <div class="wall-bar-wrap">
        <div class="wall-label">Wall HP</div>
        <div class="wall-bar-outer">
            <div class="wall-bar-inner" style="width:<?= $wallPct ?>%;background:<?= $wallColor ?>"></div>
        </div>
        <div class="wall-hp-text"><?= $fmt($wallCur) ?> / <?= $fmt($wallMax) ?></div>
    </div>

    <!-- Buildings -->
    <div class="section-title">Buildings — Castle Level <?= (int) $city['castle_level'] ?></div>
    <div class="buildings-grid">
        <?php foreach ($buildings as $code => $building): ?>
            <?php
                $queued    = $inQueue[$code] ?? null;
                $nextLevel = $building['level'] + 1;
                $cost      = BuildingData::getCost($code, $nextLevel);
                $caps      = BuildingData::getStorageCaps($buildings);
                $canAfford = $city['lumber'] >= $cost['lumber']
                          && $city['stone']  >= $cost['stone']
                          && $city['gold']   >= $cost['gold'];
                $atMax     = $building['level'] >= 30;
                $castleCap = $code !== 'castle' && $nextLevel > ($buildings['castle']['level'] ?? 1);

                // Castle requirements check for display.
                $castleReqMissing = null;
                if ($code === 'castle' && !$atMax) {
                    foreach (BuildingData::getCastleRequirements($nextLevel) as $reqCode => $reqLevel) {
                        $actual = (int) ($buildings[$reqCode]['level'] ?? 1);
                        if ($actual < $reqLevel) {
                            $reqName = CityState::BUILDING_NAMES[$reqCode] ?? $reqCode;
                            $castleReqMissing = "{$reqName} LV.{$reqLevel} required";
                            break;
                        }
                    }
                }

                $disabled  = $queued || $atMax || $castleCap || $castleReqMissing !== null;
                $buildTime = BuildingData::getBuildTime($code, $nextLevel);
            ?>
            <div class="building-card <?= $queued ? 'in-queue' : '' ?>">
                <div class="level-badge">LV.<?= $building['level'] ?><?= $queued ? ' → ' . $queued['level_to'] : '' ?></div>
                <div class="building-name"><?= htmlspecialchars(CityState::BUILDING_NAMES[$code] ?? $code) ?></div>

                <?php if ($queued): ?>
                    <div class="queue-label">⏳ done in <span x-text="countdown('<?= $code ?>')"></span></div>
                <?php elseif ($atMax): ?>
                    <div class="upgrade-cost">Max level reached</div>
                <?php elseif ($castleReqMissing !== null): ?>
                    <div class="upgrade-cost" style="color:var(--gold)"><?= htmlspecialchars($castleReqMissing) ?></div>
                <?php elseif ($castleCap): ?>
                    <div class="upgrade-cost" style="color:var(--gold)">Castle LV.<?= $nextLevel ?> required</div>
                <?php else: ?>
                    <button
                        class="upgrade-btn"
                        :disabled="upgrading"
                        @click="upgrade('<?= $code ?>')"
                        <?= !$canAfford ? 'style="border-color:var(--red);color:var(--red)"' : '' ?>
                    >
                        ▲ LV.<?= $nextLevel ?> — <?= formatTime($buildTime) ?>
                    </button>
                    <div class="upgrade-cost">
                        <?php if ($cost['lumber'] > 0): ?>
                            <span class="<?= $city['lumber'] < $cost['lumber'] ? 'short' : '' ?>">🪵 <?= number_format($cost['lumber']) ?></span>
                        <?php endif ?>
                        <?php if ($cost['stone'] > 0): ?>
                            <span class="<?= $city['stone'] < $cost['stone'] ? 'short' : '' ?>">🪨 <?= number_format($cost['stone']) ?></span>
                        <?php endif ?>
                        <?php if ($cost['gold'] > 0): ?>
                            <span class="<?= $city['gold'] < $cost['gold'] ? 'short' : '' ?>">💰 <?= number_format($cost['gold']) ?></span>
                        <?php endif ?>
                    </div>
                <?php endif ?>
            </div>
        <?php endforeach ?>
    </div>

    <!-- Toast notification -->
    <div x-show="toast.msg" x-transition :class="'toast ' + toast.type" x-text="toast.msg"></div>

    <!-- Stats strip -->
    <div class="mt" style="font-size:.8rem;color:var(--muted);display:flex;gap:1.5rem;flex-wrap:wrap">
        <span>Power: <strong style="color:var(--text)"><?= $fmt($city['power']) ?></strong></span>
        <span>AP: <strong style="color:var(--text)"><?= (int) $city['action_points'] ?></strong></span>
        <span>Coords: <strong style="color:var(--text)">(<?= (int) $city['coord_x'] ?>, <?= (int) $city['coord_y'] ?>)</strong></span>
    </div>

</main>

<script>
// Queue finish times passed from PHP (UTC unix timestamps).
const queueFinishTimes = <?= json_encode(
    array_values(array_map(
        static fn($q) => strtotime($q['finishes_at']),
        $queue,
    ))
) ?>;

function fmtTime(secs) {
    if (secs <= 0)      return 'finishing...';
    if (secs < 60)      return secs + 's';
    if (secs < 3600) {
        const m = Math.floor(secs / 60), s = secs % 60;
        return m + 'm ' + (s > 0 ? s + 's' : '');
    }
    const h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60);
    return h + 'h' + (m > 0 ? ' ' + m + 'm' : '');
}

function cityApp() {
    return {
        upgrading: false,
        toast: { msg: '', type: 'ok' },
        // Remaining seconds per queued building_code.
        remaining: <?= json_encode(
            array_map(
                static fn($q) => max(0, strtotime($q['finishes_at']) - time()),
                array_column($queue, null, 'building_code'),
            )
        ) ?>,

        init() {
            // Tick every second: count down and reload when any upgrade finishes.
            setInterval(() => {
                let anyDone = false;
                for (const code in this.remaining) {
                    if (this.remaining[code] > 0) {
                        this.remaining[code]--;
                        if (this.remaining[code] === 0) anyDone = true;
                    }
                }
                if (anyDone) {
                    setTimeout(() => window.location.reload(), 1000);
                }
            }, 1000);

            // Refresh resources every 30 seconds.
            setInterval(() => window.location.reload(), 30_000);
        },

        countdown(code) {
            const s = this.remaining[code] ?? 0;
            return fmtTime(s);
        },

        async upgrade(code) {
            if (this.upgrading) return;
            this.upgrading = true;
            this.toast = { msg: '', type: 'ok' };

            try {
                const res = await fetch('/api/city/upgrade-building', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': '<?= htmlspecialchars($session['csrf_token'], ENT_QUOTES) ?>',
                    },
                    body: JSON.stringify({ building_code: code }),
                });

                const data = await res.json();

                if (data.ok) {
                    this.showToast('Upgrade started!', 'ok');
                    setTimeout(() => window.location.reload(), 800);
                } else {
                    this.showToast(data.error?.message ?? 'Upgrade failed.', 'err');
                }
            } catch (e) {
                this.showToast('Network error. Please try again.', 'err');
            } finally {
                this.upgrading = false;
            }
        },

        showToast(msg, type) {
            this.toast = { msg, type };
            setTimeout(() => { this.toast = { msg: '', type: 'ok' }; }, 3500);
        },
    };
}
</script>

<footer>
    PHP <?= PHP_VERSION ?> &middot;
    Server time: <?= gmdate('Y-m-d H:i:s') ?> UTC &middot;
    <a href="https://github.com/svenmanderscheid/conquer" style="color:var(--muted)">GitHub</a>
</footer>

</body>
</html>
