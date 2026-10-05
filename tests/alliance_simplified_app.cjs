'use strict';
// Real /city app against a temporary synthetic Luxembourg database. Navigation only.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.resolve(root,process.env.ALLIANCE_SIMPLIFIED_OUTPUT||'artifacts/alliance-simplified');fs.mkdirSync(out,{recursive:true});
const sizes=process.env.ALLIANCE_SIMPLIFIED_SIZES?process.env.ALLIANCE_SIMPLIFIED_SIZES.split(',').map(size=>size.split('x').map(Number)):[[1280,800],[390,844],[320,568],[844,390],[568,320]];
async function freePort(){const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const port=server.address().port;await new Promise(resolve=>server.close(resolve));return port;}
(async()=>{
 const port=await freePort(),base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--territory','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,page,log='';const errors=[],badResponses=[],writes=[],checks=[],touchIssues=[];
 fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',error=>{clearTimeout(timer);clearInterval(poll);reject(error);});});
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  page.on('pageerror',error=>errors.push(error.message));
  page.on('response',response=>{if(response.url().includes('/api/')&&response.status()>=500)badResponses.push(response.status()+' '+response.url());});
  page.on('request',request=>{if(['POST','PUT','PATCH','DELETE'].includes(request.method())&&request.url().includes('/api/'))writes.push({url:request.url(),body:request.postData()});});
  await page.goto(base+'/?zugang=login');await page.locator('[name="identifier"], [name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);await page.locator('.painted-village').waitFor();
  assert.equal(await page.evaluate(()=>ConquerLocale.locale),'en','A German browser does not override the default English language');
  const response=await page.request.get(base+'/api/territory/state?world_id=1'),data=await response.json();assert(data.ok,data.message);const territory=data.data,goal=territory.targets.find(target=>target.id===territory.goal.target_id);assert(goal);
  const kingdomResponse=await page.request.get(base+'/api/kingdom/state'),kingdomData=await kingdomResponse.json();assert(kingdomData.ok,kingdomData.message);const alliance=kingdomData.data.alliance,leader=alliance.members.find(member=>Number(member.player_id)===Number(alliance.leader_id)),power=alliance.members.reduce((sum,member)=>sum+Number(member.power),0);assert(leader);
  async function fit(selector,label){const metrics=await page.locator(selector).evaluate(element=>{const r=element.getBoundingClientRect();return{fits:r.x>=-1&&r.y>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,sideways:element.scrollWidth>element.clientWidth+2,documentOverflow:document.documentElement.scrollWidth>innerWidth+1,missingText:/\b(?:alliance_home\.|territory\.ui\.|events\.simple\.)/.test(element.innerText)};});assert.deepEqual(metrics,{fits:true,sideways:false,documentOverflow:false,missingText:false},label);}
  async function touch(locator,label){await locator.scrollIntoViewIfNeeded();const metrics=await locator.evaluate(element=>{const r=element.getBoundingClientRect();return{width:innerWidth,height:innerHeight,label:element.textContent,action:element.dataset.action,id:element.dataset.id,targetHeight:r.height,size:r.height>=43,reachable:element.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))};});if(!metrics.size||!metrics.reachable)touchIssues.push({check:label,...metrics});}
  async function screenshot(name){await page.screenshot({path:path.join(out,name+'.png')});}
  async function openAlliance(){await page.goto(base+'/city#city',{waitUntil:'domcontentloaded'});await page.locator('.painted-village').waitFor();await page.locator('#navigation [data-id="alliance"]').click();await page.locator('.alliance-home-tabs').waitFor();const overview=page.locator('.alliance-home-tabs [data-id="overview"]');if(await overview.getAttribute('aria-pressed')!=='true')await overview.click();await page.locator('.alliance-home-primary').waitFor();}
  const tab=id=>page.locator('.alliance-home-tabs [data-action="panel-tab"][data-id="'+id+'"]');
  let primaryActionsChecked=false;
  for(const [width,height]of sizes){
   await page.setViewportSize({width,height});await openAlliance();await page.evaluate(()=>document.fonts.ready);
   assert.deepEqual(await page.locator('.alliance-home-tabs button').evaluateAll(buttons=>buttons.map(button=>button.dataset.id)),['overview','members','manage']);
   assert.equal(await page.locator('.alliance-home-primary .alliance-home-link').count(),8,'The reference overview offers eight existing destinations');
   assert.deepEqual(await page.locator('.alliance-home-primary .alliance-home-link').evaluateAll(buttons=>buttons.map(button=>[button.dataset.action,button.dataset.group||'',button.dataset.id||''].join(':')).sort()),['rally-list::','panel-tab:alliance:treasury','territory-open::goal','tab::expeditions','tab::market','community-open::research','tab::alliance-community','community-open::help'].sort(),'Every illustrated destination routes to its existing real feature');
   await page.waitForFunction(name=>document.querySelector('.alliance-home-goal-copy')?.textContent.includes(name),goal.name);
   assert.equal(await page.locator('.alliance-home-goal-copy b').getAttribute('translate'),'no','The real territory name is preserved');
   assert.equal(await page.locator('.alliance-home-primary [data-alliance-goal]').count(),0,'The actual shared goal sits separately from the eight navigation buttons');
   assert((await page.locator('.alliance-home-profile>.alliance-home-emblem').boundingBox()).width>=56,'The profile emblem remains readable at every size');
   assert.equal(await page.locator('.alliance-home-profile h2').innerText(),'['+alliance.tag+'] '+alliance.name);
   const facts=await page.locator('.alliance-home-stats>div').evaluateAll(rows=>rows.map(row=>({label:row.querySelector('dt')?.textContent,value:row.querySelector('dd')?.textContent.trim()})));
   assert.equal(facts.length,4,'Only the four authoritative alliance facts are displayed');assert(facts.every(fact=>fact.label&&fact.value));
   assert.equal(facts[0].value,leader.display_name,'The profile identifies the actual alliance leader');
   assert.equal(Number(facts[1].value.replace(/\D/g,'')),power,'Alliance power equals the authenticated member powers');
   assert.deepEqual(facts[2].value.match(/\d+/g)?.map(Number),[Number(alliance.member_count),Number(alliance.max_members)],'Member count and capacity are authoritative');
   assert.equal(facts[3].value,await page.evaluate(code=>ConquerLocale.supported[code]||code,alliance.recruitment_language),'The configured alliance language is shown');
   const description=page.locator('.alliance-home-notice .alliance-full-description');assert(await description.isVisible(),'The alliance description is visible without a disclosure');
   assert.equal((await description.innerText()).trim(),alliance.description||await page.evaluate(()=>ConquerLocale.t('alliance_home.description_empty')));
   assert.equal(await page.locator('.alliance-home-profile progress,.alliance-home-profile [role="progressbar"]').count(),0,'No fictional alliance XP or level progress is shown');
   const art=await page.locator('.alliance-home-primary img').evaluateAll(images=>images.map(img=>({loaded:img.complete&&img.naturalWidth>0,width:img.getBoundingClientRect().width,height:img.getBoundingClientRect().height})));
   assert(art.every(image=>image.loaded&&image.width>=40&&image.height>=40),'Every menu destination has a readable, loaded illustration');
   const menuLayout=await page.locator('.alliance-home-primary').evaluate(menu=>{const buttons=[...menu.querySelectorAll('button')],rects=buttons.map(button=>button.getBoundingClientRect());return{twoColumns:rects.every((r,i)=>i%2?r.left>rects[i-1].left&&Math.abs(r.top-rects[i-1].top)<2:!i||r.top>rects[i-2].top),titlesReadable:buttons.every(button=>{const title=button.querySelector('strong');return title&&title.scrollWidth<=title.clientWidth+1;})};});
   assert.deepEqual(menuLayout,{twoColumns:true,titlesReadable:true},'The reference menu has two columns and fully readable labels');
   assert.equal(await page.locator('.alliance-community-entry').count(),0,'The duplicate community navigation is removed');
   assert.equal(await page.locator('[data-alliance-details="about"]').count(),0,'The description is no longer hidden under About');
   await fit('#panel-dialog',width+'x'+height+' alliance');
   const dockBefore=await page.locator('.alliance-home-dock').boundingBox();assert.equal(await page.locator('.alliance-home-scroll .alliance-home-dock').count(),0,'Bottom navigation is outside the scrolling content');
   for(const action of await page.locator('.alliance-home-tabs button,.alliance-home-link,.alliance-home-goal').all())await touch(action,'Alliance navigation remains reachable');
   const dockAfter=await page.locator('.alliance-home-dock').boundingBox();assert(Math.abs(dockAfter.y-dockBefore.y)<1&&dockAfter.y+dockAfter.height<=height+1,'The bottom navigation remains fixed while the overview scrolls');
   await page.locator('.alliance-home-scroll').evaluate(element=>element.scrollTop=0);await screenshot('alliance-'+width+'x'+height);
   const overviewScroll=await page.locator('.alliance-home-scroll').evaluate(element=>{element.scrollTop=Math.min(100,element.scrollHeight-element.clientHeight);return element.scrollTop;});
   await tab('members').click();assert.equal(await page.locator('.alliance-home-members .list-row').count(),2);assert.equal(await page.locator('.panel-pagination').count(),0,'The member list has no page controls');await fit('#panel-dialog',width+'x'+height+' members');
   await tab('overview').click();const restoredOverview=await page.locator('.alliance-home-scroll').evaluate(element=>({scroll:element.scrollTop,max:element.scrollHeight-element.clientHeight}));assert(Math.abs(restoredOverview.scroll-overviewScroll)<2,'Returning from Members preserves the overview scroll position: '+JSON.stringify({expected:overviewScroll,...restoredOverview}));
   await tab('manage').click();await fit('#panel-dialog',width+'x'+height+' more');
   const treasury=page.locator('.alliance-home-link[data-action="panel-tab"][data-id="treasury"]');await touch(treasury,'Treasury remains available under More');await treasury.click();await page.locator('.treasury-view').waitFor();await touch(page.locator('.treasury-view button[type="submit"],.treasury-view form button').last(),'Treasury action stays reachable');
   await page.locator('.alliance-home-list-heading [data-action="panel-tab"][data-id="manage"]').click();await tab('overview').click();
   await page.locator('.alliance-home-link[data-action="territory-open"]').click();await page.locator('.territory-overview').waitFor();
   assert.equal(await page.locator('.territory-tabs [data-id="goal"]').getAttribute('aria-pressed'),'true','Territories opens on the shared alliance objective');
   await fit('#game-dialog',width+'x'+height+' territory goal');await screenshot('goal-'+width+'x'+height);
   await page.locator('.territory-tabs [data-id="territories"]').click();await page.locator('[data-action="territory-filter"][data-id="all"]').click();await page.locator('[data-action="territory-target"]').first().waitFor();
   await fit('#game-dialog',width+'x'+height+' territory list');
   const search=page.locator('[name="territory-search"]'),scope=page.locator('[name="territory-scope"]');
   await search.fill(goal.name);assert.equal(await page.locator('.territory-row').count(),1,'Search narrows the full map to the named target');
   const goalLink=page.locator('[data-action="territory-target"][data-id="'+goal.id+'"]');await goalLink.click();await page.locator('.territory-target-summary').waitFor();
   assert((await page.locator('.territory-target-summary').innerText()).includes(goal.name));
   assert.equal(await page.locator('[data-disclosure="facts"]').getAttribute('open'),null,'Detailed rules stay collapsed until requested');
   await fit('#game-dialog',width+'x'+height+' target detail');await screenshot('target-'+width+'x'+height);
   await touch(page.locator('[data-action="territory-back"]'),'Territory back remains reachable');await page.locator('[data-action="territory-back"]').click();
   assert.equal(await search.inputValue(),goal.name,'Back preserves the searched target');
   await search.fill('');await scope.selectOption('owned');assert.equal(await page.locator('.territory-row').count(),3,'Mine shows only the three fixture territories');
   await scope.selectOption('all');await page.locator('[data-action="territory-filter"][data-id="canton"]').click();assert.equal(await page.locator('.territory-row').count(),12,'Shrine filtering keeps the twelve cantons');
   const listMetrics=await page.locator('.territory-shell').evaluate(shell=>{const body=shell.querySelector('.territory-body'),list=body.querySelector('.territory-list'),row=list.firstElementChild,b=body.getBoundingClientRect(),r=row.getBoundingClientRect(),count=body.querySelector('.territory-result-count');return{bodyHeight:b.height,rowHeight:r.height,countHeight:count.getBoundingClientRect().height,rowOffset:r.top-b.top,rowVisible:r.top>=b.top-1&&r.bottom<=b.bottom+1,clippedFilters:[...shell.querySelectorAll('.territory-filters button')].filter(button=>button.scrollWidth>button.clientWidth+2).map(button=>({text:button.textContent,width:button.clientWidth,contentWidth:button.scrollWidth}))};});
   checks.push({width,height,territoryList:listMetrics});
   if(!listMetrics.rowVisible||listMetrics.clippedFilters.length)touchIssues.push({check:'A full Shrine row and unclipped category labels remain visible',width,height,...listMetrics});
   await screenshot('shrines-'+width+'x'+height);
   await touch(page.locator('[data-action="territory-close"]'),'Territory close remains reachable');await page.locator('[data-action="territory-close"]').click();await page.locator('#game-dialog').waitFor({state:'hidden'});
   assert.equal(await page.locator('#panel-dialog').getAttribute('data-panel'),'alliance','Closing Territories returns to the alliance');
   await page.goto(base+'/city#city',{waitUntil:'domcontentloaded'});await page.locator('.painted-village').waitFor();await screenshot('city-'+width+'x'+height);
   const castle=page.locator('.painted-village-building[data-id="castle"]');const position=await castle.evaluate(element=>{element.scrollIntoView({block:'center',inline:'center'});const r=element.getBoundingClientRect();for(const y of [.5,.8,.9,.3,.1])for(const x of [.5,.3,.7,.1,.9])if(element.contains(document.elementFromPoint(r.left+r.width*x,r.top+r.height*y)))return{x:r.width*x,y:r.height*y};return null;});assert(position,'Castle remains accessible in the actual city');await castle.click({position});await page.locator('.painted-building-actions').waitFor();await screenshot('building-'+width+'x'+height);
   if(!primaryActionsChecked){
    for(const destination of [
     {action:'rally-list',id:'',screen:'.rally-browser'},
     {action:'panel-tab',id:'treasury',screen:'.treasury-view'},
     {action:'community-open',id:'research',screen:'.community-research-tree'},
     {action:'community-open',id:'help',screen:'.community-columns'},
     {action:'tab',id:'alliance-community',screen:'.alliance-community-shell'},
     {action:'tab',id:'market',screen:'.trading-shell .trading-scroll'},
     {action:'tab',id:'expeditions',screen:'.hero-expedition'}
    ]){
     await openAlliance();const link=page.locator('.alliance-home-primary .alliance-home-link[data-action="'+destination.action+'"][data-id="'+destination.id+'"]');await link.click();await page.locator(destination.screen).first().waitFor();
     if(destination.action==='community-open')assert.equal(await page.locator('.community-tabs [data-id="'+destination.id+'"]').getAttribute('aria-pressed'),'true');
     checks.push({destination:destination.action+':'+destination.id,opened:true});
    }
    primaryActionsChecked=true;
   }
   checks.push({width,height,allianceTabs:3,primaryDestinations:8,goal:goal.id,profileFacts:4,fixedBottomNavigation:true});console.log('PASS actual reference alliance, eight destinations, shared goal, territory detail and city '+width+'x'+height);
  }
  await openAlliance();await page.waitForFunction(name=>document.querySelector('.alliance-home-goal-copy')?.textContent.includes(name),goal.name);
  await page.route(base+'/api/territory/state?world_id=1',async route=>{const response=await route.fetch(),json=await response.json();json.data.goal=null;await route.fulfill({response,json});});
  await page.evaluate(()=>{window.allianceTestClock=Date.now;Date.now=()=>window.allianceTestClock()+16000;});
  await tab('manage').click();await tab('overview').click();
  await page.waitForFunction(()=>document.querySelector('.alliance-home-goal-copy')?.textContent.includes('has not chosen a goal'));
  assert((await page.locator('[data-alliance-goal]>img').getAttribute('src')).endsWith('/territory-v3/canton-shrine.webp'),'Clearing the goal also removes the old target illustration');
  await page.evaluate(()=>{Date.now=window.allianceTestClock;delete window.allianceTestClock;});await page.unroute(base+'/api/territory/state?world_id=1');
  checks.push({goalCleared:true,targetIllustrationReset:true});
  assert.deepEqual(writes,[],'Opening alliance/territory screens must never issue gameplay actions');assert.deepEqual(errors,[]);assert.deepEqual(badResponses,[]);assert.deepEqual(touchIssues,[],'Every main action remains touch-sized and reachable');
  fs.writeFileSync(path.join(out,process.env.ALLIANCE_SIMPLIFIED_SIZES?'checks-targeted.json':'checks.json'),JSON.stringify({checks,errors,badResponses,writes,touchIssues},null,2));console.log('Screenshots: '+out);
 }catch(error){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({message:error.message,errors,badResponses,writes,touchIssues},null,2));if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
