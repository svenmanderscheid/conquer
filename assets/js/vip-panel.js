/* VIP perks are earned account-wide; every amount and claim is server-owned. */
window.ConquerVip=function(ctx){
    'use strict';
    const {base,esc,fmt,getState,getKingdom,openDialog,action}=ctx;
    let busy=false,view='overview',selected=null;
    const status=()=>getKingdom()?.vip||getState()?.vip;
    const items=()=>getKingdom()?.inventory?.filter(i=>i.category==='vip_point'&&Number(i.quantity)>0)||[];
    const names={construction_speed:'Bauzeit',research_speed:'Forschungszeit',resource_production:'Rohstoffproduktion',troop_training_speed:'Ausbildungstempo'};
    const sign=k=>k==='construction_speed'||k==='research_speed'?'−':'+';
    const button=(label,act,extra='')=>`<button type="button" class="vip-button" data-action="vip-${act}" ${busy?'disabled':''} ${extra}>${label}</button>`;
    function updateHud(){
        const v=status(),el=document.querySelector('#hud-vip-button');if(!v||!el)return;
        const level=el.querySelector('[data-vip-level]');if(level)level.textContent=fmt(v.level);
        const claim=el.querySelector('[data-vip-claim]');if(claim)claim.hidden=Boolean(v.daily_claimed);
        el.setAttribute('aria-label',`VIP ${v.level} öffnen${v.daily_claimed?'':', tägliche Punkte verfügbar'}`);
        el.title=`VIP ${v.level} · ${fmt(v.points)} Punkte`;
    }
    function bonusList(v,next=null){
        return `<dl class="vip-bonuses">${Object.entries(names).map(([k,label])=>`<div><dt>${label}</dt><dd>${sign(k)}${fmt(v[k]||0)} %${next&&Number(next[k])!==Number(v[k])?` <span>→ ${sign(k)}${fmt(next[k])} %</span>`:''}</dd></div>`).join('')}</dl>`;
    }
    function overview(v){
        const bonuses=v.passive_bonuses||v.bonuses,reset=new Date(v.daily_resets_at).toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'});
        return `<div class="vip-overview"><section class="vip-perks"><h3>Deine dauerhaften Vorteile</h3>${bonusList(bonuses,v.next_bonuses)}<div class="vip-slots"><span>Gleichzeitige Bauaufträge</span><strong>${fmt(v.building_slots||1)}${v.level<4?' <small>· 2 ab VIP 4</small>':''}</strong></div><p class="vip-note">Gilt in allen deinen Welten. Zeitboni gelten für neue Aufträge; laufende Aufträge behalten ihre Endzeit. Bau- und Forschungszeit bleiben mindestens 1 Sekunde.</p></section><section class="vip-daily"><span class="vip-section-label">TÄGLICHE BELOHNUNG</span><strong>+${fmt(v.daily_points)} <small>VIP-Punkte</small></strong>${v.daily_claimed?'<span class="vip-claimed">✓ Heute abgeholt</span>':button('Punkte abholen','daily')}<small>Wieder verfügbar um ${esc(reset)} Uhr · deine Ortszeit</small><p>Deine VIP-Stufe bleibt dauerhaft erhalten.</p></section></div><section class="vip-items"><div><h3>VIP-Punkte im Inventar</h3><p>Nutze gesammelte Prestigegegenstände für die nächste Stufe.</p></div>${items().length?`<div class="vip-item-list">${items().map(i=>`<div class="vip-item"><img src="${base}/assets/art/items/prestige.svg" alt=""><div><strong>+${fmt(i.vip_points)} VIP-Punkte</strong><small>${fmt(i.quantity)} vorhanden</small></div>${button('1 nutzen','item',`data-id="${Number(i.item_code)}"`)}</div>`).join('')}</div>`:'<div class="vip-empty">Noch keine VIP-Gegenstände vorhanden. Du kannst sie aus Belohnungen und Truhen erhalten.</div>'}</section>`;
    }
    function levels(v){
        const level=(v.levels||[]).find(l=>l.level===selected)||(v.levels||[])[v.level];if(!level)return '';
        return `<section class="vip-levels"><p>Wähle eine Stufe und entdecke ihre Vorteile.</p><div class="vip-level-picker" role="group" aria-label="VIP-Stufe wählen">${(v.levels||[]).map(l=>button(String(l.level),'level',`data-id="${l.level}" aria-label="VIP ${l.level}${l.level<=v.level?', erreicht':''}" aria-pressed="${l.level===level.level}"`)).join('')}</div><div class="vip-level-detail"><div class="vip-level-title"><h3>VIP ${level.level}</h3><span>${level.level<=v.level?'✓ Erreicht':fmt(level.points)+' Punkte erforderlich'}</span></div>${bonusList(level.bonuses)}<div class="vip-slots"><span>Gleichzeitige Bauaufträge</span><strong>${level.building_slots}</strong></div></div></section>`;
    }
    function open(){
        const v=status();if(!v)return;
        const progress=v.is_max?100:Math.max(0,Math.min(100,Number(v.progress_points)/Math.max(1,Number(v.progress_required))*100));
        openDialog(`<h2>VIP · Dein königlicher Rang</h2><div class="vip-panel"><div class="vip-hero"><img src="${base}/assets/art/items/prestige.svg" alt=""><div><span class="vip-section-label">DAUERHAFTER RANG</span><strong>VIP ${fmt(v.level)}</strong><p>${v.is_max?'Höchste VIP-Stufe erreicht':`Noch <b>${fmt(v.points_remaining)}</b> Punkte bis VIP ${v.level+1}`}</p></div></div><div class="vip-progress"><div><span>${fmt(v.points)} VIP-Punkte gesamt</span><strong>${v.is_max?'MAX':fmt(v.progress_points)+' / '+fmt(v.progress_required)}</strong></div><progress max="100" value="${progress}" aria-label="Fortschritt zur nächsten VIP-Stufe"></progress></div><nav class="vip-tabs" aria-label="VIP-Bereiche">${button('Deine Vorteile','overview',`aria-pressed="${view==='overview'}"`)}${button('Alle 21 Stufen','levels',`aria-pressed="${view==='levels'}"`)}</nav>${view==='levels'?levels(v):overview(v)}</div>`);
        updateHud();
    }
    async function mutate(kind,id){
        if(busy)return;
        if(kind==='daily'&&status()?.daily_claimed)return;
        if(kind==='item'&&!items().some(i=>Number(i.item_code)===id))return;
        busy=true;open();
        try{await action('kingdom/action',kind==='daily'?{action:'vip.daily'}:{action:'inventory.use',item_code:id},'VIP-Punkte erhalten.');}
        finally{busy=false;open();}
    }
    function onClick(act,b){
        if(!act.startsWith('vip-'))return false;
        if(act==='vip-open')open();
        else if(act==='vip-overview'||act==='vip-levels'){view=act.slice(4);open();}
        else if(act==='vip-level'){selected=Number(b.dataset.id);open();}
        else if(act==='vip-daily'||act==='vip-item')void mutate(act.slice(4),Number(b.dataset.id));
        return true;
    }
    return{open,onClick,updateHud};
};
