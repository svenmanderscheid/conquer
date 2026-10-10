/* Browser Web Push and Android FCM share explicit opt-in and account-scoped settings. */
(() => {
    'use strict';
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    function platform() {
        if(window.Capacitor?.isNativePlatform?.())return window.Capacitor.getPlatform?.()==='android'&&window.Capacitor.Plugins?.PushNotifications?'android':'native';
        if(!window.isSecureContext)return 'insecure';
        const ios=/iPad|iPhone|iPod/.test(navigator.userAgent||'')||(navigator.platform==='MacIntel'&&navigator.maxTouchPoints>1);
        if(ios&&!navigator.standalone&&!window.matchMedia?.('(display-mode: standalone)').matches)return 'ios_install';
        if(!window.Notification||!window.PushManager||!navigator.serviceWorker)return 'unsupported';
        return 'web';
    }
    function keyBytes(value) {
        const base64=String(value).replace(/-/g,'+').replace(/_/g,'/');
        return Uint8Array.from(atob(base64+'='.repeat((4-base64.length%4)%4)),char=>char.charCodeAt(0));
    }
    function create({base='',api,getPlayerId=()=>0,onOpen=()=>{location.hash='city';}}={}) {
        base=String(base).replace(/\/$/,'');
        const scope=new URL(base+'/',location.href).href;
        let playerId=0,generation=0,registration=null,subscription=null,server=null,reading=null,working=null,destroyed=false,closing=false;
        let status='loading',notice='',lastRead=0;
        const native=window.Capacitor?.Plugins?.PushNotifications,nativeKey='union-of-kingdoms:push:android:'+base;
        let nativePermission='prompt',nativeListeners=null,nativePending=null,nativeRotation=null;
        const nativeHandles=[];
        const supported=()=>['web','android'].includes(platform());
        const permission=()=>platform()==='android'?nativePermission:window.Notification?.permission;
        const devicePayload=sub=>platform()==='android'?{platform:'android',token:sub.endpoint}:{endpoint:sub.endpoint};
        function storedNative(){try{return JSON.parse(localStorage.getItem(nativeKey)||'null');}catch{return null;}}
        function saveNative(token){localStorage.setItem(nativeKey,JSON.stringify({playerId,token}));}
        function nativeSubscription(token){return token?{endpoint:token,toJSON:()=>({platform:'android',token}),async unsubscribe(){await native.unregister();try{localStorage.removeItem(nativeKey);}catch{}try{await native.removeAllDeliveredNotifications?.();}catch{}return true;}}:null;}
        async function listenNative() {
            if(nativeListeners)return nativeListeners;
            nativeListeners=(async()=>{
                for(const [event,handler]of Object.entries({
                    registration:({value})=>{
                        if(typeof value!=='string'||!value||destroyed)return;
                        if(nativePending){nativePending.resolve(nativeSubscription(value));return;}
                        if(closing)return;
                        // Only an already opted-in, same-account device may refresh its token.
                        const stored=storedNative();
                        if(status!=='enabled'||stored?.playerId!==playerId||stored.token===value||nativeRotation)return;
                        const version=generation,oldToken=stored.token;
                        nativeRotation=(async()=>{
                            try{
                                const result=await api('push/subscribe',{platform:'android',token:value,previous_token:oldToken,locale:window.ConquerLocale?.locale||'en',expected_player_id:playerId,preferences:server?.preferences||{completions:true,security:true}});
                                if(!current(version))return;
                                if(Number(result?.player_id)!==playerId){status='account_changed';server=null;return;}
                                saveNative(value);subscription=nativeSubscription(value);
                            }catch{if(current(version)){status='failed';notice='failed_action';}}
                            finally{nativeRotation=null;update();}
                        })();
                    },
                    registrationError:()=>{if(nativePending)nativePending.reject(new Error('native_registration_failed'));},
                    pushNotificationActionPerformed:()=>{if(!destroyed)onOpen();}
                })) {
                    const handle=await native.addListener(event,handler);
                    if(destroyed)await handle.remove();else nativeHandles.push(handle);
                }
                await native.createChannel?.({id:'uok_game',name:t('channel'),importance:4,visibility:0});
            })().catch(async error=>{for(const handle of nativeHandles.splice(0))await handle.remove();nativeListeners=null;throw error;});
            return nativeListeners;
        }
        async function nativeRegister() {
            await listenNative();
            return new Promise((resolve,reject)=>{
                const complete=(fn,value)=>{clearTimeout(timer);nativePending=null;fn(value);};
                const timer=setTimeout(()=>complete(reject,new Error('native_registration_timeout')),20000);
                nativePending={resolve:sub=>complete(resolve,sub),reject:error=>complete(reject,error)};
                Promise.resolve(native.register()).catch(error=>nativePending?.reject(error));
            });
        }
        const t=key=>window.ConquerLocale?.t('push.settings.'+key)||key;
        const current=version=>!destroyed&&!closing&&version===generation&&Number(getPlayerId())===playerId;
        const state=()=>({status,notice,busy:Boolean(working||reading||nativeRotation),enabled:status==='enabled',playerId,platform:platform()});
        function view() {
            const capability=platform(),display=!supported()?capability:status;
            return {display,busy:Boolean(working||reading||nativeRotation),enable:supported()&&server?.available===true&&permission()!=='denied'&&status==='disabled',disable:supported()&&Boolean(subscription)&&!['account_changed','loading'].includes(status),test:status==='enabled'&&server?.available===true};
        }
        function update() {
            if(destroyed)return;
            const v=view();
            for(const card of document.querySelectorAll('.device-notification-settings')) {
                const line=card.querySelector('[data-device-notification-status]');
                line.dataset.state=v.display;line.dataset.i18n='push.settings.'+v.display;line.textContent=t(v.display);
                const feedback=card.querySelector('[data-device-notification-feedback]');
                feedback.hidden=!notice;feedback.textContent=notice?t(notice):'';feedback.dataset.i18n=notice?'push.settings.'+notice:'';
                card.setAttribute('aria-busy',String(v.busy));
                for(const button of card.querySelectorAll('[data-device-notification]')) {
                    const action=button.dataset.deviceNotification;
                    button.disabled=v.busy||(action!=='refresh'&&!v[action]);
                    if(action==='enable')button.hidden=v.display==='enabled';
                    if(action==='disable'||action==='test')button.hidden=!v.disable;
                }
            }
        }
        async function getRegistration() {
            if(registration?.active)return registration;
            registration=await navigator.serviceWorker.getRegistration(scope);
            if(!registration||registration.scope!==scope)registration=await navigator.serviceWorker.register(base+'/service-worker.js',{scope:base+'/',updateViaCache:'none'});
            if(!registration.active) {
                const worker=registration.installing||registration.waiting;
                if(!worker)throw new Error('worker_unavailable');
                await new Promise((resolve,reject)=>{
                    const finish=error=>{clearTimeout(timer);worker.removeEventListener('statechange',changed);error?reject(error):resolve();};
                    const changed=()=>{if(worker.state==='activated')finish();else if(worker.state==='redundant')finish(new Error('worker_unavailable'));};
                    const timer=setTimeout(()=>finish(new Error('worker_timeout')),10000);
                    worker.addEventListener('statechange',changed);changed();
                });
            }
            return registration;
        }
        async function read() {
            const version=generation;
            if(!supported()){status=platform();update();return state();}
            try {
                let sub;
                if(platform()==='android'){
                    await listenNative();nativePermission=(await native.checkPermissions()).receive;
                    sub=nativeSubscription(storedNative()?.token);
                }else{
                    const reg=await getRegistration();sub=await reg.pushManager.getSubscription();
                }
                // Subscription identifiers are secrets: keep them out of URLs and access logs.
                const result=sub?await api('push/status',{...devicePayload(sub),expected_player_id:playerId}):await api('push/status'+(platform()==='android'?'?platform=android':''));
                if(!current(version))return state();
                if(Number(result.player_id)!==playerId){status='account_changed';server=null;update();return state();}
                subscription=sub;server=result;
                // An existing endpoint belongs to one account. Never silently move it on sign-in.
                if(sub&&result.available&&result.enabled!==true) {
                    if(!await sub.unsubscribe())throw new Error('unsubscribe_failed');
                    if(!current(version))return state();
                    subscription=null;
                }
                status=!result.available?'unconfigured':permission()==='denied'?'denied':subscription&&result.enabled?'enabled':'disabled';
                if(platform()==='android'&&status==='enabled'&&permission()==='granted'){
                    saveNative(subscription.endpoint);
                    await native.register();
                }
                lastRead=Date.now();
            }catch{if(current(version))status='failed';}
            update();return state();
        }
        function refresh() {
            if(!playerId||destroyed||closing||working||nativeRotation)return Promise.resolve(state());
            if(reading)return reading;
            const task=read().finally(()=>{if(reading===task)reading=null;update();});reading=task;update();return task;
        }
        function syncAccount(id) {
            if(closing||destroyed)return Promise.resolve(state());
            id=Number(id)||0;
            if(id===playerId)return reading||Promise.resolve(state());
            playerId=id;generation++;server=null;subscription=null;notice='';status='loading';lastRead=0;
            // A previous request may still return, but cannot paint or bind the new account.
            reading=null;update();return id?refresh():Promise.resolve(state());
        }
        function finish(version,error,success) {
            if(current(version)) {
                notice=error?'failed_action':success||'';
                if(permission()==='denied')status='denied';
            }
            update();
        }
        function enable(event) {
            if(!event?.isTrusted||!view().enable||view().busy||closing)return Promise.resolve(false);
            const version=generation,id=playerId;
            let permissionRequest;
            // This call must stay in the original click stack, before any network await.
            try{permissionRequest=permission()==='granted'?Promise.resolve('granted'):platform()==='android'?native.requestPermissions().then(result=>nativePermission=result.receive):window.Notification.requestPermission();}
            catch{notice='failed_action';update();return Promise.resolve(false);}
            notice='';
            working=(async()=>{
                let created=null;
                try {
                    const allowed=await permissionRequest;
                    if(!current(version))return false;
                    if(allowed!=='granted'){status=allowed==='denied'?'denied':'disabled';notice=allowed==='denied'?'':'dismissed';return false;}
                    const reg=platform()==='android'?null:await getRegistration();
                    if(!current(version))return false;
                    const existing=reg?await reg.pushManager.getSubscription():nativeSubscription(storedNative()?.token);
                    const sub=existing||(reg?await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:keyBytes(server.public_key)}):await nativeRegister());
                    if(!existing)created=sub;
                    if(!current(version)){if(created)await created.unsubscribe();return false;}
                    await api('push/subscribe',{...(platform()==='android'?devicePayload(sub):{subscription:sub.toJSON()}),locale:window.ConquerLocale?.locale||'en',preferences:{completions:true,security:true},expected_player_id:id});
                    if(!current(version)){if(created)await created.unsubscribe();return false;}
                    if(platform()==='android')saveNative(sub.endpoint);
                    subscription=sub;server.enabled=true;status='enabled';notice='';return true;
                }catch {
                    if(created)try{await created.unsubscribe();}catch{}
                    if(current(version)){subscription=null;status=permission()==='denied'?'denied':'disabled';notice='failed_action';}
                    return false;
                }finally{working=null;update();if(version!==generation&&playerId)refresh();}
            })();update();return working;
        }
        function disable() {
            if(!view().disable||view().busy||closing)return Promise.resolve(false);
            const version=generation,sub=subscription,id=playerId;notice='';
            working=(async()=>{
                // Remove server delivery first, retaining a retryable endpoint on a failure.
                try{
                    await api('push/unsubscribe',{...devicePayload(sub),expected_player_id:id});
                    if(!current(version))return false;
                    if(!await sub.unsubscribe())throw new Error('unsubscribe_failed');
                    if(!current(version))return false;
                    subscription=null;status=permission()==='denied'?'denied':'disabled';
                    if(server)server.enabled=false;finish(version,null,'disabled_done');return true;
                }catch(error){finish(version,error);return false;}
                finally{working=null;update();if(version!==generation&&playerId)refresh();}
            })();update();return working;
        }
        function test() {
            if(!view().test||view().busy||closing)return Promise.resolve(false);
            const version=generation,sub=subscription,id=playerId;notice='';
            working=(async()=>{
                try{await api('push/test',{...devicePayload(sub),expected_player_id:id});finish(version,null,'test_sent');return true;}
                catch(error){finish(version,error);return false;}
                finally{working=null;update();if(version!==generation&&playerId)refresh();}
            })();update();return working;
        }
        async function logout() {
            closing=true;generation++;playerId=0;notice='';server=null;status='disabled';
            if(!supported())return null;
            let sub=subscription;
            try{sub=sub||(platform()==='android'?nativeSubscription(storedNative()?.token):await(await getRegistration()).pushManager.getSubscription());if(sub)await sub.unsubscribe();}catch{}
            subscription=null;update();return sub?.endpoint||null;
        }
        function cancelLogout(){if(!destroyed){closing=false;return syncAccount(getPlayerId());}return Promise.resolve(state());}
        function click(event) {
            const button=event.target.closest?.('[data-device-notification]');
            if(!button||button.disabled||!event.isTrusted)return;
            const action=button.dataset.deviceNotification;
            if(action==='enable')enable(event);else if(action==='disable')disable();else if(action==='test')test();else if(action==='refresh'){notice='';refresh();}
        }
        function controls() {
            const v=view(),text=key=>escape(t(key));
            return `<section class="panel device-notification-settings" aria-labelledby="device-notification-title" aria-busy="${v.busy}"><h2 id="device-notification-title" data-i18n="push.settings.title">${text('title')}</h2><p class="muted" data-i18n="push.settings.description">${text('description')}</p><p class="device-notification-status" data-device-notification-status data-state="${v.display}" role="status" data-i18n="push.settings.${v.display}">${text(v.display)}</p><div class="button-row device-notification-actions">${['enable','disable','test','refresh'].map(action=>`<button type="button" class="button ${action==='enable'?'primary':'secondary'}" data-device-notification="${action}" data-i18n="push.settings.${action}" ${v.busy||(action!=='refresh'&&!v[action])?'disabled':''} ${(action==='enable'&&v.display==='enabled')||(['disable','test'].includes(action)&&!v.disable)?'hidden':''}>${text(action)}</button>`).join('')}</div><p class="device-notification-feedback" data-device-notification-feedback role="status" ${notice?'':'hidden'}>${notice?text(notice):''}</p><small class="muted" data-i18n="push.settings.privacy">${text('privacy')}</small></section>`;
        }
        function mount(){update();if(Date.now()-lastRead>30000)refresh();}
        function resume(){return playerId&&platform()==='android'?refresh():Promise.resolve(state());}
        function foreground(){if(!document.hidden&&(platform()==='android'||document.querySelector('.device-notification-settings')))refresh();}
        document.addEventListener('click',click);
        document.addEventListener('visibilitychange',foreground);
        window.addEventListener('focus',foreground);
        return {controls,mount,state,refresh,resume,syncAccount,enable,disable,test,logout,cancelLogout,destroy(){destroyed=true;generation++;nativePending?.reject(new Error('destroyed'));for(const handle of nativeHandles.splice(0))Promise.resolve(handle.remove()).catch(()=>{});document.removeEventListener('click',click);document.removeEventListener('visibilitychange',foreground);window.removeEventListener('focus',foreground);}};
    }
    window.ConquerDeviceNotifications={create,platform};
})();
