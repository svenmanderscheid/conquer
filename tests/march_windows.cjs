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
const styles=names.map(n=>fs.readFileSync(root+'/assets/css/'+n+'.css','utf8')).join('\n');
const js=fs.readFileSync(root+'/assets/js/castle-skins.js','utf8')+'\n'+fs.readFileSync(root+'/assets/js/reward-dialog.js','utf8')+'\n'+fs.readFileSync(root+'/assets/js/march-panel.js','utf8');
const defs=[];for(let type=1;type<=3;type++)for(let tier=1;tier<=5;tier++)defs.push({code:50100000+type*100+tier,type,tier,attack:10*tier,gather_carry:10,speed:10,march_speed:11,monster_march_speed:20,monster_rally_speed:25,charm_march_speed:30,pvp_march_speed:40,pvp_rally_speed:50,reinforce_march_speed:55,shrine_neutral_speed:60,shrine_occupied_speed:70,gather_speed:80,field_attack_speed:90,monster_power:10*tier,monster_power_single_type:12*tier,monster_rally_power:11*tier,monster_rally_power_single_type:13*tier});
fs.writeFileSync(out+'/fixture.html',`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style><body class="mobile-game"><dialog id="game-dialog"><button class="dialog-close" aria-label="Schließen">×</button><div id="dialog-content"></div></dialog><script>${js}</script><script>const defs=${JSON.stringify(defs)};window.state={troop_defs:defs,troops:Object.fromEntries(defs.map(t=>[t.code,5000])),army_limits:{march_capacity:50000,march_slots:3},city:{coord_x:20,coord_y:20,action_points:200},marches:[],players:[],monsters:[{id:99,coord_x:170,coord_y:260,hp_current:1000,required_power:121,definition:{name:'Frostgrimm',art:'monsters/frostgrimm',type:'rally',level:1,action_point_cost:25,stats:{hp:1000,attack:10,defense:5},drops:[{label:'5.000 Gold',count:5},{label:'Ausbildung 30 Minuten',count:1},{label:'Heilung 30 Minuten',count:1},{label:'100.000 Nahrung',count:1},{label:'Goldtruhe',count:1}],gems_drop:{amount:50}}}],nodes:[{id:99,coord_x:170,coord_y:260,object_type:1,resource_amount:100000,gather_rate:10,can_attack:true,gatherer_march_id:null}]};window.sent=[];window.notices=[];const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));window.march=ConquerMarch({base:${JSON.stringify(assetBase)},esc,fmt:n=>Math.floor(Number(n)||0).toLocaleString('de-DE'),unitName:t=>['Infanterie','Bogenschützen','Kavallerie'][t.type-1]+' '+['','I','II','III','IV','V'][t.tier],getState:()=>state,toast:m=>notices.push(m),action:async(path,payload,message)=>{sent.push({path,payload,message});return{}},openDialog:html=>{const d=document.querySelector('#game-dialog');delete d.dataset.march;d.classList.remove('march-dialog');document.querySelector('#dialog-content').innerHTML=html;if(!d.open)d.showModal();d.scrollTop=0;}});document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)march.onClick(b.dataset.action,b)});window.openMarch=kind=>{sent=[];state.congress={id:99,coord_x:170,coord_y:260,name:"Kongress",can_attack:kind==="congress",can_garrison:kind==="congress-garrison",garrison_total:1500000};state.shrines=[{...state.congress,element:"forest",name:"Schrein des Lebens",can_attack:kind==="shrine",can_garrison:kind==="shrine-garrison",event:{active:true,starts_at:"2020-01-01 00:00:00",ends_at:"2099-01-01 00:00:00"}}];march.open(99,kind,{rally_id:421,target:{id:99,coord_x:170,coord_y:260,display_name:'Der lange Name des Königreichs',castle_level:8}})};</script></body></html>`);
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
for(const viewport of [{width:390,height:844},{width:320,height:568},{width:568,height:320},{width:768,height:1024},{width:1280,height:720}]){await page.setViewportSize(viewport);
for(const kind of ['players','rally','monster-rally','nodes','node-attack','rally-join','congress','congress-garrison','shrine','shrine-garrison']){
 await page.evaluate(kind=>openMarch(kind),kind);
 const read=()=>page.evaluate(()=>{
  const q=s=>document.querySelector(s),rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom,scrollWidth:el.scrollWidth,clientWidth:el.clientWidth,scrollHeight:el.scrollHeight,clientHeight:el.clientHeight}};
  return{boxes:Object.fromEntries(['#game-dialog','#dialog-content','.march-command','.march-layout','.march-target','.march-formation','.march-army','.march-footer','#march-confirm'].map(s=>[s,rect(q(s))])),controls:[...q('#game-dialog').querySelectorAll('button,input,select')].filter(el=>el.getClientRects().length&&getComputedStyle(el).visibility!=='hidden').map(el=>({label:el.id||el.getAttribute('aria-label')||el.textContent,...rect(el)}))}
 });
 const geometry=await read(),d=geometry.boxes['#game-dialog'];
 check(`${viewport.width}×${viewport.height} ${kind}: stable window bounds`,()=>{assert(d.x>=0&&d.y>=0&&d.right<=viewport.width+1&&d.bottom<=viewport.height+1)});
 check(`${viewport.width}×${viewport.height} ${kind}: all visible controls fit inside frame`,()=>{const bad=geometry.controls.filter(c=>c.x<d.x-1||c.y<d.y-1||c.right>d.right+1||c.bottom>d.bottom+1);assert.deepEqual(bad.map(c=>c.label),[])});
 check(`${viewport.width}×${viewport.height} ${kind}: no hidden/required scrolling`,()=>{for(const [name,b]of Object.entries(geometry.boxes))if(!['#march-confirm','.march-target'].includes(name))assert(b.scrollWidth<=b.clientWidth+1&&b.scrollHeight<=b.clientHeight+1,name+' '+JSON.stringify(b))});
 if(kind==='nodes'){
  const initial=await page.evaluate(()=>({total:[...document.querySelectorAll('.march-unit-amount input')].reduce((sum,input)=>sum+Number(input.value),0),carry:document.querySelector('#march-strength').textContent,forecast:document.querySelector('#march-forecast').textContent}));
  check(`${viewport.width}×${viewport.height} gathering: initial troops can empty the field`,()=>{assert.equal(initial.total,10000);assert.equal(initial.carry,'100.000');assert.match(initial.forecast,/Bis zu 100\.000 Nahrung/)});
 }
 await checkTargetArtwork(`${viewport.width}×${viewport.height} ${kind}`);
 await page.evaluate(()=>march.onClick('march-max',{}));const maximum=await page.evaluate(()=>Object.fromEntries([...document.querySelectorAll('.march-unit-amount input')].map(i=>[i.id.replace('march-unit-',''),Number(i.value)])));
 check(`${viewport.width}×${viewport.height} ${kind}: Max includes all 15 troop types within 50k`,()=>{assert.equal(Object.values(maximum).reduce((a,b)=>a+b,0),50000);assert.equal(Object.values(maximum).filter(n=>n>0).length,15);assert(Object.values(maximum).every(n=>Number.isSafeInteger(n)&&n<=5000))});
 const seen=[];for(let i=0;i<5;i++){seen.push(...await page.evaluate(()=>[...document.querySelectorAll('.march-unit-row:not([hidden])')].map(row=>Number(row.dataset.unit))));if(i<4)await page.evaluate(()=>march.onClick('march-page',{dataset:{id:'next'}}));}
 const preserved=await page.evaluate(()=>Object.fromEntries([...document.querySelectorAll('.march-unit-amount input')].map(i=>[i.id.replace('march-unit-',''),Number(i.value)])));
 check(`${viewport.width}×${viewport.height} ${kind}: paging preserves every selected count`,()=>{assert.equal(new Set(seen).size,15);assert.deepEqual(maximum,preserved)});
 if(['rally','monster-rally'].includes(kind)){await page.locator('[data-action="march-time-open"]').click();assert.deepEqual(await page.locator('[data-action="march-time-select"]').evaluateAll(bs=>bs.map(b=>Number(b.dataset.id))),[1,5,15,30]);await page.locator('[data-action="march-time-select"][data-id="15"]').click();await page.locator('[data-action="march-time-confirm"]').click();await page.locator('[data-action="march-time-open"]').click();await page.locator('[data-action="march-time-select"][data-id="30"]').click();await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'-rally-time.png'});await page.keyboard.press('Escape');assert.equal(await page.locator('#march-time-label').textContent(),'15 Min.');const forecast=await page.locator('#march-forecast').textContent();check(viewport.width+'x'+viewport.height+' '+kind+': timer changes update the visible rally forecast',()=>assert(forecast.includes('15 Min. Sammelzeit')));}await page.evaluate(()=>march.onClick('march-send',{}));
 const sent=await page.evaluate(()=>window.sent);
 check(`${viewport.width}×${viewport.height} ${kind}: dispatch payload matches endpoint`,()=>{assert.equal(sent.length,1);assert.equal(sent[0].path,{players:'march/dispatch-player',rally:'rally/start','monster-rally':'rally/start-monster',nodes:'march/dispatch-gather','node-attack':'march/dispatch-field-attack','rally-join':'rally/join',congress:'shrines/99/attack','congress-garrison':'shrines/99/garrison',shrine:'shrines/99/attack','shrine-garrison':'shrines/99/garrison'}[kind]);assert.equal(sent[0].payload.target_x,170);assert.equal(sent[0].payload.target_y,260);assert.deepEqual(sent[0].payload.troops,maximum);if(['rally','monster-rally'].includes(kind)){if(kind==='rally')assert.equal(sent[0].payload.target_player_id,99);assert.equal(sent[0].payload.rally_minutes,15)}if(kind==='rally-join')assert.equal(sent[0].payload.rally_id,421)});
 for(const [label,value,all]of [['zero',0,true],['above stock',5001,false],['fraction',1.5,false],['negative',-1,false],['over capacity',5000,true]]){
  await page.evaluate(({value,all})=>{march.onClick('march-clear',{});const inputs=[...document.querySelectorAll('.march-unit-amount input')];(all?inputs:[inputs[0]]).forEach(i=>i.value=value);march.update();march.onClick('march-send',{})},{value,all});
  const invalid=await page.evaluate(()=>({disabled:document.querySelector('#march-confirm').disabled,requests:sent.length}));check(`${viewport.width}×${viewport.height} ${kind}: ${label} blocks sending`,()=>{assert(invalid.disabled);assert.equal(invalid.requests,1)});
 }
 await page.evaluate(()=>march.onClick('march-max',{}));await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'-'+kind+'.png'});
 if(await page.locator('[data-action="march-view"][data-id="target"]').isVisible()){await page.locator('[data-action="march-view"][data-id="target"]').click();const target=await read();check(`${viewport.width}×${viewport.height} ${kind}: target view incl rally timer fits`,()=>{const bad=target.controls.filter(c=>c.x<d.x-1||c.y<d.y-1||c.right>d.right+1||c.bottom>d.bottom+1);assert.deepEqual(bad.map(c=>c.label),[]);const t=target.boxes['.march-target'];assert(t.scrollWidth<=t.clientWidth+1&&t.scrollHeight<=t.clientHeight+1)});await checkTargetArtwork(`${viewport.width}×${viewport.height} ${kind}`);await page.screenshot({path:out+'/'+viewport.width+'x'+viewport.height+'-'+kind+'-target.png'});}
 if(process.env.MARCH_VERBOSE)console.log('METRICS '+JSON.stringify({viewport,kind,boxes:geometry.boxes}));
}}
await page.evaluate(()=>{state.monsters[0].definition.type='solo';openMarch('monsters');});
let automatic=await page.evaluate(()=>({total:[...document.querySelectorAll('.march-unit-amount input')].reduce((sum,input)=>sum+Number(input.value),0),power:Number(document.querySelector('#march-strength').textContent.replace(/\D/g,'')),counts:Object.fromEntries([...document.querySelectorAll('.march-unit-amount input')].map(input=>[input.id.replace('march-unit-',''),Number(input.value)]).filter(([,count])=>count>0))}));
check('Solo monster attack preselects the minimum sufficient troop count',()=>{assert.equal(automatic.total,3);assert(automatic.power>=121);});
await page.evaluate(counts=>{const [code,count]=Object.entries(counts)[0];document.querySelector('#march-unit-'+code).value=count-1;march.update();},automatic.counts);
const belowMinimumForecast=await page.locator('#march-forecast').textContent();
check('One fewer troop falls below the solo monster power requirement',()=>assert.match(belowMinimumForecast,/zu wenig Macht|knapp unter der Siegesschwelle/));
await page.evaluate(()=>{state.monsters[0].definition.type='rally';openMarch('monster-rally');});
automatic=await page.evaluate(()=>({total:[...document.querySelectorAll('.march-unit-amount input')].reduce((sum,input)=>sum+Number(input.value),0),power:Number(document.querySelector('#march-strength').textContent.replace(/\D/g,''))}));
check('Monster rally preselects its minimum sufficient own contribution',()=>{assert.equal(automatic.total,2);assert(automatic.power>=121);});
await page.evaluate(()=>{state.army_limits.gather_march_slots=1;state.marches=[{march_type:5},{march_type:5},{march_type:5}];state.nodes=[{id:99,coord_x:170,coord_y:260,object_type:1,resource_amount:10000}];openMarch('nodes');march.onClick('march-max',{});});
check('Gather-only talent slot enables gathering after three combat marches',()=>{});
assert.equal(await page.locator('#march-confirm').isDisabled(),false);
await page.evaluate(()=>{openMarch('players');march.onClick('march-max',{});});assert.equal(await page.locator('#march-confirm').isDisabled(),true);
check('Gather-only talent slot cannot dispatch a fourth combat army',()=>{});
await page.evaluate(()=>{state.marches[0].march_type=9;march.update();});assert.equal(await page.locator('#march-confirm').isDisabled(),false);
check('Gathering in reserved slot leaves the third combat slot available',()=>{});
await page.evaluate(()=>{state.marches=[];openMarch('shrine');state.shrines[0].event.ends_at='2000-01-01 00:00:00';march.onClick('march-send',{})});const expiredRequests=await page.evaluate(()=>sent.length);check('Expired shrine event blocks dispatch from stale composer',()=>assert.equal(expiredRequests,0));
const etaKinds={monsters:20,'monster-rally':25,charms:30,players:40,rally:50,'rally-join':50,congress:60,'congress-garrison':60,shrine:60,'shrine-garrison':60,nodes:80,'node-attack':90};
for(const [kind,speed] of Object.entries(etaKinds)){
 await page.evaluate(kind=>{state.marches=[];if(kind==='monsters')state.monsters[0].definition.type='solo';openMarch(kind);march.onClick('march-max',{});},kind);
 const actual=await page.locator('#march-travel-time').textContent(),seconds=Math.max(5,Math.floor(Math.hypot(150,240)*100/speed)),expected=seconds>=60?`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} Min.`:`${seconds} Sek.`;
 check(`${kind}: ETA uses authoritative mission speed`,()=>assert.equal(actual,expected));
}
await page.evaluate(()=>{openMarch('shrine');state.shrines[0].alliance_id=2;march.onClick('march-max',{});march.update();});
{const actual=await page.locator('#march-travel-time').textContent(),seconds=Math.floor(Math.hypot(150,240)*100/70),expected=`${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')} Min.`;check('occupied shrine ETA uses PvP shrine speed',()=>assert.equal(actual,expected));}
check('No uncaught JavaScript errors',()=>assert.deepEqual(errors,[]));console.log(JSON.stringify({tests,failures,output:out},null,2));if(failures.length)process.exitCode=1;
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
