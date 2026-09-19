window.ConquerVillage = function({base,esc,fmt,getState,getKingdom,openDialog,navigate,action,toast,marchPanel}) {
    let selected=null,collectionTab='castle',marchActionPending=false,bundleActionPending=false,bundleTheme=null;
    const bundleOperationKeys=new Map();
    const skins=window.ConquerCastleSkins.entries;
    let skinFilter='all',skinPage=0;
    const rarityName={common:'Klassisch',legendary:'Legendär',mythic:'Mythisch'};
    const marchMotionPreference=typeof matchMedia==='function'?matchMedia('(prefers-reduced-motion: reduce)'):{matches:false,addEventListener(){}};
    const marchMotionReduced=()=>marchMotionPreference.matches||document.body.classList.contains('reduced-motion');
    const setMarchPortraitSource=(image,moving)=>{
        const useMotion=moving&&!marchMotionReduced()&&!image.dataset.marchMotionFailed,source=useMotion?image.dataset.marchMotion:image.dataset.marchStill;
        if(!source||image.getAttribute('src')===source)return;
        image.dataset.marchSourceKind=useMotion?'motion':'still';image.style.animation=useMotion?'none':'';image.src=source;
    };
    const observedMarchPortraits=new Set();
    const marchPortraitObserver=typeof IntersectionObserver==='function'?new IntersectionObserver(entries=>entries.forEach(entry=>{
        if(!entry.target.isConnected){marchPortraitObserver.unobserve(entry.target);observedMarchPortraits.delete(entry.target);return;}
        setMarchPortraitSource(entry.target,entry.isIntersecting);
    }),{rootMargin:'24px'}):null;
    function syncMarchPortraits(){
        if(typeof document.querySelectorAll!=='function')return;
        for(const image of observedMarchPortraits)if(!image.isConnected){marchPortraitObserver?.unobserve(image);observedMarchPortraits.delete(image);}
        for(const image of document.querySelectorAll('.march-skin-portrait img[data-march-motion]')){
            if(!image.dataset.marchMediaBound){
                image.dataset.marchMediaBound='1';image.addEventListener('error',()=>{
                    if(image.dataset.marchSourceKind!=='motion')return;
                    image.dataset.marchMotionFailed='1';setMarchPortraitSource(image,false);
                });
                marchPortraitObserver?.observe(image);if(marchPortraitObserver)observedMarchPortraits.add(image);
            }
            const box=typeof image.getBoundingClientRect==='function'?image.getBoundingClientRect():null;
            const visible=!!box&&box.width>0&&box.height>0&&box.right>=-24&&box.bottom>=-24&&box.left<=innerWidth+24&&box.top<=innerHeight+24;
            setMarchPortraitSource(image,visible);
        }
    }
    marchMotionPreference.addEventListener('change',syncMarchPortraits);
    if(typeof MutationObserver==='function')new MutationObserver(syncMarchPortraits).observe(document.body,{attributes:true,attributeFilter:['class']});
    function skinPortrait(id) {
        const skin=window.ConquerCastleSkins.get(id);
        const still=window.ConquerCastleSkins.image(base,skin.id),motion=window.ConquerCastleSkins.motionImage(base,skin.id);
        const source=document.body.classList.contains('reduced-motion')?still:motion;
        return `<span class="castle-skin-portrait" data-skin="${skin.id}" data-rarity="${skin.rarity}" style="--skin-aura:${skin.effectColor}"><span class="castle-skin-effect" aria-hidden="true"></span><picture><source media="(prefers-reduced-motion: reduce)" srcset="${still}"><img src="${source}" data-castle-still="${still}" data-castle-motion="${motion}" alt="" draggable="false"></picture></span>`;
    }
    function marchPortrait(entry) {
        const catalog=window.ConquerMarchSkins,articulated=catalog.hasMotion(entry.id),flightLayout=catalog.hasFlightLayout?.(entry.id),still=catalog.image(base,entry.id),motion=catalog.motionImage(base,entry.id);
        if(typeof queueMicrotask==='function')queueMicrotask(syncMarchPortraits);
        return `<span class="march-skin-portrait ${flightLayout?'has-flight-animation':''}" data-rarity="${entry.rarity}" data-march-effect="${catalog.effect(entry.id).kind}" data-locomotion="${catalog.locomotion(entry.id)}" style="--march-aura:${entry.effect_color}"><span class="march-skin-effect" aria-hidden="true"></span><img src="${still}" data-march-still="${still}" ${articulated?`data-march-motion="${motion}"`:''} data-march-source-kind="still" alt="" draggable="false" loading="lazy"></span>`;
    }
    function marchState() {
        return window.ConquerMarchSkins.normalizeState(getKingdom()?.march_skins);
    }
    function nameFrameState() {
        const kingdom=getKingdom(),fallback=kingdom?.profile?.name_frame||kingdom?.profile?.city_skin||'default';
        return window.ConquerNameFrames?.normalizeState(kingdom?.name_frames,fallback)||{entries:[],equipped:'default'};
    }
    function bundleState() {
        const raw=getKingdom()?.theme_bundles||getKingdom()?.skin_bundles||null;
        return window.ConquerThemeBundles?.normalize(raw)||{entries:[],payment_configured:false,currency:'EUR',provider_message:'Paketdaten sind derzeit nicht verfügbar.',next_bundle_id:''};
    }
    function bundleGroups() {
        const state=bundleState(),groups=new Map();
        state.entries.forEach(entry=>{
            if(!groups.has(entry.theme_id))groups.set(entry.theme_id,{id:entry.theme_id,name:entry.theme_name,entries:[]});
            groups.get(entry.theme_id).entries.push(entry);
        });
        return {state,groups:[...groups.values()]};
    }
    function castleOwnership() {
        const entries=bundleState().entries.filter(entry=>entry.contents?.cosmetic?.type==='castle_skin');
        const known=entries.length>0,owned=new Set(['default']);
        entries.forEach(entry=>{if(entry.cosmetic_owned)owned.add(entry.contents.cosmetic.id||entry.theme_id);});
        return {known,owned};
    }
    function collectionTabs() {
        const owned=marchState().entries.filter(entry=>entry.owned).length;
        const bundles=bundleState().entries,bundleOwned=bundles.filter(entry=>entry.owned).length,castleOwned=castleOwnership();
        const frameOwned=nameFrameState().entries.filter(entry=>entry.owned).length;
        const frameTab=window.ConquerNameFrames?`<button type="button" data-action="skin-collection-tab" data-id="frame" aria-label="Namensrahmen ${frameOwned}/${skins.length}" aria-pressed="${collectionTab==='frame'}"><span aria-hidden="true">♛</span> Rahmen <small>${frameOwned}/${skins.length}</small></button>`:'';
        return `<nav class="skin-collection-tabs" aria-label="Skin-Sammlung"><button type="button" data-action="skin-collection-tab" data-id="castle" aria-label="Burg-Skins ${castleOwned.known?castleOwned.owned.size:skins.length}/${skins.length}" aria-pressed="${collectionTab==='castle'}"><span aria-hidden="true">♜</span> Burg <small>${castleOwned.known?castleOwned.owned.size:skins.length}/${skins.length}</small></button><button type="button" data-action="skin-collection-tab" data-id="march" aria-label="Marsch-Skins ${owned}/${skins.length}" aria-pressed="${collectionTab==='march'}"><span aria-hidden="true">⚑</span> Marsch <small>${owned}/${skins.length}</small></button>${frameTab}<button type="button" data-action="skin-collection-tab" data-id="bundles" aria-label="Pakete ${bundleOwned}/${bundles.length}" aria-pressed="${collectionTab==='bundles'}"><span aria-hidden="true">🎁</span> Pakete <small>${bundleOwned}/${bundles.length}</small></button></nav>`;
    }
    function open(detail={kind:'home'}) {
        const state=getState(),own=detail.kind==='home',city=own?state.city:state.players.find(p=>Number(p.id)===Number(detail.id));
        if(!city)return;
        selected={kind:own?'home':'players',id:Number(city.id),x:Number(city.coord_x),y:Number(city.coord_y)};
        const name=own?getKingdom()?.profile?.display_name||state.player.name:city.display_name||city.username;
        openDialog(`<h2>${own?'Dein Königreich':'Königreich'}</h2><div class="village-summary">${skinPortrait(own?getKingdom()?.profile?.city_skin:city.city_skin)}<div><h3>${esc(name)}</h3><p>Burg Stufe ${fmt(city.castle_level||1)}</p><small>X ${selected.x} / Y ${selected.y} · 4 × 4 Felder</small></div></div><div class="village-actions"><button class="button secondary" data-action="${own?'dialog-tab':'public-profile'}" data-id="${own?'profile':Number(city.id)}">Profil</button>${own?'<button class="button gold" data-action="city-skins">Skin-Sammlung</button>':`<button class="button danger" data-action="village-attack" data-id="${city.id}">Solo-Angriff</button><button class="button gold" data-action="village-rally" data-id="${city.id}">Rally starten</button>`}<button class="button secondary" data-action="share-coordinates" data-x="${selected.x}" data-y="${selected.y}">Koordinaten teilen</button></div>`);
    }
    function showSkins() {
        if(collectionTab==='march'){showMarchSkins();return;}
        if(collectionTab==='frame'){showNameFrames();return;}
        if(collectionTab==='bundles'){showThemeBundles();return;}
        const current=window.ConquerCastleSkins.get(getKingdom()?.profile?.city_skin).id,ownership=castleOwnership();
        const filtered=skins.filter(skin=>skinFilter==='all'||skin.rarity===skinFilter),pages=Math.max(1,Math.ceil(filtered.length/4));
        skinPage=Math.max(0,Math.min(skinPage,pages-1));
        openDialog(`<h2>Skin-Sammlung</h2><section class="skin-collection castle-skin-picker">${collectionTabs()}<div class="skin-picker-intro"><span>Deine Burg · Dorf & Weltkarte</span><small>${ownership.known?`${ownership.owned.size}/${skins.length} im Besitz`:`${skins.length} freigeschaltet`}</small></div><nav class="skin-category-tabs" aria-label="Skin-Kategorien">${[['all','Alle'],['legendary','Legendär'],['mythic','Mythisch']].map(([id,label])=>`<button type="button" data-action="city-skin-filter" data-id="${id}" aria-pressed="${skinFilter===id}">${label} <span>${id==='all'?skins.length:skins.filter(skin=>skin.rarity===id).length}</span></button>`).join('')}</nav><div class="skin-options">${filtered.slice(skinPage*4,skinPage*4+4).map(({id,name,description,rarity})=>{const owned=!ownership.known||ownership.owned.has(id),equipped=current===id;return `<button class="skin-option ${owned?'is-owned':'is-locked'}" data-action="city-skin-save" data-id="${id}" aria-pressed="${equipped}" data-rarity="${rarity}" aria-label="${esc(name)} · ${rarityName[rarity]} · ${equipped?'Angelegt':owned?'Anlegen':'In Paket erhältlich'}" ${owned?'':'disabled'}><span class="skin-rarity">${rarityName[rarity]}</span>${skinPortrait(id)}<strong>${esc(name)}</strong><span class="skin-description">${esc(description)}</span><span class="skin-equip-state">${equipped?'✓ Angelegt':owned?'Anlegen':'🔒 In Paket erhältlich'}</span></button>`;}).join('')}</div><div class="skin-pagination"><button type="button" data-action="city-skin-page" data-page="${skinPage-1}" aria-label="Vorherige Skin-Seite" ${skinPage===0?'disabled':''}>‹</button><span aria-live="polite">Seite ${skinPage+1} / ${pages}</span><button type="button" data-action="city-skin-page" data-page="${skinPage+1}" aria-label="Nächste Skin-Seite" ${skinPage===pages-1?'disabled':''}>›</button></div><button class="button secondary skin-back" data-action="village-back">Zurück zum Dorfmenü</button></section>`);
    }
    function showNameFrames() {
        if(!window.ConquerNameFrames)return;
        const state=nameFrameState(),profile=getKingdom()?.profile||{},displayName=profile.display_name||getState()?.player?.name||'Dein Name';
        const counts={all:state.entries.length,owned:state.entries.filter(entry=>entry.owned).length};
        const filtered=state.entries.filter(entry=>skinFilter==='all'||skinFilter==='owned'&&entry.owned),pages=Math.max(1,Math.ceil(filtered.length/4));
        skinPage=Math.max(0,Math.min(skinPage,pages-1));
        openDialog(`<h2>Skin-Sammlung</h2><section class="skin-collection name-frame-picker">${collectionTabs()}<div class="name-frame-intro"><span>Dein Namensrahmen · HUD & Weltkarte</span><small>${counts.owned}/${counts.all} im Besitz</small></div><nav class="skin-category-tabs" aria-label="Namensrahmen filtern">${[['all','Alle'],['owned','Im Besitz']].map(([id,label])=>`<button type="button" data-action="name-frame-filter" data-id="${id}" aria-pressed="${skinFilter===id}">${label} <span>${counts[id]}</span></button>`).join('')}</nav><div class="name-frame-grid">${filtered.slice(skinPage*4,skinPage*4+4).map(entry=>`<article class="name-frame-card" data-rarity="${entry.rarity}" data-frame-id="${entry.id}" aria-label="${esc(entry.name)} · passt zu ${esc(window.ConquerCastleSkins.get(entry.id).name)}">${window.ConquerNameFrames.markup(displayName,entry.id)}<strong>${esc(entry.name)}</strong><small>Passt zu: ${esc(window.ConquerCastleSkins.get(entry.id).name)}</small><button type="button" class="button small ${entry.equipped?'':'secondary'}" data-action="name-frame-equip" data-id="${entry.id}" ${!entry.owned||entry.equipped?'disabled':''}>${entry.equipped?'✓ Angelegt':entry.owned?'Anlegen':'Im Paket erhältlich'}</button></article>`).join('')}</div><div class="skin-pagination"><button type="button" data-action="name-frame-page" data-page="${skinPage-1}" aria-label="Vorherige Rahmen-Seite" ${skinPage===0?'disabled':''}>‹</button><span aria-live="polite">Seite ${skinPage+1} / ${pages}</span><button type="button" data-action="name-frame-page" data-page="${skinPage+1}" aria-label="Nächste Rahmen-Seite" ${skinPage===pages-1?'disabled':''}>›</button></div><p class="march-skin-footnote">Der Rahmen ändert nur die Darstellung deines Namens.</p><button class="button secondary skin-back" data-action="village-back">Zurück zum Dorfmenü</button></section>`);
    }
    function showMarchSkins() {
        const state=marchState(),gems=Number(getKingdom()?.profile?.gems||0);
        const counts={all:state.entries.length,owned:state.entries.filter(entry=>entry.owned).length,available:state.entries.filter(entry=>!entry.owned&&(entry.can_claim||entry.price_gems>0)).length};
        const filtered=state.entries.filter(entry=>skinFilter==='all'||skinFilter==='owned'&&entry.owned||skinFilter==='available'&&!entry.owned&&(entry.can_claim||entry.price_gems>0));
        const pages=Math.max(1,Math.ceil(filtered.length/4));skinPage=Math.max(0,Math.min(skinPage,pages-1));
        const status=entry=>entry.equipped?'✓ Angelegt':entry.owned?'Anlegen':entry.can_claim?'Kostenlos abholen':entry.price_gems>0?`◆ ${fmt(entry.price_gems)}`:`Ab Burg ${entry.unlock_castle_level}`;
        openDialog(`<h2>Skin-Sammlung</h2><section class="skin-collection march-skin-picker">${collectionTabs()}<div class="march-skin-benefit"><span class="march-speed-seal">+5%</span><span><strong>Marschgeschwindigkeit</strong><small>Nur der angelegte Skin wirkt. Der Bonus stapelt sich nicht und gilt ab dem nächsten Marsch.</small></span><b title="Deine Edelsteine">◆ ${fmt(gems)}</b></div><nav class="skin-category-tabs" aria-label="Marsch-Skin-Filter">${[['all','Alle'],['owned','Im Besitz'],['available','Verfügbar']].map(([id,label])=>`<button type="button" data-action="march-skin-filter" data-id="${id}" aria-pressed="${skinFilter===id}">${label} <span>${counts[id]}</span></button>`).join('')}</nav><div class="skin-options march-skin-options">${filtered.length?filtered.slice(skinPage*4,skinPage*4+4).map(entry=>{const castle=window.ConquerCastleSkins.get(entry.castle_skin);return `<button class="skin-option march-skin-option ${entry.owned?'is-owned':'is-unowned'}" data-action="march-skin-detail" data-id="${entry.id}" aria-pressed="${entry.equipped}" data-rarity="${entry.rarity}" aria-label="${esc(entry.name)} · ${rarityName[entry.rarity]} · ${status(entry)}"><span class="skin-rarity">${rarityName[entry.rarity]}</span>${marchPortrait(entry)}<strong>${esc(entry.name)}</strong><span class="skin-match">Passt zu: ${esc(castle.name)}</span><span class="skin-equip-state ${!entry.owned&&entry.price_gems>gems?'is-insufficient':''}">${status(entry)}</span></button>`;}).join(''):'<div class="skin-empty"><strong>Noch keine Skins in dieser Auswahl</strong><span>Unter „Alle“ findest du die vollständige Sammlung.</span></div>'}</div><div class="skin-pagination"><button type="button" data-action="march-skin-page" data-page="${skinPage-1}" aria-label="Vorherige Marsch-Skin-Seite" ${skinPage===0?'disabled':''}>‹</button><span aria-live="polite">Seite ${skinPage+1} / ${pages}</span><button type="button" data-action="march-skin-page" data-page="${skinPage+1}" aria-label="Nächste Marsch-Skin-Seite" ${skinPage===pages-1?'disabled':''}>›</button></div><p class="march-skin-footnote">Burg- und Marsch-Skin lassen sich unabhängig voneinander anlegen.</p><button class="button secondary skin-back" data-action="village-back">Zurück zum Dorfmenü</button></section>`);
    }
    function showMarchSkinDetail(id) {
        const entry=marchState().entries.find(item=>item.id===id);if(!entry)return;
        const gems=Number(getKingdom()?.profile?.gems||0),castleLevel=Number(getState()?.city?.castle_level||1),castle=window.ConquerCastleSkins.get(entry.castle_skin),enough=gems>=entry.price_gems;
        const actionButton=entry.equipped?'<button class="button wide" disabled>✓ Angelegt</button>':entry.owned?`<button class="button wide" data-action="march-skin-equip" data-id="${entry.id}">Marsch-Skin anlegen</button>`:entry.can_claim?`<button class="button wide" data-action="march-skin-claim" data-id="${entry.id}">Kostenlos abholen</button>`:entry.price_gems>0?`<button class="button march-skin-buy wide" data-action="march-skin-buy" data-id="${entry.id}" ${!enough?'disabled':''}>Sofort kaufen · ◆ ${fmt(entry.price_gems)}</button>`:`<button class="button wide" disabled>Ab Burg ${entry.unlock_castle_level}</button>`;
        openDialog(`<h2>Marsch-Skin</h2><section class="march-skin-detail" data-rarity="${entry.rarity}">${marchPortrait(entry)}<div class="march-skin-detail-copy"><span class="skin-rarity">${rarityName[entry.rarity]}</span><h3>${esc(entry.name)}</h3><p class="march-skin-description">${esc(entry.description)}</p><span class="skin-match">Passender Burg-Skin: ${esc(castle.name)}</span><div class="march-detail-bonus"><strong>+${fmt(entry.bonus_pct)}% Marschgeschwindigkeit</strong><small>Nur angelegt · nicht stapelbar · gilt für neu gestartete Märsche</small></div>${!entry.owned&&!entry.can_claim&&entry.price_gems===0&&castleLevel<entry.unlock_castle_level?`<p class="notice">Wird mit Burg Stufe ${entry.unlock_castle_level} freigeschaltet. Aktuell: Stufe ${castleLevel}.</p>`:''}${!entry.owned&&entry.price_gems>0&&!enough?`<p class="notice error">Dir fehlen ◆ ${fmt(entry.price_gems-gems)}.</p>`:''}<div class="march-skin-detail-actions">${actionButton}<button class="button secondary wide" data-action="march-skins" data-id="${entry.id}">Zur Sammlung</button></div></div></section>`);
    }
    function bundleContentHtml(entry) {
        const resourceNames={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
        const rows=Object.entries(entry.contents.resources||{}).filter(([,amount])=>Number(amount)>0).map(([key,amount])=>`<li><img src="${base}/assets/art/ui-resources/${key}.png" alt=""><span><small>${resourceNames[key]||esc(key)}</small><strong>${fmt(amount)}</strong></span></li>`);
        if(Number(entry.contents.gems)>0)rows.push(`<li><img src="${base}/assets/art/items/gems.svg" alt=""><span><small>Edelsteine</small><strong>${fmt(entry.contents.gems)}</strong></span></li>`);
        const cosmetic=entry.contents.cosmetic||{},typeName={name_frame:'Namensrahmen',frame:'Namensrahmen',march_skin:'Marsch-Skin',castle_skin:'Burg-Skin'}[cosmetic.type]||'Kosmetik';
        if(cosmetic.name)rows.push(`<li><span class="theme-bundle-cosmetic-icon" aria-hidden="true">${cosmetic.type==='castle_skin'?'♜':cosmetic.type==='march_skin'?'⚑':'✦'}</span><span><small>${typeName}${entry.cosmetic_owned?' · bereits im Besitz':''}</small><strong>${esc(cosmetic.name)}</strong></span></li>`);
        return `<ul class="theme-bundle-contents" aria-label="Paketinhalt">${rows.join('')}</ul>`;
    }
    function bundleStatus(entry,isNext,state) {
        if(entry.owned)return {className:'is-owned',label:'✓ Gekauft',button:'<button class="button wide" disabled>Bereits gekauft</button>',note:'Diese Stufe gehört dir.'};
        if(entry.status==='pending')return {className:'is-next',label:'Zahlung läuft',button:'<button class="button wide" disabled>Zahlung wird geprüft</button>',note:'Der Server wartet noch auf die Bestätigung des Zahlungsanbieters.'};
        if(!entry.unlocked)return {className:'is-locked',label:'Gesperrt',button:'<button class="button wide" disabled>Gesperrt</button>',note:entry.lock_reason||`Kaufe zuerst Paket ${Math.max(1,entry.step-1)}.`};
        if(!state.payment_configured)return {className:'is-next',label:'Nächster Schritt',button:'<button class="button wide" disabled>Kauf derzeit nicht möglich</button>',note:'Der Zahlungsanbieter ist noch nicht eingerichtet.'};
        return {className:isNext?'is-next':'',label:isNext?'Nächster Schritt':'Verfügbar',button:`<button class="button theme-bundle-buy wide" data-action="theme-bundle-review" data-id="${esc(entry.id)}">Für ${window.ConquerThemeBundles.money(entry.price_cents,entry.currency||state.currency)} ansehen</button>`,note:isNext?'Diese Stufe ist jetzt verfügbar.':'Dieses Paket ist laut Server verfügbar.'};
    }
    function showThemeBundles() {
        const {state,groups}=bundleGroups();
        if(!groups.length){openDialog(`<h2>Themenpakete</h2><section class="skin-collection theme-bundle-shop">${collectionTabs()}<div class="theme-bundle-empty"><strong>Paketkatalog nicht verfügbar</strong><span>${esc(state.provider_message||'Der Server liefert derzeit keine Themenpakete.')}</span></div><button class="button secondary skin-back" data-action="village-back">Zurück zum Dorfmenü</button></section>`);return;}
        if(!bundleTheme||!groups.some(group=>group.id===bundleTheme)){
            const equipped=getKingdom()?.profile?.city_skin||marchState().equipped;
            bundleTheme=groups.find(group=>group.id===equipped)?.id||groups[0].id;
        }
        const group=groups.find(item=>item.id===bundleTheme)||groups[0],currency=group.entries.find(entry=>entry.currency)?.currency||state.currency;
        const total=group.entries.reduce((sum,entry)=>sum+entry.price_cents,0),explicitNext=state.next_bundle_id&&group.entries.find(entry=>entry.id===state.next_bundle_id);
        const next=explicitNext||group.entries.find(entry=>entry.next&&!entry.owned)||group.entries.find(entry=>entry.unlocked&&!entry.owned)||null;
        const cards=group.entries.map(entry=>{const status=bundleStatus(entry,next?.id===entry.id,state);return `<article class="theme-bundle-card ${status.className}" data-bundle-id="${esc(entry.id)}" data-step="${entry.step}"><div class="theme-bundle-card-head"><span class="theme-bundle-step" aria-hidden="true">${entry.owned?'✓':entry.step}</span><span><small>Paket ${entry.step}</small><strong>${esc(entry.title)}</strong></span><b class="theme-bundle-price">${window.ConquerThemeBundles.money(entry.price_cents,entry.currency||currency)}</b></div><span class="theme-bundle-status">${status.label}</span>${bundleContentHtml(entry)}<div class="theme-bundle-card-actions">${status.button}<p class="theme-bundle-lock">${esc(status.note)}</p></div></article>`;}).join('');
        const march=marchState().entries.find(entry=>entry.id===group.id),f2p=march&&!march.owned&&march.price_gems>0?`Der Marsch-Skin bleibt auch direkt für ◆ ${fmt(march.price_gems)} erspielte Edelsteine erhältlich.`:'Burg-, Marsch-Skin und Rahmen lassen sich nach dem Kauf getrennt verwenden.';
        const provider=state.payment_configured?'<p class="theme-bundle-provider"><span aria-hidden="true">🔒</span><span>Preis und Freischaltung werden beim Checkout noch einmal serverseitig geprüft.</span></p>':`<p class="theme-bundle-provider is-unavailable"><span aria-hidden="true">!</span><span>${esc(state.provider_message||'Der Zahlungsanbieter ist noch nicht eingerichtet. Käufe sind deshalb derzeit deaktiviert.')}</span></p>`;
        openDialog(`<h2>Themenpakete</h2><section class="skin-collection theme-bundle-shop">${collectionTabs()}<div class="theme-bundle-toolbar"><nav class="theme-bundle-series" aria-label="Themenreihe">${groups.map(item=>`<button type="button" data-action="theme-bundle-theme" data-id="${esc(item.id)}" aria-pressed="${item.id===group.id}">${esc(item.name)}</button>`).join('')}</nav><span class="theme-bundle-total"><small>Gesamtpreis der Reihe</small><strong>${window.ConquerThemeBundles.money(total,currency)}</strong></span></div>${provider}<div class="theme-bundle-scroll"><div class="theme-bundle-steps">${cards}</div></div><p class="theme-bundle-f2p">${esc(f2p)}</p><button class="button secondary skin-back" data-action="village-back">Zurück zum Dorfmenü</button></section>`);
    }
    function showThemeBundleReview(id) {
        const state=bundleState(),entry=state.entries.find(item=>item.id===id);if(!entry||entry.owned||!entry.unlocked)return;
        const price=window.ConquerThemeBundles.money(entry.price_cents,entry.currency||state.currency);
        const enabled=state.payment_configured;
        openDialog(`<h2>Paketkauf prüfen</h2><section class="theme-bundle-review"><div class="theme-bundle-review-visual"><span aria-hidden="true">🎁</span><strong>${esc(entry.theme_name)}</strong><small>Paket ${entry.step} von 3</small></div><div class="theme-bundle-review-copy"><span class="theme-bundle-status">Nächster Schritt</span><h3>${esc(entry.title)}</h3>${bundleContentHtml(entry)}<div class="march-purchase-summary"><span>Preis</span><strong>${price}</strong><span>Reihenfolge</span><strong>Paket ${entry.step}</strong></div><p>Nach erfolgreicher Zahlung schreibt ausschließlich der Server die Inhalte und den Besitz gut.</p>${!enabled?`<p class="notice error">${esc(state.provider_message||'Der Zahlungsanbieter ist noch nicht eingerichtet.')}</p>`:''}<div class="theme-bundle-review-actions"><button class="button theme-bundle-buy wide" data-action="theme-bundle-checkout" data-id="${esc(entry.id)}" ${enabled?'':'disabled'}>Weiter zur Zahlung · ${price}</button><button class="button secondary wide" data-action="theme-bundles">Zurück zu den Paketen</button></div></div></section>`);
    }
    function bundleOperationKey(id) {
        if(bundleOperationKeys.has(id))return bundleOperationKeys.get(id);
        const storageKey=`conquer.theme-bundle.${id}`,valid=value=>/^[A-Za-z0-9_-]{16,64}$/.test(value||'');
        let value='';try{value=sessionStorage.getItem(storageKey)||'';}catch{}
        if(!valid(value))value=globalThis.crypto?.randomUUID?.()||`bundle_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,12)}`;
        bundleOperationKeys.set(id,value);try{sessionStorage.setItem(storageKey,value);}catch{}
        return value;
    }
    async function runBundleCheckout(button) {
        if(bundleActionPending)return;bundleActionPending=true;button.disabled=true;button.setAttribute('aria-busy','true');
        try{
            const result=await action('kingdom/action',{action:'theme_bundle.checkout',bundle_id:button.dataset.id,operation_key:bundleOperationKey(button.dataset.id)},'Checkout vorbereitet.');
            if(!result)return;
            const checkout=result.result?.checkout||result.checkout||result;
            const target=checkout.checkout_url||checkout.redirect_url;
            if(target){const url=new URL(target,location.href);if(['http:','https:'].includes(url.protocol))location.assign(url.href);return;}
            showThemeBundles();
        }finally{bundleActionPending=false;if(button.isConnected){button.disabled=false;button.removeAttribute('aria-busy');}}
    }
    async function runMarchAction(button,actionName,message) {
        if(marchActionPending)return;marchActionPending=true;button.disabled=true;button.setAttribute('aria-busy','true');
        try{
            const result=await action('kingdom/action',{action:actionName,march_skin:button.dataset.id},message);
            if(result){if(actionName==='march_skin.equip')showMarchSkins();else showMarchSkinDetail(button.dataset.id);}
        }finally{marchActionPending=false;if(button.isConnected){button.disabled=false;button.removeAttribute('aria-busy');}}
    }
    async function runNameFrameAction(button) {
        if(marchActionPending||!window.ConquerNameFrames?.ids.includes(button.dataset.id))return;
        marchActionPending=true;button.disabled=true;button.setAttribute('aria-busy','true');
        try{const result=await action('kingdom/action',{action:'name_frame.equip',name_frame:button.dataset.id},'Namensrahmen angelegt.');if(result)showNameFrames();}
        finally{marchActionPending=false;if(button.isConnected){button.disabled=false;button.removeAttribute('aria-busy');}}
    }
    async function share(x,y) {
        x=Number(x);y=Number(y);if(!Number.isInteger(x)||!Number.isInteger(y)||x<0||x>255||y<0||y>255)return;
        const text=`Conquer · Welt 1 · X ${x} / Y ${y}`;
        if(navigator.share){try{await navigator.share({title:'Conquer – Koordinaten',text});return;}catch(e){if(e.name==='AbortError')return;}}
        if(navigator.clipboard&&window.isSecureContext){try{await navigator.clipboard.writeText(text);toast('Koordinaten kopiert.');return;}catch{}}
        openDialog(`<h2>Koordinaten teilen</h2><p class="muted">Kopiere diese Koordinaten und teile sie mit deinen Verbündeten.</p><label for="share-coordinates-value">Welt und Position</label><input id="share-coordinates-value" type="text" readonly value="${esc(text)}"><button class="button gold wide" data-action="select-coordinates">Text auswählen</button>`);
        document.querySelector('#share-coordinates-value').select();
    }
    function onClick(act,b) {
        if(act==='village-menu'){open({kind:b.dataset.kind||'home',id:b.dataset.id});return true;}
        if(act==='village-back'){open(selected||{kind:'home'});return true;}
        if(act==='city-skins'){collectionTab='castle';skinFilter='all';skinPage=Math.floor(Math.max(0,skins.findIndex(s=>s.id===getKingdom()?.profile?.city_skin))/4);showSkins();return true;}
        if(act==='march-skins'){collectionTab='march';skinFilter='all';skinPage=Math.floor(Math.max(0,marchState().entries.findIndex(entry=>entry.equipped))/4);showMarchSkins();return true;}
        if(act==='theme-bundles'){collectionTab='bundles';showThemeBundles();return true;}
        if(act==='skin-collection-tab'){collectionTab=['march','frame','bundles'].includes(b.dataset.id)?b.dataset.id:'castle';skinFilter='all';skinPage=0;showSkins();return true;}
        if(act==='theme-bundle-theme'){bundleTheme=b.dataset.id;showThemeBundles();return true;}
        if(act==='theme-bundle-review'){showThemeBundleReview(b.dataset.id);return true;}
        if(act==='theme-bundle-checkout'){runBundleCheckout(b);return true;}
        if(act==='city-skin-filter'){skinFilter=['all','legendary','mythic'].includes(b.dataset.id)?b.dataset.id:'all';skinPage=0;showSkins();return true;}
        if(act==='city-skin-page'){skinPage=Number.isInteger(Number(b.dataset.page))?Number(b.dataset.page):0;showSkins();return true;}
        if(act==='city-skin-save'){const ownership=castleOwnership();if(window.ConquerCastleSkins.ids.includes(b.dataset.id)&&(!ownership.known||ownership.owned.has(b.dataset.id)))action('kingdom/action',{action:'skin.save',city_skin:b.dataset.id},'Dorf-Skin angelegt.');return true;}
        if(act==='march-skin-filter'){skinFilter=['all','owned','available'].includes(b.dataset.id)?b.dataset.id:'all';skinPage=0;showMarchSkins();return true;}
        if(act==='march-skin-page'){skinPage=Number.isInteger(Number(b.dataset.page))?Number(b.dataset.page):0;showMarchSkins();return true;}
        if(act==='march-skin-detail'){showMarchSkinDetail(b.dataset.id);return true;}
        if(act==='march-skin-claim'){runMarchAction(b,'march_skin.claim','Marsch-Skin freigeschaltet.');return true;}
        if(act==='march-skin-buy'){runMarchAction(b,'march_skin.buy','Marsch-Skin gekauft.');return true;}
        if(act==='march-skin-equip'){runMarchAction(b,'march_skin.equip','Marsch-Skin angelegt. Der Bonus gilt ab deinem nächsten Marsch.');return true;}
        if(act==='name-frame-filter'){skinFilter=['all','owned'].includes(b.dataset.id)?b.dataset.id:'all';skinPage=0;showNameFrames();return true;}
        if(act==='name-frame-page'){skinPage=Number.isInteger(Number(b.dataset.page))?Number(b.dataset.page):0;showNameFrames();return true;}
        if(act==='name-frame-equip'){runNameFrameAction(b);return true;}
        if(act==='share-coordinates'){share(b.dataset.x,b.dataset.y);return true;}
        if(act==='select-coordinates'){document.querySelector('#share-coordinates-value')?.select();return true;}
        if(act==='village-attack'||act==='village-rally'){marchPanel.open(b.dataset.id,act==='village-rally'?'rally':'players');return true;}
        if(act==='village-scout'){action('march/dispatch-scout',{target_x:Number(b.dataset.x),target_y:Number(b.dataset.y)},'Deine Späher sind unterwegs.');return true;}
        if(act==='village-debuff'){toast('Für dieses Ziel ist derzeit kein Debuff verfügbar.');return true;}
        return false;
    }
    return {open,onClick};
};
