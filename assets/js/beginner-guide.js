/* Reading progress is local; all game progress comes from authenticated snapshots. */
window.ConquerBeginnerGuide = function(ctx) {
    'use strict';
    const {base,esc,fmt,getState,getKingdom,getHost,navigate,openDialog,buildingDialog,buildingFunction,labels,buildingImage}=ctx;
    const tabs = {start:'Einstieg',buildings:'Gebäude',goals:'Ziele',knowledge:'Wissen'};
    const groups = {all:'Alle Gebäude',city:'Stadt & Schutz',economy:'Rohstoffe & Handel',army:'Truppen & Wissen',alliance:'Gemeinschaft'};
    const buildings = [
        ['castle','city','Das Herz deines Königreichs. Die Burg ermöglicht höhere Gebäudestufen und erhöht die Grundkapazität deiner Märsche.','Öffne vor dem Ausbau die Voraussetzungen. Oft müssen zuerst Stadtmauer und weitere Gebäude wachsen.'],
        ['wall','city','Die Stadtmauer gehört zur Verteidigung deiner Stadt und wird für weitere Burgausbauten benötigt.','Halte sie mit deiner Burg auf Stand. Prüfe deine Truppen und Schutzeffekte zusätzlich im Bereich Verteidigung.','defense','Verteidigung'],
        ['watch_tower','city','Dein Aussichtspunkt und Zugang zur Umgebung auf der Weltkarte.','Entdecke von hier aus Rohstofffelder und Monster. Die Weltkarte zeigt die verfügbaren Zielaktionen.','world','Weltkarte'],
        ['farm','economy','Produziert Nahrung für Ausbildung, Heilung, Ausbauten und andere Vorhaben.','Ein früher Ausbau verbessert deinen Nachschub. Die Produktion läuft auch offline bis zur Produktionsgrenze.'],
        ['lumber_camp','economy','Produziert Holz für Gebäude, Truppen und weitere Verbesserungen.','Holz wird beim Aufbau häufig gebraucht. Erhöhe die Produktion, wenn es deine nächsten Vorhaben bremst.'],
        ['quarry','economy','Produziert Stein für den Ausbau deiner Stadt und weitere Vorhaben.','Plane Stein rechtzeitig für größere Ausbauten ein. Fehlende Mengen kannst du auch auf der Weltkarte sammeln.'],
        ['gold_mine','economy','Produziert Gold für Forschung, Truppen und Verbesserungen.','Für mehr Platz in der Goldproduktion baust du die Schatzkammer aus, nicht das Lagerhaus.'],
        ['storage','economy','Erhöht die Produktionsgrenze für Nahrung, Holz und Stein. Ein Grundbetrag je Ressource ist außerdem vor Plünderung geschützt.','Produktionsgrenze und Plünderschutz sind verschiedene Werte. Tippe oben auf eine Ressource, um Vorrat, Grenze und Produktion zu sehen.','inventory','Inventar'],
        ['treasure_house','economy','Erhöht die Produktionsgrenze für Gold und öffnet deine Reliktsammlung mit Ausrüstung und Truhen.','Sammle Fragmente, verbessere Relikte und rüste passende Schätze aus. Ein besessenes Relikt allein ersetzt das Ausrüsten nicht.','treasures','Schatzkammer'],
        ['trading_post','economy','Öffnet den Markt mit verfügbaren Handelsangeboten.','Vergleiche Inhalt, Preis und deinen Vorrat. Ein Angebot wird erst durch die Kaufaktion bezahlt.','market','Markt'],
        ['barrack','army','Bildet Infanterie aus. Höhere Gebäudestufen erhöhen die Menge pro Ausbildungsauftrag.','Truppenränge werden durch die nötige Burg- und Ausbildungsgebäudestufe freigeschaltet. Die Ausbildung zeigt die konkreten Voraussetzungen.','function','Infanterie'],
        ['archery_range','army','Bildet Fernkämpfer aus und hat eine eigene Ausbildung.','Ergänze deine Infanterie durch Schützen. Gebäudestufe und Burg bestimmen, welche Ränge du ausbilden kannst.','function','Schützen'],
        ['stable','army','Bildet Kavallerie aus und hat eine eigene Ausbildung.','Beachte die Werte der gewählten Einheit. Bei einem gemischten Marsch begrenzt die langsamste Truppe das Reisetempo.','function','Kavallerie'],
        ['hospital','army','Hier warten verwundete Truppen auf ihre Behandlung.','Wähle Verwundete aus und prüfe Rohstoffe und Heilzeit. Erst „Heilen“ bezahlt und startet die Behandlung. Gefallene Truppen können nicht geheilt werden.','function','Hospital'],
        ['academy','army','Erforscht dauerhafte Verbesserungen für Wirtschaft, Militär und fortgeschrittene Bereiche.','Starte mit einem Bonus, der zu deinem nächsten Ziel passt. Die Forschungsbäume zeigen alle Voraussetzungen und werden senkrecht gescrollt.','research','Forschung'],
        ['hall_of_alliance','alliance','Verbindet dich mit deiner Allianz. Die Gebäudestufe beeinflusst die Kapazität gemeinsamer Rallies.','Tritt einer Allianz bei, um gemeinsam zu planen, zu helfen und Gruppeninhalte anzugehen.','alliance','Allianz'],
    ];
    const chapters = [
        {title:'Dein Königreich beginnt mit dir',art:'map/castle',intro:'Baue eine starke Stadt auf, entdecke die Welt und bewältige größere Herausforderungen mit Verbündeten. Du entscheidest, ob Aufbau, Jagd oder gemeinsames Spielen zuerst kommt.',points:['Tippe Gebäude an, um ihre Funktion oder ihren Ausbau zu öffnen. Kosten, Dauer und Voraussetzungen stehen vor dem Start sichtbar dabei.','Das Hauptmenü öffnet Forschung, Truppen, Verteidigung und diesen Guide. Unten findest du Aufgaben, Allianz, Post, Inventar und den Wechsel zwischen Dorf und Welt.'],tip:'Du kannst den Guide jederzeit schließen und später im Hauptmenü unter „Anfangsguide“ fortsetzen.',action:['Gebäude kennenlernen','guide-tab','buildings']},
        {title:'Sichere deinen Nachschub',art:'map/farm',intro:'Nahrung, Holz, Stein und Gold finanzieren dein Wachstum. Deine vier Rohstoffgebäude produzieren auch dann, wenn du gerade nicht spielst.',points:['Baue die Rohstoffgebäude passend zu deinem Bedarf aus. Das Lagerhaus erweitert den Platz für Nahrung, Holz und Stein; die Schatzkammer den Platz für Gold.','An der Produktionsgrenze endet der weitere Zuwachs durch Produktion. Zusätzliche Rohstoffe bekommst du durch Sammeln, Aufgaben, Pakete und Handel.'],tip:'Tippe auf eine Ressource in der Kopfleiste: Dort siehst du den Bestand, die Produktionsgrenze und den Ertrag pro Stunde.',action:['Bauernhof ansehen','guide-building','farm']},
        {title:'Lass deine Stadt wachsen',art:'map/settlement',intro:'Die Burg gibt die Richtung vor. Andere Gebäude erfüllen ihre Voraussetzungen und verbessern einzelne Bereiche deines Reichs.',points:['Prüfe zuerst die Voraussetzungen des nächsten Burgausbaus. Fehlende Gebäude lassen sich von dort direkt öffnen.','Bau, Forschung und Ausbildung brauchen Zeit. Die Auftragsanzeigen zeigen den Fortschritt. Passende Beschleuniger verkürzen laufende Aufträge; die Vorschau zeigt, wie viel Zeit verwendet wird.'],tip:'Die Gebäudeseiten im Guide zeigen deine aktuellen Stufen. Ein Klick auf „Ausbau ansehen“ startet noch keinen Bau.',action:['Burgausbau ansehen','guide-building','castle']},
        {title:'Bereite deine Armee vor',art:'research/infantry-training_amount',intro:'Kaserne, Schützenlager und Reiterhof bilden unterschiedliche Truppen aus. Forschung und ausgerüstete Relikte verstärken dein Reich.',points:['Wähle Truppenrang und Menge. Die Ausbildung zeigt Kosten und Dauer. Ein gesperrter Rang nennt die nötigen Gebäudestufen.','Verwundete kehren ins Hospital zurück. Wähle sie dort aus und starte eine bezahlte Heilung, damit sie wieder einsatzbereit werden.'],tip:'Macht ist ein Orientierungswert. Truppenwerte, Zusammensetzung, Boni und Gegner beeinflussen den Kampf.',action:['Ausbildung öffnen','guide-function','barrack']},
        {title:'Entdecke die Welt',art:'map/lumber',intro:'Auf der Weltkarte findest du Rohstofffelder, Monster, andere Städte und gemeinsame Ziele. Verschiebe die Karte mit dem Finger oder der Maus.',points:['Die Lupe sucht passende Ziele nach Art und Stufe. Ein Suchtreffer öffnet das Zielmenü und schickt noch keine Truppen los.','Beim Sammeln zählen Reisezeit, Sammeltempo und Traglast. Die Beute erreicht deinen Vorrat erst nach der Rückkehr. Prüfe bei Kämpfen Ziel und Armee vor dem Abmarsch.'],tip:'Ungeschützte fremde Städte können angegriffen werden. Stadtangriffe können Verluste verursachen und deinen eigenen Schutz beenden. Arena-Duelle sind freiwillig und ohne Truppenverluste.',action:['Weltkarte entdecken','guide-nav','world']},
        {title:'Finde Verbündete und deine Ziele',art:'research/rally',intro:'Mit einer Allianz wachsen aus kleinen Aufgaben gemeinsame Abenteuer: Monster-Rallies, Feldzüge und koordinierte Hilfe.',points:['Hole fertige Aufgabenbelohnungen und Post mit Belohnungen ab. Prüfe regelmäßig deine Aufträge, Verwundeten und Ausrüstung.','Später kommen stärkere Truppen, weitere Forschung, Dungeons, Landentwicklung und Weltereignisse hinzu. Verfügbare Inhalte und Voraussetzungen stehen im jeweiligen Bereich.'],tip:'Unter „Ziele“ siehst du erste Meilensteine anhand deines Spielstands und Ideen für deinen weiteren Weg.',action:['Meine Ziele ansehen','guide-tab','goals']},
    ];
    const knowledge = [
        ['Aufträge, Wartezeit und Beschleuniger','Aufträge laufen auch offline weiter. Bau-, Forschungs-, Ausbildungs- und Heilungsbeschleuniger passen nur zu ihrem jeweiligen Bereich; universelle Beschleuniger zu mehreren. Prüfe Menge und eventuell verfallende Zeit in der Vorschau. Kristalle sind derzeit für VIP-Punkte und Skins vorgesehen, nicht für Sofortabschlüsse.','inventory','Inventar öffnen'],
        ['Aufgaben und Belohnungen','Der goldene Zähler am Aufgabenknopf nennt fertige, noch nicht abgeholte Aufgaben. „Zeigen“ führt zum Bereich, „Abholen“ schreibt die Belohnung gut. Auch Feldzüge, Ereignisse und Post können Belohnungen enthalten, die du noch abholen musst.','quests','Aufgaben öffnen'],
        ['Forschung, Talente und Relikte','Forschung verbessert dein Reich dauerhaft. Mit Lord-Fortschritt erhältst du Talentpunkte für deine Spezialisierung. Relikte liefern ihre Ausrüstungsboni, wenn sie angelegt sind. Wähle Verbesserungen passend zu Aufbau, Kampf, Sammeln oder Jagd.','research','Forschung öffnen'],
        ['Sammeln und Märsche','Ein Marsch bindet Truppen und einen Marschplatz. Beim Sammeln bleiben sie am Feld, bis die Traglast voll oder das Feld leer ist. Ein Rückruf bringt den bisherigen Ertrag nach Hause. Marschtempo verkürzt die Reise; Sammeltempo die Arbeit am Feld.','world','Weltkarte öffnen'],
        ['Monster, Schutz und andere Spieler','Monsterjagd kann Aktionspunkte und Truppen kosten. Größere Rally-Gegner werden gemeinsam angegangen. Stadtangriffe und Stadt-Rallies gegen ungeschützte Spieler können Truppenverluste und Plünderung verursachen. Prüfe deinen Schutz und die Zielvorschau. Arena-Duelle brauchen die Zustimmung beider Spieler und verursachen keine Verluste.','defense','Verteidigung öffnen'],
        ['Allianz und Feldzüge','Tritt einer Allianz bei oder gründe eine. Im Einführungsfeldzug gegen den Aschenfürsten arbeiten zwei Allianzen zusammen: Schutzanlagen, Gebirgspass und Nahrungslieferungen bereiten den Bossangriff vor. Auch Unterstützung zählt. Die konkrete Aufgabenverteilung und Belohnungsabholung stehen im Feldzug.','expeditions','Feldzüge öffnen'],
        ['Dungeons und Rollen','Dungeons sind Gruppenabenteuer für zwei bis vier Spieler. Eine Rolle benötigt mindestens einen investierten Talentpunkt im passenden Zweig und mindestens zehn Truppen. Angriff, Verteidigung, Sammler und Jäger tragen unterschiedlich bei. Die Dungeonansicht zeigt verfügbare Abenteuer und Voraussetzungen.','dungeons','Dungeons öffnen'],
        ['Land, Ereignisse und langfristiger Fortschritt','Erkunde offene Regionen, entwickle Land und beteilige dich an verfügbaren Weltereignissen. Höhere Burg- und Ausbildungsgebäudestufen, Forschung und Relikte eröffnen weitere Möglichkeiten. Es gibt mehrere Wege, dein Reich voranzubringen.','land','Landübersicht öffnen'],
        ['Bedienung, Speichern und Kontosicherheit','Alle Bereiche lassen sich antippen. Das X oder Escape schließt ein Fenster; über Dorf/Welt wechselst du das Spielfeld. Dein Spielstand liegt auf dem Server. Prüfe nach Verbindungsproblemen zuerst den aktuellen Auftrag. Sichere dein Konto über Passwort und Wiederherstellung. Nur der Lesestand dieses Guides wird auf diesem Gerät gespeichert.','account','Konto öffnen'],
    ];
    let key='',saved={},section='start',chapter=0,group='all',welcomed=false;
    const host=getHost;
    function load() {
        const state=getState(),next=`conquer:beginner-guide:v1:${base}:${state?.city?.player_id}`;
        if(key===next)return;
        key=next;saved={};
        try { const value=JSON.parse(localStorage.getItem(key));if(value&&typeof value==='object'&&!Array.isArray(value))saved=value; } catch {}
        chapter=Number.isInteger(saved.chapter)?Math.max(0,Math.min(chapters.length-1,saved.chapter)):0;
        saved.read=Array.isArray(saved.read)?saved.read.filter(n=>Number.isInteger(n)&&n>=0&&n<chapters.length):[];
        section='start';group='all';
    }
    function save() { try { localStorage.setItem(key,JSON.stringify(saved)); } catch {} }
    function button(text,action,id='',primary=false) { return `<button type="button" class="guide-button${primary?' guide-primary':''}" data-action="${action}" data-id="${esc(id)}">${esc(text)}</button>`; }
    function goals() {
        const s=getState(),k=getKingdom(),level=code=>Number(s.buildings[code]?.level||0);
        return [
            {id:'castle',title:'Burg auf Stufe 2',text:'Prüfe die Voraussetzungen und baue das Herz deiner Stadt aus.',value:level('castle'),target:2,action:['Burg ansehen','guide-building','castle']},
            {id:'production',title:'Vier Rohstoffgebäude auf Stufe 2',text:'Sorge mit Bauernhof, Sägewerk, Steinbruch und Goldmine für Nachschub.',value:['farm','lumber_camp','quarry','gold_mine'].filter(c=>level(c)>=2).length,target:4,action:['Gebäude ansehen','guide-tab','buildings']},
            {id:'training',title:'20 Truppen ausbilden',text:'Schließe Ausbildungsaufträge für insgesamt 20 Truppen ab.',value:Number(s.trained_total||0),target:20,action:['Ausbildung öffnen','guide-function','barrack']},
            {id:'research',title:'Eine Forschung abschließen',text:'Wähle in der Akademie einen ersten dauerhaften Bonus.',value:Object.values(s.research||{}).filter(v=>Number(v)>0).length,target:1,action:['Forschung öffnen','guide-nav','research']},
            {id:'alliance',title:'Teil einer Allianz sein',text:'Finde Mitspieler für Hilfe, Austausch und gemeinsame Abenteuer.',value:k?(k.alliance?1:0):null,target:1,action:['Allianz öffnen','guide-nav','alliance']},
        ];
    }
    function start() {
        const c=chapters[chapter],read=saved.read.includes(chapter);
        return `<div class="guide-reading"><span>${new Set(saved.read).size} von ${chapters.length} Kapiteln gelesen</span><span>Lesestand auf diesem Gerät</span></div>
            <div class="guide-chapters" role="group" aria-label="Kapitel auswählen">${chapters.map((c,i)=>`<button type="button" class="guide-chapter" data-action="guide-chapter" data-id="${i}" aria-pressed="${i===chapter}" aria-label="Kapitel ${i+1}: ${esc(c.title)}${saved.read.includes(i)?', gelesen':''}">${i+1}${saved.read.includes(i)?' ✓':''}</button>`).join('')}</div>
            <article class="guide-lesson"><div class="guide-lesson-heading"><img src="${base}/assets/art/${c.art}.svg" alt=""><div><p class="guide-eyebrow">Kapitel ${chapter+1} von ${chapters.length}</p><h2 tabindex="-1" id="guide-heading">${c.title}</h2></div></div><p class="guide-intro">${c.intro}</p><ul>${c.points.map(p=>`<li>${p}</li>`).join('')}</ul><p class="guide-tip">${c.tip}</p>${button(...c.action,true)}</article>
            <div class="guide-lesson-footer">${chapter>0?button('Zurück','guide-chapter',chapter-1):'<span></span>'}${button(chapter===chapters.length-1?(read?'Zu meinen Zielen':'Gelesen · zu den Zielen'):(read?'Weiter':'Gelesen · weiter'),'guide-next','',true)}</div>`;
    }
    function buildingList() {
        return `<h2 tabindex="-1" id="guide-heading">Deine ${buildings.length} Gebäude</h2><p>Wofür sie da sind, wann sie helfen und wo es weitergeht. Die Stufen entsprechen deinem aktuellen Spielstand.</p><div class="guide-filters" role="group" aria-label="Gebäude filtern">${Object.entries(groups).map(([id,name])=>`<button type="button" class="guide-button" data-action="guide-filter" data-id="${id}" aria-pressed="${group===id}">${name}</button>`).join('')}</div><div class="guide-building-list">${buildings.filter(b=>group==='all'||b[1]===group).map(([code,category,purpose,tip,target,label])=>`<article class="guide-building" data-guide-building="${code}"><div class="guide-building-heading"><img src="${esc(buildingImage(code))}" alt="" loading="lazy"><div><small>${groups[category]}</small><h3>${esc(labels[code])}</h3></div><span class="guide-level" data-guide-level="${code}"></span></div><p>${purpose}</p><p class="guide-building-tip">${tip}</p><div class="guide-actions">${button('Ausbau ansehen','guide-building',code)}${target?button(label+' öffnen',target==='function'?'guide-function':'guide-nav',target==='function'?code:target):''}</div></article>`).join('')}</div>`;
    }
    function goalList() {
        return `<h2 tabindex="-1" id="guide-heading">Dein nächster Schritt</h2><p>Diese Meilensteine begleiten deinen Einstieg. Du bestimmst die Reihenfolge. Sie zeigen deinen Spielstand und vergeben keine zusätzliche Belohnung.</p><p class="guide-tip" data-guide-goal-summary></p><div class="guide-goals">${goals().map(g=>`<article class="guide-goal" data-guide-goal="${g.id}"><h3>${g.title}</h3><span class="guide-goal-status"></span><p>${g.text}</p><progress max="${g.target}" value="0" aria-label="${g.title}"></progress>${button(...g.action)}</article>`).join('')}</div><h3>Danach wächst dein Abenteuer weiter</h3><div class="guide-paths">${[
            ['Dein Reich stärken','Höhere Gebäudestufen, neue Truppenränge und dauerhafte Forschung eröffnen weitere Möglichkeiten.','research','Forschung'],
            ['Die Welt erkunden','Sammle Rohstoffe, jage passende Monster und entwickle dein Land.','world','Weltkarte'],
            ['Gemeinsam bestehen','Plane Rallies und Feldzüge mit Verbündeten oder stelle eine Dungeongruppe zusammen.','expeditions','Feldzüge'],
            ['Deinen Spielstil finden','Stimme Talente und Relikte auf Aufbau, Sammeln, Jagd oder Kampf ab.','mastery','Talente'],
        ].map(([title,text,target,label])=>`<article class="guide-path"><h3>${title}</h3><p>${text}</p>${button(label+' öffnen','guide-nav',target)}</article>`).join('')}</div>`;
    }
    function reference() { return `<h2 tabindex="-1" id="guide-heading">Gut zu wissen</h2><p>Die wichtigsten Spielregeln zum Nachschlagen.</p><div class="guide-reference">${knowledge.map(([title,text,target,label])=>`<details><summary>${title}</summary><p>${text}</p>${button(label,'guide-nav',target)}</details>`).join('')}</div>`; }
    function sync() {
        host().querySelectorAll('[data-guide-level]').forEach(el=>{const level=getState().buildings[el.dataset.guideLevel]?.level;el.textContent=level==null?'Stufe unbekannt':`Stufe ${fmt(level)}`;});
        const items=goals();
        for(const g of items){
            const card=host().querySelector(`[data-guide-goal="${g.id}"]`);if(!card)continue;
            const done=g.value!==null&&g.value>=g.target;
            card.classList.toggle('is-complete',done);
            card.querySelector('.guide-goal-status').textContent=g.value===null?'Spielstand derzeit nicht verfügbar':`${done?'✓ Erreicht':'Noch offen'} · ${fmt(Math.min(g.value,g.target))} / ${fmt(g.target)}`;
            card.querySelector('progress').value=Math.min(g.value||0,g.target);
        }
        const summary=host().querySelector('[data-guide-goal-summary]');
        if(summary)summary.textContent=`${items.filter(g=>g.value!==null&&g.value>=g.target).length} von ${items.length} Einstiegszielen erreicht · aus deinem aktuellen Spielstand`;
    }
    function render(force=false) {
        load();
        if(!saved.welcomed){saved.welcomed=true;save();}
        // Leave open details, keyboard focus and the reading position intact during polling.
        if(!force&&host().querySelector('.beginner-guide')){sync();return;}
        host().innerHTML=`<section class="beginner-guide"><nav class="guide-tabs" aria-label="Bereiche des Anfangsguides">${Object.entries(tabs).map(([id,title])=>`<button type="button" class="guide-button" data-action="guide-tab" data-id="${id}" aria-pressed="${section===id}">${title}</button>`).join('')}</nav><div class="guide-body">${({start,buildings:buildingList,goals:goalList,knowledge:reference})[section]()}</div></section>`;
        sync();
    }
    function redraw(focusSelector='#guide-heading') {
        render(true);host().scrollTop=0;
        host().querySelector(focusSelector)?.focus({preventScroll:true});
    }
    function onClick(act,b) {
        if(!act.startsWith('guide-'))return false;
        load();const id=b.dataset.id;
        if(act==='guide-open'){section='start';navigate('help');}
        if(act==='guide-tab'&&Object.hasOwn(tabs,id)){section=id;redraw(`[data-action="guide-tab"][data-id="${id}"]`);}
        if(act==='guide-filter'&&Object.hasOwn(groups,id)){group=id;redraw(`[data-action="guide-filter"][data-id="${id}"]`);}
        if(act==='guide-chapter'&&/^\d+$/.test(id)&&Number(id)<chapters.length){chapter=Number(id);saved.chapter=chapter;save();redraw();}
        if(act==='guide-next'){
            saved.read=[...new Set([...saved.read,chapter])];
            if(chapter<chapters.length-1)chapter++;else section='goals';
            saved.chapter=chapter;save();redraw();
        }
        if(act==='guide-building'&&getState().buildings[id])buildingDialog(id);
        if(act==='guide-function'&&getState().buildings[id])buildingFunction(id);
        if(act==='guide-nav')navigate(id);
        return true;
    }
    function maybeWelcome(current) {
        load();const s=getState();
        if(welcomed||saved.welcomed||current!=='city'||Number(s.buildings.castle?.level)>1||Number(s.trained_total)>0||Object.keys(s.research||{}).length||document.querySelector('dialog[open]'))return;
        welcomed=true;saved.welcomed=true;save();
        openDialog(`<h2>Willkommen in deinem Königreich!</h2><div class="guide-welcome"><img src="${base}/assets/art/map/castle.svg" alt=""><p>Aus einem kleinen Dorf wird dein eigenes Reich. Lerne die Gebäude kennen, sichere deinen Nachschub und finde dein erstes Ziel.</p><p>Sechs kurze Kapitel begleiten deinen Start. Du kannst jederzeit unterbrechen und den Anfangsguide im Hauptmenü wieder öffnen.</p><div class="guide-actions">${button('Guide starten','guide-open','',true)}${button('Später entdecken','close-dialog')}</div></div>`,{focusHeading:true});
    }
    return {render,onClick,maybeWelcome};
};
