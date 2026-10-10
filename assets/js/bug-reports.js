window.ConquerBugReports=function(ctx){
    'use strict';
    const {api,toast,openDialog,navigate}=ctx,$=selector=>document.querySelector(selector);
    let pending=false,lastId=0,operationKey='',screenshot='',sourceContext=null,previousDialog='';
    let supportMode='',supportRows=null,supportCase=null,supportLoading=false,supportError='',supportToken=0;
    const replyDrafts=new Map(),supportText=key=>window.ConquerLocale.t('admin.cases.'+key);
    const supportButton=()=>`<button type="button" class="button secondary" data-action="support-case-list">${escapeHtml(supportText('my_support'))}</button>`;
    const key=()=>{
        if(!operationKey)operationKey=globalThis.crypto?.randomUUID?.()||`bug_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,12)}`;
        return operationKey;
    };
    function render(){
        const host=$('#content');if(!host)return;
        if(supportMode){renderSupport(host);return;}
        if(lastId){
            host.innerHTML=`<section class="panel bug-report-success"><span class="bug-report-seal" aria-hidden="true">✓</span><h2>Meldung ist angekommen</h2><p data-i18n="bugreport.thanks" data-i18n-params="${escapeHtml(JSON.stringify({reference:'#'+lastId}))}">${escapeHtml(window.ConquerLocale.t('bugreport.thanks',{reference:'#'+lastId}))}</p><button class="button secondary" data-action="bug-report-new">Weiteren Bug melden</button>${supportButton()}</section>`;
            return;
        }
        host.innerHTML=`<section class="panel bug-report-panel">
          <div class="bug-report-intro"><span class="bug-report-mark" aria-hidden="true"><img src="${ctx.base}/assets/art/menu-icons-v2/bug-report.png" alt=""></span><div><h2>Bug oder Idee melden</h2><p data-i18n="bugreport.intro">${escapeHtml(window.ConquerLocale.t('bugreport.intro'))}</p></div></div>
          ${supportButton()}<form data-form="bug-report">
            <div class="bug-report-fields">
              <label for="bug-type">Meldungsart<select id="bug-type" name="report_type" required><option value="bug">Bug</option><option value="idea">Idee / Vorschlag</option><option value="support">${escapeHtml(supportText('kind_support'))}</option></select></label>
              <label for="bug-category">Bereich<select id="bug-category" name="category" required><option value="gameplay">Spielablauf</option><option value="interface">Anzeige & Bedienung</option><option value="performance">Leistung & Absturz</option><option value="account">Konto & Anmeldung</option><option value="other">Anderer Bereich</option></select></label>
            </div>
            <label for="bug-severity">Auswirkung<select id="bug-severity" name="severity" required><option value="normal">Störend, Spielen möglich</option><option value="minor">Kleiner Fehler / kleine Verbesserung</option><option value="blocking">Spielen nicht möglich</option></select></label>
            <p class="bug-report-context"><strong>Automatisch erfasster Bereich:</strong> ${escapeHtml(sourceContext?.label||'Aktueller Spielbereich')}</p>
            <label for="bug-title"><span>Kurzer Titel</span><input id="bug-title" name="title" minlength="5" maxlength="120" required placeholder="z. B. Ausbauknopf reagiert nicht"></label>
            <label for="bug-description"><span>Was ist passiert?</span><textarea id="bug-description" name="description" minlength="20" maxlength="3000" rows="5" required placeholder="Beschreibe den Fehler und was du gerade tun wolltest."></textarea></label>
            <label for="bug-steps"><span>Wie lässt sich der Fehler wiederholen? <small>optional</small></span><textarea id="bug-steps" name="reproduction_steps" maxlength="2000" rows="3" placeholder="1. Weltkarte öffnen&#10;2. Monster antippen&#10;3. …"></textarea></label>
            <label for="bug-expected"><span>Was hättest du erwartet? <small>optional</small></span><textarea id="bug-expected" name="expected_result" maxlength="1000" rows="2"></textarea></label>
            <label class="bug-diagnostics"><input type="checkbox" name="diagnostics" checked><span><strong>Technische Angaben mitsenden</strong><small>Bildschirmgröße, Sprache, Plattform und aktueller Spielbereich – keine Passwörter oder Chatnachrichten.</small></span></label>
            ${screenshot?`<figure class="bug-screenshot-preview"><img src="${screenshot}" alt="Vorschau des freigegebenen Screenshots"><figcaption>Screenshot wird mitgesendet. <button type="button" class="button secondary" data-action="bug-report-remove-screenshot">Entfernen</button></figcaption></figure>`:'<p class="bug-no-screenshot">Kein Screenshot ausgewählt.</p>'}
            <button class="button wide" type="submit">Meldung senden</button>
          </form>
        </section>`;
    }
    function diagnostics(){
        return {viewport:`${innerWidth}x${innerHeight}`,screen:`${screen.width}x${screen.height}`,language:navigator.language||'',platform:navigator.userAgentData?.platform||navigator.platform||'',online:String(navigator.onLine),reduced_motion:String(matchMedia('(prefers-reduced-motion: reduce)').matches),app_mode:matchMedia('(display-mode: standalone)').matches?'standalone':'browser',client_time:new Date().toISOString()};
    }
    async function onSubmit(form){
        if(form.dataset.form==='support-reply')return submitSupportReply(form);
        if(form.dataset.form!=='bug-report')return false;
        if(pending)return true;
        const data=new FormData(form),button=form.querySelector('button[type=submit]');
        pending=true;button.disabled=true;button.setAttribute('aria-busy','true');
        try{
            const result=await api('bug-reports',{operation_key:key(),report_type:String(data.get('report_type')||'bug'),category:String(data.get('category')||''),severity:String(data.get('severity')||''),title:String(data.get('title')||'').trim(),description:String(data.get('description')||'').trim(),reproduction_steps:String(data.get('reproduction_steps')||'').trim(),expected_result:String(data.get('expected_result')||'').trim(),page_path:sourceContext?.path||location.pathname+location.hash,client_context:data.has('diagnostics')?{...diagnostics(),menu:sourceContext?.label||''}:null,screenshot:screenshot||null});
            lastId=Number(result.id);operationKey='';delete $('#content').dataset.dirty;render();toast(result.message||'Bugmeldung gesendet.');
        }catch(error){toast(error.message);button.disabled=false;button.removeAttribute('aria-busy');}
        finally{pending=false;}
        return true;
    }
    function escapeHtml(value){return String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));}
    function begin(context){
        sourceContext=context;screenshot='';lastId=0;operationKey='';supportMode='';supportToken++;
        const dialog=$('#game-dialog');previousDialog=dialog?.open?$('#dialog-content')?.innerHTML||'':'';
        const supported=Boolean(navigator.mediaDevices?.getDisplayMedia);
        openDialog(`<section class="bug-capture-consent"><h2>Bug oder Idee melden</h2><p>Der geöffnete Bereich <strong>${escapeHtml(context?.label||'Union of Kingdoms')}</strong> wird automatisch notiert.</p><p>Möchtest du zusätzlich einen Screenshot mitsenden? Erst nach deinem Klick fragt der Browser, welchen Tab oder Bildschirm du freigeben willst. Passwörter oder private Nachrichten sollten nicht sichtbar sein.</p><div class="button-row"><button class="button secondary" data-action="bug-report-without-screenshot">Ohne Screenshot</button><button class="button" data-action="bug-report-capture" ${supported?'':'disabled'}>Screenshot aufnehmen</button></div>${supported?'':'<small>Screenshot-Aufnahme wird von diesem Gerät nicht unterstützt.</small>'}</section>`,{focusHeading:true});
    }
    async function capture(){
        let stream;
        try{
            stream=await navigator.mediaDevices.getDisplayMedia({video:{displaySurface:'browser'},audio:false,preferCurrentTab:true,selfBrowserSurface:'include'});
            if(previousDialog&&$('#game-dialog').open)$('#dialog-content').innerHTML=previousDialog;else $('#game-dialog')?.close();
            await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));
            const video=document.createElement('video');video.srcObject=stream;video.muted=true;await video.play();
            if(!video.videoWidth)await new Promise(resolve=>video.addEventListener('loadedmetadata',resolve,{once:true}));
            const scale=Math.min(1,1280/video.videoWidth,1280/video.videoHeight),canvas=document.createElement('canvas');canvas.width=Math.max(1,Math.round(video.videoWidth*scale));canvas.height=Math.max(1,Math.round(video.videoHeight*scale));
            canvas.getContext('2d',{alpha:false}).drawImage(video,0,0,canvas.width,canvas.height);screenshot=canvas.toDataURL('image/jpeg',.7);
            $('#game-dialog')?.close();navigate('bugreport');toast('Screenshot aufgenommen. Du kannst ihn vor dem Senden entfernen.');
        }catch(error){if(error?.name!=='NotAllowedError')toast('Der Screenshot konnte nicht aufgenommen werden.');}
        finally{stream?.getTracks().forEach(track=>track.stop());}
    }
    function onClick(action,button){
        if(action==='support-case-list'){loadSupport();return true;}
        if(action==='support-case-open'){loadSupport(button.dataset.source,Number(button.dataset.id));return true;}
        if(action==='bug-report-without-screenshot'){$('#game-dialog')?.close();navigate('bugreport');return true;}
        if(action==='bug-report-capture'){capture();return true;}
        if(action==='bug-report-remove-screenshot'){screenshot='';render();return true;}
        if(action!=='bug-report-new')return false;lastId=0;screenshot='';supportMode='';supportToken++;render();return true;
    }
    function caseTitle(row){return row.kind==='content'?window.ConquerLocale.t('social.reason_'+row.title):row.title;}
    function renderSupport(host){
        const text=supportText,head=`<div class="button-row"><button type="button" class="button secondary" data-action="bug-report-new">${escapeHtml(text('new_report'))}</button>${supportMode==='detail'?supportButton():''}</div>`;
        if(supportLoading||supportError){host.innerHTML=`<section class="panel bug-report-panel" data-support-view>${head}<h2>${escapeHtml(text('my_support'))}</h2><p role="status">${escapeHtml(supportError||text('loading'))}</p></section>`;return;}
        if(supportMode==='list'){
            host.innerHTML=`<section class="panel bug-report-panel" data-support-view>${head}<h2>${escapeHtml(text('my_support'))}</h2><div class="support-player-list">${(supportRows||[]).map(row=>`<button type="button" class="button secondary support-player-case" data-action="support-case-open" data-source="${escapeHtml(row.source_type)}" data-id="${Number(row.source_id)}"><small>${escapeHtml(text('kind_'+row.kind))} #${Number(row.source_id)} · ${escapeHtml(text('status_'+row.status))}</small><strong data-user-content>${escapeHtml(caseTitle(row))}</strong><small>${escapeHtml(row.created_at)} UTC</small></button>`).join('')||`<p>${escapeHtml(text('player_empty'))}</p>`}</div>${supportRows?.length===100?`<p>${escapeHtml(text('player_limit'))}</p>`:''}</section>`;return;
        }
        const row=supportCase;if(!row)return;
        const draftKey=row.source_type+':'+row.source_id,draft=replyDrafts.get(draftKey)||{body:'',operation:''};
        host.innerHTML=`<section class="panel bug-report-panel" data-support-view>${head}<small>#${Number(row.source_id)} · ${escapeHtml(text('status_'+row.status))}</small><h2 data-user-content>${escapeHtml(caseTitle(row))}</h2><article class="support-player-message"><strong>${escapeHtml(text('you'))}</strong><p data-user-content>${escapeHtml(row.description).replace(/\n/g,'<br>')}</p><small>${escapeHtml(row.created_at)} UTC</small></article>${row.messages.map(message=>`<article class="support-player-message ${message.author_type==='admin'?'from-team':''}"><strong>${escapeHtml(text(message.author_type==='admin'?'team_name':'you'))}</strong><p data-user-content>${escapeHtml(message.body).replace(/\n/g,'<br>')}</p><small>${escapeHtml(message.created_at)} UTC</small></article>`).join('')}<form data-form="support-reply"><label>${escapeHtml(text('message'))}<textarea name="body" required maxlength="4000" rows="4">${escapeHtml(draft.body)}</textarea></label><small>${escapeHtml(text('reply_reopens'))}</small><button type="submit" class="button">${escapeHtml(text('send_reply'))}</button></form></section>`;
        host.querySelector('[name="body"]').addEventListener('input',event=>{const existing=replyDrafts.get(draftKey);replyDrafts.set(draftKey,{body:event.target.value,operation:existing?.body===event.target.value?existing.operation:''});});
    }
    async function loadSupport(source,id){
        const token=++supportToken;supportMode=source?'detail':'list';supportLoading=true;supportError='';render();
        try{const result=await api(source?`support-cases/${encodeURIComponent(source)}/${Number(id)}`:'support-cases');if(token!==supportToken)return;if(source)supportCase=result;else supportRows=result.cases;}
        catch(error){if(token!==supportToken)return;supportError=error.message;}
        finally{if(token===supportToken){supportLoading=false;if($('#content [data-support-view]'))render();}}
    }
    async function submitSupportReply(form){
        if(pending||!supportCase)return true;
        const source=supportCase.source_type,id=Number(supportCase.source_id),draftKey=source+':'+id,body=String(new FormData(form).get('body')||'').trim();
        const previous=replyDrafts.get(draftKey),operation=previous?.body.trim()===body&&previous.operation?previous.operation:(globalThis.crypto?.randomUUID?.()||`support_${Date.now()}_${Math.random().toString(36).slice(2)}`);
        replyDrafts.set(draftKey,{body,operation});pending=true;const button=form.querySelector('button[type="submit"]');button.disabled=true;
        try{const result=await api(`support-cases/${encodeURIComponent(source)}/${id}/reply`,{body,operation_key:operation});replyDrafts.delete(draftKey);toast(result.message);await loadSupport(source,id);}
        catch(error){toast(error.message);button.disabled=false;}
        finally{pending=false;}return true;
    }
    return {render,onSubmit,onClick,begin};
};
