/* VIP perks are earned separately in each world; amounts and claims are server-owned. */
window.ConquerVip=function(ctx){
    'use strict';
    const {base,esc,fmt,getState,getKingdom,openDialog,action}=ctx;
    let busy=false,view='overview',selected=null;
    const t=(key,params)=>window.ConquerLocale.t('vip.'+key,params);
    const status=()=>getKingdom()?.vip||getState()?.vip;
    const items=()=>getKingdom()?.inventory?.filter(i=>i.category==='vip_point'&&Number(i.quantity)>0)||[];
    const perks=['hunter_points','rally_troop_capacity','troop_training_cost','troop_training_speed','troop_training_amount','action_points','mortality_reduction','troop_limit','marching_troop_capacity','troop_dispatch_queue','action_point_regeneration','research_speed','construction_speed','gathering_speed','resource_production'];
    const flat=new Set(['hunter_points','action_points','marching_troop_capacity','troop_dispatch_queue']);
    const value=(key,amount)=>`${Number(amount)<0?'−':'+'}${fmt(Math.abs(Number(amount)||0))}${flat.has(key)?'':' %'}`;
    const button=(label,act,extra='')=>`<button type="button" class="vip-button${act==='level'?' secondary':''}" data-action="vip-${act}" ${busy?'disabled':''} ${extra}>${label}</button>`;
    function updateHud(){
        const v=status(),el=document.querySelector('#hud-vip-button');if(!v||!el)return;
        const level=el.querySelector('[data-vip-level]');if(level)level.textContent=fmt(v.level);
        const claim=el.querySelector('[data-vip-claim]');if(claim)claim.hidden=Boolean(v.daily_claimed);
        el.setAttribute('aria-label',window.ConquerLocale.t(v.daily_claimed?'hud.vip_open':'hud.vip_open_available',{level:v.level}));
        el.title=t('hud_title',{level:v.level,points:fmt(v.points)});
    }
    function bonusList(v,next=null){
        return `<dl class="vip-bonuses">${perks.map(key=>`<div data-vip-perk="${key}"><dt>${esc(t(key))}</dt><dd>${value(key,v[key])}${next&&Number(next[key])!==Number(v[key])?` <span>→ ${value(key,next[key])}</span>`:''}</dd></div>`).join('')}</dl>`;
    }
    const slots=(v,showUnlock=false)=>`<div class="vip-slots"><span>${esc(t('building_slots'))}</span><strong>${fmt(v.building_slots||1)}${showUnlock&&Number(v.building_slots)<=1?` <small>· ${esc(t('building_unlock'))}</small>`:''}</strong></div>`;
    function overview(v){
        const bonuses=v.passive_bonuses||v.bonuses,reset=new Date(v.daily_resets_at).toLocaleTimeString(window.ConquerLocale?.locale??'en',{hour:'2-digit',minute:'2-digit'});
        return `<div class="vip-overview"><section class="vip-perks"><h3>${esc(t('permanent_benefits'))}</h3>${bonusList(bonuses,v.next_bonuses)}${slots(v,true)}<p class="vip-note">${esc(t('world_scope'))}</p></section><section class="vip-daily"><span class="vip-section-label">${esc(t('daily_reward'))}</span><strong>+${fmt(v.daily_points)} <small>${esc(t('points'))}</small></strong>${v.daily_claimed?`<span class="vip-claimed">✓ ${esc(t('claimed'))}</span>`:button(esc(t('claim')),'daily')}<small>${esc(t('reset_time',{time:reset}))}</small><p>${esc(t('rank_permanent'))}</p></section></div><section class="vip-items"><div><h3>${esc(t('inventory_title'))}</h3><p>${esc(t('inventory_hint'))}</p></div>${items().length?`<div class="vip-item-list">${items().map(i=>`<div class="vip-item"><img src="${base}/assets/art/items/prestige.svg" alt=""><div><strong>+${fmt(i.vip_points)} ${esc(t('points'))}</strong><small>${esc(t('owned',{count:fmt(i.quantity)}))}</small></div>${button(esc(t('use_one')),'item',`data-id="${Number(i.item_code)}"`)}</div>`).join('')}</div>`:`<div class="vip-empty">${esc(t('empty_inventory'))}</div>`}</section>`;
    }
    function levels(v){
        const catalog=v.levels||[],level=catalog.find(l=>l.level===(selected??v.level))||catalog[0];if(!level)return '';
        return `<section class="vip-levels"><p>${esc(t('choose_hint'))}</p><div class="vip-level-picker" role="group" aria-label="${esc(t('choose_level'))}">${catalog.map(l=>button(String(l.level),'level',`data-id="${l.level}" aria-label="VIP ${l.level}${l.level<=v.level?', '+esc(t('reached')):''}" aria-pressed="${l.level===level.level}"`)).join('')}</div><div class="vip-level-detail"><div class="vip-level-title"><h3>VIP ${level.level}</h3><span>${level.level<=v.level?'✓ '+esc(t('reached')):esc(t('points_required',{points:fmt(level.points)}))}</span></div>${bonusList(level.bonuses)}${slots(level)}</div></section>`;
    }
    function open(){
        const v=status();if(!v)return;
        const progress=v.is_max?100:Math.max(0,Math.min(100,Number(v.progress_points)/Math.max(1,Number(v.progress_required))*100));
        openDialog(`<h2>${esc(t('title'))}</h2><div class="vip-panel"><div class="vip-hero"><img src="${base}/assets/art/items/prestige.svg" alt=""><div><span class="vip-section-label">${esc(t('permanent_rank'))}</span><strong>VIP ${fmt(v.level)}</strong><p>${esc(v.is_max?t('maximum'):t('points_remaining',{points:fmt(v.points_remaining),level:v.level+1}))}</p></div></div><div class="vip-progress"><div><span>${esc(t('points_total',{points:fmt(v.points)}))}</span><strong>${v.is_max?esc(t('max')):fmt(v.progress_points)+' / '+fmt(v.progress_required)}</strong></div><progress max="100" value="${progress}" aria-label="${esc(t('progress'))}"></progress></div><nav class="vip-tabs" aria-label="${esc(t('sections'))}">${button(esc(t('your_benefits')),'overview',`aria-pressed="${view==='overview'}"`)}${button(esc(t('all_levels',{count:v.levels?.length||20})),'levels',`aria-pressed="${view==='levels'}"`)}</nav>${view==='levels'?levels(v):overview(v)}</div>`);
        updateHud();
    }
    async function mutate(kind,id){
        if(busy)return;
        if(kind==='daily'&&status()?.daily_claimed)return;
        if(kind==='item'&&!items().some(i=>Number(i.item_code)===id))return;
        busy=true;open();
        try{await action('kingdom/action',kind==='daily'?{action:'vip.daily'}:{action:'inventory.use',item_code:id},t('points_received'));}
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
