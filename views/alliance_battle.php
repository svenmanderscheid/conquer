<?php
declare(strict_types=1);
/**
 * Alliance Battle Page — Sprint 5
 *
 * Shows active rallies for alliance members.
 * Players can view and join rallies started by alliance members.
 */
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conquer — Alliance Battle</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            min-height: 100%;
            background: #060e1c;
            color: #e2e8f0;
            font-family: system-ui, -apple-system, sans-serif;
        }

        #page-wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 70px 16px 80px;
        }

        .page-header {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 24px;
        }
        .page-title {
            font-size: 1.3rem; font-weight: 800; color: #f59e0b;
        }
        .back-btn {
            padding: 6px 14px; border-radius: 6px;
            border: 1px solid #334155; background: #1e293b;
            color: #94a3b8; font-size: 0.8rem; text-decoration: none;
            transition: background 0.15s;
        }
        .back-btn:hover { background: #273548; color: #e2e8f0; }

        /* Rally list */
        .rally-list { display: flex; flex-direction: column; gap: 12px; }

        .rally-card {
            background: #0d1628; border: 1px solid #1e3a5f;
            border-radius: 10px; padding: 16px;
            transition: border-color 0.15s;
        }
        .rally-card:hover { border-color: #2a5a8f; }

        .rally-card-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 12px;
        }
        .rally-target-name {
            font-size: 1rem; font-weight: 700; color: #ef4444;
        }
        .rally-leader {
            font-size: 0.72rem; color: #64748b; margin-top: 3px;
        }
        .rally-timer-badge {
            background: rgba(245,158,11,0.1); border: 1px solid rgba(245,158,11,0.4);
            border-radius: 6px; padding: 4px 10px;
            font-size: 0.85rem; font-weight: 700; color: #fbbf24;
            font-variant-numeric: tabular-nums; white-space: nowrap;
        }

        .rally-info-row {
            display: flex; gap: 16px; margin-bottom: 12px; flex-wrap: wrap;
        }
        .rally-info-cell {
            background: #0f172a; border: 1px solid #1e293b; border-radius: 6px;
            padding: 6px 12px;
        }
        .rally-info-cell .ri-val { font-size: 0.88rem; font-weight: 700; color: #e2e8f0; }
        .rally-info-cell .ri-lbl { font-size: 0.62rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 2px; }

        .rally-participants {
            font-size: 0.72rem; color: #64748b; margin-bottom: 12px;
        }

        .rally-actions {
            display: flex; gap: 8px;
        }
        .rally-btn {
            padding: 8px 20px; border-radius: 6px; border: none;
            font-size: 0.85rem; font-weight: 700; cursor: pointer;
            transition: opacity 0.15s;
        }
        .rally-btn:hover { opacity: 0.85; }
        .rally-btn-join { background: #f59e0b; color: #1c1917; }
        .rally-btn-view { background: #334155; color: #e2e8f0; }
        .rally-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        /* Empty state */
        .empty-state {
            text-align: center; padding: 60px 20px;
            color: #334155;
        }
        .empty-state-icon { font-size: 3rem; margin-bottom: 12px; }
        .empty-state-text { font-size: 0.9rem; }

        /* Join modal */
        .modal-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(0,0,0,0.7);
            display: flex; align-items: center; justify-content: center;
        }
        .modal-box {
            background: #1e293b; border: 1px solid #334155;
            border-radius: 12px; padding: 1.5rem;
            width: 380px; max-width: 95vw; max-height: 90vh; overflow-y: auto;
        }
        .modal-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: #f59e0b; }
        .modal-sub   { font-size: 0.7rem; color: #64748b; text-transform: uppercase;
                       letter-spacing: 0.08em; margin: 0.75rem 0 0.4rem; }
        .troop-pick  {
            background: #0f172a; border: 1px solid #334155;
            border-radius: 6px; padding: 0.5rem 0.6rem; margin-bottom: 0.4rem;
        }
        .troop-pick-header {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-bottom: 0.35rem;
        }
        .troop-pick-name  { font-size: 0.82rem; font-weight: 600; }
        .troop-pick-avail { font-size: 0.72rem; color: #64748b; }
        .troop-pick-controls { display: flex; align-items: center; gap: 0.4rem; }
        input[type=range] { flex: 1; accent-color: #f59e0b; cursor: pointer; height: 4px; }
        input[type=range]:disabled { opacity: 0.3; cursor: not-allowed; }
        .troop-pick-input {
            width: 68px; padding: 0.2rem 0.35rem; border-radius: 4px;
            border: 1px solid #334155; background: #1e293b;
            color: #e2e8f0; font-size: 0.8rem; text-align: right;
        }
        .troop-pick-max {
            padding: 0.2rem 0.45rem; border-radius: 4px; font-size: 0.72rem;
            background: #1e293b; border: 1px solid #475569;
            color: #94a3b8; cursor: pointer; white-space: nowrap;
        }
        .troop-pick-max:hover { border-color: #f59e0b; color: #f59e0b; }
        .modal-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }
        .modal-btn {
            flex: 1; padding: 0.55rem; border-radius: 6px; border: none;
            font-size: 0.85rem; font-weight: 700; cursor: pointer;
        }
        .modal-btn-cancel  { background: #334155; color: #94a3b8; }
        .modal-btn-orange  { background: #f59e0b; color: #1c1917; }
        .modal-btn:disabled { opacity: 0.45; cursor: not-allowed; }

        /* Toast */
        #page-toast {
            position: fixed; bottom: 1.2rem; left: 50%; transform: translateX(-50%);
            background: #1e293b; border: 1px solid #334155; border-radius: 8px;
            padding: 0.5rem 1.1rem; font-size: 0.82rem; z-index: 300;
            white-space: nowrap; display: none;
        }
        #page-toast.ok  { border-color: #22c55e; color: #22c55e; }
        #page-toast.err { border-color: #ef4444; color: #ef4444; }
    </style>
</head>
<body x-data="battleApp()" x-init="boot()">
<?php $hudCurrentView = 'other'; require __DIR__ . '/partials/hud.php'; ?>

<div id="page-wrap">
    <div class="page-header">
        <a href="/map" class="back-btn">← Karte</a>
        <div class="page-title">⚔ Alliance Battle</div>
        <button style="margin-left:auto;padding:6px 14px;border-radius:6px;border:1px solid #334155;
                       background:#1e293b;color:#94a3b8;font-size:0.8rem;cursor:pointer"
                @click="loadRallies()">↻ Aktualisieren</button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div style="text-align:center;padding:40px;color:#475569">Rallys laden…</div>
    </template>

    <!-- No alliance -->
    <template x-if="!loading && !inAlliance">
        <div class="empty-state">
            <div class="empty-state-icon">🤝</div>
            <div class="empty-state-text">Du bist in keiner Allianz.<br>Tritt einer Allianz bei um Rallys zu sehen.</div>
        </div>
    </template>

    <!-- Empty rallies -->
    <template x-if="!loading && inAlliance && rallies.length === 0">
        <div class="empty-state">
            <div class="empty-state-icon">🏳️</div>
            <div class="empty-state-text">Keine aktiven Rallys in deiner Allianz.<br>Starte eine Rally auf der Karte!</div>
        </div>
    </template>

    <!-- Rally list -->
    <template x-if="!loading && inAlliance && rallies.length > 0">
        <div class="rally-list">
            <template x-for="rally in rallies" :key="rally.id">
                <div class="rally-card">
                    <div class="rally-card-header">
                        <div>
                            <div class="rally-target-name">
                                ⚔ <span x-text="rally.target_name ?? ('Spieler #' + rally.target_player_id)"></span>
                            </div>
                            <div class="rally-leader">
                                Geführt von <strong x-text="rally.leader_name ?? ('Spieler #' + rally.leader_player_id)"></strong>
                            </div>
                        </div>
                        <div class="rally-timer-badge" x-text="rallyCountdown(rally, tick)"></div>
                    </div>

                    <div class="rally-info-row">
                        <div class="rally-info-cell">
                            <div class="ri-val" x-text="rally.rally_minutes + 'min'"></div>
                            <div class="ri-lbl">Timer</div>
                        </div>
                        <div class="rally-info-cell">
                            <div class="ri-val" x-text="rally.target_x + ', ' + rally.target_y"></div>
                            <div class="ri-lbl">Koordinaten</div>
                        </div>
                        <div class="rally-info-cell">
                            <div class="ri-val" x-text="rally.participant_count ?? 1"></div>
                            <div class="ri-lbl">Teilnehmer</div>
                        </div>
                    </div>

                    <div class="rally-actions">
                        <button class="rally-btn rally-btn-join"
                                :disabled="rally.already_joined || rally.leader_player_id == myPlayerId"
                                @click="openJoinModal(rally)"
                                x-text="rally.already_joined ? '✓ Beigetreten' : (rally.leader_player_id == myPlayerId ? 'Eigene Rally' : '⚔ Beitreten')">
                        </button>
                        <button class="rally-btn rally-btn-view"
                                @click="goToRally(rally)">
                            🗺 Auf Karte
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

<!-- Join Rally Modal -->
<div class="modal-overlay" x-show="joinModal" @click.self="joinModal=false" style="display:none">
    <div class="modal-box">
        <div class="modal-title">⚔ Rally beitreten</div>
        <div style="font-size:0.82rem;color:#94a3b8;margin-bottom:0.75rem"
             x-text="joinTarget ? 'Ziel: ' + (joinTarget.target_name ?? 'Spieler #' + joinTarget.target_player_id) : ''"></div>

        <div class="modal-sub">Truppen auswählen</div>
        <template x-if="joinLoading"><div style="text-align:center;color:#475569;padding:1rem">Laden…</div></template>
        <template x-if="!joinLoading">
            <div>
                <template x-for="t in joinTroops" :key="t.code">
                    <div class="troop-pick">
                        <div class="troop-pick-header">
                            <span class="troop-pick-name" x-text="t.name"></span>
                            <span class="troop-pick-avail" x-text="(t.toSend||0).toLocaleString() + ' / ' + t.available.toLocaleString()"></span>
                        </div>
                        <div class="troop-pick-controls">
                            <input type="range" min="0" :max="t.available" step="1" x-model.number="t.toSend" :disabled="t.available===0">
                            <input type="number" class="troop-pick-input" min="0" :max="t.available" x-model.number="t.toSend" :disabled="t.available===0">
                            <button class="troop-pick-max" :disabled="t.available===0" @click="t.toSend=t.available">Max</button>
                        </div>
                    </div>
                </template>
                <div style="font-size:0.72rem;color:#64748b;margin-top:0.5rem"
                     x-text="'Gesamt: ' + joinTroops.reduce((s,t)=>s+(t.toSend||0),0).toLocaleString() + ' Truppen'"></div>
            </div>
        </template>

        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" @click="joinModal=false">Abbrechen</button>
            <button class="modal-btn modal-btn-orange"
                    :disabled="joinLoading || joinTroops.reduce((s,t)=>s+(t.toSend||0),0)===0"
                    @click="joinRally()">⚔ Beitreten</button>
        </div>
    </div>
</div>

<div id="page-toast"></div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
const CSRF         = <?= json_encode($session['csrf_token']) ?>;
const MY_PLAYER_ID = <?= json_encode((int) $session['player_id']) ?>;

function battleApp() {
    return {
        loading:    true,
        inAlliance: false,
        rallies:    [],
        tick:       0,
        myPlayerId: MY_PLAYER_ID,

        // Join modal
        joinModal:  false,
        joinTarget: null,
        joinTroops: [],
        joinLoading: false,

        async boot() {
            setInterval(() => this.tick++, 1000);
            await this.loadRallies();
        },

        async loadRallies() {
            this.loading = true;
            try {
                const r = await fetch('/api/rally/list');
                const j = await r.json();
                if (j.ok) {
                    this.inAlliance = j.data.in_alliance !== false;
                    this.rallies    = j.data.rallies ?? [];
                } else if (j.error === 'NO_ALLIANCE') {
                    this.inAlliance = false;
                }
            } catch {}
            this.loading = false;
        },

        rallyCountdown(rally, _tick) {
            if (!rally.launch_at) return 'Wartet…';
            const launchMs = new Date(rally.launch_at.replace(' ', 'T') + 'Z').getTime();
            const secsLeft = Math.max(0, Math.round((launchMs - Date.now()) / 1000));
            if (secsLeft === 0) return 'Startet!';
            const m = Math.floor(secsLeft / 60);
            const s = secsLeft % 60;
            return m > 0 ? `${m}m ${s}s bis Start` : `${s}s bis Start`;
        },

        goToRally(rally) {
            window.location.href = `/map#${rally.target_x},${rally.target_y}`;
        },

        async openJoinModal(rally) {
            this.joinTarget  = rally;
            this.joinModal   = true;
            this.joinTroops  = [];
            this.joinLoading = true;
            try {
                const r = await fetch('/api/troops/list');
                const j = await r.json();
                if (j.ok) {
                    this.joinTroops = j.data.definitions
                        .filter(d => (j.data.troops[d.code] ?? 0) > 0)
                        .map(d => ({ code: d.code, name: d.name, available: j.data.troops[d.code] ?? 0, toSend: 0 }));
                }
            } catch {}
            this.joinLoading = false;
        },

        async joinRally() {
            const troops = {};
            let total = 0;
            for (const t of this.joinTroops) {
                if (t.toSend > 0) { troops[t.code] = t.toSend; total += t.toSend; }
            }
            if (total === 0) return;
            this.joinLoading = true;
            try {
                const r = await fetch('/api/rally/join', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
                    body:    JSON.stringify({ rally_id: this.joinTarget.id, troops }),
                });
                const j = await r.json();
                if (j.ok) {
                    this.joinModal = false;
                    this.showToast('✓ Rally beigetreten!', 'ok');
                    await this.loadRallies();
                } else {
                    this.showToast(j.message ?? j.error ?? 'Fehler', 'err');
                }
            } catch { this.showToast('Netzwerkfehler', 'err'); }
            this.joinLoading = false;
        },

        showToast(msg, type = 'ok') {
            const el = document.getElementById('page-toast');
            el.textContent   = msg;
            el.className     = type;
            el.style.display = 'block';
            setTimeout(() => { el.style.display = 'none'; }, 4000);
        },
    };
}
</script>
</body>
</html>
