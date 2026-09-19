(() => {
    'use strict';

    const branches = [
        {id:'economy', name:'Wirtschaft', subtitle:'Vorräte für ein wachsendes Reich', icon:'harvest'},
        {id:'military', name:'Militär', subtitle:'Stärke für deine Truppen', icon:'swords'},
        {id:'development', name:'Fortgeschritten', subtitle:'Kriegskunst, Verteidigung und gemeinsame Feldzüge', icon:'shield'}
    ];
    const names = {
        food_production:'Reiche Ernte', wood_production:'Geschickte Holzfäller', lumber_production:'Geschickte Holzfäller',
        stone_production:'Bessere Werkzeuge', gold_production:'Goldene Zeiten',
        food_capacity:'Nahrungslager', wood_capacity:'Holzlager', stone_capacity:'Steinlager', gold_capacity:'Goldlager',
        food_gathering_speed:'Nahrung sammeln', wood_gathering_speed:'Holz sammeln', stone_gathering_speed:'Stein sammeln', gold_gathering_speed:'Gold sammeln', crystal_gathering_speed:'Kristalle sammeln',
        infantry_hp:'Standhafte Infanterie', infantry_def:'Verstärkte Schilde', infantry_atk:'Geschärfte Klingen',
        ranged_hp:'Ausdauer der Schützen', ranged_def:'Geschützte Schützen', ranged_atk:'Präzise Pfeile',
        cavalry_hp:'Starke Reittiere', cavalry_def:'Gepanzerte Reiter', cavalry_atk:'Kraftvoller Ansturm',
        infantry_storage:'Traglast der Infanterie', ranged_storage:'Traglast der Schützen', cavalry_storage:'Traglast der Reiterei',
        research_speed:'Wissensdurst', construction_speed:'Flotte Baumeister', production_resource_protect:'Geschützte Vorräte', resource_protect:'Strategischer Vorratsschutz',
        troops_storage:'Traglast der Armee', march_size:'Größere Marschverbände', march_limit:'Zusätzlicher Marschplatz', hospital_capacity:'Größeres Hospital', healing_time_reduced:'Schnellere Heilung', rally_attack_amount:'Größere Sammelangriffe',
        resource_production:'Reichsweite Produktion', resource_capacity:'Reichsweite Lagerung', troop_speed_when_participating_a_rally:'Tempo im Sammelangriff',
        warrior:'Krieger', longbow_man:'Langbogenschützen', horseman:'Reiter', knight:'Ritter', ranger:'Waldläufer', heavy_cavalry:'Schwere Kavallerie', guardian:'Wächter', crossbow_man:'Armbrustschützen', iron_cavalry:'Eiserne Kavallerie', crusader:'Kreuzritter', sniper:'Scharfschützen', dragoon:'Dragoner'
    };
    const paths = {
        book:'M3 4h7l2 2 2-2h7v15h-7l-2 2-2-2H3V4Zm9 2v15M6 8h3m-3 4h3m6-4h3m-3 4h3',
        harvest:'M12 21V8m0 8C6 16 4 13 4 9c5 0 8 3 8 7Zm0-5c6 0 8-3 8-7-5 0-8 3-8 7Zm0-3C8 7 8 4 12 2c4 2 4 5 0 6Z',
        swords:'m3 3 8 8-2 2-7-7 1-3Zm18 0-8 8 2 2 7-7-1-3ZM8 14l-5 5m13-5 5 5M5 13l6 6m2 0 6-6',
        academy:'m3 8 9-5 9 5H3Zm2 3v7m5-7v7m4-7v7m5-7v7M3 21h18',
        shield:'m12 2 8 3v6c0 5-4 9-8 11-4-2-8-6-8-11V5l8-3Zm-4 9 3 3 5-6',
        heart:'M12 21S2 15 2 8c0-6 8-7 10-1 2-6 10-5 10 1 0 7-10 13-10 13Z',
        hammer:'m14 3 7 7-3 3-3-3L5 21l-3-3L12 7 9 4l5-1Z',
        lock:'M5 10h14v11H5V10Zm3 0V6a4 4 0 0 1 8 0v4m-4 5v2',
        check:'m5 12 4 4L19 6',
        clock:'M12 8v5l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
        arrow:'M4 12h16m-6-6 6 6-6 6',
        box:'m3 7 9-5 9 5v11l-9 4-9-4V7Zm0 0 9 5 9-5m-9 5v10M7 4l10 6'
    };
    let selectedBranch = 'economy', selectedGroup = 'all', searchQuery = '', focusCode = '', pendingFocus = false, lastDefs = [];
    const icon = key => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${paths[key] || paths.book}"/></svg>`;
    const branchOf = node => node.tree === 'advanced' ? 'development' : node.tree === 'battle' ? 'military' : 'economy';
    const groupNames = {food:'Nahrung',wood:'Holz',stone:'Stein',general:'Reich & Versorgung',infantry:'Infanterie',ranged:'Schützen',cavalry:'Reiterei',counter:'Konter',castle_def:'Stadtverteidigung',composed:'Reine Truppentypen',rally:'Reich & Sammelangriffe'};
    const troopType = code => /infantry|infantrys/.test(code) ? 'Infanterie' : /ranged|archer/.test(code) ? 'Schützen' : /cavalry|cavalrys/.test(code) ? 'Reiterei' : 'Armee';
    function title(value) {
        const code = typeof value === 'string' ? value : value?.code || '';
        if (names[code]) return names[code];
        if (code.startsWith('advanced_')) return title(code.slice(9)) + ' II';
        if (code.includes('_against_')) return `${troopType(code.split('_against_')[0])} gegen ${troopType(code.split('_against_')[1])} · ${statLabel(code)}`;
        if (code.startsWith('castle_defending_')) return `${troopType(code)} in Verteidigung · ${statLabel(code)}`;
        if (code.includes('when_composed_of')) return `Reine ${troopType(code)} · ${statLabel(code)}`;
        if (code.includes('when_participating')) return `${troopType(code)} im Sammelangriff · ${statLabel(code)}`;
        if (/^(infantry|ranged|cavalry|troops)_/.test(code)) return `${troopType(code)} · ${statLabel(code)}`;
        return (typeof value === 'object' && value.name) || code.replace(/_/g,' ');
    }
    function statLabel(code) {
        if (/training_amount/.test(code)) return 'Ausbildungsplätze';
        if (/training_speed/.test(code)) return 'Ausbildungstempo';
        if (/training_cost/.test(code)) return 'Ausbildungskosten';
        if (/(?:^|_)hp(?:_|$)/.test(code)) return 'Lebenspunkte';
        if (/(?:^|_)def(?:_|$)/.test(code)) return 'Verteidigung';
        if (/(?:^|_)atk(?:_|$)/.test(code)) return 'Angriff';
        if (/gathering_speed/.test(code)) return 'Sammeltempo';
        if (/research_speed/.test(code)) return 'Forschungstempo';
        if (/construction_speed/.test(code)) return 'Bautempo';
        if (/healing_time_reduced/.test(code)) return 'Heilzeitverkürzung';
        if (/hospital_capacity/.test(code)) return 'Hospitalplätze';
        if (/march_size|rally_attack_amount/.test(code)) return 'Truppenplätze';
        if (/spd|speed/.test(code)) return 'Marschtempo';
        if (/storage/.test(code)) return 'Traglast';
        if (/capacity/.test(code)) return 'Lagerkapazität';
        if (/protect/.test(code)) return 'Vorratsschutz';
        if (/production/.test(code)) return 'Produktion';
        return 'Bonus';
    }
    function bonus(node, entry) {
        const code = node.code, number = Number(entry?.ability_value || 0);
        let note = '';
        if (code.startsWith('castle_defending_') || /resource_protect/.test(code)) note = 'Wirkt bei Stadtverteidigung. Spielerstädte sind in dieser Welt geschützt.';
        else if (code.includes('_against_')) note = `Gilt für ${troopType(code.split('_against_')[0])} gegen ${troopType(code.split('_against_')[1])}.`;
        else if (code.includes('when_composed_of')) note = `Gilt, wenn der Verband ausschließlich aus ${troopType(code)} besteht.`;
        else if (code.includes('_hp_when_participating')) note = 'Mehr Lebenspunkte bei gemeinsamen Sammelangriffen. Im Feldzug gegen den Aschenfürsten entstehen keine Truppenverluste.';
        else if (code.includes('when_participating') || code === 'troop_speed_when_participating_a_rally' || code === 'rally_attack_amount') note = 'Gilt bei gemeinsamen Sammelangriffen.';
        if (node.effect_note) note = node.effect_note;
        if (node.type === 'unlock') return code === 'march_limit' ? {label:'Marschplätze',value:`+${number || 1} Marschplatz`,note} : {label:'Einheit freischalten',value:title(node),note};
        return {label:statLabel(code),value:(number < 0 ? '−' : '+') + percent(Math.abs(number)),note};
    }
    const levelEntry = (node, level) => (node.levels || []).find(entry => Number(entry.level) === level);
    const researchRequirements = (node, next) => {
        const result = new Map();
        // Opening a technology keeps its original prerequisites; self requirements are the previous level, not graph edges.
        [...(levelEntry(node, 1)?.requirements || []), ...(next?.requirements || [])].forEach(req => {
            if (req.type === 'research' && req.code !== node.code) result.set(req.code, Math.max(Number(req.level) || 1, result.get(req.code) || 0));
        });
        return [...result].map(([code, level]) => ({code, level}));
    };
    const academyFor = entry => Math.max(1, ...(entry?.requirements || []).filter(req => req.type === 'academy').map(req => Number(req.level) || 1));
    const timeValue = value => {
        if (!value) return 0;
        const raw = String(value).replace(' ', 'T');
        return Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(raw) ? raw : raw + 'Z');
    };
    const percent = value => (Math.round((Number(value) || 0) * 1000) / 10).toLocaleString('de-DE', {maximumFractionDigits:1}) + ' %';

    const unitUnlockArt={warrior:'infantry-tier2',knight:'infantry-tier3',guardian:'infantry-tier4',crusader:'infantry-tier5',longbow_man:'ranged-tier2',ranger:'ranged-tier3',crossbow_man:'ranged-tier4',sniper:'ranged-tier5',horseman:'cavalry-tier2',heavy_cavalry:'cavalry-tier3',iron_cavalry:'cavalry-tier4',dragoon:'cavalry-tier5'};
    function artFor(node){
        const code=String(node.code||'').replace(/^advanced_/,''),advanced=String(node.code||'').startsWith('advanced_');
        if(unitUnlockArt[code])return {key:unitUnlockArt[code],advanced};
        const resource=code.match(/^(food|wood|lumber|stone|gold|crystal)_(production|capacity|gathering_speed)$/);
        if(resource)return {key:'resource-'+(resource[2]==='gathering_speed'?'gathering':resource[2]),resource:resource[1]==='wood'?'lumber':resource[1],advanced};
        const special={academy:'academy',research_speed:'research',construction_speed:'construction',hospital_capacity:'hospital',healing_time_reduced:'healing',march_size:'march-size',march_limit:'march-limit',rally_attack_amount:'rally',troop_speed_when_participating_a_rally:'rally',production_resource_protect:'protection',resource_protect:'protection',resource_production:'production',resource_capacity:'capacity'};
        if(special[code])return {key:special[code],advanced};
        // Counter research depicts the beneficiary, never the opponent named after "against".
        const subject=code.split('_against_')[0],type=troopType(subject);
        const family=type==='Infanterie'?'infantry':type==='Schützen'?'ranged':type==='Reiterei'?'cavalry':'army';
        const effect=/training_amount/.test(code)?'training_amount':/training_speed/.test(code)?'training_speed':/training_cost/.test(code)?'training_cost':/(?:^|_)hp(?:_|$)/.test(code)?'hp':/(?:^|_)def(?:_|$)/.test(code)?'def':/(?:^|_)atk(?:_|$)/.test(code)?'atk':/(?:^|_)spd(?:_|$)/.test(code)?'spd':/storage/.test(code)?'storage':null;
        return {key:effect?family+'-'+effect:'research',advanced};
    }
    function nodeArt(node, base, esc) {
        const art=artFor(node),src=`${base}/assets/art/research/${art.key}.svg`;
        const resourceSrc=art.resource?`${base}/assets/art/${art.resource==='crystal'?'items/gems.svg':'ui-resources/'+art.resource+'.png'}`:null;
        return `<span class="rt-node-art rt-illustrated${art.resource?' rt-resource':''}" data-art="${art.key}">${resourceSrc?`<img class="rt-resource-art" src="${esc(resourceSrc)}" alt="" loading="lazy"><img class="rt-resource-effect" src="${esc(src)}" alt="" loading="lazy">`:`<img src="${esc(src)}" alt="" loading="lazy">`}${art.advanced?'<small class="rt-art-rank" aria-hidden="true">II</small>':''}</span>`;
    }

    function renderRequirements({requirements, state, base, esc, researchNames = {}}) {
        const entries = (requirements || []).filter(req => ['academy','research'].includes(req.type)).map(req => {
            const code = req.type === 'academy' ? 'academy' : req.code;
            const name = code === 'academy' ? 'Akademie' : title(state.research_defs.find(node => node.code === code) || code);
            const current = Number(code === 'academy' ? state.buildings.academy.level : state.research[code] || 0);
            return {code, name, current, needed:Number(req.level), met:current >= Number(req.level)};
        });
        if (!entries.length) return '';
        return `<section class="research-requirements" aria-label="Forschungsvoraussetzungen"><div class="research-requirements-heading"><h3>Voraussetzungen</h3><span>${entries.filter(req => req.met).length} / ${entries.length} erfüllt</span></div><div class="research-requirement-grid">${entries.map(req => `<button type="button" class="research-requirement ${req.met ? 'requirement-met' : 'requirement-missing'}" data-action="${req.code === 'academy' ? 'building' : 'research-dialog'}" data-id="${esc(req.code)}" aria-label="${esc(req.name)}, Stufe ${req.needed} benötigt, aktuell Stufe ${req.current}, ${req.met ? 'erfüllt' : 'fehlt noch'}. Details öffnen">${nodeArt({code:req.code},base,esc)}<span class="research-requirement-copy"><strong>${esc(req.name)}</strong><span>Stufe ${req.needed} benötigt</span><small>${icon(req.met ? 'check' : 'lock')}${req.met ? 'Erfüllt' : 'Fehlt noch'} · aktuell ${req.current}</small></span></button>`).join('')}</div></section>`;
    }

    // Each source column becomes one vertical step with up to three parallel technologies.
    // Positions describe presentation only;
    // every rendered connection still comes from the JSON requirements.
    const center = code => [null,code,null];
    const unitColumn = suffix => ['infantry','ranged','cavalry'].map(type => type+'_'+suffix);
    const advancedColumn = suffix => ['infantry','ranged','cavalry'].map(type => 'advanced_'+type+'_'+suffix);
    const resourceColumn = suffix => ['food','wood','stone'].map(type => type+'_'+suffix);
    const advancedResources = suffix => ['food','wood','stone'].map(type => 'advanced_'+type+'_'+suffix);
    const chapter = (id,name,columns,headings,rows=[]) => ({id,name,columns,headings,rows});
    const sourceChapters = {
        military:[
            chapter('battle-basics','Grundausbildung',[unitColumn('hp'),unitColumn('def'),unitColumn('atk')],['Lebenspunkte','Verteidigung','Angriff'],['Infanterie','Schützen','Reiterei']),
            chapter('battle-muster','Aufmarsch & Versorgung',[unitColumn('spd'),center('troops_storage')],['Marschtempo','Traglast']),
            chapter('battle-training','Truppenausbildung',[unitColumn('training_amount'),unitColumn('training_speed'),unitColumn('training_cost')],['Plätze','Ausbildungstempo','Kosten'],['Infanterie','Schützen','Reiterei']),
            chapter('battle-command','Heeresführung',[center('march_size'),center('march_limit')],['Marschgröße','Marschplätze']),
            chapter('battle-army','Die starke Armee',[center('troops_spd'),['troops_hp','troops_def','troops_atk'],center('hospital_capacity')],['Marschtempo','Kampfkraft','Hospital']),
            chapter('battle-elite','Heilkunst & Sammelangriffe',[center('healing_time_reduced'),center('rally_attack_amount')],['Heilkunst','Sammelangriff']),
            chapter('battle-veterans','Veteranen',[advancedColumn('hp'),advancedColumn('def'),advancedColumn('atk')],['Lebenspunkte II','Verteidigung II','Angriff II'],['Infanterie','Schützen','Reiterei']),
            chapter('battle-masters','Marschtempo II',[advancedColumn('spd')],['Marschtempo II'],['Infanterie','Schützen','Reiterei'])
        ],
        economy:[
            chapter('economy-supply','Grundversorgung',[resourceColumn('production'),center('gold_production'),resourceColumn('capacity')],['Produktion','Goldproduktion','Lager']),
            chapter('economy-gather','Lager & Sammler',[center('gold_capacity'),resourceColumn('gathering_speed'),center('gold_gathering_speed')],['Goldlager','Sammeltempo','Gold sammeln']),
            chapter('economy-knowledge','Wege & Wissen',[center('crystal_gathering_speed'),unitColumn('storage'),center('research_speed')],['Kristalle','Traglast','Forschung']),
            chapter('economy-build','Bauen & Bewahren',[center('construction_speed'),center('production_resource_protect'),advancedResources('production')],['Bautempo','Vorratsschutz','Produktion II']),
            chapter('economy-store','Große Lager',[center('advanced_gold_production'),advancedResources('capacity'),center('advanced_gold_capacity')],['Goldproduktion II','Lager II','Goldlager II']),
            chapter('economy-progress','Neue Baukunst',[center('advanced_research_speed'),center('advanced_construction_speed'),advancedResources('gathering_speed')],['Forschung II','Bautempo II','Sammeltempo II']),
            chapter('economy-caravans','Große Vorratszüge',[center('advanced_gold_gathering_speed'),center('advanced_crystal_gathering_speed')],['Gold sammeln II','Kristalle II'])
        ],
        development:[
            chapter('advanced-counter','Kontertechnik',[center('resource_production'),['infantry_hp_against_archer','archer_hp_against_cavalry','cavalry_hp_against_infantry'],['infantry_def_against_archer','archer_def_against_cavalry','cavalry_def_against_infantry'],['infantry_atk_against_archer','archer_atk_against_cavalry','cavalry_atk_against_infantry']],['Reichsproduktion','Lebenspunkte','Verteidigung','Angriff']),
            chapter('advanced-defend','Stadtverteidigung',[center('resource_capacity'),['castle_defending_infantrys_hp','castle_defending_archers_hp','castle_defending_cavalrys_hp'],['castle_defending_infantrys_def','castle_defending_archers_def','castle_defending_cavalrys_def'],['castle_defending_infantrys_atk','castle_defending_archers_atk','castle_defending_cavalrys_atk']],['Reichslager','Lebenspunkte','Verteidigung','Angriff']),
            chapter('advanced-pure','Reine Truppentypen',[center('resource_protect'),['infantrys_hp_when_composed_of_infantry_only','archers_hp_when_composed_of_archer_only','cavalrys_hp_when_composed_of_cavalry_only'],['infantrys_def_when_composed_of_infantry_only','archers_def_when_composed_of_archer_only','cavalrys_def_when_composed_of_cavalry_only'],['infantrys_atk_when_composed_of_infantry_only','archers_atk_when_composed_of_archer_only','cavalrys_atk_when_composed_of_cavalry_only']],['Vorratsschutz','Lebenspunkte','Verteidigung','Angriff']),
            chapter('advanced-rally','Sammelangriffe',[center('troop_speed_when_participating_a_rally'),['infantrys_hp_when_participating_a_rally','archers_hp_when_participating_a_rally','cavalrys_hp_when_participating_a_rally'],['infantrys_def_when_participating_a_rally','archers_def_when_participating_a_rally','cavalrys_def_when_participating_a_rally'],['infantrys_atk_when_participating_a_rally','archers_atk_when_participating_a_rally','cavalrys_atk_when_participating_a_rally']],['Marschtempo','Lebenspunkte','Verteidigung','Angriff'])
        ]
    };
    let lastTreeInfo=null, lastEdges=[];
    function chaptersFor(defs, branch) {
        const available=new Map(defs.filter(node=>branchOf(node)===branch).map(node=>[node.code,node]));
        const chapters=sourceChapters[branch].map(ch=>({...ch,columns:ch.columns.map(col=>col.map(code=>available.has(code)?code:null))})).filter(ch=>ch.columns.some(col=>col.some(Boolean)));
        const placed=new Set(chapters.flatMap(ch=>ch.columns.flat().filter(Boolean)));
        // Future catalogue additions remain reachable instead of being silently discarded.
        const extra=[...available.keys()].filter(code=>!placed.has(code));
        if(extra.length){const columns=[];for(let i=0;i<extra.length;i+=3)columns.push([extra[i]||null,extra[i+1]||null,extra[i+2]||null]);chapters.push(chapter('extra-'+branch,'Weitere Entdeckungen',columns,columns.map(()=> 'Ergänzungen')));}
        return chapters;
    }
    function compactTitle(node) {
        if(/^(advanced_)?(infantry|ranged|cavalry)_(hp|def|atk|spd)$/.test(node.code))return troopType(node.code)+' · '+statLabel(node.code);
        if(node.code.includes('_against_'))return troopType(node.code.split('_against_')[0])+' · '+statLabel(node.code);
        if(node.code.startsWith('castle_defending_')||node.code.includes('when_composed_of')||node.code.includes('when_participating'))return troopType(node.code)+' · '+statLabel(node.code);
        return title(node);
    }
    function render(options) {
        const {state,base='',esc,fmt,countdown}=options;
        const defs=Array.isArray(state.research_defs)?state.research_defs:Object.values(state.research_defs||{});
        lastDefs=defs;
        const byCode=new Map(defs.map(node=>[node.code,node]));
        const researched=state.research||{},queue=state.research_queue||[],academy=Number(state.buildings?.academy?.level)||0;
        const selected=branches.find(branch=>branch.id===selectedBranch)||branches[0];
        const chapters=chaptersFor(defs,selected.id);
        let sections=chapters, searchMatches=[];
        if(searchQuery){
            const normalize=value=>String(value).normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('de-DE');
            searchMatches=defs.filter(node=>normalize([title(node),node.name,node.code,bonus(node,levelEntry(node,1)).label].join(' ')).includes(normalize(searchQuery)));
            // Search results also flow downwards, including matches from other tabs.
            sections=branches.map(branch=>{
                const matches=searchMatches.filter(node=>branchOf(node)===branch.id),columns=[];
                for(let i=0;i<matches.length;i+=3)columns.push(matches.slice(i,i+3).map(node=>node.code));
                return chapter('search-'+branch.id,branch.name,columns,[]);
            }).filter(section=>section.columns.length);
        }
        const steps=[],tracks=[],sectionSlots=[],slots=new Map();
        sections.forEach(section=>{
            sectionSlots.push({...section,gridRow:tracks.length+1});
            tracks.push('var(--rt-heading-height)');
            section.columns.forEach(column=>{
                const row=steps.length,gridRow=tracks.length+1;
                column.forEach((code,col)=>{if(code)slots.set(code,{col,row,gridRow});});
                steps.push(column);
                tracks.push('var(--rt-node-height)');
            });
        });
        const codes=steps.flat().filter(Boolean),nodes=codes.map(code=>byCode.get(code));
        const models=new Map(nodes.map(node=>{
            const level=Number(researched[node.code])||0,next=levelEntry(node,level+1),running=queue.find(job=>(job.research_code||job.code)===node.code);
            const unmet=(next?.requirements||[]).some(req=>req.type==='academy'?academy<Number(req.level):req.type==='research'?Number(researched[req.code]||0)<Number(req.level):false);
            return [node.code,{level,next,running,status:running?'running':!next?'complete':unmet?'locked':'available',requirements:researchRequirements(node,next)}];
        }));
        lastEdges=searchQuery?[]:nodes.flatMap(node=>models.get(node.code).requirements.map(req=>({from:req.code,to:node.code,level:req.level,external:!slots.has(req.code),met:Number(researched[req.code]||0)>=req.level})));
        lastTreeInfo={branch:selectedBranch,codes,columns:3,rows:steps.length,direction:'vertical',edges:lastEdges};
        const active=queue[0],branchCount=defs.filter(node=>branchOf(node)===selected.id).length;
        let activeHtml='';
        if(active){
            const activeCode=active.research_code||active.code,activeNode=byCode.get(activeCode);
            const started=timeValue(active.started_at),finishes=timeValue(active.finishes_at);
            const serverNow=Number(state.server_time)>0?Number(state.server_time)*1000:Date.now();
            const progress=started&&finishes>started?Math.max(0,Math.min(100,(serverNow-started)/(finishes-started)*100)):0;
            const targetLevel=Number(active.level_to)||Number(researched[activeCode]||0)+1;
            activeHtml=`<button type="button" class="rt-active-research" data-action="research-dialog" data-id="${esc(activeCode)}" aria-label="Forschung läuft: ${esc(title(activeNode||activeCode))}, Zielstufe ${targetLevel}. Details öffnen"><span class="rt-active-icon">${icon('clock')}</span><span class="rt-active-copy"><strong>Forschung läuft</strong><span>${esc(title(activeNode||activeCode))} · Stufe ${targetLevel}</span></span><span class="rt-active-time"><small>Verbleibend</small><b>${countdown(active.finishes_at)}</b></span><span class="rt-active-progress" role="progressbar" aria-label="Forschungsfortschritt" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(progress)}"><span style="width:${progress.toFixed(1)}%"></span></span></button>`;
        }
        function nodeHtml(node){
            const m=models.get(node.code),pos=slots.get(node.code),ratio=node.max_level?Math.min(100,m.level/Number(node.max_level)*100):0;
            const outside=m.requirements.filter(req=>!slots.has(req.code));
            const stateText={available:'Verfügbar',running:'Wird erforscht',locked:'Gesperrt',complete:'Vollständig erforscht'}[m.status];
            const requirements=outside.map(req=>title(byCode.get(req.code)||req.code)+' Stufe '+req.level).join(', ');
            return `<button type="button" class="rt-node rt-${m.status}${focusCode===node.code?' rt-focused':''}" style="grid-column:${pos.col+1};grid-row:${pos.gridRow}" data-action="${searchQuery?'research-focus':'research-dialog'}" data-id="${esc(node.code)}" data-row="${pos.row}" data-col="${pos.col}" aria-label="${esc(title(node))}, Stufe ${m.level} von ${node.max_level}. ${stateText}. ${searchQuery?'Im Forschungsbaum zeigen':'Details öffnen'}." title="${esc(title(node)+' · '+stateText+(requirements?' · Vorstufen: '+requirements:''))}"><span class="rt-node-emblem">${nodeArt(node,base,esc)}<span class="rt-node-marker" aria-hidden="true">${icon(m.status==='locked'?'lock':m.status==='running'?'clock':m.status==='complete'?'check':'arrow')}</span></span><span class="rt-level-track" role="progressbar" aria-label="Erforschte Stufen" aria-valuemin="0" aria-valuemax="${node.max_level}" aria-valuenow="${m.level}"><span style="width:${ratio}%"></span><b>${m.level} / ${node.max_level}</b></span><span class="rt-node-name">${esc(compactTitle(node))}</span>${outside.length&&!searchQuery?`<span class="rt-source-count" aria-hidden="true">↤ ${outside.length}</span>`:''}</button>`;
        }
        const empty=searchQuery?'Keine Forschung gefunden. Versuche einen Truppentyp oder einen Bonus.':'Für diesen Bereich sind noch keine Forschungen verfügbar.';
        const edgesHtml='<g class="rt-connection-beds"/>'+[...lastEdges].sort((a,b)=>Number(a.met)-Number(b.met)).map(edge=>`<g class="rt-edge${edge.met?' rt-edge-met':''}${edge.external?' rt-edge-external':''}" data-from="${esc(edge.from)}" data-to="${esc(edge.to)}" data-level="${edge.level}"><title>${esc(title(byCode.get(edge.from)||edge.from))} Stufe ${edge.level} → ${esc(title(byCode.get(edge.to)||edge.to))}</title><path class="rt-edge-line"/><circle r="3"/></g>`).join('');
        return `<section class="rt-academy rt-continuous" aria-label="Akademie und Forschungsbaum"><header class="rt-academy-header"><div>${icon('academy')}<span>Akademie <b>Stufe ${academy}</b></span></div>${active?'':`<button type="button" class="rt-academy-upgrade" data-action="building" data-id="academy">Ausbauen ${icon('arrow')}</button>`}</header>${activeHtml}
            <nav class="rt-branches" aria-label="Forschungsbereiche">${branches.map(branch=>`<button type="button" class="rt-branch${branch.id===selected.id?' is-selected':''}" data-action="research-branch" data-id="${branch.id}" aria-pressed="${branch.id===selected.id}" aria-label="${branch.name}, ${defs.filter(node=>branchOf(node)===branch.id).length} Forschungen">${icon(branch.icon)}<span>${branch.name}<small>${defs.filter(node=>branchOf(node)===branch.id).length}</small></span></button>`).join('')}</nav>
            <div class="rt-toolbar"><span class="rt-result-count" role="status">${searchQuery?`${searchMatches.length} Treffer in allen Tabs`:`${branchCount} Forschungen`}</span><label class="rt-search"><span class="rt-sr-only">Alle Forschungen durchsuchen</span><input id="research-search" type="search" value="${esc(searchQuery)}" placeholder="Forschung suchen …" maxlength="80" autocomplete="off"><button type="button" data-action="${searchQuery?'research-clear':'research-search'}" aria-label="${searchQuery?'Suche schließen':'Suchen'}">${searchQuery?'×':'⌕'}</button></label></div>
            ${nodes.length?`<div class="rt-scroll" tabindex="0" role="region" aria-label="${searchQuery?'Suchergebnisse':esc(selected.name)+' – Forschungsbaum'}" aria-describedby="research-scroll-hint"><div class="rt-board" style="grid-template-rows:${tracks.join(' ')}"><svg class="rt-connections" aria-hidden="true">${edgesHtml}</svg>${sectionSlots.map(section=>`<h3 class="rt-section-title" style="grid-column:1 / -1;grid-row:${section.gridRow}"><span>${esc(section.name)}</span></h3>${section.columns.flat().filter(Boolean).map(code=>nodeHtml(byCode.get(code))).join('')}`).join('')}</div></div>`:`<div class="rt-empty">${empty}</div>`}
            <div id="research-scroll-hint" class="rt-scroll-hint">↓ Nach unten scrollen${searchQuery?' · Treffer zeigt die Forschung im Baum.':' · Forschung antippen für Details.'}</div>
        </section>`;
    }
    function afterRender(container){
        if(!container)return;
        const board=container.querySelector('.rt-board'),svg=board?.querySelector('.rt-connections');
        if(board&&svg){
            const rect=board.getBoundingClientRect(),elements=new Map([...board.querySelectorAll('.rt-node')].map(node=>[node.dataset.id,{
                box:node.getBoundingClientRect(),art:node.querySelector('.rt-node-art').getBoundingClientRect(),name:node.querySelector('.rt-node-name').getBoundingClientRect()
            }]));
            svg.setAttribute('viewBox',`0 0 ${rect.width} ${rect.height}`);
            const beds=svg.querySelector('.rt-connection-beds');beds.replaceChildren();
            for(const edge of svg.querySelectorAll('.rt-edge')){
                const target=elements.get(edge.dataset.to),source=elements.get(edge.dataset.from);if(!target)continue;
                const tx=target.art.left-rect.left+target.art.width/2,ty=target.art.top-rect.top-6;
                let d;
                if(source){
                    const fx=source.art.left-rect.left+source.art.width/2,fy=source.name.bottom-rect.top+8;
                    // Keep each junction aligned across its whole row, even when names wrap.
                    const mid=(source.box.bottom+target.box.top)/2-rect.top;
                    const direction=Math.sign(tx-fx),radius=Math.max(0,Math.min(12,Math.abs(tx-fx)/2,mid-fy,ty-mid));
                    d=Math.abs(tx-fx)<1?`M${fx} ${fy}V${ty}`:
                        `M${fx} ${fy}V${mid-radius}Q${fx} ${mid} ${fx+direction*radius} ${mid}H${tx-direction*radius}Q${tx} ${mid} ${tx} ${mid+radius}V${ty}`;
                }else{d=`M${tx} ${Math.max(0,ty-12)}V${ty}`;}
                edge.querySelector('path').setAttribute('d',d);
                // Paint every border below every line so shared junctions have no seams.
                const bed=document.createElementNS('http://www.w3.org/2000/svg','path');
                bed.setAttribute('class','rt-edge-bed'+(edge.classList.contains('rt-edge-met')?' rt-edge-met':''));
                bed.setAttribute('d',d);beds.append(bed);
                edge.querySelector('circle').setAttribute('cx',String(tx));edge.querySelector('circle').setAttribute('cy',String(ty));
            }
        }
        if(pendingFocus){
            const target=[...container.querySelectorAll('.rt-node')].find(node=>node.dataset.id===focusCode);
            const scroll=container.querySelector('.rt-scroll');
            if(target&&scroll){
                const nodeRect=target.getBoundingClientRect(),scrollRect=scroll.getBoundingClientRect();
                scroll.scrollLeft+=nodeRect.left-scrollRect.left-(scroll.clientWidth-nodeRect.width)/2;
                scroll.scrollTop+=nodeRect.top-scrollRect.top-(scroll.clientHeight-nodeRect.height)/2;
                target.focus({preventScroll:true});
            }
            pendingFocus=false;
        }
    }
    window.ConquerResearch={
        render,renderRequirements,title,bonus,nodeArt,afterRender,
        selectBranch(id){if(!branches.some(branch=>branch.id===id))return false;selectedBranch=id;selectedGroup='all';searchQuery='';focusCode='';pendingFocus=false;return true;},
        getBranch(){return selectedBranch;},
        setGroup(id){if(id==='all'){selectedGroup='all';return true;}const node=lastDefs.find(node=>branchOf(node)===selectedBranch&&(node.row||'general')===id);if(!node)return false;selectedGroup=id;return this.focus(node.code);},
        setSearch(value){searchQuery=String(value||'').trim().slice(0,80);focusCode='';pendingFocus=false;},
        focus(code,defs=lastDefs){const node=defs.find(node=>node.code===code);if(!node)return false;lastDefs=defs;selectedBranch=branchOf(node);selectedGroup='all';searchQuery='';focusCode=code;pendingFocus=true;return true;},
        getTreeInfo(){return lastTreeInfo?{...lastTreeInfo,codes:[...lastTreeInfo.codes],edges:lastTreeInfo.edges.map(edge=>({...edge}))}:null;},
        getChapters(defs=lastDefs,branch=selectedBranch){return chaptersFor(defs,branch).map(ch=>({id:ch.id,name:ch.name,codes:ch.columns.flat().filter(Boolean)}));}
    };
})();
