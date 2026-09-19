/* Player battle reports use the saved combat snapshot, including every rally army. */
window.ConquerCombatReport = function ({base, esc, fmt, openDialog, toast, unitName, getState, openReport, shareReport}) {
    const roles = {attacker:'Angreifer', defender:'Verteidiger'};
    const types = {infantry:['Infanterie','knight','♜'], cavalry:['Kavallerie','rider','♞'], ranged:['Bogenschützen','archer','➶']};
    const root = document.getElementById('game-dialog');
    const detail = document.createElement('dialog');
    detail.id = 'combat-details'; detail.className = 'combat-details';
    detail.setAttribute('aria-labelledby','combat-detail-title');
    document.body.append(detail);
    let report = null, combat = null, token = null, origin = '', closing = false, metric = 'strength';
    const number = value => value == null ? '—' : fmt(value);
    const percent = value => value == null ? '—' : `${value >= 0 ? '+' : ''}${Number(value).toLocaleString('de-DE',{maximumFractionDigits:1,minimumFractionDigits:1})} %`;
    const button = (label, action, extra='', classes='') => `<button type="button" class="cr-button ${classes}" data-combat="${action}" ${extra}>${label}</button>`;
    const section = (title, body, extra='') => `<section class="cr-section"><h3>${title}${extra}</h3>${body}</section>`;
    const fold = (title, body, key) => `<details class="cr-section cr-fold" data-cr-fold="${key}"><summary>${title}<span class="cr-chevron" aria-hidden="true">⌄</span></summary>${body}</details>`;
    const avatar = army => `<img class="cr-avatar" src="${base}/assets/art/${['knight','archer','rider'].includes(army.avatar)?army.avatar:'knight'}.png" alt="">`;
    const troopArt = troop => {
        const tier=Number(troop.tier),prefix={infantry:'infantry',ranged:'archer',cavalry:'cavalry'}[troop.type];
        if(prefix&&tier>=1&&tier<=10)return `characters/${prefix}-t${tier}-report-v${prefix==='infantry'&&tier===10?2:1}`;
        return types[troop.type]?.[1]||'knight';
    };
    const troopName = troop => unitName({...getState().troop_defs.find(t=>Number(t.code)===Number(troop.code)),...troop,type:{infantry:1,ranged:2,cavalry:3}[troop.type]||troop.type});
    const coord = army => Number.isFinite(army.x)&&Number.isFinite(army.y)?`X:${army.x} Y:${army.y}`:'Koordinaten nicht gespeichert';
    const identity = (army, role) => `<div class="cr-identity ${role}"><small class="cr-role">${roles[role]}${combat[role].armies.length>1?` · ${combat[role].armies.length} Armeen`:''}</small>${avatar(army)}<div><strong>${esc(army.name)}</strong><small>${esc(coord(army))}</small></div></div>`;
    function fallback(r) {
        const d=r.details, own=d.perspective==='defender'?'defender':'attacker', enemy=own==='attacker'?'defender':'attacker';
        const totals={sent:0,dead:0,injured:0,survived:0,power_lost:null};
        const troops=(d.troops||[]).map(t=>{
            const def=getState().troop_defs.find(u=>Number(u.code)===Number(t.code));
            for(const key of ['sent','dead','injured','survived']) totals[key]+=Number(t[key]||0);
            return {...t,name:def?unitName(def):String(t.code),tier:def?.tier,type:{1:'infantry',2:'ranged',3:'cavalry'}[def?.type]||'infantry'};
        });
        const ownArmy={name:'Deine Armee',troops,totals,equipment:null,talents:null};
        const other={name:d.target_name||'Gegner',troops:[],totals:{},equipment:null,talents:null};
        return {legacy:true,[own]:{armies:[ownArmy],totals,types:{}},[enemy]:{armies:[other],totals:{},types:{}}};
    }
    function compareRow(label, left, right, classes=['','']) {
        return `<tr><td class="${classes[0]}">${left}</td><th scope="row">${label}</th><td class="${classes[1]}">${right}</td></tr>`;
    }
    function overview() {
        const a=combat.attacker,b=combat.defender;
        const win=report.outcome==='attacker_wins', draw=report.outcome==='draw';
        const rows=[['sent','Truppen'],['dead','Gefallen'],['injured','Verwundet'],['survived','Einsatzfähig']];
        return `<div class="cr-versus"><div class="cr-result ${draw?'draw':win?'victory':'defeat'}"><span aria-hidden="true">${win?'♛':'⚔'}</span><strong>${draw?'Unentschieden':win?'Sieg':'Niederlage'}</strong><small>Deine ${report.details.perspective==='defender'?'Verteidigung':'Offensive'}</small></div>${identity(a.armies[0],'attacker')}${identity(b.armies[0],'defender')}</div>
            <table class="cr-compare"><caption class="cr-sr">Angreifer und Verteidiger im Vergleich</caption><thead class="cr-sr"><tr><th>Angreifer</th><th>Wert</th><th>Verteidiger</th></tr></thead><tbody>
            ${compareRow('Truppenmacht verloren',number(a.totals.power_lost),number(b.totals.power_lost),['cr-negative','cr-negative'])}
            ${rows.map(([key,label])=>compareRow(label,number(a.totals[key]),number(b.totals[key]),key==='dead'||key==='injured'?['cr-negative','cr-negative']:['',''])).join('')}
            </tbody></table>`;
    }
    function equipment() {
        return `<div class="cr-columns">${Object.keys(roles).map(role=>`<div class="cr-loadouts">${combat[role].armies.map(army=>`<div><h4>${esc(army.name)}</h4>${army.equipment===null?'<p class="cr-note">Nicht gespeichert.</p>':army.equipment.length?`<div class="cr-equipment">${army.equipment.map(item=>`<div class="cr-item grade-${['normal','uncommon','rare','epic','legendary'].includes(item.grade)?item.grade:'normal'}">${/^[a-z0-9_/-]+\.(png|svg|webp)$/i.test(item.icon)?`<img src="${base}/assets/art/items/${esc(item.icon)}" alt="" loading="lazy">`:'<span aria-hidden="true">✦</span>'}<strong>${esc(item.name)}</strong><small>St. ${number(item.level)}</small></div>`).join('')}</div>`:'<p class="cr-note">Keine Relikte ausgerüstet.</p>'}</div>`).join('')}</div>`).join('')}</div>`;
    }
    function talents() {
        return `<div class="cr-columns">${Object.keys(roles).map(role=>`<div class="cr-loadouts">${combat[role].armies.map(army=>`<div><h4>${esc(army.name)}</h4><p class="cr-hunter">✦ Hunter · Stufe ${number(army.hunter_level)}</p>${army.talents===null?'<p class="cr-note">Nicht gespeichert.</p>':army.talents.length?`<ul class="cr-talents">${army.talents.map(t=>`<li><span>${esc(t.name)}</span><b>${number(t.rank)}/5</b></li>`).join('')}</ul>`:'<p class="cr-note">Keine Talente vergeben.</p>'}</div>`).join('')}</div>`).join('')}</div>`;
    }
    function power() {
        return `<p class="cr-note">${metric==='strength'?'Kampfstärke nach Boni':'Anzahl entsandter Truppen'} · Angreifer rot / Verteidiger blau</p><div class="cr-power-rows">${Object.entries(types).map(([type,[name]])=>{
            const a=combat.attacker.types[type]?.[metric],b=combat.defender.types[type]?.[metric],sum=Number(a)+Number(b);
            return `<div class="cr-power-row"><strong>${name}</strong><div><div class="cr-bar ${sum>0?'':'is-empty'}" aria-hidden="true"><span style="width:${sum>0?Math.max(0,Math.min(100,Number(a)/sum*100)):0}%"></span></div><div class="cr-bar-values"><span>${number(a)}</span><span>${number(b)}</span></div></div></div>`;
        }).join('')}</div>`;
    }
    function bonuses() {
        return `<table class="cr-compare cr-bonuses"><caption class="cr-sr">Boni je Truppentyp: Angreifer links, Verteidiger rechts</caption><thead><tr><th>Angreifer</th><th>Bonus</th><th>Verteidiger</th></tr></thead><tbody>${Object.entries(types).map(([type,[name]])=>Object.entries({atk:'Angriff',def:'Verteidigung',hp:'Lebenspunkte'}).map(([stat,label])=>{
            const a=combat.attacker.types[type]?.bonuses[stat],b=combat.defender.types[type]?.bonuses[stat];
            const comparable=a!=null&&b!=null&&a!==b;
            return compareRow(`${name}<br>${label}`,percent(a),percent(b),comparable?[a>b?'cr-positive':'cr-negative',b>a?'cr-positive':'cr-negative']:['','']);
        }).join('')).join('')}</tbody></table><p class="cr-note">Bei mehreren Armeen sind die Boni nach Truppenzahl gewichtet. Ohne diesen Truppentyp: —.</p>`;
    }
    function loot() {
        const resources=report.details.resources_lost||report.details.loot||{};
        return `<div class="cr-resources">${Object.entries({food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'}).map(([key,label])=>`<div><img src="${base}/assets/art/ui-resources/${key}.png" alt=""><span>${label}<strong>${number(resources[key]||0)}</strong></span></div>`).join('')}</div>`;
    }
    function neighbors() {
        const reports=(getState()?.reports||[]).filter(item=>['city','rally'].includes(item.details?.battle_kind));
        const index=reports.findIndex(item=>Number(item.id)===Number(report.id));
        return index<0?{previous:null,next:null}:{previous:reports[index-1]?.id||null,next:reports[index+1]?.id||null};
    }
    function shareText() {
        const result=report.outcome==='attacker_wins'?'Sieg':report.outcome==='defender_wins'?'Niederlage':'Unentschieden';
        return `⚔ Spieler-Kampfbericht #${Number(report.id)} · ${result} · X:${Number(report.target_x)} Y:${Number(report.target_y)} · ${combat.attacker.armies.map(army=>army.name).join(', ')} gegen ${combat.defender.armies.map(army=>army.name).join(', ')} · ${number(combat.attacker.totals.sent)} gegen ${number(combat.defender.totals.sent)} Truppen`;
    }
    function render() {
        const when=new Date(String(report.created_at).replace(' ','T')+'Z'),near=neighbors();
        openDialog(`<h2>Spieler-Kampfbericht</h2><div class="combat-report">
            <div class="cr-scroll"><div class="cr-banner"><div><small>${report.details.battle_kind==='rally'?'Gemeinsamer Angriff':'Stadtgefecht'} · #${Number(report.id)}</small><strong>X:${Number(report.target_x)} Y:${Number(report.target_y)}</strong></div><time>${Number.isNaN(when.getTime())?esc(report.created_at):when.toLocaleString('de-DE')}</time></div>
            ${combat.legacy?'<p class="cr-notice">Älterer Bericht: Gegneraufstellung und damalige Boni wurden noch nicht gespeichert.</p>':''}
            ${section('Kampfübersicht',overview())}${section(report.details.perspective==='defender'?'Verlorene Ressourcen':'Deine Beute',loot())}
            ${section('Truppenvergleich',`<div data-cr-power>${power()}</div>`,button('⇄','metric','aria-label="Zwischen Kampfstärke und Truppenzahl wechseln"'))}
            ${fold('Reliktvergleich',equipment(),'equipment')}${fold('Hunter-Talente',talents(),'talents')}${fold('Werteboni',bonuses(),'bonuses')}</div>
            <footer class="cr-footer">${button('‹','previous',`data-id="${near.previous||''}" aria-label="Neuerer Kampfbericht" ${near.previous?'':'disabled'}`,'cr-report-arrow')}${button('<span class="cr-wide-label">Kampfdetails</span><span class="cr-short-label">Details</span>','details','','cr-primary')}${shareReport&&report.can_share!==false?button('↗ <span class="cr-action-label">Teilen</span>','share','aria-label="Bericht teilen"','cr-share-button'):''}${button('⧉ <span class="cr-action-label">Kopieren</span>','copy','aria-label="Berichtszusammenfassung kopieren"','cr-copy-button')}${button('›','next',`data-id="${near.next||''}" aria-label="Älterer Kampfbericht" ${near.next?'':'disabled'}`,'cr-report-arrow')}</footer></div>`);
        root.classList.add('combat-report-dialog');
    }
    function armyDetails(army,role,index) {
        return `<details class="cr-army" ${index===0?'open':''}><summary>${avatar(army)}<span><strong>${esc(army.name)}</strong><small>${number(army.totals.sent)} Truppen · ${number(army.totals.power_lost)} Truppenmacht verloren</small></span><b class="cr-chevron" aria-hidden="true">⌄</b></summary>
            <div class="cr-troop-list">${army.troops.length?army.troops.map(t=>`<article class="cr-troop"><div class="cr-troop-name"><img src="${base}/assets/art/${troopArt(t)}.png" alt=""><span><strong>${esc(troopName(t))}</strong><small>Tier ${number(t.tier)} · ${number(t.sent)} Truppen</small></span></div><dl>${[['dead','Gefallen'],['injured','Verwundet'],['survived','Einsatzfähig']].map(([key,label])=>`<div><dt>${label}</dt><dd class="${key==='survived'?'':'cr-negative'}">${number(t[key])}</dd></div>`).join('')}</dl></article>`).join(''):'<p class="cr-note">Keine Truppenaufstellung vorhanden.</p>'}</div></details>`;
    }
    function showDetails() {
        detail.innerHTML=`<header class="cr-detail-heading"><h2 id="combat-detail-title" tabindex="-1">Kampfdetails</h2>${button('×','close-details','aria-label="Kampfdetails schließen"')}</header><div class="cr-detail-scroll">
            <details class="cr-rules"><summary>Kampfwertung & Rückkehr</summary><p>Die Kampfstärke berücksichtigt Angriff, Verteidigung und Lebenspunkte mit den damals aktiven Boni. Mauerbonus: ${percent(combat.wall_defense_pct)}; Verteidigervorteil: ${percent(combat.defender_advantage_pct)}.</p><p>Truppenmacht verloren = Macht der gefallenen und verwundeten Truppen. Leicht Verwundete und Fähigkeitenauslösungen werden derzeit nicht separat erfasst. Abschüsse je Einheit werden nicht zugeordnet.</p><p>${report.details.perspective==='defender'?'Einsatzfähige Verteidiger bleiben in der Stadt.':'Einsatzfähige Truppen und Beute kehren mit dem Rückmarsch zurück.'} Verwundete werden im Hospital versorgt.</p></details>
            ${Object.keys(roles).map(role=>`<section class="cr-detail-side"><h3 class="cr-ribbon ${role}">${roles[role]}</h3>${combat[role].armies.map((army,i)=>armyDetails(army,role,i)).join('')}</section>`).join('')}
            ${report.details.wall?section('Stadtmauer',`<p class="cr-note">Haltbarkeit: ${number(report.details.wall.before)} → ${number(report.details.wall.after)} / ${number(report.details.wall.max)}${report.details.wall.relocated?'<br>Die Stadt wurde nach dem Mauerbruch versetzt.':''}</p>`):''}
            </div>`;
        if(!detail.open)detail.showModal();
        detail.querySelector('h2').focus({preventScroll:true});
    }
    function open(r) {
        report=r;combat=r.details.combat||fallback(r);metric='strength';
        if(!token){token=`battle-${r.id}-${Date.now()}`;origin=location.href;history.pushState({...history.state,conquerCombat:token,combatDepth:1},'',location.href);}
        render();
    }
    async function copy() {
        const text=`Conquer · Kampfbericht #${Number(report.id)}\n${combat.attacker.armies.map(a=>a.name).join(', ')} gegen ${combat.defender.armies.map(a=>a.name).join(', ')}\nX:${Number(report.target_x)} Y:${Number(report.target_y)} · ${report.outcome==='attacker_wins'?'Sieg':report.outcome==='defender_wins'?'Niederlage':'Unentschieden'} (${report.details.perspective==='defender'?'Verteidigung':'Angriff'})\n`+Object.keys(roles).map(role=>`${roles[role]}: ${number(combat[role].totals.sent)} Truppen, ${number(combat[role].totals.dead)} gefallen, ${number(combat[role].totals.injured)} verwundet`).join('\n');
        try { await navigator.clipboard.writeText(text); const copyButton=root.querySelector('[data-combat="copy"]');if(copyButton){copyButton.textContent='✓ Kopiert';copyButton.setAttribute('aria-label','Berichtszusammenfassung kopiert');} }
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
