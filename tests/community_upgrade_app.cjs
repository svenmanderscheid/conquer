'use strict';
// Starts and removes an isolated preview; all mutations are synthetic fixture actions.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=process.env.COMMUNITY_UPGRADE_OUTPUT||path.join(root,'artifacts/ui-ux-implementation-2026-09-30/community-flows');fs.mkdirSync(out,{recursive:true});
const pause=ms=>new Promise(r=>setTimeout(r,ms));
const localInput=ms=>{const d=new Date(ms);return new Date(d.getTime()-d.getTimezoneOffset()*60000).toISOString().slice(0,16);};
async function freePort(){const s=net.createServer();await new Promise(r=>s.listen(0,'127.0.0.1',r));const p=s.address().port;await new Promise(r=>s.close(r));return p;}
(async()=>{
 const port=await freePort(),base='http://127.0.0.1:'+port;
 const preview=spawn(process.env.PHP_BINARY||'php',['tools/preview-feature-fixture.php','--port='+port,'--chat','--hud','--appearance'],{cwd:root,windowsHide:true,stdio:['pipe','pipe','pipe']});
 let log='';preview.stdout.on('data',c=>log+=c);preview.stderr.on('data',c=>log+=c);let browser,page;const diagnostics=[];
 try{
  for(let i=0;i<120&&!log.includes('Synthetic preview ready');i++){if(preview.exitCode!==null)throw new Error(log);await pause(250);}assert.match(log,/Synthetic preview ready/);
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,locale:'en-US',timezoneId:'Europe/Luxembourg'});page.setDefaultTimeout(25000);page.setDefaultNavigationTimeout(60000);
  const errors=[],badResponses=[];page.on('pageerror',e=>{errors.push(e.message);diagnostics.push(e.message);});page.on('requestfailed',r=>diagnostics.push(r.url()+': '+r.failure()?.errorText));page.on('response',async r=>{if(r.url().includes('/api/')&&r.status()>=500){badResponses.push(r.status()+' '+r.url());diagnostics.push(r.status()+' '+r.url()+' '+(await r.text().catch(()=>'')).slice(0,1000));}});
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});await page.locator('[name="identifier"], [name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city',{waitUntil:'domcontentloaded'}),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);await page.locator('.painted-village').waitFor();
  await page.goto(base+'/city#community',{waitUntil:'domcontentloaded'});await page.locator('.social-summary').waitFor();
  assert.equal((await page.request.post(base+'/api/community/alliance-action',{data:{action:'notice.create'}})).status(),403,'HTTP mutation requires CSRF token');
  assert.equal((await page.request.get(base+'/api/community/alliance?world_id=2')).status(),409,'HTTP state rejects cross-world request');
  assert.match(await page.locator('.social-hub').innerText(),/Overview/);
  async function socialTab(id){await page.locator('.social-tabs [data-id="'+id+'"]').click();await page.waitForTimeout(120);}
  async function allianceTab(id){await page.locator('.alliance-social-tabs [data-id="'+id+'"]').click();await page.waitForTimeout(100);}
  async function communityState(){const response=await page.request.get(base+'/api/community/state?world_id=1');assert.equal(response.status(),200);return (await response.json()).data;}
  function actionResponse(action){return page.waitForResponse(r=>{if(!r.url().includes('/api/community/action')||r.request().method()!=='POST')return false;try{return r.request().postDataJSON()?.action===action;}catch{return false;}});}
  async function geometry(label,selector){
   const issues=await page.evaluate(selector=>{
    const problems=[],shell=document.querySelector(selector),panel=document.querySelector('#panel-dialog');
    if(!shell)return ['missing shell'];
    if(panel.scrollWidth>panel.clientWidth+2)problems.push('horizontal dialog overflow');
    if(shell.scrollWidth>shell.clientWidth+2)problems.push('horizontal shell overflow');
    for(const el of document.querySelectorAll(selector+' nav button,#panel-dialog .panel-close')){
     const r=el.getBoundingClientRect();if(!r.width||!r.height)continue;
     if(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1)problems.push('unreachable navigation: '+el.textContent);
    }
    if(shell.innerText.includes('alliance_community.')||shell.innerText.includes('social.'))problems.push('untranslated catalog key');
    return problems;
   },selector);assert.deepEqual(issues,[],label);
  }
  // Check the actual page navigation, shared frame, touch sizes and language at every size.
  for(const [w,h]of[[1280,800],[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width:w,height:h});
   for(const id of['overview','conversations','friends','news','settings']){await socialTab(id);await geometry(w+'x'+h+' social '+id,'.social-hub');}
   await page.screenshot({path:path.join(out,'social-'+w+'x'+h+'.png')});
  }
  console.log('PASS 20 responsive main-app community views.');
  await page.setViewportSize({width:1280,height:800});await socialTab('settings');
  await page.locator('[data-form="social-settings"] select[name="private_messages"]').selectOption('friends');
  await page.locator('[data-form="social-settings"] select[name="world"]').selectOption('mentions');
  const preference=page.waitForResponse(r=>r.url().includes('/community/social-action')&&r.request().method()==='POST');await page.locator('[data-form="social-settings"] button').click();assert.equal((await preference).status(),200);
  await page.reload();await page.locator('.social-summary').waitFor();await socialTab('settings');assert.equal(await page.locator('[name="private_messages"]').inputValue(),'friends');assert.equal(await page.locator('[name="world"]').inputValue(),'mentions');
  await socialTab('friends');await page.locator('[data-form="social-search"] input').fill('Elara');const searchResponse=page.waitForResponse(r=>r.url().includes('/community/social?')&&r.url().includes('query=Elara'));await page.locator('[data-form="social-search"] button').click();assert.equal((await searchResponse).status(),200);
  // A city is supplied by the community-enabled fixture; contacts must remain searchable.
  const contact=page.locator('.social-contact').filter({hasText:'Elara'}).first();
  await contact.waitFor();
  assert.equal(await contact.count(),1,'same-world player search finds fixture contact');
  {
   const request=page.waitForResponse(r=>r.url().includes('/community/social-action')&&r.request().method()==='POST');await contact.locator('[data-action="social-request"]').click();assert.equal((await request).status(),200);await page.waitForTimeout(150);
   assert.ok((await page.locator('.social-hub').innerText()).includes('pending'));
  }
  await page.locator('.social-contact').filter({hasText:'Elara'}).first().locator('[data-action="social-message"]').click();
  await page.locator('.world-chat-window:not([hidden])').waitFor();await page.locator('#world-chat-message').fill('QA persistent conversation <script>test</script>');
  let chatResponse=actionResponse('chat.send');await page.locator('.world-chat-compose button[type="submit"]').click();assert.equal((await chatResponse).status(),200);
  await page.locator('.world-chat-bubble p').filter({hasText:'QA persistent conversation'}).waitFor();assert.equal(await page.locator('.world-chat-bubble script').count(),0);
  await page.locator('[data-chat-channel="world"]').click();await page.locator('.world-chat-message [data-chat-menu]').first().waitFor();await page.locator('.world-chat-message [data-chat-menu]').first().click();
  await page.locator('[data-chat-report-open]').click();await page.locator('[data-chat-report-details]').fill('Synthetic QA report, isolated database.');
  chatResponse=page.waitForResponse(r=>r.url().includes('/community/social-action')&&r.request().method()==='POST');await page.locator('[data-chat-report-send]').click();assert.equal((await chatResponse).status(),200);
  await page.locator('.world-chat-avatar-button[data-chat-profile="2"]').first().click();await page.locator('#game-dialog[open]').waitFor();assert.match(await page.locator('#game-dialog').innerText(),/Elara/);
  await page.goto(base+'/city#community',{waitUntil:'domcontentloaded'});await page.reload({waitUntil:'domcontentloaded'});await page.locator('.social-summary').waitFor();await socialTab('conversations');assert.match(await page.locator('.social-list').innerText(),/QA persistent conversation/);
  console.log('PASS persisted private conversation, message report and profile navigation through actual chat.');
  await socialTab('overview');await page.locator('[data-action="social-alliance"]').click();await page.locator('.alliance-social-card h3').first().waitFor();
  for(const [w,h]of[[1280,800],[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width:w,height:h});
   for(const id of['overview','find','calendar','polls','manage']){await allianceTab(id);await geometry(w+'x'+h+' alliance '+id,'.alliance-community-shell');}
   await page.screenshot({path:path.join(out,'alliance-'+w+'x'+h+'.png')});
  }
  console.log('PASS 20 responsive main-app alliance views.');
  await page.setViewportSize({width:390,height:844});await allianceTab('overview');
  await page.locator('.alliance-social-create summary').click();const nf=page.locator('[data-form="alliance-social-notice"]');await nf.locator('[name="kind"]').selectOption('goal');await nf.locator('[name="title"]').fill('QA shared goal <img src=x>');await nf.locator('[name="body"]').fill('Together <script>window.communityXss=1</script>');
  let response=page.waitForResponse(r=>r.url().includes('/community/alliance-action')&&r.request().method()==='POST');await nf.locator('button[type="submit"]').click();assert.equal((await response).status(),200);await page.getByRole('heading',{name:'QA shared goal <img src=x>'}).waitFor();assert.equal(await page.evaluate(()=>window.communityXss),undefined);assert.equal(await page.locator('.alliance-social-copy script,.alliance-social-card h4 img').count(),0);
  await allianceTab('calendar');await page.locator('.alliance-social-create summary').click();const ef=page.locator('[data-form="alliance-social-event"]');await ef.locator('[name="title"]').fill('QA boss hunt');await ef.locator('[name="description"]').fill('Gather for a shared activity.');await ef.locator('[name="starts_at"]').fill(localInput(Date.now()+2*3600000));
  response=page.waitForResponse(r=>r.url().includes('/community/alliance-action')&&r.request().method()==='POST');await ef.locator('button[type="submit"]').click();assert.equal((await response).status(),200);await page.getByRole('heading',{name:'QA boss hunt'}).waitFor();
  response=page.waitForResponse(r=>r.url().includes('/community/alliance-action')&&r.request().method()==='POST');await page.locator('[data-action="alliance-social-rsvp"][data-value="yes"]').click();assert.equal((await response).status(),200);await page.waitForTimeout(150);await allianceTab('overview');await page.locator('.alliance-social-reminder').waitFor();assert.match(await page.locator('.alliance-social-reminder').innerText(),/QA boss hunt/);
  await allianceTab('polls');await page.locator('.alliance-social-create summary').first().click();const pf=page.locator('[data-form="alliance-social-poll"]');await pf.locator('[name="question"]').fill('QA next target?');await pf.locator('[name="options"]').fill('Forest\nFrost');
  response=page.waitForResponse(r=>r.url().includes('/community/alliance-action')&&r.request().method()==='POST');await pf.locator('button[type="submit"]').click();assert.equal((await response).status(),200);await page.getByRole('heading',{name:'QA next target?'}).waitFor();
  response=page.waitForResponse(r=>r.url().includes('/community/alliance-action')&&r.request().method()==='POST');await page.locator('[data-action="alliance-social-vote"][data-value="1"]').click();assert.equal((await response).status(),200);await page.waitForTimeout(180);assert.equal(await page.locator('[data-action="alliance-social-vote"][data-value="1"]').getAttribute('aria-pressed'),'true');
  await page.locator('.alliance-social-create summary').last().click();const tf=page.locator('[data-form="alliance-social-time-poll"]');await tf.locator('[name="question"]').fill('QA best time?');
  response=page.waitForResponse(r=>r.url().includes('/community/alliance-action')&&r.request().method()==='POST');await tf.locator('button[type="submit"]').click();assert.equal((await response).status(),200);await page.getByRole('heading',{name:'QA best time?'}).waitFor();
  await allianceTab('manage');const rf=page.locator('[data-form="alliance-social-recruitment"]');await rf.locator('[name="mode"]').selectOption('application');await rf.locator('[name="timezone"]').fill('Europe/Luxembourg');
  response=page.waitForResponse(r=>r.url().includes('/community/alliance-action')&&r.request().method()==='POST');await rf.locator('button[type="submit"]').click();assert.equal((await response).status(),200);await page.waitForTimeout(180);assert.equal(await page.locator('[name="mode"]').inputValue(),'application');
  await page.reload();await page.locator('.alliance-social-card h3').first().waitFor();await allianceTab('calendar');assert.match(await page.locator('.alliance-social-body').innerText(),/QA boss hunt/);await page.screenshot({path:path.join(out,'calendar-390x844.png')});
  await allianceTab('polls');assert.match(await page.locator('.alliance-social-body').innerText(),/QA next target/);await page.screenshot({path:path.join(out,'polls-390x844.png')});

  // The third route must be reached through the actual alliance-planning shortcut.
  // Only this process's disposable --chat/--hud fixture receives these mutations.
  await allianceTab('overview');await page.locator('[data-action="community-open"][data-id="alliance"]').click();
  await page.locator('.community-shell form[data-form="community-chat"]').waitFor();
  assert.equal(new URL(page.url()).hash,'#alliance-tools');
  assert.equal(await page.locator('form[data-form="community-chat"] [name="channel"]').inputValue(),'alliance');
  const before=await communityState();assert.equal(Number(before.player_id),1);assert.equal(Number(before.world_id),1);assert.equal(before.alliance.name,'Die Morgenwacht');
  const queue=before.queues.find(q=>q.type==='building');assert.ok(queue,'--hud supplies an active synthetic building queue');
  await page.locator('[data-action="community-tab"][data-id="help"]').click();
  let toolsResponse=actionResponse('help.request');await page.locator('[data-action="community-help-request"][data-type="building"][data-id="'+queue.id+'"]').click();
  const helpResponse=await toolsResponse;assert.equal(helpResponse.status(),200);const helpPayload=await helpResponse.json(),helpId=Number(helpPayload.data.result.id);assert.ok(helpId>0);
  const helped=await communityState(),request=helped.help_requests.find(h=>Number(h.id)===helpId);assert.ok(request,'help request is persisted by the server');assert.equal(Number(request.queue_id),Number(queue.id));assert.equal(Number(request.player_id),1);assert.equal(Number(request.help_count),0,'requesting help does not grant help');
  // Re-enter through a page reload before donating, so local optimistic state is insufficient.
  await page.reload({waitUntil:'domcontentloaded'});await page.locator('.community-shell form[data-form="community-chat"]').waitFor();
  await page.locator('[data-action="community-tab"][data-id="help"]').click();await page.locator('[data-action="community-help-request"]').first().waitFor();
  const persisted=await communityState();assert.equal(persisted.help_requests.filter(h=>Number(h.id)===helpId).length,1);
  await page.locator('[data-action="community-tab"][data-id="research"]').click();const donation=page.locator('form[data-form="community-donate"]');await donation.waitFor();
  const treasuryBefore=Number(persisted.treasury.gold),amount=250;
  await donation.locator('[name="resource"]').selectOption('gold');await donation.locator('[name="amount"]').fill(String(amount));
  toolsResponse=actionResponse('treasury.donate');await donation.locator('button[type="submit"]').click();assert.equal((await toolsResponse).status(),200);
  const donated=await communityState();assert.equal(Number(donated.treasury.gold),treasuryBefore+amount,'one donation credits exactly its submitted amount');
  assert.equal(donated.help_requests.filter(h=>Number(h.id)===helpId).length,1,'donation does not duplicate the previous help request');
  await page.reload({waitUntil:'domcontentloaded'});await page.locator('.community-shell form[data-form="community-chat"]').waitFor();
  assert.equal(Number((await communityState()).treasury.gold),treasuryBefore+amount,'treasury credit survives a full app reload');
  for(const [width,height]of [[1280,800],[320,568],[568,320]]){
   await page.setViewportSize({width,height});
   for(const tab of ['alliance','help','research']){
    const tabButton=page.locator('[data-action="community-tab"][data-id="'+tab+'"]');await tabButton.click();assert.equal(await tabButton.getAttribute('aria-pressed'),'true');
    assert.equal(await page.locator('.community-shell').evaluate(el=>el.scrollWidth<=el.clientWidth+2),true,`alliance-tools ${tab} has no horizontal overflow at ${width}x${height}`);
   }
   await page.screenshot({path:path.join(out,`alliance-tools-${width}x${height}.png`)});
  }
  await page.goto(base+'/city#alliance-community',{waitUntil:'domcontentloaded'});await page.locator('[data-action="community-open"][data-id="help"]').waitFor();
  await page.locator('[data-action="community-open"][data-id="help"]').click();await page.locator('.community-shell [data-action="community-tab"][data-id="help"][aria-pressed="true"]').waitFor();
  fs.writeFileSync(path.join(out,'tools-state.json'),JSON.stringify({worldId:1,playerId:1,queueId:Number(queue.id),helpId,helpCount:Number(request.help_count),donation:{resource:'gold',amount,before:treasuryBefore,after:Number(donated.treasury.gold)},helpGivenByAnotherPlayer:false},null,2));
  assert.deepEqual(errors,[]);assert.deepEqual(badResponses,[]);console.log('PASS main-app community and alliance planning: 49 responsive views; persisted preferences, recruitment, goals, event RSVP/reminders, poll voting, escaped player text, real alliance-tools shortcuts, persisted help request and exact treasury donation.');
 }catch(e){if(page){await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});fs.writeFileSync(path.join(out,'failure.html'),await page.content().catch(()=>''));}console.error(diagnostics);fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify(diagnostics,null,2));throw e;
 }finally{if(browser)await browser.close();preview.stdin.write('\n');await Promise.race([new Promise(r=>preview.once('exit',r)),pause(15000)]);if(preview.exitCode===null)preview.kill();fs.writeFileSync(path.join(out,'preview.log'),log);}
})().catch(e=>{console.error(e);process.exitCode=1;});
