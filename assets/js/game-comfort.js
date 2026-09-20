/* Local preferences never replace server-confirmed game progress. */
'use strict';
window.ConquerGameComfort = function({base,getState,nextGoal,openGoal,openDialog,navigate,esc,fmt,labels}) {
    const card=document.createElement('aside');card.id='hud-objective';card.dataset.i18nScope='';card.className='hud-objective comfort-hint';card.hidden=true;card.setAttribute('aria-label','Dein nächster Schritt');document.body.append(card);
    let scope='',saved={},lastSeen=0,lastWrite=0,summary=null,requestedSince=null,signature='',consumed=null;
    function load() {
        const city=getState()?.city;if(!city)return false;
        const key=`conquer:comfort:v1:${base}:${city.player_id}:${city.world_id}`;
        if(scope!==key){scope=key;saved={};summary=null;consumed=null;requestedSince=null;try{const value=JSON.parse(localStorage.getItem(scope));if(value&&typeof value==='object'&&!Array.isArray(value))saved=value;}catch{}lastSeen=Number(saved.lastSeen)||0;lastWrite=0;}
        return true;
    }
    function save(){if(!scope)return;try{localStorage.setItem(scope,JSON.stringify({...saved,lastSeen}));}catch{}lastWrite=Date.now();}
    function since(){
        // On the first request, window.CONQUER_WORLD and an authenticated city are
        // not yet available. The first confirmed snapshot establishes the scope.
        if(!load())return null;
        if(requestedSince)return requestedSince;
        if(lastSeen>0&&Date.now()/1000-lastSeen>=300)requestedSince=Math.floor(lastSeen);
        return requestedSince;
    }
    function update(){
        if(!load()||document.hidden)return;
        const state=getState();
        if(state.return_summary&&state.return_summary!==consumed){
            const data=state.return_summary;
            consumed=data;
            if(data.buildings.length||data.trained||data.research||data.marches)summary=data;
            requestedSince=null;
        }
        // Preserve the old timestamp until its requested summary has arrived.
        if(!requestedSince&&!(lastSeen>0&&Date.now()/1000-lastSeen>=300))lastSeen=Number(state.server_time)||Math.floor(Date.now()/1000);
        if(state.return_summary)lastSeen=Number(state.server_time);
        if(Date.now()-lastWrite>=60000)save();
        const goal=nextGoal(),html=summary?`<button type="button" data-comfort="return"><strong>Willkommen zurück</strong><small>Deine Abschlüsse ansehen</small></button><button type="button" data-comfort="dismiss-return" aria-label="Rückkehrhinweis ausblenden">×</button>`:!saved.hideGoal&&goal?`<button type="button" data-comfort="goal"><strong>${esc(goal.title)}</strong><small>Nächstes Ziel · ${fmt(Math.min(goal.value,goal.target))} / ${fmt(goal.target)}</small></button><button type="button" data-comfort="dismiss-goal" aria-label="Zielhinweis ausblenden">×</button>`:'';
        card.hidden=!html;
        if(signature!==html){signature=html;card.innerHTML=html;}
    }
    function showReturn(){
        if(!summary)return;
        const data=summary;
        const row=(text,tab,label)=>`<li><span>${text}</span><button class="button secondary" type="button" data-comfort-tab="${tab}">${label}</button></li>`;
        openDialog(`<h2>Willkommen zurück</h2><section class="return-summary"><p>Seit deinem letzten Besuch${data.until-data.since>=7*86400?' (letzte 7 Tage)':''} wurden diese Aufträge abgeschlossen:</p><ul>${data.buildings.map(b=>row(`${esc(labels[b.building_code]||b.building_code)} · Stufe ${fmt(b.level)}`,'city','Zum Dorf')).join('')}${data.trained?row(`${fmt(data.trained)} Truppen ausgebildet`,'army','Ausbildung'):''}${data.research?row(`${fmt(data.research)} Forschungen abgeschlossen`,'research','Forschung'):''}${data.marches?row(`${fmt(data.marches)} Rückmärsche abgeschlossen`,'reports','Berichte'):''}</ul><button class="button wide" type="button" data-comfort="done">Weiter spielen</button></section>`,{focusHeading:true});
    }
    document.addEventListener('click',event=>{
        const button=event.target.closest('[data-comfort],[data-comfort-tab]');if(!button)return;
        if(button.dataset.comfortTab){summary=null;update();navigate(button.dataset.comfortTab);return;}
        switch(button.dataset.comfort){
            case 'goal':openGoal();break;
            case 'return':showReturn();break;
            case 'dismiss-goal':saved.hideGoal=true;save();update();break;
            case 'dismiss-return':summary=null;update();break;
            case 'done':summary=null;document.querySelector('#game-dialog')?.close();update();break;
        }
    });
    function leave(){if(document.hidden)save();}
    document.addEventListener('visibilitychange',leave);window.addEventListener('pagehide',save);
    return {since,update,showGoal(){load();saved.hideGoal=false;save();update();}};
};
