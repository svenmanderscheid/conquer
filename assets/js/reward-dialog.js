/* Catalog art and confirmed reward receipts for inventory and daily chests. */
window.ConquerRewards = (() => {
    'use strict';
    const resourceNames={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold',gems:'Edelsteine'};
    const rarities={normal:'Gewöhnlich',common:'Gewöhnlich',uncommon:'Ungewöhnlich',rare:'Selten',epic:'Episch',legendary:'Legendär',mythic:'Mythisch'};
    function asset(base,file){return /^[a-zA-Z0-9_/-]+\.(svg|png|webp)$/.test(file||'')&&!file.includes('..')?(window.ConquerItemArt?.url(base,file)??`${base}/assets/art/items/${file}?v=${encodeURIComponent(window.CONQUER_ITEM_ART_VERSION||'catalog3')}`):'';}
    function presentationFile(item,rawIcon){
        const painted=window.ConquerItemArt?.forItem(item);
        if(painted)return painted;
        if(item.category==='speedup'&&!item.icon_framed&&(!rawIcon||rawIcon==='speedup.svg')){
            const specialty=['building','training','research','healing'].includes(item.subcategory)?`-${item.subcategory}`:'';
            return `backpack/speedup${specialty}.svg`;
        }
        if(item.category==='resource_pack'&&!rawIcon&&['food','lumber','stone','gold','gems'].includes(item.resource)){
            const amount=Number(item.amount),scale=amount>=(item.resource==='gems'?1000:1000000)?'cart':amount>=(item.resource==='gems'?100:100000)?'crate':'bundle';
            return `backpack/${item.resource}-${scale}.svg`;
        }
        return rawIcon||(item.category==='chest'?`chest-${item.chest_type||'silver'}.svg`:'resource-box.svg');
    }
    function resolve(drop,kingdom={},base=''){
        const wholeRelic=drop.type==='relic',fragment=!wholeRelic&&(drop.type==='fragment'||Number(drop.treasure_code)>0),treasure=wholeRelic||fragment,resource=drop.type==='resource';
        const code=Number(treasure?drop.treasure_code:drop.item_code||drop.code);
        const catalog=treasure?kingdom.treasures?.items:kingdom.inventory_catalog||kingdom.inventory;
        const def=(catalog||[]).find(i=>Number(treasure?i.treasure_code:i.item_code||i.code)===code)||{};
        const item={...def,...drop},relic=treasure?window.ConquerRelicPresentation:null;
        const relicKind=treasure?(window.ConquerLocale?.t(wholeRelic?'reward.kind.relic':'reward.kind.fragment')??(wholeRelic?'Whole relic':'Relic fragments')):'';
        const name=relic?relic.name(item):drop.name_de||def.name_de||drop.name||def.name||drop.label||(treasure?relicKind:resource?resourceNames[drop.resource]:'Gegenstand');
        const file=treasure?(drop.icon||def.icon||'fragment.svg'):presentationFile(item,drop.icon||def.icon);
        const icon=relic?relic.image(base,item):resource&&['food','lumber','stone','gold'].includes(drop.resource)?`${window.ConquerItemArt?.resourceUrl(base,drop.resource) || `${base}/assets/art/ui-resources/${drop.resource}.png`}`:asset(base,resource&&drop.resource==='gems'?'gems.svg':file);
        const grade=relic?relic.grade(item):item.rarity||item.grade;
        const rarity=Object.hasOwn(rarities,grade)?grade:'normal';
        const speedupTier=window.ConquerItemArt?.speedupTier(item)||null;
        const labels=!treasure&&!resource?(window.ConquerItemArt?.labels(item)||[]):[];
        const converted=wholeRelic&&Number(drop.duplicate_relics)>0?Math.max(0,Number(drop.fragments_added)||0):0;
        const conversion=converted?(window.ConquerLocale?.t('reward.relic_duplicates',{count:converted.toLocaleString(window.ConquerLocale?.locale??'en')})??`Duplicates converted to ${converted.toLocaleString('en')} fragments.`):'';
        return {name,icon,quantity:Number(drop.quantity??drop.count??drop.amount)||0,kind:treasure?relicKind:resource?'Rohstoffe':rarities[rarity],type:wholeRelic?'relic':fragment?'fragment':resource?'resource':'item',conversion,rarity,code,speedupTier,labels,stamp:labels.join(' · ')};
    }
    function create(ctx){
        const {getState,getKingdom,openDialog,esc,fmt}=ctx,dialog=document.querySelector('#game-dialog');
        let pending=null,scope='',entry=null,closingHistory=false;
        const tracked=payload=>['inventory.use','chest.free'].includes(payload?.action)&&(!payload.queue_type||payload.use_all===true);
        function restore(){
            const key=`conquer-item-receipt:${getState().city.world_id}:${getState().city.id}`;
            if(scope===key)return;
            scope=key;pending=null;
            try{const saved=JSON.parse(sessionStorage.getItem(scope)||'null');if(tracked(saved)&&typeof saved.operation_key==='string'&&Number(saved.expected_world_id)===Number(getState().city.world_id))pending=saved;}catch{}
        }
        function save(value){pending=value;try{value?sessionStorage.setItem(scope,JSON.stringify(value)):sessionStorage.removeItem(scope);}catch{}}
        function begin(){if(entry||closingHistory)return;entry={token:history.state?.conquerRewards||crypto.randomUUID(),url:location.href};if(!history.state?.conquerRewards)history.pushState({...history.state,conquerRewards:entry.token},'',location.href);}
        function show(title,body){begin();document.querySelector('#toast')?.classList.remove('visible');openDialog(`<h2>${esc(title)}</h2>${body}`,{focusHeading:true,historyManaged:true});dialog.classList.add('reward-dialog');}
        function recovery(){
            show('Verwendung prüfen',`<section class="reward-recovery" data-operation-key="${esc(pending.operation_key)}"><p>Die letzte Verwendung wurde noch nicht bestätigt. Prüfe denselben Vorgang, um das Ergebnis sicher abzurufen.</p><button type="button" class="button wide" data-action="reward-retry">Verwendung prüfen</button></section>`);
        }
        function prepare(payload){
            restore();
            if(pending&&payload.operation_key!==pending.operation_key){recovery();return null;}
            if(!pending)save({...payload,operation_key:crypto.randomUUID(),expected_world_id:Number(getState().city.world_id)});
            return {...pending};
        }
        function success(response,payload){
            save(null);
            const result=response?.result||response||{},drops=Array.isArray(result.drops)?result.drops:[];
            if(!drops.length){dialog.close();return;}
            const rewards=drops.map(drop=>resolve(drop,getKingdom(),ctx.base)).filter(r=>r.quantity>0);
            const chest=payload.action==='chest.free'||(getKingdom()?.inventory_catalog||[]).some(i=>Number(i.item_code)===Number(payload.item_code)&&i.category==='chest');
            show(chest?'Schatztruhe geöffnet':'Deine Belohnung',`<section class="reward-result" aria-label="Erhaltene Belohnungen"><p class="reward-confirmed">✓ Deinem Reich gutgeschrieben</p><ul class="reward-list">${rewards.map(r=>`<li class="reward-item" data-reward-code="${r.code}" data-reward-quantity="${r.quantity}"><span class="reward-art grade-${r.rarity}${r.stamp?' item-value-art':''}" data-value-lines="${r.labels.length}"${r.speedupTier?` data-speedup-tier="${r.speedupTier}"`:''}>${r.icon?`<img src="${esc(r.icon)}" alt="">`:'<span aria-hidden="true">✦</span>'}${r.stamp?`<strong class="speedup-stamp">${r.labels.map(label=>`<span>${esc(label)}</span>`).join('')}</strong>`:''}</span><span class="reward-name"><strong>${esc(r.name)}</strong><small>${esc(r.kind)}</small>${r.conversion?`<small class="reward-conversion">${esc(r.conversion)}</small>`:""}</span><strong class="reward-quantity">× ${fmt(r.quantity)}</strong></li>`).join('')}</ul><footer><button type="button" class="button wide" data-action="close-dialog">Weiter</button></footer></section>`);
        }
        function failure(error){if(error.definite){save(null);if(dialog.querySelector('.reward-recovery'))dialog.close();}else recovery();}
        function resume(){restore();if(pending)recovery();}
        dialog.addEventListener('close',()=>{
            dialog.classList.remove('reward-dialog');const old=entry;entry=null;
            if(old&&history.state?.conquerRewards===old.token){if(location.href===old.url){closingHistory=true;history.back();}else{const next={...history.state};delete next.conquerRewards;history.replaceState(next,'',location.href);}}
        });
        window.addEventListener('popstate',()=>{closingHistory=false;if(entry&&history.state?.conquerRewards!==entry.token){entry=null;if(dialog.open&&dialog.classList.contains('reward-dialog'))dialog.close();}});
        function onClick(action){if(action!=='reward-retry')return false;restore();if(pending)ctx.action('kingdom/action',{...pending});return true;}
        return {tracked,prepare,success,failure,resume,onClick};
    }
    return {asset,resolve,create};
})();
