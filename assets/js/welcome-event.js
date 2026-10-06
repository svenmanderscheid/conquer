/* Welcome rewards are derived and granted by the authenticated kingdom service. */
window.ConquerWelcomeEvent = function(ctx) {
    'use strict';
    const {base,esc,fmt,getState,getKingdom,action,navigate,countdown}=ctx;
    const t=(key,args={})=>window.ConquerLocale.t('welcome_event.ui.'+key,args);
    let selected='login',signature='';
    function state(){
        const event=getKingdom()?.welcome_event,current=getState();
        if(!event?.available||Number(event.world_id)!==Number(current?.city?.world_id)||Number(event.player_id)!==Number(current?.player?.id??current?.city?.player_id))return null;
        return event;
    }
    const count=()=>Number(state()?.claimable_count)||0;
    const badge=()=>`<span class="welcome-event-count"${count()?'':' hidden'}>${fmt(count())}</span>`;
    function drops(rewards){
        return (rewards||[]).flatMap(reward=>reward.item_code?[{...reward,type:'item'}]:Number(reward.gems)>0?[{type:'resource',resource:'gems',quantity:Number(reward.gems)}]:Object.entries(reward.resources||{}).map(([resource,quantity])=>({type:'resource',resource,quantity})));
    }
    function rewardList(rewards){
        return `<ul class="welcome-reward-list">${drops(rewards).map(drop=>{
            const item=window.ConquerRewards.resolve(drop,getKingdom(),base),name=window.ConquerLocale.text(item.name);
            return `<li><span class="welcome-reward-art"${item.speedupTier?` data-speedup-tier="${esc(item.speedupTier)}"`:''}><img src="${esc(item.icon)}" alt="" loading="lazy">${item.stamp?`<small>${esc(item.stamp)}</small>`:''}</span><span>${esc(name)}</span><strong>×${fmt(item.quantity)}</strong></li>`;
        }).join('')}</ul>`;
    }
    function teaser(){
        if(!state())return '';
        return `<article class="welcome-event-teaser event-simple-row"><img src="${base}/assets/art/menu-icons-v2/treasures.png" alt=""><div><strong>${esc(t('title'))} ${badge()}</strong><small>${esc(t('teaser',{days:fmt(Math.min(7,Number(state().visit_days)||0))}))}</small></div><button type="button" class="button" data-action="progress-event-tab" data-id="welcome">${esc(t('open'))}</button></article>`;
    }
    function render(){
        const event=state();if(!event)return `<section class="welcome-event"><p>${esc(t('unavailable'))}</p></section>`;
        signature=JSON.stringify(event);
        const rows=selected==='login'?event.login_rewards:event.growth_rewards;
        return `<section class="welcome-event" data-view="${selected}"><header class="welcome-event-hero"><img src="${base}/assets/art/characters/fantasy-troops-v3/guardian-ui.webp" alt=""><div><small>${esc(t('subtitle'))}</small><h2>${esc(t('title'))}</h2><p>${esc(t('description'))}</p></div></header><nav class="welcome-event-tabs" aria-label="${esc(t('navigation'))}">${['login','growth'].map(group=>{const ready=(group==='login'?event.login_rewards:event.growth_rewards).filter(row=>row.completed&&!row.claimed).length;return `<button type="button" data-action="welcome-tab" data-id="${group}" aria-pressed="${selected===group}">${esc(t(group))}${ready?` <b>${fmt(ready)}</b>`:''}</button>`;}).join('')}</nav><div class="welcome-event-list" tabindex="0" role="region" aria-label="${esc(t(selected))}"><div class="welcome-event-rules"><p>${esc(t(selected==='login'?'login_rules':'growth_rules'))}</p>${selected==='login'&&Number(event.visit_days)<7?`<small>${esc(t('next_day'))} ${countdown(event.next_visit_at)}</small>`:''}</div>${(rows||[]).map(row=>{
            const status=row.claimed?'claimed':row.completed?'ready':'locked';
            return `<article class="welcome-event-milestone is-${status}" data-milestone="${esc(row.code)}"><div class="welcome-milestone-copy"><div class="welcome-milestone-heading"><h3>${esc(row.title)}</h3><span>${esc(t(status))}</span></div><p>${esc(row.description)}</p><div class="welcome-event-progress"><progress max="${Math.max(1,Number(row.target)||1)}" value="${Math.max(0,Math.min(Number(row.target),Number(row.progress)))}" aria-label="${esc(row.title)}"></progress><strong>${fmt(row.progress)} / ${fmt(row.target)}</strong></div>${rewardList(row.rewards)}</div><div class="welcome-milestone-action">${row.claimed?`<strong>${esc(t('claimed'))}</strong>`:row.completed?`<button type="button" class="button green" data-action="welcome-claim" data-id="${esc(row.code)}" aria-label="${esc(t('claim_for',{name:row.title}))}">${esc(t('claim'))}</button>`:row.navigation?`<button type="button" class="button secondary" data-action="welcome-go" data-id="${esc(row.code)}">${esc(t('go'))}</button>`:`<span>${esc(t('return_later'))}</span>`}</div></article>`;
        }).join('')}</div></section>`;
    }
    function update(){
        const event=state(),root=document.querySelector('.welcome-event');
        if(root&&signature!==JSON.stringify(event)){
            const list=root.querySelector('.welcome-event-list'),scroll=list?.scrollTop||0,focused=root.contains(document.activeElement),code=document.activeElement?.closest('[data-milestone]')?.dataset.milestone;
            root.outerHTML=render();
            const current=document.querySelector('.welcome-event-list');if(current)current.scrollTop=scroll;
            if(focused){const row=[...document.querySelectorAll('.welcome-event [data-milestone]')].find(row=>row.dataset.milestone===code);(row?.querySelector('button')||current)?.focus({preventScroll:true});}
        }
        const card=document.querySelector('.welcome-event-teaser');
        if(card){if(event)card.querySelector('small').textContent=t('teaser',{days:fmt(Math.min(7,Number(event.visit_days)||0))});else card.remove();}
        document.querySelectorAll('.welcome-event-count').forEach(el=>{el.textContent=fmt(count());el.hidden=!count();});
    }
    function onClick(act,button){
        if(!act.startsWith('welcome-'))return false;
        if(act==='welcome-tab'&&['login','growth'].includes(button.dataset.id)){
            selected=button.dataset.id;const root=document.querySelector('.welcome-event');if(root)root.outerHTML=render();
            document.querySelector(`.welcome-event-tabs [data-id="${selected}"]`)?.focus({preventScroll:true});return true;
        }
        const event=state(),row=[...(event?.login_rewards||[]),...(event?.growth_rewards||[])].find(row=>row.code===button.dataset.id);
        if(!row)return true;
        if(act==='welcome-claim'&&row.completed&&!row.claimed)action('kingdom/action',{action:'welcome.claim',milestone_code:row.code,expected_world_id:Number(event.world_id)}).then(update);
        if(act==='welcome-go'&&['city','army','research'].includes(row.navigation?.section))navigate(row.navigation.section);
        return true;
    }
    return {state,count,badge,teaser,render,update,onClick};
};
