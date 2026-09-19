(() => {
  'use strict';
  window.ConquerLand = function (ctx) {
    const {api, esc, fmt, toast, navigate, getState} = ctx;
    const host = () => document.querySelector('#content');
    const zoneNames = {outer:'Außenbereich', middle:'Mittlerer Bereich', center:'Zentrum'};
    const sourceNames = {monster_kill:'Monsterjagden', gather:'Sammelertrag', donation:'Ressourcenspenden'};
    const resourceNames = {food:'Nahrung', lumber:'Holz', stone:'Stein', gold:'Gold'};
    let state = null, detail = null, selected = null, active = false, generation = 0, pending = false;
    let filter = 'all', search = '', retry = null;
    const opId = () => globalThis.crypto?.randomUUID?.() || `land_${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}`;
    const date = value => value ? new Date(String(value).replace(' ', 'T') + (/[zZ]|[+-]\d\d:\d\d$/.test(String(value)) ? '' : 'Z')).toLocaleString('de-DE') : 'Noch nicht geöffnet';
    const percent = value => Math.max(0, Math.min(100, Number(value) || 0));
    const zone = key => state?.zones?.find(item => item.key === key);
    const chosen = () => state?.lands?.find(item => Number(item.id) === Number(selected));

    function render(tab) {
      if (tab !== 'land') { active = false; return false; }
      if (active && host()?.querySelector('.land-shell')) return true;
      active = true;
      const n = ++generation;
      host().innerHTML = '<p class="land-loading" role="status">Landübersicht wird geladen …</p>';
      api('land/state').then(next => {
        if (!active || n !== generation) return;
        state = next;
        const own = state.lands.find(item => item.own_land);
        if (!state.lands.some(item => Number(item.id) === Number(selected))) selected = Number(own?.id ?? state.lands.find(item => item.open)?.id ?? state.lands[0]?.id ?? 0);
        paint();
        if (selected) loadDetail(selected, false);
      }).catch(error => {
        if (active && n === generation) host().innerHTML = `<div class="land-empty" role="alert"><h3>Landübersicht nicht erreichbar</h3><p>${esc(error.message)}</p><button class="button secondary" data-action="land-reload">Erneut laden</button></div>`;
      });
      return true;
    }

    function zoneCards() {
      return (state.zones || []).map(item => {
        const worldStart = item.key === 'outer', hasGoal = !worldStart && Number(item.required_count) > 0;
        return `<article class="land-zone ${item.open ? 'is-open' : 'is-locked'}">
          <span>${item.open ? 'Offen' : 'Gesperrt'}</span><h3>${esc(zoneNames[item.key] || item.key)}</h3>
          <p>${worldStart ? 'Ab Weltstart geöffnet' : hasGoal ? `${fmt(item.reached_count)} / ${fmt(item.required_count)} Landteile auf Stufe ${fmt(item.target_level)}` : 'Freigabeziel wird vorbereitet'}</p>
          ${hasGoal ? `<div class="land-progress" role="progressbar" aria-label="Freigabe ${esc(zoneNames[item.key] || item.key)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${percent(item.progress_pct)}"><span style="width:${percent(item.progress_pct)}%"></span></div>` : ''}
          <small>${worldStart ? 'Für alle Herrscher zugänglich' : item.open ? `Geöffnet ${esc(date(item.opened_at))}` : item.not_before ? `Frühestens ${esc(date(item.not_before))}` : 'Gemeinsam weiterentwickeln'}</small>
        </article>`;
      }).join('');
    }

    function matches(item) {
      if (filter === 'open' && !item.open) return false;
      if (filter === 'own' && !item.own_land) return false;
      if (!search) return true;
      const text = `${item.id} ${item.parcel_x} ${item.parcel_y} ${item.level} ${zoneNames[item.zone] || item.zone} ${item.center?.x} ${item.center?.y}`.toLocaleLowerCase('de-DE');
      return text.includes(search.toLocaleLowerCase('de-DE'));
    }

    function resultRows() {
      return state.lands.filter(matches).slice(0, 24).map(item => `<button type="button" class="land-result-row ${Number(item.id) === Number(selected) ? 'is-selected' : ''}" data-action="land-select" data-id="${Number(item.id)}" aria-pressed="${Number(item.id) === Number(selected)}"><span><strong>Land ${fmt(item.parcel_x + 1)}/${fmt(item.parcel_y + 1)}</strong><small>${esc(zoneNames[item.zone] || item.zone)}${item.own_land ? ' · Bei deiner Stadt' : ''}</small></span><em>Stufe ${fmt(item.level)} · ${item.open ? 'offen' : 'gesperrt'}</em></button>`).join('');
    }

    function paint() {
      if (!state || !active) return;
      const geometry = state.geometry || {}, columns = Number(geometry.columns) || 32, rows = Number(geometry.rows) || 32, parcelSize = Number(geometry.parcel_size) || 8;
      host().innerHTML = `<section class="land-shell">
        <header class="land-intro"><div><span>ÖFFENTLICHE WELTENTWICKLUNG</span><h2>Landübersicht</h2><p>${columns} × ${rows} Landteile · ${parcelSize} × ${parcelSize} Felder je Landteil · alle können Stufe 9 erreichen.</p></div><button class="button secondary" data-action="land-reload">Aktualisieren</button></header>
        <div class="land-zones" aria-label="Freigabe der Weltzonen">${zoneCards()}</div>
        <div class="land-workspace">
          <section class="land-atlas" aria-label="Interaktive Landkarte">
            <div class="land-tools"><label>Land suchen<input id="land-search" type="search" value="${esc(search)}" placeholder="Nummer, Koordinate, Zone" autocomplete="off"></label><div role="group" aria-label="Land filtern">${[['all','Alle'],['open','Nur offen'],['own','Bei meiner Stadt']].map(([key,label]) => `<button type="button" data-action="land-filter" data-id="${key}" aria-pressed="${filter === key}">${label}</button>`).join('')}</div></div>
            <p class="land-result" aria-live="polite"><strong>${state.lands.filter(matches).length}</strong> von ${state.lands.length} Landteilen passen zum Filter.</p>
            <canvas class="land-map" width="${columns * 24}" height="${rows * 24}" tabindex="0" role="img" aria-label="${columns} mal ${rows} Landteile. Mit Pfeiltasten Land wählen."></canvas>
            <div class="land-legend"><span><i class="is-own"></i>Bei deiner Stadt</span><span><i class="is-open"></i>Offen</span><span><i class="is-locked"></i>Gesperrt</span></div>
            <div class="land-results" aria-label="Passende Landteile">${resultRows()}</div>
          </section>
          <aside class="land-detail" aria-live="polite">${detail && Number(detail.id) === Number(selected) ? detailHtml(detail) : '<p role="status">Landdetails werden geladen …</p>'}</aside>
        </div>
      </section>`;
      host().querySelector('#land-search')?.addEventListener('input', event => { search = event.target.value.trim(); updateFilter(); });
      bindMap();
    }

    function updateFilter() {
      if (!state || !active) return;
      const filtered = state.lands.filter(matches), count = filtered.length;
      const result = host().querySelector('.land-result');
      if (result) result.innerHTML = `<strong>${count}</strong> von ${state.lands.length} Landteilen passen zum Filter.`;
      const rows = host().querySelector('.land-results');
      if (rows) rows.innerHTML = resultRows() || '<p class="land-muted">Kein Landteil passt zu diesem Filter.</p>';
      host().querySelectorAll('[data-action="land-filter"]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.id === filter)));
      drawMap();
    }

    function color(name, fallback) {
      const value = getComputedStyle(host()).getPropertyValue(name).trim();
      return value || fallback;
    }

    function drawMap() {
      const canvas = host()?.querySelector('.land-map');
      if (!canvas || !state) return;
      const context = canvas.getContext('2d'), columns = Number(state.geometry?.columns) || 32, rows = Number(state.geometry?.rows) || 32;
      const cellWidth = canvas.width / columns, cellHeight = canvas.height / rows;
      const palette = {paper:color('--ui-card-light','#fffaf0'), middle:color('--ui-gold-soft','#fff0c4'), center:color('--ui-green-soft','#e9efd3'), locked:color('--ui-disabled','#e5dac3'), line:color('--ui-line','#c7ab7b'), ink:color('--ui-ink','#493b33'), muted:color('--ui-muted','#786345'), blue:color('--ui-blue','#2a72c9'), gold:color('--ui-gold','#e4af38')};
      context.clearRect(0, 0, canvas.width, canvas.height);
      context.textAlign = 'center'; context.textBaseline = 'middle'; context.font = `bold ${Math.max(11, Math.floor(cellHeight * .62))}px sans-serif`;
      for (const item of state.lands) {
        const x = Number(item.parcel_x) * cellWidth, y = Number(item.parcel_y) * cellHeight, visible = matches(item);
        context.globalAlpha = visible ? 1 : .16;
        context.fillStyle = item.open ? item.zone === 'center' ? palette.center : item.zone === 'middle' ? palette.middle : palette.paper : palette.locked;
        context.fillRect(x, y, cellWidth, cellHeight);
        context.strokeStyle = palette.line; context.lineWidth = 1; context.strokeRect(x + .5, y + .5, cellWidth - 1, cellHeight - 1);
        context.fillStyle = item.open ? palette.ink : palette.muted;
        context.fillText(String(Number(item.level) || 0), x + cellWidth / 2, y + cellHeight / 2);
        if (item.own_land) { context.strokeStyle = palette.gold; context.lineWidth = 4; context.strokeRect(x + 2, y + 2, cellWidth - 4, cellHeight - 4); }
        if (Number(item.id) === Number(selected)) { context.strokeStyle = palette.blue; context.lineWidth = 4; context.strokeRect(x + 3, y + 3, cellWidth - 6, cellHeight - 6); }
      }
      const byPosition = new Map(state.lands.map(land => [`${Number(land.parcel_x)}:${Number(land.parcel_y)}`, land]));
      context.globalAlpha = 1; context.strokeStyle = palette.ink; context.lineWidth = 3;
      for (const item of state.lands) {
        const x = Number(item.parcel_x) * cellWidth, y = Number(item.parcel_y) * cellHeight;
        const right = byPosition.get(`${Number(item.parcel_x) + 1}:${Number(item.parcel_y)}`);
        const below = byPosition.get(`${Number(item.parcel_x)}:${Number(item.parcel_y) + 1}`);
        if (right && right.zone !== item.zone) { context.beginPath(); context.moveTo(x + cellWidth, y); context.lineTo(x + cellWidth, y + cellHeight); context.stroke(); }
        if (below && below.zone !== item.zone) { context.beginPath(); context.moveTo(x, y + cellHeight); context.lineTo(x + cellWidth, y + cellHeight); context.stroke(); }
      }
      context.globalAlpha = 1;
    }

    function bindMap() {
      const canvas = host()?.querySelector('.land-map');
      if (!canvas || !state) return;
      drawMap();
      canvas.addEventListener('click', event => {
        const box = canvas.getBoundingClientRect(), columns = Number(state.geometry?.columns) || 32, rows = Number(state.geometry?.rows) || 32;
        const parcelX = Math.min(columns - 1, Math.max(0, Math.floor((event.clientX - box.left) / box.width * columns)));
        const parcelY = Math.min(rows - 1, Math.max(0, Math.floor((event.clientY - box.top) / box.height * rows)));
        const item = state.lands.find(land => Number(land.parcel_x) === parcelX && Number(land.parcel_y) === parcelY);
        if (item && matches(item)) loadDetail(item.id);
      });
      canvas.addEventListener('keydown', event => {
        if (!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key)) return;
        const current = chosen() || state.lands.find(matches); if (!current) return;
        const delta = {ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]}[event.key];
        const item = state.lands.find(land => Number(land.parcel_x) === Number(current.parcel_x) + delta[0] && Number(land.parcel_y) === Number(current.parcel_y) + delta[1]);
        if (item && matches(item)) { event.preventDefault(); loadDetail(item.id, false); requestAnimationFrame(() => host()?.querySelector('.land-map')?.focus({preventScroll:true})); }
      });
    }

    function lootHtml(list) {
      if (!list?.length) return '<p class="land-muted">In diesem Land stehen aktuell keine aktiven Monster.</p>';
      return `<div class="land-loot-list">${list.map(monster => {
        const resources = Object.entries(monster.resource_reward || {}).filter(([, amount]) => Number(amount) > 0).map(([key, amount]) => `${resourceNames[key] || key} ${fmt(amount)}`);
        const drops = (monster.drops || []).map(drop => `${drop.label || `Item ${drop.item_code}`} × ${fmt(drop.count)} · ${Math.round(Number(drop.probability || 0) * 100)} %`);
        const gems = monster.gems_drop && Number(monster.gems_drop.amount) > 0 ? [`Edelsteine ${fmt(monster.gems_drop.amount)} · ${Math.round(Number(monster.gems_drop.chance || 0) * 100)} %`] : [];
        return `<article><h4>${esc(monster.name)} · Stufe ${fmt(monster.level)}</h4><span>${esc(monster.type === 'rally' ? 'Rally' : 'Solo')}</span><p>${[...resources, ...drops, ...gems].map(esc).join(' · ') || 'Keine Beutevorschau verfügbar'}${monster.guaranteed_charms ? ' · 1 Karten-Charm garantiert' : ''}</p></article>`;
      }).join('')}</div>`;
    }

    function detailHtml(item) {
      const currentZone = zone(item.zone) || {}, max = item.next_threshold == null, progress = max ? 100 : percent(item.progress_pct ?? (Number(item.next_threshold) ? Number(item.points) / Number(item.next_threshold) * 100 : 0));
      const sources = item.sources || [], daily = item.own_daily || [], recent = item.recent_events || [];
      return `<div class="land-detail-heading"><div><span>${esc(zoneNames[item.zone] || item.zone)}</span><h3 tabindex="-1">Land ${item.parcel_x + 1}/${item.parcel_y + 1}</h3><p>X ${fmt(item.bounds.x_min)}–${fmt(item.bounds.x_max)} · Y ${fmt(item.bounds.y_min)}–${fmt(item.bounds.y_max)}</p></div><strong>Stufe ${fmt(item.level)}</strong></div>
        <div class="land-level-copy"><span>${max ? 'Maximal entwickelt' : `${fmt(item.points)} / ${fmt(item.next_threshold)} Entwicklungspunkte`}</span><div class="land-progress" role="progressbar" aria-label="Entwicklung von Land ${item.parcel_x + 1}/${item.parcel_y + 1}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${progress}"><span style="width:${progress}%"></span></div></div>
        <div class="land-badges"><span>${item.open ? 'Zone offen' : 'Zone gesperrt'}</span>${item.own_land ? '<span>Bei deiner Stadt</span>' : ''}${item.zone !== 'outer' && Number(currentZone.required_count) > 0 ? `<span>${fmt(currentZone.reached_count || 0)} / ${fmt(currentZone.required_count)} am Zonen-Ziel</span>` : ''}</div>
        <button type="button" class="button secondary land-world-link" data-action="land-world" data-x="${Number(item.center.x)}" data-y="${Number(item.center.y)}">Auf der Weltkarte zeigen</button>
        <section><h4>Entwicklung</h4>${sources.length ? `<div class="land-source-list">${sources.map(source => `<span><strong>${esc(sourceNames[source.source] || source.source)}</strong><em>${fmt(source.points)} Punkte · ${fmt(source.event_count)} Beiträge</em></span>`).join('')}</div>` : '<p class="land-muted">Noch keine Beiträge in diesem Land.</p>'}${daily.length ? `<p class="land-muted">Heute: ${daily.map(row => `${sourceNames[row.source] || row.source} ${fmt(row.credited_points)}`).join(' · ')}</p>` : ''}</section>
        <section><h4>Monsterbeute in diesem Land</h4>${lootHtml(item.monsters || [])}</section>
        <section><h4>Ressourcen spenden</h4>${donationHtml(item, max)}</section>
        ${recent.length ? `<details><summary>Letzte Entwicklung</summary><ol>${recent.slice(0, 8).map(event => `<li><strong>+${fmt(event.points)}</strong> ${esc(sourceNames[event.source] || event.source)}${Number(event.level_after) > Number(event.level_before) ? ` · Stufe ${fmt(event.level_after)} erreicht` : ''}<small>${esc(date(event.created_at))}</small></li>`).join('')}</ol></details>` : ''}`;
    }

    function donationHtml(item, max) {
      if (!item.open) return '<p class="land-muted">Dieses Land wird mit seiner Zone freigeschaltet.</p>';
      if (max) return '<p class="land-muted">Dieses Land hat Stufe 9 erreicht.</p>';
      if (!item.can_donate) return '<p class="land-muted">Du kannst diesem Land derzeit keine Ressourcen spenden.</p>';
      const city = getState()?.city || {}, donation = item.donation || {}, units = Number(donation.resource_units_per_point), values = donation.resource_values || {};
      const rate = key => units > 0 && Number(values[key]) > 0 ? Math.ceil(units / Number(values[key])) : null;
      const allowance = Number.isFinite(Number(donation.remaining_points)) ? `<p class="land-donation-help"><strong>Heute noch ${fmt(donation.remaining_points)} Entwicklungspunkte</strong><span>Ressourcen werden nur bis zu diesem Tagesrest angerechnet.</span></p>` : '';
      return `${allowance}<form data-form="land-donate" data-id="${Number(item.id)}" data-revision="${Number(item.revision)}"><div class="land-donation-grid">${Object.entries(resourceNames).map(([key, label]) => `<label>${label}<input name="${key}" type="number" inputmode="numeric" min="0" max="${Math.max(0, Number(city[key]) || 0)}" step="1" value="0"><small>${fmt(city[key])} verfügbar${rate(key) ? ` · ${fmt(rate(key))} ${label} = 1 Punkt` : ''}</small></label>`).join('')}</div><button class="button gold" ${pending ? 'disabled' : ''}>${pending ? 'Spende wird bestätigt …' : 'Ressourcen spenden'}</button></form>`;
    }

    async function loadDetail(id, focus = true) {
      selected = Number(id); detail = null; paint();
      const n = generation;
      try {
        const next = await api(`land/${selected}`);
        if (!active || n !== generation || Number(selected) !== Number(id)) return;
        detail = {...chosen(), ...next, own_land:Boolean(next.own_land ?? chosen()?.own_land)}; paint();
        if (focus) host().querySelector('.land-detail h3')?.focus?.({preventScroll:true});
      } catch (error) {
        if (active && n === generation) {
          const box = host().querySelector('.land-detail');
          if (box) box.innerHTML = `<p role="alert">${esc(error.message)}</p><button class="button secondary" data-action="land-select" data-id="${Number(id)}">Erneut laden</button>`;
        }
      }
    }

    function onClick(action, button) {
      if (!action.startsWith('land-')) return false;
      if (action === 'land-select') loadDetail(Number(button.dataset.id));
      if (action === 'land-filter') { filter = button.dataset.id; updateFilter(); }
      if (action === 'land-reload') { active = false; render('land'); }
      if (action === 'land-world') { navigate('world'); requestAnimationFrame(() => window.ConquerWorld?.focus(Number(button.dataset.x), Number(button.dataset.y))); }
      return true;
    }

    async function onSubmit(form) {
      if (form.dataset.form !== 'land-donate') return false;
      const resources = {}, values = new FormData(form);
      for (const key of Object.keys(resourceNames)) resources[key] = Math.max(0, Number(values.get(key)) || 0);
      if (!Object.values(resources).some(Number)) { toast('Wähle mindestens eine Ressource für die Spende.'); return true; }
      const landId = Number(form.dataset.id), expectedWorld = Number(getState()?.city?.world_id || window.CONQUER_WORLD || 1), revision = Number(form.dataset.revision), n = generation;
      const signature = JSON.stringify({landId, expectedWorld, revision, resources});
      if (!retry || retry.signature !== signature) retry = {signature, payload:{expected_world_id:expectedWorld, revision, request_id:opId(), resources}};
      pending = true; form.querySelectorAll('input,button').forEach(control => control.disabled = true);
      try {
        await api(`land/${landId}/donate`, retry.payload);
        retry = null; toast('Deine Ressourcen fördern dieses Land.');
        if (!active || n !== generation || Number(selected) !== landId || Number(getState()?.city?.world_id || window.CONQUER_WORLD || 1) !== expectedWorld) return true;
        const nextState = await api('land/state');
        if (!active || n !== generation || Number(selected) !== landId || Number(getState()?.city?.world_id || window.CONQUER_WORLD || 1) !== expectedWorld) return true;
        const nextDetail = await api(`land/${landId}`);
        if (!active || n !== generation || Number(selected) !== landId || Number(getState()?.city?.world_id || window.CONQUER_WORLD || 1) !== expectedWorld) return true;
        state = nextState; selected = landId; detail = {...chosen(), ...nextDetail, own_land:Boolean(nextDetail.own_land ?? chosen()?.own_land)}; pending = false; paint();
      } catch (error) {
        toast(error.message);
        const rejected = ['STALE_LAND','LAND_LOCKED','LAND_MAX_LEVEL','INVALID_DONATION','LAND_RULE','WORLD_MISMATCH','REQUEST_REUSED','LAND_NOT_FOUND'].includes(error.code) || /zwischenzeitlich|veraltet|aktualisiert|stale/i.test(error.message);
        if (rejected) {
          retry = null; pending = false;
          if (active && n === generation && Number(selected) === landId) await loadDetail(landId, false);
        }
        else form.querySelectorAll('input,button').forEach(control => control.disabled = false);
      } finally { pending = false; }
      return true;
    }

    return {render, onClick, onSubmit};
  };
})();
