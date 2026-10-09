'use strict';
// Actual /city profile UI against a disposable database. Invitation writes are intercepted;
// permission and persistence rules are exercised separately by alliance_invitations.php.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.resolve(root,process.env.ALLIANCE_PROFILE_INVITE_OUTPUT||'output/playwright/alliance-profile-invite');fs.mkdirSync(out,{recursive:true});
const sizes=process.env.ALLIANCE_PROFILE_INVITE_SIZES?process.env.ALLIANCE_PROFILE_INVITE_SIZES.split(',').map(size=>size.split('x').map(Number)):[[1280,800],[390,844],[320,568],[844,390],[568,320]];
async function freePort(){const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const port=server.address().port;await new Promise(resolve=>server.close(resolve));return port;}
(async()=>{
 const port=await freePort(),base='http://127.0.0.1:'+port,fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--chat','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,page,log='',visit=0,viewerRole='leader',targetAllied=false,onAction=null;
 const errors=[],badResponses=[],writes=[],unexpectedWrites=[],failedRequests=[],checks=[],receipts=new Set();
 fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',error=>{clearTimeout(timer);clearInterval(poll);reject(error);});});
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  page.on('pageerror',error=>errors.push(error.message));page.on('response',response=>{if(response.status()>=400)badResponses.push(response.status()+' '+response.url());});page.on('requestfailed',request=>failedRequests.push({url:request.url(),reason:request.failure()?.errorText}));
  page.on('request',request=>{if(['POST','PUT','PATCH','DELETE'].includes(request.method())&&request.url().includes('/api/')){const entry={url:request.url(),body:request.postDataJSON()};writes.push(entry);if(!request.url().endsWith('/api/community/alliance-action'))unexpectedWrites.push(entry);}});
  await page.route('**/api/kingdom/state*',async route=>{
   const response=await route.fetch(),json=await response.json(),target=new URL(route.request().url()).searchParams.get('player_id');
   if(target)json.data.profile.alliance=targetAllied?{id:1,name:'Morning Watch',tag:'MW',role:'member'}:null;
   else if(viewerRole){json.data.alliance.role=viewerRole;json.data.alliance.role_level={leader:5,vice_leader:4,officer:3,member:1}[viewerRole];if(json.data.profile.alliance)json.data.profile.alliance.role=viewerRole;}
   else{json.data.alliance=null;json.data.profile.alliance=null;}
   await route.fulfill({response,json});
  });
  await page.route('**/api/community/alliance-action',async route=>{
   const body=route.request().postDataJSON();assert.equal(body.action,'invitation.send');assert.equal(Number(body.player_id),2);assert.equal(Number(body.world_id),1);assert.match(body.request_id,/^[a-zA-Z0-9_-]{16,80}$/);receipts.add(body.request_id);
   const signal=onAction;onAction=null;assert(signal,'An invitation requires an explicit test click');
   const outcome=await new Promise(resolve=>signal({body,release:resolve}));
   if(outcome==='lost')await route.abort('failed');else await route.fulfill({json:{ok:true,data:{message:'Saved.',result:{invitation_id:91,alliance_id:1}}}});
  });
  await page.goto(base+'/?zugang=login');await page.locator('[name="identifier"], [name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);await page.locator('.painted-village').waitFor();assert.equal(await page.evaluate(()=>ConquerLocale.locale),'en');
  const invite=()=>page.locator('#game-dialog .lok-profile [data-action="alliance-profile-invite"][data-id="2"]');
  async function openProfile(role='leader',allied=false,id=2){viewerRole=role;targetAllied=allied;const before=writes.length;await page.goto(base+'/city?profile_invite_qa='+ ++visit+'#city',{waitUntil:'domcontentloaded'});await page.locator('.painted-village').waitFor();await page.evaluate(()=>location.hash='rankings');await page.locator('.ranking-table').waitFor();await page.locator('.ranking-table [data-action="public-profile"][data-id="'+id+'"]').click();await page.locator('#game-dialog .lok-profile').waitFor();assert.equal(writes.length,before,'Viewing another profile never sends an invitation');}
  async function fit(label){await page.evaluate(()=>document.fonts.ready);const metrics=await page.locator('#game-dialog').evaluate(dialog=>{const r=dialog.getBoundingClientRect(),profile=dialog.querySelector('.lok-profile');return{fits:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,sideways:profile.scrollWidth>profile.clientWidth+2,documentOverflow:document.documentElement.scrollWidth>innerWidth+1,untranslated:/\b(?:profile|alliance_community)\.[a-z_]+/.test(profile.innerText),brokenImages:[...profile.querySelectorAll('img')].some(img=>img.complete&&!img.naturalWidth)};});assert.deepEqual(metrics,{fits:true,sideways:false,documentOverflow:false,untranslated:false,brokenImages:false},label);}
  async function touch(){await invite().scrollIntoViewIfNeeded();const metrics=await invite().evaluate(button=>{const r=button.getBoundingClientRect();return{height:r.height,visible:r.left>=0&&r.top>=0&&r.right<=innerWidth&&r.bottom<=innerHeight,reachable:button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)),profileContact:button.classList.contains('profile-contact')};});assert(metrics.height>=43&&metrics.visible&&metrics.reachable&&metrics.profileContact,'Profile invitation stays touch-sized and reachable: '+JSON.stringify(metrics));}
  async function send(outcome='ok'){
   const before=writes.length,seen=new Promise(resolve=>onAction=resolve);await invite().click();const pending=await seen;assert.equal(await invite().isDisabled(),true,'Invitation button is disabled while sending');await invite().evaluate(button=>{button.click();button.click();});assert.equal(writes.length,before+1,'Repeated taps while sending issue one action');assert.equal(await page.locator('#game-dialog .lok-profile.is-public').isVisible(),true,'Sending keeps the target profile open');pending.release(outcome);
   if(outcome==='lost')await page.waitForFunction(()=>!document.querySelector('#game-dialog [data-action="alliance-profile-invite"]')?.disabled);else{await page.waitForFunction(()=>document.querySelector('#game-dialog [data-action="alliance-profile-invite"]')?.textContent.includes('Invitation sent'));assert.equal(await invite().isDisabled(),true,'Confirmed invitation cannot be sent again');}
   return pending.body;
  }
  for(const [index,[width,height]]of sizes.entries()){
   await page.setViewportSize({width,height});const role=index%2?'vice_leader':'leader';await openProfile(role);await invite().waitFor();await fit(width+'x'+height+' profile');await touch();assert.equal(await invite().isEnabled(),true);await page.screenshot({path:path.join(out,'profile-'+width+'x'+height+'.png')});await send();await fit(width+'x'+height+' sent');await page.screenshot({path:path.join(out,'sent-'+width+'x'+height+'.png')});checks.push({width,height,role,profileOpen:true,directInvite:true,pendingDisabled:true,sentDisabled:true});console.log('PASS direct profile invitation '+width+'x'+height);
  }
  await page.setViewportSize({width:390,height:844});
  for(const [role,allied,id,label]of [['member',false,2,'R1'],['officer',false,2,'R3'],[null,false,2,'No alliance'],['leader',true,2,'Already allied'],['leader',false,1,'Own public profile']]){await openProfile(role,allied,id);assert.equal(await page.locator('#game-dialog [data-action="alliance-profile-invite"]').count(),0,label+' cannot invite from this profile');checks.push({permission:label,inviteHidden:true});}
  await openProfile('leader');const before=writes.length,count=receipts.size,lost=await send('lost');assert.equal(writes.length,before+1,'A lost reply is not automatically resent');assert.equal(await invite().isEnabled(),true,'The user can explicitly retry a lost reply');const retry=await send();assert.equal(retry.request_id,lost.request_id,'Retry reuses the same operation receipt');assert.equal(receipts.size,count+1,'The server sees only one unique invitation operation');checks.push({lostReply:true,explicitRetry:true,sameRequestId:true});
  assert.deepEqual(errors,[]);assert.deepEqual(badResponses,[]);assert.deepEqual(unexpectedWrites,[]);assert.equal(failedRequests.filter(request=>request.url.endsWith('/api/community/alliance-action')).length,1,'Exactly one deliberately lost reply');
  fs.writeFileSync(path.join(out,'checks.json'),JSON.stringify({checks,errors,badResponses,writes,failedRequests,uniqueOperations:receipts.size},null,2));console.log('Screenshots: '+out);
 }catch(error){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({message:error.message,errors,badResponses,writes,failedRequests,log},null,2));if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
