'use strict';
// Startup milestones and intro lifecycle, without browser, network or player writes.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const root=path.resolve(__dirname,'..'),catalog=JSON.parse(fs.readFileSync(path.join(root,'data/i18n/en.json'),'utf8'));
assert(catalog['intro.welcome.title']&&catalog['intro.quests.text'],'English intro catalog must be integrated');
const t=(key,values={})=>Object.entries(values).reduce((s,[k,v])=>s.replaceAll('{'+k+'}',v),catalog[key]||key);
function startup({game=true,reduced=false,readyState='loading'}={}) {
    const window=new EventTarget(),document=new EventTarget(),timers=new Map();let removed=false,nextTimer=0,reloads=0,completions=0;
    const node=()=>Object.assign(new EventTarget(),{attributes:{},setAttribute(k,v){this.attributes[k]=v;}});
    const screen=Object.assign(node(),{dataset:{game:String(game)},classList:{add(){}},remove(){removed=true;}});
    const track=Object.assign(node(),{firstElementChild:{style:{}}}),status={textContent:'loading'},percent={},retry=Object.assign(node(),{hidden:true});
    const elements={'app-start':screen,'app-start-progress':track,'app-start-status':status,'app-start-percent':percent,'app-start-retry':retry};
    Object.assign(document,{readyState,images:[],body:{classList:{contains:()=>false}},getElementById:id=>elements[id]});
    Object.assign(window,{ConquerLocale:{t},matchMedia:()=>({matches:reduced})});
    window.addEventListener('conquer:startup-complete',()=>completions++);
    vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/app-start.js'),'utf8'),{window,document,Event,location:{reload(){reloads++;}},setTimeout(fn,delay){const id=++nextTimer;timers.set(id,{fn,delay});return id;},clearTimeout(id){timers.delete(id);}});
    return {window,document,screen,track,status,percent,retry,timers,get removed(){return removed;},get completions(){return completions;},get reloads(){return reloads;},fire(delay){for(const [id,timer]of [...timers])if(timer.delay===delay){timers.delete(id);timer.fn();}}};
}
{
    const h=startup();h.document.dispatchEvent(new Event('DOMContentLoaded'));h.window.dispatchEvent(new Event('load'));
    assert.equal(h.removed,false,'page load must wait for authenticated state');assert.equal(h.percent.textContent,'80%');
    h.fire(30000);assert.equal(h.retry.hidden,false,'slow startup has an explicit retry');
    h.retry.dispatchEvent(new Event('click'));assert.equal(h.reloads,1);
    h.window.ConquerStartup.ready();assert.equal(h.percent.textContent,'100%');assert.equal(h.retry.hidden,true);
    assert.equal(h.screen.attributes['aria-busy'],'false');h.fire(300);
    assert.equal(h.removed,true);assert.equal(h.completions,1);
    h.window.ConquerStartup.ready();h.window.ConquerStartup.fail();h.fire(300);assert.equal(h.completions,1,'dismiss once even when polling continues');
}
{
    const h=startup({reduced:true});h.window.ConquerStartup.ready();assert.equal(h.removed,false,'state alone must wait for page load');
    h.document.dispatchEvent(new Event('DOMContentLoaded'));h.window.dispatchEvent(new Event('load'));
    assert.equal(h.removed,true,'reduced motion does not wait for a fade');assert.equal(h.completions,1);
}
{
    const h=startup({game:false,reduced:true,readyState:'complete'});
    assert.equal(h.removed,true,'sign-in and late script execution do not wait for game state');assert.equal(h.completions,1);
}
function intro({stored=new Map(),storageBlocked=false,loading=false,advanced=false}={}) {
    const window=new EventTarget();let html='',open=false,opens=0,focused=0;
    const location={hash:'#city'},state={city:{player_id:1},buildings:{castle:{level:advanced?2:1}},trained_total:0,research:{}},routes=[];
    const document={querySelector(selector){if(selector==='dialog[open]')return open?{}:null;if(selector==='#game-dialog[open] .kingdom-intro')return open&&html.includes('kingdom-intro')?{}:null;return null;},getElementById(id){return id==='app-start'?(loading?{}:null):id==='kingdom-intro-heading'?{focus(){focused++;}}:null;}};
    window.ConquerLocale={t,text:String};
    const localStorage={getItem(k){if(storageBlocked)throw Error('blocked');return stored.get(k)||null;},setItem(k,v){if(storageBlocked)throw Error('blocked');stored.set(k,v);}};
    vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/beginner-guide.js'),'utf8'),{window,document,localStorage,location});
    const guide=window.ConquerBeginnerGuide({base:'/conquer',esc:String,fmt:String,getState:()=>state,getKingdom:()=>null,getHost:()=>({querySelector:()=>null}),navigate(id,options={}){routes.push(id);location.hash='#'+id;open=false;options.afterCommit?.();},openDialog(value){html=value;open=true;opens++;},buildingDialog(){throw Error('Intro must not trigger a building action');},buildingFunction(){throw Error('Intro must not trigger training');},labels:{},buildingImage:()=>''});
    return {guide,state,stored,routes,location,get html(){return html;},get opens(){return opens;},get focused(){return focused;},close(){open=false;},loaded(){loading=false;window.dispatchEvent(new Event('conquer:startup-complete'));},click(action){guide.onClick(action,{dataset:{}});}};
}
{
    const h=intro({loading:true});h.guide.maybeWelcome('city');h.guide.maybeWelcome('city');assert.equal(h.opens,0,'modal never covers true startup progress');
    h.loaded();assert.equal(h.opens,1);assert(h.html.includes(catalog['intro.welcome.title']));assert.equal(h.focused,1);
    h.click('guide-intro-next');assert(h.html.includes(catalog['intro.city.title']));
    h.click('guide-intro-back');assert(h.html.includes(catalog['intro.welcome.title']));
    h.click('guide-intro-next');h.click('guide-intro-next');assert(h.html.includes(catalog['intro.quests.title']));
    h.click('guide-intro-next');assert(h.html.includes('data-action="guide-open"'));assert.deepEqual(h.routes,[],'reading does not navigate or execute gameplay');
    h.click('guide-open');assert.deepEqual(h.routes,['help']);h.guide.maybeWelcome('city');assert.equal(h.opens,6,'completed introduction does not reopen');
    const again=intro({stored:h.stored});again.guide.maybeWelcome('city');assert.equal(again.opens,0,'welcome marker survives reload');
    h.click('guide-intro-replay');assert.deepEqual(h.routes,['help','city']);assert(h.html.includes(catalog['intro.welcome.title']),'replay starts over actual city');
    h.close();h.state.city.player_id=2;h.guide.maybeWelcome('city');assert.equal(h.opens,8,'another player has an independent welcome');
}
{
    const h=intro({advanced:true});h.guide.maybeWelcome('city');assert.equal(h.opens,0,'established players are not interrupted');
    h.click('guide-intro-replay');assert.equal(h.opens,1,'established players can explicitly replay');
}
{
    const h=intro({storageBlocked:true});h.guide.maybeWelcome('city');h.close();h.guide.maybeWelcome('city');assert.equal(h.opens,1,'blocked storage still supports once per session');
    h.click('guide-intro-next');assert.equal(h.opens,1,'stale next action does not reopen a dismissed intro');
}
{
    const h=intro({loading:true});h.guide.maybeWelcome('city');h.location.hash='#world';h.loaded();assert.equal(h.opens,0,'changing destination while loading cancels the auto intro');
}
console.log('PASS kingdom entry: genuine loading milestones, retry recovery, reduced motion, late scripts, intro handoff, replay, dismissal, account scope and no game actions.');
