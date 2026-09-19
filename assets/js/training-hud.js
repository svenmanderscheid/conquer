(() => {
    'use strict';

    // Database timestamps are UTC, just like the shared game clock passed in ctx.now.
    const timestamp = value => {
        if (!value) return NaN;
        const raw = String(value).replace(' ', 'T');
        return Date.parse(/(?:Z|[+-]\d{2}:?\d{2})$/i.test(raw) ? raw : raw + 'Z');
    };
    const number = value => Math.max(0, Math.floor(Number(value) || 0));
    const formatTime = seconds => {
        const remaining = Math.max(0, Math.ceil(seconds));
        const hours = Math.floor(remaining / 3600);
        const minutes = Math.floor(remaining % 3600 / 60);
        const secs = remaining % 60;
        return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}` : `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    };
    function summarize(state, now) {
        if (!state) return {status: 'loading', count: 0, batches: 0};
        const queue = (Array.isArray(state.troop_queue) ? state.troop_queue : []).filter(row => !Number(row.is_processed));
        if (!queue.length) return {status: 'idle', count: 0, batches: 0};
        const count = queue.reduce((total, row) => total + number(row.count), 0);
        const timed = queue.map(row => ({row, end: timestamp(row.finishes_at)})).filter(entry => Number.isFinite(entry.end)).sort((a, b) => a.end - b.end);
        if (!timed.length) return {status: 'unknown', count, batches: queue.length};
        const {row, end} = timed[0];
        const start = timestamp(row.started_at);
        const seconds = Math.max(0, Math.ceil((end - now) / 1000));
        const progress = Number.isFinite(start) && end > start ? Math.max(0, Math.min(1, (now - start) / (end - start))) : seconds ? 0 : 1;
        return {
            status: seconds ? 'active' : 'finishing', count, batches: queue.length, seconds, progress,
            // Deadline changes after a speedup must be observed even when the row id stays the same.
            dueKey: timed.filter(entry => entry.end <= now).map(entry => `${entry.row.id}:${entry.end}`).join('|'),
        };
    }

    function ConquerTrainingHud(ctx) {
        let button, countNode, timeNode, progressNode, stateNode;
        let pending = false, destroyed = false, retryAt = 0, attemptedDueKey = '';
        function mount() {
            if (button?.isConnected) return true;
            const host = document.querySelector('#hud-left-tools');
            if (!host) return false;
            button = document.createElement('button');
            button.type = 'button';
            button.id = 'hud-training';
            button.className = 'hud-edge-button hud-training hud-job';
            button.dataset.cityOnly = '';
            button.dataset.action = 'tab';
            button.dataset.id = 'army';
            button.innerHTML = '<span class="hud-edge-art training-hud-art" aria-hidden="true"><svg viewBox="0 0 48 48" fill="none"><path d="m29 5 9-2-2 9-17 20-6-6L29 5Z" fill="#fff2cd" stroke="#573a28" stroke-width="2.5" stroke-linejoin="round"/><path d="m12 24 12 10M8 39l9-10" stroke="#573a28" stroke-width="6" stroke-linecap="round"/><path d="m12 24 12 10M8 39l9-10" stroke="#e9af45" stroke-width="3" stroke-linecap="round"/><path d="M25 24c7 0 12-4 12-4s5 4 8 4c0 11-3 17-10 20-7-3-10-9-10-20Z" fill="#72b7c6" stroke="#573a28" stroke-width="2.5" stroke-linejoin="round"/><path d="M35 26v11m-4-7h8" stroke="#fff2cd" stroke-width="3" stroke-linecap="round"/></svg></span><span class="hud-edge-label">Ausbildung</span><small class="training-hud-count"></small><strong class="training-hud-time"></strong><span class="training-hud-track" aria-hidden="true"><span class="training-hud-progress"></span></span>';
            countNode = button.querySelector('.training-hud-count');
            timeNode = button.querySelector('.training-hud-time');
            progressNode = button.querySelector('.training-hud-progress');
            const copy = document.createElement('span');
            copy.className = 'hud-job-copy';
            stateNode = document.createElement('small');
            stateNode.className = 'hud-job-state';
            timeNode.className += ' hud-job-time';
            progressNode.className += ' hud-job-progress';
            button.querySelector('.training-hud-track').className += ' hud-job-track';
            copy.append(button.querySelector('.hud-edge-label'), stateNode, timeNode, countNode);
            button.append(copy);
            host.append(button);
            return true;
        }
        function update() {
            if (destroyed || !mount()) return;
            const snapshot = summarize(ctx.getState(), ctx.now());
            const count = snapshot.count.toLocaleString('de-DE');
            const time = snapshot.status === 'active' ? formatTime(snapshot.seconds) : ({loading: 'Bitte warten', idle: 'Auftrag starten', unknown: 'Zeit offen', finishing: 'Wird bestätigt'})[snapshot.status];
            const countText = snapshot.batches ? `× ${count}` : 'Truppen';
            if (countNode.textContent !== countText) countNode.textContent = countText;
            if (timeNode.textContent !== time) timeNode.textContent = time;
            button.dataset.trainingStatus = snapshot.status;
            button.dataset.jobState = snapshot.status === 'unknown' ? 'active' : snapshot.status;
            stateNode.textContent = ({active:'Läuft',unknown:'Läuft',idle:'Bereit',loading:'Lädt …',finishing:'Abschluss'})[snapshot.status];
            countNode.hidden = !snapshot.batches;
            progressNode.style.width = `${Math.round((snapshot.progress || 0) * 100)}%`;
            let description = 'Keine Truppen in Ausbildung. Truppenausbildung öffnen.';
            if (snapshot.status === 'loading') description = 'Truppenausbildung wird geladen.';
            if (snapshot.batches) {
                const next = snapshot.status === 'active' ? `Nächster Abschluss in ${time}.` : snapshot.status === 'finishing' ? 'Abschluss wird bestätigt.' : 'Restzeit derzeit nicht verfügbar.';
                description = `${count} Truppen in ${snapshot.batches} ${snapshot.batches === 1 ? 'Auftrag' : 'Aufträgen'}. ${next} Truppenausbildung öffnen.`;
            }
            button.title = description;
            button.setAttribute('aria-label', description);
            // Never announce a finished batch before the server has actually credited it.
            // Normal game polling is still active; this only closes the gap at a deadline.
            if (!snapshot.dueKey) {
                attemptedDueKey = '';
                retryAt = 0;
                return;
            }
            if (document.hidden || pending || typeof ctx.refresh !== 'function') return;
            if (snapshot.dueKey === attemptedDueKey && Date.now() < retryAt) return;
            attemptedDueKey = snapshot.dueKey;
            retryAt = Date.now() + 15000;
            pending = true;
            Promise.resolve().then(() => ctx.refresh()).catch(() => {
                // The shared refresh reports connection failures; retain the honest pending state.
            }).finally(() => { pending = false; });
        }
        const interval = window.setInterval(update, 1000);
        document.addEventListener('visibilitychange', update);
        return {
            update,
            destroy() {
                destroyed = true;
                window.clearInterval(interval);
                document.removeEventListener('visibilitychange', update);
                button?.remove();
            },
        };
    }
    ConquerTrainingHud.summarize = summarize;
    ConquerTrainingHud.formatTime = formatTime;
    window.ConquerTrainingHud = ConquerTrainingHud;
})();
