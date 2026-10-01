'use strict';
// Read-only visual audit of the real app. Owns only disposable synthetic data.
const fs=require('fs'),path=require('path'),net=require('net'),crypto=require('crypto'),{spawn,execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const textContrast=require('./fixtures/menu_text_contrast.cjs');
const root=path.resolve(__dirname,'..'),remaining=process.argv.includes('--remaining'),finalRun=process.argv.includes('--final'),baselineOut=path.join(root,'artifacts/ui-ux-audit-2026-09-30/app-matrix'),out=process.env.UI_UX_MATRIX_OUTPUT|| (finalRun?baselineOut+'-final':remaining?baselineOut+'-remaining':baselineOut);
if(remaining&&finalRun)throw Error('--remaining and --final are mutually exclusive');
if(remaining){const original=path.join(baselineOut,'report.json'),backup=path.join(baselineOut,'baseline-report.json');if(!fs.existsSync(backup))fs.copyFileSync(original,backup);}
fs.mkdirSync(out,{recursive:true});
const sizes=[[1280,800],[390,844],[320,568],[844,390],[568,320]];
const panels=['profile','quests','army','research','inventory','treasures','mastery','market','defense','land','dungeons','expeditions','community','alliance-community','alliance-tools','events','rankings','arena','settings','worlds','account','help','alliance','reports','bugreport'];
const results=[],errors=[],badResponses=[],posts=[],responseDiagnostics=new Set();let active='setup';
async function recordConflictBody(response,entry){
 try{const body=await response.text();entry.contentType=response.headers()['content-type']||'';entry.responseBody=body.slice(0,2048);entry.responseBodyLength=body.length;entry.responseBodyTruncated=body.length>2048;}catch(e){entry.responseBodyError=String(e).slice(0,500);}
}
const hashEvidence={method:'SHA-256 of all CSS/JS/MJS and locale JSON files. Workspace and copied fixture are separate snapshots; fixture directory is matched to the live test port in its own server log.',errors:[]},hashSnapshots={};
function hashSnapshot(label,directory){
 const files=[];function walk(folder){for(const entry of fs.readdirSync(folder,{withFileTypes:true}).sort((a,b)=>a.name.localeCompare(b.name))){const file=path.join(folder,entry.name);if(entry.isDirectory())walk(file);else if(entry.isFile()&&/\.(css|js|mjs|json)$/i.test(entry.name)){const bytes=fs.readFileSync(file);files.push({path:path.relative(directory,file).replaceAll('\\','/'),bytes:bytes.length,modifiedAt:fs.statSync(file).mtime.toISOString(),sha256:crypto.createHash('sha256').update(bytes).digest('hex')});}}}
 for(const relative of ['assets/css','assets/js','data/i18n'])walk(path.join(directory,relative));
 const snapshot={at:new Date().toISOString(),directory,files},filename=label+'-hashes.json';hashSnapshots[label]=snapshot;fs.writeFileSync(path.join(out,filename),JSON.stringify(snapshot,null,2));hashEvidence[label]={file:filename,at:snapshot.at,count:files.length};
}
function hashChanges(before,after){const a=new Map(hashSnapshots[before].files.map(f=>[f.path,f.sha256])),b=new Map(hashSnapshots[after].files.map(f=>[f.path,f.sha256]));return [...new Set([...a.keys(),...b.keys()])].filter(p=>a.get(p)!==b.get(p)).sort();}
function findFixture(base){
 const temp=execFileSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['-r','echo sys_get_temp_dir();'],{encoding:'utf8',windowsHide:true,timeout:10000}).trim();
 const candidates=fs.readdirSync(temp,{withFileTypes:true}).filter(e=>e.isDirectory()&&/^conquer_feature_test_[a-f0-9]{12}$/.test(e.name)).map(e=>path.join(temp,e.name)).filter(dir=>{try{return fs.readFileSync(path.join(dir,'logs/server.log'),'utf8').includes('('+base+')');}catch{return false;}});
 if(candidates.length!==1)throw Error('Expected one fixture snapshot matching '+base+', found '+candidates.length);return candidates[0];
}
function save(){fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({at:new Date().toISOString(),scope:finalRun?'Final full app matrix including all 25 panel routes, locale/reflow/admin and invalid-login probes':remaining?'Remaining locale/reflow/admin probes and targeted short-landscape building check':'Full app matrix',baselineReport:remaining?path.join(baselineOut,'baseline-report.json'):null,sizes,panels,hashEvidence,results,errors,badResponses,posts,method:'Visible CSS-pixel metrics and real-app navigation. Candidates require visual review; not a WCAG certification or physical-device test. Targets below 44 px are design-guide candidates, not automatic WCAG failures. Route panels are reset to their initial scroll positions before each top capture.'},null,2));}
async function fontEvidence(page,selector){await page.locator(selector).first().waitFor({state:'visible'});await page.evaluate(()=>document.fonts.ready);const cdp=await page.context().newCDPSession(page);try{await cdp.send('DOM.enable');await cdp.send('CSS.enable');const {root:doc}=await cdp.send('DOM.getDocument',{depth:0});const {nodeId}=await cdp.send('DOM.querySelector',{nodeId:doc.nodeId,selector});if(!nodeId)throw Error('Font evidence node missing: '+selector);const fonts=(await cdp.send('CSS.getPlatformFontsForNode',{nodeId})).fonts;return{selector,text:await page.locator(selector).first().innerText(),status:fonts.length?'measured':'no_rendered_glyphs',fonts};}finally{await cdp.detach();}}
async function settleSurface(page,selector){
 await page.locator(selector).first().waitFor({state:'visible'});await page.evaluate(()=>document.fonts.ready);
 // Lazy images outside the viewport are deliberately not forced into the workload.
 try{await page.waitForFunction(selector=>[...document.querySelector(selector).querySelectorAll('img')].filter(img=>{if(!img.checkVisibility({checkOpacity:true,checkVisibilityCSS:true}))return false;const r=img.getBoundingClientRect();let l=Math.max(0,r.left),t=Math.max(0,r.top),right=Math.min(innerWidth,r.right),b=Math.min(innerHeight,r.bottom);for(let p=img.parentElement;p;p=p.parentElement){const s=getComputedStyle(p),pr=p.getBoundingClientRect();if(/hidden|clip|auto|scroll/.test(s.overflowX)){l=Math.max(l,pr.left);right=Math.min(right,pr.right);}if(/hidden|clip|auto|scroll/.test(s.overflowY)){t=Math.max(t,pr.top);b=Math.min(b,pr.bottom);}}return right-l>2&&b-t>2;}).every(img=>img.complete),selector,{timeout:5000});}catch(e){if(e.name!=='TimeoutError')throw e;}
 await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
}
async function recordNavigation(page,tag){results.push({tag,method:'Observed local-browser navigation snapshot. Response-start minus request-start excludes preceding connection setup but still includes request/response transit, not pure server CPU time. Resource timing totals may include cache hits or unfinished requests; this is not a mobile-device performance benchmark.',...(await page.evaluate(()=>{const nav=performance.getEntriesByType('navigation')[0],resources=performance.getEntriesByType('resource');return{url:location.pathname,navigation:nav?{type:nav.type,fetchStart:nav.fetchStart,domainLookupStart:nav.domainLookupStart,domainLookupEnd:nav.domainLookupEnd,connectStart:nav.connectStart,connectEnd:nav.connectEnd,secureConnectionStart:nav.secureConnectionStart,requestStart:nav.requestStart,responseStart:nav.responseStart,requestToFirstByteMs:nav.responseStart-nav.requestStart,ttfbMs:nav.responseStart-nav.startTime,responseEndMs:nav.responseEnd,domContentLoadedMs:nav.domContentLoadedEventEnd,loadMs:nav.loadEventEnd,loadComplete:nav.loadEventEnd>0,transferSize:nav.transferSize}:null,resourceCount:resources.length,resourceTransferSize:resources.reduce((n,r)=>n+r.transferSize,0),resourceDecodedSize:resources.reduce((n,r)=>n+r.decodedBodySize,0),observedAtMs:performance.now()};}))});save();}
async function metrics(page,selector){return page.locator(selector).first().evaluate(root=>{
 const cssPath=el=>el.tagName.toLowerCase()+(el.id?'#'+el.id:el.classList.length?'.'+[...el.classList].slice(0,3).join('.'):'');
 const visible=el=>{if(!el.checkVisibility({checkOpacity:true,checkVisibilityCSS:true}))return false;const r=el.getBoundingClientRect();if(r.width<1||r.height<1)return false;let left=Math.max(0,r.left),right=Math.min(innerWidth,r.right),top=Math.max(0,r.top),bottom=Math.min(innerHeight,r.bottom);for(let p=el.parentElement;p;p=p.parentElement){const s=getComputedStyle(p),b=p.getBoundingClientRect();if(/hidden|clip|auto|scroll/.test(s.overflowX)){left=Math.max(left,b.left);right=Math.min(right,b.right);}if(/hidden|clip|auto|scroll/.test(s.overflowY)){top=Math.max(top,b.top);bottom=Math.min(bottom,b.bottom);}}return right-left>2&&bottom-top>2;};
 const texts=[],targets=[],inputs=[],brokenImages=[],pendingImages=[];
 for(const el of root.querySelectorAll('*')){if(el.closest('svg,script,style,[aria-hidden=true]')||!visible(el))continue;const s=getComputedStyle(el),r=el.getBoundingClientRect(),text=[...el.childNodes].filter(n=>n.nodeType===3).map(n=>n.textContent.trim()).filter(Boolean).join(' ').slice(0,150);if(text&&/[\p{L}\p{N}]/u.test(text)){const disabled=!!el.closest('[disabled],[aria-disabled=true]'),userContent=!!el.closest('[data-user-content],[translate="no"],input,textarea,[contenteditable="true"]');texts.push({selector:cssPath(el),text,size:parseFloat(s.fontSize),lineHeight:s.lineHeight,weight:s.fontWeight,font:s.fontFamily,colour:s.color,background:s.backgroundColor,disabled,userContent,interactive:!!el.closest('button,a[href],input,select,textarea,[role=button]')});}}
 for(const el of root.querySelectorAll('button,a[href],input:not([type=hidden]),select,textarea,[role=button]')){if(!visible(el))continue;const r=el.getBoundingClientRect(),s=getComputedStyle(el),x=Math.max(0,Math.min(innerWidth-1,r.x+r.width/2)),y=Math.max(0,Math.min(innerHeight-1,r.y+r.height/2)),hit=document.elementFromPoint(x,y),disabled=el.disabled||el.getAttribute('aria-disabled')==='true';targets.push({selector:cssPath(el),action:el.dataset.action||'',name:(el.getAttribute('aria-label')||el.innerText||el.getAttribute('title')||el.getAttribute('placeholder')||'').trim().slice(0,100),width:r.width,height:r.height,x:r.x,y:r.y,disabled,centerHit:!!hit&&(hit===el||el.contains(hit)),outside:r.left<0||r.top<0||r.right>innerWidth||r.bottom>innerHeight});if(el.matches('input,select,textarea'))inputs.push({selector:cssPath(el),type:el.type,size:parseFloat(s.fontSize),label:el.labels?.length||0,ariaLabel:el.getAttribute('aria-label')||el.getAttribute('aria-labelledby')||'',placeholder:el.placeholder||''});}
 for(const img of root.querySelectorAll('img'))if(visible(img)){if(!img.complete)pendingImages.push(img.getAttribute('src'));else if(!img.naturalWidth)brokenImages.push(img.getAttribute('src'));}
 const r=root.getBoundingClientRect();return {lang:document.documentElement.lang,view:{hash:location.hash,panel:document.querySelector('#panel-dialog[open]')?.dataset.panel||null,title:document.querySelector('#panel-dialog[open] #page-title')?.textContent||null,detailsOpen:!!document.querySelector('#game-dialog[open]')},rect:{x:r.x,y:r.y,width:r.width,height:r.height},overflow:root.scrollWidth-root.clientWidth,documentOverflow:document.documentElement.scrollWidth-innerWidth,texts,targets,inputs,brokenImages,pendingImages,rootFont:getComputedStyle(root).fontFamily};
 });}
async function inspect(page,tag,selector,{screenshot=true}={}){active=tag;try{await settleSurface(page,selector);const data=await metrics(page,selector),contrast=await textContrast(page,selector);contrast.failures=await page.locator(selector).evaluate((root,findings)=>findings.map(f=>{let matches=[];try{matches=[...root.querySelectorAll(f.selector)].filter(el=>[...el.childNodes].filter(n=>n.nodeType===3).map(n=>n.textContent.trim()).join(' ').slice(0,100)===f.text);}catch{}return {...f,state:!matches.length?'unclassified':matches.every(el=>el.closest('[disabled],[aria-disabled=true]'))?'disabled':matches.every(el=>el.closest('.quest-row.claimed'))?'completed-content':'active-content'};}),contrast.failures);const row={tag,status:'captured',...data,contrast};results.push(row);if(screenshot)await page.screenshot({path:path.join(out,tag+'.png')});save();return row;}catch(e){results.push({tag,status:'inspection_failed',error:String(e)});save();}}
async function closeDetails(page){for(let i=0;i<5;i++){const d=page.locator('#game-dialog[open],.battle-preview-dialog[open]');if(!await d.count())return;await page.keyboard.press('Escape');await page.waitForTimeout(80);}}
async function resetPanelScroll(page){await page.locator('#panel-dialog').evaluate(root=>{for(const e of [root,...root.querySelectorAll('*')]){const s=getComputedStyle(e);if(/auto|scroll/.test(s.overflowY))e.scrollTop=0;if(/auto|scroll/.test(s.overflowX))e.scrollLeft=0;}});}
async function openPanel(page,id){
 await closeDetails(page);await page.evaluate(id=>{location.hash=id;},id);await page.locator('#panel-dialog[open][data-panel="'+id+'"]').waitFor();
 const ready={dungeons:'.dungeon-shell[aria-busy="false"]',army:'.training-school',research:'.rt-scroll',treasures:'.treasury-shell',inventory:'.inventory-shell',mastery:'.talent-node',defense:'.defense-body',land:'.land-detail-heading',community:'.social-hub .social-welcome','alliance-community':'.alliance-social-body > .alliance-social-card','alliance-tools':'.community-chat-log',worlds:'.world-selector',events:'.progression-content',account:'.progression-content',reports:'.mail-list[aria-busy="false"]',market:'.trading-shell',profile:'.lok-profile',quests:'.quest-list',rankings:'.ranking-table',arena:'[data-action="panel-tab"][data-group="arena"]',settings:'[data-form="settings"]',help:'.beginner-guide',expeditions:'.hero-expedition',alliance:'.alliance-home-scroll',bugreport:'[data-form="bug-report"]'}[id];
 if(!ready)throw Error('No readiness marker for route '+id);
 await page.locator('#panel-dialog').locator(ready).first().waitFor();
 if(id==='market')await page.waitForFunction(()=>document.querySelectorAll('#panel-dialog .trading-scroll[data-mode="merchant"] .shop-merchant-card').length===8&&!document.querySelector('#panel-dialog .trading-empty')&&![...document.querySelectorAll('#panel-dialog .trading-shell')].some(el=>el.textContent.includes('--:--:--')));
 await page.waitForFunction(()=>!document.querySelector('#panel-dialog #content [aria-busy="true"]'));
 await resetPanelScroll(page);await settleSurface(page,'#panel-dialog');
}
async function waitForGameData(page){await page.locator('#hud-research:not([data-job-state=loading])').waitFor({state:'attached'});}
async function inspectNarrowPanel(page,id,tag){
 const result={tag:tag+'-narrow-usability',status:'passed'};
 try{
  if(id==='expeditions'){
   result.layout=await page.locator('.hero-expedition').evaluate(root=>{const copy=root.querySelector('.hero-copy').getBoundingClientRect(),art=root.querySelector('.hero-art').getBoundingClientRect();return{copyBottom:copy.bottom,artTop:art.top,separate:art.top>=copy.bottom-1,documentOverflow:document.documentElement.scrollWidth-innerWidth};});
   if(!result.layout.separate||result.layout.documentOverflow>1)throw Error('Expedition illustration overlaps copy or exceeds the viewport');
  }else{
   const bar=page.locator(id==='inventory'?'.inventory-category-tabs':'.guide-tabs'),last=bar.locator('button').last();
   await last.scrollIntoViewIfNeeded();
   result.tabs=await bar.evaluate(root=>({scrollLeft:root.scrollLeft,scrollWidth:root.scrollWidth,clientWidth:root.clientWidth,documentOverflow:document.documentElement.scrollWidth-innerWidth,buttons:[...root.querySelectorAll('button')].map(el=>{const range=document.createRange();range.selectNodeContents(el.querySelector('span')||el);const r=range.getBoundingClientRect(),b=el.getBoundingClientRect();return{text:el.textContent,height:b.height,fontSize:parseFloat(getComputedStyle(el).fontSize),fits:r.width<=b.width+1,singleLine:r.height<=parseFloat(getComputedStyle(el).lineHeight)+1};})}));
   if(result.tabs.documentOverflow>1||result.tabs.buttons.some(b=>!b.fits||!b.singleLine||b.height<43))throw Error('Scrollable tabs clip or break their labels');
   await last.click();if(await last.getAttribute('aria-pressed')!=='true')throw Error('Last tab cannot be selected');
   await inspect(page,tag+'-last-tab','#panel-dialog');
  }
 }catch(e){result.status='usability_failed';result.error=String(e);}
 results.push(result);save();
}
async function openCity(page){await closeDetails(page);await page.evaluate(()=>location.hash='city');await page.locator('#panel-dialog').waitFor({state:'hidden'});await page.locator('.playfield-scene-city.is-active .painted-village').waitFor();await page.locator('#scene-transition.is-active').waitFor({state:'hidden'});await settleSurface(page,'.playfield-scene-city.is-active');}
async function inspectBuilding(page,width,height){
 const tag=`building-upgrade-${width}x${height}`;active=tag;
 try{await openCity(page);const code='hall_of_alliance',building=page.locator('.painted-village-building[data-id="'+code+'"]');await building.evaluate(el=>el.scrollIntoView({block:'center',inline:'center'}));
  const position=await building.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.7,.3,.9,.1])for(const fx of [.5,.7,.3,.9,.1]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
  if(!position){results.push({tag:tag+'-pointer-evidence',...(await building.evaluate(el=>{const r=el.getBoundingClientRect();return{rect:{x:r.x,y:r.y,width:r.width,height:r.height},transition:document.querySelector('#scene-transition')?.className,hits:[.3,.5,.7].flatMap(fy=>[.3,.5,.7].map(fx=>{const x=r.left+r.width*fx,y=r.top+r.height*fy,h=document.elementFromPoint(x,y);return{x,y,hit:h?.tagName+'#'+h?.id+'.'+h?.className};}))};}))});throw Error('Building is not reachable by pointer');}
  await building.click({position});await page.locator('.painted-building-menu [data-action="building"][data-id="'+code+'"]').click();await page.locator('#game-dialog[open][data-building="'+code+'"] .levelup-shell').waitFor();const row=await inspect(page,tag,'#game-dialog');if(row){row.probeScope='Current fixture building state; training fixture sets level 30. This is not an enabled upgrade or purchase test.';row.maximumLayout=await page.locator('.levelup-overview').evaluate(el=>{const max=el.querySelector('.levelup-levels strong'),range=document.createRange();range.selectNodeContents(max);const text=range.getBoundingClientRect(),column=el.getBoundingClientRect(),needs=document.querySelector('.levelup-needs').getBoundingClientRect();return{label:max.textContent,textLeft:text.left,textRight:text.right,columnLeft:column.left,columnRight:column.right,needsLeft:needs.left,contained:text.left>=column.left-1&&text.right<=column.right+1,overlapsNeeds:text.left<needs.right&&text.right>needs.left&&text.top<needs.bottom&&text.bottom>needs.top};});save();if(!row.maximumLayout.contained||row.maximumLayout.overlapsNeeds)throw Error('Maximum label crosses the building overview column');}
 }catch(e){results.push({tag,status:'route_failed',error:String(e)});await inspect(page,tag+'-failure-state','body');save();}finally{await closeDetails(page);}
}
async function inspectGrumwald(page,width,height){
 const tag=`grumwald-march-${width}x${height}`;active=tag;
 try{await closeDetails(page);await page.evaluate(()=>location.hash='world');await page.locator('#panel-dialog').waitFor({state:'hidden'});await page.locator('.playfield-scene-world.is-active .atlas-shell').waitFor();await page.locator('#scene-transition.is-active').waitFor({state:'hidden'});const boss=page.locator('.atlas-marker--monsters').filter({has:page.locator('img[src$="/grumwald.png"]')}).first();await boss.evaluate(el=>ConquerWorld.focus(Number(el.dataset.x)+.5,Number(el.dataset.y)+.5));const hit=await boss.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.7,.9,.3])for(const fx of [.5,.3,.7]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});if(!hit)throw Error('Focused boss has no reachable painted target');await boss.click({position:hit});await page.locator('#game-dialog[open] #march-preflight .boss-mechanic').waitFor();
  const tab=page.locator('[data-action=march-view][data-id=troops]');if(await tab.isVisible())await tab.click();await page.locator('#march-preflight .boss-mechanic p > strong').waitFor();await inspect(page,tag,'#game-dialog');
 }catch(e){results.push({tag,status:'route_failed',error:String(e)});save();}finally{await closeDetails(page);}
}
(async()=>{
 hashSnapshot('workspace-start',root);
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const p=server.address().port;server.close(()=>resolve(p));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--appearance','--hud','--chat','--talents','--training','--hospital','--inventory-overview','--mailbox','--map-search','--regional-bosses'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});let log='',browser,fixtureDirectory;const base='http://127.0.0.1:'+port;
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error('Fixture startup '+log)),90000);child.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',d=>log+=d);child.once('error',e=>{clearTimeout(timer);reject(e);});child.on('exit',code=>{clearTimeout(timer);reject(Error('Fixture exit '+code+': '+log));});});
  browser=await chromium.launch({headless:true,channel:'chrome'});const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});await context.addInitScript(()=>performance.setResourceTimingBufferSize(5000));const page=await context.newPage();page.setDefaultTimeout(12000);page.setDefaultNavigationTimeout(45000);
  const attach=p=>{p.on('pageerror',e=>errors.push({tag:active,message:e.message}));p.on('response',r=>{
   if(r.status()<400||new URL(r.url()).origin!==base)return;
   const entry={tag:active,at:new Date().toISOString(),status:r.status(),method:r.request().method(),url:new URL(r.url()).pathname};badResponses.push(entry);
   // Only the isolated fixture's conflict body is retained, never request payloads/cookies.
   if(r.status()===409){const pending=recordConflictBody(r,entry);responseDiagnostics.add(pending);void pending.finally(()=>responseDiagnostics.delete(pending));}
  });p.on('request',r=>{if(r.method()==='POST')posts.push({tag:active,url:new URL(r.url()).pathname});});};attach(page);
  active='login';await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});await page.evaluate(()=>document.fonts.ready);results.push({tag:'default-language',browserLocale:'de-DE',actual:await page.locator('html').getAttribute('lang')});
  try{fixtureDirectory=findFixture(base);hashSnapshot('fixture-start',fixtureDirectory);hashEvidence.workspaceToFixtureChanges=hashChanges('workspace-start','fixture-start');}catch(e){hashEvidence.errors.push({stage:'fixture-start',error:String(e)});}save();
  for(const [width,height]of (remaining?[[1280,800]]:sizes)){await page.setViewportSize({width,height});await inspect(page,`login-${width}x${height}`,'body');}
  await recordNavigation(page,'navigation-login');
  if(remaining||finalRun){
   active='login-invalid-320x568';await page.setViewportSize({width:320,height:568});await page.locator('[name=identifier]').fill('PreviewPlayer');await page.locator('[name=password]').fill('IncorrectSyntheticFixturePassword');
   await Promise.all([page.waitForURL('**/auth/local',{waitUntil:'domcontentloaded'}),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
   await page.locator('.play-error[role=alert]').waitFor();await page.locator('form[action$="/auth/local"] [name=password]').waitFor();await inspect(page,'login-invalid-320x568','body');await recordNavigation(page,'navigation-invalid-login');
  }
  active='login-valid';
  await page.setViewportSize({width:1280,height:800});await page.locator('[name=identifier]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);await page.locator('.painted-village').waitFor();await waitForGameData(page);await page.evaluate(()=>document.fonts.ready);
  results.push({tag:'actual-resource-fonts',...await fontEvidence(page,'#resources .resource strong')});
  await recordNavigation(page,'navigation-main-app');
  for(const [width,height]of (remaining?[]:sizes)){
   await page.setViewportSize({width,height});await openCity(page);await inspect(page,`city-${width}x${height}`,'body');
   await page.locator('#hud-menu').click();await page.locator('.menu-groups').waitFor();await inspect(page,`menu-${width}x${height}`,'#game-dialog');await closeDetails(page);
   await page.locator('#navigation [data-id=world]').click();await page.locator('.atlas-shell').waitFor();await page.waitForTimeout(650);await inspect(page,`world-${width}x${height}`,'body');
   for(const id of panels){const tag=id+'-'+width+'x'+height;active=tag;try{await openPanel(page,id);await inspect(page,tag,'#panel-dialog');if(['quests','help','account','settings','dungeons','inventory','market','profile'].includes(id)){await page.locator('#panel-dialog').evaluate(root=>{for(const e of root.querySelectorAll('*')){if(/auto|scroll/.test(getComputedStyle(e).overflowY)&&e.scrollHeight>e.clientHeight+10)e.scrollTop=e.scrollHeight;}});await inspect(page,tag+'-bottom','#panel-dialog',{screenshot:width===320});}}catch(e){results.push({tag,status:'route_failed',error:String(e)});await page.screenshot({path:path.join(out,tag+'-failure.png')}).catch(()=>{});save();}}
   await inspectBuilding(page,width,height);await inspectGrumwald(page,width,height);console.log(`Captured all app routes and building/march dialogs at ${width}x${height}`);save();
  }
  if(remaining){await page.setViewportSize({width:568,height:320});await openCity(page);await inspect(page,'city-before-building-568x320','body');await inspectBuilding(page,568,320);}
  for(const [width,height]of[[1280,800],[820,720],[568,320]]){
   await page.setViewportSize({width,height});
   for(const tab of ['members','manage']){
    const tag=`alliance-${tab}-${width}x${height}`;active=tag;
    try{
     await openPanel(page,'alliance');await page.locator(`[data-action="panel-tab"][data-group="alliance"][data-id="${tab}"]`).click();
     await page.locator('#content > .window-list').waitFor();
     const actions=[];
     for(const button of await page.locator('#content > .window-list button').all()){
      await button.scrollIntoViewIfNeeded();
      actions.push(await button.evaluate(el=>{const r=el.getBoundingClientRect(),list=el.closest('.window-list').getBoundingClientRect();return{text:el.textContent,height:r.height,inside:r.top>=list.top-1&&r.bottom<=list.bottom+1,centerHit:el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))};}));
     }
     if(actions.some(a=>!a.inside||!a.centerHit||a.height<43))throw Error('Alliance list contains unreachable actions: '+JSON.stringify(actions));
     results.push({tag:tag+'-actions',status:'passed',actions});await inspect(page,tag,'#panel-dialog');
    }catch(e){results.push({tag,status:'usability_failed',error:String(e)});save();}
   }
  }
  await openPanel(page,'quests');results.push({tag:'actual-title-fonts',...await fontEvidence(page,'#page-title')});
  // Keyboard/focus and text scale probes are distinct from native device testing.
  await page.setViewportSize({width:390,height:844});await openPanel(page,'settings');await page.keyboard.press('Tab');results.push({tag:'keyboard-focus',...(await page.evaluate(()=>{const e=document.activeElement,s=getComputedStyle(e),r=e.getBoundingClientRect();return {element:e.outerHTML.slice(0,300),outline:s.outline,boxShadow:s.boxShadow,inViewport:r.y>=0&&r.bottom<=innerHeight};}))});
  await page.emulateMedia({reducedMotion:'reduce'});await closeDetails(page);await page.evaluate(()=>location.hash='city');await page.waitForTimeout(600);results.push({tag:'reduced-motion',...(await page.evaluate(()=>({media:matchMedia('(prefers-reduced-motion: reduce)').matches,bodyClass:document.body.className,activeAnimations:document.getAnimations().filter(a=>a.playState==='running').length})))});await inspect(page,'city-reduced-motion','body');await page.emulateMedia({reducedMotion:'no-preference'});
  for(const locale of ['en','de','fr']){active='locale-'+locale+'-reload';await context.addCookies([{name:'conquer_locale',value:locale,url:base}]);await page.evaluate(l=>localStorage.setItem('conquer.locale',l),locale);await page.reload({waitUntil:'domcontentloaded'});await waitForGameData(page);await page.setViewportSize({width:320,height:568});for(const id of ['army','quests','inventory','market','help','expeditions']){try{await openPanel(page,id);await inspect(page,`${id}-${locale}-320x568`,'#panel-dialog');if(['inventory','help','expeditions'].includes(id))await inspectNarrowPanel(page,id,`${id}-${locale}-320x568`);}catch(e){results.push({tag:`${id}-${locale}`,status:'route_failed',error:String(e)});save();}}}
  await context.addCookies([{name:'conquer_locale',value:'en',url:base}]);await page.evaluate(()=>localStorage.setItem('conquer.locale','en'));await page.reload({waitUntil:'domcontentloaded'});await waitForGameData(page);
  // A reflow proxy only: 640 CSS px at DPR 2 approximates 1280 px/200% desktop geometry.
  const scaled=await browser.newContext({storageState:await context.storageState(),viewport:{width:640,height:400},deviceScaleFactor:2});const zoom=await scaled.newPage();attach(zoom);zoom.setDefaultTimeout(12000);zoom.setDefaultNavigationTimeout(45000);await zoom.goto(base+'/city#city',{waitUntil:'domcontentloaded'});await waitForGameData(zoom);for(const id of ['army','research','inventory','help']){try{await openPanel(zoom,id);await inspect(zoom,'reflow-proxy-'+id,'#panel-dialog');}catch(e){results.push({tag:'reflow-proxy-'+id,status:'route_failed',error:String(e)});}}await scaled.close();
  const admin=await context.newPage();attach(admin);admin.setDefaultTimeout(12000);admin.setDefaultNavigationTimeout(45000);await admin.goto(base+'/admin/login',{waitUntil:'domcontentloaded'});await admin.locator('[name=username]').fill('PreviewAdmin');await admin.locator('[name=password]').fill('PreviewFixture!2026');await Promise.all([admin.waitForURL(base+'/admin'),admin.locator('button[type=submit]').click()]);const links=await admin.locator('#admin-nav a').evaluateAll(es=>es.map(e=>({name:e.innerText,url:e.href})));
  active='admin-route-inventory';if(!links.length)throw Error('Authenticated backoffice navigation contains no routes');results.push({tag:'admin-route-inventory',count:links.length,routes:links});save();
  for(const [width,height]of[[1280,800],[390,844],[568,320]]){await admin.setViewportSize({width,height});for(const link of links){const code=new URL(link.url).pathname.replace('/admin','').replaceAll('/','-')||'-dashboard',tag='admin'+code+'-'+width+'x'+height;try{active=tag;await admin.goto(link.url,{waitUntil:'domcontentloaded'});await inspect(admin,tag,'body');}catch(e){results.push({tag,status:'route_failed',error:String(e)});save();}}console.log(`Captured all backoffice routes at ${width}x${height}`);}
  active='admin-compact-navigation';
  try{
   await admin.evaluate(()=>scrollTo(0,0));
   const compact=await admin.evaluate(()=>({headerBottom:document.querySelector('.topbar').getBoundingClientRect().bottom,documentOverflow:document.documentElement.scrollWidth-innerWidth,controls:[...document.querySelectorAll('.mobile-menu,.sidebar select,.world-picker select,.world-picker button')].filter(e=>e.checkVisibility()).map(e=>({name:e.getAttribute('aria-label')||e.textContent,height:e.getBoundingClientRect().height}))}));
   if(compact.documentOverflow>1||compact.controls.some(c=>c.height<43))throw Error('Compact administration clips the viewport or shrinks an action');
   await admin.locator('.mobile-menu').click();await admin.locator('#admin-nav.is-open').waitFor();
   const last=admin.locator('#admin-nav a').last();await last.scrollIntoViewIfNeeded();
   compact.lastLinkReachable=await last.evaluate(el=>{const r=el.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));});
   if(!compact.lastLinkReachable)throw Error('Last administration route is unreachable in landscape');
   results.push({tag:active,status:'passed',...compact});await inspect(admin,active+'-open','body');
   await admin.locator('.mobile-menu').click();
  }catch(e){results.push({tag:active,status:'usability_failed',error:String(e)});}
  save();console.log('UI/UX matrix complete: '+results.length+' observations; '+errors.length+' browser errors; inspect report for findings.');
 }catch(e){results.push({tag:active,status:'audit_failed',error:String(e)});save();throw e;}finally{
  await Promise.allSettled([...responseDiagnostics]);
  try{hashSnapshot('workspace-end',root);hashEvidence.workspaceChanges=hashChanges('workspace-start','workspace-end');if(fixtureDirectory){hashSnapshot('fixture-end',fixtureDirectory);hashEvidence.fixtureChanges=hashChanges('fixture-start','fixture-end');}}catch(e){hashEvidence.errors.push({stage:'end',error:String(e)});}save();
  await browser?.close();if(child.exitCode===null){child.stdin.end('\n');await new Promise(resolve=>child.once('exit',resolve));}fs.writeFileSync(path.join(out,'fixture.log'),log);
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
