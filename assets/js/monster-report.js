/* Monster reports share the player report layout and historical presentation. */
window.ConquerMonsterReport = (() => {
    'use strict';
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const number = value => value == null || !Number.isFinite(Number(value)) ? '—' : Math.round(Number(value)).toLocaleString('de-DE');
    const percent = value => value == null ? '—' : `${Number(value)>=0?'+':''}${Number(value).toLocaleString('de-DE',{minimumFractionDigits:1,maximumFractionDigits:1})} %`;
    const types = {infantry:['Infanterie','knight'],cavalry:['Kavallerie','rider'],ranged:['Bogenschützen','archer']};
    const troopArt = (type,tier) => {
        const level=Number(tier),prefix=type===types.infantry?'infantry':type===types.ranged?'archer':type===types.cavalry?'cavalry':null;
        return prefix&&level>=1&&level<=10?`characters/tier-colors-v1/${prefix}-t${level}-report.webp`:type[1]+'.png';
    };
    const isMonster = r => Boolean(r?.details && !r.details.battle_kind && (r.target_type == null || Number(r.target_type)===3) && (r.details.monster_name != null || r.details.monster_snapshot != null));
    const sum = (rows,key) => rows.reduce((total,row)=>total+(Number(row[key])||0),0);
    // Older monster rows omit deaths; derive them only from a complete saved balance.
    const fallen = troop => troop.dead ?? (['sent','injured','survived'].every(key=>troop[key]!=null&&Number.isFinite(Number(troop[key])))?Math.max(0,Number(troop.sent)-Number(troop.injured)-Number(troop.survived)):null);
    const section = (title,body,cls='') => `<section class="cr-section ${cls}"><h3>${title}</h3>${body}</section>`;
    const fold = (title,body,key) => `<details class="cr-section cr-fold mr-report-block" data-mr-fold="${key}" open><summary>${title}<span class="cr-chevron" aria-hidden="true">⌄</span></summary>${body}</details>`;
    const button = (label,action,extra='',classes='') => `<button type="button" class="cr-button ${classes}" data-monster="${action}" ${extra}>${label}</button>`;
    const luck = value => {
        if(value==null||!Number.isFinite(Number(value)))return '';
        const amount=Math.max(-10,Math.min(10,Number(value))),position=(amount+10)*5;
        return `<div class="cr-luck" role="img" aria-label="Kampfglück ${percent(amount)}"><div class="cr-luck-heading"><strong>Glück</strong><span class="${amount>0?'cr-positive':amount<0?'cr-negative':''}">${percent(amount)}</span></div><div class="cr-luck-track"><span class="cr-luck-zero" aria-hidden="true"></span><span class="cr-luck-marker" style="left:${position}%" aria-hidden="true"></span></div><div class="cr-luck-scale" aria-hidden="true"><span>−10 %</span><span>0 %</span><span>+10 %</span></div></div>`;
    };
    const row = (label,left,right,cls='') => `<tr><td class="${cls}">${left}</td><th scope="row">${label}</th><td class="${cls}">${right}</td></tr>`;
    function asset(base,file) {
        return /^[a-zA-Z0-9_/-]+\.(svg|png|webp)$/.test(file||'')&&!file.includes('..')?`${base}/assets/art/items/${file}`:'';
    }
    function monsterArt(base,m,name) {
        const art=String(m?.art||'');
        if(/^monsters\/[a-z-]+$/.test(art))return `${base}/assets/art/${art}.png`;
        const aliases={skeleton:['skeleton','skelett'],golem:['golem'],goblin:['goblin'],orc:['orc','ork']};
        const kind=Object.keys(aliases).find(k=>art===k||aliases[k].some(alias=>String(name).toLowerCase().includes(alias)));
        return kind?`${base}/assets/art/map/life-${kind}.png`:'';
    }
    function model(r,base) {
        const d=r.details||{},source=d.source_snapshot,identity=source?.identity||{},m=d.monster_snapshot;
        const name=`${identity.alliance_tag?'['+identity.alliance_tag+'] ':''}${identity.name||'Deine Armee'}`;
        const enemy=m?`${m.name} · Stufe ${number(m.level)}`:d.monster_name||'Monster';
        const avatar=['knight','archer','rider'].includes(identity.avatar)?identity.avatar:'knight';
        return {d,source,identity,m,name,enemy,troops:d.troops||[],own:d.combat_snapshot,rally:d.rally_combat_snapshot,
            avatar:`${base}/assets/art/${avatar}.png`,portrait:monsterArt(base,m,enemy)};
    }
    function delivery(r) {
        const d=r.details||{},rewarded=Object.values(d.loot||{}).some(n=>Number(n)>0)||(d.item_rewards||[]).length;
        if(!rewarded)return 'Keine Beute in diesem Gefecht.';
        return r.reward_delivery==='delivered'?'✓ Beute gutgeschrieben':r.reward_delivery==='returning'?'Beute auf dem Rückmarsch':'Beute wird bei der Rückkehr gutgeschrieben.';
    }
    function hp(d) {
        return `<div class="mr-hp"><span>Monster-HP · nach / vor Kampf</span><strong>${number(d.monster_hp_after)} / ${number(d.monster_hp_before)}</strong><progress max="${Math.max(1,Number(d.monster_hp_before)||1)}" value="${Math.max(0,Number(d.monster_hp_after)||0)}" aria-label="Verbleibende Monster-Lebenspunkte"></progress></div>`;
    }
    function power(v) {
        const comparison=v.rally||v.own;
        if(!comparison||!v.m)return '<p class="cr-note">Ein vollständiger Stärkenvergleich ist für diesen älteren Bericht nicht verfügbar.</p>';
        const powerRow=v.d.army_power!=null&&v.d.required_power!=null?(()=>{const a=Math.max(0,Number(v.d.army_power)||0),b=Math.max(0,Number(v.d.required_power)||0),share=a+b>0?a/(a+b)*100:0;return `<div class="cr-power-row"><strong>Macht</strong><div><div class="cr-bar" aria-hidden="true"><span style="width:${share}%"></span></div><div class="cr-bar-values"><span>${number(a)}</span><span>${number(b)} benötigt</span></div></div></div>`;})():'';
        return `<p class="cr-note">${v.rally?'Gesamte Rally':'Deine Armee'} rot / Monster blau · Werte beim Kampf</p><div class="cr-power-rows">${powerRow}${['attack','defense','hp'].map((key,i)=>{
            const a=Math.max(0,Number(comparison[key])||0),b=Math.max(0,Number(v.m[key])||0),share=a+b>0?a/(a+b)*100:0;
            return `<div class="cr-power-row"><strong>${['Angriff','Verteidigung','Lebenspunkte'][i]}</strong><div><div class="cr-bar ${a+b>0?'':'is-empty'}" aria-hidden="true"><span style="width:${share}%"></span></div><div class="cr-bar-values"><span>${number(a)}</span><span>${number(b)}</span></div></div></div>`;
        }).join('')}</div>${v.rally?`<p class="cr-note">${number(v.rally.count)} Truppen in der gesamten Rally · ${number(v.own?.count)} gehören dir. Truppenbilanz, Relikte und Boni zeigen deinen Anteil.</p>`:''}`;
    }
    function shareText(r,base) {
        const v=model(r,base),result=r.outcome==='attacker_wins'?'Sieg':'Niederlage';
        return `⚔ Monster-Kampfbericht #${Number(r.id)} · ${result} · X:${number(r.target_x)} Y:${number(r.target_y)} · ${v.name} gegen ${v.enemy} · ${number(sum(v.troops,'sent'))} Truppen, ${number(sum(v.troops,'injured'))} verwundet, ${number(sum(v.troops,'dead'))} gefallen`;
    }
    function troopCard(base,troop) {
        const type=types[troop.type]||types[({1:'infantry',2:'ranged',3:'cavalry'})[String(troop.code)[2]]]||types.infantry;
        return `<article class="mr-troop-card"><img class="troop-tier-frame" data-troop-tier="${Number(troop.tier)||0}" src="${base}/assets/art/${troopArt(type,troop.tier)}" alt=""><div><strong>${esc(troop.name||type[0])}</strong><small>Tier ${number(troop.tier)}</small><b>${number(troop.sent)}</b></div></article>`;
    }
    function troopOverview(v,base) {
        const player=v.troops.length?v.troops.map(troop=>troopCard(base,troop)).join(''):'<p class="cr-note">Keine Truppenaufstellung gespeichert.</p>';
        const monster=v.m?`<article class="mr-troop-card defender">${v.portrait?`<img src="${v.portrait}" alt="">`:'<span class="cr-avatar mr-placeholder" aria-hidden="true">♜</span>'}<div><strong>${esc(v.enemy)}</strong><small>Monstertruppe</small><b>${number(v.m.count)}</b></div></article>`:'<p class="cr-note">Monstertruppen wurden damals nicht gespeichert.</p>';
        return `<div class="mr-troop-overview"><div><h4>${esc(v.name)}</h4><div class="mr-troop-grid">${player}</div><strong class="mr-troop-total">Gesamt: ${number(sum(v.troops,'sent'))}</strong></div><div><h4>${esc(v.enemy)}</h4><div class="mr-troop-grid">${monster}</div><strong class="mr-troop-total">Gesamt: ${number(v.m?.count)}</strong></div></div>`;
    }
    function render(r,{base='',previous=null,next=null,kingdom={},canShare=false}={}) {
        const v=model(r,base),{d,m,source,identity,troops,own,name,enemy}=v,win=r.outcome==='attacker_wins';
        const date=new Date(String(r.created_at||'').replace(' ','T')+'Z'),stamp=Number.isNaN(date.getTime())?'Zeitpunkt unbekannt':date.toLocaleString('de-DE');
        const mapAction=win&&d.charm?'Charm am Kampfort einsammeln':'Monster am Kampfort erneut angreifen';
        const overview=section('Kampfübersicht',`<div class="cr-versus"><div class="cr-result ${win?'victory':'defeat'}"><span aria-hidden="true">${win?'♛':'⚔'}</span><strong>${win?'Sieg':'Niederlage'}</strong><small>Deine Offensive</small></div>
            <div class="cr-identity attacker"><small class="cr-role">Angreifer${v.rally?' · dein Anteil':''}</small><img class="cr-avatar" src="${v.avatar}" alt=""><div><strong>${esc(name)}</strong><small>${identity.x==null?'Koordinaten nicht gespeichert':`X:${number(identity.x)} Y:${number(identity.y)}`}</small></div></div>
            <button type="button" class="cr-identity defender cr-location-link" data-monster="location" aria-label="${esc(mapAction)}"><small class="cr-role">Monster</small>${v.portrait?`<img class="cr-avatar" src="${v.portrait}" alt="">`:'<span class="cr-avatar mr-placeholder" aria-hidden="true">♜</span>'}<div><strong>${esc(enemy)}</strong><small>X:${number(r.target_x)} Y:${number(r.target_y)}</small><span class="cr-location-hint">${win&&d.charm?'Charm einsammeln':'Erneut angreifen'} →</span></div></button></div>
            <table class="cr-compare"><caption class="cr-sr">Deine Armee und Monster im Vergleich</caption><thead class="cr-sr"><tr><th>Angreifer</th><th>Wert</th><th>Monster</th></tr></thead><tbody>
            ${row('Truppen',number(sum(troops,'sent')),number(m?.count))}
            ${row('Gefallen',number(sum(troops,'dead')),number(m&&d.monster_killed?m.count:null),'cr-negative')}
            ${row('Verwundet',number(sum(troops,'injured')),number(m?0:null),'cr-negative')}
            ${row('Einsatzfähig',number(sum(troops,'survived')),number(m&&d.monster_killed?0:null))}
            </tbody></table>${hp(d)}`,'mr-summary');
        const rewards=[];
        const reward=(label,count,src)=>`<div class="mr-reward">${src?`<img src="${src}" alt="${esc(label)}">`:'<span class="mr-reward-symbol" aria-hidden="true">✦</span>'}<span><span class="mr-reward-name">${esc(label)}</span><strong>× ${number(count)}</strong></span></div>`;
        for(const [key,label] of Object.entries({food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold',gems:'Edelsteine'})){
            const amount=d.loot?.[key]??(key==='lumber'?d.loot?.wood:0);
            if(Number(amount)>0)rewards.push(reward(label,amount,key==='gems'?asset(base,'gems.svg'):`${base}/assets/art/ui-resources/${key}.png`));
        }
        for(const item of d.item_rewards||[])if(Number(item.count??item.quantity)>0){const resolved=window.ConquerRewards?.resolve(item,kingdom,base);rewards.push(reward(resolved?.name||item.name,item.count??item.quantity,resolved?.icon||asset(base,item.icon)));}
        const loot=section('Erhaltene Beute',`<div class="cr-resources">${rewards.join('')||'<p class="cr-note">Keine Gegenstände oder Rohstoffe erhalten.</p>'}</div><p class="cr-note mr-delivery ${r.reward_delivery==='delivered'?'mr-delivered':''}" data-report-delivery role="status">${delivery(r)}</p>${d.charm?'<p class="cr-note">✦ Öffentlicher Charm am Kampfort · separat einsammeln.</p>':''}`,'mr-loot');
        const equipment=!source?'<p class="cr-note">Die damaligen Relikte wurden nicht gespeichert.</p>':source.equipment?.length?`<div class="cr-equipment mr-equipment-list">${source.equipment.map(item=>`<div class="cr-item grade-${['normal','uncommon','rare','epic','legendary'].includes(item.grade)?item.grade:'normal'}">${asset(base,item.icon)?`<img src="${asset(base,item.icon)}" alt="" loading="lazy">`:'<span aria-hidden="true">✦</span>'}<strong>${esc(item.name_de||item.name)}</strong><small>Stufe ${number(item.level)}</small></div>`).join('')}</div>`:'<p class="cr-note">Keine Relikte ausgerüstet.</p>';
        const talents=!source?'<p class="cr-note">Die damalige Hunter-Meisterschaft wurde nicht gespeichert.</p>':`<div class="mr-mastery-head"><span aria-hidden="true">✦</span><div><strong>Hunter-Meisterschaft</strong><small>Lord-Stufe ${number(source.hunter?.level)}</small></div></div>${source.hunter?.talents?.length?`<ul class="cr-talents mr-mastery-list">${source.hunter.talents.map(t=>`<li><span>${esc(t.name)}</span><b>Rang ${number(t.rank)}/5</b></li>`).join('')}</ul>`:'<p class="cr-note">Keine Hunter-Talente vergeben.</p>'}`;
        const bonusRows=own?Object.entries(types).flatMap(([type,[label]])=>Object.entries({atk:'Angriff',def:'Verteidigung',hp:'Lebenspunkte'}).map(([key,stat])=>`<li><span><i aria-hidden="true">◈</i>${label} · ${stat}</span><b>${percent(own.bonuses?.[type]?.[key])}</b></li>`)).join(''):'';
        const bonuses=own?`<ul class="mr-boost-list">${bonusRows}</ul><p class="cr-note">Wirksame Boni zum Kampfzeitpunkt, inklusive Monsterbonus. Das Monster kämpft mit seinen gespeicherten Grundwerten.</p>`:'<p class="cr-note">Die Kampfboni wurden damals nicht gespeichert. Aktuelle Boni werden diesem Bericht nicht nachträglich zugeordnet.</p>';
        const experience=section('Erfahrung',`<div class="mr-experience"><span>Hunter-XP</span><strong>${d.lord_xp>0?'+'+number(d.lord_xp):'Keine XP'}</strong><small>${d.lord_xp>0?'Bereits gutgeschrieben':'In diesem Gefecht wurde keine Erfahrung vergeben.'}</small></div>`,'mr-xp');
        return `<h2>Monster-Kampfbericht</h2><article class="combat-report monster-report" data-monster-report="${Number(r.id)}"><div class="cr-scroll">
            <div class="cr-banner"><div><small>${d.type==='monster_rally'?'Gemeinsamer Monsterangriff':'Monsterangriff'} · #${Number(r.id)}</small><strong>X:${number(r.target_x)} Y:${number(r.target_y)}</strong></div><time>${esc(stamp)}</time></div>
            ${luck(d.luck_percent)}${!m?'<p class="cr-notice">Älterer Bericht: Monster-Truppenwerte wurden damals nicht gespeichert.</p>':''}${overview}${loot}${section('Truppenübersicht',troopOverview(v,base),'mr-troops')}${section('Kampfstärke',power(v))}${experience}${fold('Hunter-Meisterschaft',talents,'talents')}${fold('Relikte im Kampf',equipment,'equipment')}${fold('Aktive Kampfboni',bonuses,'bonuses')}
            ${r.can_delete?`<details class="cr-rules mr-delete"><summary>Bericht löschen</summary><p>Der Bericht verschwindet aus deiner Post. Truppen und Belohnungen bleiben erhalten.</p><button type="button" class="cr-button" data-action="monster-report-delete" data-id="${Number(r.id)}">Löschen</button></details>`:''}</div>
            <footer class="cr-footer">${button('‹','previous',`data-id="${previous||''}" aria-label="Neuerer Kampfbericht" ${previous?'':'disabled'}`,'cr-report-arrow')}${button('<span class="cr-wide-label">Kampfdetails</span><span class="cr-short-label">Details</span>','details','','cr-primary')}${canShare?button('↗ <span class="cr-action-label">Teilen</span>','share','aria-label="Bericht teilen"','cr-share-button'):''}${button('⧉ <span class="cr-action-label">Kopieren</span>','copy','aria-label="Berichtszusammenfassung kopieren"','cr-copy-button')}${button('›','next',`data-id="${next||''}" aria-label="Älterer Kampfbericht" ${next?'':'disabled'}`,'cr-report-arrow')}</footer></article>`;
    }
    function renderDetails(r,base) {
        const v=model(r,base),{d,troops}=v;
        return `<header class="cr-detail-heading"><h2 id="monster-detail-title" tabindex="-1">Kampfdetails</h2>${button('×','close-details','aria-label="Kampfdetails schließen"')}</header><div class="cr-detail-scroll">
            <details class="cr-rules"><summary>Kampfwertung & Rückkehr</summary><p>Die Kampfwerte enthalten die damals aktiven Boni. Truppenmacht verloren, leicht Verwundete und Abschüsse je Einheit werden in Monsterberichten nicht separat erfasst.</p><p>Einsatzfähige Truppen und Beute kehren mit dem Rückmarsch zurück. Verwundete werden im Hospital versorgt. Die Monster-HP zeigen den Stand nach und vor diesem Gefecht.</p></details>
            <section class="cr-detail-side"><h3 class="cr-ribbon attacker">Angreifer${v.rally?' · dein Anteil':''}</h3><details class="cr-army" open><summary><img class="cr-avatar" src="${v.avatar}" alt=""><span><strong>${esc(v.name)}</strong><small>${number(sum(troops,'sent'))} Truppen</small></span><b class="cr-chevron" aria-hidden="true">⌄</b></summary><div class="cr-troop-list">${troops.map(t=>{
                const type=types[t.type]||types[({1:'infantry',2:'ranged',3:'cavalry'})[String(t.code)[2]]]||types.infantry;
                return `<article class="cr-troop"><div class="cr-troop-name"><img class="troop-tier-frame" data-troop-tier="${Number(t.tier)||0}" src="${base}/assets/art/${troopArt(type,t.tier)}" alt=""><span><strong>${esc(t.name||type[0])}</strong><small>Tier ${number(t.tier)} · ${number(t.sent)} Truppen</small></span></div><dl>${[['dead','Gefallen'],['injured','Verwundet'],['survived','Einsatzfähig']].map(([key,label])=>`<div><dt>${label}</dt><dd class="${key==='survived'?'':'cr-negative'}">${number(key==='dead'?fallen(t):t[key])}</dd></div>`).join('')}</dl></article>`;
            }).join('')||'<p class="cr-note">Keine Truppenaufstellung gespeichert.</p>'}</div></details></section>
            <section class="cr-detail-side"><h3 class="cr-ribbon defender">Monster</h3>${section(esc(v.enemy),hp(d)+power(v))}</section></div>`;
    }
    // Both the app dialog and the direct report page use these same actions.
    function bind({root,getReport,base='',toast,navigateReport,shareReport,locateReport}) {
        const detail=document.createElement('dialog');detail.id='monster-combat-details';detail.className='combat-details';detail.setAttribute('aria-labelledby','monster-detail-title');document.body.append(detail);
        const modal=root.tagName==='DIALOG';let token=null,origin='',closing=false;
        const showDetails=()=>{detail.innerHTML=renderDetails(getReport(),base);if(!detail.open)detail.showModal();detail.querySelector('h2').focus({preventScroll:true});};
        function begin() {
            if(token)return;
            token=`monster-${getReport().id}-${Date.now()}`;origin=location.href;
            if(modal)history.pushState({...history.state,conquerMonsterReport:token,monsterDepth:1},'',location.href);
        }
        function leaveReport(callback){
            if(token&&history.state?.conquerMonsterReport===token){const depth=Number(history.state.monsterDepth||1);token=null;window.addEventListener('popstate',()=>callback(),{once:true});history.go(-depth);return;}
            callback();
        }
        root.addEventListener('click',async event=>{
            const action=event.target.closest('[data-monster]')?.dataset.monster;
            if(!getReport()||!root.querySelector('.monster-report'))return;
            if(action==='details'){begin();history.pushState({...history.state,conquerMonsterReport:token,monsterDepth:2},'',location.href);showDetails();}
            if(action==='location'&&locateReport)leaveReport(()=>locateReport(getReport()));
            if((action==='previous'||action==='next')&&navigateReport){const id=Number(event.target.closest('[data-id]')?.dataset.id);if(id)navigateReport(id);}
            if(action==='share'&&shareReport)shareReport(shareText(getReport(),base),Number(getReport().id));
            if(action==='copy'){
                const r=getReport(),v=model(r,base),text=`Conquer · Monster-Kampfbericht #${Number(r.id)}\n${v.name} gegen ${v.enemy}\nX:${number(r.target_x)} Y:${number(r.target_y)} · ${r.outcome==='attacker_wins'?'Sieg':'Niederlage'}${v.d.army_power!=null?`\nArmeemacht: ${number(v.d.army_power)} / ${number(v.d.required_power)} benötigt`:''}\n${number(sum(v.troops,'sent'))} Truppen, ${number(sum(v.troops,'dead'))} gefallen, ${number(sum(v.troops,'injured'))} verwundet\nMonster-HP nach / vor Kampf: ${number(v.d.monster_hp_after)} / ${number(v.d.monster_hp_before)}`;
                try{await navigator.clipboard.writeText(text);const b=root.querySelector('[data-monster="copy"]');if(b){b.textContent='✓ Kopiert';b.setAttribute('aria-label','Berichtszusammenfassung kopiert');}}
                catch{toast('Kopieren ist in diesem Browser nicht verfügbar.');}
            }
        });
        detail.addEventListener('click',event=>{if(event.target.closest('[data-monster="close-details"]'))detail.close();});
        detail.addEventListener('close',()=>{
            if(!closing&&token&&history.state?.conquerMonsterReport===token&&history.state.monsterDepth===2)history.back();
            root.querySelector('[data-monster="details"]')?.focus({preventScroll:true});
        });
        if(modal)root.addEventListener('close',()=>{
            if(detail.open){closing=true;detail.close();closing=false;}
            if(!token)return;
            root.classList.remove('monster-report-dialog');delete root.dataset.monsterReport;
            if(history.state?.conquerMonsterReport===token){
                if(location.href===origin)history.go(-Number(history.state.monsterDepth||1));
                else{const state={...history.state};delete state.conquerMonsterReport;delete state.monsterDepth;history.replaceState(state,'',location.href);}
            }
            token=null;
        });
        window.addEventListener('popstate',()=>{
            if(!token)return;
            if(history.state?.conquerMonsterReport!==token){token=null;if(detail.open)detail.close();if(modal&&root.open)root.close();}
            else if(history.state.monsterDepth===1&&detail.open)detail.close();
            else if(history.state.monsterDepth===2&&!detail.open)showDetails();
        });
        return {begin};
    }
    function create(ctx) {
        let active=null;
        const dialog=document.querySelector('#game-dialog');
        const controls=bind({root:dialog,getReport:()=>active,base:ctx.base,toast:ctx.toast,navigateReport:ctx.openReport,shareReport:ctx.shareReport,locateReport:ctx.locateReport});
        const visible=id=>dialog.open&&dialog.dataset.monsterReport===String(id);
        function update(reports) {
            if(!active||!visible(active.id))return;
            const fresh=reports.find(r=>Number(r.id)===Number(active.id));if(!fresh)return;active=fresh;
            const status=dialog.querySelector('[data-report-delivery]');if(status){status.textContent=delivery(fresh);status.classList.toggle('mr-delivered',fresh.reward_delivery==='delivered');}
        }
        async function open(r) {
            active=r;controls.begin();
            const reports=ctx.getState().reports.filter(isMonster),index=reports.findIndex(entry=>Number(entry.id)===Number(r.id));
            ctx.openDialog(render(r,{base:ctx.base,previous:reports[index-1]?.id,next:reports[index+1]?.id,kingdom:ctx.getKingdom?.(),canShare:Boolean(ctx.shareReport)&&r.can_share!==false}));
            dialog.classList.add('combat-report-dialog','monster-report-dialog');dialog.dataset.monsterReport=String(r.id);
            try{const fresh=(await ctx.api('battle/report/'+Number(r.id))).report;if(visible(r.id))update([fresh]);}
            catch(error){if(visible(r.id))ctx.toast(error.message);}
        }
        function onClick(action,button) {
            if(action==='monster-report-back'){ctx.navigate('reports');return true;}
            if(action==='monster-report-delete'){ctx.action('battle/report/'+Number(button.dataset.id)+'/delete',{},'Bericht gelöscht.');return true;}
            return false;
        }
        return {open,update,onClick};
    }
    return {create,render,bind,isMonster,delivery};
})();
