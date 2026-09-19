const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path');
const {pathToFileURL}=require('node:url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');

(async()=>{
  const out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-world-chat-'));
  const root=path.resolve(__dirname,'..');
  const script=pathToFileURL(path.join(root,'assets/js/world-chat.js')).href;
  const css=pathToFileURL(path.join(root,'assets/css/world-chat.css')).href;
  const theme=pathToFileURL(path.join(root,'assets/css/village-theme.css')).href;
  const html=`<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="${css}"><link rel="stylesheet" href="${theme}"></head><body class="mobile-game"><nav id="navigation"><button data-id="chat"><span class="chat-dock-badge" hidden></span></button></nav><aside id="world-chat" class="world-chat" hidden></aside><script src="${script}"></script><script>
  window.calls=[];window.openedReports=[];
  const base={player_id:1,world_id:1,alliance:{id:1,name:'Sonne',tag:'SOL'},world_chat:[
    {id:1,player_id:2,username:'Ekki1992',avatar:'archer',alliance_tag:'SOL',message:'Hallo',created_at:'2026-09-18 10:00:00'},
    {id:2,player_id:3,username:'Freya',avatar:'rider',alliance_tag:'SOL',message:'Wer kommt zum Schrein?',shared_report_id:17,created_at:'2026-09-18 10:01:00'},
    {id:3,player_id:2,username:'Ekki1992',avatar:'archer',alliance_tag:'SOL',message:'Ich bin dabei.',created_at:'2026-09-18 10:02:00'}],alliance_chat:[],private_player:null,private_chat:[]};
  const ctx={esc:v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),date:v=>Date.parse(v.replace(' ','T')+'Z'),getState:()=>({city:{world_id:1}}),navigate:()=>{},openSharedReport:id=>openedReports.push(id),api:async(url,payload)=>{if(payload){calls.push(payload);return {message:'Gesendet'};}if(url.includes('player_id=2'))return {...base,private_player:{id:2,username:'Ekki1992'},private_chat:[{id:4,player_id:2,username:'Ekki1992',message:'Privat hallo',created_at:'2026-09-18 10:03:00'}]};return structuredClone(base);}};
  window.chat=ConquerWorldChat(ctx);chat.update(true);
  </script></body></html>`;
  const file=path.join(out,'fixture.html');fs.writeFileSync(file,html);
  const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(pathToFileURL(file).href);await page.waitForSelector('.world-chat-preview-message');
  assert.equal(await page.locator('.world-chat-preview-message').count(),2,'preview shows exactly the last two messages');
  let box=await page.locator('#world-chat').boundingBox();assert.ok(box.height>=60&&box.height<=90,'preview matches the dock height: '+JSON.stringify(box));
  await page.locator('.world-chat-preview').dispatchEvent('pointerdown',{clientX:280,clientY:30});
  await page.locator('.world-chat-preview').dispatchEvent('pointerup',{clientX:100,clientY:30});
  await page.locator('.world-chat-preview').click();
  assert.equal(await page.locator('[data-chat-preview-channel]').textContent(),'Allianzchat','left swipe selects the next channel');
  assert.equal(await page.locator('.world-chat-window').isVisible(),false,'swiping does not open the full window');
  await page.locator('.world-chat-preview').dispatchEvent('pointerdown',{clientX:100,clientY:30});
  await page.locator('.world-chat-preview').dispatchEvent('pointerup',{clientX:280,clientY:30});
  await page.locator('.world-chat-preview').click();
  assert.equal(await page.locator('[data-chat-preview-channel]').textContent(),'Weltchat','right swipe selects the previous channel');
  await page.locator('.world-chat-preview').click();await page.waitForSelector('.world-chat-window:not([hidden])');
  assert.deepEqual(await page.locator('[data-chat-channel]').allTextContents(),['Weltchat','Allianzchat','Privatchat']);
  assert.equal(await page.locator('.world-chat-message').count(),3);
  assert.equal(await page.locator('.world-chat-avatar').count(),3,'every message shows the sender portrait');
  assert.match(await page.locator('.world-chat-avatar').first().getAttribute('src'),/archer\.png$/);
  await page.locator('[data-chat-mute]').click();assert.equal(await page.locator('[data-chat-mute]').getAttribute('aria-pressed'),'true');
  await page.locator('[data-chat-mute]').click();assert.equal(await page.locator('[data-chat-mute]').getAttribute('aria-pressed'),'false');
  await page.evaluate(()=>chat.openPrivate(2,'Ekki1992'));await page.waitForSelector('[data-chat-channel="private"][aria-pressed="true"]');
  await page.waitForSelector('.world-chat-message');assert.match(await page.locator('.world-chat-message p').last().textContent(),/Privat hallo/);
  await page.locator('#world-chat-message').fill('Geheime Nachricht');await page.locator('.world-chat-compose button').click();await page.waitForFunction(()=>calls.length===1);
  const sent=await page.evaluate(()=>calls[0]);assert.equal(sent.channel,'private');assert.equal(sent.player_id,2);assert.equal(sent.message,'Geheime Nachricht');assert.match(sent.request_id,/^[a-zA-Z0-9_-]{16,80}$/);
  await page.locator('[data-chat-close]').last().click();assert.equal(await page.locator('.world-chat-preview').isVisible(),true);
  await page.setViewportSize({width:740,height:430});box=await page.locator('#world-chat').boundingBox();assert.ok(box.height<=65,'landscape preview stays shallow');
  assert.deepEqual(errors,[]);await browser.close();fs.rmSync(out,{recursive:true,force:true});
  console.log('PASS two-line swipable preview, full chat tabs, per-channel mute, private send and responsive height.');
})().catch(error=>{console.error(error);process.exitCode=1;});
