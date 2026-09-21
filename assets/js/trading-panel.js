/* Caravan and weekly VIP offers share the reference game's fixed market window. */
window.ConquerTrading = function(ctx){
    'use strict';
    const {base,esc,fmt,getKingdom,getState,getMarket,action,toast}=ctx;
    const host=()=>document.querySelector('#content');
    const data=()=>getKingdom()?.trading;
    const resources={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold',gems:'Edelsteine'};
    let mode='merchant',vipView='mine',vipCategory='all',busy=false,clockOffset=0,lastServerTime='',refreshRequested='';
    const num=n=>Number(n||0);
    const now=()=>ctx.now?ctx.now():Date.now()+clockOffset;
    const art=file=>base+'/assets/art/items/'+file+'?v='+encodeURIComponent(window.CONQUER_ITEM_ART_VERSION||'catalog3');
    const resourceArt=key=>key==='gems'?art('gems.svg'):base+'/assets/art/ui-resources/'+key+'.png';
    const list=()=>mode==='vip'?data()?.vip:data();
    const allOffers=()=>list()?.offers||[];
    const category=o=>({resource_pack:'resources',speedup:'speedups',boost:'boosts',vip_point:'progress',ap_refill:'progress',chest:'treasures',fragment_pack:'treasures'})[o.item?.category]||'other';
    const offers=()=>allOffers().filter(o=>mode!=='vip'||(vipView==='all'||!o.locked)&&(vipCategory==='all'||category(o)===vipCategory));
    const deadline=()=>Date.parse(mode==='merchant'?getMarket?.()?.refresh_at:mode==='vip'?data()?.vip?.reset_at:data()?.refresh_at);
    const balance=key=>num(key==='gems'?getKingdom()?.profile?.gems??getState()?.player?.gems:getState()?.city?.[key]);
    const affordable=(o,q)=>balance(o.price?.resource)>=num(o.price?.amount)*q;
    const expired=()=>Number.isFinite(deadline())&&deadline()<=now();
    const short=n=>num(n)>=1000000?String(num(n)/1000000).replace('.',',')+'M':num(n)>=1000?String(num(n)/1000).replace('.',',')+'k':fmt(num(n));
    const timeStamp=s=>num(s)>=86400?short(num(s)/86400)+'d':num(s)>=3600?short(num(s)/3600)+'h':short(num(s)/60)+'min';
    function stamp(i){return i.category==='resource_pack'?short(i.amount):i.duration_seconds?timeStamp(i.duration_seconds):i.category==='vip_point'?short(i.vip_points):i.category==='ap_refill'?short(i.ap_amount):i.category==='fragment_pack'?short(i.fragment_amount)+' Fr.':'';}
    function itemArt(i){
        if(i.icon)return `<img src="${esc(art(i.icon))}" alt="" loading="lazy">${i.category==='speedup'&&!i.icon_framed?specialty(i):''}`;
        if(i.category==='resource_pack')return `<img src="${resourceArt(i.resource)}" alt="" loading="lazy">`;
        const file=i.category==='speedup'?'speedup.svg':i.category==='vip_point'?'prestige.svg':i.category==='ap_refill'?'energy.svg':i.category==='chest'?'chest-'+(['silver','gold','platinum'].includes(i.chest_type)?i.chest_type:'silver')+'.svg':'fragment.svg';
        return `<img src="${art(file)}" alt="" loading="lazy">${i.category==='speedup'?specialty(i):''}`;
    }
    function specialty(i){const icon=({building:'hammer.svg',research:'research.svg',training:'helmet.svg',healing:'healing.svg'})[i.subcategory];return icon?`<span class="trading-specialty"><img src="${art(icon)}" alt=""></span>`:'';}
    function price(o,q){return `<span class="trading-price"><img src="${resourceArt(o.price.resource)}" alt="${esc(resources[o.price.resource]||o.price.resource)}"><strong>${fmt(num(o.price.amount)*q)}</strong></span>`;}
    function buyButton(o,q,all=false){
        const disabled=busy||o.locked||num(o.remaining)<q||q<1||!affordable(o,q)||expired();
        const label=all?'Alle '+fmt(q)+' kaufen':num(o.quantity)>1?fmt(o.quantity)+' kaufen':'Kaufen';
        const why=o.locked?(mode==='vip'?'VIP '+o.vip_level+' benötigt':'Handelsposten benötigt'):num(o.remaining)<1?'Ausverkauft':expired()?'Angebote werden erneuert':!affordable(o,q)?'Nicht genug '+(resources[o.price.resource]||o.price.resource):label;
        return `<button type="button" class="trading-buy ${o.price.resource==='gems'?'pays-gems':'pays-resources'} ${all?'buy-all':''}" data-action="trading-buy" data-id="${esc(o.id)}" data-quantity="${q}" ${disabled?'disabled':''} title="${esc(why)}" aria-label="${esc(label+' · '+(o.item.name_de||o.item.name)+' · '+fmt(num(o.price.amount)*q)+' '+resources[o.price.resource]+(disabled?' · '+why:''))}">${price(o,q)}<span>${label}</span></button>`;
    }
    function card(o){
        const i=o.item||{},rarity=i.rarity||i.grade||'normal',grade=['normal','rare','epic','legendary','mythic'].includes(rarity)?rarity:'normal';
        return `<article class="trading-card ${o.locked?'is-locked':''} ${num(o.remaining)<1?'is-sold-out':''}" aria-label="${esc(i.name_de||i.name||'Gegenstand')}"><div class="trading-item-art grade-${grade} ${i.icon_framed?'is-framed':''}">${itemArt(i)}${stamp(i)?`<span class="trading-item-stamp">${esc(stamp(i))}</span>`:''}${o.treasure_code?'<span class="trading-item-fragment">✚ 1</span>':''}${num(o.quantity)>1?`<span class="trading-item-count">×${fmt(o.quantity)}</span>`:''}${o.locked?'<span class="trading-lock" aria-label="Gesperrt">▣</span>':''}</div>${num(o.discount)>0?`<span class="trading-discount ${num(o.discount)>=60?'hot':''}">−${fmt(o.discount)}%</span>`:''}<h3>${esc(i.name_de||i.name||'Gegenstand')}</h3>${mode==='vip'?`<strong class="trading-vip-level">VIP ${fmt(o.vip_level)}</strong>`:''}<p class="trading-item-description" title="${esc(i.description_de||i.description||'')}">${esc(i.description_de||i.description||'Für dein Königreich.')}</p><div class="trading-stock">${num(o.remaining)>0?'Verfügbar: <strong>'+fmt(o.remaining)+'</strong>':'Ausverkauft'}${mode==='vip'?'<small> / Woche</small>':''}</div><div class="trading-purchase">${buyButton(o,1)}${mode==='vip'&&num(o.limit)>1?buyButton(o,Math.max(1,num(o.remaining)),true):''}</div></article>`;
    }
    function grids(){
        const visible=offers();
        if(mode!=='vip')return `<div class="trading-grid">${visible.map(card).join('')}</div>`;
        const groups=new Map();for(const o of visible){const level=num(o.vip_level);if(!groups.has(level))groups.set(level,[]);groups.get(level).push(o);}
        return [...groups].map(([level,items])=>`<section class="trading-level-group" aria-labelledby="trading-level-${level}"><h3 id="trading-level-${level}"><span>VIP ${fmt(level)}</span><small>${fmt(items.length)} ${items.length===1?'Angebot':'Angebote'}</small></h3><div class="trading-grid">${items.map(card).join('')}</div></section>`).join('');
    }
    function vipTools(){
        if(mode!=='vip')return'';
        const cats=[['all','Alle'],['resources','Rohstoffe'],['speedups','Beschleuniger'],['boosts','Boni'],['progress','VIP & Energie'],['treasures','Truhen & Relikte']];
        const available=new Set(allOffers().map(category));
        return `<div class="trading-tools"><div class="trading-view-switch" role="group" aria-label="Sichtbare VIP-Angebote"><button type="button" data-action="trading-view" data-id="mine" class="${vipView==='mine'?'active':''}" aria-pressed="${vipView==='mine'}">Für dich</button><button type="button" data-action="trading-view" data-id="all" class="${vipView==='all'?'active':''}" aria-pressed="${vipView==='all'}">Alle Stufen <small>${fmt(allOffers().length)}</small></button></div><div class="trading-categories" role="group" aria-label="Gegenstandsart">${cats.filter(([id])=>id==='all'||available.has(id)).map(([id,label])=>`<button type="button" data-action="trading-category" data-id="${id}" class="${vipCategory===id?'active':''}" aria-pressed="${vipCategory===id}">${label}</button>`).join('')}</div></div>`;
    }
    function shopTabs(){
        const tabs=[['merchant','Händler'],['crystals','Kristall-Shop'],['vip','VIP-Shop'],['caravan','Karawane']];
        return `<nav class="trading-tabs shop-tabs" aria-label="Shopbereiche">${tabs.map(([id,label])=>`<button type="button" data-action="trading-tab" data-id="${id}" class="${mode===id?'active':''}" aria-pressed="${mode===id}">${label}</button>`).join('')}</nav>`;
    }
    function merchantView(){
        const market=getMarket?.(),offers=market?.offers||[],balances=market?.resources||getState()?.city||{};
        const cards=offers.map(o=>{const balance=num(balances[o.give.resource]),enough=balance>=num(o.give.amount),giveName=resources[o.give.resource]||o.give.resource,receiveName=resources[o.receive.resource]||o.receive.resource;return `<article class="shop-merchant-card"><div class="shop-merchant-reward"><img src="${resourceArt(o.receive.resource)}" alt="${esc(receiveName)}" loading="lazy"><span>Du erhältst</span><strong>+${fmt(o.receive.amount)} ${esc(receiveName)}</strong></div><h3>${esc(o.name)}</h3><div class="shop-merchant-balance"><span>Dein Bestand</span><strong>${fmt(balance)} ${esc(giveName)}</strong></div><button type="button" class="button gold wide shop-merchant-action ${o.give.resource==='gems'?'pays-gems':''}" data-action="market-trade" data-id="${esc(o.id)}" ${enough?'':'disabled'} aria-label="${esc((enough?'Eintauschen':'Nicht genug '+giveName)+': '+fmt(o.give.amount)+' '+giveName)}"><span class="shop-merchant-cost"><img src="${resourceArt(o.give.resource)}" alt=""><strong>${fmt(o.give.amount)}</strong><small>${esc(giveName)}</small></span><span class="shop-merchant-action-label">${enough?'Eintauschen':'Nicht genug'}</span></button></article>`;}).join('');
        const history=(market?.history||[]).map(entry=>`<div class="shop-merchant-history-row"><strong>${esc(entry.name)}</strong><small>${new Date(String(entry.created_at).replace(' ','T')+'Z').toLocaleString('de-DE')}</small></div>`).join('');
        return `${shopTabs()}<header class="trading-summary"><div><h2>Händler</h2><p>Faire 1:1-Tauschkurse und besondere Kristallangebote.</p></div><div class="trading-reset"><span>Neue Angebote in</span><strong data-trading-countdown>--:--:--</strong><div class="trading-countdown-track"><i data-trading-progress></i></div></div></header><div class="trading-scroll" data-mode="merchant" tabindex="0" aria-label="Angebote des Händlers"><div class="shop-merchant-grid">${cards}</div>${history?`<section class="shop-merchant-history"><h3>Letzte Handelsabschlüsse</h3>${history}</section>`:''}${!offers.length?'<div class="trading-empty">Der Händler wird geladen.</div>':''}</div><footer class="trading-footer"><span>${fmt(offers.length)} Angebote · Nach jedem Kauf wird der Platz neu belegt.</span><span>Alle 24 Stunden komplett erneuert.</span></footer>`;
    }
    function crystalView(){
        const gems=num(getKingdom()?.profile?.gems??getState()?.player?.gems);
        return `${shopTabs()}<header class="trading-summary"><div><h2>Kristall-Shop</h2><p>Kristalle und besondere Pakete.</p></div><span class="trading-level">${fmt(gems)} Kristalle</span></header><div class="trading-scroll shop-crystal-view" data-mode="crystals" tabindex="0"><div class="crystal-shop-summary"><img src="${base}/assets/art/items/gems.svg" alt=""><span><small>Dein Bestand</small><strong>${fmt(gems)} Kristalle</strong></span></div><p class="notice">Kristallpakete werden erst mit der späteren App- und Zahlungsanbindung freigeschaltet. Es werden hier noch keine Käufe oder Preise vorgetäuscht.</p></div><footer class="trading-footer"><span>Sicher vorbereitet</span><span>Noch keine Echtgeldkäufe aktiv.</span></footer>`;
    }
    function render(){
        if(!host())return;
        if(mode==='merchant'){host().innerHTML=`<section class="trading-shell" aria-label="Shop">${merchantView()}</section>`;updateTime();return;}
        if(mode==='crystals'){host().innerHTML=`<section class="trading-shell" aria-label="Shop">${crystalView()}</section>`;return;}
        const d=data();if(!d){host().innerHTML='<div class="trading-empty">Der Handelsposten wird geladen.</div>';return;}
        if(d.server_time!==lastServerTime){lastServerTime=d.server_time;const t=Date.parse(d.server_time);if(Number.isFinite(t))clockOffset=t-Date.now();}
        const previous=host().querySelector('.trading-scroll'),sameList=previous?.dataset.mode===mode&&previous?.dataset.view===(mode==='vip'?vipView:'')&&previous?.dataset.category===(mode==='vip'?vipCategory:''),scroll=sameList?previous.scrollTop:0;
        const levelControl=mode==='caravan'&&ctx.onUpgrade?`<button type="button" class="trading-level trading-upgrade" data-action="trading-upgrade" aria-label="Handelsposten Stufe ${fmt(d.market_level)}, Markt ausbauen" ${busy?'disabled':''}><span>Markt St. ${fmt(d.market_level)}</span><small>Markt ausbauen ↗</small></button>`:`<span class="trading-level">${mode==='vip'?'Dein VIP '+fmt(d.vip?.level):'Markt St. '+fmt(d.market_level)}</span>`;
        const empty=num(d.market_level)<1?'Baue zuerst deinen Handelsposten, um Angebote zu kaufen.':mode==='vip'?'Für diesen Filter gibt es keine Angebote.':'Baue deinen Handelsposten, um Angebote zu erhalten.';
        host().innerHTML=`<section class="trading-shell" aria-label="Shop">${shopTabs()}<header class="trading-summary"><div><h2>${mode==='vip'?'VIP-Wochenangebote':'Die Karawane ist da'}</h2><p>${mode==='vip'?fmt(offers().length)+' sichtbar · Freigeschaltete Angebote zuerst':fmt(offers().length)+' Angebote · Baue den Markt für mehr Auswahl aus.'}</p>${levelControl}</div><div class="trading-reset"><span>${mode==='vip'?'Neue Wochenvorräte in':'Nächste Karawane in'}</span><strong data-trading-countdown>--:--:--</strong><div class="trading-countdown-track"><i data-trading-progress></i></div></div></header>${vipTools()}<div class="trading-scroll" data-mode="${mode}" data-view="${mode==='vip'?vipView:''}" data-category="${mode==='vip'?vipCategory:''}" tabindex="0" aria-label="${mode==='vip'?'Gefilterte VIP-Angebote':'Angebote der Karawane'}, nach unten scrollen">${grids()}${!offers().length?`<div class="trading-empty">${empty}</div>`:''}</div><footer class="trading-footer"><span>${mode==='vip'?fmt(offers().length)+' von '+fmt(allOffers().length)+' Angeboten':'Neue Angebote alle 8 Stunden.'}</span><span>${mode==='vip'?'Wöchentliche Vorräte · Items landen im Inventar.':'Gekaufte Items landen im Inventar.'}</span></footer></section>`;
        host().querySelector('.trading-scroll').scrollTop=scroll;
        updateTime();
    }
    function updateTime(){
        const el=host()?.querySelector('[data-trading-countdown]');if(!el)return;
        const remaining=Math.max(0,Math.ceil((deadline()-now())/1000)),valid=Number.isFinite(remaining);
        const days=Math.floor(remaining/86400),hours=Math.floor(remaining%86400/3600),minutes=Math.floor(remaining%3600/60),seconds=remaining%60;
        el.textContent=valid?(remaining?(days?days+'d ':'')+[hours,minutes,seconds].map(n=>String(n).padStart(2,'0')).join(':'):'Wird erneuert …'):'--:--:--';
        const fill=host().querySelector('[data-trading-progress]');if(fill)fill.style.width=(valid?Math.min(100,remaining/(mode==='vip'?604800:mode==='merchant'?86400:28800)*100):0)+'%';
        if(valid&&remaining===0){
            host().querySelectorAll(mode==='merchant'?'[data-action="market-trade"]':'[data-action="trading-buy"]').forEach(b=>b.disabled=true);
            const token=mode+':'+(mode==='merchant'?getMarket?.()?.rotation:list()?.rotation);
            if(token!==refreshRequested){refreshRequested=token;if(ctx.refresh)Promise.resolve(ctx.refresh()).catch(()=>{refreshRequested='';});}
        }
    }
    async function purchase(id,quantity){
        const o=allOffers().find(o=>String(o.id)===String(id));
        if(busy||!o||o.locked||!Number.isInteger(quantity)||quantity<1||quantity>num(o.remaining)||!affordable(o,quantity)||expired())return;
        const selectedMode=mode,rotation=list().rotation;busy=true;render();
        try{await action('kingdom/action',{action:'trading.buy',mode:selectedMode,offer_id:o.id,quantity,rotation},'Kauf abgeschlossen. Die Items sind im Inventar.');}
        catch(error){toast(error.message||'Der Kauf konnte nicht abgeschlossen werden.');}
        finally{busy=false;if(host()?.querySelector('.trading-shell'))render();}
    }
    function onClick(act,b){
        if(!act.startsWith('trading-'))return false;
        if(b.disabled||busy)return true;
        if(act==='trading-tab'&&['merchant','crystals','vip','caravan'].includes(b.dataset.id)){mode=b.dataset.id;render();host()?.querySelector(`[data-action="trading-tab"][data-id="${mode}"]`)?.focus({preventScroll:true});}
        else if(act==='trading-view'&&['mine','all'].includes(b.dataset.id)){vipView=b.dataset.id;render();host()?.querySelector(`[data-action="trading-view"][data-id="${vipView}"]`)?.focus({preventScroll:true});}
        else if(act==='trading-category'&&['all','resources','speedups','boosts','progress','treasures'].includes(b.dataset.id)){vipCategory=b.dataset.id;render();host()?.querySelector(`[data-action="trading-category"][data-id="${vipCategory}"]`)?.focus({preventScroll:true});}
        else if(act==='trading-upgrade')ctx.onUpgrade?.();
        else if(act==='trading-buy')void purchase(b.dataset.id,Number(b.dataset.quantity));
        return true;
    }
    return{render,onClick,updateTime,selectTab:value=>{if(['merchant','crystals','vip','caravan'].includes(value))mode=value;}};
};
