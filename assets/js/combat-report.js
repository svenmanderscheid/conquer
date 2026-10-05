/* Player battle reports use the saved combat snapshot, including every rally army. */
window.ConquerCombatReport = function ({base, esc, fmt, openDialog, toast, unitName, getState, openReport, shareReport}) {
    const tr=(key,parameters={})=>window.ConquerLocale.t('report.'+key,parameters), authored=value=>window.ConquerLocale.text(value);
    const roles = {attacker:'Angreifer', defender:'Verteidiger'};
    const types = {infantry:['Infanterie','knight','♜'], cavalry:['Kavallerie','rider','♞'], ranged:['Bogenschützen','archer','➶']};
    const root = document.getElementById('game-dialog');
    const detail = document.createElement('dialog');
    detail.id = 'combat-details'; detail.className = 'combat-details';
    detail.setAttribute('aria-labelledby','combat-detail-title');
    document.body.append(detail);
    let report = null, combat = null, token = null, origin = '', closing = false, metric = 'strength';
    const number = value => value == null ? '—' : fmt(value);
    const percent = value => value == null ? '—' : `${value >= 0 ? '+' : ''}${Number(value).toLocaleString(window.ConquerLocale?.locale??'en',{maximumFractionDigits:1,minimumFractionDigits:1})} %`;
    const button = (label, action, extra='', classes='') => `<button type="button" class="cr-button ${classes}" data-combat="${action}" ${extra}>${label}</button>`;
    const section = (title, body, extra='') => `<section class="cr-section"><h3>${title}${extra}</h3>${body}</section>`;
    const fold = (title, body, key) => `<details class="cr-section cr-fold" data-cr-fold="${key}" open><summary>${title}<span class="cr-chevron" aria-hidden="true">⌄</span></summary>${body}</details>`;
    const luck = value => {
        if(value==null||!Number.isFinite(Number(value)))return '';
        const amount=Math.max(-10,Math.min(10,Number(value))),position=(amount+10)*5;
        return `<div class="cr-luck" role="img" aria-label="Kampfglück ${percent(amount)}"><div class="cr-luck-heading"><strong>Glück</strong><span class="${amount>0?'cr-positive':amount<0?'cr-negative':''}">${percent(amount)}</span></div><div class="cr-luck-track"><span class="cr-luck-zero" aria-hidden="true"></span><span class="cr-luck-marker" style="left:${position}%" aria-hidden="true"></span></div><div class="cr-luck-scale" aria-hidden="true"><span>−10 %</span><span>0 %</span><span>+10 %</span></div></div>`;
    };
    const avatar = army => `<img class="cr-avatar" src="${base}/assets/art/${['knight','archer','rider'].includes(army.avatar)?army.avatar:'knight'}.png" alt="">`;
    const troopArt = troop => {
        const tier=Number(troop.tier),prefix={infantry:'guardian',ranged:'fire-archer',cavalry:'shadow-rider'}[troop.type];
        if(prefix&&tier>=1&&tier<=10)return `characters/fantasy-troops-v3/${prefix}-t${tier}-ui.webp`;
        return (types[troop.type]?.[1]||'knight')+'.png';
    };
    const troopName = troop => unitName({...getState().troop_defs.find(t=>Number(t.code)===Number(troop.code)),...troop,type:{infantry:1,ranged:2,cavalry:3}[troop.type]||troop.type});
    const coord = army => Number.isFinite(army.x)&&Number.isFinite(army.y)?`X:${army.x} Y:${army.y}`:'Koordinaten nicht gespeichert';
    const identity = (army, role) => `<div class="cr-identity ${role}"><small class="cr-role">${roles[role]}${combat[role].armies.length>1?` · ${combat[role].armies.length} Armeen`:''}</small>${avatar(army)}<div><strong data-user-content>${esc(army.name)}</strong><small>${esc(coord(army))}</small></div></div>`;
    function fallback(r) {
        const d=r.details, own=d.perspective==='defender'?'defender':'attacker', enemy=own==='attacker'?'defender':'attacker';
        const totals={sent:0,dead:0,injured:0,survived:0,power_lost:null};
        const troops=(d.troops||[]).map(t=>{
            const def=getState().troop_defs.find(u=>Number(u.code)===Number(t.code));
            for(const key of ['sent','dead','injured','survived']) totals[key]+=Number(t[key]||0);
            return {...t,name:def?unitName(def):String(t.code),tier:def?.tier,type:{1:'infantry',2:'ranged',3:'cavalry'}[def?.type]||'infantry'};
        });
        const ownArmy={name:authored('Deine Armee'),troops,totals,equipment:null,talents:null};
        const other={name:d.target_name||authored('Gegner'),troops:[],totals:{},equipment:null,talents:null};
        return {legacy:true,[own]:{armies:[ownArmy],totals,types:{}},[enemy]:{armies:[other],totals:{},types:{}}};
    }
    function compareRow(label, left, right, classes=['','']) {
        return `<tr><td class="${classes[0]}">${left}</td><th scope="row">${label}</th><td class="${classes[1]}">${right}</td></tr>`;
    }
    function comparison(left,right,lowerIsBetter=false) {
        if(left==null||right==null||!Number.isFinite(Number(left))||!Number.isFinite(Number(right))||Number(left)===Number(right))return ['',''];
        const leftWins=lowerIsBetter?Number(left)<Number(right):Number(left)>Number(right);
        return leftWins?['cr-positive','cr-negative']:['cr-negative','cr-positive'];
    }
    function overview() {
        const a=combat.attacker,b=combat.defender;
        const win=report.details.battle_kind==='territory'&&report.details.perspective==='defender'?report.outcome==='defender_wins':report.outcome==='attacker_wins', draw=report.outcome==='draw';
        return `<div class="cr-versus"><div class="cr-result ${draw?'draw':win?'victory':'defeat'}"><span aria-hidden="true">${win?'♛':'⚔'}</span><strong>${draw?'Unentschieden':win?'Sieg':'Niederlage'}</strong><small>Deine ${report.details.perspective==='defender'?'Verteidigung':'Offensive'}</small></div>${identity(a.armies[0],'attacker')}${identity(b.armies[0],'defender')}</div>`;
    }
    function battleTotals() {
        const a=combat.attacker,b=combat.defender;
        const rows=[['sent','Truppen'],['dead',window.ConquerLocale.t('battle.preview.fallen')],['injured','Verwundet'],['survived','Einsatzfähig']];
        return `<table class="cr-compare"><caption class="cr-sr">Angreifer und Verteidiger im Vergleich</caption><thead><tr><th>Angreifer</th><th>Wert</th><th>Verteidiger</th></tr></thead><tbody>
            ${compareRow('Truppenmacht verloren',number(a.totals.power_lost),number(b.totals.power_lost),comparison(a.totals.power_lost,b.totals.power_lost,true))}
            ${rows.map(([key,label])=>compareRow(label,number(a.totals[key]),number(b.totals[key]),comparison(a.totals[key],b.totals[key],key==='dead'||key==='injured'))).join('')}
            </tbody></table>`;
    }
    function equipment() {
        return `<div class="cr-columns">${Object.keys(roles).map(role=>`<div class="cr-loadouts">${combat[role].armies.map(army=>`<div><h4 data-user-content>${esc(army.name)}</h4>${army.equipment===null?'<p class="cr-note">Nicht gespeichert.</p>':army.equipment.length?`<div class="cr-equipment">${army.equipment.map(item=>`<div class="cr-item grade-${['normal','uncommon','rare','epic','legendary'].includes(item.grade)?item.grade:'normal'}">${/^[a-z0-9_/-]+\.(png|svg|webp)$/i.test(item.icon)?`<img src="${base}/assets/art/items/${esc(item.icon)}" alt="" loading="lazy">`:'<span aria-hidden="true">✦</span>'}<strong>${esc(item.name)}</strong><small>St. ${number(item.level)}</small></div>`).join('')}</div>`:'<p class="cr-note">Keine Relikte ausgerüstet.</p>'}</div>`).join('')}</div>`).join('')}</div>`;
    }
    function talents() {
        return `<div class="cr-columns">${Object.keys(roles).map(role=>`<div class="cr-loadouts">${combat[role].armies.map(army=>`<div><h4 data-user-content>${esc(army.name)}</h4><p class="cr-hunter">✦ Hunter · Stufe ${number(army.hunter_level)}</p>${army.talents===null?'<p class="cr-note">Nicht gespeichert.</p>':army.talents.length?`<ul class="cr-talents">${army.talents.map(t=>`<li><span>${esc(t.name_key?ConquerLocale.t(t.name_key):t.name)}</span><b>${number(t.rank)}/${number(t.max_rank??5)}</b></li>`).join('')}</ul>`:'<p class="cr-note">Keine Talente vergeben.</p>'}</div>`).join('')}</div>`).join('')}</div>`;
    }
    function power() {
        return `<p class="cr-note">${metric==='strength'?'Kampfstärke nach Boni':'Anzahl entsandter Truppen'} · Angreifer rot / Verteidiger blau</p><div class="cr-power-rows">${Object.entries(types).map(([type,[name]])=>{
            const a=combat.attacker.types[type]?.[metric],b=combat.defender.types[type]?.[metric],sum=Number(a)+Number(b);
            const classes=comparison(a,b);
            return `<div class="cr-power-row"><strong>${name}</strong><div><div class="cr-bar ${sum>0?'':'is-empty'}" aria-hidden="true"><span style="width:${sum>0?Math.max(0,Math.min(100,Number(a)/sum*100)):0}%"></span></div><div class="cr-bar-values"><span class="${classes[0]}">${number(a)}</span><span class="${classes[1]}">${number(b)}</span></div></div></div>`;
        }).join('')}</div>`;
    }
    function bonuses() {
        return `<table class="cr-compare cr-bonuses"><caption class="cr-sr">Boni je Truppentyp: Angreifer links, Verteidiger rechts</caption><thead><tr><th>Angreifer</th><th>Bonus</th><th>Verteidiger</th></tr></thead><tbody>${Object.entries(types).map(([type,[name]])=>Object.entries({atk:'Angriff',def:'Verteidigung',hp:'Lebenspunkte'}).map(([stat,label])=>{
            const a=combat.attacker.types[type]?.bonuses?.[stat]??0,b=combat.defender.types[type]?.bonuses?.[stat]??0;
            return compareRow(`${name}<br>${label}`,percent(a),percent(b),comparison(a,b));
        }).join('')).join('')}</tbody></table><table class="cr-compare cr-combat-factors"><caption class="cr-sr">Gesamte Kampfstärke von Angreifer und Verteidiger</caption><thead><tr><th>Angreifer</th><th>Kampfstatistik</th><th>Verteidiger</th></tr></thead><tbody>${compareRow('Wirksame Gesamtstärke',number(combat.attacker.score),number(combat.defender.score),comparison(combat.attacker.score,combat.defender.score))}</tbody></table><dl class="cr-stat-list"><div><dt>Kampfglück Angreifer</dt><dd class="${Number(combat.luck_percent)>0?'cr-positive':Number(combat.luck_percent)<0?'cr-negative':''}">${percent(combat.luck_percent??0)}</dd></div><div><dt>Mauerbonus Verteidiger</dt><dd>${percent(combat.wall_defense_pct??0)}</dd></div><div><dt>Verteidigervorteil</dt><dd>${percent(combat.defender_advantage_pct??0)}</dd></div><div><dt>Angreiferstärke vor Glück</dt><dd>${number(combat.attacker_score_before_luck)}</dd></div></dl><p class="cr-note">Alle neun Truppenboni und alle beidseitig vergleichbaren Kampfstatistiken werden gezeigt. Grün ist der bessere, Rot der schlechtere Wert; Gleichstände bleiben neutral. Bei mehreren Armeen sind Boni nach Truppenzahl gewichtet.</p>`;
    }
    function loot() {
        const resources=report.details.resources_lost||report.details.loot||{};
        return `<div class="cr-resources">${Object.entries({food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'}).map(([key,label])=>`<div><img src="${base}/assets/art/ui-resources/${key}.png" alt=""><span>${label}<strong>${number(resources[key]||0)}</strong></span></div>`).join('')}</div>`;
    }
    function neighbors() {
        const reports=(getState()?.reports||[]).filter(item=>['city','rally','territory'].includes(item.details?.battle_kind));
        const index=reports.findIndex(item=>Number(item.id)===Number(report.id));
        return index<0?{previous:null,next:null}:{previous:reports[index-1]?.id||null,next:reports[index+1]?.id||null};
    }
    function shareText() {
        const perspectiveWin=report.details.battle_kind==='territory'&&report.details.perspective==='defender'?report.outcome==='defender_wins':report.outcome==='attacker_wins';const result=authored(report.outcome==='draw'?'Unentschieden':perspectiveWin?'Sieg':'Niederlage');
        return `⚔ ${tr(report.details.battle_kind==='territory'?'territory_title':'player_title')} #${Number(report.id)} · ${result} · X:${Number(report.target_x)} Y:${Number(report.target_y)} · ${tr('versus',{attacker:combat.attacker.armies.map(army=>army.name).join(', '),defender:combat.defender.armies.map(army=>army.name).join(', ')})} · ${tr('troops_versus',{attacker:number(combat.attacker.totals.sent),defender:number(combat.defender.totals.sent)})}`;
    }
    function render() {
        const when=new Date(String(report.created_at).replace(' ','T')+'Z'),near=neighbors();
        openDialog(`<h2>${report.details.battle_kind==='territory'?'Gebiets-Kampfbericht':'Spieler-Kampfbericht'}</h2><div class="combat-report">
            <div class="cr-scroll"><div class="cr-banner"><div><small>${report.details.battle_kind==='territory'?`<span translate="no">${esc(report.details.target_name||'Gebietseroberung')}</span>`:report.details.battle_kind==='rally'?'Gemeinsamer Angriff':'Stadtgefecht'} · #${Number(report.id)}</small><strong>X:${Number(report.target_x)} Y:${Number(report.target_y)}</strong></div><time data-i18n-ignore>${Number.isNaN(when.getTime())?esc(report.created_at):when.toLocaleString(window.ConquerLocale?.locale??'en')}</time></div>
            <section class="cr-section cr-battle-hero" aria-label="Kampfübersicht">${overview()}</section>${report.details.battle_kind==='territory'?section('Gebiet und Belohnungen',`<p><span translate="no">${esc(report.details.target_name||'Gebietseroberung')}</span>${report.details.objective?' · '+esc(({gate:'Tor',arsenal:'Arsenal',throne:'Thron'})[report.details.objective]||report.details.objective):''}</p><p>Persönliche Ansprüche stehen unter Allianzgebiete → Eroberungsverlauf bereit.</p>`):section(report.details.perspective==='defender'?'Verlorene Ressourcen':'Deine Beute',loot())}
            ${combat.legacy?'<p class="cr-notice">Älterer Bericht: Gegneraufstellung und damalige Boni wurden noch nicht gespeichert.</p>':''}${section(esc(tr('troop_balance')),battleTotals())}${luck(combat.luck_percent)}
            ${section('Truppenvergleich',`<div data-cr-power>${power()}</div>`,button('⇄','metric','aria-label="Zwischen Kampfstärke und Truppenzahl wechseln"'))}
            ${fold('Reliktvergleich',equipment(),'equipment')}${fold('Hunter-Talente',talents(),'talents')}${fold('Werteboni',bonuses(),'bonuses')}</div>
            <footer class="cr-footer">${button('‹','previous',`data-id="${near.previous||''}" aria-label="Neuerer Kampfbericht" ${near.previous?'':'disabled'}`,'cr-report-arrow')}${button('<span class="cr-wide-label">Kampfdetails</span><span class="cr-short-label">Details</span>','details','','cr-primary')}${shareReport&&report.can_share!==false?button('↗ <span class="cr-action-label">Teilen</span>','share','aria-label="Bericht teilen"','cr-share-button'):''}${button('⧉ <span class="cr-action-label">Kopieren</span>','copy','aria-label="Berichtszusammenfassung kopieren"','cr-copy-button')}${button('›','next',`data-id="${near.next||''}" aria-label="Älterer Kampfbericht" ${near.next?'':'disabled'}`,'cr-report-arrow')}</footer></div>`,{historyManaged:true});
        root.classList.add('combat-report-dialog');
    }
    function armyDetails(army,role,index) {
        return `<details class="cr-army" open><summary>${avatar(army)}<span><strong data-user-content>${esc(army.name)}</strong><small>${number(army.totals.sent)} Truppen · ${number(army.totals.power_lost)} Truppenmacht verloren</small></span><b class="cr-chevron" aria-hidden="true">⌄</b></summary>
            <div class="cr-troop-list">${army.troops.length?army.troops.map(t=>`<article class="cr-troop"><div class="cr-troop-name"><img class="troop-tier-frame" data-troop-tier="${Number(t.tier)||0}" src="${base}/assets/art/${troopArt(t)}" alt=""><span><strong>${esc(troopName(t))}</strong><small>Tier ${number(t.tier)} · ${number(t.sent)} Truppen</small></span></div><dl>${[['dead',window.ConquerLocale.t('battle.preview.fallen')],['injured','Verwundet'],['survived','Einsatzfähig']].map(([key,label])=>`<div><dt>${label}</dt><dd class="${key==='survived'?'':'cr-negative'}">${number(t[key])}</dd></div>`).join('')}</dl></article>`).join(''):'<p class="cr-note">Keine Truppenaufstellung vorhanden.</p>'}</div></details>`;
    }
    function showDetails() {
        detail.innerHTML=`<header class="cr-detail-heading"><h2 id="combat-detail-title" tabindex="-1">Kampfdetails</h2>${button('×','close-details','aria-label="Kampfdetails schließen"')}</header><div class="cr-detail-scroll">
            <details class="cr-rules" open><summary>Kampfwertung & Rückkehr</summary><p>Die Kampfstärke berücksichtigt Angriff, Verteidigung und Lebenspunkte mit den damals aktiven Boni. Mauerbonus: ${percent(combat.wall_defense_pct)}; Verteidigervorteil: ${percent(combat.defender_advantage_pct)}.</p><p>Truppenmacht verloren = Macht der gefallenen und verwundeten Truppen. Leicht Verwundete und Fähigkeitenauslösungen werden derzeit nicht separat erfasst. Abschüsse je Einheit werden nicht zugeordnet.</p><p>${report.details.battle_kind==='territory'?(report.details.perspective==='defender'?(report.outcome==='defender_wins'?'Einsatzfähige Verteidiger bleiben als Garnison im Gebiet.':'Einsatzfähige Verteidiger kehren mit dem Rückmarsch zurück.'):'Einsatzfähige Angreifer kehren mit dem Rückmarsch zurück.'):report.details.perspective==='defender'?'Einsatzfähige Verteidiger bleiben in der Stadt.':'Einsatzfähige Truppen und Beute kehren mit dem Rückmarsch zurück.'} Verwundete werden im Hospital versorgt.</p></details>
            ${Object.keys(roles).map(role=>`<section class="cr-detail-side"><h3 class="cr-ribbon ${role}">${roles[role]}</h3>${combat[role].armies.map((army,i)=>armyDetails(army,role,i)).join('')}</section>`).join('')}
            ${report.details.wall?section('Stadtmauer',`<p class="cr-note">Haltbarkeit: ${number(report.details.wall.before)} → ${number(report.details.wall.after)} / ${number(report.details.wall.max)}${report.details.wall.relocated?'<br>Die Stadt wurde nach dem Mauerbruch versetzt.':''}</p>`):''}
            </div>`;
        if(!detail.open)detail.showModal();
        detail.querySelector('h2').focus({preventScroll:true});
    }
    function open(r) {
        report=r;combat=r.details.combat||fallback(r);metric='strength';
        // Territory battles may have only NPC defenders. Keep unknown NPC loss
        // values unknown; an absent player army must not make the report crash.
        if(r.details.battle_kind==='territory'){
            combat=structuredClone(combat);
            for(const role of ['attacker','defender']){
                const side=combat[role];
                if(side.armies?.length)continue;
                const npc=role==='defender'?Number(combat.npc_troops||r.details.npc_troops||0):0;
                const totals={sent:npc,dead:null,injured:null,survived:null,power_lost:null};
                side.armies=[{name:npc?tr('npc_army',{name:r.details.target_name||authored('Gebiet')}):authored(role==='defender'?'Keine stationierte Garnison':'Keine entsandte Armee'),x:Number(r.target_x),y:Number(r.target_y),troops:[],totals,equipment:null,talents:null,hunter_level:null}];
                side.totals=totals;
                // NPC count and total score are known; its unit-type split was not saved.
                if(npc)side.types={};
            }
        }
        if(!token){token=`battle-${r.id}-${Date.now()}`;origin=location.href;history.pushState({...history.state,conquerCombat:token,combatDepth:1},'',location.href);}
        render();
    }
    async function copy() {
        const result=authored(report.outcome==='draw'?'Unentschieden':report.outcome===(report.details.battle_kind==='territory'&&report.details.perspective==='defender'?'defender_wins':'attacker_wins')?'Sieg':'Niederlage');
        const text=`Union of Kingdoms · ${tr('title')} #${Number(report.id)}\n${tr('versus',{attacker:combat.attacker.armies.map(a=>a.name).join(', '),defender:combat.defender.armies.map(a=>a.name).join(', ')})}\nX:${Number(report.target_x)} Y:${Number(report.target_y)} · ${result} (${authored(report.details.perspective==='defender'?'Verteidigung':'Angriff')})\n`+Object.keys(roles).map(role=>`${authored(roles[role])}: ${tr('army_summary',{sent:number(combat[role].totals.sent),dead:number(combat[role].totals.dead),injured:number(combat[role].totals.injured)})}`).join('\n');
        try { await navigator.clipboard.writeText(text); const copyButton=root.querySelector('[data-combat="copy"]');if(copyButton){copyButton.textContent=tr('copied');copyButton.setAttribute('aria-label',authored('Berichtszusammenfassung kopiert'));} }
        catch { toast('Kopieren ist in diesem Browser nicht verfügbar.'); }
    }
    root.addEventListener('click',e=>{
        const action=e.target.closest('[data-combat]')?.dataset.combat;
        if(action==='details'){history.pushState({...history.state,combatDepth:2},'',location.href);showDetails();}
        if(action==='metric'){metric=metric==='strength'?'count':'strength';root.querySelector('[data-cr-power]').innerHTML=power();}
        if((action==='previous'||action==='next')&&openReport){const id=Number(e.target.closest('[data-id]')?.dataset.id);if(id)openReport(id);}
        if(action==='share'&&shareReport&&report.can_share!==false)shareReport(shareText(),Number(report.id));
        if(action==='copy')copy();
    });
    detail.addEventListener('click',e=>{if(e.target.closest('[data-combat="close-details"]'))detail.close();});
    detail.addEventListener('close',()=>{
        if(!closing&&token&&history.state?.conquerCombat===token&&history.state.combatDepth===2)history.back();
        root.querySelector('[data-combat="details"]')?.focus({preventScroll:true});
    });
    root.addEventListener('close',()=>{
        root.classList.remove('combat-report-dialog');
        if(detail.open){closing=true;detail.close();closing=false;}
        if(!token)return;
        if(history.state?.conquerCombat===token){
            if(location.href===origin)history.go(-Number(history.state.combatDepth||1));
            else {const state={...history.state};delete state.conquerCombat;delete state.combatDepth;history.replaceState(state,'',location.href);}
        }
        token=null;
    });
    window.addEventListener('popstate',()=>{
        if(!token)return;
        if(history.state?.conquerCombat!==token){token=null;if(detail.open)detail.close();root.close();}
        else if(history.state.combatDepth===1&&detail.open)detail.close();
        else if(history.state.combatDepth===2&&!detail.open)showDetails();
    });
    return {open};
};
