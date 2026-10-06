/* Acquisition previews only. Opening a destination never consumes an item or sends troops. */
(() => {
    'use strict';
    function create(ctx) {
        const {api,esc,openDialog,getState}=ctx;
        const t=(key,params={})=>ctx.t?ctx.t(key,params):window.ConquerLocale?.t(key,params)||key;
        const dialog=document.getElementById('game-dialog');
        const world=()=>Number(getState()?.city?.world_id||getState()?.world_id||1);
        const active=()=>dialog.open&&!!dialog.querySelector('.item-sources');
        const fmt=n=>Number(n).toLocaleString(window.ConquerLocale?.locale||'en',{maximumFractionDigits:3});
        const pct=n=>(Number(n)*100).toLocaleString(window.ConquerLocale?.locale||'en',{maximumSignificantDigits:3});
        let sequence=0,query=null,result=null,requestWorld=null;
        function note(value){
            const params={...value.params};
            if(value.key==='sources.next_at'&&params.time)params.time=new Date(params.time).toLocaleString(window.ConquerLocale?.locale||'en');
            return t(value.key,params);
        }
        function reward(entry){
            const chance=t(entry.per_draw?'sources.chance_draw':'sources.chance',{chance:pct(entry.chance)});
            const quantity=t(entry.reward_type==='relic'?'sources.relics':query.treasure_code?'sources.fragments':'sources.items',{count:fmt(entry.quantity)});
            if(entry.via_item_code)return `<p class="item-source-reward">${esc(t('sources.pack_reward',{count:fmt(entry.pack_quantity),chance:pct(entry.chance)}))}${entry.per_draw?`<small>${esc(chance)}</small>`:''}<small>${esc(t('sources.pack_selection',{quantity:fmt(entry.quantity),chance:pct(entry.selection_chance)}))}</small></p>`;
            return `<p class="item-source-reward"><strong>${esc(quantity)}</strong> · ${esc(chance)}${entry.random_relic?`<small>${esc(t('sources.random_included'))}</small>`:''}</p>`;
        }
        function render(data) {
            const panel=dialog.querySelector('.item-sources');if(!panel)return;
            const selectedName=query.treasure_code?(window.ConquerRelicPresentation?.name({...data.item,treasure_code:query.treasure_code})??data.item.name):data.item.name;
            panel.innerHTML=`<p class="item-sources-intro">${esc(t('sources.intro'))}</p><strong class="item-sources-selected">${esc(selectedName)}${query.treasure_code?` · ${esc(t('sources.relic_rewards_label'))}`:''}</strong><div class="item-sources-scroll" tabindex="0" aria-label="${esc(t('sources.list'))}">${data.sources.length?data.sources.map((source,index)=>`<article class="item-source-card"><header><span><small>${esc(t('sources.type.'+source.type))}</small><h3>${esc(source.name)}${source.level?` · ${esc(t('sources.level',{level:source.level}))}`:''}</h3></span><span class="item-source-status ${source.status==='available'?'is-available':''}">${esc(t(source.status==='available'?'sources.available':'sources.unavailable'))}</span></header>${source.rewards.map(reward).join('')}<p class="item-source-reason">${esc(note(source.reason))}</p><ul>${source.notes.map(value=>`<li>${esc(note(value))}</li>`).join('')}</ul>${source.destination?`<button type="button" class="button secondary item-source-open" data-action="item-sources-go" data-id="${index}">${esc(t(source.destination.target?'sources.show_map':'sources.show'))}</button>`:''}</article>`).join(''):`<p class="notice">${esc(t('sources.empty'))}</p>`}</div><p class="item-sources-footnote">${esc(t('sources.scope'))}</p>`;
        }
        async function open(input) {
            const item=Number(input.item_code),treasure=Number(input.treasure_code);
            if(!Number.isSafeInteger(item)&&!Number.isSafeInteger(treasure))return;
            query=Number.isSafeInteger(item)&&item>0?{item_code:item}:{treasure_code:treasure};
            const version=++sequence;requestWorld=world();result=null;
            openDialog(`<section class="item-sources"><h2>${esc(t('sources.title'))}</h2><p role="status">${esc(t('sources.loading'))}</p></section>`,{focusHeading:true});
            try{
                const data=await api('item-sources?'+new URLSearchParams({...query,expected_world_id:requestWorld}));
                if(version!==sequence||!active()||world()!==requestWorld||Number(data.world_id)!==requestWorld)return;
                result=data;render(data);
            }catch(error){
                if(version!==sequence||!active()||world()!==requestWorld)return;
                dialog.querySelector('.item-sources').innerHTML=`<p role="alert">${esc(error.message||t('sources.error'))}</p><button type="button" class="button" data-action="item-sources-retry">${esc(t('sources.retry'))}</button>`;
            }
        }
        async function go(index,button) {
            const source=result?.sources[index];if(!source?.destination||button.disabled)return;
            const version=sequence,requestedWorld=requestWorld;let handedOff=false;
            button.disabled=true;
            try{
                // Recheck rotation, world and target existence immediately before navigation.
                const fresh=await api('item-sources?'+new URLSearchParams({...query,expected_world_id:requestedWorld}));
                if(version!==sequence||!active()||world()!==requestedWorld||Number(fresh.world_id)!==requestedWorld)return;
                const latest=fresh.sources.find(row=>row.id===source.id);
                if(!latest?.destination){result=fresh;render(fresh);ctx.toast(t('sources.changed'));return;}
                handedOff=true;dialog.close();
                if(latest.destination.target)await ctx.onTarget?.(latest.destination.target,requestedWorld);
                else if(ctx.onOpenDestination)await ctx.onOpenDestination(latest.destination);
                else ctx.navigate(latest.destination.tab);
            }catch(error){if(world()===requestedWorld&&(handedOff||version===sequence&&active()))ctx.toast(error.message||t('sources.error'));}
            finally{if(button.isConnected)button.disabled=false;}
        }
        function onClick(action,button){
            if(action==='item-sources'){void open(button.dataset.treasureCode?{treasure_code:button.dataset.treasureCode}:{item_code:button.dataset.itemCode});return true;}
            if(action==='item-sources-retry'){if(query)void open(query);return true;}
            if(action==='item-sources-go'){void go(Number(button.dataset.id),button);return true;}
            return false;
        }
        function update(){if(active()&&requestWorld!==world()){++sequence;dialog.close();}}
        dialog.addEventListener('close',()=>{++sequence;result=null;});
        return {open,onClick,update};
    }
    window.ConquerItemSources={create};
})();
