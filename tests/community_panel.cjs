'use strict';
// Real browser rendering and form contracts against a fixture API, never a player account.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert'),{pathToFileURL}=require('url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-community-panel-'));
const css=[...new Set([...fs.readFileSync(root+'/views/game.php','utf8').matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(m=>m[1]).concat('community-panel'))].map(f=>fs.readFileSync(root+'/assets/css/'+f+'.css','utf8')).join('\n');
const fixture={world_id:1,player_id:1,world_chat:[{id:1,player_id:2,username:'<img src=x onerror="window.xss=1">',message:'<script>window.xss=1</script> & Guten Tag!',shared_report_id:23,created_at:'2026-09-11 12:00:00'}],alliance_chat:[],alliance:{id:1,name:'Die Gemeinschaft',tag:'GEM'},role:'leader',members:[{player_id:1,username:'Anführer',role:'leader',coord_x:50,coord_y:50},{player_id:2,username:'Member Two',role:'member',role_level:1,assignable_roles:['member','veteran','officer','vice_leader'],coord_x:51,coord_y:50},{player_id:3,username:'Member Three',role:'officer',role_level:3,assignable_roles:['member','veteran','officer','vice_leader'],coord_x:52,coord_y:50}],players:[{id:2,username:'Member Two'},{id:3,username:'Member Three'}],mail:[{id:7,sender_id:2,recipient_id:1,sender_name:'Member Two',recipient_name:'Anführer',subject:'Ein Brief <svg onload=window.xss=1>',body:'Hallo!\n<script>window.xss=1</script>',read_at:null,created_at:'2026-09-11 12:00:00'}],unread:1,gifts:[{id:1,title:'Willkommen <img src=x>',message:'Ein Geschenk der Spielleitung.',rewards:{food:5000,gems:100,item_code:1,quantity:2},item_name:'Vorratspaket',delivered_at:'2026-09-11 12:00:00'}],queues:[{id:9,type:'building',label:'farm',finishes_at:'2099-01-01 00:00:00'}],help_requests:[{id:1,player_id:2,username:'Member Two',queue_type:'building',help_count:2,max_helps:30,reduced_seconds:60,finishes_at:'2099-01-01 00:00:00',already_helped:0}],research:{nodes:{ally_food_prod:{code:'ally_food_prod',name:'Gemeinsame Ernte',tree:'economy',level:0,max_level:20,bonus_label:' +{n}% Produktion',bonus_current:0,bonus_next:1,cost_next:{lumber:750,stone:750,gold:750},duration_seconds:900,description:'Ein Prozent mehr Produktion.',in_queue:false}},active_code:null},territory:{structures:[],center_radius:12,outpost_radius:6,outpost_limit:1,outpost_count:0},treasury:{food:5000,lumber:5000,stone:5000,gold:5000},alliances:[{id:2,tag:'NBR',name:'Nachbarallianz'}],treaties:[],proposals:[{id:5,alliance_id:2,target_id:1,alliance_name:'Nachbarallianz',target_name:'Die Gemeinschaft',relation:'nap',expires_at:'2099-01-01 00:00:00'}],shipments:[{id:1,sender_id:1,recipient_id:2,sender_name:'Anführer',recipient_name:'Member Two',resource:'gold',amount:1000,status:'travelling',arrives_at:'2099-01-01 00:00:00'}]};
fs.writeFileSync(out+'/fixture.html',`<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}\nbody{display:block!important;overflow:auto!important;background:#e9dbc1;padding:16px}#content{position:static!important;width:100%;max-width:1100px;margin:auto;min-height:400px;overflow:visible!important;box-sizing:border-box}#game-dialog{max-width:700px;width:90%;max-height:85vh}</style><body><main id="content"></main><dialog id="game-dialog"><div id="dialog-content"></div></dialog><script>${require('./fixtures/isolated_locale.cjs')('de')+'\n'+fs.readFileSync(root+'/assets/js/alliance-ranks.js','utf8')+'\n'+fs.readFileSync(root+'/assets/js/community-panel.js','utf8')}</script><script>
window.fixture=${JSON.stringify(fixture).replaceAll('<' , '\\u003c')};window.calls=[];window.failNext=false;window.messages=[];window.openedReports=[];window.xss=0;
window.currentWorld=1;window.deferNextAction=false;window.loseResponseNext=false;window.receipts=new Map();
async function fixtureApi(path,payload){
    if(payload){
        calls.push({path,payload});
        if(deferNextAction){deferNextAction=false;await new Promise(resolve=>{window.releaseAction=()=>{window.releaseAction=null;resolve();};});}
        if(failNext){failNext=false;throw new Error('Fixture connection interrupted');}
        const {request_id,...operation}=payload,signature=JSON.stringify(operation),previous=receipts.get(request_id);
        if(previous&&previous!==signature)throw new Error('Receipt payload conflict');
        receipts.set(request_id,signature);
        if(loseResponseNext){loseResponseNext=false;throw new Error('Fixture response lost after saving');}
        return {message:'Gespeichert'};
    }
    const requestedWorld=Number(new URLSearchParams(path.split('?')[1]).get('world_id'));
    return {...structuredClone(fixture),world_id:requestedWorld,...(requestedWorld===1?{}:{world_chat:[],alliance_chat:[]})};
}
window.placements=[];window.panel=ConquerCommunity({esc:v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),fmt:n=>Number(n).toLocaleString('de-DE'),date:v=>Date.parse(v.replace(' ','T')+'Z'),countdown:()=>'<span>5 Min.</span>',getState:()=>({city:{world_id:currentWorld}}),api:fixtureApi,toast:m=>messages.push(m),refresh:async()=>{},openSharedReport:id=>openedReports.push(id),beginStructurePlacement:type=>placements.push(type),openDialog:html=>{const d=document.querySelector('#game-dialog');document.querySelector('#dialog-content').innerHTML=html;d.showModal();}});
document.addEventListener('click',e=>{const b=e.target.closest('[data-action]');if(b){if(b.dataset.action==='close-dialog')document.querySelector('#game-dialog').close();else panel.onClick(b.dataset.action,b);}});document.addEventListener('submit',e=>{e.preventDefault();panel.onSubmit(e.target)});panel.render('alliance-tools');</script></body></html>`);
(async()=>{const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});try{
    const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(pathToFileURL(out+'/fixture.html').href);await page.waitForSelector('.community-message');
    assert.equal(await page.evaluate(()=>window.xss),0);assert.equal(await page.locator('.community-message img,.community-message script').count(),0);
    await page.locator('.community-report-link').click();await page.waitForFunction(()=>openedReports[0]===23);
    for(const [width,height]of [[320,568],[390,844],[568,320],[1280,800]]){
        await page.setViewportSize({width,height});for(const tab of ['world','alliance','mail','help','research','buildings','members','diplomacy','shipments']){
            await page.locator('[data-action="community-tab"][data-id="'+tab+'"]').click();
            const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);assert.equal(overflow,false,`${width}×${height} ${tab} has horizontal overflow`);
            assert.equal(await page.evaluate(()=>window.xss),0);assert.ok(await page.locator('.community-heading h2').textContent());
            if((width===1280&&['mail','research'].includes(tab))||(width===390&&['buildings','shipments'].includes(tab)))await page.screenshot({path:out+'/'+width+'-'+tab+'.png',fullPage:true});
        }
    }
    await page.setViewportSize({width:1280,height:800});
    await page.locator('[data-action="community-tab"][data-id="world"]').click();await page.locator('textarea[name="message"]').fill('World draft');
    await page.locator('[data-action="community-tab"][data-id="alliance"]').click();assert.equal(await page.locator('input[name="channel"]').inputValue(),'alliance');await page.locator('textarea[name="message"]').fill('Alliance draft');
    await page.locator('[data-action="community-tab"][data-id="world"]').click();assert.equal(await page.locator('textarea[name="message"]').inputValue(),'World draft');assert.equal(await page.locator('input[name="channel"]').inputValue(),'world');
    await page.evaluate(()=>window.failNext=true);await page.locator('form[data-form="community-chat"] button').click();await page.waitForFunction(()=>messages.includes('Fixture connection interrupted'));await page.locator('form[data-form="community-chat"] button').click();await page.waitForFunction(()=>calls.length===2);
    const retry=await page.evaluate(()=>calls.slice(0,2));assert.equal(retry[0].payload.request_id,retry[1].payload.request_id);assert.equal(retry[1].payload.channel,'world');assert.equal(retry[1].payload.message,'World draft');
    const message=page.locator('textarea[name="message"]'),send=page.locator('form[data-form="community-chat"] button');
    const chatTab=async channel=>{await page.locator('[data-action="community-tab"][data-id="'+channel+'"]').click();assert.equal(await page.locator('input[name="channel"]').inputValue(),channel);};
    await page.waitForFunction(()=>document.querySelector('textarea[name="message"]').value===''&&!document.querySelector('form[data-form="community-chat"] button').disabled);
    await message.fill('Draft saved immediately');
    await page.evaluate(()=>{document.querySelector('#content').replaceChildren();panel.render('alliance-tools');});
    assert.equal(await message.inputValue(),'Draft saved immediately','Leaving the menu without a tab change preserves the draft');

    // Refresh the same state path used by polling while the live composer has focus.
    await page.evaluate(()=>{fixture.world_chat=Array.from({length:30},(_,i)=>({...fixture.world_chat[0],id:i+1,message:'Visible history '+(i+1),shared_report_id:null}));panel.onClick({dataset:{action:'community-reload'}});});
    await page.locator('[data-community-message-id="30"]').waitFor();
    await message.focus();await message.evaluate(el=>el.setSelectionRange(3,8));
    const originalComposer=await message.elementHandle();
    const anchor=await page.locator('.community-chat-log').evaluate(log=>{log.scrollTop=160;const top=log.getBoundingClientRect().top,row=[...log.querySelectorAll('[data-community-message-id]')].find(el=>el.getBoundingClientRect().bottom>top);return {id:row.dataset.communityMessageId,offset:row.getBoundingClientRect().top-top,scrollable:log.scrollHeight>log.clientHeight};});
    assert.equal(anchor.scrollable,true,'The polling fixture actually contains a scrollable message history');
    await page.evaluate(()=>{fixture.world_chat.shift();fixture.world_chat.push({...fixture.world_chat[0],id:31,message:'Arrived while typing'});document.querySelector('#content').dataset.dirty='true';panel.onClick({dataset:{action:'community-reload'}});});
    await page.locator('[data-community-message-id="31"]').waitFor();
    assert.equal(await originalComposer.evaluate(el=>el.isConnected&&document.activeElement===el&&el.selectionStart===3&&el.selectionEnd===8),true,'Polling preserves the composer node, focus and selection');
    assert.equal(await message.inputValue(),'Draft saved immediately');
    const anchorAfter=await page.locator('[data-community-message-id="'+anchor.id+'"]').evaluate(el=>el.getBoundingClientRect().top-el.parentElement.getBoundingClientRect().top);
    assert.ok(Math.abs(anchorAfter-anchor.offset)<=2,'Polling keeps the visible message anchored when an older row leaves the window');
    await page.locator('.community-chat-log').evaluate(log=>{log.scrollTop=log.scrollHeight;});
    await page.evaluate(()=>{fixture.world_chat.push({...fixture.world_chat[0],id:32,message:'Follow at the end'});panel.onClick({dataset:{action:'community-reload'}});});
    await page.locator('[data-community-message-id="32"]').waitFor();
    assert.equal(await page.locator('.community-chat-log').evaluate(log=>log.scrollHeight-log.clientHeight-log.scrollTop<2),true,'A reader at the end follows new messages');

    await message.fill('Pending channel draft');await page.evaluate(()=>{deferNextAction=true;failNext=true;});await send.click();await page.waitForFunction(()=>typeof releaseAction==='function');
    const pendingReceipt=await page.evaluate(()=>calls.at(-1).payload.request_id);
    await chatTab('alliance');assert.equal(await message.inputValue(),'Alliance draft');await chatTab('world');
    assert.equal(await message.inputValue(),'Pending channel draft','Disabled in-flight form fields do not overwrite the saved draft');
    await page.evaluate(()=>releaseAction());await page.waitForFunction(()=>document.querySelector('.community-form-error').textContent==='Fixture connection interrupted');
    assert.equal(await page.locator('form[data-form="community-chat"]').getAttribute('data-request-id'),pendingReceipt,'The remounted form retains the failed operation receipt');
    await send.click();await page.waitForFunction(()=>document.querySelector('textarea[name="message"]').value==='');
    assert.equal(await page.evaluate(()=>calls.at(-1).payload.request_id),pendingReceipt,'Unchanged retry after tab switching keeps its receipt');

    await message.fill('Saved before lost response');await page.evaluate(()=>{loseResponseNext=true;});await send.click();await page.waitForFunction(()=>document.querySelector('.community-form-error').textContent==='Fixture response lost after saving');
    const lostReceipt=await page.evaluate(()=>calls.at(-1).payload.request_id);
    await message.fill('Edited message is a new operation');assert.equal(await page.locator('form[data-form="community-chat"]').getAttribute('data-request-id'),null);
    await send.click();await page.waitForFunction(()=>document.querySelector('textarea[name="message"]').value==='');
    assert.notEqual(await page.evaluate(()=>calls.at(-1).payload.request_id),lostReceipt,'Edited payload never reuses an already stored receipt');

    await message.fill('Confirmed after remount');await page.evaluate(()=>{deferNextAction=true;});await send.click();await page.waitForFunction(()=>typeof releaseAction==='function');
    await chatTab('alliance');await chatTab('world');await page.evaluate(()=>releaseAction());await page.waitForFunction(()=>document.querySelector('textarea[name="message"]').value==='');
    await page.evaluate(()=>{document.querySelector('#content').replaceChildren();panel.render('alliance-tools');});assert.equal(await message.inputValue(),'','Confirmed text stays cleared after reopening');

    await message.fill('Original pending payload');await page.evaluate(()=>{deferNextAction=true;});await send.click();await page.waitForFunction(()=>typeof releaseAction==='function');
    await chatTab('alliance');await chatTab('world');await message.fill('New draft while the old send finishes');
    await page.evaluate(async()=>{releaseAction();await new Promise(resolve=>setTimeout(resolve,0));});
    assert.equal(await message.inputValue(),'New draft while the old send finishes','An older success never clears an edited remounted draft');
    await send.click();await page.waitForFunction(()=>document.querySelector('textarea[name="message"]').value==='');

    await message.fill('First world draft');await page.evaluate(()=>{failNext=true;});await send.click();await page.waitForFunction(()=>document.querySelector('.community-form-error').textContent==='Fixture connection interrupted');
    const firstWorldReceipt=await page.evaluate(()=>calls.at(-1).payload.request_id);
    await page.evaluate(()=>{currentWorld=2;panel.render('alliance-tools');});await page.locator('form[data-community-world="2"]').waitFor();
    assert.equal(await message.inputValue(),'','A different world never inherits the text');assert.equal(await page.locator('form[data-form="community-chat"]').getAttribute('data-request-id'),null,'A different world never inherits the receipt');
    await message.fill('Second world draft');await page.evaluate(()=>{currentWorld=1;panel.render('alliance-tools');});await page.locator('form[data-community-world="1"]').waitFor();
    assert.equal(await message.inputValue(),'First world draft');assert.equal(await page.locator('form[data-form="community-chat"]').getAttribute('data-request-id'),firstWorldReceipt);
    await send.click();await page.waitForFunction(()=>document.querySelector('textarea[name="message"]').value==='');assert.equal(await page.evaluate(()=>calls.at(-1).payload.world_id),1);
    await page.locator('[data-action="community-tab"][data-id="members"]').click();await page.locator('form[data-id="2"] select[name="role"]').selectOption('veteran');await page.locator('form[data-id="3"] select[name="role"]').selectOption('member');
    await page.locator('[data-action="community-tab"][data-id="help"]').click();await page.locator('[data-action="community-tab"][data-id="members"]').click();
    assert.equal(await page.locator('form[data-id="2"] input[name="player_id"]').inputValue(),'2');assert.equal(await page.locator('form[data-id="3"] input[name="player_id"]').inputValue(),'3');assert.equal(await page.locator('form[data-id="2"] select[name="role"]').inputValue(),'veteran');assert.equal(await page.locator('form[data-id="3"] select[name="role"]').inputValue(),'member');
    await page.locator('form[data-id="2"] button').click();await page.waitForFunction(()=>calls.some(c=>c.payload.action==='alliance.role'));const role=await page.evaluate(()=>calls.find(c=>c.payload.action==='alliance.role').payload);assert.equal(role.player_id,2);assert.equal(role.role,'veteran');
    await page.locator('[data-action="community-tab"][data-id="mail"]').click();await page.locator('[data-action="community-mail-open"]').click();await page.waitForFunction(()=>calls.some(c=>c.payload.action==='mail.read'));assert.ok((await page.locator('.community-letter').textContent()).includes('<script>'));assert.equal(await page.locator('.community-letter script').count(),0);assert.equal(await page.evaluate(()=>window.xss),0);
    await page.locator('[data-action="community-mail-reply"]').click();assert.equal(await page.locator('select[name="player_id"]').inputValue(),'2');assert.ok((await page.locator('input[name="subject"]').inputValue()).startsWith('Re: Ein Brief'));
    await page.locator('[data-action="community-tab"][data-id="shipments"]').click();await page.locator('select[name="player_id"]').selectOption('2');await page.locator('select[name="resource"]').selectOption('gold');await page.locator('input[name="amount"]').fill('750');await page.locator('form[data-form="community-shipment"] button').click();await page.waitForFunction(()=>calls.some(c=>c.payload.action==='shipment.send'));const shipment=await page.evaluate(()=>calls.find(c=>c.payload.action==='shipment.send').payload);assert.equal(shipment.player_id,2);assert.equal(shipment.amount,750);assert.equal(shipment.resource,'gold');assert.equal(shipment.world_id,1);assert.ok(shipment.request_id.length>=16);
    await page.locator('[data-action="community-tab"][data-id="buildings"]').click();assert.equal(await page.locator('input[name="coord_x"],input[name="coord_y"]').count(),0);await page.locator('select[name="structure_type"]').selectOption('center');await page.locator('[data-action="community-structure-place"]').click();await page.waitForFunction(()=>placements[0]==='center');
    assert.deepEqual(errors,[]);console.log('PASS 36 responsive community layouts, alliance buildings, XSS escaping, private letters/admin gifts, world-scoped live drafts, polling focus/selection/scroll, pending-send remounts, edited retry receipts, role targets and shipment payloads. '+out);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
