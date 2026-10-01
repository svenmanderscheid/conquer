'use strict';
// No browser/server/DB: execute the real card handoff and mobile history handlers.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'..'),source=fs.readFileSync(path.join(root,'assets/js/world-map.js'),'utf8');
const start=source.indexOf('  function handleClick('),end=source.indexOf('  function notify()',start);
assert(start>=0&&end>start,'Production card/history block found');
const mobileSource=fs.readFileSync(path.join(root,'assets/js/mobile-pages.js'),'utf8');
const gameSource=fs.readFileSync(path.join(root,'assets/js/game.js'),'utf8');
const marchCallbacks=gameSource.match(/onGather:(.*?),onMonsterAttack:(.*?),onAllianceGarrison:/);
assert(marchCallbacks,'Actual game march callbacks found');
function setup({mobile=true,card=true,kind='monsters'}={}){
 const listeners=new Map(),microtasks=[],timers=[],events=[],stack=[{}];let index=0,pendingBack=0,current='world';
 const listen=(type,fn)=>{const list=listeners.get(type)||[];list.push(fn);listeners.set(type,list);};
 const dialog={open:false,querySelector:()=>null,classList:{toggle(){}},close(){this.open=false;events.push('dialog-close');}};
 const panel={querySelector:()=>null};
 const history={get state(){return stack[index];},pushState(state){stack.splice(++index);stack[index]=state;},replaceState(state){stack[index]=state;},back(){pendingBack++;events.push('back-request');}};
 if(card)history.pushState({conquerMapTarget:true});
 const location={href:'http://preview.invalid/city#world'};
 const c={history,location,events,queueMicrotask:fn=>microtasks.push(fn),setTimeout:(fn,delay)=>{assert.equal(delay,0);timers.push(fn);},performance:{now:()=>0},
  window:{addEventListener:listen},matchMedia:()=>({matches:mobile,addEventListener(){}}),innerHeight:844,
  document:{addEventListener(){},querySelector:selector=>selector==='#game-dialog'?dialog:panel,documentElement:{style:{setProperty(){}}},body:{classList:{contains:()=>false}}},
  cancelSearch(){c.searchSequence++;},stopFollowing(){},updateMarkers(){},updateSidebar(){},paint(){},
  view:{el:{isConnected:true},viewport:{focus(){events.push('map-focus');}},mapHistoryUrl:location.href},
  context:{state:{city:{world_id:1}}},sceneVisible:true,searchSequence:0,memory:{panel:'actions',selected:'target'},selectedTarget:()=>({kind}),
  getRoute:()=>current,setRoute:route=>{current=route;}};
 vm.createContext(c);vm.runInContext(mobileSource,c);
 const mobilePages=c.window.ConquerMobilePages({getRoute:()=>current,getPlayfield:()=>current,navigate:route=>{current=route;dialog.close();},closeChat(){}});
 function launch(type,id){events.push(['launch',type,id]);dialog.open=true;mobilePages.opened('dialog');}
 c.expeditionDialog=(id,kind)=>launch(kind,id);
 vm.runInContext(`context.onGather=${marchCallbacks[1]};context.onMonsterAttack=${marchCallbacks[2]};`,c);
 vm.runInContext(source.slice(start,end),c);
 const button={dataset:{action:'expedition',kind,id:'17'},disabled:false,closest:()=>true};
 function click(){let stopped=false;c.handleClick({target:{closest:()=>button},stopPropagation(){stopped=true;}});if(!stopped)launch(kind,17);return stopped;}
 function flushMicrotasks(){while(microtasks.length)microtasks.shift()();}
 function flushTimers(){while(timers.length){timers.shift()();flushMicrotasks();}}
 function flushBack(runTimers=true){
  assert(pendingBack>0,'Expected queued Back');pendingBack--;index=Math.max(0,index-1);
  // Chromium performs a microtask checkpoint after each native event listener,
  // not only after all popstate listeners. Timers run in a later task.
  for(const fn of listeners.get('popstate')||[]){fn({});flushMicrotasks();}
  events.push('popstate-complete');if(runTimers)flushTimers();
 }
 // Load order does not change the handoff: the real game loads the world
 // listener before the mobile controller, while this setup also covers reverse.
 function worldFirst(){const list=listeners.get('popstate');list.unshift(list.pop());}
 return {c,events,dialog,history,mobilePages,click,flushBack,flushTimers,worldFirst,button,get pendingBack(){return pendingBack;},launches:()=>events.filter(e=>Array.isArray(e)&&e[0]==='launch')};
}
for(const kind of ['monsters','nodes','node-attack','charms'])for(const worldFirst of [false,true]){
 const s=setup({kind});if(worldFirst)s.worldFirst();
 assert(s.click(),'The original event must not also reach the app action handler');
 assert.equal(s.dialog.open,false,'No new dialog while the card Back is pending');assert.equal(s.pendingBack,1);assert.equal(s.launches().length,0);
 s.flushBack();assert.equal(s.dialog.open,true,'Mobile command remains open after the old card entry is removed');
 assert(s.events.findIndex(e=>Array.isArray(e)&&e[0]==='launch')>s.events.indexOf('popstate-complete'),'The replacement dialog opens only after every native popstate listener completes');
 assert.deepEqual(s.launches(),[['launch',kind,17]]);assert.equal(s.history.state.conquerMobilePage.overlay,'dialog');assert(!s.history.state.conquerMapTarget);
 // A real Back after the handoff still closes this dialog exactly once.
 s.history.back();s.flushBack();assert.equal(s.dialog.open,false);assert.equal(s.launches().length,1);
}
{
 const s=setup();s.click();s.click();assert.equal(s.pendingBack,1,'Rapid repeated action must not queue two Back calls');s.flushBack();assert.equal(s.launches().length,1,'Rapid repeated action launches only once');
}
for(const kind of ['monsters','nodes','node-attack','charms'])for(const cancel of ['escape','hidden','detached','world'])for(const afterBack of [false,true]){
 const s=setup({kind});s.click();
 if(afterBack)s.flushBack(false);
 if(cancel==='escape')s.c.clearSelection();
 if(cancel==='hidden')s.c.sceneVisible=false;
 if(cancel==='detached')s.c.view.el.isConnected=false;
 if(cancel==='world')s.c.context.state.city.world_id=2;
 if(afterBack)s.flushTimers();else s.flushBack();
 assert.equal(s.launches().length,0,`${cancel} cancels the deferred ${kind} command, including between popstate and the launch task`);
}
{
 const s=setup();s.click();s.flushBack();s.dialog.close();s.mobilePages.closed('dialog');assert.equal(s.pendingBack,1,'Escape/close removes only the command entry');s.flushBack();assert.equal(s.dialog.open,false);assert.equal(s.launches().length,1);
}
{
 const s=setup({mobile:false});s.click();s.flushBack();assert.equal(s.dialog.open,true);assert.equal(s.history.state.conquerMobilePage,undefined,'Desktop receives no mobile overlay');
 for(const kind of ['monsters','nodes','node-attack','charms']){
  const direct=setup({card:false,kind});direct.click();assert.equal(direct.dialog.open,true);assert.equal(direct.pendingBack,0,'Actions without a card history entry remain immediate');assert.deepEqual(direct.launches(),[['launch',kind,17]]);
 }
 const directNode=setup({card:false});directNode.c.context.onGather(18);assert.deepEqual(directNode.launches(),[['launch','nodes',18]],'Direct node callback without optional kind still opens gathering');
}
console.log('PASS map action history: real game callbacks for monsters/resources/node attacks/charms, both listener orders, single dispatch, Back/Escape, cancellation, desktop and no-card entry.');
