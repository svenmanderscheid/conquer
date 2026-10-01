'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../assets/js/game-audio.js'),'utf8');

function fixture(saved={}){
    let now=Date.parse('2030-01-01T12:00:00Z');
    const events=()=>{const handlers=new Map();return {addEventListener(type,fn){const listeners=handlers.get(type)||new Set();listeners.add(fn);handlers.set(type,listeners);},removeEventListener(type,fn){handlers.get(type)?.delete(fn);},emit(type,event={}){for(const fn of handlers.get(type)||[])fn({...event,type});}};};
    const document={...events(),hidden:false,querySelectorAll:()=>[]},window={...events()};
    const parameter=()=>({value:0,setTargetAtTime(){},setValueAtTime(){},linearRampToValueAtTime(){},exponentialRampToValueAtTime(){}});
    class AudioContext{
        state='running';destination={};
        get currentTime(){return now/1000;}
        createGain(){return {gain:parameter(),connect(){},disconnect(){}};}
        createOscillator(){return {frequency:parameter(),connect(){},disconnect(){},start(){},stop(){this.onended?.();}};}
        addEventListener(){}removeEventListener(){}
        resume(){this.state='running';return Promise.resolve();}
        suspend(){this.state='suspended';return Promise.resolve();}
        close(){this.state='closed';return Promise.resolve();}
    }
    window.AudioContext=AudioContext;
    const storage=new Map([['conquer:audio:v1:',JSON.stringify({music:false,...saved})]]);
    class Clock extends Date{static now(){return now;}}
    vm.runInNewContext(source,{window,document,Date:Clock,localStorage:{getItem:key=>storage.get(key),setItem:(key,value)=>storage.set(key,value)}});
    const audio=window.ConquerAudio.create({base:''});
    const options={playerId:7,worldId:1,allianceId:2};
    const row=(id,extra={})=>({id,leader_player_id:8,status:'gathering',created_at:new Date(now).toISOString(),participants:[],...extra});
    return {audio,document,window,storage,row,options,
        tick(seconds=1){now+=seconds*1000;},
        unlock(){document.emit('pointerdown',{isTrusted:true});},
        observe(rows,extra={}){return audio.observeRallies(rows,{...options,serverTime:now/1000,...extra});},
        setting(key,value){const field={dataset:{audioSetting:key},type:typeof value==='boolean'?'checkbox':'range',checked:value,value};document.emit('change',{target:{closest:()=>field}});}
    };
}

(async()=>{
{
    const f=fixture();f.unlock();
    assert.equal(f.observe([f.row(10),f.row(11)]),false,'Existing rallies on first load stay silent');
    f.tick();assert.equal(f.observe([f.row(12),f.row(13)]),true,'Several fresh rallies share one cue');
    assert.equal(f.audio.status().playedCount,1);assert.equal(f.audio.status().lastSound,'rally');
    f.tick();assert.equal(f.observe([f.row(13),f.row(12)]),false,'Polling order does not replay a cue');
    f.observe([f.row(10)]);f.tick();assert.equal(f.observe([f.row(13)]),false,'An older in-flight response cannot reset deduplication');
    f.tick();assert.equal(f.observe([f.row(14,{leader_player_id:7})]),false,'A player never hears a second announcement for their own rally');
    f.tick();assert.equal(f.observe([f.row(15,{participants:[{player_id:7,status:'joining'}]})]),false,'Already-joined rallies stay silent');
    f.tick();assert.equal(f.observe([f.row(16,{status:'marching'})]),false);
    f.tick();assert.equal(f.observe([f.row(17,{created_at:'2030-01-01 11:30:00'})]),false,'Delayed historical records stay silent');
    f.tick();assert.equal(f.observe([f.row(18,{created_at:''})]),false,'Unknown creation time is not announced as a new rally');
    f.tick();assert.equal(f.observe([f.row(19)]),true,'A new ally rally still works after exclusions');
    f.tick();assert.equal(f.observe([f.row(20)],{allianceId:3}),false,'Changing alliance starts a new silent baseline');
    f.tick();assert.equal(f.observe([f.row(21)],{worldId:2,allianceId:3}),false,'Changing world stays silent');
    f.tick();assert.equal(f.observe([f.row(22)],{playerId:70,worldId:2,allianceId:3}),false,'Changing player stays silent');
    f.tick(91);assert.equal(f.observe([f.row(23)],{playerId:70,worldId:2,allianceId:3}),false,'Reconnecting after a long polling gap stays silent');
}

for(const [key,value] of [['rallyAlerts',false],['effects',false],['effectsVolume',0],['muted',true]]){
    const f=fixture();f.unlock();f.observe([]);f.setting(key,value);f.tick();
    assert.equal(f.observe([f.row(1)]),false,`${key} silences rally alerts`);
    f.setting(key,key==='effectsVolume'?45:!value);f.tick();assert.equal(f.observe([f.row(1)]),false,'Re-enabling sound never queues an old alert');
    f.tick();assert.equal(f.observe([f.row(2)]),true,'New alerts work after re-enabling sound');
}

{
    const f=fixture();f.observe([]);f.tick();assert.equal(f.observe([f.row(1)]),false,'No audio context before a trusted user gesture');
    assert.equal(f.audio.status().context,'idle');f.unlock();f.tick();assert.equal(f.observe([f.row(1)]),false,'Unlocking does not replay an alert');
    f.tick();assert.equal(f.observe([f.row(2)]),true);
    f.document.hidden=true;f.document.emit('visibilitychange');f.tick();assert.equal(f.observe([f.row(3)]),false);
    f.document.hidden=false;f.document.emit('visibilitychange');f.tick();assert.equal(f.observe([f.row(4)]),false,'Returning to the foreground silently refreshes the baseline');
    f.tick();assert.equal(f.observe([f.row(5)]),true);
    await new Promise(resolve=>setImmediate(resolve));
    f.window.emit('pagehide');f.tick();assert.equal(f.observe([f.row(6)]),false);
    f.window.emit('pageshow');f.tick();assert.equal(f.observe([f.row(7)]),false,'Returning from page cache stays silent');
    f.tick();assert.equal(f.observe([f.row(8)]),true);
    f.audio.destroy();f.tick();assert.equal(f.observe([f.row(9)]),false);
}

{
    const f=fixture();assert.equal(f.audio.status().settings.rallyAlerts,true,'Existing audio preferences gain the new default');
    f.setting('rallyAlerts',false);assert.equal(JSON.parse(f.storage.get('conquer:audio:v1:')).rallyAlerts,false);
    assert.equal(fixture({rallyAlerts:false}).audio.status().settings.rallyAlerts,false,'Rally preference survives recreation');
    assert.match(f.audio.controls(),/data-audio-setting="rallyAlerts"/);assert.match(f.audio.controls(),/data-i18n="audio.rally_alerts"/);assert.match(f.audio.controls(),/data-audio-demo="rally"/);
}
console.log('PASS rally audio: silent baseline, fresh ally cues, duplicate/race suppression, membership scopes, settings persistence, mute/effects, gesture and app lifecycle.');
})().catch(error=>{console.error(error);process.exitCode=1;});
