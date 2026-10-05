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
        let context=null,musicGain=null,effectsGain=null,musicBuffer=null,musicSource=null,loading=null,controller=null,resuming=null,noiseBuffer=null;
        let unlocked=false,loadFailed=false,previous=null,lastEffect=-Infinity,lastSound=null,playedCount=0,destroyed=false,pageActive=true,musicOffset=0,musicStartedAt=0;
        let rallySnapshot=null,rallyBaseline=true;
        const escape=value=>String(value).replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
        const text=(key,fallback)=>window.ConquerLocale?.has?.(key)?window.ConquerLocale.t(key):fallback;
        const voices=new Set();
        const confirmedOperations=new Set();
        const demos=[['confirm','Confirmation'],['training','Training'],['building','Upgrade'],['research','Research'],['complete','Upgrade complete'],['trained','Training complete'],['march','Troops deployed'],['attack','Attack dispatched'],['gather','Gathering dispatched'],['scout','Scouts deployed'],['recall','Troops recalled'],['heal','Healing'],['chest','Open chest'],['reward','Reward'],['purchase','Purchase'],['speedup','Speedup'],['equip','Equip relic'],['craft','Relic upgrade'],['teleport','Teleport'],['rally','Rally call'],['error','Action failed']];
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
                const mute=panel.querySelector('[data-audio-mute]');mute.dataset.i18n=settings.muted?'audio.unmute':'audio.mute';mute.textContent=text(mute.dataset.i18n,settings.muted?'Enable sound':'Mute all sound');mute.setAttribute('aria-pressed',String(settings.muted));
                const statusKey=!Context?'unavailable':settings.muted?'muted':loadFailed?'failed':status().musicPlaying?'playing':loading?'loading':!unlocked?'gesture':!pageActive||document.hidden?'background':'paused';
                const statusElement=panel.querySelector('[data-audio-status]');statusElement.dataset.i18n='audio.'+statusKey;statusElement.textContent=text('audio.'+statusKey,'Sound starts after your first tap.');
                for(const button of panel.querySelectorAll('[data-audio-demo]'))button.disabled=!Context||settings.muted||!settings.effects||settings.effectsVolume===0;
            }
        }
        function gains(){if(!context)return;const time=context.currentTime;musicGain.gain.setTargetAtTime(wantsMusic()?settings.musicVolume/100:0,time,.08);effectsGain.gain.setTargetAtTime(allowed()&&settings.effects?settings.effectsVolume/100:0,time,.025);}
        async function loadMusic(){
            if(musicBuffer||loading||loadFailed||!wantsMusic()||!context)return;
            controller=new AbortController();const request=controller;
            loading=(async()=>{
                try{const response=await fetch(`${base}/assets/audio/village-daylight-v1.wav`,{credentials:'omit',cache:'force-cache',signal:request.signal});if(!response.ok)throw new Error('Audio unavailable');const bytes=await response.arrayBuffer();if(request.signal.aborted||destroyed)return;musicBuffer=await context.decodeAudioData(bytes);}
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
        function tone(midi,at,length,volume=.22,type='sine',endMidi=null,envelope='soft'){
            // Warm horn harmonics for armies; bell overtones and plucks for storybook magic.
            if(type==='horn'){
                tone(midi,at,length,volume*.78,'triangle',endMidi,'horn');
                tone(midi+12,at,length*.92,volume*.16,'sine',endMidi===null?null:endMidi+12,'horn');
                tone(midi+19,at,length*.8,volume*.06,'sine',endMidi===null?null:endMidi+19,'horn');return;
            }
            if(type==='bell'){
                tone(midi,at,length,volume*.75,'sine');
                tone(midi+19.1,at,length*.55,volume*.18,'sine');
                tone(midi+28.4,at,length*.32,volume*.07,'sine');return;
            }
            if(type==='pluck'){
                tone(midi,at,length,volume*.8,'triangle',endMidi,'pluck');
                tone(midi+12,at,length*.48,volume*.2,'sine',endMidi===null?null:endMidi+12,'pluck');return;
            }
            if(type==='pop'){type='sine';envelope='pop';}
            if(type==='drum'){type='sine';envelope='drum';}
            if(voices.size>=24)return;
            const oscillator=context.createOscillator(),gain=context.createGain();
            oscillator.type=type;oscillator.frequency.setValueAtTime(440*2**((midi-69)/12),at);
            if(endMidi!==null)oscillator.frequency.exponentialRampToValueAtTime(440*2**((endMidi-69)/12),at+length);
            const attack=envelope==='horn'?.035:envelope==='drum'||envelope==='pop'?.003:envelope==='pluck'?.005:.012;
            gain.gain.setValueAtTime(0,at);gain.gain.linearRampToValueAtTime(volume,at+Math.min(attack,length/3));
            if(envelope==='horn')gain.gain.linearRampToValueAtTime(volume*.65,at+length*.45);
            gain.gain.exponentialRampToValueAtTime(.0001,at+length);
            oscillator.connect(gain);gain.connect(effectsGain);voices.add(oscillator);
            oscillator.onended=()=>{voices.delete(oscillator);oscillator.disconnect();gain.disconnect();};
            oscillator.start(at);oscillator.stop(at+length+.02);
        }
        // One small reusable noise buffer gives footsteps, wood and metal a soft texture.
        function noise(at,length,volume,frequency,endFrequency=frequency){
            if(!noiseBuffer){
                noiseBuffer=context.createBuffer(1,Math.ceil(context.sampleRate*.8),context.sampleRate);
                const samples=noiseBuffer.getChannelData(0);let seed=731;
                for(let i=0;i<samples.length;i++){seed=(1664525*seed+1013904223)>>>0;samples[i]=seed/2147483648-1;}
            }
            const source=context.createBufferSource(),filter=context.createBiquadFilter(),gain=context.createGain();
            source.buffer=noiseBuffer;filter.type='bandpass';filter.Q.value=.7;filter.frequency.setValueAtTime(frequency,at);filter.frequency.exponentialRampToValueAtTime(endFrequency,at+length);
            gain.gain.setValueAtTime(0,at);gain.gain.linearRampToValueAtTime(volume,at+.008);gain.gain.exponentialRampToValueAtTime(.0001,at+length);
            source.connect(filter);filter.connect(gain);gain.connect(effectsGain);voices.add(source);
            source.onended=()=>{voices.delete(source);source.disconnect();filter.disconnect();gain.disconnect();};source.start(at);source.stop(at+length+.02);
        }
        function play(kind){
            if(!allowed()||!settings.effects||settings.effectsVolume===0||!unlocked||context?.state!=='running')return false;
            const now=context.currentTime;if(now-lastEffect<.18||voices.size>16)return false;
            const sequences={
                confirm:[[79,0,.12,.21,'pop',72],[83,.085,.24,.17,'pluck']],
                training:[[46,0,.18,.30,'drum',30],[53,.17,.19,.23,'drum',34],[60,.32,.27,.20,'horn'],[67,.43,.36,.16,'horn']],
                building:[[47,0,.22,.36,'drum',27],[52,.20,.24,.31,'drum',31],[67,.40,.32,.18,'pluck']],
                research:[[72,0,.42,.18,'bell'],[76,.14,.45,.17,'bell'],[79,.28,.58,.16,'bell'],[84,.44,.52,.10,'bell']],
                complete:[[48,0,.25,.26,'drum',30],[60,.10,.42,.18,'horn'],[67,.25,.42,.17,'horn'],[84,.43,.58,.20,'bell']],
                trained:[[48,0,.20,.29,'drum',29],[60,.12,.28,.20,'horn'],[64,.27,.32,.18,'horn'],[67,.43,.42,.18,'horn']],
                reward:[[67,0,.18,.23,'pop',72],[79,.10,.44,.22,'bell'],[83,.22,.46,.20,'bell'],[86,.34,.58,.18,'bell'],[91,.48,.62,.12,'bell']],
                rally:[[43,0,.26,.34,'drum',27],[55,.06,.33,.22,'horn'],[62,.25,.34,.22,'horn'],[67,.45,.53,.23,'horn']],
                march:[[45,0,.19,.30,'drum',28],[45,.21,.19,.26,'drum',28],[55,.38,.28,.20,'horn'],[62,.51,.35,.18,'horn']],
                attack:[[44,0,.31,.38,'drum',23],[51,.22,.26,.29,'drum',29],[55,.32,.34,.21,'horn'],[62,.44,.40,.18,'horn']],
                gather:[[67,0,.15,.20,'pop',60],[72,.14,.21,.20,'pluck'],[76,.29,.34,.17,'pluck'],[84,.40,.36,.10,'bell']],
                scout:[[79,0,.26,.17,'pluck',84],[86,.20,.40,.13,'bell']],
                recall:[[62,0,.26,.18,'horn'],[55,.20,.33,.17,'horn'],[48,.40,.23,.24,'drum',29],[72,.46,.30,.11,'pluck']],
                heal:[[72,0,.55,.15,'bell'],[76,.20,.56,.15,'bell'],[79,.40,.65,.13,'bell']],
                chest:[[50,0,.23,.25,'drum',35],[67,.12,.17,.18,'pop',74],[79,.28,.48,.20,'bell'],[84,.43,.61,.18,'bell'],[88,.54,.58,.11,'bell']],
                purchase:[[84,0,.28,.20,'bell'],[91,.11,.33,.16,'bell'],[76,.28,.19,.19,'pop',72]],
                speedup:[[69,0,.14,.18,'pop',81],[81,.105,.17,.16,'pop',93],[88,.23,.35,.14,'bell'],[93,.32,.42,.09,'bell']],
                equip:[[57,0,.18,.24,'drum',40],[76,.12,.26,.18,'pluck'],[84,.26,.44,.14,'bell']],
                craft:[[47,0,.18,.32,'drum',28],[54,.21,.20,.28,'drum',32],[67,.36,.31,.16,'horn'],[84,.49,.55,.18,'bell']],
                teleport:[[48,0,.52,.21,'horn',72],[67,.15,.24,.17,'pop',79],[79,.34,.48,.17,'bell'],[86,.49,.64,.15,'bell'],[91,.58,.57,.09,'bell']],
                error:[[67,0,.15,.18,'pop',55],[60,.14,.23,.15,'pluck',53]],
            };
            const notes=sequences[kind];if(!notes)return false;
            lastEffect=now;lastSound=kind;playedCount++;
            for(const [midi,delay,length,volume,type,end]of notes)tone(midi,now+.005+delay,length,volume,type,end);
            const textures={training:[[0,.07,.12,700],[.17,.07,.09,900]],building:[[0,.12,.21,520,300],[.20,.11,.17,750,400]],rally:[[0,.14,.14,350,220]],march:[[0,.10,.14,380],[.21,.10,.12,420]],attack:[[.02,.34,.20,2800,380],[.22,.10,.13,600,300]],gather:[[0,.08,.09,850],[.14,.08,.07,1100]],scout:[[0,.27,.06,1400,3200]],chest:[[0,.20,.14,450,900]],purchase:[[0,.05,.07,3400],[.11,.06,.05,3900]],equip:[[0,.10,.12,2400,1200]],craft:[[0,.09,.20,1900,750],[.21,.09,.17,2300,900]],teleport:[[0,.72,.12,320,3400]]};
            for(const [delay,length,volume,frequency,end]of textures[kind]||[])noise(now+.005+delay,length,volume,frequency,end);
            return true;
        }
        function confirmed(path,payload,result){
            if(!payload||/\/(?:preview|simulate)$/.test(path)||payload.action==='preview'||/^(?:auth|chat|world-chat|notifications)\//.test(path))return false;
            const outcome=result?.result||result||{},action=payload.action||'';
            // Consume receipts even while muted; retries never replay an old success.
            const operation=payload.operation_key||payload.request_id;
            if(operation){const key=`${payload.expected_world_id||''}:${path}:${action}:${operation}`;if(confirmedOperations.has(key))return false;confirmedOperations.add(key);if(confirmedOperations.size>128)confirmedOperations.delete(confirmedOperations.values().next().value);}
            if(outcome.duplicate||outcome.replayed)return false;
            let kind='confirm';
            if(path==='troops/train'||['troops.train','promote','promotion.start'].includes(action))kind='training';
            else if(path==='city/upgrade-building'||path==='city/build-plot'||action==='wall.repair')kind='building';
            else if(path==='research/start')kind='research';
            else if(path==='hospital/heal'||action==='hospital.heal')kind='heal';
            else if(path==='hospital/speedup'||action==='hospital.speedup')kind='speedup';
            else if(path==='march/dispatch-gather'||path==='march/dispatch-charm')kind='gather';
            else if(path==='march/recall'||['reinforcement.recall','recall'].includes(action))kind='recall';
            else if(action==='scout'||action==='support'&&payload.kind==='scout')kind='scout';
            else if(['rally/start','rally/start-monster'].includes(path)||path==='territory/action'&&action==='start')kind='rally';
            else if(['march/dispatch','march/dispatch-player','march/dispatch-field-attack','march/dispatch-neutral-village'].includes(path)||/^shrines\/\d+\/attack$/.test(path)||path==='expeditions/action'&&action==='dispatch'||path==='arena.accept'||action==='arena.accept')kind='attack';
            else if(path==='rally/join'||['reinforce','structure.garrison'].includes(action)||/^shrines\/\d+\/garrison$/.test(path)||path==='territory/action'&&action==='join')kind='march';
            else if(action==='rune_teleport'||action==='inventory.use'&&outcome.teleport_mode)kind='teleport';
            else if(['treasure.equip','treasure.unequip','treasure.preset_apply','city_skin.equip','name_frame.equip'].includes(action))kind='equip';
            else if(['treasure.upgrade_effect','treasure.exchange_fragments'].includes(action))kind='craft';
            else if(path==='market/action'||['trading.buy','inventory.buy','crystal.buy'].includes(action))kind='purchase';
            else if(action==='chest.free')kind='chest';
            else if(action==='inventory.use')kind=Number(outcome.seconds)>0?'speedup':outcome.drops?.length?'chest':outcome.resource?'gather':'confirm';
            else if(['quest.claim','vip.daily','claim'].includes(action)||outcome.drops?.length)kind='reward';
            return play(kind);
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
            const label=(key,fallback)=>escape(text('audio.'+key,fallback));
            return `<section class="panel audio-settings" aria-label="${label('label','Music and sounds')}" data-i18n-attrs="aria-label:audio.label"><div class="audio-heading"><h2 data-i18n="audio.title">${label('title','Music & sounds')}</h2><button type="button" class="button secondary" data-audio-mute data-i18n="audio.${settings.muted?'unmute':'mute'}" aria-pressed="${settings.muted}">${settings.muted?label('unmute','Enable sound'):label('mute','Mute all sound')}</button></div>
                ${[['music','Background music','Gentle village music with harp and flute.'],['effects','Game sounds','Short sounds for building, troops, gathering, healing, items and rewards.']].map(([key,title,description])=>`<label class="setting-row"><span><strong data-i18n="audio.${key}">${label(key,title)}</strong><small data-i18n="audio.${key}_description">${label(key+'_description',description)}</small></span><input type="checkbox" data-audio-setting="${key}" ${settings[key]?'checked':''}></label><label class="audio-volume"><span data-i18n="audio.${key}_volume">${label(key+'_volume',key==='music'?'Music volume':'Sound effects volume')}</span><input type="range" min="0" max="100" step="1" data-audio-setting="${key}Volume" value="${settings[key+'Volume']}" aria-label="${label(key+'_volume',key==='music'?'Music volume':'Sound effects volume')}" data-i18n-attrs="aria-label:audio.${key}_volume"><output data-audio-value="${key}Volume">${Math.round(settings[key+'Volume'])} %</output></label>`).join('')}
                <label class="setting-row"><span><strong data-i18n="audio.rally_alerts">${escape(text('audio.rally_alerts','New rally alerts'))}</strong><small data-i18n="audio.rally_alerts_description">${escape(text('audio.rally_alerts_description','Play a short sound when an ally opens a rally while you are playing.'))}</small></span><input type="checkbox" data-audio-setting="rallyAlerts" ${settings.rallyAlerts?'checked':''}></label>
                <div class="audio-demos" aria-label="${label('try','Try the sounds')}" data-i18n-attrs="aria-label:audio.try"><button type="button" class="button secondary" data-audio-start data-i18n="audio.start">${label('start','Start music')}</button>${demos.map(([key,fallback])=>`<button type="button" class="button secondary" data-audio-demo="${key}" data-i18n="audio.${key}" ${!Context||settings.muted||!settings.effects||settings.effectsVolume===0?'disabled':''}>${label(key,fallback)}</button>`).join('')}</div><p data-audio-status role="status"></p><small data-i18n="audio.saved">${label('saved','Your sound settings are saved on this device. Sound pauses in the background.')}</small></section>`;
        }
        function input(event){const field=event.target.closest('[data-audio-setting]');if(!field)return;const key=field.dataset.audioSetting;if(!Object.hasOwn(defaults,key))return;settings[key]=field.type==='checkbox'?field.checked:Math.max(0,Math.min(100,Number(field.value)||0));if(event.type==='change')save();unlock();}
        function click(event){
            const button=event.target.closest('[data-audio-mute],[data-audio-start],[data-audio-demo]');if(!button)return;
            if(button.hasAttribute('data-audio-mute')){settings.muted=!settings.muted;save();unlock();}
            else if(button.hasAttribute('data-audio-start')){settings.music=true;settings.muted=false;loadFailed=false;save();unlock();}
            else{unlock();Promise.resolve(resuming).then(()=>play(button.dataset.audioDemo));}
        }
        function leave(){previous=null;rallyBaseline=true;pageActive=false;stopEffects();stopMusic();controller?.abort();if(context&&context.state==='running')context.suspend().catch(()=>{});}
        function enter(){previous=null;rallyBaseline=true;pageActive=true;sync();}
        function visibility(){previous=null;rallyBaseline=true;sync();}
        document.addEventListener('pointerdown',gesture,{capture:true,passive:true});document.addEventListener('keydown',gesture,{capture:true});
        document.addEventListener('click',click);document.addEventListener('input',input);document.addEventListener('change',input);
        document.addEventListener('visibilitychange',visibility);window.addEventListener('pagehide',leave);window.addEventListener('pageshow',enter);
        const instance={play,confirmed,observe,observeRallies,controls,status,updateControls,destroy(){destroyed=true;leave();context?.removeEventListener('statechange',updateControls);context?.close().catch(()=>{});document.removeEventListener('pointerdown',gesture,true);document.removeEventListener('keydown',gesture,true);document.removeEventListener('click',click);document.removeEventListener('input',input);document.removeEventListener('change',input);document.removeEventListener('visibilitychange',visibility);window.removeEventListener('pagehide',leave);window.removeEventListener('pageshow',enter);}};
        active=instance;return instance;
    }
    window.ConquerAudio={create,status:()=>active?.status()};
})();
