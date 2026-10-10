/* Announce fresh server-confirmed rallies once, including the leader's own rally. */
'use strict';
window.ConquerRallyNotifications = function ({announce, now = () => Date.now()}) {
    let snapshot = null, baseline = true;
    const seen = new Set();
    function pause() { baseline = true; }
    function observe(rows, {playerId, worldId, allianceId, serverTime = now() / 1000} = {}) {
        if (!Array.isArray(rows) || !Number(playerId) || !Number(worldId) || allianceId == null) return;
        const scope = `${Number(playerId)}:${Number(worldId)}:${Number(allianceId)}`, time = now();
        const changed = snapshot?.scope !== scope;
        if (changed) seen.clear();
        const quiet = baseline || changed || time - snapshot.time > 90000;
        snapshot = {scope, time};
        baseline = false;
        const fresh = [];
        for (const row of rows) {
            if (!Number(allianceId) || !Number(row.id)
                || (row.world_id && Number(row.world_id) !== Number(worldId))
                || (row.result?.alliance_id && Number(row.result.alliance_id) !== Number(allianceId))) continue;
            const id = Number(row.id), known = seen.has(id);
            seen.add(id);
            if (quiet || known || !['gathering', 'marching'].includes(row.status)) continue;
            const raw = String(row.created_at || '').replace(' ', 'T');
            const created = Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(raw) ? raw : raw + 'Z') / 1000;
            if (Number.isFinite(created) && serverTime - created >= -5 && serverTime - created <= 90) fresh.push(row);
        }
        if (fresh.length) announce(fresh);
    }
    return {observe, pause};
};
