// Shared catalog for collection menus and immutable march snapshots on the world map.
window.ConquerMarchSkins = (()=>{
    const entries=Object.freeze([
        {id:'default',castle_skin:'default',name:'Grenzlandzug',rarity:'common',description:'Wächter, Feuerbogenschützen und Schattenreiter ziehen als sichtbare Formation über die Karte.',effect_color:'#b8d9ec',price_gems:0,bonus_pct:5,unlock_castle_level:2},
        {id:'forest',castle_skin:'forest',name:'Waldläufer',rarity:'legendary',description:'Ein verzauberter Wurzelhirsch trägt Blätterkrone und goldene Wegglocke durch den Wald.',effect_color:'#78a957',price_gems:1200,bonus_pct:5,unlock_castle_level:1},
        {id:'fire',castle_skin:'fire',name:'Glutstürmer',rarity:'legendary',description:'Ein kräftiger Glutwidder schreitet mit Basalthörnern und kupferner Rüstung voran.',effect_color:'#ed7a32',price_gems:1200,bonus_pct:5,unlock_castle_level:1},
        {id:'water',castle_skin:'water',name:'Wellenreiter',rarity:'legendary',description:'Ein flinker Wellenotter trägt Muschelpanzer, Perlen und einen mächtigen Schweif aus Wasser.',effect_color:'#44bfd0',price_gems:1200,bonus_pct:5,unlock_castle_level:1},
        {id:'wind',castle_skin:'wind',name:'Wolkengreif',rarity:'legendary',description:'Ein junger Wolkengreif läuft und gleitet mit breiten Schwingen durch die Welt.',effect_color:'#89c8eb',price_gems:1200,bonus_pct:5,unlock_castle_level:1}
    ].map(entry=>Object.freeze(entry)));
    const legacyEntries=Object.freeze([{"id":"ironkeep","castle_skin":"ironkeep","name":"Eisenkoloss","rarity":"legendary","description":"Ein gepanzerter Festungsgolem stampft für die Eisenwacht über die Weltkarte.","effect_color":"#91a9bc","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"rosehall","castle_skin":"rosehall","name":"Rosenhirsch","rarity":"legendary","description":"Ein elfenbeinfarbener Hirsch trägt ein lebendes Geweih aus Rosenblüten.","effect_color":"#d97798","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"sandspire","castle_skin":"sandspire","name":"Sonnenskarabäus","rarity":"legendary","description":"Ein goldener Riesenskarabäus zieht mit Türkissteinen und Sonnenpanzer durch die Dünen.","effect_color":"#e6b55a","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"tidewatch","castle_skin":"tidewatch","name":"Leuchtrücken","rarity":"legendary","description":"Eine große Meeresschildkröte trägt einen kleinen Leuchtturm auf ihrem Panzer.","effect_color":"#55b8cf","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"winterhold","castle_skin":"winterhold","name":"Frostmammut","rarity":"legendary","description":"Ein zottiges Mammut mit Eisstoßzähnen bahnt der Winterfeste den Weg.","effect_color":"#a0d9f3","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"jadecourt","castle_skin":"jadecourt","name":"Jadeglockenlöwe","rarity":"legendary","description":"Ein jadegrüner Tempellöwe trägt Pagodendetails und eine klingende Hofglocke.","effect_color":"#70b89b","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"emberforge","castle_skin":"emberforge","name":"Glutsalamander","rarity":"legendary","description":"Ein geschmiedeter Salamander glüht zwischen Kupferplatten und dunklem Eisen.","effect_color":"#d58955","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"ravenloft","castle_skin":"ravenloft","name":"Rabenfürst","rarity":"legendary","description":"Ein riesiger gepanzerter Rabe gleitet mit violetten Bändern aus dem Rabenhorst.","effect_color":"#afa0c9","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"clockwork","castle_skin":"clockwork","name":"Uhrwerkhase","rarity":"legendary","description":"Ein kupferner Maschinenhase sprintet mit Zahnrädern und kleinen Dampfstößen.","effect_color":"#ceac69","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"sapphire","castle_skin":"sapphire","name":"Saphirpfau","rarity":"legendary","description":"Ein königlicher Pfau entfaltet einen Fächer aus großen geschliffenen Saphiren.","effect_color":"#648fd6","price_gems":1200,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"phoenix","castle_skin":"phoenix","name":"Phönixgarde","rarity":"mythic","description":"Ein rotgoldener Phönix mit Obsidianrüstung, Sonnenkranz und vielschichtigen Flammenfedern. Beim Angriff entfaltet sich seine Feuerkrone.","effect_color":"#ff8a42","price_gems":2400,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"dragon","castle_skin":"dragon","name":"Drachenmarsch","rarity":"mythic","description":"Ein smaragdgrüner Drache in gealterter Bronze begleitet die Drachenfestung. Bei Angriffen stößt er herab und speit bernsteinfarbenes Feuer.","effect_color":"#d99a42","price_gems":2400,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"astral","castle_skin":"astral","name":"Sternenwal","rarity":"mythic","description":"Ein schwebender Himmelswal zieht einen Schweif aus Sternenlicht hinter sich her.","effect_color":"#9bafff","price_gems":2400,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"leviathan","castle_skin":"leviathan","name":"Korallenleviathan","rarity":"mythic","description":"Ein türkisfarbener Meeresleviathan windet sich zwischen lebenden Korallen.","effect_color":"#55f4df","price_gems":2400,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"yggdrasil","castle_skin":"yggdrasil","name":"Wurzelkoloss","rarity":"mythic","description":"Ein uralter Baumkoloss schreitet auf mächtigen Wurzeln durch die Welt.","effect_color":"#7cff9b","price_gems":2400,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"tempest","castle_skin":"tempest","name":"Sturmqualle","rarity":"mythic","description":"Eine schwebende Gewitterqualle lädt ihre langen Tentakel mit Blitzen auf.","effect_color":"#8edbff","price_gems":2400,"bonus_pct":5,"unlock_castle_level":null,"legacy":true},{"id":"eclipse","castle_skin":"eclipse","name":"Finstersonnenwagen","rarity":"mythic","description":"Ein reiterloser Obsidianwagen trägt eine schwarze Sonne mit violetter Korona.","effect_color":"#cb91ff","price_gems":2400,"bonus_pct":5,"unlock_castle_level":null,"legacy":true}].map(Object.freeze));
    const allEntries=Object.freeze([...entries,...legacyEntries]);
    const allIds=Object.freeze(allEntries.map(entry=>entry.id));
    const ids=Object.freeze(entries.map(entry=>entry.id));
    const get=id=>allEntries.find(entry=>entry.id===id)||entries[0];
    const locomotions=Object.freeze({
        default:'walk',forest:'gallop',fire:'heavy-walk',water:'swim',wind:'fly',ironkeep:'heavy-walk',rosehall:'gallop',sandspire:'crawl',tidewatch:'swim',
        winterhold:'heavy-walk',jadecourt:'gallop',emberforge:'crawl',ravenloft:'fly',clockwork:'hop',
        sapphire:'prance',phoenix:'fly',dragon:'fly',astral:'glide',leviathan:'swim',
        yggdrasil:'heavy-walk',tempest:'hover',eclipse:'glide'
    });
    const locomotion=id=>locomotions[get(id).id]||'walk';
    const articulatedIds=Object.freeze([
        'forest','fire','water','wind',
        'ironkeep','rosehall','sandspire','tidewatch','winterhold','jadecourt','emberforge','ravenloft',
        'clockwork','sapphire','phoenix','dragon','astral','leviathan','yggdrasil','tempest','eclipse'
    ]);
    const flightLayoutIds=Object.freeze(['phoenix','dragon']);
    const hasMotion=id=>articulatedIds.includes(get(id).id);
    // Phoenix and dragon use a larger map silhouette and an attack banking pose.
    // Other articulated creatures keep the ordinary march footprint.
    const hasFlightLayout=id=>flightLayoutIds.includes(get(id).id);
    const image=(base,id='default')=>{
        const resolved=get(id).id;
        if(hasFlightLayout(resolved))return `${base}/assets/art/marches/flight-${resolved}.png?v=2`;
        return `${base}/assets/art/marches/march-${resolved}.webp?v=${['default','forest','fire','water','wind'].includes(resolved)?4:3}`;
    };
    const motionImage=(base,id='default')=>{
        const resolved=get(id).id;
        if(!hasMotion(resolved))return image(base,resolved);
        if(hasFlightLayout(resolved))return `${base}/assets/art/marches/flight-${resolved}.webp?v=2`;
        return `${base}/assets/art/marches/animated-march-${resolved}.webp?v=2`;
    };
    // Visual motifs only. Ownership, price and travel speed remain server-defined.
    const effect=id=>{
        if(id==='dragon')return {kind:'dragon',symbol:'♨'};
        if(['fire','phoenix','emberforge'].includes(id))return {kind:'ember',symbol:'♨'};
        if(['winterhold','sapphire'].includes(id))return {kind:'frost',symbol:'❄'};
        if(['forest','yggdrasil','jadecourt','rosehall'].includes(id))return {kind:'leaf',symbol:'❧'};
        if(['astral','eclipse','ravenloft'].includes(id))return {kind:'arcane',symbol:id==='eclipse'?'☾':'✦'};
        if(['water','wind','leviathan','tempest'].includes(id))return {kind:'storm',symbol:id==='wind'||id==='tempest'?'ϟ':'≈'};
        if(id==='clockwork')return {kind:'steam',symbol:'⚙'};
        return {kind:'dust',symbol:'✦'};
    };
    const normalizeState=raw=>{
        const source=raw?.march_skins&&Array.isArray(raw.march_skins.entries)?raw.march_skins:raw;
        const rows=Array.isArray(source?.entries)?source.entries:[];
        const supplied=new Map(rows.filter(row=>row&&allIds.includes(row.id)).map(row=>[row.id,row]));
        const requested=allIds.includes(source?.equipped)?source.equipped:null;
        const marked=rows.find(row=>row?.equipped&&allIds.includes(row.id))?.id||null;
        const candidate=requested||marked;
        const equipped=visibleCandidate(candidate);
        function visibleCandidate(id){return ids.includes(id)||supplied.get(id)?.owned?id:null;}
        const visible=[...entries,...legacyEntries.filter(entry=>supplied.get(entry.id)?.owned)];
        const normalized=visible.map(baseEntry=>{
            const row=supplied.get(baseEntry.id)||{};
            return Object.freeze({
                ...baseEntry,
                name:String(row.name||baseEntry.name),description:String(row.description||baseEntry.description),
                rarity:['common','legendary','mythic'].includes(row.rarity)?row.rarity:baseEntry.rarity,
                effect_color:/^#[0-9a-f]{6}$/i.test(row.effect_color||'')?row.effect_color:baseEntry.effect_color,
                price_gems:Math.max(0,Math.floor(Number(row.price_gems??baseEntry.price_gems)||0)),
                bonus_pct:Math.max(0,Math.floor(Number(row.bonus_pct??baseEntry.bonus_pct)||0)),
                unlock_castle_level:Math.max(1,Math.floor(Number(row.unlock_castle_level??baseEntry.unlock_castle_level)||1)),
                owned:Boolean(row.owned),equipped:baseEntry.id===equipped,can_claim:Boolean(row.can_claim)
            });
        });
        return Object.freeze({entries:Object.freeze(normalized),equipped,bonus_pct:equipped?(normalized.find(entry=>entry.id===equipped)?.bonus_pct||0):0});
    };
    return Object.freeze({entries,legacyEntries,ids,allIds,get,image,motionImage,hasMotion,hasFlightLayout,locomotion,effect,normalizeState});
})();
