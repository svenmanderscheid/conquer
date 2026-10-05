'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../assets/js/game-audio.js'),'utf8');
(async()=>{
let time=0,contexts=0,buffers=0;
const listeners=new Map(),document={hidden:false,querySelectorAll:()=>[],addEventListener(type,fn){listeners.set(type,fn);},removeEventListener(){}};
const parameter=()=>({value:0,setTargetAtTime(){},setValueAtTime(){},linearRampToValueAtTime(){},exponentialRampToValueAtTime(){}});
const node=(extra={})=>({...extra,connect(){},disconnect(){},start(){},stop(){this.onended?.();}});
class AudioContext{
    constructor(){contexts++;this.state='running';this.destination={};this.sampleRate=22050;}
    get currentTime(){return time;}
    createGain(){return node({gain:parameter()});}
    createOscillator(){return node({frequency:parameter()});}
    createBuffer(channels,length){buffers++;return {getChannelData:()=>new Float32Array(length)};}
    createBufferSource(){return node();}
    createBiquadFilter(){return node({frequency:parameter(),Q:parameter()});}
    addEventListener(){}removeEventListener(){}
    resume(){this.state='running';return Promise.resolve();}
    suspend(){this.state='suspended';return Promise.resolve();}
    close(){this.state='closed';return Promise.resolve();}
}
const window={AudioContext,addEventListener(){},removeEventListener(){}},storage=new Map([['conquer:audio:v1:',JSON.stringify({music:false})]]);
vm.runInNewContext(source,{window,document,localStorage:{getItem:k=>storage.get(k),setItem:(k,v)=>storage.set(k,v)}});
const audio=window.ConquerAudio.create({base:''});
const confirmed=(path,payload={},result={})=>{time++;return audio.confirmed(path,payload,result);};
assert.equal(confirmed('march/dispatch'),false);assert.equal(contexts,0,'No context before a trusted gesture');
listeners.get('pointerdown')({isTrusted:true});
const cases=[
    ['troops/train',{}, {},'training'],['defense/action',{action:'promotion.start'}, {},'training'],
    ['city/upgrade-building',{}, {},'building'],['research/start',{}, {},'research'],
    ['hospital/heal',{}, {},'heal'],['hospital/speedup',{}, {},'speedup'],
    ['march/dispatch',{}, {},'attack'],['march/dispatch-player',{}, {},'attack'],
    ['march/dispatch-field-attack',{}, {},'attack'],['march/dispatch-neutral-village',{}, {},'attack'],
    ['march/dispatch-gather',{}, {},'gather'],['march/dispatch-charm',{}, {},'gather'],
    ['rally/start',{}, {},'rally'],['rally/start-monster',{}, {},'rally'],['rally/join',{}, {},'march'],
    ['march/recall',{}, {},'recall'],['defense/action',{action:'reinforcement.recall'}, {},'recall'],
    ['defense/action',{action:'scout'}, {},'scout'],['defense/action',{action:'reinforce'}, {},'march'],
    ['shrines/123/attack',{}, {},'attack'],['shrines/123/garrison',{}, {},'march'],
    ['community/action',{action:'structure.garrison'}, {},'march'],
    ['territory/action',{action:'start'}, {},'rally'],['territory/action',{action:'join'}, {},'march'],
    ['territory/action',{action:'support',kind:'scout'}, {},'scout'],
    ['territory/action',{action:'rune_teleport'}, {},'teleport'],
    ['kingdom/action',{action:'inventory.use'}, {result:{teleport_mode:'random'}},'teleport'],
    ['kingdom/action',{action:'inventory.use'}, {result:{seconds:60}},'speedup'],
    ['kingdom/action',{action:'inventory.use'}, {result:{drops:[{quantity:1}]}},'chest'],
    ['kingdom/action',{action:'inventory.use'}, {result:{resource:'food',amount:100}},'gather'],
    ['kingdom/action',{action:'chest.free'}, {},'chest'],['kingdom/action',{action:'quest.claim'}, {},'reward'],
    ['kingdom/action',{action:'vip.daily'}, {},'reward'],['territory/action',{action:'claim'}, {},'reward'],
    ['expeditions/action',{action:'dispatch'}, {},'attack'],['expeditions/action',{action:'claim'}, {},'reward'],
    ['kingdom/action',{action:'treasure.equip'}, {},'equip'],['kingdom/action',{action:'treasure.unequip'}, {},'equip'],
    ['kingdom/action',{action:'treasure.preset_apply'}, {},'equip'],
    ['kingdom/action',{action:'treasure.upgrade_effect'}, {},'craft'],['kingdom/action',{action:'treasure.exchange_fragments'}, {},'craft'],
    ['kingdom/action',{action:'trading.buy'}, {result:{drops:[{}]}},'purchase'],
    ['kingdom/action',{action:'crystal.buy'}, {},'purchase'],['kingdom/action',{action:'inventory.buy'}, {},'purchase'],
    ['market/action',{}, {},'purchase'],['kingdom/action',{action:'profile.save'}, {},'confirm'],
];
for(const [path,payload,result,kind]of cases){assert.equal(confirmed(path,payload,result),true,path);assert.equal(audio.status().lastSound,kind,path+': '+payload.action);assert.equal(audio.status().voices,0);}
assert.equal(buffers,1,'Textures reuse one noise buffer');assert.equal(contexts,1);
const count=audio.status().playedCount;
for(const [path,payload]of [['march/preview',{}],['dungeons/action',{action:'preview'}],['battle/simulate',{}],['world-chat/send',{}],['notifications/read',{}],['auth/logout',{}],['game/state',null]])assert.equal(confirmed(path,payload),false,path);
assert.equal(audio.status().playedCount,count,'Reads, previews and chat remain silent');
const receipt={action:'inventory.use',operation_key:'audio_receipt_001',expected_world_id:1};
assert(confirmed('kingdom/action',receipt,{result:{seconds:60}}));
assert.equal(confirmed('kingdom/action',receipt,{result:{seconds:60}}),false,'Retrying a receipt does not play twice');
assert(confirmed('kingdom/action',{...receipt,expected_world_id:2},{result:{seconds:60}}),'World-scoped receipts');
assert.equal(confirmed('kingdom/action',{action:'crystal.buy'},{result:{duplicate:true}}),false);
const setting=(key,value)=>listeners.get('change')({type:'change',target:{closest:()=>({dataset:{audioSetting:key},type:typeof value==='boolean'?'checkbox':'range',checked:value,value})}});
setting('muted',true);
assert.equal(confirmed('kingdom/action',{...receipt,operation_key:'muted_receipt_001'},{}),false);
setting('muted',false);
assert.equal(confirmed('kingdom/action',{...receipt,operation_key:'muted_receipt_001'},{}),false,'Muted successes are consumed');
setting('effectsVolume',0);assert.equal(confirmed('march/dispatch'),false);setting('effectsVolume',45);
document.hidden=true;listeners.get('visibilitychange')();assert.equal(confirmed('march/dispatch'),false);
await new Promise(resolve=>setImmediate(resolve));
document.hidden=false;listeners.get('visibilitychange')();await new Promise(resolve=>setImmediate(resolve));assert(confirmed('march/dispatch'));
assert.equal(audio.play('does-not-exist'),false);assert.equal(audio.play('attack'),false,'Rapid cues are throttled');
for(const locale of ['en','de','fr']){
    const dictionary=JSON.parse(fs.readFileSync(path.join(__dirname,'../data/i18n',locale+'.json'),'utf8'));
    for(const match of audio.controls().matchAll(/data-i18n="([^"]+)"/g))assert(dictionary[match[1]],locale+': '+match[1]);
}
audio.destroy();assert.equal(confirmed('march/dispatch'),false);
console.log(`PASS action audio: ${cases.length} confirmed actions, silent previews, receipt deduplication, one context/buffer, mute, zero volume, lifecycle, throttling and three locales.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
