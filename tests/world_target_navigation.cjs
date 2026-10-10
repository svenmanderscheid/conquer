'use strict';
// Exercise the production scene-commit and target navigation functions without DOM/network/DB.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/game.js'),'utf8');
const functions=source.slice(source.indexOf('    function transitionScene('),source.indexOf('    function costHtml('));
function setup(){
 const events=[],timers=new Map();let next=0;
 const classes=new Set(),veil={dataset:{},classList:{contains:k=>classes.has(k),add:(...ks)=>ks.forEach(k=>classes.add(k)),remove:(...ks)=>ks.forEach(k=>classes.delete(k))},querySelector:()=>({})};
 const el={dataset:{},open:false,focus:()=>{},close(){this.open=false;},querySelector:()=>null};
 const c={events,timers,console,URLSearchParams,Number,Object,Map,performance:{now:()=>0},base:'',setTimeout:fn=>{timers.set(++next,fn);return next;},clearTimeout:id=>timers.delete(id),requestAnimationFrame:fn=>{timers.set(++next,fn);return next;},cancelAnimationFrame:id=>timers.delete(id),matchMedia:()=>({matches:false}),document:{body:{classList:{contains:()=>false}},activeElement:null},location:{hash:''},$:s=>s==='#scene-transition'?veil:el,window:{},pendingRefresh:null};
 vm.createContext(c);vm.runInContext(`
 let sceneTransitionToken=0,sceneTransitionTimer=0,sceneTransitionFrame=0,sceneCommitPending=false,navigationVersion=0;
 let state={city:{world_id:1},monsters:[]},current='city',playfield='city',polling=null,teleportSelection=null,panelTrigger=null,mounted=false;
 const navs={world:1,city:1,help:1},isPlayfield=t=>t==='city'||t==='world',mobilePages=null,viewPositions=new Map(),panelDialog={open:false},panelHost={scrollTop:0,querySelector:()=>null};
 function rememberView(){} function render(){mounted=playfield==='world';events.push(['render',current]);}
 let center=null;
 window.ConquerWorld={focus:(x,y)=>{if(!mounted)throw Error('Map not mounted');center={x,y};events.push(['focus',x,y]);},locate:(x,y,kinds,id)=>{events.push(['locate',id]);return true;}};
 async function refresh(){if(polling){await polling;return true;}events.push(['refresh',center.x,center.y]);if(pendingRefresh)await pendingRefresh;state.monsters=[{id:center.x,coord_x:center.x,coord_y:center.y}];return true;}
 ${functions}
 globalThis.open=openWorldTarget;globalThis.nav=navigate;globalThis.changeWorld=()=>state.city.world_id++;globalThis.setPolling=p=>{polling=p;p.then(()=>polling=null);};`,c);
 c.commit=()=>{const [id,fn]=timers.entries().next().value||[];assert(fn,'pending scene commit');timers.delete(id);fn();};
 return c;
}
const settle=async()=>{for(let i=0;i<8;i++)await Promise.resolve();};
const target=x=>({x,y:20,kind:'monsters',id:x});
(async()=>{
 let c=setup();c.open(target(7),1);assert.deepEqual(c.events,[],'first world opening does not focus before the scene exists');c.commit();await settle();assert.deepEqual(c.events.map(x=>Array.from(x)),[['render','world'],['focus',7,20],['refresh',7,20],['locate',7]],'fast response waits for slow transition commit');
 c=setup();c.open(target(7),1);c.open(target(9),1);c.commit();await settle();assert.deepEqual(c.events.filter(x=>x[0]==='locate').map(x=>x[1]),[9],'double destination navigation selects only the newest target');
 c=setup();c.open(target(7),1);c.nav('help');await settle();assert.equal(c.events.some(x=>x[0]==='focus'),false,'leaving before commit cancels the pending focus');assert.equal(c.timers.size,0,'superseded scene timer is cleared');
 c=setup();c.open(target(7),1);c.changeWorld();c.commit();await settle();assert.deepEqual(c.events,[],'foreign world cannot commit an old scene target');
 c=setup();let release;c.pendingRefresh=new Promise(r=>release=r);c.open(target(7),1);c.commit();await settle();c.nav('help');c.nav('world');release();await settle();assert.equal(c.events.some(x=>x[0]==='locate'),false,'leaving and returning to the same route invalidates an old result');
 c=setup();c.pendingRefresh=new Promise(r=>release=r);c.open(target(7),1);c.commit();await settle();c.changeWorld();release();await settle();assert.equal(c.events.some(x=>x[0]==='locate'),false,'world change during refresh invalidates result');
 c=setup();c.setPolling(new Promise(r=>release=r));c.open(target(7),1);c.commit();await settle();assert.equal(c.events.some(x=>x[0]==='focus'),false,'an older viewport poll drains before new focus');release();await settle();assert.deepEqual(c.events.filter(x=>x[0]==='refresh').map(x=>Array.from(x)),[['refresh',7,20]],'destination receives a fresh viewport query after coalesced poll');assert.equal(c.events.at(-1)[0],'locate');
 console.log('PASS world target navigation: delayed first scene, fast response, double navigation, route re-entry, world changes, coalesced viewport poll.');
})().catch(e=>{console.error(e);process.exitCode=1;});
