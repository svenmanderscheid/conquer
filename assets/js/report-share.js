/* Share a compact battle summary through the authenticated community channels. */
window.ConquerReportShare = function ({api,toast,esc,getState}) {
    const dialog=document.createElement('dialog');
    dialog.id='report-share-dialog';dialog.className='report-share-dialog';dialog.setAttribute('aria-labelledby','report-share-title');
    document.body.append(dialog);
    let channel='alliance',playerId=0,summary='',reportId=0,community=null,loading=false,sending=false,token=null,closing=false,copy={};
    const receipts=new Map();
    const world=()=>Number(getState()?.city?.world_id||1);
    const button=(key,label,disabled=false)=>`<button type="button" class="cr-share-channel" data-share-channel="${key}" aria-pressed="${channel===key}" ${disabled?'disabled':''}>${label}</button>`;
    function draw(focusHeading=false){
        const players=community?.players||[],hasAlliance=Boolean(community?.alliance);
        if(channel==='alliance'&&!loading&&!hasAlliance)channel='world';
        dialog.innerHTML=`<header class="cr-detail-heading"><h2 id="report-share-title" tabindex="-1">${esc(copy.title||'Bericht teilen')}</h2><button type="button" class="cr-button" data-share-close aria-label="Teilen schließen">×</button></header><form class="report-share-form"><div class="report-share-scroll"><p>${esc(copy.prompt||'Wohin möchtest du diese Kampfzusammenfassung senden?')}</p><nav class="cr-share-channels" aria-label="${esc(copy.destinationLabel||'Ziel für den Bericht')}">${button('alliance','Allianzchat',loading||!hasAlliance)}${button('world','Weltchat',loading)}${button('private','Privat',loading||!players.length)}</nav>${loading?'<p class="cr-share-loading" role="status">Empfänger werden geladen …</p>':`<label class="cr-share-player" ${channel==='private'?'':'hidden'}>Spieler<select name="player_id" required ${channel==='private'?'':'disabled'}><option value="">Spieler auswählen</option>${players.map(player=>`<option value="${Number(player.id)}" ${Number(player.id)===playerId?'selected':''}>${esc(player.username)}</option>`).join('')}</select></label>`}<section class="cr-share-preview" aria-label="Geteilte Zusammenfassung"><strong>Vorschau</strong><p>${esc(summary)}</p></section></div><footer class="report-share-actions"><button type="button" class="cr-button" data-share-close>Abbrechen</button><button type="submit" class="cr-button cr-primary" ${loading||sending?'disabled':''}>${sending?'Wird geteilt …':'Jetzt teilen'}</button></footer></form>`;
        if(focusHeading)dialog.querySelector('#report-share-title')?.focus({preventScroll:true});
    }
    async function loadCommunity(openWorld){
        loading=true;community=null;draw(true);
        try{community=await api('community/state?world_id='+openWorld);}
        catch(error){toast(error.message);if(dialog.open)dialog.close();return;}
        finally{loading=false;}
        if(dialog.open&&world()===openWorld)draw(true);
    }
    function open(text,id,options={}){
        summary=Array.from(String(text||'').replace(/\s+/g,' ').trim()).slice(0,200).join('');reportId=Number(id)||0;
        copy=options&&typeof options==='object'?options:{};
        channel='alliance';playerId=0;sending=false;token=`report-share-${Date.now()}`;
        history.pushState({...history.state,conquerReportShare:token},'',location.href);
        if(!dialog.open)dialog.showModal();
        loadCommunity(world());
    }
    dialog.addEventListener('click',event=>{
        if(event.target.closest('[data-share-close]')){dialog.close();return;}
        const selected=event.target.closest('[data-share-channel]')?.dataset.shareChannel;
        if(selected){channel=selected;draw();dialog.querySelector(`[data-share-channel="${CSS.escape(selected)}"]`)?.focus({preventScroll:true});}
    });
    dialog.addEventListener('change',event=>{if(event.target.matches('[name="player_id"]'))playerId=Number(event.target.value)||0;});
    dialog.addEventListener('submit',async event=>{
        event.preventDefault();if(loading||sending||!community)return;
        const player=Number(new FormData(event.target).get('player_id'));playerId=player||0;
        if(channel==='private'&&!player){event.target.elements.player_id?.focus();return;}
        const payload={action:'chat.send',channel,message:summary};if(reportId>0)payload.report_id=reportId;if(channel==='private')payload.player_id=player;
        const key=JSON.stringify({world_id:world(),...payload});if(!receipts.has(key))receipts.set(key,crypto.randomUUID());
        sending=true;draw();
        try{const result=await api('community/action',{...payload,request_id:receipts.get(key)});receipts.delete(key);toast(result.message||copy.success||'Bericht geteilt.');if(dialog.open)dialog.close();}
        catch(error){if(error.definite)receipts.delete(key);toast(error.message);sending=false;if(dialog.open)draw();}
    });
    dialog.addEventListener('close',()=>{
        if(!closing&&token&&history.state?.conquerReportShare===token)history.back();
        token=null;community=null;loading=false;sending=false;
        document.querySelector('.march-command [data-action="march-share-target"],.combat-report [data-combat="share"],.combat-report [data-monster="share"]')?.focus({preventScroll:true});
    });
    window.addEventListener('popstate',()=>{if(token&&history.state?.conquerReportShare!==token&&dialog.open){closing=true;dialog.close();closing=false;token=null;}});
    return {open};
};
