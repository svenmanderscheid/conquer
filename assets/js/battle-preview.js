(() => {
    'use strict';
    window.ConquerBattlePreview = function({api,esc,fmt,getState}) {
        let dialog=null;
        const text=(key,parameters={})=>window.ConquerLocale.t('battle.preview.'+key,parameters);
        const html=(key,parameters={})=>esc(text(key,parameters));
        function open({kind,target,troops,onResult}) {
            dialog?.close();
            const monster=kind.startsWith('monster');
            const element=document.createElement('dialog');
            dialog=element;
            const historyEntry={token:crypto.randomUUID(),url:location.href};
            history.pushState({...history.state,conquerBattlePreview:historyEntry.token},'',location.href);
            // This nested dialog owns one history entry. Returning from it must not
            // also dismiss the mobile march window, including after a viewport change.
            const onBack=event=>{
                if(history.state?.conquerBattlePreview===historyEntry.token)return;
                if(location.href===historyEntry.url)event.stopImmediatePropagation();
                window.removeEventListener('popstate',onBack,true);element.close();
            };
            window.addEventListener('popstate',onBack,true);
            element.className='battle-preview-dialog';
            element.setAttribute('aria-labelledby','battle-preview-title');
            // Every authored string is translated explicitly. Do not let the
            // generic text matcher reinterpret labels or formatted table values.
            element.setAttribute('data-i18n-ignore','');
            const definitions=getState().troop_defs;
            const options=type=>definitions.filter(t=>Number(t.type)===type).sort((a,b)=>a.tier-b.tier).map(t=>`<option value="${t.code}">T${t.tier}</option>`).join('');
            element.innerHTML=`<header><h2 id="battle-preview-title">${html('title')}</h2><button type="button" data-preview-close aria-label="${html('close')}">×</button></header>
                <form class="battle-preview-body"><p>${html(monster?'intro_monster':'intro_pvp')}</p>
                ${monster?'':`<fieldset><legend>${html('enemy_troops')}</legend>${['infantry','ranged','cavalry'].map((type,i)=>`<div class="battle-preview-unit"><label for="preview-count-${i}">${html(type)}</label><select data-preview-type="${i}" aria-label="${html('troop_tier',{type:text(type)})}">${options(i+1)}</select><input id="preview-count-${i}" type="number" inputmode="numeric" min="0" max="500000" step="1" value="0" required></div>`).join('')}<label class="battle-preview-bonus">${html('enemy_bonus')}<input name="defender_bonus" type="number" inputmode="decimal" min="0" max="1000" step="0.1" value="0" required></label><label class="battle-preview-bonus">${html('wall_bonus')}<input name="wall_bonus" type="number" inputmode="decimal" min="0" max="1000" step="0.1" value="0" required></label></fieldset>`}
                <p class="battle-preview-selection">${html(kind.includes('rally')?'selection_rally':'selection_march',{count:fmt(Object.values(troops).reduce((s,n)=>s+n,0))})}</p>
                <button class="button" type="submit">${html(monster?'recalculate':'calculate_example')}</button>
                <div class="battle-preview-result" role="status" aria-live="polite"></div>
                <p class="battle-preview-footnote">${html('no_action')}</p></form>`;
            document.body.append(element);
            const parent=document.querySelector('#game-dialog');
            const close=()=>element.close();
            parent?.addEventListener('close',close);
            element.addEventListener('close',()=>{
                parent?.removeEventListener('close',close);element.remove();if(dialog===element)dialog=null;
                if(history.state?.conquerBattlePreview===historyEntry.token){
                    if(location.href===historyEntry.url)history.back();
                    else {window.removeEventListener('popstate',onBack,true);const state={...history.state};delete state.conquerBattlePreview;history.replaceState(state,'',location.href);}
                }else window.removeEventListener('popstate',onBack,true);
            });
            element.querySelector('[data-preview-close]').onclick=close;
            element.addEventListener('click',e=>e.stopPropagation());
            element.addEventListener('input',()=>{onResult?.(null);element.querySelector('.battle-preview-result').textContent=text('changed');});
            const form=element.querySelector('form'),result=element.querySelector('.battle-preview-result');
            let pending=false;
            async function calculate(event) {
                event?.preventDefault();
                if(pending||!form.reportValidity())return;
                const body={kind,target_id:Number(target.id),target_x:Number(target.coord_x??target.x),target_y:Number(target.coord_y??target.y),troops};
                if(!monster){
                    body.defender_troops=Object.fromEntries([0,1,2].map(i=>[Number(element.querySelector(`[data-preview-type="${i}"]`).value),Number(element.querySelector(`#preview-count-${i}`).value)]));
                    body.wall_bonus=Number(form.elements.wall_bonus.value);body.defender_bonus=Number(form.elements.defender_bonus.value);
                }
                pending=true;
                const controls=[...form.querySelectorAll('input,select,button')];controls.forEach(el=>el.disabled=true);
                result.textContent=text('calculating');
                try {
                    const data=await api('march/preview',body);
                    if(!element.isConnected)return;
                    onResult?.(data);
                    const win=data.outcome==='attacker_wins';
                    const row=(key,side)=>`<tr><th scope="row">${html(key)}</th><td>${fmt(side.survivors)}</td><td>${fmt(side.wounded)}</td><td>${fmt(side.dead)}</td></tr>`;
                    const at=window.ConquerLocale.formatDate(new Date(data.calculated_at*1000),{hour:'numeric',minute:'2-digit',second:'2-digit'});
                    result.innerHTML=`<h3 class="${win?'is-win':'is-loss'}">${html(monster?(win?'monster_win':'monster_survives'):(win?'example_win':'example_loss'))}</h3>
                        <p>${monster?html('monster_power',{power:fmt(data.army_power),required:fmt(data.required_power),hp:fmt(data.monster_hp_after)}):html('pvp_power',{attack:fmt(data.attacker_score),defense:fmt(data.defender_score)})}</p>
                        <table><caption>${html('troops_reference')}</caption><thead><tr><th scope="col">${html('army')}</th><th scope="col">${html('uninjured')}</th><th scope="col">${html('wounded')}</th><th scope="col">${html('fallen')}</th></tr></thead><tbody>${row('yours',data.attacker)}${data.defender?row('enemy',data.defender):''}</tbody></table>
                        ${window.ConquerBossMechanic?.render(data.boss_mechanic,{rally:kind.includes('rally')})||''}<p>${html(monster?(kind==='monster-rally'?'assumptions_rally':'assumptions_monster'):'assumptions_pvp')}</p><p>${html(monster?'notice_monster':'notice_pvp')}</p><small>${html('calculated_at',{time:at})}</small>`;
                    if(!monster)result.scrollIntoView({block:'start'});
                } catch(error) { if(element.isConnected)result.textContent=window.ConquerLocale.text(error.message); }
                finally {pending=false;controls.forEach(el=>el.disabled=false);}
            }
            form.addEventListener('submit',calculate);
            element.showModal();
            if(monster)calculate();
        }
        return {open};
    };
})();
