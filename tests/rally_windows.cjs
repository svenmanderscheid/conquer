'use strict';
// Isolated real renderer/CSS/composer regression. No account, database or live API.
// Run with Playwright available, or set PLAYWRIGHT_MODULE and PLAYWRIGHT_CHANNEL.
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/rally-windows');
fs.mkdirSync(out,{recursive:true});
const catalogs=Object.fromEntries(['en','de','fr'].map(locale=>[locale,JSON.parse(fs.readFileSync(path.join(root,'data/i18n',locale+'.json'),'utf8'))]));
const styles=[...fs.readFileSync(path.join(root,'views/game.php'),'utf8').matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(m=>m[1]);
const scripts=['localization','mobile-pages','castle-skins','reward-dialog','march-panel','rally-panel'];
const html=`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${styles.map(n=>'<link rel="stylesheet" href="/assets/css/'+n+'.css">').join('')}
</head><body class="mobile-game world-mode"><dialog id="panel-dialog"><div class="page-heading"></div></dialog><dialog id="game-dialog"><button class="dialog-close" aria-label="Close">×</button><div id="dialog-content"></div></dialog>
<script>window.CONQUER_BASE='';window.CONQUER_I18N=${JSON.stringify({locale:'en',catalogs}).replaceAll('<','\\u003c')};</script>
${scripts.map(n=>'<script src="/assets/js/'+n+'.js"></script>').join('')}
<script>
window.fixtureNow=Date.parse('2030-01-01T12:00:00Z');
const stamp=seconds=>new Date(fixtureNow+seconds*1000).toISOString().slice(0,19).replace('T',' ');
const troop={code:50100101,type:1,tier:1,name:'Infantry',attack:10,gather_carry:10,march_speed:50,pvp_rally_speed:50,monster_rally_speed:100,monster_power:10,monster_power_single_type:10,monster_rally_power:12,monster_rally_power_single_type:12};
window.state={player:{id:7},city:{id:7,player_id:7,world_id:1,coord_x:20,coord_y:20,action_points:200},world:{map_profile:{travel_scale:1}},troop_defs:[troop],troops:{50100101:5000},army_limits:{march_capacity:5000,march_slots:3},marches:[],players:[],monsters:[],nodes:[]};
const leader={name:'Forschung',coord_x:25,coord_y:20,power:1200,avatar:'knight',city_skin:'default'};
const basic={id:1,target_kind:'monster',leader_player_id:8,leader_name:'Forschung',leader,capacity:2000,troops:{50100101:100},participants:[],participant_count:0,status:'gathering',launch_at:stamp(300),target_x:30,target_y:40,result:{monster:{name:'Frostgrimm',level:3,art:'monsters/frostgrimm'}}};
window.listRows=[basic,{...basic,id:2,capacity:100,troops:{50100101:100}},{...basic,id:3,launch_at:stamp(-1)},{...basic,id:4,leader_player_id:7},{...basic,id:5,participants:[{player_id:7,status:'pending',troops:{50100101:10}}],participant_count:1},{...basic,id:6,status:'marching',arrival_time:stamp(600)},{...basic,id:7,status:'returning',return_time:stamp(900)},{...basic,id:8,target_kind:'territory',target_name:'Commune Forschung',launch_at:stamp(1200)},{...basic,id:9,target_kind:'city',target_name:'Forschung',target_player:{...leader,name:'Forschung'},capacity:null,launch_at:stamp(1800)}];
window.detailRows=Object.fromEntries(listRows.map(r=>[r.id,{rally:structuredClone(r),participants:structuredClone(r.participants)}]));
detailRows[1].participants=Array.from({length:12},(_,i)=>({player_id:30+i,username:i?'Ally '+i:'Forschung',profile:{name:i?'Ally '+i:'Forschung',power:900},troops:{50100101:25},status:i===0?'joining':'pending',arrival_time:stamp(30)}));
Object.assign(detailRows[1].participants[11],{player_id:-1,is_ai:true,name_key:'rally.ai.name',username:'Royal Vanguard (AI)',profile:null});
detailRows[1].rally.participant_count=12;detailRows[1].rally.participants=detailRows[1].participants;
window.sent=[];window.reads=[];window.notices=[];window.composerCalls=[];
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const parse=value=>Date.parse(String(value).replace(' ','T').replace(/Z?$/,'Z'));
window.countdown=seconds=>{seconds=Math.max(0,Math.ceil(seconds));return Math.floor(seconds/60)+':'+String(seconds%60).padStart(2,'0')};
window.mobile=ConquerMobilePages({navigate(){},getRoute:()=> 'world',getPlayfield:()=> 'world',closeChat(){}});
function openDialog(content){
 const dialog=document.querySelector('#game-dialog'),body=document.querySelector('#dialog-content');
 delete dialog.dataset.march;dialog.classList.remove('march-dialog','has-popup-heading');dialog.querySelector(':scope > .popup-heading')?.remove();body.innerHTML=content;
 const heading=body.querySelector('h2');if(heading){heading.id='dialog-title';heading.tabIndex=-1;dialog.setAttribute('aria-labelledby','dialog-title');if(!body.querySelector('.march-command,.territory-shell')){const header=document.createElement('header');header.className='popup-heading';header.append(heading);dialog.insertBefore(header,body);dialog.classList.add('has-popup-heading');}}
 mobile.syncDialog();if(!dialog.open){dialog.showModal();mobile.opened('dialog');}dialog.scrollTop=0;heading?.focus({preventScroll:true});ConquerLocale.apply(dialog);
}
const api=async(path,payload)=>{if(payload)throw new Error('Unexpected preview/write '+path);reads.push(path);const result=path==='rally/list'?{rallies:structuredClone(listRows)}:structuredClone(detailRows[Number(path.split('/')[1])]);if(window.delayReadPath===path){window.delayReadPath=null;await new Promise(resolve=>window.releaseRead=resolve);}return result;};
const action=async(path,payload)=>{sent.push({path,payload});if(window.delayAction){window.delayAction=false;await new Promise(resolve=>window.releaseAction=resolve);}return{joined:true}};
const common={base:'',api,esc,fmt:n=>Number(n||0).toLocaleString('en-US'),date:parse,duration:countdown,now:()=>fixtureNow,openDialog,action,toast:m=>notices.push(m),getState:()=>state};
window.march=ConquerMarch({...common,getKingdom:()=>({hospital:{capacity:10000,used:0}}),getProfile:()=>({action_points:200}),unitName:t=>t.name});
const marchProxy={open(...args){composerCalls.push(args);march.open(...args)}};
window.rallies=ConquerRallies({...common,marchPanel:marchProxy});
document.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(button&&!rallies.onClick(button.dataset.action,button))march.onClick(button.dataset.action,button)});
document.querySelector('.dialog-close').onclick=()=>document.querySelector('#game-dialog').close();
window.advanceTime=seconds=>{fixtureNow+=seconds*1000;document.querySelectorAll('[data-end]').forEach(el=>el.textContent=countdown((parse(el.dataset.end)-fixtureNow)/1000));rallies.updateTime();march.update();ConquerLocale.apply();};
</script></body></html>`;

async function geometry(page,selector){return page.locator(selector).evaluate(element=>{const r=element.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height,scrollHeight:element.scrollHeight,clientHeight:element.clientHeight,scrollWidth:element.scrollWidth,clientWidth:element.clientWidth};});}
async function reachable(page,selector,label,minHeight=40){
 const result=await page.locator(selector).evaluate((element,minHeight)=>{const r=element.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {okay:r.width>0&&r.height>=minHeight&&r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&element.contains(hit),rect:{x:r.x,y:r.y,width:r.width,height:r.height},hit:hit?.outerHTML.slice(0,140)};},minHeight);
 assert(result.okay,label+' '+JSON.stringify(result));
}
async function stableFrame(page,viewport,label){
 const frame=await geometry(page,'#game-dialog');assert(frame.x>=-1&&frame.y>=-1&&frame.right<=viewport.width+1&&frame.bottom<=viewport.height+1,label+' frame '+JSON.stringify(frame));
 const content=await geometry(page,'#dialog-content');assert(content.scrollWidth<=content.clientWidth+1,label+' horizontal overflow');
 assert(content.scrollHeight<=content.clientHeight+1,label+' outer scrolls instead of inner list '+JSON.stringify(content));
 const back=page.locator('#game-dialog .mobile-page-back');
 await reachable(page,await back.isVisible()?'#game-dialog .mobile-page-back':'.dialog-close',label+' close/back',32);
}
async function statusContrasts(page,view){return page.locator('.rally-status').evaluateAll((elements,view)=>elements.map(element=>{
 const style=getComputedStyle(element),light=value=>{const channels=value.match(/[\d.]+/g).slice(0,3).map(n=>{n=Number(n)/255;return n<=.04045?n/12.92:Math.pow((n+.055)/1.055,2.4)});return channels[0]*.2126+channels[1]*.7152+channels[2]*.0722},fg=light(style.color),bg=light(style.backgroundColor),ratio=(Math.max(fg,bg)+.05)/(Math.min(fg,bg)+.05),origins=[];
 if(ratio<4.5){const visit=(rules,file)=>{for(const rule of rules){if(rule.selectorText&&rule.style?.color){try{if(element.matches(rule.selectorText))origins.push({file,selector:rule.selectorText,color:rule.style.color,important:rule.style.getPropertyPriority('color')})}catch{}}if(rule.cssRules)visit(rule.cssRules,file)}};for(const sheet of document.styleSheets)try{visit(sheet.cssRules,sheet.href?.split('/').pop()||'inline')}catch{}}
 return {view,status:element.textContent,color:style.color,background:style.backgroundColor,ratio,...(origins.length?{origins}:{} )};
}),view);}

(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const errors=[],failures=[],localizedFooters=[];let checks=0;
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},locale:'de-DE'});page.setDefaultTimeout(5000);page.on('pageerror',error=>errors.push(error.message));
  await page.route('https://rally-windows.fixture/**',route=>{const url=new URL(route.request().url());if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html});const file=path.resolve(root,'.'+url.pathname);if(!file.startsWith(root+path.sep)||!fs.existsSync(file))return route.fulfill({status:404});return route.fulfill({path:file});});
  await page.goto('https://rally-windows.fixture/');await page.evaluate(()=>rallies.list());
  assert.equal(await page.locator('html').getAttribute('lang'),'en','English is default despite German browser locale');
  const statusReport=await statusContrasts(page,'list');
  for(const [id,status] of [[6,'marching'],[7,'returning']]){await page.evaluate(id=>rallies.onClick('rally-detail',{dataset:{id:String(id)}}),id);await page.waitForSelector('.rally-detail-hero.rally-card--'+status);statusReport.push(...await statusContrasts(page,'detail'));}
  await page.evaluate(()=>rallies.sync([]));statusReport.push(...await statusContrasts(page,'inactive detail'));
  const badStatuses=statusReport.filter(status=>status.ratio<4.5);console.log('STATUS_CONTRAST '+JSON.stringify(statusReport.map(({view,status,ratio})=>({view,status,ratio:Number(ratio.toFixed(2))}))));
  if(process.env.RALLY_STATUS_ONLY){assert.deepEqual(badStatuses,[],'all rally status contrast');fs.writeFileSync(path.join(out,'status-contrast.json'),JSON.stringify(statusReport,null,2));console.log('PASS list, marching/returning detail and inactive status contrast');return;}
  for(const viewport of [{width:1280,height:800},{width:390,height:844},{width:320,height:568},{width:844,height:390},{width:568,height:320}]){
   const label=viewport.width+'x'+viewport.height;await page.setViewportSize(viewport);await page.evaluate(()=>rallies.list());
   try{
    await stableFrame(page,viewport,label+' list');assert.equal(await page.locator('.rally-list .rally-card').count(),9,'every rally rendered');assert.equal(await page.locator('.panel-pagination').count(),0,'no pagination');
    await page.waitForFunction(()=>[...document.querySelectorAll('.rally-browser img')].every(img=>img.complete&&img.naturalWidth>0));
    assert.equal(await page.locator('.rally-card').first().getAttribute('data-rally-id'),'1','joinable rallies first');
    assert.equal(await page.locator('.rally-card[data-rally-id="1"] [data-action="rally-join"]').getAttribute('aria-label'),'Choose troops');
    assert.equal(await page.locator('.rally-card[data-rally-id="1"] .rally-join-plus').innerText(),'+');
    assert.equal(await page.locator('.rally-card[data-rally-id="1"] .rally-side--leader strong').textContent(),'Forschung','player name is not translated');
    assert.equal(await page.locator('.rally-card[data-rally-id="1"] .rally-team-avatars img').getAttribute('alt'),'Forschung','avatar name is not translated');
    assert.equal(await page.locator('.rally-card[data-rally-id="1"] .rally-team-avatars img').getAttribute('title'),'Forschung','avatar tooltip is not translated');
    const cover=await page.locator('.rally-card[data-rally-id="1"] .rally-card-open').evaluate(el=>({image:getComputedStyle(el).backgroundImage,color:getComputedStyle(el).backgroundColor}));
    assert.equal(cover.image,'none');assert.equal(cover.color,'rgba(0, 0, 0, 0)','card content remains visible behind its clickable area');
    assert.match(await page.locator('.rally-card[data-rally-id="1"] .rally-target-art img').getAttribute('src'),/storybook-v2\/frostgrimm\.png$/,'shared boss art');
    for(const id of [2,3,4,5,6,7])assert(await page.locator('.rally-card[data-rally-id="'+id+'"] [data-action="rally-join"]').isDisabled(),'blocked rally '+id);
    await page.locator('.rally-card[data-rally-id="7"]').scrollIntoViewIfNeeded();const scroll=await page.locator('.rally-list').evaluate(el=>el.scrollTop);assert(scroll>0,'last rally reachable by scroll');
    await page.evaluate(()=>{listRows[1].target_x++;rallies.sync(listRows)});assert.equal(await page.locator('.rally-list').evaluate(el=>el.scrollTop),scroll,'poll preserves list scroll');
    await page.locator('.rally-list').evaluate(el=>el.scrollTop=0);await page.screenshot({path:path.join(out,label+'-list.png')});
    await page.locator('.rally-card[data-rally-id="1"] [data-action="rally-detail"]').click({position:{x:15,y:15}});await page.waitForSelector('.rally-detail');
    await stableFrame(page,viewport,label+' detail');assert.equal(await page.locator('.rally-member').count(),13,'all twelve members and captain shown');
    assert.equal(await page.locator('.rally-member[open]').count(),1,'captain troop roster opens initially');
    const ally=page.locator('.rally-member[data-player-id="30"]');await ally.locator('summary').click();assert(await ally.locator('.rally-member-troops').isVisible(),'member tap shows exact troop roster');assert((await page.locator('.rally-member[data-player-id="-1"]').textContent()).includes(catalogs.en['rally.ai.label']),'AI reinforcement is explicitly labelled');
    await page.evaluate(()=>{const rows=structuredClone(listRows);rows[0]=structuredClone(detailRows[1].rally);rows[0].target_y++;rallies.sync(rows)});
    assert(await ally.locator('.rally-member-troops').isVisible(),'poll preserves expanded member troops');
    await page.waitForFunction(()=>[...document.querySelectorAll('.rally-detail img')].every(img=>img.complete&&img.naturalWidth>0));
    const contrast=await page.locator('.rally-detail-hero .rally-status').evaluate(element=>{const style=getComputedStyle(element),light=value=>{const channels=value.match(/[\d.]+/g).slice(0,3).map(n=>{n=Number(n)/255;return n<=.04045?n/12.92:Math.pow((n+.055)/1.055,2.4)});return channels[0]*.2126+channels[1]*.7152+channels[2]*.0722},fg=light(style.color),bg=light(style.backgroundColor);return {ratio:(Math.max(fg,bg)+.05)/(Math.min(fg,bg)+.05),color:style.color,background:style.backgroundColor};});
    assert(contrast.ratio>=4.5,label+' readable status pill '+JSON.stringify(contrast));
    await reachable(page,'.rally-detail-actions [data-action="rally-join"]',label+' fixed join');
    const before=await geometry(page,'.rally-detail-actions');await page.locator('.rally-member').last().scrollIntoViewIfNeeded();assert((await page.locator('.rally-detail-scroll').evaluate(el=>el.scrollTop))>0,'members scroll');
    const after=await geometry(page,'.rally-detail-actions');assert.equal(after.y,before.y,'join action remains fixed');await reachable(page,'.rally-detail-actions [data-action="rally-join"]',label+' fixed join after scroll');
    await page.locator('.rally-detail-scroll').evaluate(el=>el.scrollTop=0);await page.screenshot({path:path.join(out,label+'-detail.png')});
    await page.locator('.rally-detail-actions [data-action="rally-join"]').click();await page.waitForSelector('.march-command');
    await reachable(page,'#march-confirm',label+' composer confirm');assert.equal(await page.evaluate(()=>sent.length),0,'no dispatch when choosing troops');
    const meta=await page.evaluate(()=>{const [id,kind,options]=composerCalls.at(-1);return {id,kind,capacity:options.rally_capacity_remaining,launch:options.rally_launch_at,status:options.rally_status,target:options.rally_target_kind,x:options.target.coord_x,name:options.target.display_name};});
    assert.deepEqual(meta,{id:8,kind:'rally-join',capacity:1600,launch:'2030-01-01 12:05:00',status:'gathering',target:'monster',x:25,name:'Forschung'});
    await page.screenshot({path:path.join(out,label+'-join.png')});
    if(await page.locator('#game-dialog .mobile-page-back').isVisible()){
     await page.locator('#game-dialog .mobile-page-back').click();await page.waitForSelector('.rally-detail');
     assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),true,'mobile header returns to rally details');
     assert.equal(await page.locator('.rally-detail .rally-side--leader strong').textContent(),'Forschung','mobile return preserves rally selection');
     assert.equal(await page.evaluate(()=>sent.length),0,'mobile return does not dispatch');
    }
    checks++;console.log('PASS '+label+' list/detail/shared join, scroll and fixed controls');
   }catch(error){failures.push(label+': '+error.message);await page.screenshot({path:path.join(out,label+'-failure.png')});console.log('FAIL '+label+': '+error.message);}
  }
  await page.setViewportSize({width:1280,height:800});await page.evaluate(()=>rallies.list());
  const readsBefore=await page.evaluate(()=>reads.filter(path=>path==='rally/1').length);await page.locator('.rally-card[data-rally-id="1"] [data-action="rally-join"]').click();await page.waitForSelector('.march-command');
  assert.equal(await page.evaluate(()=>reads.filter(path=>path==='rally/1').length),readsBefore+1,'direct list join refreshes detail');assert.equal(await page.evaluate(()=>sent.length),0);
  await page.locator('#march-confirm').click();assert.equal(await page.evaluate(()=>sent.length),1,'only explicit confirmation dispatches');assert.equal(await page.evaluate(()=>sent[0].path),'rally/join');assert.equal(await page.evaluate(()=>sent[0].payload.rally_id),1);checks++;
  await page.evaluate(()=>{sent=[];detailRows[1].rally.capacity=400;rallies.list()});await page.waitForSelector('.rally-list');
  const composerBefore=await page.evaluate(()=>composerCalls.length);await page.locator('.rally-card[data-rally-id="1"] [data-action="rally-join"]').click();await page.waitForSelector('.rally-detail');
  assert.equal(await page.evaluate(()=>composerCalls.length),composerBefore,'fresh full capacity blocks stale list invitation');assert(await page.locator('.rally-detail-actions [data-action="rally-join"]').isDisabled());assert.equal(await page.locator('.rally-detail-actions [data-action="rally-join"]').textContent(),'Rally full');await page.evaluate(()=>detailRows[1].rally.capacity=2000);checks++;
  await page.evaluate(()=>{window.delayReadPath='rally/list';});
  await page.locator('.rally-detail-actions [data-action="rally-list"]').click();
  await page.evaluate(()=>{const rows=structuredClone(listRows);rows[0].target_y++;rallies.sync(rows);releaseRead();});
  await page.waitForSelector('.rally-list');assert.equal(await page.locator('.rally-detail').count(),0,'live polling cannot swallow Back to rallies');checks++;
  await page.evaluate(()=>{sent=[];rallies.list()});await page.waitForSelector('.rally-list');
  const timer=page.locator('.rally-card[data-rally-id="1"] [data-end]');assert.equal(await timer.textContent(),'5:00');await page.evaluate(()=>advanceTime(1));assert.equal(await timer.textContent(),'4:59','countdown updates via shared data-end mechanism');
  await page.evaluate(()=>advanceTime(300));assert(await page.locator('.rally-card[data-rally-id="1"] [data-action="rally-join"]').isDisabled());assert.equal(await page.locator('.rally-card[data-rally-id="1"] [data-action="rally-join"]').getAttribute('aria-label'),'Joining closed');checks++;
  await page.locator('.rally-card[data-rally-id="8"] [data-action="rally-join"]').click();await page.waitForSelector('.march-command');assert.equal(await page.evaluate(()=>composerCalls.at(-1)[2].rally_target_kind),'territory','territory uses shared composer');assert.equal(await page.evaluate(()=>sent.length),0);checks++;
  await page.evaluate(()=>rallies.list());const beforeDelayed=await page.evaluate(()=>composerCalls.length);await page.evaluate(()=>window.delayReadPath='rally/8');await page.locator('.rally-card[data-rally-id="8"] [data-action="rally-join"]').click();
  await page.evaluate(()=>{document.querySelector('#game-dialog').close();releaseRead()});await page.evaluate(()=>Promise.resolve());assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),false,'late detail cannot reopen closed dialog');assert.equal(await page.evaluate(()=>composerCalls.length),beforeDelayed);
  await page.evaluate(()=>rallies.list());await page.evaluate(()=>window.delayReadPath='rally/8');await page.locator('.rally-card[data-rally-id="8"] [data-action="rally-join"]').click();await page.evaluate(()=>{openDialog('<h2>Other window</h2><p id="other-window">Kept</p>');releaseRead()});await page.evaluate(()=>Promise.resolve());assert.equal(await page.locator('#other-window').textContent(),'Kept','late detail cannot replace another view');
  await page.evaluate(()=>{delayReadPath='rally/list';rallies.list();state.city.world_id=2;releaseRead()});await page.evaluate(()=>Promise.resolve());assert.equal(await page.locator('#other-window').textContent(),'Kept','late list cannot replace view after world change');checks++;
  await page.evaluate(()=>{state.city.world_id=1;detailRows[4].rally.launch_at='2030-01-01 13:00:00';rallies.onClick('rally-detail',{dataset:{id:'4'}})});await page.waitForSelector('.rally-detail');
  await page.evaluate(()=>window.delayAction=true);await page.locator('[data-action="rally-cancel"]').click();
  await page.evaluate(()=>{openDialog('<h2>Other window</h2><p id="other-window">Kept</p>');releaseAction()});await page.evaluate(()=>Promise.resolve());
  assert.equal(await page.locator('#other-window').textContent(),'Kept','late leader action cannot reopen the rally list over another view');checks++;
  await page.setViewportSize({width:320,height:568});
  await page.evaluate(()=>{listRows=[{...listRows[0],launch_at:'2030-01-01 13:00:00',participants:detailRows[1].participants,participant_count:12}];});
  for(const locale of ['en','de','fr']){
   await page.evaluate(locale=>{ConquerLocale.setLocale(locale);rallies.list();},locale);await page.waitForSelector('.rally-team-preview');
   await page.waitForFunction(()=>[...document.querySelectorAll('.rally-browser img')].every(img=>img.complete&&img.naturalWidth>0));
   await page.locator('.rally-join-button').scrollIntoViewIfNeeded();await reachable(page,'.rally-join-button',locale+' narrow populated rally join',44);
   const footer=await page.locator('.rally-team-preview').evaluate(element=>{
    const bounds=node=>{const r=node.getBoundingClientRect();return {left:r.left,top:r.top,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
    const button=element.querySelector('.rally-join-button'),style=getComputedStyle(button);
    const light=value=>{const c=value.match(/[\d.]+/g).slice(0,3).map(n=>{n=Number(n)/255;return n<=.04045?n/12.92:Math.pow((n+.055)/1.055,2.4)});return c[0]*.2126+c[1]*.7152+c[2]*.0722;};
    const ink=light(style.color),backgrounds=style.backgroundImage.match(/rgba?\([^)]+\)/g)||[style.backgroundColor];
    const avatarRects=[...element.querySelector('.rally-team-avatars').children].map(bounds);
    const avatars={left:Math.min(...avatarRects.map(r=>r.left)),top:Math.min(...avatarRects.map(r=>r.top)),right:Math.max(...avatarRects.map(r=>r.right)),bottom:Math.max(...avatarRects.map(r=>r.bottom))};
    return {join:bounds(button),avatars,count:bounds(element.querySelector('.rally-member-count')),
     texts:[button.textContent,element.querySelector('.rally-member-count').textContent],overflow:element.scrollWidth>element.clientWidth+1,
     contrast:Math.min(...backgrounds.map(value=>{const bg=light(value);return (Math.max(ink,bg)+.05)/(Math.min(ink,bg)+.05);} ))};
   });
   assert.equal(footer.overflow,false,locale+' populated footer fits narrow card');
   const separate=(a,b)=>a.right<=b.left+1||b.right<=a.left+1||a.bottom<=b.top+1||b.bottom<=a.top+1;
   assert(separate(footer.join,footer.avatars)&&separate(footer.join,footer.count)&&separate(footer.avatars,footer.count),locale+' populated footer elements do not overlap '+JSON.stringify(footer));
   assert(footer.contrast>=4.5,locale+' readable Join label '+JSON.stringify(footer));
   localizedFooters.push({locale,...footer});await page.screenshot({path:path.join(out,'320x568-list-'+locale+'.png')});checks++;
  }
  for(const locale of ['en','de','fr']){
   await page.evaluate(locale=>{ConquerLocale.setLocale(locale);detailRows[1].rally.launch_at='2030-01-01 13:00:00';detailRows[1].rally.human_capacity_remaining=100;detailRows[1].rally.capacity=400;rallies.onClick('rally-detail',{dataset:{id:'1'}});},locale);
   await page.waitForFunction(name=>document.querySelector('.rally-member[data-player-id="-1"]')?.textContent.includes(name),catalogs[locale]['rally.ai.name']);const ai=page.locator('.rally-member[data-player-id="-1"]');await ai.scrollIntoViewIfNeeded();
   assert((await ai.textContent()).includes(catalogs[locale]['rally.ai.name']));assert((await ai.textContent()).includes(catalogs[locale]['rally.ai.label']));
   assert.equal(await page.locator('.rally-detail-actions [data-action="rally-join"]').isDisabled(),false,'humans can replace AI at full capacity');
   await page.screenshot({path:path.join(out,'320x568-ai-'+locale+'.png')});
  }
  for(const key of ['rally.join.open','rally.join.closed','rally.list_hint','rally.arrival_rule','rally.ai.name','rally.ai.label','rally.ai.hint'])assert.equal(typeof catalogs.en[key],'string','English catalog key '+key);
  assert.deepEqual(errors,[],'no browser exceptions');assert.deepEqual(failures,[],'layout checks');assert.deepEqual(badStatuses,[],'all rally status contrast');
  fs.writeFileSync(path.join(out,'result.json'),JSON.stringify({checks,viewports:5,errors,failures,statusReport,localizedFooters},null,2));console.log('PASS '+checks+' rally window groups. Screenshots: '+out);
 }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1});
