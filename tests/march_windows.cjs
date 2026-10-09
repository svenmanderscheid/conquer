'use strict';
// Isolated DOM/CSS and action-mock regression. No live API, account or database calls.
// Run: node tests/march_windows.cjs. Install Playwright + Chromium, or set
// PLAYWRIGHT_MODULE to its module path and PLAYWRIGHT_CHANNEL=chrome for system Chrome.
const fs=require('fs'),path=require('path'),assert=require('assert'),os=require('os');
const {pathToFileURL}=require('url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-march-windows-'));
const assetBase=pathToFileURL(root).href.replace(/\/$/,'');
const names=[...fs.readFileSync(root+'/views/game.php','utf8').matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(m=>m[1]);
const styles=names.map(n=>fs.readFileSync(root+'/assets/css/'+n+'.css','utf8')).join('\n').replaceAll('../fonts/',assetBase+'/assets/fonts/');
const js=require('./fixtures/isolated_locale.cjs')('de')+'\n'+fs.readFileSync(root+'/assets/js/castle-skins.js','utf8')+'\n'+fs.readFileSync(root+'/assets/js/reward-dialog.js','utf8')+'\n'+fs.readFileSync(root+'/assets/js/boss-mechanic.js','utf8')+'\n'+fs.readFileSync(root+'/assets/js/march-panel.js','utf8');
const defs=[];for(let type=1;type<=3;type++)for(let tier=1;tier<=5;tier++)defs.push({code:50100000+type*100+tier,type,tier,attack:10*tier,gather_carry:10,speed:10,march_speed:11,monster_march_speed:10+type*10,monster_rally_speed:15+type*10,charm_march_speed:30,pvp_march_speed:40,pvp_rally_speed:50,reinforce_march_speed:55,shrine_neutral_speed:60,shrine_occupied_speed:70,gather_speed:80,field_attack_speed:90,monster_power:10*tier,monster_power_single_type:12*tier,monster_rally_power:11*tier,monster_rally_power_single_type:13*tier});
fs.writeFileSync(out+'/fixture.html',`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style><body class="mobile-game"><dialog id="game-dialog"><button class="dialog-close" aria-label="Schließen">×</button><div id="dialog-content"></div></dialog><script>${js}</script><script>const defs=${JSON.stringify(defs)};window.state={troop_defs:defs,troops:Object.fromEntries(defs.map(t=>[t.code,5000])),army_limits:{march_capacity:50000,march_slots:3},city:{coord_x:20,coord_y:20,action_points:200},marches:[],players:[],monsters:[{id:99,coord_x:170,coord_y:260,hp_current:1000,required_power:121,definition:{name:'Frostgrimm',art:'monsters/frostgrimm',type:'rally',level:1,action_point_cost:25,stats:{hp:1000,attack:10,defense:5},drops:[{label:'5.000 Gold',count:5},{label:'Ausbildung 30 Minuten',count:1},{label:'Heilung 30 Minuten',count:1},{label:'100.000 Nahrung',count:1},{label:'Goldtruhe',count:1}],gems_drop:{amount:50}}}],nodes:[{id:99,coord_x:170,coord_y:260,object_type:1,resource_amount:100000,gather_rate:10,can_attack:true,gatherer_march_id:null}]};window.sent=[];window.notices=[];const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));window.march=ConquerMarch({base:${JSON.stringify(assetBase)},esc,fmt:n=>Math.floor(Number(n)||0).toLocaleString('de-DE'),unitName:t=>['Infanterie','Bogenschützen','Kavallerie'][t.type-1]+' '+['','I','II','III','IV','V'][t.tier],getState:()=>state,toast:m=>notices.push(m),action:async(path,payload,message)=>{sent.push({path,payload,message});return{}},openDialog:html=>{const d=document.querySelector('#game-dialog');delete d.dataset.march;d.classList.remove('march-dialog');document.querySelector('#dialog-content').innerHTML=html;if(!d.open)d.showModal();d.scrollTop=0;}});document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)march.onClick(b.dataset.action,b)});window.openMarch=kind=>{localStorage.clear();sent=[];state.congress={id:99,coord_x:170,coord_y:260,name:"Kongress",can_attack:kind==="congress",can_garrison:kind==="congress-garrison",garrison_total:1500000};state.shrines=[{...state.congress,element:"forest",name:"Schrein des Lebens",can_attack:kind==="shrine",can_garrison:kind==="shrine-garrison",event:{active:true,starts_at:"2020-01-01 00:00:00",ends_at:"2099-01-01 00:00:00"}}];march.open(99,kind,{rally_id:421,target:{id:99,coord_x:170,coord_y:260,display_name:'Der lange Name des Königreichs',castle_level:8}})};</script></body></html>`);
(async()=>{const browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})}),page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));try{await page.goto(pathToFileURL(path.join(out,'fixture.html')).href);
const failures=[];let tests=0;
const check=(name,fn)=>{try{fn();tests++;console.log('PASS '+name)}catch(e){failures.push(name+': '+e.message);console.log('FAIL '+name+': '+e.message)}};
const checkTargetArtwork=async label=>{
 const overlap=await page.evaluate(()=>{
  const target=document.querySelector('.march-target');if(!target.getClientRects().length)return null;
  const image=target.querySelector('.march-target-art img').getBoundingClientRect();
  return [...target.querySelectorAll('.march-target-heading,.march-target-stat,.march-pvp-note')].filter(el=>{
   const text=el.getBoundingClientRect();return Math.min(image.right,text.right)-Math.max(image.left,text.left)>1&&Math.min(image.bottom,text.bottom)-Math.max(image.top,text.top)>1;
  }).map(el=>el.className);
 });
 if(overlap)check(label+': target artwork does not overlap text',()=>assert.deepEqual(overlap,[]));
};
// Territory orders share this composer while retaining their own receipt-protected sender.
await page.addScriptTag({path:root+'/assets/js/territory-art.js'});
await page.evaluate(()=>{
 window.openTerritoryRally=kind=>{
  localStorage.clear();sent=[];window.territorySubmitted=[];
  state.buildings={hall_of_alliance:{rally_capacity:{total:1200}}};
  window.territoryTarget={id:kind+':wiltz',kind,name:kind==='commune'?'Commune Wiltz':kind==='canton'?'Shrine Wiltz':'Royal Castle',benefit_type:'lumber',x:170,y:260,can_attack:true,npc_troops:120};
  march.open(territoryTarget.id,'territory-rally',{target:territoryTarget,onTerritoryRally:async payload=>{territorySubmitted.push(payload);await new Promise(resolve=>window.releaseTerritoryCallback=resolve);return {};}});
 };
});
for(const viewport of [{width:320,height:568},{width:568,height:320},{width:1280,height:800}]){
 await page.setViewportSize(viewport);
 for(const kind of ['commune','canton','crown']){
  await page.evaluate(kind=>openTerritoryRally(kind),kind);
  const label=`${viewport.width}×${viewport.height} ${kind} rally`;
  const target=await page.evaluate(()=>territoryTarget),title=page.locator('.march-target-heading h3');
  assert.equal(await title.textContent(),target.name);assert.equal(await title.getAttribute('translate'),'no');
  const art=await page.locator('.march-target-art img').evaluate(async img=>{await img.decode();return {src:img.src,loaded:img.naturalWidth>0};});
  const expectedArt=await page.evaluate(base=>ConquerTerritoryArt.image(base,territoryTarget),assetBase);
  check(label+': string target keeps its name and approved territory art',()=>{assert.equal(typeof target.id,'string');assert(art.loaded);assert.equal(art.src,expectedArt);});
  assert.equal(await page.locator('[data-action="march-preview"]').count(),0,'territory does not send unsupported battle-preview requests');
  await page.evaluate(()=>march.onClick('march-max',{}));
  const troops=await page.locator('.march-unit-amount input').evaluateAll(inputs=>Object.fromEntries(inputs.map(i=>[i.id.replace('march-unit-',''),Number(i.value)]).filter(([,count])=>count>0)));
  check(label+': Max respects the smaller rally capacity',()=>assert.equal(Object.values(troops).reduce((sum,count)=>sum+count,0),1200));
  const time=await page.locator('#march-travel-time').textContent(),seconds=Math.floor(Math.hypot(150,240)*100/50);
  check(label+': ETA uses PvP rally speed',()=>assert.equal(time,`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} Min.`));
  let objective;
  if(kind==='crown'){
   if(await page.locator('[data-action="march-view"][data-id="target"]').isVisible())await page.locator('[data-action="march-view"][data-id="target"]').click();
   assert.deepEqual(await page.locator('#march-territory-objective option').evaluateAll(options=>options.map(o=>o.value)),['gate','arsenal','throne']);
   objective=viewport.width===320?'arsenal':viewport.width===568?'throne':'gate';await page.locator('#march-territory-objective').selectOption(objective);
  }else assert.equal(await page.locator('#march-territory-objective').count(),0);
  await page.locator('[data-action="march-time-open"]').click();await page.locator('[data-action="march-time-select"][data-id="15"]').click();await page.locator('[data-action="march-time-confirm"]').click();
  const confirm=await page.locator('#march-confirm').evaluate(button=>{const r=button.getBoundingClientRect();return {height:r.height,visible:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,hit:button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))};});
  check(label+': confirmation stays reachable after choosing target and duration',()=>assert(confirm.height>=44&&confirm.visible&&confirm.hit));
  assert.deepEqual(await page.evaluate(()=>({generic:sent.length,territory:territorySubmitted.length})),{generic:0,territory:0});
  await page.evaluate(()=>{march.onClick('march-send',{});march.onClick('march-send',{});});await page.waitForFunction(()=>territorySubmitted.length===1);
  const submitted=await page.evaluate(()=>({generic:sent.length,payloads:territorySubmitted}));
  check(label+': explicit confirmation invokes its sender once with the exact selection',()=>{assert.equal(submitted.generic,0);assert.deepEqual(submitted.payloads,[{troops,rally_minutes:15,objective}]);});
  await page.evaluate(()=>releaseTerritoryCallback());await page.waitForFunction(()=>!document.querySelector('#march-confirm').disabled);
  await page.screenshot({path:path.join(out,`territory-${kind}-${viewport.width}x${viewport.height}.png`)});
 }
}
await page.evaluate(()=>{
 openTerritoryRally('commune');state.troop_defs.forEach(t=>{t.power=10*t.tier;t.cavalry_pvp_rally_speed=125;});march.onClick('march-clear',{});
 document.querySelector('.march-unit-row[data-type="3"] input[type="number"]').value=1200;march.update();
});
{const actual=await page.locator('#march-travel-time').textContent(),seconds=Math.floor(Math.hypot(150,240)*100/125);check('Territory cavalry army uses the server-provided PvP rally cavalry speed',()=>assert.equal(actual,`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} Min.`));}
await page.evaluate(()=>{territoryTarget.can_attack=false;march.onClick('march-send',{});});
assert.equal(await page.locator('#march-confirm').isDisabled(),true);assert.equal(await page.evaluate(()=>territorySubmitted.length),0,'withdrawn target eligibility blocks dispatch');
await page.evaluate(()=>{delete state.buildings;state.troop_defs.forEach(t=>{delete t.power;delete t.cavalry_pvp_rally_speed;});});
for(const viewport of [{width:390,height:844},{width:320,height:568},{width:568,height:320},{width:768,height:1024},{width:1280,height:720}]){await page.setViewportSize(viewport);
for(const kind of ['players','rally','monster-rally','nodes','node-attack','rally-join','congress','congress-garrison','shrine','shrine-garrison']){
 await page.evaluate(kind=>openMarch(kind),kind);
 const read=()=>page.evaluate(()=>{
  const q=s=>document.querySelector(s),rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom,scrollWidth:el.scrollWidth,clientWidth:el.clientWidth,scrollHeight:el.scrollHeight,clientHeight:el.clientHeight}};
  return{boxes:Object.fromEntries(['#game-dialog','#dialog-content','.march-command','.march-layout','.march-target','.march-formation','.march-army','.march-footer','#march-confirm'].map(s=>[s,rect(q(s))])),controls:[...q('#game-dialog').querySelectorAll('button,input,select')].filter(el=>{if(!el.getClientRects().length||getComputedStyle(el).visibility==='hidden')return false;const r=el.getBoundingClientRect();for(let p=el.parentElement;p&&p!==q('#game-dialog');p=p.parentElement){if(/auto|scroll|hidden/.test(getComputedStyle(p).overflowY)){const b=p.getBoundingClientRect();if(r.bottom<=b.top||r.top>=b.bottom)return false;}}return true;}).map(el=>({label:el.id||el.getAttribute('aria-label')||el.textContent,...rect(el)}))}
 });
 const geometry=await read(),d=geometry.boxes['#game-dialog'];
 check(`${viewport.width}×${viewport.height} ${kind}: action points visible without scrolling`,()=>{});
 assert(await page.locator('#march-action-points').evaluate(el=>{const r=el.getBoundingClientRect(),box=el.closest('.march-army').getBoundingClientRect();return r.width>0&&r.height>0&&r.top>=box.top&&r.bottom<=box.bottom;}),'Action points must remain inside the visible army summary');
 check(`${viewport.width}×${viewport.height} ${kind}: stable window bounds`,()=>{assert(d.x>=0&&d.y>=0&&d.right<=viewport.width+1&&d.bottom<=viewport.height+1)});
 check(`${viewport.width}×${viewport.height} ${kind}: all visible controls fit inside frame`,()=>{const bad=geometry.controls.filter(c=>c.x<d.x-1||c.y<d.y-1||c.right>d.right+1||c.bottom>d.bottom+1);assert.deepEqual(bad.map(c=>c.label),[])});
 check(`${viewport.width}×${viewport.height} ${kind}: fixed frame has no overflow`,()=>{for(const [name,b]of Object.entries(geometry.boxes))if(!['#march-confirm','.march-target','.march-army'].includes(name))assert(b.scrollWidth<=b.clientWidth+1&&b.scrollHeight<=b.clientHeight+1,name+' '+JSON.stringify(b))});
 if(kind==='nodes'){
  const initial=await page.evaluate(()=>({total:[...document.querySelectorAll('.march-unit-amount input')].reduce((sum,input)=>sum+Number(input.value),0),carry:document.querySelector('#march-strength').textContent,forecast:document.querySelector('#march-forecast').textContent}));
  check(`${viewport.width}×${viewport.height} gathering: initial troops can empty the field`,()=>{assert.equal(initial.total,10000);assert.equal(initial.carry,'100.000');assert.match(initial.forecast,/Bis zu 100\.000 Nahrung/)});
 }
 await checkTargetArtwork(`${viewport.width}×${viewport.height} ${kind}`);
 await page.evaluate(()=>march.onClick('march-max',{}));const maximum=await page.evaluate(()=>Object.fromEntries([...document.querySelectorAll('.march-unit-amount input')].map(i=>[i.id.replace('march-unit-',''),Number(i.value)])));
 check(`${viewport.width}×${viewport.height} ${kind}: Max includes all 15 troop types within 50k`,()=>{assert.equal(Object.values(maximum).reduce((a,b)=>a+b,0),50000);assert.equal(Object.values(maximum).filter(n=>n>0).length,15);assert(Object.values(maximum).every(n=>Number.isSafeInteger(n)&&n<=5000))});
 const roster=await page.evaluate(()=>({count:document.querySelectorAll('.march-unit-row:not([hidden])').length,pages:document.querySelectorAll('[data-action="march-page"]').length,heights:[...document.querySelectorAll('.march-unit-row')].map(el=>el.getBoundingClientRect().height)}));
 check(`${viewport.width}×${viewport.height} ${kind}: all 15 troop types share one illustrated list`,()=>{assert.equal(roster.count,15);assert.equal(roster.pages,0);assert(roster.heights.every(h=>h>0&&h<=150),'Illustrated rows keep a bounded height and remain in one scrolling roster');});
 await page.locator('.march-unit-amount input').last().scrollIntoViewIfNeeded();
 const scrolled=await page.evaluate(()=>{const list=document.querySelector('.march-unit-list'),input=list.querySelector('.march-unit-row:last-child input[type=number]'),r=input.getBoundingClientRect();return{top:list.scrollTop,reachable:input.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)),counts:Object.fromEntries([...document.querySelectorAll('.march-unit-amount input')].map(i=>[i.id.replace('march-unit-',''),Number(i.value)]))};});
 check(`${viewport.width}×${viewport.height} ${kind}: last troop is reachable and scrolling preserves counts`,()=>{assert(scrolled.top>0);assert(scrolled.reachable);assert.deepEqual(maximum,scrolled.counts);});
 await page.evaluate(()=>march.update());
 check(`${viewport.width}×${viewport.height} ${kind}: refresh preserves list position`,()=>{});assert.equal(await page.locator('.march-unit-list').evaluate(el=>el.scrollTop),scrolled.top);
 await page.locator('.march-unit-list').evaluate(el=>el.scrollTop=0);
 if(['rally','monster-rally'].includes(kind)){await page.locator('[data-action="march-time-open"]').click();assert.deepEqual(await page.locator('[data-action="march-time-select"]').evaluateAll(bs=>bs.map(b=>Number(b.dataset.id))),[1,5,15,30]);await page.locator('[data-action="march-time-select"][data-id="15"]').click();await page.locator('[data-action="march-time-confirm"]').click();await page.locator('[data-action="march-time-open"]').click();await page.locator('[data-action="march-time-select"][data-id="30"]').click();await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'-rally-time.png'});await page.keyboard.press('Escape');assert.equal(await page.locator('#march-time-label').textContent(),'15 Min.');const forecast=await page.locator('#march-forecast').textContent();check(viewport.width+'x'+viewport.height+' '+kind+': timer changes update the visible rally forecast',()=>assert(forecast.includes('15 Min. Sammelzeit')));}await page.evaluate(()=>march.onClick('march-send',{}));
 const sent=await page.evaluate(()=>window.sent);
 check(`${viewport.width}×${viewport.height} ${kind}: dispatch payload matches endpoint`,()=>{assert.equal(sent.length,1);assert.equal(sent[0].path,{players:'march/dispatch-player',rally:'rally/start','monster-rally':'rally/start-monster',nodes:'march/dispatch-gather','node-attack':'march/dispatch-field-attack','rally-join':'rally/join',congress:'shrines/99/attack','congress-garrison':'shrines/99/garrison',shrine:'shrines/99/attack','shrine-garrison':'shrines/99/garrison'}[kind]);assert.equal(sent[0].payload.target_x,170);assert.equal(sent[0].payload.target_y,260);assert.deepEqual(sent[0].payload.troops,maximum);if(['rally','monster-rally'].includes(kind)){if(kind==='rally')assert.equal(sent[0].payload.target_player_id,99);assert.equal(sent[0].payload.rally_minutes,15)}if(kind==='rally-join')assert.equal(sent[0].payload.rally_id,421)});
 for(const [label,value,all]of [['zero',0,true],['above stock',5001,false],['fraction',1.5,false],['negative',-1,false],['over capacity',5000,true]]){
  await page.evaluate(({value,all})=>{march.onClick('march-clear',{});const inputs=[...document.querySelectorAll('.march-unit-amount input')];(all?inputs:[inputs[0]]).forEach(i=>i.value=value);march.update();march.onClick('march-send',{})},{value,all});
  const invalid=await page.evaluate(()=>({disabled:document.querySelector('#march-confirm').disabled,requests:sent.length}));check(`${viewport.width}×${viewport.height} ${kind}: ${label} blocks sending`,()=>{assert(invalid.disabled);assert.equal(invalid.requests,1)});
 }
 await page.evaluate(()=>march.onClick('march-max',{}));await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'-'+kind+'.png'});
 if(await page.locator('[data-action="march-view"][data-id="target"]').isVisible()){await page.locator('[data-action="march-view"][data-id="target"]').click();const target=await read();check(`${viewport.width}×${viewport.height} ${kind}: target view incl rally timer fits`,()=>{const targetFrame=target.boxes['#game-dialog'];assert(targetFrame.x>=0&&targetFrame.y>=0&&targetFrame.right<=viewport.width+1&&targetFrame.bottom<=viewport.height+1);const bad=target.controls.filter(c=>c.x<targetFrame.x-1||c.y<targetFrame.y-1||c.right>targetFrame.right+1||c.bottom>targetFrame.bottom+1);assert.deepEqual(bad.map(c=>c.label),[]);const t=target.boxes['.march-target'];assert(t.scrollWidth<=t.clientWidth+1)});await checkTargetArtwork(`${viewport.width}×${viewport.height} ${kind}`);await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'-'+kind+'-target.png'});}
 if(process.env.MARCH_VERBOSE)console.log('METRICS '+JSON.stringify({viewport,kind,boxes:geometry.boxes}));
}}
await page.evaluate(()=>{state.monsters[0].definition.type='solo';openMarch('monsters');});
let automatic=await page.evaluate(()=>({total:[...document.querySelectorAll('.march-unit-amount input')].reduce((sum,input)=>sum+Number(input.value),0),power:Number(document.querySelector('#march-strength').textContent.replace(/\D/g,'')),counts:Object.fromEntries([...document.querySelectorAll('.march-unit-amount input')].map(input=>[input.id.replace('march-unit-',''),Number(input.value)]).filter(([,count])=>count>0)),types:[...document.querySelectorAll('.march-unit-amount input')].filter(input=>Number(input.value)>0).map(input=>Number(input.closest('.march-unit-row').dataset.type))}));
check('Solo monster attack preselects the minimum sufficient troop count',()=>{assert.equal(automatic.total,3);assert(automatic.power>=121);});
check('Solo monster attack prefers cavalry when equally economical',()=>assert.deepEqual(automatic.types,[3]));
await page.evaluate(counts=>{const [code,count]=Object.entries(counts)[0];document.querySelector('#march-unit-'+code).value=count-1;march.update();},automatic.counts);
const belowMinimumForecast=await page.locator('#march-forecast').textContent();
check('One fewer troop falls below the solo monster power requirement',()=>assert.match(belowMinimumForecast,/zu wenig Macht|knapp unter der Siegesschwelle/));
await page.evaluate(()=>{state.monsters[0].definition.type='rally';openMarch('monster-rally');});
automatic=await page.evaluate(()=>({total:[...document.querySelectorAll('.march-unit-amount input')].reduce((sum,input)=>sum+Number(input.value),0),power:Number(document.querySelector('#march-strength').textContent.replace(/\D/g,'')),types:[...document.querySelectorAll('.march-unit-amount input')].filter(input=>Number(input.value)>0).map(input=>Number(input.closest('.march-unit-row').dataset.type))}));
check('Monster rally preselects its minimum sufficient own contribution',()=>{assert.equal(automatic.total,2);assert(automatic.power>=121);});
check('Monster rally prefers cavalry when equally economical',()=>assert.deepEqual(automatic.types,[3]));
await page.evaluate(()=>{state.army_limits.gather_march_slots=1;state.marches=[{march_type:5},{march_type:5},{march_type:5}];state.nodes=[{id:99,coord_x:170,coord_y:260,object_type:1,resource_amount:10000}];openMarch('nodes');march.onClick('march-max',{});});
check('Gather-only talent slot enables gathering after three combat marches',()=>{});
assert.equal(await page.locator('#march-confirm').isDisabled(),false);
await page.evaluate(()=>{openMarch('players');march.onClick('march-max',{});});assert.equal(await page.locator('#march-confirm').isDisabled(),true);
check('Gather-only talent slot cannot dispatch a fourth combat army',()=>{});
await page.evaluate(()=>{state.marches[0].march_type=9;march.update();});assert.equal(await page.locator('#march-confirm').isDisabled(),false);
check('Gathering in reserved slot leaves the third combat slot available',()=>{});
await page.evaluate(()=>{state.army_limits.hunt_march_slots=1;state.marches=[{march_type:7},{march_type:7},{march_type:7}];state.monsters[0].definition.type='solo';openMarch('monsters');});
check('Hunter-only slot permits a solo hunt after three PvP marches',()=>{});assert.equal(await page.locator('#march-confirm').isDisabled(),false);
await page.evaluate(()=>{openMarch('players');march.onClick('march-max',{});});check('Unused Hunter-only slot cannot launch PvP',()=>{});assert.equal(await page.locator('#march-confirm').isDisabled(),true);
await page.evaluate(()=>{state.marches[0].march_type=5;march.update();});check('Solo hunt in its reserved slot leaves a regular slot available',()=>{});assert.equal(await page.locator('#march-confirm').isDisabled(),false);
await page.evaluate(()=>{state.marches=[];state.monster_ap_discount=.1;state.city.action_points=23;openMarch('monsters');});check('AP cost preview rounds the discounted 25 AP up to 23',()=>{});assert.equal(await page.locator('#march-action-points').textContent(),'23 / 23');assert.equal(await page.locator('#march-confirm').isDisabled(),false);
await page.evaluate(()=>{state.city.action_points=22;march.update();});check('Discounted monster attack still requires enough AP',()=>{});assert.equal(await page.locator('#march-confirm').isDisabled(),true);
await page.evaluate(()=>{state.army_limits.hunt_march_slots=0;state.monster_ap_discount=0;state.city.action_points=200;});
await page.evaluate(()=>{state.marches=[];openMarch('shrine');state.shrines[0].event.ends_at='2000-01-01 00:00:00';march.onClick('march-send',{})});const expiredRequests=await page.evaluate(()=>sent.length);check('Expired shrine event blocks dispatch from stale composer',()=>assert.equal(expiredRequests,0));
const etaKinds={monsters:20,'monster-rally':25,charms:30,players:40,rally:50,'rally-join':50,congress:60,'congress-garrison':60,shrine:60,'shrine-garrison':60,nodes:80,'node-attack':90};
for(const [kind,speed] of Object.entries(etaKinds)){
 await page.evaluate(kind=>{state.marches=[];if(kind==='monsters')state.monsters[0].definition.type='solo';openMarch(kind);march.onClick('march-max',{});},kind);
 const actual=await page.locator('#march-travel-time').textContent(),seconds=Math.max(5,Math.floor(Math.hypot(150,240)*100/speed)),expected=seconds>=60?`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} Min.`:`${seconds} Sek.`;
 check(`${kind}: ETA uses authoritative mission speed`,()=>assert.equal(actual,expected));
}
await page.evaluate(()=>{openMarch('shrine');state.shrines[0].alliance_id=2;march.onClick('march-max',{});march.update();});
{const actual=await page.locator('#march-travel-time').textContent(),seconds=Math.floor(Math.hypot(150,240)*100/70),expected=`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} Min.`;check('occupied shrine ETA uses PvP shrine speed',()=>assert.equal(actual,expected));}
// Gathering always recomputes the required carry, not the previous manual army.
await page.evaluate(base=>{openMarch('nodes');localStorage.setItem(`conquer:march-choice:v1:${base}:${state.city.player_id}:${state.city.world_id}:nodes`,JSON.stringify({[defs[0].code]:1}));state.nodes[0].resource_amount=12345;march.open(99,'nodes');},assetBase);
assert.equal(await page.locator('.march-remembered').count(),0);
assert.equal(await page.locator('.march-unit-amount input').evaluateAll(inputs=>inputs.reduce((sum,input)=>sum+Number(input.value),0)),1235,'round carry up to cover remaining resources');
await page.evaluate(()=>{state.nodes[0].resource_amount=100000;});
// Updated portraits must load, including historical monster IDs and all resource sites.
for(const [objectType,file] of [[1,'farm-v8'],[2,'lumber-v8'],[3,'quarry-v8'],[4,'gold-v2'],[5,'crystal-v8']]){
 await page.evaluate(type=>{state.nodes[0].object_type=type;openMarch('nodes');},objectType);
 await page.waitForFunction(()=>[...document.querySelectorAll('.march-target-art img,.march-portrait img,.march-selected-card img')].every(i=>i.complete&&i.naturalWidth>0));
 check('Painted resource '+file,()=>{});
 assert.match(await page.locator('.march-target-art img').getAttribute('src'),new RegExp('world-'+file+'\\.png$'));
 assert((await page.locator('.march-portrait img').first().getAttribute('src')).includes('fantasy-troops-v3/'));
}
for(const id of ['orc','skeleton','golem','treasure-goblin','green-dragon','red-dragon','gold-dragon','magdar','frostgrimm']){
 await page.evaluate(id=>{state.monsters[0].definition.art='monsters/'+id;state.monsters[0].definition.name=id;openMarch('monster-rally');},id);
 await page.waitForFunction(()=>{const i=document.querySelector('.march-target-art img');return i.complete&&i.naturalWidth>0;});
 const src=await page.locator('.march-target-art img').getAttribute('src');
 assert(src.includes(id==='frostgrimm'?'monsters/storybook-v2/frostgrimm.png':'monsters/2.5d/bright-v2/'),'each encounter uses its current portrait');
}
await page.addScriptTag({path:root+'/assets/js/monster-report.js'});
for(const art of ['', 'orc', 'goblin', 'monsters/treasure-goblin']){
 await page.evaluate(art=>{Object.assign(state.monsters[0].definition,{name:'Treasure Goblin',art,type:'solo'});openMarch('monsters');},art);
 assert.match(await page.locator('.march-target-art img').getAttribute('src'),/bright-v2\/treasure-goblin-turquoise\.png$/,'Goblin must not fall back to the orc portrait');
 await page.waitForFunction(()=>{const i=document.querySelector('.march-target-art img');return i.complete&&i.naturalWidth>0;});
}
// Joining uses the rule saved when this rally started, not today's monster catalogue.
// No calculator/server-result flags are invented for a contribution still being chosen.
await page.evaluate(()=>{
 window.savedJoinRule=Object.freeze({id:'frostgrimm_ice_armor',version:1,required_power_percent:17,counter_type:'infantry',counter_power_percent:31});
 state.monsters[0].definition.boss_mechanic={...savedJoinRule,required_power_percent:12,counter_power_percent:50};
 window.openJoinSkillCase=(kind,hasRule)=>{
  state.marches=[];localStorage.clear();sent=[];
  march.open(99,'rally-join',{rally_id:421,rally_target_kind:kind,rally_status:'gathering',rally_launch_at:new Date(Date.now()+600000).toISOString(),rally_capacity_remaining:1200,rally_boss_mechanic:hasRule?savedJoinRule:null,target:{id:99,coord_x:21,coord_y:20,display_name:'Rally Leader',castle_level:8}});
 };
});
for(const viewport of [{width:320,height:568},{width:568,height:320},{width:1280,height:800}]){
 await page.setViewportSize(viewport);await page.evaluate(()=>openJoinSkillCase('monster',true));
 const label=viewport.width+'×'+viewport.height+' monster rally join',card=page.locator('.march-army .boss-mechanic');
 await card.waitFor({state:'visible'});
 const rule=await card.evaluate(el=>({id:el.dataset.bossMechanic,state:el.dataset.bossState,params:JSON.parse(el.querySelector('[data-i18n="boss.frostgrimm.rule"]').dataset.i18nParams),text:el.querySelector('[data-i18n="boss.frostgrimm.rule"]').textContent,expected:ConquerLocale.t('boss.frostgrimm.rule',{effect:'17',threshold:'31'}),keys:[...el.querySelectorAll('[data-i18n]')].map(node=>node.dataset.i18n)}));
 check(label+': saved 17% / 31% rule stays exact',()=>{assert.equal(rule.id,'frostgrimm_ice_armor');assert.deepEqual(rule.params,{effect:'17',threshold:'31'});assert.equal(rule.text,rule.expected);});
 check(label+': unresolved rule never claims an active or countered result',()=>{assert.equal(rule.state,'rule');assert(!rule.keys.some(key=>/\.(active|inactive|countered|share|formation)$/.test(key)||key.startsWith('boss.effects.required_power')));assert(rule.keys.includes('boss.effects.counter_basis'));assert(rule.keys.includes('boss.frostgrimm.rally'));});
 check(label+': joining has no unsupported calculator',()=>{});assert.equal(await page.locator('[data-action="march-preview"],.battle-preview-dialog,#march-preflight').count(),0);
 // Check both ends through actual scrolling: a short army pane need not display
 // the entire explanation at once, but every part must remain reachable.
 for(const key of ['boss.frostgrimm.title','boss.frostgrimm.rally']){
  const text=card.locator('[data-i18n="'+key+'"]');await text.scrollIntoViewIfNeeded();
  const visible=await text.evaluate(el=>{const r=el.getBoundingClientRect(),pane=el.closest('.march-army').getBoundingClientRect(),left=Math.max(r.left,pane.left,0),right=Math.min(r.right,pane.right,innerWidth),top=Math.max(r.top,pane.top,0),bottom=Math.min(r.bottom,pane.bottom,innerHeight);return right>left&&bottom>top&&(el.closest('p')||el).contains(document.elementFromPoint((left+right)/2,(top+bottom)/2));});
  check(label+': '+key+' is visible inside its scrolling pane',()=>assert(visible));
 }
 check(label+': skill copy has no horizontal overflow',()=>{});assert(await card.evaluate(el=>el.scrollWidth<=el.clientWidth+1));
 const confirm=await page.locator('#march-confirm').evaluate(el=>{const r=el.getBoundingClientRect();return{width:r.width,height:r.height,inViewport:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,hit:el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)),enabled:!el.disabled};});
 check(label+': 44px confirm remains enabled and reachable after scrolling',()=>{assert(confirm.width>=43&&confirm.height>=43);assert(confirm.inViewport&&confirm.hit&&confirm.enabled);});
 await page.locator('#march-confirm').click({trial:true});
 const untouched=await page.evaluate(()=>({sent:sent.length,saved:savedJoinRule,current:state.monsters[0].definition.boss_mechanic}));
 check(label+': reading and trial tap start no action or mutation',()=>{assert.equal(untouched.sent,0);assert.equal(untouched.saved.required_power_percent,17);assert.equal(untouched.saved.counter_power_percent,31);assert.equal(untouched.current.required_power_percent,12);assert.equal(untouched.current.counter_power_percent,50);});
 await page.screenshot({path:path.join(out,`saved-boss-rally-join-${viewport.width}x${viewport.height}.png`)});
 await page.evaluate(()=>{march.onClick('march-clear',{});document.querySelector('.march-unit-amount input').value=500;march.update();});
 check(label+': changing the army does not resolve a saved rule locally',()=>{});assert.equal(await card.getAttribute('data-boss-state'),'rule');assert.equal(await card.locator('[data-i18n="boss.frostgrimm.countered"],[data-i18n="boss.frostgrimm.active"]').count(),0);
 await page.evaluate(()=>openJoinSkillCase('player',true));
 check(label+': city rally join has no monster-skill card',()=>{});assert.equal(await page.locator('.boss-mechanic').count(),0);
 await page.evaluate(()=>openJoinSkillCase('monster',false));
 check(label+': legacy monster rally without a saved rule has no card',()=>{});assert.equal(await page.locator('.boss-mechanic').count(),0);
}
for(const viewport of [{width:1280,height:800},{width:390,height:844},{width:320,height:568},{width:844,height:390}]){
 await page.setViewportSize(viewport);
 await page.evaluate(base=>{
  const troops=['infantry','ranged','cavalry'].flatMap(type=>[1,5,10].map(tier=>({type,tier,name:type+' '+tier,sent:100,dead:0,injured:0,survived:100})));
  const report={id:1,outcome:'attacker_wins',target_x:10,target_y:20,details:{monster_snapshot:{name:'Ork',art:'monsters/orc',level:2,count:1},troops}};
  const dialog=document.querySelector('#game-dialog');dialog.className='combat-report-dialog monster-report-dialog';delete dialog.dataset.march;
  document.querySelector('#dialog-content').innerHTML=ConquerMonsterReport.render(report,{base});
  dialog.querySelector(':scope > .popup-heading')?.remove();
  const header=document.createElement('header');header.className='popup-heading';header.append(dialog.querySelector('h2'));dialog.insertBefore(header,document.querySelector('#dialog-content'));dialog.classList.add('has-popup-heading');
 },assetBase);
 await page.waitForFunction(()=>[...document.querySelectorAll('.monster-report img')].every(i=>i.complete&&i.naturalWidth>0));
 assert((await page.locator('.cr-identity.defender img').getAttribute('src')).includes('bright-v2/orc.png'));
 assert.equal(await page.locator('img[src*="fantasy-troops-v3"]').count(),9);
 assert(await page.locator('.combat-report').evaluate(el=>el.scrollWidth<=el.clientWidth+1),'Report has no horizontal overflow');
 await page.screenshot({path:path.join(out,`updated-report-${viewport.width}.png`)});
}
check('No uncaught JavaScript errors',()=>assert.deepEqual(errors,[]));console.log(JSON.stringify({tests,failures,output:out},null,2));if(failures.length)process.exitCode=1;
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
