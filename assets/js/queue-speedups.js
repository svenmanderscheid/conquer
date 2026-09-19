/* One quantity picker for every running job. Receipts survive a lost response/reload. */
window.ConquerPlanSpeedups=function(stock,seconds){
    'use strict';
    const source=(stock||[]).map(item=>({...item,duration_seconds:Math.floor(Number(item.duration_seconds)),quantity:Math.floor(Number(item.quantity))})).filter(item=>item.duration_seconds>0&&item.quantity>0);
    seconds=Math.ceil(Number(seconds));if(seconds<=0||!source.length||source.reduce((sum,item)=>sum+item.duration_seconds*item.quantity,0)<seconds)return [];
    const gcd=(a,b)=>{while(b)[a,b]=[b,a%b];return a;},step=source.reduce((value,item)=>gcd(value,item.duration_seconds),source[0].duration_seconds);
    const target=Math.ceil(seconds/step),largest=Math.max(...source.map(item=>item.duration_seconds/step)),cap=target+largest-1,best=new Array(cap+1);best[0]={generic:0,count:0,previous:null,item:null,quantity:0};
    for(const item of source){
        const units=item.duration_seconds/step,max=Math.min(item.quantity,Math.ceil(cap/units));let left=max,chunk=1;
        while(left>0){const take=Math.min(chunk,left),weight=units*take,generic=item.subcategory==='generic'?item.duration_seconds*take:0;
            for(let total=cap-weight;total>=0;total--){const previous=best[total];if(!previous)continue;const at=total+weight,candidate={generic:previous.generic+generic,count:previous.count+take,previous,item,quantity:take},current=best[at];if(!current||candidate.count<current.count||(candidate.count===current.count&&candidate.generic<current.generic))best[at]=candidate;}
            left-=take;chunk*=2;
        }
    }
    let result=null;for(let total=target;total<=cap;total++)if(best[total]){result=best[total];break;}if(!result)return [];
    const grouped=new Map();for(let node=result;node?.item;node=node.previous)grouped.set(Number(node.item.item_code),(grouped.get(Number(node.item.item_code))||0)+node.quantity);
    return [...grouped].map(([item_code,quantity])=>{const item=source.find(entry=>Number(entry.item_code)===item_code);return {item_code,quantity,duration_seconds:item.duration_seconds,subcategory:item.subcategory};}).sort((a,b)=>b.duration_seconds-a.duration_seconds||(a.subcategory==='generic')-(b.subcategory==='generic')||a.item_code-b.item_code);
};
window.ConquerQueueSpeedups = function(ctx) {
    'use strict';
    const {esc,fmt,duration,date,now,getState,getKingdom,openDialog,toast}=ctx;
    const names={building:'Ausbau',research:'Forschung',training:'Ausbildung',healing:'Heilung'};
    const queues={building:'build_queue',research:'research_queue',training:'troop_queue'};
    let selected=null,itemCode=null,quantity=1,pending=false,request=null,scope=null,version=0,awaitingState=null,cooldownUntil=0;
    const key=()=>`conquer-queue-speedup:${getState().city.world_id}:${getState().city.id}`;
    const picker=()=>document.querySelector('#game-dialog[open] .queue-speedup-picker');
    const itemTime=seconds=>seconds%86400===0?`${fmt(seconds/86400)} ${seconds===86400?'Tag':'Tage'}`:seconds%3600===0?`${fmt(seconds/3600)} Std.`:seconds%60===0?`${fmt(seconds/60)} Min.`:duration(seconds);
    function restore(){
        if(scope===key())return;
        scope=key();request=null;selected=null;awaitingState=null;
        try{const saved=JSON.parse(sessionStorage.getItem(scope)||'null');if(typeof saved?.operation_key==='string'&&((saved.action==='inventory.use'&&queues[saved.queue_type])||saved.action==='hospital.speedup'))request=saved;}catch{}
        if(request)selected=request.action==='hospital.speedup'?{type:'healing',id:request.batch_id}:{type:request.queue_type,id:Number(request.queue_id)};
    }
    function save(value){request=value;try{value?sessionStorage.setItem(scope,JSON.stringify(value)):sessionStorage.removeItem(scope);}catch{}}
    function job(){
        if(!selected)return null;
        if(selected.type==='healing'){const active=getKingdom()?.hospital?.active;return active?.batch_id===selected.id?{...active,finishes_at:active.ends_at}:null;}
        return (getState()[queues[selected.type]]||[]).find(q=>Number(q.id)===selected.id);
    }
    const remaining=()=>job()?Math.max(0,Math.ceil((date(job().finishes_at)-now())/1000)):0;
    const items=()=>selected?(getKingdom()?.inventory||[]).filter(i=>i.category==='speedup'&&['generic',selected.type].includes(i.subcategory)&&Number(i.quantity)>0&&Number(i.duration_seconds)>0).sort((a,b)=>Number(a.duration_seconds)-Number(b.duration_seconds)||(a.subcategory==='generic')-(b.subcategory==='generic')||Number(a.item_code)-Number(b.item_code)):[];
    const currentItem=()=>items().find(i=>Number(i.item_code)===itemCode);
    const quickPlan=()=>window.ConquerPlanSpeedups(items(),remaining());
    const limit=i=>i?Math.min(10000,Number(i.quantity),Math.ceil(remaining()/Number(i.duration_seconds))):0;
    const fit=i=>i?Math.min(limit(i),Math.max(1,Math.floor(remaining()/Number(i.duration_seconds)))):0;
    function recommend(){
        const seconds=remaining();
        return items().sort((a,b)=>{const x=fit(a)*a.duration_seconds,y=fit(b)*b.duration_seconds;return (x>seconds)-(y>seconds)||(x>seconds?x-y:y-x)||(a.subcategory==='generic')-(b.subcategory==='generic')||fit(a)-fit(b);})[0];
    }
    function choose(code){const i=items().find(i=>Number(i.item_code)===Number(code))||recommend();itemCode=i?Number(i.item_code):null;quantity=fit(i);}
    function show(type,id,code){
        restore();if(!names[type])return;
        if(type==='training'&&ctx.canUseTrainingSpeedups&&!ctx.canUseTrainingSpeedups()){toast('Prüfe zuerst den noch unbestätigten Ausbildungsauftrag.');return;}
        if(!request){selected={type,id:type==='healing'?(getKingdom()?.hospital?.active?.batch_id||String(id)):Number(id)};choose(code);}
        version++;draw();
    }
    function title(q){
        if(selected.type==='building')return ctx.labels[q.building_code]||'Gebäude';
        if(selected.type==='research')return ctx.researchNames[q.research_code||q.code]||'Forschung';
        return `${fmt(q.count)} ${selected.type==='healing'?'Truppen in Behandlung':'Truppen in Ausbildung'}`;
    }
    function draw(preserve=false){
        const old=picker(),scroll=old?.querySelector('.queue-speedup-list')?.scrollTop||0;
        const focus=old?.contains(document.activeElement)?{action:document.activeElement.dataset.action,id:document.activeElement.dataset.id}:null;
        if(request){
            openDialog(`<h2>Beschleunigung prüfen</h2><section class="queue-speedup-recovery" data-speedup-version="${version}" data-operation-key="${esc(request.operation_key)}"><p>Die letzte Verwendung wurde noch nicht bestätigt. Prüfe denselben Vorgang, bevor du weitere Beschleuniger verwendest.</p><button type="button" class="button wide" data-action="queue-speedup-retry" ${pending?'disabled':''}>${pending?'Wird geprüft …':'Verwendung prüfen'}</button></section>`,{focusHeading:true});return;
        }
        const q=job();
        if(!q||remaining()<=0){const dialog=document.querySelector('#game-dialog');if(dialog?.open)dialog.close();return;}
        const available=items();if(!currentItem())choose();
        openDialog(`<h2>${names[selected.type]} beschleunigen</h2><section class="queue-speedup-picker" data-speedup-version="${version}" data-type="${selected.type}"><div class="queue-speedup-summary"><strong>${esc(title(q))}</strong><span>Restzeit <b data-queue-speedup-time></b></span></div><div class="queue-speedup-list" role="group" aria-label="Beschleuniger auswählen">${available.map(i=>`<button type="button" class="queue-speedup-option" data-action="queue-speedup-select" data-id="${Number(i.item_code)}" aria-pressed="${itemCode===Number(i.item_code)}"><img src="${ctx.base}/assets/art/items/backpack/speedup${i.subcategory==='generic'?'':'-'+selected.type}.svg" alt=""><span><strong>${itemTime(i.duration_seconds)}</strong><small>${i.subcategory==='generic'?'Allgemein':names[selected.type]}</small><small data-speedup-stock>${fmt(i.quantity)} vorhanden</small></span><span class="queue-speedup-check" aria-hidden="true">✓</span></button>`).join('')||'<p class="queue-speedup-empty">Du besitzt noch keine passenden Beschleuniger.</p>'}</div>${available.length?`<div class="queue-speedup-controls"><button type="button" class="button wide queue-speedup-quick" data-action="queue-speedup-quick">QuickUse</button><div class="queue-speedup-quantity"><label for="queue-speedup-quantity">Anzahl</label><button type="button" data-action="queue-speedup-step" data-step="-1" aria-label="Einen weniger">−</button><input id="queue-speedup-quantity" type="number" inputmode="numeric" min="1" step="1" value="${quantity}"><button type="button" data-action="queue-speedup-step" data-step="1" aria-label="Einen mehr">+</button></div><div class="queue-speedup-presets" role="group" aria-label="Anzahl schnell auswählen"><button type="button" data-action="queue-speedup-one">Nur 1</button><button type="button" data-action="queue-speedup-fit">Passend</button><button type="button" data-action="queue-speedup-finish">Fertigstellen</button></div><div class="queue-speedup-preview"><span data-speedup-selection></span><strong data-queue-speedup-after></strong><small data-speedup-waste></small></div><button type="button" class="button wide queue-speedup-use" data-action="queue-speedup-use"></button><p class="queue-speedup-feedback" role="status" aria-live="polite"></p></div>`:''}</section>`,{focusHeading:true});
        if(preserve){picker().querySelector('.queue-speedup-list').scrollTop=scroll;if(focus?.action)picker().querySelector(`[data-action="${focus.action}"]${focus.id?`[data-id="${focus.id}"]`:''}`)?.focus({preventScroll:true});}
        update();
        if(!preserve)picker()?.querySelector('[aria-pressed="true"]')?.scrollIntoView({block:'nearest'});
    }
    function update(){
        const view=picker();if(!view)return;
        if(awaitingState&&getState()!==awaitingState){awaitingState=null;choose(itemCode);draw(true);return;}
        if(!job()&&!pending&&!request&&!awaitingState){draw();return;}
        const seconds=remaining(),i=currentItem(),max=limit(i),busy=pending||!!awaitingState;
        const availableCodes=items().map(i=>String(i.item_code)).join(','),displayedCodes=[...view.querySelectorAll('[data-action="queue-speedup-select"]')].map(b=>b.dataset.id).join(',');
        if(!busy&&!request&&availableCodes!==displayedCodes){if(!i)choose();draw(true);return;}
        view.querySelector('[data-queue-speedup-time]').textContent=seconds?duration(seconds):'Abschluss wird bestätigt …';
        view.querySelectorAll('[data-action="queue-speedup-select"]').forEach(button=>{const item=items().find(i=>Number(i.item_code)===Number(button.dataset.id));button.disabled=busy||!seconds||!item;button.setAttribute('aria-pressed',String(itemCode===Number(button.dataset.id)));button.querySelector('[data-speedup-stock]').textContent=`${fmt(item?.quantity||0)} vorhanden`;});
        const input=view.querySelector('#queue-speedup-quantity');if(!input)return;
        if(document.activeElement!==input&&Number.isInteger(quantity)&&quantity>max)quantity=Math.max(1,max);
        input.max=max;input.disabled=busy||!seconds||!i;if(document.activeElement!==input)input.value=quantity;
        const valid=!!i&&Number.isInteger(quantity)&&quantity>=1&&quantity<=max&&seconds>0,used=valid?quantity*Number(i.duration_seconds):0,waste=Math.max(0,used-seconds);
        view.querySelector('[data-speedup-selection]').textContent=i?`${fmt(quantity||0)} × ${itemTime(i.duration_seconds)} · ${i.subcategory==='generic'?'Allgemein':names[selected.type]}`:'';
        view.querySelector('[data-queue-speedup-after]').textContent=valid?`Danach: ${used>=seconds?'Auftrag fertig':duration(seconds-used)}`:'Wähle eine vorhandene Anzahl.';
        view.querySelector('[data-speedup-waste]').textContent=valid?(waste?`${duration(waste)} überschüssige Zeit verfällt.`:'Ohne Zeitverlust.'):'';
        const use=view.querySelector('[data-action="queue-speedup-use"]');use.disabled=busy||!valid||Date.now()<cooldownUntil;use.dataset.id=itemCode||'';use.textContent=pending?'Wird verwendet …':awaitingState?'Bestand wird aktualisiert …':`${fmt(quantity||0)} Beschleuniger verwenden`;
        const quick=view.querySelector('[data-action="queue-speedup-quick"]'),enough=items().reduce((sum,item)=>sum+Number(item.duration_seconds)*Number(item.quantity),0)>=seconds;quick.disabled=busy||!seconds||!enough||Date.now()<cooldownUntil;quick.textContent=pending&&request?.quick_use?'QuickUse läuft …':'QuickUse';
        view.querySelectorAll('[data-action="queue-speedup-step"]').forEach(b=>b.disabled=busy||!i||!seconds||(Number(b.dataset.step)<0?quantity<=1:quantity>=max));
        view.querySelector('[data-action="queue-speedup-one"]').disabled=busy||!max;view.querySelector('[data-action="queue-speedup-fit"]').disabled=busy||!max;
        view.querySelector('[data-action="queue-speedup-finish"]').disabled=busy||!i||!seconds||Math.ceil(seconds/i.duration_seconds)>Math.min(10000,i.quantity);
    }
    function receipt(code,count,extra={}){
        const body={item_code:Number(code),quantity:Number(count),operation_key:crypto.randomUUID(),expected_world_id:Number(getState().city.world_id),...extra};
        return selected.type==='healing'?{...body,action:'hospital.speedup',batch_id:selected.id}:{...body,action:'inventory.use',queue_type:selected.type,queue_id:selected.id};
    }
    async function quickUse(){
        if(pending||awaitingState||Date.now()<cooldownUntil)return;const plan=quickPlan(),first=plan[0];
        if(!first){const feedback=picker()?.querySelector('.queue-speedup-feedback');if(feedback)feedback.textContent='Deine passenden Beschleuniger reichen noch nicht zum Fertigstellen.';return;}
        itemCode=first.item_code;quantity=Math.min(10000,first.quantity);save(receipt(itemCode,quantity,{quick_use:true,quick_used:0}));update();await use();
    }
    async function use(){
        if(pending||awaitingState||Date.now()<cooldownUntil)return;
        if(!request){
            const i=currentItem();if(!i||!job()||!Number.isInteger(quantity)||quantity<1||quantity>limit(i)){update();return;}
            save(receipt(itemCode,quantity));
        }
        const ownVersion=version,firstReceipt=request,oldScope=scope;
        const ownsDialog=()=>version===ownVersion&&document.querySelector(`#game-dialog[open] [data-speedup-version="${ownVersion}"]`)||document.querySelector('#game-dialog[open] .queue-speedup-recovery')?.dataset.operationKey===firstReceipt.operation_key;
        pending=true;update();document.querySelector('[data-action="queue-speedup-retry"]')?.setAttribute('disabled','');
        try{
            let result=null,total=Number(request.quick_used||0),isQuick=Boolean(request.quick_use);
            while(request){const current=request;result=await ctx.api(current.action==='hospital.speedup'?'hospital/speedup':'kingdom/action',current);if(scope!==oldScope)return;total+=Number(current.quantity||1);save(null);awaitingState=getState();await ctx.refresh(false);if(getState()!==awaitingState)awaitingState=null;
                if(!isQuick||!job()||remaining()<=0)break;const next=quickPlan()[0];if(!next)break;itemCode=next.item_code;quantity=Math.min(10000,next.quantity);save(receipt(itemCode,quantity,{quick_use:true,quick_used:total}));
            }
            cooldownUntil=Date.now()+600;setTimeout(update,620);
            if(ownsDialog()){ctx.render();choose(itemCode);pending=false;draw(true);const feedback=picker()?.querySelector('.queue-speedup-feedback');if(feedback)feedback.textContent=isQuick?`QuickUse: ${fmt(total)} Beschleuniger automatisch verwendet.`:`${fmt(firstReceipt.quantity||1)} verwendet. ${result?.message||'Die Restzeit wurde verkürzt.'}`;}
            document.querySelector('#city-frame')?.contentWindow?.postMessage({type:'conquer:refresh'},location.origin);
        }catch(e){
            if(scope!==oldScope)return;
            if(e.code){save(null);await ctx.refresh(false);}
            pending=false;if(ownsDialog()){draw(true);const feedback=picker()?.querySelector('.queue-speedup-feedback');if(feedback)feedback.textContent=e.message;else toast(e.message);}
        }finally{pending=false;update();document.querySelector('[data-action="queue-speedup-retry"]')?.removeAttribute('disabled');}
    }
    function onClick(action,b,event){
        if(action==='queue-speedups'){show(b.dataset.type,b.dataset.id);return true;}
        if(action==='training-speedups'){show('training',b.dataset.id);return true;}
        if(action==='hospital-speedups'){show('healing');return true;}
        if(action==='speedup-target'){show(b.dataset.type,b.dataset.queue,b.dataset.id);return true;}
        if(!action.startsWith('queue-speedup-'))return false;
        if(pending||awaitingState)return true;
        if(action==='queue-speedup-retry'){if(request)use();else draw();return true;}
        if(action==='queue-speedup-quick'){quickUse();return true;}
        if(action==='queue-speedup-use'){if(!(event?.detail>1))use();return true;}
        if(action==='queue-speedup-select'){choose(b.dataset.id);update();return true;}
        if(action==='queue-speedup-step')quantity=Math.min(limit(currentItem()),Math.max(1,(Number(quantity)||1)+Number(b.dataset.step)));
        if(action==='queue-speedup-one')quantity=1;if(action==='queue-speedup-fit')quantity=fit(currentItem());if(action==='queue-speedup-finish')quantity=limit(currentItem());update();return true;
    }
    document.addEventListener('input',e=>{if(e.target.id==='queue-speedup-quantity'&&!pending){quantity=Number(e.target.value);update();}});
    document.addEventListener('change',e=>{if(e.target.id==='queue-speedup-quantity'&&!pending){quantity=Math.min(limit(currentItem()),Math.max(1,Math.floor(Number(e.target.value)||1)));e.target.value=quantity;update();}});
    return {show,onClick,update,resume(){restore();if(request)draw();}};
};
