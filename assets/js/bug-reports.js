window.ConquerBugReports=function(ctx){
    'use strict';
    const {api,toast}=ctx,$=selector=>document.querySelector(selector);
    let pending=false,lastId=0,operationKey='';
    const key=()=>{
        if(!operationKey)operationKey=globalThis.crypto?.randomUUID?.()||`bug_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,12)}`;
        return operationKey;
    };
    function render(){
        const host=$('#content');if(!host)return;
        if(lastId){
            host.innerHTML=`<section class="panel bug-report-success"><span class="bug-report-seal" aria-hidden="true">✓</span><h2>Meldung ist angekommen</h2><p>Danke, dass du hilfst, Conquer besser zu machen. Deine Referenz lautet <strong>#${lastId}</strong>.</p><button class="button secondary" data-action="bug-report-new">Weiteren Bug melden</button></section>`;
            return;
        }
        host.innerHTML=`<section class="panel bug-report-panel">
          <div class="bug-report-intro"><span aria-hidden="true">!</span><div><h2>Bug melden</h2><p>Beschreibe kurz, was passiert ist. Deine Meldung landet direkt in der Verwaltung.</p></div></div>
          <form data-form="bug-report">
            <div class="bug-report-fields">
              <label for="bug-category">Bereich<select id="bug-category" name="category" required><option value="gameplay">Spielablauf</option><option value="interface">Anzeige & Bedienung</option><option value="performance">Leistung & Absturz</option><option value="account">Konto & Anmeldung</option><option value="other">Anderer Bereich</option></select></label>
              <label for="bug-severity">Auswirkung<select id="bug-severity" name="severity" required><option value="normal">Störend, Spielen möglich</option><option value="minor">Kleiner Fehler</option><option value="blocking">Spielen nicht möglich</option></select></label>
            </div>
            <label for="bug-title"><span>Kurzer Titel</span><input id="bug-title" name="title" minlength="5" maxlength="120" required placeholder="z. B. Ausbauknopf reagiert nicht"></label>
            <label for="bug-description"><span>Was ist passiert?</span><textarea id="bug-description" name="description" minlength="20" maxlength="3000" rows="5" required placeholder="Beschreibe den Fehler und was du gerade tun wolltest."></textarea></label>
            <label for="bug-steps"><span>Wie lässt sich der Fehler wiederholen? <small>optional</small></span><textarea id="bug-steps" name="reproduction_steps" maxlength="2000" rows="3" placeholder="1. Weltkarte öffnen&#10;2. Monster antippen&#10;3. …"></textarea></label>
            <label for="bug-expected"><span>Was hättest du erwartet? <small>optional</small></span><textarea id="bug-expected" name="expected_result" maxlength="1000" rows="2"></textarea></label>
            <label class="bug-diagnostics"><input type="checkbox" name="diagnostics" checked><span><strong>Technische Angaben mitsenden</strong><small>Bildschirmgröße, Sprache, Plattform und aktueller Spielbereich – keine Passwörter oder Chatnachrichten.</small></span></label>
            <button class="button wide" type="submit">Bugmeldung senden</button>
          </form>
        </section>`;
    }
    function diagnostics(){
        return {viewport:`${innerWidth}x${innerHeight}`,screen:`${screen.width}x${screen.height}`,language:navigator.language||'',platform:navigator.userAgentData?.platform||navigator.platform||'',online:String(navigator.onLine),reduced_motion:String(matchMedia('(prefers-reduced-motion: reduce)').matches),app_mode:matchMedia('(display-mode: standalone)').matches?'standalone':'browser',client_time:new Date().toISOString()};
    }
    async function onSubmit(form){
        if(form.dataset.form!=='bug-report')return false;
        if(pending)return true;
        const data=new FormData(form),button=form.querySelector('button[type=submit]');
        pending=true;button.disabled=true;button.setAttribute('aria-busy','true');
        try{
            const result=await api('bug-reports',{operation_key:key(),category:String(data.get('category')||''),severity:String(data.get('severity')||''),title:String(data.get('title')||'').trim(),description:String(data.get('description')||'').trim(),reproduction_steps:String(data.get('reproduction_steps')||'').trim(),expected_result:String(data.get('expected_result')||'').trim(),page_path:location.pathname+location.hash,client_context:data.has('diagnostics')?diagnostics():null});
            lastId=Number(result.id);operationKey='';delete $('#content').dataset.dirty;render();toast(result.message||'Bugmeldung gesendet.');
        }catch(error){toast(error.message);button.disabled=false;button.removeAttribute('aria-busy');}
        finally{pending=false;}
        return true;
    }
    function onClick(action){if(action!=='bug-report-new')return false;lastId=0;render();return true;}
    return {render,onSubmit,onClick};
};
