/* One audio context for the app. No timers, external samples or background playback. */
(() => {
    'use strict';
    let active=null;
    function create({base=''}) {
        const storageKey=`conquer:audio:v1:${base}`;
        const defaults={music:true,effects:true,rallyAlerts:true,muted:false,musicVolume:20,effectsVolume:45};
        let settings={...defaults};
        try{const saved=JSON.parse(localStorage.getItem(storageKey));for(const key of Object.keys(defaults)){if(typeof defaults[key]==='boolean'&&typeof saved?.[key]==='boolean')settings[key]=saved[key];else if(typeof defaults[key]==='number'&&Number.isFinite(saved?.[key]))settings[key]=Math.max(0,Math.min(100,saved[key]));}}catch{}
        const Context=window.AudioContext||window.webkitAudioContext;
        let context=null,musicGain=null,effectsGain=null,musicBuffer=null,musicSource=null,loading=null,controller=null,resuming=null;
        let unlocked=false,loadFailed=false,previous=null,lastEffect=-Infinity,lastSound=null,playedCount=0,destroyed=false,pageActive=true,musicOffset=0,musicStartedAt=0;
        let rallySnapshot=null,rallyBaseline=true;
        const escape=value=>String(value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
        const text=(key,fallback)=>window.ConquerLocale?.has?.(key)?window.ConquerLocale.t(key):fallback;
        const voices=new Set();
        const allowed=()=>!destroyed&&pageActive&&!document.hidden&&!settings.muted;
        const wantsMusic=()=>allowed()&&settings.music&&settings.musicVolume>0;
        const audible=()=>allowed()&&((settings.music&&settings.musicVolume>0)||(settings.effects&&settings.effectsVolume>0));
        function save(){try{localStorage.setItem(storageKey,JSON.stringify(settings));}catch{}}
        function stopEffects(){for(const node of voices){try{node.stop();}catch{}}voices.clear();}
        function stopMusic(){if(!musicSource)return;musicOffset=(musicOffset+context.currentTime-musicStartedAt)%musicBuffer.duration;try{musicSource.stop();}catch{}musicSource.disconnect();musicSource=null;}
        function status(){return {supported:Boolean(Context),unlocked,context:context?.state||'idle',musicPlaying:Boolean(musicSource&&context?.state==='running'&&wantsMusic()),musicLoaded:Boolean(musicBuffer),loading:Boolean(loading),loadFailed,voices:voices.size,lastSound,playedCount,settings:{...settings}};}
        function updateControls(){
            if(destroyed)return;
            for(const panel of document.querySelectorAll('.audio-settings')){
                for(const input of panel.querySelectorAll('[data-audio-setting]')){const value=settings[input.dataset.audioSetting];if(input.type==='checkbox')input.checked=value;else{input.value=value;panel.querySelector(`[data-audio-value="${input.dataset.audioSetting}"]`).textContent=`${Math.round(value)} %`;}}
                const mute=panel.querySelector('[data-audio-mute]');mute.textContent=settings.muted?'Ton einschalten':'Alles stummschalten';mute.setAttribute('aria-pressed',String(settings.muted));
                panel.querySelector('[data-audio-status]').textContent=!Context?'Ton ist auf diesem Gerät nicht verfügbar.':settings.muted?'Ton ist stumm.':loadFailed?'Die Musik konnte nicht geladen werden. Du kannst sie erneut starten.':status().musicPlaying?'Dorfmusik läuft.':loading?'Musik wird geladen …':!unlocked?'Ton startet nach deinem ersten Tippen.':document.hidden?'Ton pausiert im Hintergrund.':'Musik ist pausiert.';
                for(const button of panel.querySelectorAll('[data-audio-demo]'))button.disabled=!Context||settings.muted||!settings.effects||settings.effectsVolume===0;
            }
        }
        function gains(){if(!context)return;const time=context.currentTime;musicGain.gain.setTargetAtTime(wantsMusic()?settings.musicVolume/100:0,time,.08);effectsGain.gain.setTargetAtTime(allowed()&&settings.effects?settings.effectsVolume/100:0,time,.025);}
        async function loadMusic(){
            if(musicBuffer||loading||loadFailed||!wantsMusic()||!context)return;
            controller=new AbortController();const request=controller;
            loading=(async()=>{
                try{const response=await fetch(`${base}/assets/audio/village-meadow-v1.wav`,{credentials:'omit',cache:'force-cache',signal:request.signal});if(!response.ok)throw new Error('Audio unavailable');const bytes=await response.arrayBuffer();if(request.signal.aborted||destroyed)return;musicBuffer=await context.decodeAudioData(bytes);}
                catch(error){if(error.name!=='AbortError')loadFailed=true;}
                finally{loading=null;controller=null;if(!destroyed)sync();}
            })();updateControls();return loading;
        }
        function sync(){
            if(!context||destroyed){updateControls();return;}
            gains();
            if(!wantsMusic()){stopMusic();controller?.abort();}
            if(!audible()){
                stopEffects();controller?.abort();
                if(context.state==='running')context.suspend().catch(()=>{});
            }else if(unlocked){
                if(context.state!=='running'&&!resuming){resuming=context.resume().catch(()=>{}).finally(()=>{resuming=null;if(!audible()&&context.state==='running')context.suspend().catch(()=>{});updateControls();});}
                if(wantsMusic()&&musicBuffer&&!musicSource){musicSource=context.createBufferSource();musicSource.buffer=musicBuffer;musicSource.loop=true;musicSource.connect(musicGain);musicStartedAt=context.currentTime;musicSource.start(0,musicOffset);}
                if(wantsMusic()&&!musicBuffer&&!loading&&!loadFailed)loadMusic();
            }
            updateControls();
        }
        function unlock(){
            if(!Context||destroyed)return;
            unlocked=true;
            if(audible()&&!context){
                try{context=new Context({latencyHint:'balanced'});musicGain=context.createGain();effectsGain=context.createGain();musicGain.gain.value=0;effectsGain.gain.value=0;musicGain.connect(context.destination);effectsGain.connect(context.destination);context.addEventListener('statechange',updateControls);}
                catch{loadFailed=true;updateControls();return;}
            }
            sync();
        }
        function gesture(event){if(event.isTrusted&&(!unlocked||(audible()&&context?.state!=='running')))unlock();}
        function tone(midi,at,length,volume=.22,type='sine',endMidi=null){
            const oscillator=context.createOscillator(),gain=context.createGain();
            oscillator.type=type;oscillator.frequency.setValueAtTime(440*2**((midi-69)/12),at);
            if(endMidi!==null)oscillator.frequency.exponentialRampToValueAtTime(440*2**((endMidi-69)/12),at+length);
            gain.gain.setValueAtTime(0,at);gain.gain.linearRampToValueAtTime(volume,at+.015);gain.gain.exponentialRampToValueAtTime(.0001,at+length);
            oscillator.connect(gain);gain.connect(effectsGain);voices.add(oscillator);
            oscillator.onended=()=>{voices.delete(oscillator);oscillator.disconnect();gain.disconnect();};
            oscillator.start(at);oscillator.stop(at+length+.02);
        }
        function play(kind){
            if(!allowed()||!settings.effects||settings.effectsVolume===0||!unlocked||context?.state!=='running')return false;
            const now=context.currentTime;if(now-lastEffect<.18||voices.size>16)return false;
            const sequences={
                confirm:[[74,0,.16],[81,.08,.22]],
                training:[[62,0,.18,.20,'triangle'],[69,.11,.22,.18,'triangle'],[74,.23,.25]],
                building:[[52,0,.10,.30,'sine',40],[57,.14,.12,.24,'sine',45],[74,.28,.25,.16]],
                research:[[74,0,.26],[78,.14,.30],[81,.28,.36]],
                complete:[[74,0,.28],[78,.15,.32],[81,.30,.45]],
                trained:[[69,0,.22],[74,.14,.30],[78,.28,.42]],
                reward:[[74,0,.25],[78,.10,.28],[81,.20,.35],[86,.32,.45,.16]],
                rally:[[57,0,.22,.16,'triangle'],[64,.16,.26,.15,'triangle'],[69,.34,.38,.14,'triangle']],
                error:[[64,0,.15,.12],[62,.12,.22,.10]],
            };
            const notes=sequences[kind];if(!notes)return false;
            lastEffect=now;lastSound=kind;playedCount++;
            for(const [midi,delay,length,volume,type,end]of notes)tone(midi,now+.005+delay,length,volume,type,end);
            return true;
        }
        function confirmed(path,payload,result){
            if(!payload||path==='march/preview'||/^(?:auth|chat|world-chat)\//.test(path))return;
            if(path==='troops/train'||payload.action==='troops.train'||payload.action==='promote')play('training');
            else if(path==='city/upgrade-building'||path==='city/build-plot')play('building');
            else if(path==='research/start')play('research');
            else if(payload.action==='quest.claim'||payload.action==='chest.free'||result?.result?.drops?.length)play('reward');
            else play('confirm');
        }
        function observe(state){
            if(!state?.city)return;
            const snapshot={scope:`${state.city.player_id}:${state.city.world_id}`,time:Number(state.server_time),buildings:Object.fromEntries(Object.entries(state.buildings||{}).map(([key,value])=>[key,Number(value.level)])),trained:Number(state.trained_total||0),research:{...state.research}};
            if(previous?.scope===snapshot.scope&&snapshot.time-previous.time<300&&!state.return_summary){
                if(Object.entries(snapshot.buildings).some(([key,value])=>value>Number(previous.buildings[key]||0)))play('complete');
                else if(snapshot.trained>previous.trained)play('trained');
                else if(Object.entries(snapshot.research).some(([key,value])=>Number(value)>Number(previous.research[key]||0)))play('research');
            }
            previous=snapshot;
        }
        function observeRallies(rows,{playerId,worldId,allianceId,serverTime=Date.now()/1000}={}){
            if(destroyed||!Array.isArray(rows)||!Number(playerId)||!Number(worldId)||!Number.isFinite(Number(allianceId)))return false;
            const time=Date.now(),scope=`${Number(playerId)}:${Number(worldId)}:${Number(allianceId)}`;
            const previousRallies=rallySnapshot?.scope===scope?rallySnapshot:null;
            const highestId=Math.max(previousRallies?.highestId||0,...rows.map(row=>Number(row.id)||0));
            // Consume every successful snapshot, even while sound is unavailable. There is
            // never a deferred alert after unmuting, unlocking audio or returning to the app.
            rallySnapshot={scope,time,highestId};
            const baseline=rallyBaseline||!previousRallies||time-previousRallies.time>90000;
            rallyBaseline=false;
            if(baseline||!Number(allianceId)||!settings.rallyAlerts||!allowed())return false;
            const fresh=rows.some(row=>{
                if(Number(row.id)<=previousRallies.highestId||row.status!=='gathering'||Number(row.leader_player_id)===Number(playerId))return false;
                if((row.participants||[]).some(participant=>Number(participant.player_id)===Number(playerId)))return false;
                // The server supplies UTC creation time; ignore delayed old records.
                const raw=String(row.created_at||'').replace(' ','T');
                const created=Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(raw)?raw:raw+'Z')/1000;
                return Number.isFinite(created)&&Number(serverTime)-created>=-5&&Number(serverTime)-created<=90;
            });
            return fresh?play('rally'):false;
        }
        function controls(){
            return `<section class="panel audio-settings" aria-label="Musik und Klänge"><div class="audio-heading"><h2>Musik & Klänge</h2><button type="button" class="button secondary" data-audio-mute aria-pressed="${settings.muted}">${settings.muted?'Ton einschalten':'Alles stummschalten'}</button></div>
                ${[['music','Hintergrundmusik','Sanfte Dorfmusik mit Harfe und Flöte.'],['effects','Spieleffekte','Bestätigungen, Ausbildung, Ausbau und Belohnungen.']].map(([key,title,description])=>`<label class="setting-row"><span><strong>${title}</strong><small>${description}</small></span><input type="checkbox" data-audio-setting="${key}" ${settings[key]?'checked':''}></label><label class="audio-volume"><span>${key==='music'?'Musiklautstärke':'Effektlautstärke'}</span><input type="range" min="0" max="100" step="1" data-audio-setting="${key}Volume" value="${settings[key+'Volume']}" aria-label="${key==='music'?'Musiklautstärke':'Effektlautstärke'}"><output data-audio-value="${key}Volume">${Math.round(settings[key+'Volume'])} %</output></label>`).join('')}
                <label class="setting-row"><span><strong data-i18n="audio.rally_alerts">${escape(text('audio.rally_alerts','New rally alerts'))}</strong><small data-i18n="audio.rally_alerts_description">${escape(text('audio.rally_alerts_description','Play a short sound when an ally opens a rally while you are playing.'))}</small></span><input type="checkbox" data-audio-setting="rallyAlerts" ${settings.rallyAlerts?'checked':''}></label>
                <div class="audio-demos" aria-label="Klänge ausprobieren"><button type="button" class="button secondary" data-audio-start>Musik starten</button>${[['confirm','Bestätigung'],['training','Ausbildung'],['building','Ausbau'],['reward','Belohnung'],['rally',escape(text('audio.rally','Rally call'))]].map(([key,label])=>`<button type="button" class="button secondary" data-audio-demo="${key}" ${key==='rally'?'data-i18n="audio.rally"':''} ${settings.muted||!settings.effects||settings.effectsVolume===0?'disabled':''}>${label}</button>`).join('')}</div><p data-audio-status role="status"></p><small>Deine Toneinstellungen bleiben auf diesem Gerät gespeichert. Im Hintergrund pausiert der Ton.</small></section>`;
        }
        function input(event){const field=event.target.closest('[data-audio-setting]');if(!field)return;const key=field.dataset.audioSetting;if(!Object.hasOwn(defaults,key))return;settings[key]=field.type==='checkbox'?field.checked:Math.max(0,Math.min(100,Number(field.value)||0));if(event.type==='change')save();unlock();}
        function click(event){
            const button=event.target.closest('[data-audio-mute],[data-audio-start],[data-audio-demo]');if(!button)return;
            if(button.hasAttribute('data-audio-mute')){settings.muted=!settings.muted;save();unlock();}
            else if(button.hasAttribute('data-audio-start')){settings.music=true;settings.muted=false;loadFailed=false;save();unlock();}
            else{unlock();Promise.resolve(resuming).then(()=>play(button.dataset.audioDemo));}
        }
        function leave(){rallyBaseline=true;pageActive=false;stopEffects();stopMusic();controller?.abort();if(context&&context.state==='running')context.suspend().catch(()=>{});}
        function enter(){rallyBaseline=true;pageActive=true;sync();}
        function visibility(){rallyBaseline=true;sync();}
        document.addEventListener('pointerdown',gesture,{capture:true,passive:true});document.addEventListener('keydown',gesture,{capture:true});
        document.addEventListener('click',click);document.addEventListener('input',input);document.addEventListener('change',input);
        document.addEventListener('visibilitychange',visibility);window.addEventListener('pagehide',leave);window.addEventListener('pageshow',enter);
        const instance={play,confirmed,observe,observeRallies,controls,status,updateControls,destroy(){destroyed=true;leave();context?.removeEventListener('statechange',updateControls);context?.close().catch(()=>{});document.removeEventListener('pointerdown',gesture,true);document.removeEventListener('keydown',gesture,true);document.removeEventListener('click',click);document.removeEventListener('input',input);document.removeEventListener('change',input);document.removeEventListener('visibilitychange',visibility);window.removeEventListener('pagehide',leave);window.removeEventListener('pageshow',enter);}};
        active=instance;return instance;
    }
    window.ConquerAudio={create,status:()=>active?.status()};
})();
