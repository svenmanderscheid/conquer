// Shared, code-native player-name frames. Server ownership can be merged later
// without changing the HUD or world-map renderers.
window.ConquerNameFrames = (() => {
    'use strict';
    const definitions = [
        ['default','Grenzlandrahmen','common','#2a72c9','#70472f','<path d="M12 2.5 19 6v5.5c0 4.4-2.8 7.7-7 10-4.2-2.3-7-5.6-7-10V6l7-3.5Z"/><path d="M8.5 9.5h7v5h-7z"/>'],
        ['ironkeep','Zinnenrahmen','legendary','#71879a','#70472f','<path d="M4 20V7h4v3h3V7h3v3h3V7h3v13Z"/><path d="M8 20v-5h8v5M6 5h12"/>'],
        ['rosehall','Rosenrahmen','legendary','#d97798','#70472f','<path d="M12 12c-5-1-5-7-1-8 1-4 7-2 6 2 4 1 3 7-1 7-1 4-7 5-8 1-4-1-3-7 1-7"/><path d="M9 16c-2 1-3 3-3 5m5-4c2 1 3 2 4 4"/>'],
        ['sandspire','Sonnenrahmen','legendary','#d49a35','#70472f','<circle cx="12" cy="12" r="4"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4M5 5l3 3m8 8 3 3M19 5l-3 3M8 16l-3 3"/>'],
        ['tidewatch','Gezeitenrahmen','legendary','#3b9eb8','#70472f','<path d="M3 10c3-3 6-3 9 0s6 3 9 0M3 15c3-3 6-3 9 0s6 3 9 0M5 20c2-2 4-2 6 0"/><path d="M12 4v5m-3-3 3-3 3 3"/>'],
        ['winterhold','Frostkristallrahmen','legendary','#70bddd','#70472f','<path d="M12 2v20M3.3 7l17.4 10M20.7 7 3.3 17M12 2l-2 3m2-3 2 3m-2 17-2-3m2 3 2-3M3.3 7l4 .2M3.3 7l1.7 3.5M20.7 17l-4-.2m4 .2-1.7-3.5"/>'],
        ['jadecourt','Jadetempelrahmen','legendary','#4e9b77','#70472f','<path d="m4 10 8-6 8 6-3 1v8H7v-8l-3-1Z"/><path d="M9 19v-6h6v6M6 10h12M9 7h6"/>'],
        ['emberforge','Schmiederahmen','legendary','#c56f3b','#70472f','<path d="M4 14h16c-1 4-4 6-8 6s-7-2-8-6Z"/><path d="M8 14V9h8v5M10 8c-2-3 1-4 2-6 3 3 4 5 1 7"/>'],
        ['ravenloft','Rabenrahmen','legendary','#766797','#70472f','<path d="M5 20c3-8 5-13 14-16-1 8-5 14-12 14"/><path d="m8 15 8-6m-6 3 1 4m2-7 3 2"/>'],
        ['clockwork','Uhrwerkrahmen','legendary','#ae813e','#70472f','<circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="2"/><path d="M12 2v4m0 12v4M2 12h4m12 0h4M5 5l3 3m8 8 3 3M19 5l-3 3M8 16l-3 3"/>'],
        ['sapphire','Saphirkronenrahmen','legendary','#416fc0','#70472f','<path d="m5 9 4 3 3-7 3 7 4-3-2 10H7L5 9Z"/><path d="M8 16h8M12 5V2"/>'],
        ['phoenix','Phönixrahmen','mythic','#e36b32','#8a4a2b','<path d="M12 21c-5-3-7-7-5-12 1 3 3 3 4 4-1-5 2-8 5-10-1 4 3 6 2 11-1 4-3 6-6 7Z"/><path d="M12 18c-2-2-2-4 0-6 2 2 3 4 0 6Z"/>'],
        ['astral','Sternenrahmen','mythic','#778de8','#70472f','<path d="m12 2 2.5 6.5L21 11l-6.5 2.5L12 20l-2.5-6.5L3 11l6.5-2.5L12 2Z"/><ellipse cx="12" cy="11" rx="10" ry="4"/>'],
        ['leviathan','Korallenrahmen','mythic','#32bfae','#70472f','<path d="M12 21V8m0 5-5-4m5 7 6-5M7 9V5m11 6V6M5 20c4-3 10-3 14 0"/><circle cx="7" cy="4" r="2"/><circle cx="19" cy="5" r="2"/>'],
        ['yggdrasil','Weltenbaumrahmen','mythic','#56a96a','#70472f','<path d="M12 22V9m0 5-5-4m5 1 5-5m-5 11 6-3M8 21h8"/><path d="M4 7c0-3 4-5 7-2 1-4 7-4 8 0 3 1 2 6-1 7H7C3 12 2 9 4 7Z"/>'],
        ['tempest','Sturmrahmen','mythic','#5aaed5','#70472f','<path d="M5 14c-5-1-4-8 1-8 2-5 9-4 10 1 5-1 7 7 2 8H5Z"/><path d="m13 13-4 6h4l-2 4 6-7h-4l2-3"/>'],
        ['eclipse','Finstersonnenrahmen','mythic','#965bcc','#70472f','<circle cx="12" cy="12" r="8"/><path d="M16 5a8 8 0 0 0 0 14 8 8 0 1 1 0-14Z"/><path d="M12 1v2m0 18v2M1 12h2m18 0h2"/>'],
        ['dragon','Drachenrahmen','mythic','#c88735','#70472f','<path d="M12 18c-4-5-7-8-10-8 2 5 4 8 9 11M12 18c4-5 7-8 10-8-2 5-4 8-9 11"/><path d="M9 15 6 8l5 3 1-8 1 8 5-3-3 7M10 18h4"/>']
    ];
    const entries = Object.freeze(definitions.map(([id,name,rarity,accent,edge,motif]) => Object.freeze({id,name,rarity,accent,edge,motif})));
    const ids = Object.freeze(entries.map(entry => entry.id));
    const byId = new Map(entries.map(entry => [entry.id,entry]));
    const safeId = value => {
        if(value && typeof value === 'object')value=value.id??value.name_frame??value.frame_id??value.skin_id;
        const id=typeof value==='string'?value.trim().toLowerCase():'';
        return byId.has(id)?id:'default';
    };
    const get = value => byId.get(safeId(value));
    const resolve = (source,fallback='default') => {
        if(typeof source==='string'&&byId.has(source.trim().toLowerCase()))return source.trim().toLowerCase();
        if(source && typeof source==='object'){
            const explicit=source.name_frame??source.name_frame_id??source.player_name_frame??source.equipped??source.current;
            if(typeof explicit==='string'&&byId.has(explicit.trim().toLowerCase()))return explicit.trim().toLowerCase();
            if(source.profile)return resolve(source.profile,fallback);
        }
        return safeId(fallback);
    };
    const normalizeState = (raw,fallback='default') => {
        const source=raw?.name_frames??raw?.frames??raw??{};
        const rows=Array.isArray(source)?source:Array.isArray(source.entries)?source.entries:[];
        const rowId=row=>typeof row?.id==='string'&&byId.has(row.id.trim().toLowerCase())?row.id.trim().toLowerCase():null;
        const supplied=new Map(rows.map(row=>[rowId(row),row]).filter(([id])=>id));
        const marked=rowId(rows.find(row=>row?.equipped&&rowId(row)));
        const equipped=resolve(source,marked||fallback);
        return Object.freeze({
            equipped,
            entries:Object.freeze(entries.map(entry=>Object.freeze({...entry,owned:entry.id==='default'||Boolean(supplied.get(entry.id)?.owned),equipped:entry.id===equipped})))
        });
    };
    const ornament = entry => `<span class="name-frame-ornament" data-name-frame-ornament aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${entry.motif}</svg></span>`;
    const escape = value => String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const markup = (label,value='default',className='') => {
        const entry=get(value),extra=String(className).replace(/[^a-z0-9_-]/gi,' ').trim();
        return `<span class="name-frame name-frame--${entry.id}${extra?' '+extra:''}" data-name-frame="${entry.id}">${ornament(entry)}<span class="name-frame-label">${escape(label)}</span>${ornament(entry)}</span>`;
    };
    const apply = (node,value='default') => {
        if(!node?.classList)return get(value);
        const entry=get(value);
        node.querySelectorAll(':scope > [data-name-frame-ornament]').forEach(child=>child.remove());
        node.classList.add('name-frame');
        for(const id of ids)node.classList.remove(`name-frame--${id}`);
        node.classList.add(`name-frame--${entry.id}`);node.dataset.nameFrame=entry.id;
        node.insertAdjacentHTML('afterbegin',ornament(entry));node.insertAdjacentHTML('beforeend',ornament(entry));
        return entry;
    };
    // Hook for the future server adapter: sync all self-name slots, then notify UI extensions.
    const syncSelf = (raw,root=document,fallback='default') => {
        const state=normalizeState(raw,fallback);
        root.querySelectorAll('[data-name-frame-self]').forEach(node=>apply(node,state.equipped));
        if(typeof window.CustomEvent==='function')window.dispatchEvent(new CustomEvent('conquer:name-frame-changed',{detail:state}));
        return state;
    };
    return Object.freeze({entries,ids,get,resolve,normalizeState,markup,apply,syncSelf});
})();
