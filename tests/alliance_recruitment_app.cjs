'use strict';
// Actual /city UI with a disposable FeatureDatabase and explicit synthetic recruitment responses.
// No recruitment write reaches PHP; server rules are covered by alliance_invitations.php.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.resolve(root,process.env.ALLIANCE_RECRUITMENT_OUTPUT||'output/playwright/alliance-recruitment');fs.mkdirSync(out,{recursive:true});
const sizes=process.env.ALLIANCE_RECRUITMENT_SIZES?process.env.ALLIANCE_RECRUITMENT_SIZES.split(',').map(size=>size.split('x').map(Number)):[[1280,800],[390,844],[320,568],[844,390],[568,320]];
const clone=value=>JSON.parse(JSON.stringify(value));
const alliances=[
 {id:1,world_id:1,name:'Morning Watch',tag:'MW',description:'A friendly home for every new kingdom.',leader_id:1,member_count:5,max_members:50,minimum_power:0,recruitment_mode:'open',recruitment_language:'en',play_style:'casual',activity_time:'flexible',activity_timezone:'Europe/Luxembourg'},
 {id:2,world_id:1,name:'Guardians of the Long Valley',tag:'GLV',description:'Apply with a short introduction and plan the next expedition together.',leader_id:10,member_count:12,max_members:50,minimum_power:0,recruitment_mode:'application',recruitment_language:'en',play_style:'pve',activity_time:'evening',activity_timezone:'Europe/Luxembourg'}
];
const expires=new Date(Date.now()+7*86400000).toISOString().slice(0,19).replace('T',' ');
const invitation=(id,aid)=>({...alliances.find(a=>a.id===aid),id,alliance_id:aid,player_id:1,invited_by:10,sender_name:'Captain Elara',status:'pending',expires_at:expires});
async function freePort(){const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const port=server.address().port;await new Promise(resolve=>server.close(resolve));return port;}
(async()=>{
 const port=await freePort(),base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--alliance-ranks','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,page,log='',scenario,kingdomAlliance,visit=0;
 const errors=[],badResponses=[],writes=[],unexpectedWrites=[],checks=[],touchIssues=[],queries=[];
 fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);
 function reset(role=null){scenario={role,allianceId:role?1:null,invitations:role?[]:[invitation(81,1),invitation(82,2)],sent:[],applications:[],inviteSearch:''};}
 function state(url){
  const params=new URL(url).searchParams,role=scenario.role;
  const candidates=params.get('invite_search')?.toLowerCase().includes('elara')?[{id:22,player_id:22,username:'Elara of the Northern Watch',display_name:'Elara of the Northern Watch',power:12345}]:[];
  const search=params.get('search')?.toLowerCase()||'';
  return {world_id:1,player_id:1,server_time:Math.floor(Date.now()/1000),power:50000,alliance:scenario.allianceId?clone(alliances.find(a=>a.id===scenario.allianceId)):null,role,role_level:{leader:5,vice_leader:4,officer:3,member:1}[role]||0,can_manage:['leader','vice_leader'].includes(role),can_plan:['leader','vice_leader','officer'].includes(role),search:{items:clone(alliances.filter(a=>(a.name+' '+a.tag).toLowerCase().includes(search))),next_cursor:null},invitations:clone(scenario.invitations),sent_invitations:clone(scenario.sent),invite_candidates:candidates,applications:clone(scenario.applications),notices:[],events:[],polls:[],poll_next:null,recent_members:[],open_help:[],research:[],territory_goal:null,pending_applications:[]};
 }
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',error=>{clearTimeout(timer);clearInterval(poll);reject(error);});});
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.status()>=400)badResponses.push(response.status()+' '+response.url());});
  page.on('request',request=>{if(['POST','PUT','PATCH','DELETE'].includes(request.method())&&request.url().includes('/api/')){const item={url:request.url(),body:request.postDataJSON()};writes.push(item);if(!request.url().endsWith('/api/community/alliance-action'))unexpectedWrites.push(item);}});
  reset();
  await page.route('**/api/kingdom/state*',async route=>{const response=await route.fetch(),json=await response.json();kingdomAlliance||=clone(json.data.alliance);if(!scenario.allianceId)json.data.alliance=null;else{json.data.alliance={...clone(kingdomAlliance),...clone(alliances.find(a=>a.id===scenario.allianceId)),role:scenario.role,role_level:{leader:5,vice_leader:4,officer:3,member:1}[scenario.role]};json.data.alliance.members=json.data.alliance.members.map(member=>Number(member.player_id)===1?{...member,role:scenario.role,role_level:json.data.alliance.role_level}:member);}await route.fulfill({response,json});});
  await page.route('**/api/community/alliance?*',async route=>{queries.push(route.request().url());await route.fulfill({json:{ok:true,data:state(route.request().url())}});});
  await page.route('**/api/community/alliance-action',async route=>{
   const body=route.request().postDataJSON();assert.equal(body.world_id,1,'Action carries the active world');assert.match(body.request_id,/^[a-zA-Z0-9_-]{16,80}$/,'Action carries a replay receipt');
   if(body.action==='invitation.send'){assert.equal(body.player_id,22);scenario.sent=[{id:91,alliance_id:1,player_id:22,username:'Elara of the Northern Watch',status:'pending',expires_at:expires}];}
   else if(body.action==='invitation.revoke'){assert.equal(body.invitation_id,91);scenario.sent=[];}
   else if(body.action==='invitation.decline')scenario.invitations=scenario.invitations.filter(i=>i.id!==body.invitation_id);
   else if(body.action==='invitation.accept'){scenario.allianceId=scenario.invitations.find(i=>i.id===body.invitation_id).alliance_id;scenario.role='member';scenario.invitations=[];}
   else if(body.action==='alliance.join'){scenario.allianceId=body.alliance_id;scenario.role='member';scenario.invitations=[];}
   else if(body.action==='application.submit'){assert.equal(body.alliance_id,2);scenario.applications=[{id:71,alliance_id:2,name:alliances[1].name,tag:alliances[1].tag,status:'pending',message:body.message}];}
   else if(body.action==='application.withdraw')scenario.applications=[];
   else throw Error('Unexpected synthetic action '+body.action);
   await route.fulfill({json:{ok:true,data:{message:'Saved.',result:{}}}});
  });
  await page.goto(base+'/?zugang=login');await page.locator('[name="identifier"], [name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);await page.locator('.painted-village').waitFor();
  assert.equal(await page.evaluate(()=>ConquerLocale.locale),'en','German browser keeps default English');
  const action=(name,id)=>page.locator('[data-action="alliance-social-'+name+'"]'+(id===undefined?'':'[data-id="'+id+'"]'));
  async function openAlliance(role=null){reset(role);await page.goto(base+'/city?recruitment_qa='+ ++visit+'#city',{waitUntil:'domcontentloaded'});await page.locator('.painted-village').waitFor();await page.locator('#navigation [data-id="alliance"]').click();await page.locator(role?'.alliance-home-tabs':'.alliance-community-shell').waitFor();}
  async function fit(label){const metrics=await page.locator('.alliance-community-shell').evaluate(element=>{const r=element.getBoundingClientRect(),body=element.querySelector('.alliance-social-body');return{fits:r.x>=-1&&r.y>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,sideways:element.scrollWidth>element.clientWidth+2||body.scrollWidth>body.clientWidth+2,documentOverflow:document.documentElement.scrollWidth>innerWidth+1,untranslated:/\balliance_community\.[a-z_]/.test(element.innerText),scrollable:getComputedStyle(body).overflowY==='auto',scrollbarsHidden:getComputedStyle(body).scrollbarWidth==='none',bodyHeight:body.clientHeight};});assert(metrics.bodyHeight>=44,label+' keeps room for one full touch target');delete metrics.bodyHeight;assert.deepEqual(metrics,{fits:true,sideways:false,documentOverflow:false,untranslated:false,scrollable:true,scrollbarsHidden:true},label);}
  async function touch(locator,label){await locator.scrollIntoViewIfNeeded();const metrics=await locator.evaluate(element=>{const r=element.getBoundingClientRect();return{width:innerWidth,height:innerHeight,label:element.textContent,action:element.dataset.action,height:r.height,size:r.height>=43,reachable:element.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))};});if(!metrics.size||!metrics.reachable)touchIssues.push({check:label,...metrics});}
  async function capture(name){await page.screenshot({path:path.join(out,name+'.png')});}
  async function clickAction(name,id){const before=writes.length;await action(name,id).click();await page.waitForFunction(()=>!document.querySelector('.alliance-social-heading button')?.disabled);assert.equal(writes.length,before+1,'One tap sends exactly one action');}
  for(const [width,height]of sizes){
   await page.setViewportSize({width,height});await openAlliance();await action('invitation-accept',81).waitFor();await page.evaluate(()=>document.fonts.ready);
   assert.deepEqual(await page.locator('.alliance-social-tabs button').evaluateAll(buttons=>buttons.map(button=>button.dataset.id)),['find'],'Nonmembers go directly to recruitment');
   for(const control of await page.locator('[data-action="alliance-social-invitation-accept"],[data-action="alliance-social-invitation-decline"],[data-action="alliance-social-join"],.alliance-social-form button[type="submit"]').all())if(await control.isVisible())await touch(control,'Recruitment action');
   await fit(width+'x'+height+' incoming invitations and search');await page.locator('.alliance-social-body').evaluate(el=>el.scrollTop=0);await capture('find-'+width+'x'+height);
   await clickAction('invitation-decline',81);await action('invitation-decline',81).waitFor({state:'detached'});assert.equal(await action('invitation-accept',82).count(),1,'Declining one invitation preserves another');
   await clickAction('invitation-accept',82);await page.locator('.alliance-home-tabs').waitFor();assert.equal(scenario.allianceId,2);assert.equal(scenario.role,'member');
   await openAlliance();await action('join',1).waitFor();await clickAction('join',1);await page.locator('.alliance-home-tabs').waitFor();assert.equal(scenario.allianceId,1,'Open alliance joins directly');
   await openAlliance();const apply=page.locator('[data-form="alliance-social-application"][data-id="2"]');await apply.locator('..').locator('summary').click();await apply.locator('[name="message"]').fill('Ready to help with the next expedition.');await touch(apply.locator('button[type="submit"]'),'Application submit');await apply.locator('button[type="submit"]').click();await action('withdraw',2).waitFor();await fit(width+'x'+height+' application submitted');await clickAction('withdraw',2);await action('withdraw',2).waitFor({state:'detached'});
   await openAlliance('vice_leader');await page.locator('.alliance-home-tabs [data-id="members"]').click();const inviteEntry=action('open','invite');await touch(inviteEntry,'Member list invite entry');await inviteEntry.click();const search=page.locator('[data-form="alliance-social-invite-search"]');await search.waitFor();await search.locator('[name="invite_search"]').fill('Elara');await search.locator('button[type="submit"]').click();await action('invite-send',22).waitFor();assert(queries.some(url=>new URL(url).searchParams.get('invite_search')==='Elara'),'Search sends the player name to the scoped API');
   await fit(width+'x'+height+' invite player search');await touch(action('invite-send',22),'Invite candidate');await capture('invite-'+width+'x'+height);
   await clickAction('invite-send',22);await action('invitation-revoke',91).waitFor();await touch(action('invitation-revoke',91),'Revoke outgoing invitation');await capture('sent-'+width+'x'+height);await clickAction('invitation-revoke',91);await action('invitation-revoke',91).waitFor({state:'detached'});
   const mobileBack=page.locator('#panel-dialog .mobile-page-back');if(await mobileBack.isVisible()){await mobileBack.click();await page.locator('.alliance-home-tabs').waitFor();await mobileBack.click();}else await page.locator('#panel-dialog [data-action="return-playfield"]').click();await page.locator('#panel-dialog').waitFor({state:'hidden'});await page.locator('.painted-village').waitFor();await capture('city-'+width+'x'+height);
   const castle=page.locator('.painted-village-building[data-id="castle"]');const position=await castle.evaluate(element=>{element.scrollIntoView({block:'center',inline:'center'});const r=element.getBoundingClientRect();for(const y of [.5,.8,.9,.3,.1])for(const x of [.5,.3,.7,.1,.9])if(element.contains(document.elementFromPoint(r.left+r.width*x,r.top+r.height*y)))return{x:r.width*x,y:r.height*y};return null;});assert(position,'Castle remains touch-accessible in the actual city');await castle.click({position});await page.locator('.painted-building-actions').waitFor();await capture('building-'+width+'x'+height);
   checks.push({width,height,nonmemberFind:true,declinedInvitation:true,acceptedInvitation:true,joinedOpenAlliance:true,applicationSubmittedAndWithdrawn:true,rank4InvitedAndRevoked:true,cityReturn:true,buildingActions:true});console.log('PASS recruitment and invitation UI '+width+'x'+height);
  }
  await openAlliance('member');await page.locator('.alliance-home-tabs [data-id="members"]').click();assert.equal(await action('open','invite').count(),0,'Regular members cannot invite players');
  assert.deepEqual(errors,[],'No browser errors');assert.deepEqual(badResponses,[],'No missing assets or failed responses');assert.deepEqual(unexpectedWrites,[],'No unintended gameplay writes');assert.deepEqual(touchIssues,[],'Every recruitment action remains touch-sized and reachable');
  fs.writeFileSync(path.join(out,'checks.json'),JSON.stringify({checks,errors,badResponses,writes,touchIssues},null,2));console.log('Screenshots: '+out);
 }catch(error){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({message:error.message,errors,badResponses,writes,touchIssues,log},null,2));if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
