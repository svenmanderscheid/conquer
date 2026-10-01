'use strict';
// Isolated DOM/CSS and action-mock regression. No live API, account or database calls.
// Run: node tests/battle_preview_ui.cjs. Install Playwright + Chromium, or set
// PLAYWRIGHT_MODULE to its module path and PLAYWRIGHT_CHANNEL=chrome for system Chrome.
const fs=require('fs'),path=require('path'),assert=require('assert'),os=require('os');
const {pathToFileURL}=require('url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-battle-preview-'));
const assetBase=pathToFileURL(root).href.replace(/\/$/,'');
const names=[...fs.readFileSync(root+'/views/game.php','utf8').matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(m=>m[1]);
const styles=names.map(n=>fs.readFileSync(root+'/assets/css/'+n+'.css','utf8')).join('\n').replaceAll('../fonts/',assetBase+'/assets/fonts/');
const catalogs=Object.fromEntries(['en','de','fr'].map(locale=>[locale,JSON.parse(fs.readFileSync(root+'/data/i18n/'+locale+'.json','utf8'))]));
for(const locale of ['en','de','fr'])assert(catalogs[locale]['battle.preview.title'],'battle calculator catalog missing: '+locale);
const js='localStorage.clear();window.CONQUER_I18N='+JSON.stringify({locale:'de',catalogs}).replaceAll('<','\\u003c')+';\n'+['localization','mobile-pages','battle-preview','castle-skins','reward-dialog','march-panel'].map(name=>fs.readFileSync(root+'/assets/js/'+name+'.js','utf8')).join('\n');
const defs=[];for(let type=1;type<=3;type++)for(let tier=1;tier<=5;tier++)defs.push({code:50100000+type*100+tier,type,tier,attack:10*tier,gather_carry:10,speed:10,march_speed:11,monster_march_speed:10+type*10,monster_rally_speed:15+type*10,charm_march_speed:30,pvp_march_speed:40,pvp_rally_speed:50,reinforce_march_speed:55,shrine_neutral_speed:60,shrine_occupied_speed:70,gather_speed:80,field_attack_speed:90,monster_power:10*tier,monster_power_single_type:12*tier,monster_rally_power:11*tier,monster_rally_power_single_type:13*tier});
fs.writeFileSync(out+'/fixture.html',`<!doctype html><html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${styles}</style><body class="mobile-game"><dialog id="panel-dialog"><div class="page-heading"></div></dialog><dialog id="game-dialog"><button class="dialog-close" aria-label="Schließen">×</button><div id="dialog-content"></div></dialog><script>${js}</script><script>const defs=${JSON.stringify(defs)};window.state={troop_defs:defs,troops:Object.fromEntries(defs.map(t=>[t.code,5000])),army_limits:{march_capacity:50000,march_slots:3},city:{coord_x:20,coord_y:20,action_points:200},marches:[],players:[],monsters:[{id:99,coord_x:170,coord_y:260,hp_current:1000,required_power:121,definition:{name:'Frostgrimm',art:'monsters/frostgrimm',type:'rally',level:1,action_point_cost:25,stats:{hp:1000,attack:10,defense:5},drops:[{label:'5.000 Gold',count:5},{label:'Ausbildung 30 Minuten',count:1},{label:'Heilung 30 Minuten',count:1},{label:'100.000 Nahrung',count:1},{label:'Goldtruhe',count:1}],gems_drop:{amount:50}}}],nodes:[{id:99,coord_x:170,coord_y:260,object_type:1,resource_amount:100000,gather_rate:10,can_attack:true,gatherer_march_id:null}]};window.mobile=ConquerMobilePages({navigate(){},getRoute:()=> 'world',getPlayfield:()=> 'world',closeChat(){}});document.querySelector('#game-dialog').addEventListener('close',()=>{if(!document.querySelector('#game-dialog').open)mobile.closed('dialog')});window.sent=[];window.notices=[];window.previews=[];const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));window.march=ConquerMarch({base:${JSON.stringify(assetBase)},esc,fmt:n=>Math.floor(Number(n)||0).toLocaleString('de-DE'),unitName:t=>['Infanterie','Bogenschützen','Kavallerie'][t.type-1]+' '+['','I','II','III','IV','V'][t.tier],getState:()=>state,toast:m=>notices.push(m),api:async(path,payload)=>{previews.push({path,payload});return{outcome:'attacker_wins',army_power:130,required_power:121,monster_hp_after:0,attacker:{survivors:2,wounded:0,dead:0},luck_percent:0,luck_range:[-10,10],assumptions:'Referenz ohne weitere Mitspieler',notice:'Referenzrechnung mit 0 % Kampfglück. Echter Kampf: −10 % bis +10 %.',calculated_at:1700000000}},action:async(path,payload,message)=>{sent.push({path,payload,message});return{}},openDialog:html=>{const d=document.querySelector('#game-dialog');delete d.dataset.march;d.classList.remove('march-dialog');document.querySelector('#dialog-content').innerHTML=html;mobile.syncDialog();if(!d.open){d.showModal();mobile.opened('dialog');}d.scrollTop=0;}});document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)march.onClick(b.dataset.action,b)});window.openMarch=kind=>{localStorage.clear();sent=[];state.congress={id:99,coord_x:170,coord_y:260,name:"Kongress",can_attack:kind==="congress",can_garrison:kind==="congress-garrison",garrison_total:1500000};state.shrines=[{...state.congress,element:"forest",name:"Schrein des Lebens",can_attack:kind==="shrine",can_garrison:kind==="shrine-garrison",event:{active:true,starts_at:"2020-01-01 00:00:00",ends_at:"2099-01-01 00:00:00"}}];march.open(99,kind,{rally_id:421,target:{id:99,coord_x:170,coord_y:260,display_name:'Der lange Name des Königreichs',castle_level:8}})};</script></body></html>`);
(async()=>{const browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})}),page=await browser.newPage({viewport:{width:568,height:320}});const errors=[];page.on('pageerror',error=>errors.push(error.message));try{
 await page.goto(pathToFileURL(path.join(out,'fixture.html')).href);await page.evaluate(()=>openMarch('monster-rally'));
 for(const viewport of [{width:1280,height:720},{width:390,height:844},{width:320,height:568},{width:844,height:390}]){
  await page.setViewportSize(viewport);await page.evaluate(()=>openMarch('monster-rally'));
  const button=page.getByRole('button',{name:'Kampfrechner mit 0 Prozent Kampfglück öffnen'});
  assert.equal(await button.count(),1,'the real March factory must expose its calculator');await button.click();
  await page.locator('.battle-preview-result table').waitFor();
  assert.match(await page.locator('.battle-preview-result').innerText(),/Sieg bei 0 % Kampfglück/);
  assert((await page.locator('.battle-preview-result').innerText()).includes(catalogs.de['battle.preview.notice_monster']),'the actual localized luck-range notice is shown');
  const metrics=await page.locator('.battle-preview-dialog').evaluate(el=>{const r=el.getBoundingClientRect(),b=el.querySelector('[data-preview-close]').getBoundingClientRect();return{fits:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:el.scrollWidth-el.clientWidth,close:b.height>=44&&b.top>=0&&b.bottom<=innerHeight}});
  assert(metrics.fits&&metrics.overflow<=1&&metrics.close,JSON.stringify({viewport,metrics}));
  const before=await page.evaluate(()=>previews.length);await page.getByRole('button',{name:'Neu berechnen'}).click();await page.waitForFunction(n=>previews.length>n,before);
  await page.screenshot({path:path.join(out,`preview-${viewport.width}x${viewport.height}.png`)});
  if(viewport.width===390)await page.goBack();else await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerBattlePreview);
  assert.equal(await page.locator('#game-dialog').getAttribute('open'),'');assert.equal(await page.evaluate(()=>sent.length),0,'preview must never dispatch a march');
  console.log(`PASS calculator integrated, labeled and reachable at ${viewport.width}x${viewport.height}`);
 }
 const calls=await page.evaluate(()=>previews);assert(calls.length>=8);assert(calls.every(call=>call.path==='march/preview'&&call.payload.target_id===99));
 for(const locale of ['en','fr']){
  await page.setViewportSize({width:390,height:844});await page.evaluate(value=>{ConquerLocale.setLocale(value);openMarch('monster-rally');},locale);
  await page.locator('[data-action=march-preview]').click();await page.locator('.battle-preview-result table').waitFor();
  assert.equal(await page.locator('#battle-preview-title').innerText(),catalogs[locale]['battle.preview.title']);
  assert.equal(await page.locator('.battle-preview-result th').nth(3).innerText(),catalogs[locale]['battle.preview.fallen']);
  assert.doesNotMatch(await page.locator('.battle-preview-dialog').innerText(),/Kampfrechner|Kampfglück|Truppen|Liked|battle\.preview\./);
  assert.deepEqual(await page.locator('.battle-preview-result td').allTextContents(),['2','0','0'],'numeric results stay untouched');
  await page.screenshot({path:path.join(out,`preview-${locale}-390x844.png`)});await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerBattlePreview);
  // Exercise editable PvP labels and the result branch with deliberately opaque API prose.
  await page.evaluate(()=>ConquerBattlePreview({esc,fmt:n=>String(n),getState:()=>state,api:async(path,payload)=>{previews.push({path,payload});return {outcome:'defender_wins',attacker_score:130,defender_score:200,attacker:{survivors:12,wounded:3,dead:1},defender:{survivors:20,wounded:2,dead:0},assumptions:'Untranslated API assumptions',notice:'Untranslated API notice',calculated_at:1700000000}}}).open({kind:'players',target:{id:99,coord_x:170,coord_y:260,display_name:'Kampfglück <Grumwald>'},troops:{[defs[0].code]:16}}));
  const tier=page.locator('[data-preview-type="0"]');assert.equal(await tier.getAttribute('aria-label'),catalogs[locale]['battle.preview.troop_tier'].replace('{type}',catalogs[locale]['battle.preview.infantry']));
  await page.locator('#preview-count-0').fill('25');assert.equal(await page.locator('.battle-preview-result').innerText(),catalogs[locale]['battle.preview.changed']);
  await page.locator('.battle-preview-dialog button[type=submit]').click();await page.locator('.battle-preview-result table').waitFor();
  assert.equal(await page.locator('.battle-preview-result h3').innerText(),catalogs[locale]['battle.preview.example_loss']);
  assert.deepEqual(await page.locator('.battle-preview-result td').allTextContents(),['12','3','1','20','2','0']);
  assert.doesNotMatch(await page.locator('.battle-preview-dialog').innerText(),/Untranslated API|Kampfrechner|Kampfglück|Truppen|Liked|battle\.preview\./);
  assert.equal(await page.evaluate(()=>previews.at(-1).payload.defender_troops[defs[0].code]),25,'translated form keeps its request values');
  await page.keyboard.press('Escape');await page.waitForFunction(()=>!history.state?.conquerBattlePreview);
  assert.equal(await page.evaluate(()=>sent.length),0);console.log('PASS explicit monster and PvP calculator copy in '+locale);
 }
 assert.deepEqual(errors,[]);console.log('Fixture/screenshots: '+out);
 }finally{await browser.close()}})().catch(error=>{console.error(error);process.exitCode=1});
