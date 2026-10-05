'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const output=path.join(root,'artifacts/rally-hud');fs.mkdirSync(output,{recursive:true});
const php=`define('ROOT_DIR',${JSON.stringify(root.replaceAll('\\','/'))});define('APP_BASE','');require ROOT_DIR.'/src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();$session=['username'=>'Rally Fixture'];$uiLayoutProfiles=\\Conquer\\Game\\Ui\\LayoutSettings::defaults();if(\\Conquer\\Db\\Connection::isInitialized())throw new RuntimeException('Unexpected database initialization');require ROOT_DIR.'/views/game.php';if(\\Conquer\\Db\\Connection::isInitialized())throw new RuntimeException('Unexpected database initialization');`;
const html=execFileSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['-r',php],{encoding:'utf8',maxBuffer:8*1024*1024}).replace(/<script\b[^>]*>[\s\S]*?<\/script>/gi,'').replace(/<section id="app-start"[\s\S]*?<\/section>/,'');
async function assertRallySkillSnapshots(page){
 await page.setViewportSize({width:1280,height:800});
 for(const name of ['boss-mechanic','rally-panel'])await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js',name+'.js'),'utf8')});
 await page.evaluate(()=>{
  // Deliberately differs from today's 12% / 50% catalog: this rally owns its saved rule.
  window.savedRallyRule=Object.freeze({id:'frostgrimm_ice_armor',version:1,required_power_percent:17,counter_type:'infantry',counter_power_percent:31});
  window.rallyDetailFixture={id:91,leader_player_id:8,leader_name:'Frozen leader',leader:{name:'Frozen leader',coord_x:12,coord_y:13},origin_x:12,origin_y:13,target_x:25,target_y:26,target_kind:'monster',status:'gathering',launch_at:'2030-01-01 12:05:00',capacity:2000,troops:{50100101:200},participants:[],result:{monster:{name:'Frostgrimm',level:1,art:'monsters/storybook-v2/frostgrimm',boss_mechanic:savedRallyRule}}};
  window.rallyDetailRenders=0;window.rallyReadCalls=[];window.rallyWriteCalls=[];window.rallyJoinOptions=null;window.rallyFixtureErrors=[];
  const dialog=document.querySelector('#game-dialog'),content=document.querySelector('#dialog-content');
  window.rallyDetailPanel=ConquerRallies({base:'',api:async endpoint=>{rallyReadCalls.push(endpoint);if(endpoint!=='rally/91')throw new Error('Unexpected endpoint '+endpoint);return {rally:structuredClone(rallyDetailFixture),participants:[]};},esc:value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),fmt:value=>ConquerLocale.formatNumber(value),date:value=>Date.parse(String(value).replace(' ','T')+'Z'),duration:value=>String(value),now:()=>fixtureNow,getState:()=>fixtureState,toast:message=>rallyFixtureErrors.push(message),action:async(...args)=>{rallyWriteCalls.push(args);throw new Error('Joining preview must not write');},openDialog:markup=>{content.innerHTML=markup;ConquerLocale.apply(content);if(!dialog.open)dialog.showModal();rallyDetailRenders++;},marchPanel:{open:(id,kind,options)=>{rallyJoinOptions={id,kind,...options,onRallyBack:typeof options.onRallyBack};}}});
  dialog.addEventListener('click',event=>{const button=event.target.closest('[data-action^="rally-"]');if(button)rallyDetailPanel.onClick(button.dataset.action,button);});
 });
 const detail=async()=>{
  const next=await page.evaluate(()=>{const next=rallyDetailRenders+1;rallyDetailPanel.onClick('rally-detail',{dataset:{id:'91'}});return next;});
  await page.waitForFunction(next=>rallyDetailRenders===next,next);
 };
 const join=async()=>{await page.evaluate(()=>{rallyJoinOptions=null;});await page.locator('#game-dialog [data-action="rally-join"]').click();await page.waitForFunction(()=>rallyJoinOptions!==null);return page.evaluate(()=>rallyJoinOptions);};
 const saved=await page.evaluate(()=>savedRallyRule);
 await detail();
 const mechanic=page.locator('#game-dialog .boss-mechanic');
 assert.equal(await mechanic.count(),1,'monster rally details render the saved boss skill');
 assert.equal(await mechanic.getAttribute('data-boss-mechanic'),saved.id);
 assert.equal(await mechanic.locator('[data-i18n="boss.frostgrimm.rule"]').textContent(),await page.evaluate(()=>ConquerLocale.t('boss.frostgrimm.rule',{effect:'17',threshold:'31'})),'details explain the frozen values, not current catalog defaults');
 const options=await join();
 assert.equal(options.kind,'rally-join');assert.equal(options.rally_target_kind,'monster');assert.equal(options.rally_id,91);assert.equal(options.id,8);assert.equal(options.onRallyBack,'function');
 assert.deepEqual(options.rally_boss_mechanic,saved,'join preflight receives the identical saved rule');
 assert.deepEqual(await page.evaluate(()=>savedRallyRule),saved,'display and joining do not mutate the frozen fixture rule');

 await page.evaluate(()=>{rallyDetailFixture.status='completed';delete rallyDetailFixture.result.monster.boss_mechanic;rallyDetailFixture.result.report={boss_mechanic:{...savedRallyRule,countered:true,active:false,counter_power_share_percent:42,required_power_before:1000,required_power_after:1000}};});
 await detail();
 assert.equal(await mechanic.count(),1,'historical monster rally details use the saved report mechanic');
 assert.equal(await mechanic.getAttribute('data-boss-state'),'countered');
 assert.match(await mechanic.locator('[data-i18n="boss.effects.required_power"]').textContent(),/1[.\s,]?000.*1[.\s,]?000/);

 await page.evaluate(()=>{rallyDetailFixture.status='gathering';delete rallyDetailFixture.result.report;});
 await detail();assert.equal(await mechanic.count(),0,'old monster rally without a saved mechanic remains unchanged');
 assert.equal((await join()).rally_boss_mechanic??null,null,'legacy join cannot acquire a skill from the current catalog');

 await page.evaluate(()=>{rallyDetailFixture.target_kind='city';rallyDetailFixture.result.monster.boss_mechanic=savedRallyRule;rallyDetailFixture.result.report={boss_mechanic:savedRallyRule};});
 await detail();assert.equal(await mechanic.count(),0,'non-monster rally ignores stray monster/report skill metadata');
 assert.equal((await join()).rally_boss_mechanic??null,null,'non-monster join carries no boss mechanic');
 assert.deepEqual(await page.evaluate(()=>rallyWriteCalls),[],'details and join selection do not submit a march');
 assert.deepEqual(await page.evaluate(()=>rallyFixtureErrors),[]);
 await page.evaluate(()=>document.querySelector('#game-dialog').close());
}
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://rally-hud.fixture/**',route=>{const url=new URL(route.request().url()),file=path.resolve(root,'.'+url.pathname);if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html});if(!file.startsWith(root+path.sep)||!fs.existsSync(file))return route.fulfill({status:404});return route.fulfill({path:file});});
  await page.goto('https://rally-hud.fixture/');
  await page.addScriptTag({content:require('./fixtures/isolated_locale.cjs')('de')});
  await page.evaluate(require('./fixtures/hud_navigation.cjs'));
  for(const name of ['game-overlay','world-march-hud'])await page.addScriptTag({content:fs.readFileSync(path.join(root,'assets/js',name+'.js'),'utf8')});
  await page.evaluate(()=>{
   document.body.classList.add('world-mode','playfield-mode');document.body.classList.remove('city-mode');
   window.fixtureNow=Date.parse('2030-01-01T12:00:00Z');
   window.fixtureState={player:{name:'Leader'},city:{player_id:7,power:1,world_id:1,coord_x:10,coord_y:11},lord:{level:1},vip:{building_slots:1},buildings:{},research_defs:[],build_queue:[],research_queue:[],marches:[{id:'rally:4',march_type:'rally',state:'gathering',target_x:25,target_y:26,origin_x:10,origin_y:11,departure_time:'2030-01-01 12:05:00',arrival_time:'2030-01-01 12:05:00',troops_json:'{"1":200}'}],army_limits:{march_slots:3,gather_march_slots:0},troop_defs:[]};
   window.fixtureKingdom={alliance:{id:2},profile:{display_name:'Leader',power:1,lord_level:1,lord_max_level:60,lord_xp_into:0,lord_xp_next:1,action_points:10,action_points_max:200,gems:0},vip:{building_slots:1}};
   window.fixtureRallies=[{id:9,world_id:1,result:{alliance_id:2},leader_player_id:8,status:'gathering',launch_at:'2030-01-01 12:03:00'}];
   const parse=s=>Date.parse(String(s).replace(' ','T').replace(/Z?$/,'Z')),context={base:'',getState:()=>fixtureState,getKingdom:()=>fixtureKingdom,getRallies:()=>fixtureRallies,now:()=>fixtureNow,date:parse,fmt:String,esc:String,openDialog:()=>{},countdown:String};
   window.fixtureOverlay=ConquerOverlay(context);fixtureOverlay.update();
   const host=document.querySelector('#content');host.replaceChildren();host.classList.add('atlas-shell','map-overlay-shell');
   window.rallyHud=ConquerMarchHud({host,getContext:()=>({...context,esc:String}),follow:()=>{},locate:()=>{},stop:()=>{},focus:()=>{},getSelected:()=>null});rallyHud.sync(fixtureState);
  });
  const alert=page.locator('#hud-alliance-rallies');assert(await alert.isVisible());assert.equal(await page.locator('#hud-rally-count').innerText(),'1');assert.equal(await page.locator('#hud-rally-status').innerText(),await page.evaluate(()=>ConquerLocale.t('rally.hud.start',{time:'3:00'})));assert.equal(await alert.getAttribute('aria-label'),await page.evaluate(()=>ConquerLocale.t('rally.hud.summary',{count:'1',gathering:'1',marching:'0'})));
  const march=page.locator('.world-march-row');assert.match(await march.innerText(),/Rally startet in/);assert.match(await march.innerText(),/00:05:00/);
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
   assert(await page.locator('#hud-marches').isHidden(),'Active march HUD replaces the duplicate troop shortcut');
   const placement=await page.evaluate(()=>{const hud=document.querySelector('.world-march-hud').getBoundingClientRect();return {inside:hud.left>=0&&hud.top>=0&&hud.right<=innerWidth+1&&hud.bottom<=innerHeight+1,buttons:[...document.querySelectorAll('.world-march-hud button')].filter(el=>el.checkVisibility({visibilityProperty:true})).map(el=>{const rect=el.getBoundingClientRect(),hit=document.elementFromPoint(rect.x+rect.width/2,rect.y+rect.height/2);return {text:el.textContent,width:rect.width,height:rect.height,hit:el===hit||el.contains(hit),interceptor:hit?.id||hit?.className,bounds:rect.toJSON()};})};});
   await page.screenshot({path:path.join(output,`${width}x${height}.png`)});
   assert(placement.inside,`${width}x${height}: rally HUD remains inside the viewport`);
   assert(placement.buttons.every(button=>button.width>=44&&button.height>=44&&button.hit),`${width}x${height}: rally controls are reachable 44px targets: ${JSON.stringify(placement.buttons)}`);
   const shortcut=await alert.evaluate(el=>{const box=el.getBoundingClientRect(),hit=document.elementFromPoint(box.x+box.width/2,box.y+box.height/2);return {inside:box.left>=0&&box.top>=0&&box.right<=innerWidth+1&&box.bottom<=innerHeight+1,touch:box.width>=44&&box.height>=44,hit:el===hit||el.contains(hit)};});
   assert(shortcut.inside&&shortcut.touch&&shortcut.hit,`${width}x${height}: alliance rally shortcut remains reachable: ${JSON.stringify(shortcut)}`);
  }
  await page.evaluate(()=>{fixtureRallies.push({id:10,leader_player_id:9,status:'marching',arrival_time:'2030-01-01 12:00:15'},{id:11,leader_player_id:7,status:'gathering',launch_at:'2030-01-01 12:01:00'},{id:12,leader_player_id:9,world_id:2,status:'gathering',launch_at:'2030-01-01 12:01:00'},{id:13,leader_player_id:9,result:{alliance_id:3},status:'gathering',launch_at:'2030-01-01 12:01:00'});fixtureOverlay.update();});
  assert.equal(await page.locator('#hud-rally-count').innerText(),'2','Own, other-world and other-alliance rallies are excluded');
  assert.equal(await page.locator('#hud-rally-status').innerText(),await page.evaluate(()=>ConquerLocale.t('rally.hud.start',{time:'3:00'})),'Gathering countdown takes priority over marching arrivals');
  await page.evaluate(()=>{fixtureRallies=fixtureRallies.filter(row=>row.id===10);fixtureRallies[0].arrival_time=null;fixtureOverlay.update();});
  assert.equal(await page.locator('#hud-rally-status').innerText(),await page.evaluate(()=>ConquerLocale.t('rally.hud.pending')),'Missing countdown never displays NaN');
  await page.evaluate(()=>{fixtureRallies=[];fixtureOverlay.update();});assert(await alert.isHidden());assert.equal(await page.locator('#hud-rally-count').textContent(),'0');
  await page.evaluate(()=>{fixtureRallies=[{id:14,leader_player_id:8,status:'gathering',launch_at:'2030-01-01 12:02:00'}];fixtureOverlay.update();});assert(await alert.isVisible(),'A new allied rally restores the shortcut');
  await page.evaluate(()=>{fixtureState.marches[0].state='marching';fixtureState.marches[0].arrival_time='2030-01-01 12:09:00';rallyHud.sync(fixtureState)});assert.match(await march.innerText(),/Rally kommt an in/);assert.match(await march.innerText(),/00:09:00/);
  await assertRallySkillSnapshots(page);
  assert.deepEqual(errors,[]);console.log('PASS rally leader start/arrival countdown, alliance alert, HUD placement, saved boss skills in details/join, historical reports and legacy/non-monster exclusion');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
