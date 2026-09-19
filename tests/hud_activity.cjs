'use strict';
// Real PHP shell and queue HUDs, deterministic server clock; no account or order is changed.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-hud-activity-'));
const php=`define('ROOT_DIR',${JSON.stringify(root.replaceAll('\\','/'))});define('APP_BASE','');require ROOT_DIR.'/src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();$session=['username'=>'HUD Fixture'];require ROOT_DIR.'/views/game.php';`;
const html=execFileSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['-r',php],{encoding:'utf8'}).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 try{
  const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://hud.fixture/**',route=>{
   const url=new URL(route.request().url());if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html});
   const file=path.resolve(root,'.'+url.pathname);
   if(!file.startsWith(root+path.sep)||!fs.existsSync(file))return route.fulfill({status:404});
   return route.fulfill({path:file});
  });
  await page.goto('https://hud.fixture/');
  for(const name of ['training-hud','game-overlay','world-chat'])await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js',name+'.js'),'utf8')});
  await page.evaluate(()=>{
   document.body.classList.add('city-mode');
   window.time=Date.parse('2030-01-01T12:00:00Z');
   window.state={player:{name:'Fixture'},city:{power:10,world_id:1},lord:{level:1},vip:{building_slots:1},buildings:{farm:{name:'Bauernhof'}},research_defs:[],build_queue:[],research_queue:[],troop_queue:[]};
   window.kingdom={profile:{display_name:'HUD Fixture',power:64007,avatar:'knight',lord_level:3,lord_max_level:60,lord_xp_into:125,lord_xp_next:500,action_points:130,action_points_max:200,gems:75},vip:{building_slots:1}};
   const date=s=>Date.parse(String(s).replace(' ','T').replace(/Z?$/,'Z'));
   const context={getState:()=>state,getKingdom:()=>kingdom,now:()=>time,date,fmt:String,esc:String,openDialog:()=>{},countdown:String};
   window.overlay=ConquerOverlay(context);window.training=ConquerTrainingHud(context);
   const messages=Array.from({length:12},(_,i)=>({id:i+1,player_id:2,username:'Elara',message:'Wer sammelt heute Holz?',created_at:'2030-01-01 11:59:00'}));
   window.chat=ConquerWorldChat({...context,navigate:()=>{},api:async()=>({world_id:1,player_id:1,alliance:{id:1},world_chat:messages,alliance_chat:messages})});chat.update(true);
   document.querySelector('#resources').innerHTML=['food','lumber','stone','gold'].map((key,i)=>`<button class="resource" aria-label="Ressource ${i+1}"><span class="resource-icon" aria-hidden="true"><img src="/assets/art/ui-resources/${key}.png" alt=""></span><span><strong>${['490.562','994.901','632.009','120.037'][i]}</strong><small>${['Nahrung','Holz','Stein','Gold'][i]}</small></span></button>`).join('');
   window.draw=()=>{overlay.update();training.update()};
   window.orders=()=>{state.build_queue=[{id:1,building_code:'farm',started_at:'2030-01-01 11:30:00',finishes_at:'2030-01-01 12:30:00'}];state.research_queue=[{id:2,started_at:'2030-01-01 11:40:00',finishes_at:'2030-01-01 12:40:00'}];state.troop_queue=[{id:3,count:250,started_at:'2030-01-01 11:50:00',finishes_at:'2030-01-01 12:20:00'}];kingdom.hospital={used:80,waiting:20,active:{batch_id:'healing-1',count:60,started_at:'2030-01-01 11:30:00',ends_at:'2030-01-01 12:30:00'}};draw()};draw();
  });
  assert.equal(await page.locator('#hud-energy strong').innerText(),'130 / 200');
  assert.equal(await page.locator('#hud-energy-fill').evaluate(e=>e.style.width),'65%');
  assert.equal(await page.locator('#hud-hunter-xp').innerText(),'125 / 500 XP');
  assert.equal(await page.locator('#hud-hunter-fill').evaluate(e=>e.style.width),'25%');
  assert.deepEqual(await page.locator('.hud-right-tools [data-id]').evaluateAll(buttons=>buttons.map(button=>button.dataset.id)),['events'],'Retired Feldzüge and Meisterschaft shortcuts stay out of the HUD');
  const stateOf=id=>page.locator('#'+id).getAttribute('data-job-state');
  assert.equal(await page.locator('#hud-healing').isVisible(),false);
  assert.equal(await stateOf('hud-research'),'idle');assert.equal(await stateOf('hud-build-second'),'locked');
  assert.equal(await page.locator('#hud-build-second').getAttribute('data-action'),'vip-open');
  await page.evaluate(()=>orders());
  for(const id of ['hud-healing','hud-build','hud-research','hud-training'])assert.equal(await stateOf(id),'active');
  assert.equal(await page.locator('#hud-healing .hud-job-state').innerText(),'In Behandlung');assert.match(await page.locator('#hud-healing').getAttribute('aria-label'),/60 Truppen in Behandlung/);
  assert.equal(await page.locator('#hud-build .hud-job-progress').evaluate(e=>e.style.width),'50%');
  assert.equal(await page.locator('#hud-research .hud-job-time').innerText(),'40:00');
  await page.evaluate(()=>{time+=1000;draw()});assert.equal(await page.locator('#hud-research .hud-job-time').innerText(),'39:59');
  await page.evaluate(()=>{state.research_queue[0].finishes_at='2030-01-01 12:00:10';draw()});
  assert.equal(await page.locator('#hud-research .hud-job-time').innerText(),'0:09','Speedups immediately replace the deadline');
  await page.evaluate(()=>{time+=10000;draw()});assert.equal(await stateOf('hud-research'),'finishing');
  assert.match(await page.locator('#hud-research').getAttribute('aria-label'),/Server bestätigt/);
  await page.evaluate(()=>{state.research_queue=[];draw()});assert.equal(await stateOf('hud-research'),'idle');
  await page.evaluate(()=>{kingdom.vip.building_slots=2;state.build_queue.push({id:4,started_at:'2030-01-01 11:00:00',finishes_at:'2030-01-01 13:00:00'});draw()});
  assert.equal(await stateOf('hud-build-second'),'active');assert.equal(await page.locator('#hud-build-second').getAttribute('data-action'),'buildings');
  await page.evaluate(()=>{state.research_queue=[{id:5,finishes_at:'invalid'}];draw()});
  assert.equal(await stateOf('hud-research'),'active');assert.equal(await page.locator('#hud-research .hud-job-time').innerText(),'Zeit offen');
  await page.evaluate(()=>{state.build_queue=[];kingdom.vip.building_slots=1;orders()});
  const failures=[];
  for(const [width,height] of [[1280,800],[1280,720],[820,720],[526,705],[390,844],[320,568],[568,320],[844,390],[1152,500]])for(const mode of ['active','idle','finishing']){
   await page.setViewportSize({width,height});
   await page.evaluate(mode=>{time=Date.parse('2030-01-01T12:00:11Z');orders();if(mode==='idle'){state.build_queue=[];state.research_queue=[];state.troop_queue=[];kingdom.hospital=null}if(mode==='finishing')time+=3600000;draw()},mode);
   const result=await page.evaluate(()=>{
    const rect=e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height}};
    const jobs=[...document.querySelectorAll('.hud-job')].filter(e=>e.getClientRects().length);
    const other=[...document.querySelectorAll('#world-chat,.hud-right-tools button')].filter(e=>getComputedStyle(e).visibility!=='hidden');
    const overlap=(a,b)=>a.x<b.right-1&&a.right>b.x+1&&a.y<b.bottom-1&&a.bottom>b.y+1;
    const hud=[document.querySelector('.hud-profile'),document.querySelector('#resources'),document.querySelector('#hud-gems')].filter(e=>e.getClientRects().length),hudBad=[];
    const statusButtons=['hud-vip-button','lord-talent-button','hud-energy'].map(id=>rect(document.getElementById(id))),statusHeights=statusButtons.map(r=>r.height);
    if(Math.max(...statusHeights)-Math.min(...statusHeights)>1)hudBad.push('VIP, Hunter and AP do not have equal heights');
    for(const e of hud){const r=rect(e);if(r.x<0||r.y<0||r.right>innerWidth||r.bottom>innerHeight)hudBad.push((e.id||e.className)+' outside viewport');if(e.id!=='resources'&&(e.scrollWidth>e.clientWidth+2||e.scrollHeight>e.clientHeight+2))hudBad.push((e.id||e.className)+` overflows ${e.clientWidth}x${e.clientHeight} -> ${e.scrollWidth}x${e.scrollHeight}`);}
    for(let i=0;i<hud.length;i++)for(let j=i+1;j<hud.length;j++)if(overlap(rect(hud[i]),rect(hud[j])))hudBad.push((hud[i].id||hud[i].className)+' overlaps '+(hud[j].id||hud[j].className));
    for(const resource of document.querySelectorAll('#resources .resource'))for(const tool of document.querySelectorAll('.hud-right-tools button'))if(overlap(rect(resource),rect(tool)))hudBad.push('Resource overlaps '+(tool.id||tool.getAttribute('aria-label')));
    if(innerWidth<=600&&innerHeight>520){
     const profile=rect(document.querySelector('.hud-profile')),vip=rect(document.querySelector('#hud-vip-button')),energy=rect(document.querySelector('#hud-energy')),hunter=rect(document.querySelector('#lord-talent-button'));
     if(profile.height>104)hudBad.push('Mobile profile is taller than the compact two-row HUD');
     if(!(vip.y<hunter.y&&hunter.y<energy.y))hudBad.push('VIP, Hunter and AP are not stacked in order');
    }
    if(innerWidth>innerHeight&&innerHeight<=520){
     const profile=rect(document.querySelector('.hud-profile')),resourceRows=[...document.querySelectorAll('#resources .resource')].map(rect);
     if(profile.height>76)hudBad.push('Landscape profile is taller than the compact status band');
     if(resourceRows.some(r=>Math.abs(r.y-resourceRows[0].y)>1))hudBad.push('Landscape resources wrap to a second row');
    }
    return hudBad.concat(jobs.flatMap(e=>{const r=rect(e),bad=[];
     if(r.x<0||r.y<0||r.right>innerWidth||r.bottom>innerHeight)bad.push(e.id+' outside viewport');
     if(e.scrollWidth>e.clientWidth+2||e.scrollHeight>e.clientHeight+2)bad.push(e.id+' overflows');
     for(const n of e.querySelectorAll('.hud-edge-label,.hud-job-state,.hud-job-time')){const b=rect(n);if(b.width&&(b.x<r.x||b.right>r.right||b.bottom>r.bottom))bad.push(e.id+' clips '+n.textContent)}
     if(innerWidth<=900||innerHeight<=520){
      if(r.width>60||r.height>52||r.width<44||r.height<44)bad.push(e.id+' is not a compact touch target');
      if(rect(e.querySelector('.hud-edge-label')).width)bad.push(e.id+' still shows its title');
      if(!e.getAttribute('aria-label'))bad.push(e.id+' lost its accessible description');
      if(['idle','locked'].includes(e.dataset.jobState)&&rect(e.querySelector('.hud-job-time')).width)bad.push(e.id+' shows inactive text');
     }
     for(const n of other)if(overlap(r,rect(n)))bad.push(e.id+' overlaps '+(n.id||n.textContent));
     if(innerHeight<=520)for(const n of hud)if(overlap(r,rect(n)))bad.push(e.id+' overlaps '+(n.id||n.className));
     return bad;
    }));
   });
   if(result.length)failures.push({width,height,mode,result});
   const chatResult=await page.evaluate(()=>{
    const chat=document.querySelector('#world-chat'),r=chat.getBoundingClientRect(),dock=document.querySelector('#navigation'),dr=dock.getBoundingClientRect(),bad=[];
    if(r.left<0||r.top<0||r.right>innerWidth||r.bottom>innerHeight)bad.push('Chat outside viewport');
    if(dr.height&&Math.abs(r.height-dr.height)>3)bad.push(`Chat preview and dock differ in height (${r.height}px / ${dr.height}px)`);
    if(dr.height&&r.bottom>dr.top+1)bad.push('Chat preview overlaps the dock');
    if(chat.querySelectorAll('.world-chat-preview-message').length>2)bad.push('Chat preview shows more than two messages');
    return bad;
   });
   if(chatResult.length)failures.push({width,height,mode,result:chatResult});
   await page.screenshot({path:path.join(output,`${width}x${height}-${mode}.png`)});
   await page.locator('.world-chat-preview').click();
   assert.equal(await page.locator('#world-chat-body').isVisible(),true);
   assert.ok((await page.locator('#world-chat').boundingBox()).height<=height+1,'Full chat stays inside viewport');
   await page.locator('[data-chat-close]').first().click();
  }
  await page.evaluate(()=>{document.body.classList.remove('city-mode');document.body.classList.add('world-mode')});
  assert.equal(await page.locator('#hud-training').isVisible(),false);assert.equal(await page.locator('#hud-build').isVisible(),false);assert.equal(await page.locator('#hud-healing').isVisible(),true);
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[],JSON.stringify(failures));
  console.log('PASS queue states, deadlines, speedups, server confirmation, second slot, invalid dates, 27 compact HUD/chat layouts, full chat and world visibility. '+output);
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
