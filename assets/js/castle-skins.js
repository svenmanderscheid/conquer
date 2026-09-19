// Map portraits and village geometry share these stable skin identifiers.
window.ConquerCastleSkins = (()=>{
    const entries=Object.freeze([
        {id:'default',name:'Grenzlandburg',rarity:'common',effectColor:'#b8d9ec',description:'Helle Steinmauern, blaue Turmdächer und das Wappen über dem Tor.'},
        {id:'ironkeep',name:'Eisenwacht',rarity:'legendary',effectColor:'#91a9bc',description:'Eine stählerne Höhenburg mit Zinnen, Werkhöfen und einer drehenden Windmühle.'},
        {id:'rosehall',name:'Rosenpalast',rarity:'legendary',effectColor:'#d97798',description:'Ein heller Gartenpalast mit Rosentürmen und einem wehenden Königsbanner.'},
        {id:'sandspire',name:'Dünenkrone',rarity:'legendary',effectColor:'#e6b55a',description:'Ein Wüstenpalast mit Sandsteinbögen und einem drehenden Sonnenornament.'},
        {id:'tidewatch',name:'Gezeitenwacht',rarity:'legendary',effectColor:'#55b8cf',description:'Eine maritime Hafenfestung mit einem rotierenden Leuchtturmaufsatz.'},
        {id:'winterhold',name:'Winterfeste',rarity:'legendary',effectColor:'#a0d9f3',description:'Eine verschneite Bergfestung mit einem langsam drehenden Eiskristall.'},
        {id:'jadecourt',name:'Jadehof',rarity:'legendary',effectColor:'#70b89b',description:'Ein jadegrüner Tempelpalast mit geschwungenen Dächern und schwingender Glocke.'},
        {id:'emberforge',name:'Glutschmiede',rarity:'legendary',effectColor:'#d58955',description:'Eine kupferne Schmiedefestung mit einem arbeitenden Schmiedehammer.'},
        {id:'ravenloft',name:'Rabenhorst',rarity:'legendary',effectColor:'#afa0c9',description:'Eine gotische Felsenburg mit hohen Spitzdächern und drehender Raben-Wetterfahne.'},
        {id:'clockwork',name:'Uhrwerkzitadelle',rarity:'legendary',effectColor:'#ceac69',description:'Eine reich verzierte Uhrwerkburg mit einem großen rotierenden Zahnrad.'},
        {id:'sapphire',name:'Saphirresidenz',rarity:'legendary',effectColor:'#648fd6',description:'Ein königlicher Kuppelpalast mit einem einzelnen kreisenden Saphir.'},
        {id:'phoenix',name:'Phönixthron',rarity:'mythic',effectColor:'#ff8a42',description:'Ein goldroter Flammenthron mit schwingenden Phönixflügeln und glühenden Funken.'},
        {id:'astral',name:'Sternenwarte der Ewigkeit',rarity:'mythic',effectColor:'#9bafff',description:'Eine kosmische Sternenburg mit kreisenden Himmelskörpern und schimmernder Sternenaura.'},
        {id:'leviathan',name:'Thron der Gezeiten',rarity:'mythic',effectColor:'#55f4df',description:'Ein versunkener Meerespalast mit leuchtenden Korallen und wirbelnder Wassermagie.'},
        {id:'yggdrasil',name:'Herz des Weltenbaums',rarity:'mythic',effectColor:'#7cff9b',description:'Ein lebendiges Baumheiligtum mit goldenen Runen und tanzenden Waldlichtern.'},
        {id:'tempest',name:'Sturmkrone',rarity:'mythic',effectColor:'#8edbff',description:'Eine schwebende Sturmfestung mit kreisenden Wolken und pulsierender Blitzenergie.'},
        {id:'eclipse',name:'Zitadelle der Finstersonne',rarity:'mythic',effectColor:'#cb91ff',description:'Eine obsidianfarbene Zitadelle unter einer schwarzen Sonne mit violetter Korona.'},
        {id:'dragon',name:'Drachenstahl-Zitadelle',rarity:'mythic',effectColor:'#63cfff',description:'Eine goldweiße Drachenzitadelle mit wehenden Bannern, leuchtenden Saphirkristallen und magischem Drachenatem.'}
    ].map(entry=>Object.freeze(entry)));
    const ids=Object.freeze(entries.map(entry=>entry.id));
    const get=skin=>entries.find(entry=>entry.id===skin)||entries[0];
    const image=(base,skin='default')=>`${base}/assets/art/map/castle-${get(skin).id}.png?v=epic7`;
    const motionImage=(base,skin='default')=>get(skin).rarity!=='common'?`${base}/assets/art/map/castle-${get(skin).id}.webp?v=epic7`:image(base,skin);
    const syncMotion=(root=document)=>{
        const reduced=document.body?.classList.contains('reduced-motion');
        root.querySelectorAll('img[data-castle-motion]').forEach(img=>{
            const src=reduced?img.dataset.castleStill:img.dataset.castleMotion;
            if(img.getAttribute('src')!==src)img.setAttribute('src',src);
        });
    };
    const observeMotion=()=>{
        const observer=new MutationObserver(()=>syncMotion());
        observer.observe(document.body,{attributes:true,attributeFilter:['class']});
    };
    if(typeof document!=='undefined'&&typeof MutationObserver!=='undefined'){if(document.body)observeMotion();else document.addEventListener('DOMContentLoaded',observeMotion,{once:true});}
    return Object.freeze({entries,ids,get,image,motionImage,syncMotion});
})();
