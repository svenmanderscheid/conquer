/* Explanations only; all thresholds and resolved effects are supplied by the server. */
(() => {
    'use strict';
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const rules=Object.freeze({
        frostgrimm_ice_armor:{prefix:'boss.frostgrimm.',percent:'required_power_percent',counter:'infantry'},
        sandmaul_sandstorm:{prefix:'boss.sandmaul.',percent:'army_power_reduction_percent',counter:'cavalry'},
        glutramm_ember_backlash:{prefix:'boss.glutramm.',percent:'injury_increase_percent',counter:'ranged'},
        daemmerhorn_runic_barrier:{prefix:'boss.daemmerhorn.',percent:'required_power_percent',counter:'balanced'}
    });
    window.ConquerBossMechanic={render(rule,{rally=false}={}){
        // Keep the original renderer for saved Grumwald reports, including those without a version.
        if(rule?.id==='grumwald_regeneration'){
        const tr=(key,parameters={})=>esc(window.ConquerLocale.t('boss.grumwald.'+key,parameters));
        const label=(key,parameters={})=>`<span data-i18n="boss.grumwald.${key}" data-i18n-params="${esc(JSON.stringify(parameters))}">${tr(key,parameters)}</span>`;
        const fmt=n=>window.ConquerLocale.formatNumber(Number(n)||0,{maximumFractionDigits:1});
        let result='';
        if(typeof rule.countered==='boolean'){
            result=`<p><strong>${label(rule.countered?'countered':'active')}</strong> ${label('share',{share:fmt(rule.counter_power_share_percent)})}</p>`;
            if(Number.isFinite(Number(rule.hp_restored)))result+=`<p>${label('restored',{hp:fmt(rule.hp_restored)})}</p>`;
        }
        return `<section class="boss-mechanic" data-i18n-attrs="aria-label:boss.grumwald.title" aria-label="${tr('title')}"><strong>${label('title')}</strong><p>${label('rule',{heal:fmt(rule.heal_percent),threshold:fmt(rule.counter_power_percent)})}</p>${result}${rally?`<p>${label('rally')}</p>`:''}</section>`;
        }
        const spec=rule&&Object.hasOwn(rules,rule.id)?rules[rule.id]:null;
        const finite=value=>typeof value==='number'&&Number.isFinite(value);
        const percent=value=>finite(value)&&value>=0&&value<=100;
        if(!spec||rule.version!==1||rule.counter_type!==spec.counter||!percent(rule[spec.percent])||!percent(rule.counter_power_percent))return '';
        const phased=spec.counter==='balanced';
        if(phased&&!percent(rule.active_above_hp_percent))return '';
        const fmt=n=>window.ConquerLocale.formatNumber(n,{maximumFractionDigits:2});
        const translated=(key,parameters={})=>esc(window.ConquerLocale.t(key,parameters));
        const text=(key,parameters={})=>`<span data-i18n="${key}" data-i18n-params="${esc(JSON.stringify(parameters))}">${translated(key,parameters)}</span>`;
        const label=(key,parameters={})=>text(spec.prefix+key,parameters);
        const parameters={effect:fmt(rule[spec.percent]),threshold:fmt(rule.counter_power_percent)};
        if(phased)parameters.hp=fmt(rule.active_above_hp_percent);
        // The server decides the phase, counter and effect; display values never decide combat.
        const phaseInactive=phased&&rule.phase_active===false;
        let state='rule';
        if(phaseInactive)state='phase-inactive';
        else if(rule.countered===true)state='countered';
        else if(rule.active===true)state='active';
        else if(rule.active===false)state='inactive';
        let result=state==='rule'?'':`<p><strong>${label(state==='phase-inactive'?'phase_inactive':state,parameters)}</strong></p>`;
        if(phaseInactive&&rule.countered===true)result+=`<p>${label('countered')}</p>`;
        if(finite(rule.counter_power_share_percent))result+=`<p>${label('share',{share:fmt(rule.counter_power_share_percent)})}</p>`;
        const shares=rule.type_power_share_percent;
        if(phased&&shares&&['infantry','ranged','cavalry'].every(type=>finite(shares[type])))result+=`<p>${label('formation',Object.fromEntries(['infantry','ranged','cavalry'].map(type=>[type,fmt(shares[type])])))}</p>`;
        const effect=(key,before,after,multiplier=1)=>finite(before)&&finite(after)?`<p>${text('boss.effects.'+key,{before:fmt(before*multiplier),after:fmt(after*multiplier)})}</p>`:'';
        if(spec.percent==='required_power_percent')result+=effect('required_power',rule.required_power_before,rule.required_power_after);
        if(spec.percent==='army_power_reduction_percent')result+=effect('army_power',rule.army_power_before,rule.army_power_after);
        if(spec.percent==='injury_increase_percent')result+=effect('injury_ratio',rule.injury_ratio_before,rule.injury_ratio_after,100);
        return `<section class="boss-mechanic" data-boss-mechanic="${esc(rule.id)}" data-boss-state="${state}" data-i18n-attrs="aria-label:${spec.prefix}title" aria-label="${translated(spec.prefix+'title')}"><strong>${label('title')}</strong><p>${label('rule',parameters)}</p><p>${text('boss.effects.counter_basis')}</p>${result}${rally?`<p>${label('rally')}</p>`:''}</section>`;
    }};
})();
