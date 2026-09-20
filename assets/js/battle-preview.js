(() => {
    'use strict';
    window.ConquerBattlePreview = function({api,esc,fmt,getState}) {
        let dialog=null;
        function open({kind,target,troops,onResult}) {
            dialog?.close();
            const monster=kind.startsWith('monster');
            const element=document.createElement('dialog');
            dialog=element;
            const historyEntry={token:crypto.randomUUID(),url:location.href};
            history.pushState({...history.state,conquerBattlePreview:historyEntry.token},'',location.href);
            const onBack=()=>{if(history.state?.conquerBattlePreview!==historyEntry.token)element.close();};
            window.addEventListener('popstate',onBack);
            element.className='battle-preview-dialog';
            element.setAttribute('aria-labelledby','battle-preview-title');
            const definitions=getState().troop_defs;
            const options=type=>definitions.filter(t=>Number(t.type)===type).sort((a,b)=>a.tier-b.tier).map(t=>`<option value="${t.code}">T${t.tier}</option>`).join('');
            element.innerHTML=`<header><h2 id="battle-preview-title">Kampfrechner</h2><button type="button" data-preview-close aria-label="Kampfrechner schließen">×</button></header>
                <form class="battle-preview-body"><p>${monster?'Deine ausgewählten Truppen werden gegen die aktuellen Monsterwerte berechnet.':'Trage die vermutete Verteidigung ein. Dies ist eine Beispielrechnung; unbekannte Gegnerdaten bleiben verborgen.'}</p>
                ${monster?'':`<fieldset><legend>Angenommene Gegnertruppen</legend>${['Infanterie','Fernkämpfer','Kavallerie'].map((name,i)=>`<div class="battle-preview-unit"><label for="preview-count-${i}">${name}</label><select data-preview-type="${i}" aria-label="${name} Stufe">${options(i+1)}</select><input id="preview-count-${i}" type="number" inputmode="numeric" min="0" max="500000" step="1" value="0" required></div>`).join('')}<label class="battle-preview-bonus">Gegnerbonus auf Angriff, Verteidigung und LP (%)<input name="defender_bonus" type="number" inputmode="decimal" min="0" max="1000" step="0.1" value="0" required></label><label class="battle-preview-bonus">Zusätzlicher Mauerbonus (%)<input name="wall_bonus" type="number" inputmode="decimal" min="0" max="1000" step="0.1" value="0" required></label></fieldset>`}
                <p class="battle-preview-selection">${fmt(Object.values(troops).reduce((s,n)=>s+n,0))} eigene Truppen · ${kind.includes('rally')?'nur dein Rally-Beitrag':'aus deiner Marschauswahl'}</p>
                <button class="button" type="submit">${monster?'Neu berechnen':'Beispiel berechnen'}</button>
                <div class="battle-preview-result" role="status" aria-live="polite"></div>
                <p class="battle-preview-footnote">Die Berechnung startet keinen Angriff und verbraucht keine Truppen oder Aktionspunkte.</p></form>`;
            document.body.append(element);
            const parent=document.querySelector('#game-dialog');
            const close=()=>element.close();
            parent?.addEventListener('close',close);
            element.addEventListener('close',()=>{
                parent?.removeEventListener('close',close);window.removeEventListener('popstate',onBack);element.remove();if(dialog===element)dialog=null;
                if(history.state?.conquerBattlePreview===historyEntry.token){
                    if(location.href===historyEntry.url)history.back();
                    else {const state={...history.state};delete state.conquerBattlePreview;history.replaceState(state,'',location.href);}
                }
            });
            element.querySelector('[data-preview-close]').onclick=close;
            element.addEventListener('click',e=>e.stopPropagation());
            element.addEventListener('input',()=>{onResult?.(null);element.querySelector('.battle-preview-result').textContent='Eingaben geändert. Bitte erneut berechnen.';});
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
                result.textContent='Wird berechnet …';
                try {
                    const data=await api('march/preview',body);
                    if(!element.isConnected)return;
                    onResult?.(data);
                    const win=data.outcome==='attacker_wins';
                    const row=(name,side)=>`<tr><th scope="row">${name}</th><td>${fmt(side.survivors)}</td><td>${fmt(side.wounded)}</td><td>${fmt(side.dead)}</td></tr>`;
                    result.innerHTML=`<h3 class="${win?'is-win':'is-loss'}">${monster?(win?'Sieg erwartet':'Monster überlebt'):(win?'Sieg in diesem Beispiel':'Niederlage in diesem Beispiel')}</h3>
                        <p>${monster?`Macht ${fmt(data.army_power)} / ${fmt(data.required_power)} · Monster-LP danach: ${fmt(data.monster_hp_after)}`:`Angriff ${fmt(data.attacker_score)} / Verteidigung ${fmt(data.defender_score)}`}</p>
                        <table><caption>Erwartete Truppen nach dem Kampf</caption><thead><tr><th scope="col">Armee</th><th scope="col">Unverletzt</th><th scope="col">Verwundet</th><th scope="col">Gefallen</th></tr></thead><tbody>${row('Deine',data.attacker)}${data.defender?row('Gegner',data.defender):''}</tbody></table>
                        <p>${esc(data.assumptions)}</p><p>${esc(data.notice)}</p><small>Stand: ${esc(new Date(data.calculated_at*1000).toLocaleTimeString())}</small>`;
                    if(!monster)result.scrollIntoView({block:'start'});
                } catch(error) { if(element.isConnected)result.textContent=error.message; }
                finally {pending=false;controls.forEach(el=>el.disabled=false);}
            }
            form.addEventListener('submit',calculate);
            element.showModal();
            if(monster)calculate();
        }
        return {open};
    };
})();
