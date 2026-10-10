'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../assets/js/device-notifications.js'),'utf8');
const flush=()=>new Promise(setImmediate);
const deferred=()=>{let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b;});return {promise,resolve,reject};};
function fixture(options={}) {
    const calls=[],events=new Map(),storage=new Map(),nativeEvents=new Map();
    const f={playerId:7,permission:options.permission||'default',enabled:options.enabled||false,available:options.available!==false,subscribed:options.subscribed||false,prompts:0,subscriptions:0,unsubscriptions:0,registers:0,nativeRegisters:0,nativeUnregisters:0,removed:0,opened:0,token:'android-device-token-1',calls};
    const on=(key,fn)=>{if(!events.has(key))events.set(key,new Set());events.get(key).add(fn);};
    const off=(key,fn)=>events.get(key)?.delete(fn);
    const document={hidden:false,querySelectorAll:()=>[],querySelector:()=>null,addEventListener:on,removeEventListener:off};
    const sub={endpoint:'https://push.example.test/subscription-private',toJSON(){return {endpoint:this.endpoint,keys:{p256dh:'fixture-p256dh',auth:'fixture-auth'}};},async unsubscribe(){f.unsubscriptions++;if(f.unsubscribeFail)return false;f.subscribed=false;return true;}};
    const registration={scope:'https://game.example.test/conquer/',active:{},pushManager:{async getSubscription(){return f.subscribed?sub:null;},async subscribe(value){f.subscriptions++;f.subscribeOptions=value;if(f.subscribeGate)await f.subscribeGate.promise;f.subscribed=true;return sub;}}};
    const Notification={get permission(){return f.permission;},requestPermission(){f.prompts++;if(f.permissionGate)return f.permissionGate.promise.then(value=>f.permission=value);return Promise.resolve(f.permission=options.promptResult||'granted');}};
    const native={
        async checkPermissions(){return {receive:f.permission==='default'?'prompt':f.permission};},
        requestPermissions(){f.prompts++;return Promise.resolve({receive:f.permission=options.promptResult||'granted'});},
        async addListener(name,fn){nativeEvents.set(name,fn);return {remove(){f.removed++;nativeEvents.delete(name);}};},
        async createChannel(channel){f.channel=channel;},
        async register(){f.nativeRegisters++;if(f.nativeRegisterFail)throw Error('FCM missing');if(!f.manualNativeRegistration)queueMicrotask(()=>nativeEvents.get('registration')?.({value:f.token}));},
        async unregister(){f.nativeUnregisters++;},
        async removeAllDeliveredNotifications(){f.deliveredCleared=true;}
    };
    const navigator={userAgent:'Test',platform:'Linux',maxTouchPoints:0,serviceWorker:{async getRegistration(scope){f.lookupScope=scope;return options.noRegistration?null:registration;},async register(url,settings){f.registers++;f.registerUrl=url;f.registerSettings=settings;return registration;}}};
    const window={Notification,PushManager:function(){},isSecureContext:true,matchMedia:()=>({matches:false}),addEventListener:on,removeEventListener:off,ConquerLocale:{locale:'en',t:key=>key}};
    if(options.native)window.Capacitor={isNativePlatform:()=>true,getPlatform:()=>options.nativePlatform||'android',Plugins:options.missingPlugin?{}:{PushNotifications:native}};
    const api=async(route,payload)=>{
        calls.push({route,payload});
        if(f.onApi){const result=await f.onApi(route,payload);if(result!==undefined)return result;}
        if(route.startsWith('push/status'))return {available:f.available,public_key:Buffer.concat([Buffer.from([4]),Buffer.alloc(64,1)]).toString('base64url'),enabled:payload?f.enabled:false,player_id:f.playerId,preferences:{completions:true,security:true}};
        if(route==='push/subscribe'){if(f.failSubscribe)throw Error('offline');f.enabled=true;return {player_id:f.playerId};}
        if(route==='push/unsubscribe'){if(f.failUnsubscribe)throw Error('offline');f.enabled=false;return {};}
        if(route==='push/test')return {queued:true};
        throw Error('Unknown route '+route);
    };
    vm.runInNewContext(source,{window,document,navigator,location:{href:'https://game.example.test/conquer/city'},localStorage:{getItem:key=>storage.get(key)||null,setItem:(key,value)=>storage.set(key,value),removeItem:key=>storage.delete(key)},URL,Uint8Array,atob:value=>Buffer.from(value,'base64').toString('binary'),setTimeout,clearTimeout,console});
    f.controller=window.ConquerDeviceNotifications.create({base:'/conquer',api,getPlayerId:()=>f.playerId,onOpen:()=>f.opened++});
    Object.assign(f,{window,navigator,native,registration,storage,nativeEvents,events,platform:window.ConquerDeviceNotifications.platform});
    f.sync=()=>f.controller.syncAccount(f.playerId);
    return f;
}

test('web requires a trusted click, prompts synchronously and never duplicates registration',async()=>{
    const f=fixture({noRegistration:true});assert.equal(f.calls.length,0);
    await f.sync();assert.equal(f.controller.state().status,'disabled');assert.equal(f.prompts,0);
    assert.equal(f.registerUrl,'/conquer/service-worker.js');assert.equal(f.registerSettings.scope,'/conquer/');assert.equal(f.lookupScope,'https://game.example.test/conquer/');
    await f.controller.enable({isTrusted:false});assert.equal(f.prompts,0);
    const enabled=f.controller.enable({isTrusted:true});assert.equal(f.prompts,1,'permission is requested in the original gesture stack');
    assert.equal(await f.controller.enable({isTrusted:true}),false);assert.equal(await enabled,true);
    assert.equal(f.subscriptions,1);assert.equal(f.subscribeOptions.userVisibleOnly,true);assert.equal(f.subscribeOptions.applicationServerKey.length,65);
    const bind=f.calls.find(c=>c.route==='push/subscribe');assert.equal(bind.payload.expected_player_id,7);assert.equal(bind.payload.locale,'en');assert.equal(bind.payload.subscription.endpoint,'https://push.example.test/subscription-private');
    await f.controller.refresh();assert.equal(f.subscriptions,1);assert.equal(f.prompts,1);
    assert(f.calls.some(c=>c.route==='push/status'&&c.payload?.endpoint));assert(f.calls.every(c=>!c.route.includes('subscription-private')),'endpoint never appears in a URL');
    assert.equal(await f.controller.test(),true);assert.equal(f.controller.state().notice,'test_sent');
    assert.equal(await f.controller.disable(),true);assert.equal(f.controller.state().status,'disabled');assert.equal(f.subscribed,false);
    f.controller.destroy();
});
test('blocked, dismissed and unconfigured permissions remain explicit and retryable',async()=>{
    for(const [options,want]of [[{permission:'denied'},'denied'],[{available:false},'unconfigured']]){
        const f=fixture(options);await f.sync();await f.controller.enable({isTrusted:true});assert.equal(f.controller.state().status,want);assert.equal(f.prompts,0);assert.equal(f.subscriptions,0);f.controller.destroy();
    }
    const f=fixture({promptResult:'default'});await f.sync();assert.equal(await f.controller.enable({isTrusted:true}),false);assert.equal(f.controller.state().notice,'dismissed');assert.equal(f.subscriptions,0);f.controller.destroy();
});
test('insecure contexts, iOS install requirements and missing native bridges explain support without API writes',async()=>{
    for(const [setup,want]of [[f=>{f.window.isSecureContext=false;},'insecure'],[f=>{f.navigator.userAgent='iPhone';},'ios_install'],[f=>{delete f.window.PushManager;},'unsupported'],[f=>{f.window.Capacitor={isNativePlatform:()=>true,Plugins:{}};},'native']]){
        const f=fixture();setup(f);await f.sync();assert.equal(f.controller.state().status,want);assert.equal(f.calls.length,0);assert.equal(f.prompts,0);f.controller.destroy();
    }
});
test('persisted browser subscription restores only the current account binding',async()=>{
    const f=fixture({permission:'granted',subscribed:true,enabled:true});await f.sync();assert.equal(f.controller.state().status,'enabled');assert.equal(f.subscriptions,0);assert.equal(f.prompts,0);
    f.playerId=8;f.enabled=false;await f.sync();assert.equal(f.controller.state().status,'disabled');assert.equal(f.unsubscriptions,1);assert.equal(f.calls.filter(c=>c.route==='push/subscribe').length,0);f.controller.destroy();
});
test('failed subscribe cleans orphan subscriptions; failed disable retains a retryable endpoint',async()=>{
    const f=fixture();f.failSubscribe=true;await f.sync();assert.equal(await f.controller.enable({isTrusted:true}),false);assert.equal(f.unsubscriptions,1);assert.equal(f.controller.state().status,'disabled');
    f.failSubscribe=false;await f.controller.enable({isTrusted:true});f.failUnsubscribe=true;assert.equal(await f.controller.disable(),false);assert.equal(f.subscribed,true);assert.equal(f.controller.state().notice,'failed_action');assert.equal(f.controller.state().busy,false);
    f.failUnsubscribe=false;assert.equal(await f.controller.disable(),true);assert.equal(f.subscribed,false);f.controller.destroy();
});
test('permission dialog completing after an account switch cannot opt the new account in',async()=>{
    const f=fixture();await f.sync();f.permissionGate=deferred();const pending=f.controller.enable({isTrusted:true});f.playerId=8;await f.sync();f.permissionGate.resolve('granted');assert.equal(await pending,false);await flush();
    assert.equal(f.subscriptions,0);assert.equal(f.controller.state().playerId,8);assert.equal(f.controller.state().status,'disabled');f.controller.destroy();
});
test('late account reads cannot paint or clear the newer account request',async()=>{
    const f=fixture(),a=deferred(),b=deferred();let reads=0;
    f.onApi=route=>route.startsWith('push/status')?(++reads===1?a.promise:b.promise):undefined;
    const old=f.sync();await flush();f.playerId=8;const next=f.sync();await flush();
    a.resolve({available:false,player_id:7});await old;assert.equal(f.controller.state().busy,true,'old finally must not clear the new read');assert.equal(f.controller.state().status,'loading');
    b.resolve({available:false,player_id:8});await next;assert.equal(f.controller.state().status,'unconfigured');assert.equal(f.controller.state().busy,false);f.controller.destroy();
});
test('server identity changes require reload without revoking another account subscription',async()=>{
    const f=fixture({subscribed:true});f.onApi=async()=>({available:true,player_id:99,enabled:false});await f.sync();assert.equal(f.controller.state().status,'account_changed');assert.equal(f.unsubscriptions,0);await f.controller.enable({isTrusted:true});assert.equal(f.prompts,0);f.controller.destroy();
});
test('logout cancels in-flight permission and does not restart status polling',async()=>{
    const f=fixture();await f.sync();f.permissionGate=deferred();const pending=f.controller.enable({isTrusted:true});await f.controller.logout();const count=f.calls.length;f.permissionGate.resolve('granted');await pending;await f.sync();await flush();assert.equal(f.calls.length,count);assert.equal(f.subscriptions,0);assert.equal(f.controller.state().status,'disabled');f.controller.destroy();
});
test('Android installs scoped listeners before explicit registration and uses FCM device payloads',async()=>{
    const f=fixture({native:true});await f.sync();assert.equal(f.controller.state().status,'disabled');assert.equal(f.nativeRegisters,0);assert.equal(f.prompts,0);assert.equal(f.nativeEvents.size,3);assert.equal(f.channel.id,'uok_game');
    const pending=f.controller.enable({isTrusted:true});assert.equal(f.prompts,1);assert.equal(await pending,true);assert.equal(f.nativeRegisters,1);
    const bind=f.calls.find(c=>c.route==='push/subscribe');assert.equal(bind.payload.platform,'android');assert.equal(bind.payload.token,f.token);assert.equal(bind.payload.expected_player_id,7);assert.equal(f.subscriptions,0);
    await f.controller.test();assert.equal(f.calls.at(-1).payload.token,f.token);
    await f.controller.disable();assert.equal(f.nativeUnregisters,1);assert.equal(f.deliveredCleared,true);assert.equal(f.controller.state().status,'disabled');f.controller.destroy();assert.equal(f.removed,3);
});
test('Android token rotation requires existing consent, preserves account and ignores external tap URLs',async()=>{
    const f=fixture({native:true});await f.sync();await f.controller.enable({isTrusted:true});
    f.nativeEvents.get('registration')({value:'android-rotated-token'});await flush();
    assert.equal(f.calls.filter(c=>c.route==='push/subscribe').length,2);assert.equal(f.calls.at(-1).payload.previous_token,f.token);assert.equal(f.calls.at(-1).payload.expected_player_id,7);
    assert.equal(f.calls.filter(c=>c.route==='push/unsubscribe').length,0,'rotation is one atomic rebind, preserving the outbox and device slot');
    const stored=JSON.parse(f.storage.get('union-of-kingdoms:push:android:/conquer'));assert.equal(stored.token,'android-rotated-token');assert.equal(stored.playerId,7);
    f.nativeEvents.get('pushNotificationActionPerformed')({notification:{data:{url:'https://attacker.invalid'}}});assert.equal(f.opened,1,'tap always opens existing game navigation');
    f.controller.destroy();assert.equal(f.nativeEvents.size,0);
});
test('failed Android rotation keeps the old token and retries atomically on resume',async()=>{
    const f=fixture({native:true});await f.sync();await f.controller.enable({isTrusted:true});const oldToken=f.token;
    f.failSubscribe=true;f.token='rotation-after-outage';f.nativeEvents.get('registration')({value:f.token});await flush();
    assert.equal(f.controller.state().status,'failed');assert.equal(JSON.parse(f.storage.get('union-of-kingdoms:push:android:/conquer')).token,oldToken);
    assert.equal(f.calls.filter(c=>c.route==='push/unsubscribe').length,0);assert.equal(f.nativeUnregisters,0);
    f.failSubscribe=false;await f.controller.resume();await flush();
    assert.equal(f.controller.state().status,'enabled');assert.equal(JSON.parse(f.storage.get('union-of-kingdoms:push:android:/conquer')).token,f.token);
    const retries=f.calls.filter(c=>c.route==='push/subscribe'&&c.payload.previous_token);assert.equal(retries.length,2);for(const retry of retries){assert.equal(retry.payload.previous_token,oldToken);assert.equal(retry.payload.expected_player_id,7);}
    assert.equal(f.calls.filter(c=>c.route==='push/unsubscribe').length,0);f.controller.destroy();
});
test('Android rotation response for another account cannot replace the stored token',async()=>{
    const f=fixture({native:true});await f.sync();await f.controller.enable({isTrusted:true});const oldToken=f.token;
    f.onApi=(route,payload)=>route==='push/subscribe'&&payload.previous_token?{player_id:99}:undefined;
    f.nativeEvents.get('registration')({value:'foreign-response-token'});await flush();
    assert.equal(f.controller.state().status,'account_changed');assert.equal(JSON.parse(f.storage.get('union-of-kingdoms:push:android:/conquer')).token,oldToken);
    assert.equal(f.calls.filter(c=>c.route==='push/unsubscribe').length,0);f.controller.destroy();
});
test('Android startup never rebinds a stored token after session/account change',async()=>{
    const f=fixture({native:true,permission:'granted'});f.storage.set('union-of-kingdoms:push:android:/conquer',JSON.stringify({playerId:6,token:f.token}));await f.sync();assert.equal(f.controller.state().status,'disabled');assert.equal(f.nativeUnregisters,1);assert.equal(f.nativeRegisters,0);assert.equal(f.prompts,0);assert.equal(f.calls.filter(c=>c.route==='push/subscribe').length,0);f.controller.destroy();
});
test('Android resume refreshes an opted-in token without another permission prompt',async()=>{
    const f=fixture({native:true});await f.sync();await f.controller.enable({isTrusted:true});const prompts=f.prompts;
    f.token='token-after-native-resume';await f.controller.resume();await flush();
    assert.equal(f.nativeRegisters,2);assert.equal(f.prompts,prompts);assert.equal(f.calls.filter(c=>c.route==='push/subscribe').at(-1).payload.token,f.token);
    await f.controller.logout();assert.equal(f.nativeUnregisters,1);assert.equal(f.storage.size,0);const count=f.calls.length;await f.controller.resume();assert.equal(f.calls.length,count);f.controller.destroy();
});
test('Android without opt-in does not create a Firebase token during logout',async()=>{
    const f=fixture({native:true});await f.sync();await f.controller.logout();assert.equal(f.nativeRegisters,0);assert.equal(f.nativeUnregisters,0);f.controller.destroy();
});
test('Android registration failure reports failure without a false enabled state',async()=>{
    const f=fixture({native:true});await f.sync();f.nativeRegisterFail=true;assert.equal(await f.controller.enable({isTrusted:true}),false);assert.equal(f.controller.state().status,'disabled');assert.equal(f.controller.state().notice,'failed_action');assert.equal(f.calls.filter(c=>c.route==='push/subscribe').length,0);f.controller.destroy();
});
test('late Android registration after logout is revoked without server binding',async()=>{
    const f=fixture({native:true});await f.sync();f.manualNativeRegistration=true;const pending=f.controller.enable({isTrusted:true});await flush();assert.equal(f.nativeRegisters,1);
    await f.controller.logout();f.nativeEvents.get('registration')({value:f.token});assert.equal(await pending,false);assert.equal(f.nativeUnregisters,1);assert.equal(f.calls.filter(c=>c.route==='push/subscribe').length,0);f.controller.destroy();
});
test('failed sign-out leaves settings usable without silently restoring notifications',async()=>{
    const f=fixture();await f.sync();await f.controller.enable({isTrusted:true});await f.controller.logout();await f.controller.cancelLogout();assert.equal(f.controller.state().status,'disabled');assert.equal(f.subscriptions,1);
    assert.equal(await f.controller.enable({isTrusted:true}),true);assert.equal(f.subscriptions,2);f.controller.destroy();
});
test('all new notification messages exist in the four shared language catalogs',()=>{
    let expected;
    for(const lang of ['en','de','fr','lb']){
        const catalog=JSON.parse(fs.readFileSync(path.join(__dirname,'../data/i18n/'+lang+'.json'),'utf8'));
        const keys=Object.keys(catalog).filter(k=>k.startsWith('push.')).sort();expected??=keys;assert.deepEqual(keys,expected);assert(keys.length>=30);for(const k of keys)assert.equal(typeof catalog[k],'string');
    }
});
