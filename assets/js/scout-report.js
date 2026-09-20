/* Historical scout intelligence, shared by the mailbox and report shortcuts. */
window.ConquerScoutReport = function(ctx) {
    'use strict';
    const {base,esc,fmt,openDialog,getState,getKingdom,unitName}=ctx;
    const number=value=>value==null||!Number.isFinite(Number(value))?'—':fmt(Number(value));
    const percent=value=>value==null?'—':`${Number(value)>=0?'+':''}${Number(value).toLocaleString('de-DE',{maximumFractionDigits:2})} %`;
    const resources={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
    const branchDefaults=[{code:'attack',name:'Angriff',icon:'shadow-blade.png'},{code:'defense',name:'Verteidigung',icon:'iron-shield.png'},{code:'gather',name:'Sammler',icon:'woodcutter.png'},{code:'hunter',name:'Jäger',icon:'hunters-bow.png'}];
    const section=(title,body,kind)=>`<section class="sr-section sr-${kind}"><h3>${title}</h3>${body}</section>`;
    const note=text=>`<p class="sr-note">${esc(text)}</p>`;
    const image=(file,cls='')=>`<img class="${cls}" src="${esc(base+'/assets/art/'+file)}" alt="" loading="lazy">`;
    const itemArt=icon=>{const path=String(icon||'').replace(/^assets\/art\/items\//,'');return /^[a-zA-Z0-9_/-]+\.(png|svg|webp)$/.test(path)&&!path.includes('..')?'items/'+path:'items/compass.svg';};
    const stat=(label,value)=>`<div><dt>${esc(label)}</dt><dd>${value}</dd></div>`;
    function troopList(rows,empty) {
        if(rows==null)return note('Truppen wurden in diesem Bericht nicht erfasst.');
        const entries=(Array.isArray(rows)?rows:Object.entries(rows).map(([code,count])=>({code,count}))).filter(t=>Number(t.count??t.sent)>0);
        if(!entries.length)return note(empty);
        const cards=entries.map(t=>{
            const def=getState()?.troop_defs?.find(u=>Number(u.code)===Number(t.code)),tier=Number(t.tier??def?.tier),type=t.type??def?.type;
            const prefix=({1:'infantry',2:'archer',3:'cavalry',infantry:'infantry',ranged:'archer',cavalry:'cavalry'})[type];
            const art=prefix&&tier>=1&&tier<=10?`characters/tier-colors-v1/${prefix}-t${tier}-report.webp`:'hud/expeditions.svg';
            const name=t.name||(def?unitName(def):'Truppe #'+t.code);
            return `<article class="sr-troop"><div class="sr-portrait troop-tier-frame" data-troop-tier="${Number(tier)||0}">${image(art)}<span class="sr-tier">${tier?'T'+tier:'?'}</span><b>${number(t.count??t.sent)}</b></div><strong>${esc(name)}</strong></article>`;
        }).join('');
        return `<div class="sr-troop-grid">${cards}</div><p class="sr-total">Gesamt <strong>${number(entries.reduce((sum,t)=>sum+Number(t.count??t.sent),0))}</strong></p>`;
    }
    function supplies(d) {
        const cards=Object.entries(resources).map(([key,name])=>{
            const total=d.resources?.[key],protectedAmount=d.protected_resources?.[key];
            const vulnerable=total!=null&&protectedAmount!=null?Math.max(0,Number(total)-Number(protectedAmount)):null;
            return `<article class="sr-resource">${image('ui-resources/'+key+'.png')}<strong>${name}</strong><b>${number(vulnerable)}</b><dl>${stat('Gesichtet',number(total))}${stat('Geschützt',number(protectedAmount))}</dl></article>`;
        }).join('');
        return section('Plünderbare Vorräte',`<div class="sr-resource-grid">${cards}</div>${note('Gesichteter Vorrat abzüglich Schutz. Stand zum Zeitpunkt der Aufklärung.')}`,'supplies');
    }
    function mastery(d) {
        if(!d.mastery)return section('Meisterschaft',note('Meisterschaft wurde in diesem Bericht nicht erfasst.'),'mastery');
        const nodes=(d.mastery.nodes||[]).filter(n=>Number(n.level)>0);
        const branches=d.mastery.branches?.length?d.mastery.branches:branchDefaults;
        const cards=branches.map(b=>{
            const selected=nodes.filter(n=>n.branch===b.code),points=selected.reduce((sum,n)=>sum+Number(n.level),0);
            const role=branchDefaults.some(v=>v.code===b.code)?b.code:'unknown';
            return `<article class="sr-mastery-card sr-role-${role}">${image(itemArt(b.icon))}<strong>${esc(b.name)}</strong><span><b>${number(points)}</b> Punkte</span></article>`;
        }).join('');
        const ranks=nodes.length?`<dl class="sr-stats sr-talents">${nodes.map(n=>stat(n.name,`Rang ${number(n.level)}${n.max_level?'/'+number(n.max_level):''}`)).join('')}</dl>`:note('Keine Meisterschaftspunkte vergeben.');
        return section('Meisterschaft',`<div class="sr-mastery-grid">${cards}</div>${ranks}<p class="sr-total">Lord-Stufe <strong>${number(d.lord_level??d.mastery.lord?.level)}</strong></p>`,'mastery');
    }
    function equipment(d) {
        if(!Array.isArray(d.treasures))return section('Relikte',note('Relikte wurden in diesem Bericht nicht erfasst.'),'equipment');
        const catalog=getKingdom()?.treasures?.items||[];
        const cards=Array.from({length:6},(_,i)=>{
            const t=d.treasures.find(t=>Number(t.equipped_slot)===i+1);
            if(!t)return `<article class="sr-relic sr-slot-empty"><span aria-hidden="true">✦</span><strong>Platz ${i+1}</strong><small>Nicht belegt</small></article>`;
            // Only artwork and rarity come from the catalog, never the viewer's level or stats.
            const def=catalog.find(v=>Number(v.treasure_code)===Number(t.treasure_code)),grade=t.grade||def?.grade;
            return `<article class="sr-relic grade-${['normal','uncommon','rare','epic','legendary','mythic'].includes(grade)?grade:'normal'}">${image(itemArt(t.icon||def?.icon))}<strong>${esc(def?.name_de||t.name||'Relikt #'+t.treasure_code)}</strong><small>Stufe ${number(t.level)}</small></article>`;
        }).join('');
        return section('Relikte',`<div class="sr-relic-grid">${cards}</div>${!d.treasures.length?note('Keine ausgerüsteten Relikte.'):''}`,'equipment');
    }
    function bonuses(d) {
        const rows=[];
        if(d.wall?.attack_buff!=null)rows.push(stat('Mauerangriff',percent(d.wall.attack_buff)));
        if(d.wall?.defense_buff!=null)rows.push(stat('Mauerverteidigung',percent(d.wall.defense_buff)));
        for(const node of d.mastery?.nodes||[])if(Number(node.level)>0&&node.bonus!=null)rows.push(stat((node.label||node.name)+' · Meisterschaft',percent(Number(node.level)*Number(node.bonus)*100)));
        return section('Gesichtete Boni',`${rows.length?`<dl class="sr-stats">${rows.join('')}</dl>`:note('Keine Boni in diesem Bericht erfasst.')}${note('Mauer- und Meisterschaftsboni bei der Aufklärung. Weitere aktive Boni wurden nicht erfasst.')}`,'bonuses');
    }
    function render(r,footer) {
        const d=r.details||{},wall=d.wall;
        const observed=d.observed_at||r.created_at,stamp=new Date(observed?ctx.date(observed):NaN),time=Number.isNaN(stamp.getTime())?'Zeitpunkt unbekannt':stamp.toLocaleString('de-DE');
        const identity=`<div class="sr-target">${image('map/castle-default.png')}<div><small>Ausgespähtes Königreich</small><strong>${esc(d.target_name||'Unbekanntes Ziel')}</strong><span>Burgstufe ${number(d.castle_level)}</span><b>Macht ${number(d.target_power)}</b></div></div>`;
        const defense=wall?`<div class="sr-wall"><div class="sr-wall-label">${image('map/wall.svg')}<strong>Mauer <small>Stufe ${number(wall.level)}</small></strong></div><dl class="sr-stats">${stat('Haltbarkeit',`${number(wall.durability)} / ${number(wall.durability_max)}`)}${stat('Angriffsbonus',percent(wall.attack_buff))}${stat('Verteidigungsbonus',percent(wall.defense_buff))}</dl><progress max="${Math.max(1,Number(wall.durability_max)||1)}" value="${Math.max(0,Number(wall.durability)||0)}" aria-label="Haltbarkeit der Mauer"></progress></div>`:note('Mauerwerte wurden nicht erfasst.');
        const intro=`<header class="sr-banner">${image('hud/expeditions.svg')}<div><strong>${d.blocked?'Aufklärung verhindert':'Aufklärung erfolgreich'}</strong><span>${esc(d.target_name||'Unbekanntes Ziel')} · X:${number(r.target_x)} Y:${number(r.target_y)}</span></div></header><div class="sr-date"><time>${esc(time)}</time><span>Bericht #${number(r.id)}</span></div>`;
        const body=d.blocked?`<section class="sr-blocked">${image('items/shield.svg')}<h3>Spähschutz aktiv</h3>${note(d.reason||'Die Stadt ist vor Spähern geschützt.')}</section>`:`<section class="sr-overview" aria-label="Ziel und Mauer">${identity}${defense}</section>${supplies(d)}${section('Truppenübersicht',troopList(d.troops,'Keine Truppen in der Garnison gesichtet.'),'troops')}${section('Verstärkungen',troopList(d.reinforcements,'Keine Verstärkungen gesichtet.'),'reinforcements')}${mastery(d)}${equipment(d)}${bonuses(d)}`;
        return `<h2>Spähbericht</h2><article class="mail-detail scout-report"><div class="mail-detail-scroll sr-scroll" tabindex="0" aria-label="Spähbericht, nach unten scrollen">${intro}${body}</div><footer class="mail-detail-actions sr-footer">${footer||'<button type="button" class="mail-button" data-action="mailbox-back">Schließen</button>'}<small>Aufnahme zum Spähzeitpunkt</small></footer></article>`;
    }
    const dialog=document.querySelector('#game-dialog');
    let entry=null,closingHistory=false;
    dialog.addEventListener('close',()=>{
        const previous=entry;entry=null;
        if(previous&&history.state?.conquerScoutReport===previous.token){
            if(location.href===previous.url){closingHistory=true;history.back();}
            else {const state={...history.state};delete state.conquerScoutReport;history.replaceState(state,'',location.href);}
        }
    });
    window.addEventListener('popstate',()=>{
        closingHistory=false;
        if(entry&&history.state?.conquerScoutReport!==entry.token){entry=null;if(dialog.open&&dialog.querySelector('.scout-report'))dialog.close();}
    });
    return {open(r,{footer=''}={}){
        if(closingHistory)return;
        if(!entry){entry={token:crypto.randomUUID(),url:location.href};history.pushState({...history.state,conquerScoutReport:entry.token},'',location.href);}
        openDialog(render(r,footer));
    }};
};
