/* Isolated offer-window QA. Uses local artwork and fake transactions, never a live account. */
'use strict';
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert');
const playwright=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-trading-panel-'));
const definitions=JSON.parse(fs.readFileSync(path.join(root,'data/items.json'),'utf8')).items;
const view=fs.readFileSync(path.join(root,'views/game.php'),'utf8');
const sheets=[...new Set([...view.matchAll(/assets\/css\/([^?"']+)\?/g)].map(m=>m[1]).concat('trading-panel.css'))];
const styles=sheets.map(file=>fs.readFileSync(path.join(root,'assets/css',file),'utf8')).join('\n');
(async()=>{
 const browser=await playwright.chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});
 const errors=[],checks=[];
 try{
  const page=await browser.newPage({viewport:{width:1280,height:720}});page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/*',async route=>{const u=new URL(route.request().url()),file=path.resolve(root,'.'+decodeURIComponent(u.pathname));if(u.origin!=='https://trade.fixture'||!file.startsWith(path.join(root,'assets')+path.sep)||!fs.existsSync(file))return route.abort();return route.fulfill({body:fs.readFileSync(file),contentType:{'.svg':'image/svg+xml','.png':'image/png'}[path.extname(file)]||'application/octet-stream'});});
  await page.setContent('<!doctype html><html lang="de"><head><base href="https://trade.fixture/"></head><body class="mobile-game playfield-mode city-mode"><main id="main"><section id="playfield-content"></section></main><dialog id="panel-dialog" data-panel="market"><div class="page-heading"><h1 id="page-title">Handelsposten</h1><button class="panel-close">×</button></div><section id="content"></section></dialog></body></html>');
  await page.addStyleTag({content:styles});await page.addScriptTag({path:path.join(root,'assets/js/trading-panel.js')});
  await page.evaluate(defs=>{
   window.testNow=Date.now();window.calls=[];window.toasts=[];window.refreshes=0;window.upgrades=0;window.fail=false;window.hold=false;
   const make=(item,n)=>({id:'offer-'+n,item,item_code:item.code,quantity:1,price:{resource:n%2?'gems':'gold',amount:100+n},remaining:10,limit:10,discount:40,vip_level:Math.floor(n/4)+1,locked:n>=12});
   window.K={profile:{gems:1000000},trading:{market_level:15,server_time:new Date(testNow).toISOString(),refresh_at:new Date(testNow+28800000).toISOString(),rotation:'cycle-a',offers:defs.slice(0,15).map((i,n)=>({...make(i,n),locked:false,remaining:1,limit:1})),vip:{level:3,rotation:'week-a',reset_at:new Date(testNow+604800000).toISOString(),offers:defs.slice(0,52).map(make)}}};
   window.S={city:{gold:10000000,food:10000000,lumber:10000000,stone:10000000}};
   window.M={offers:[{id:'food_lumber',name:'Holz für den Aufbau',give:{resource:'food',amount:1000},receive:{resource:'lumber',amount:1000}},{id:'gems_lumber',name:'Bauholz des Kristallhändlers',give:{resource:'gems',amount:20},receive:{resource:'lumber',amount:1100}}],history:[],resources:{...S.city,gems:K.profile.gems},server_time:new Date(testNow).toISOString(),refresh_at:new Date(testNow+86400000).toISOString(),rotation:'merchant-day-a'};
   const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
   window.panel=ConquerTrading({base:'',esc,fmt:v=>Number(v).toLocaleString('de-DE'),getKingdom:()=>K,getState:()=>S,getMarket:()=>M,now:()=>testNow,onUpgrade:()=>upgrades++,toast:m=>toasts.push(m),refresh:()=>refreshes++,action:async(endpoint,payload)=>{
    calls.push({endpoint,payload});if(hold)await new Promise(r=>window.release=r);if(fail)throw new Error('Test: Angebot abgelaufen.');
    const list=payload.mode==='vip'?K.trading.vip:K.trading,o=list.offers.find(o=>o.id===payload.offer_id);o.remaining-=payload.quantity;
    if(o.price.resource==='gems')K.profile.gems-=o.price.amount*payload.quantity;else S.city[o.price.resource]-=o.price.amount*payload.quantity;
    panel.render();
   }});
   document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b)panel.onClick(b.dataset.action,b);});document.querySelector('#panel-dialog').showModal();panel.render();
  },definitions);
  const tab=id=>page.locator(`[data-action="trading-tab"][data-id="${id}"]`);
  const viewFilter=id=>page.locator(`[data-action="trading-view"][data-id="${id}"]`);
  const categoryFilter=id=>page.locator(`[data-action="trading-category"][data-id="${id}"]`);
  const buy=(id,q)=>page.locator(`[data-action="trading-buy"][data-id="offer-${id}"][data-quantity="${q}"]`).first();
  const check=async label=>{
   await page.locator('.trading-shell img').evaluateAll(images=>Promise.all(images.map(i=>{i.loading='eager';return i.decode().catch(()=>{});})));
   assert.deepEqual(await page.locator('.trading-shell img').evaluateAll(images=>images.filter(i=>!i.naturalWidth).map(i=>i.src)),[],label+' broken images');
   const bad=await page.evaluate(()=>{const bad=[],frame=document.querySelector('#panel-dialog').getBoundingClientRect();for(const sel of ['#panel-dialog','#content','.trading-shell','.trading-summary','.trading-tabs']){const e=document.querySelector(sel),r=e.getBoundingClientRect();if(e.scrollWidth>e.clientWidth+2||e.scrollHeight>e.clientHeight+2)bad.push(sel+' overflow');if(r.left<frame.left-2||r.right>frame.right+2||r.top<frame.top-2||r.bottom>frame.bottom+2)bad.push(sel+' outside frame');}if(document.querySelector('.trading-scroll').scrollWidth>document.querySelector('.trading-scroll').clientWidth+1)bad.push('Grid horizontal overflow');if(frame.left<0||frame.right>innerWidth+1||frame.top<0||frame.bottom>innerHeight+1)bad.push('Frame outside screen');return bad;});
   if(bad.length)await page.screenshot({path:path.join(output,'failure.png')});assert.deepEqual(bad,[],label);checks.push(label);
  };
  assert.deepEqual(await page.locator('.shop-tabs [data-action="trading-tab"]').allTextContents(),['Händler','Kristall-Shop','VIP-Shop','Karawane']);checks.push('Shop opens with four direct tabs');
  await tab('crystals').click();assert.equal(await page.locator('.trading-summary h2').innerText(),'Kristall-Shop');
  await tab('merchant').click();assert.equal(await page.locator('.shop-merchant-card').count(),2);assert.equal(await page.locator('.shop-merchant-reward img').count(),2);assert.equal(await page.locator('.shop-merchant-reward strong').first().innerText(),'+1.000 Holz');assert.equal(await page.locator('.shop-merchant-cost strong').first().innerText(),'1.000');assert(await page.locator('.shop-merchant-action.pays-gems').isVisible());assert.equal(await page.locator('[data-trading-countdown]').innerText(),'1d 00:00:00');assert((await page.locator('.trading-footer').innerText()).includes('Nach jedem Kauf'));checks.push('Merchant cards show received material art, replacement rule, daily timer and icon-labelled costs');
  for(const [width,height]of [[1280,720],[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width,height});await tab('merchant').click();await check(width+'x'+height+' merchant');assert.equal(await page.locator('.shop-merchant-card').count(),2);await page.screenshot({path:path.join(output,width+'x'+height+'-merchant.png')});
   await tab('caravan').click();await check(width+'x'+height+' caravan');assert.equal(await page.locator('.trading-card').count(),15);await page.screenshot({path:path.join(output,width+'x'+height+'-caravan.png')});
   await tab('vip').click();await viewFilter('mine').click();await check(width+'x'+height+' personal VIP');assert.equal(await page.locator('.trading-card').count(),12);assert.equal(await page.locator('.trading-level-group').count(),3);await page.screenshot({path:path.join(output,width+'x'+height+'-vip-personal.png')});
   await viewFilter('all').click();await check(width+'x'+height+' all VIP');assert.equal(await page.locator('.trading-card').count(),52);assert.equal(await page.locator('.trading-level-group').count(),13);await page.locator('.trading-card').last().scrollIntoViewIfNeeded();await check(width+'x'+height+' last VIP offer reachable');await page.screenshot({path:path.join(output,width+'x'+height+'-vip-all.png')});
  }
  await page.setViewportSize({width:1280,height:720});await tab('caravan').click();await page.locator('[data-action=trading-upgrade]').click();assert.equal(await page.evaluate(()=>upgrades),1);checks.push('Market level opens upgrade menu');await tab('vip').click();
  await categoryFilter('resources').click();const filtered=await page.locator('.trading-card').count();assert(filtered>0&&filtered<52);assert.equal(await categoryFilter('resources').getAttribute('aria-pressed'),'true');checks.push('VIP category filters shorten the catalogue and expose selection state');await categoryFilter('all').click();
  assert.equal(await buy(1,10).locator('strong').innerText(),'1.010');await buy(1,10).click();await page.waitForFunction(()=>K.trading.vip.offers[1].remaining===0);assert.deepEqual(await page.evaluate(()=>calls.at(-1).payload),{action:'trading.buy',mode:'vip',offer_id:'offer-1',quantity:10,rotation:'week-a'});checks.push('Buy All submits remaining count and displayed total');
  const before=await page.evaluate(()=>calls.length);await page.evaluate(()=>panel.onClick('trading-buy',{dataset:{id:'offer-50',quantity:'1'}}));assert.equal(await page.evaluate(()=>calls.length),before);checks.push('Required VIP blocks click and synthetic action');
  await page.evaluate(()=>{K.profile.gems=0;panel.render();});assert(await buy(3,1).isDisabled());await page.evaluate(()=>panel.onClick('trading-buy',{dataset:{id:'offer-3',quantity:'1'}}));assert.equal(await page.evaluate(()=>calls.length),before);checks.push('Insufficient currency blocks clicks and synthetic action');
  await page.evaluate(()=>{hold=true;fail=true;});await buy(0,1).click();assert(await buy(2,1).isDisabled());await page.evaluate(()=>panel.onClick('trading-buy',{dataset:{id:'offer-2',quantity:'1'}}));assert.equal(await page.evaluate(()=>calls.length),before+1);await page.evaluate(()=>release());await page.waitForFunction(()=>toasts.length===1);assert(!(await buy(0,1).isDisabled()));assert.equal(await page.evaluate(()=>K.trading.vip.offers[0].remaining),10);checks.push('Pending actions cannot duplicate; server errors restore controls without changing stock');
  await page.evaluate(()=>{window.oldCard=document.querySelector('.trading-card');testNow+=1000;panel.updateTime();});assert(await page.evaluate(()=>oldCard===document.querySelector('.trading-card')));checks.push('Countdown updates without rebuilding catalogue');
  await page.evaluate(()=>{testNow+=604800000;panel.updateTime();panel.updateTime();});assert.equal(await page.evaluate(()=>refreshes),1);assert(await buy(0,1).isDisabled());checks.push('Expired offers block purchases and request one refresh');
  assert.deepEqual(errors,[]);console.log(JSON.stringify({checks:checks.length,output},null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);console.error('Screenshots: '+output);process.exitCode=1;});
