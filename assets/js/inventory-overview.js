(() => {
    'use strict';
    const resources = [
        ['food', 'Nahrung', 'backpack/food-bundle.svg'],
        ['lumber', 'Holz', 'backpack/lumber-bundle.svg'],
        ['stone', 'Stein', 'backpack/stone-bundle.svg'],
        ['gold', 'Gold', 'backpack/gold-bundle.svg'],
        ['gems', 'Edelsteine', 'backpack/gems-bundle.svg'],
        ['ap', 'Energie', 'energy.svg'],
        ['prestige', 'Prestige', 'prestige.svg'],
    ];
    const activities = [
        ['generic', 'Universell', 'backpack/speedup.svg'], ['training', 'Ausbildung', 'backpack/speedup-training.svg'],
        ['building', 'Bauen', 'backpack/speedup-building.svg'], ['research', 'Forschung', 'backpack/speedup-research.svg'],
        ['healing', 'Heilung', 'backpack/speedup-healing.svg'],
    ];
    const positive = value => Number.isFinite(Number(value)) ? Math.max(0, Math.floor(Number(value))) : 0;
    const balance = value => value == null || !Number.isFinite(Number(value)) ? null : positive(value);
    const exact = value => value == null ? 'Nicht verfügbar' : value.toLocaleString('de-DE');
    function compact(value) {
        if (value == null) return '–';
        for (const [size, suffix] of [[1e12, 'Bill.'], [1e9, 'Mrd.'], [1e6, 'Mio.'], [1e3, 'Tsd.']]) {
            if (value >= size) return (value / size).toLocaleString('de-DE', {maximumFractionDigits:2}) + ' ' + suffix;
        }
        return exact(value);
    }
    // Only owned items contribute. The full catalogue and random chests are deliberately excluded.
    function summarize(state, kingdom) {
        const available = Array.isArray(kingdom?.inventory);
        const packets = Object.fromEntries(resources.map(([key]) => [key, available ? 0 : null]));
        const seconds = Object.fromEntries(activities.map(([key]) => [key, available ? 0 : null]));
        for (const item of kingdom?.inventory || []) {
            const quantity = positive(item.quantity);
            if (!quantity) continue;
            if (item.category === 'resource_pack' && resources.slice(0, 5).some(([key]) => key === item.resource)) {
                packets[item.resource] += quantity * positive(item.amount);
            } else if (item.category === 'ap_refill') packets.ap += quantity * positive(item.ap_amount);
            else if (item.category === 'vip_point') packets.prestige += quantity * positive(item.vip_points);
            else if (item.category === 'speedup') {
                const key = Object.hasOwn(seconds, item.subcategory) ? item.subcategory : 'other';
                seconds[key] = (seconds[key] || 0) + quantity * positive(item.duration_seconds);
            }
        }
        const profile = kingdom?.profile;
        return {
            resources:resources.map(([key, name, icon]) => ({key, name, icon, items:packets[key], stock:balance(
                key === 'ap' ? profile?.action_points : key === 'prestige' ? profile?.prestige_points : key === 'gems' ? profile?.gems : state?.city?.[key]
            )})),
            speedups:[...activities, ...(seconds.other > 0 ? [['other', 'Weitere Beschleuniger', 'backpack/speedup.svg']] : [])]
                .map(([key, name, icon]) => ({key, name, icon, seconds:seconds[key]})),
        };
    }
    function formatTime(seconds, unit = 'days') {
        if (seconds == null) return 'Nicht verfügbar';
        let rest = positive(seconds);
        if (!rest) return 'Keine Items';
        const parts = [];
        const units = unit === 'minutes' ? [[60, 'Min.']] : unit === 'hours' ? [[3600, 'Std.'], [60, 'Min.']] : [[86400, 'Tg.'], [3600, 'Std.'], [60, 'Min.']];
        for (const [size, label] of units) {
            const value = Math.floor(rest / size);
            if (value) parts.push(exact(value) + ' ' + label);
            rest %= size;
        }
        if (rest) parts.push(exact(rest) + ' Sek.');
        return parts.join(' ');
    }
    function create({base, esc, getState, getKingdom, openDialog}) {
        const button = document.getElementById('inventory-overview-button');
        const dialog = document.getElementById('game-dialog');
        let tab = 'resources', unit = 'days', historyEntry = null, closingHistory = false, sequence = 0;
        const image = file => `${base}/assets/art/items/${file}?v=${encodeURIComponent(window.CONQUER_ITEM_ART_VERSION || 'overview1')}`;
        const numberCell = (value, column) => `<td data-column="${column}" data-value="${value ?? ''}" title="${exact(value)}" aria-label="${exact(value)}">${compact(value)}</td>`;
        function table(totals) {
            if (tab === 'resources') return `<table class="inventory-overview-table"><caption class="inventory-overview-sr">Rohstoffpakete und aktuelle Vorräte</caption>
                <thead><tr><th scope="col">Rohstoff</th><th scope="col">In Items</th><th scope="col">Im Vorrat</th></tr></thead><tbody>${totals.resources.map(row => `
                <tr data-resource="${row.key}"><th scope="row"><span class="inventory-overview-identity"><span class="inventory-overview-art"><img src="${image(row.icon)}" alt=""></span><span>${row.name}</span></span></th>${numberCell(row.items, 'items')}${numberCell(row.stock, 'stock')}</tr>`).join('')}</tbody></table>`;
            return `<table class="inventory-overview-table is-speedups"><caption class="inventory-overview-sr">Gesamte Beschleunigungszeit im Inventar</caption>
                <thead><tr><th scope="col">Beschleuniger</th><th scope="col">Gesamtdauer</th></tr></thead><tbody>${totals.speedups.map(row => `
                <tr data-speedup="${row.key}"><th scope="row"><span class="inventory-overview-identity"><span class="inventory-overview-art"><img src="${image(row.icon)}" alt=""></span><span>${row.name}</span></span></th><td data-seconds="${row.seconds ?? ''}">${formatTime(row.seconds, unit)}</td></tr>`).join('')}</tbody></table>`;
        }
        function update(resetScroll = false) {
            const panel = dialog.open && dialog.querySelector('.inventory-overview');
            if (!panel) return;
            const totals = summarize(getState(), getKingdom()), signature = JSON.stringify([totals, tab, unit]);
            if (panel.dataset.signature === signature) return;
            panel.dataset.signature = signature;
            const scroll = panel.querySelector('.inventory-overview-scroll'), top = resetScroll ? 0 : scroll.scrollTop;
            scroll.innerHTML = table(totals);
            scroll.scrollTop = top;
            panel.querySelectorAll('[data-overview-tab]').forEach(b => {
                const selected = b.dataset.overviewTab === tab;
                b.classList.toggle('active', selected); b.setAttribute('aria-pressed', String(selected));
            });
            panel.querySelectorAll('[data-overview-unit]').forEach(b => {
                const selected = b.dataset.overviewUnit === unit;
                b.classList.toggle('active', selected); b.setAttribute('aria-pressed', String(selected));
            });
            panel.querySelector('.inventory-overview-units').hidden = tab !== 'speedups';
            panel.querySelector('.inventory-overview-note').textContent = tab === 'resources'
                ? 'Vorrat: aktive Stadt; Edelsteine, Energie und Prestige: Konto. Zufalls- und Auswahltruhen sind nicht eingerechnet.'
                : 'Jeder Beschleuniger zählt nur in seinem Einsatzbereich. Universelle Zeit ist separat aufgeführt.';
        }
        function open() {
            if (closingHistory || dialog.open && dialog.querySelector('.inventory-overview')) return;
            tab = 'resources';
            historyEntry = {token:`inventory-overview-${Date.now()}-${++sequence}`, url:location.href};
            history.pushState({...history.state, conquerInventoryOverview:historyEntry.token}, '', location.href);
            openDialog(`<section class="inventory-overview"><h2>Rohstoffe & Beschleuniger</h2>
                <nav class="inventory-overview-tabs" aria-label="Übersicht auswählen"><button type="button" data-overview-tab="resources" class="active" aria-pressed="true">Rohstoffe</button><button type="button" data-overview-tab="speedups" aria-pressed="false">Beschleuniger</button></nav>
                <div class="inventory-overview-scroll" tabindex="0" aria-label="Inventarbestände"></div>
                <div class="inventory-overview-footer"><div class="inventory-overview-units" role="group" aria-label="Zeitdarstellung" hidden>${[['days','Tage'],['hours','Stunden'],['minutes','Minuten']].map(([key, label]) => `<button type="button" data-overview-unit="${key}" aria-pressed="${unit === key}"><span aria-hidden="true">✓</span>${label}</button>`).join('')}</div><p class="inventory-overview-note"></p></div></section>`);
            update();
        }
        button.addEventListener('click', open);
        dialog.addEventListener('click', event => {
            const control = event.target.closest('[data-overview-tab],[data-overview-unit]');
            if (!control || !control.closest('.inventory-overview')) return;
            if (['resources', 'speedups'].includes(control.dataset.overviewTab)) { tab = control.dataset.overviewTab; update(true); }
            if (['days', 'hours', 'minutes'].includes(control.dataset.overviewUnit)) { unit = control.dataset.overviewUnit; update(); }
        });
        dialog.addEventListener('close', () => {
            const entry = historyEntry; historyEntry = null;
            if (entry && history.state?.conquerInventoryOverview === entry.token) {
                if (location.href === entry.url) { closingHistory = true; button.disabled = true; history.back(); }
                else { const state = {...history.state}; delete state.conquerInventoryOverview; history.replaceState(state, '', location.href); }
            }
        });
        window.addEventListener('popstate', () => {
            closingHistory = false; button.disabled = false;
            if (historyEntry && history.state?.conquerInventoryOverview !== historyEntry.token) {
                historyEntry = null;
                if (dialog.open && dialog.querySelector('.inventory-overview')) dialog.close();
            }
        });
        return {update};
    }
    window.ConquerInventoryOverview = {summarize, formatTime, create};
})();
