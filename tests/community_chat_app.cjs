/* Contract fixture for chat UX, server cursors and asynchronous world changes. */
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os');
const {pathToFileURL}=require('node:url');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const isolatedLocale=require('./fixtures/isolated_locale.cjs');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/community-upgrade');

async function assertTouch(page,selector,width,height,label){
    const control=page.locator(selector).first();await control.scrollIntoViewIfNeeded();
    // A short conversation pane has a floating Latest button at its lower edge.
    // Read/check a message control at the top of that pane, as a player can by scrolling.
    await control.evaluate(async e=>{const log=e.closest('.world-chat-log');if(log){log.scrollTop+=e.getBoundingClientRect().top-log.getBoundingClientRect().top-2;await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));}});
    const box=await control.boundingBox();
    const hit=await control.evaluate(e=>{
        const rect=node=>{if(!node)return null;const b=node.getBoundingClientRect();return{x:b.x,y:b.y,width:b.width,height:b.height,right:b.right,bottom:b.bottom,scrollTop:node.scrollTop,scrollHeight:node.scrollHeight,clientHeight:node.clientHeight};};
        const describe=node=>node?{tag:node.tagName,id:node.id,class:node.className,text:node.textContent?.trim().slice(0,140),bounds:rect(node)}:null;
        const b=e.getBoundingClientRect(),x=b.x+b.width/2,y=b.y+b.height/2,top=document.elementFromPoint(x,y);
        return{reachable:top===e||e.contains(top),point:{x,y},control:describe(e),hit:describe(top),stack:document.elementsFromPoint(x,y).slice(0,8).map(describe),log:rect(document.querySelector('.world-chat-log')),latest:describe(document.querySelector('[data-chat-latest]')),privateList:rect(document.querySelector('[data-chat-private-list]'))};
    });
    const fits=box&&box.width>=44&&box.height>=44&&box.x>=-1&&box.y>=-1&&box.x+box.width<=width+1&&box.y+box.height<=height+1;
    let evidence='';
    if(!fits||!hit.reachable){
        const name='touch-failure-'+label.replace(/[^a-zA-Z0-9_-]+/g,'-');
        evidence=path.join(out,name+'.json');fs.writeFileSync(evidence,JSON.stringify({label,selector,viewport:{width,height},box,...hit},null,2));
        await page.screenshot({path:path.join(out,name+'.png')});
    }
    assert.ok(fits,`${label}: touch control fits ${width}x${height}: ${JSON.stringify(box)}; evidence: ${evidence}`);
    assert.equal(hit.reachable,true,`${label}: center is not covered; hit=${JSON.stringify(hit.hit)}; log=${JSON.stringify(hit.log)}; evidence: ${evidence}`);
}

async function assertMessageFocus(page,id,action){
    await page.waitForFunction(messageId=>window.pendingChatGets===0&&document.querySelector('.world-chat-tools').hidden&&document.activeElement===document.querySelector(`[data-chat-menu="${messageId}"]`),id);
    // Check the settled render as well as the first action response.
    await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
    assert.equal(await page.locator(`[data-chat-menu="${id}"]`).evaluate(e=>document.activeElement===e),true,`${action}: focus survives the message replacement and refresh`);
}

async function assertChannelPresentation(page,channel){
    const value=await page.evaluate(()=>({
        previewChannel:document.querySelector('[data-chat-preview-icon]').dataset.channel,
        activeDots:[...document.querySelectorAll('[data-chat-channel-dot].is-active')].map(e=>e.dataset.chatChannelDot),
        dotOrder:[...document.querySelectorAll('[data-chat-channel-dot]')].map(e=>e.dataset.chatChannelDot),
        activeTabs:[...document.querySelectorAll('[data-chat-channel][aria-pressed="true"]')].map(e=>e.dataset.chatChannel),
        previewImages:document.querySelectorAll('.world-chat-preview-messages img').length,
        previewIcon:document.querySelector('[data-chat-preview-icon] svg')?.innerHTML,
        icons:[...document.querySelectorAll('[data-chat-channel]')].map(e=>({channel:e.dataset.chatChannel,shape:e.querySelector('svg')?.innerHTML,hidden:e.querySelector('svg')?.getAttribute('aria-hidden'),focusable:e.querySelector('svg')?.getAttribute('focusable')}))
    }));
    assert.deepEqual(value.dotOrder,['world','alliance','private'],'pager preserves channel order');
    assert.deepEqual(value.activeDots,[channel],'exactly the selected channel has an active pager dot');
    assert.deepEqual(value.activeTabs,[channel],'tab state agrees with the pager');
    assert.equal(value.previewChannel,channel,'preview emblem follows the selected channel');
    assert.equal(value.icons.length,3);assert.equal(new Set(value.icons.map(icon=>icon.shape)).size,3,'channels have distinct vector symbols');
    for(const icon of value.icons){assert.ok(icon.shape);assert.equal(icon.hidden,'true');assert.equal(icon.focusable,'false','decorative channel symbols do not add focus stops');}
    assert.equal(value.previewIcon,value.icons.find(icon=>icon.channel===channel).shape,'preview and tab use the same channel symbol');
    assert.equal(value.previewImages,0,'compact inline preview has no tiny sender portraits');
}

async function assertTimeline(page,channel,target=0){
    const value=await page.evaluate(({channel,target})=>{
        const rows=[...document.querySelectorAll('.world-chat-log [data-message-id]')].map(el=>({id:Number(el.dataset.messageId),message:el.querySelector('.world-chat-bubble>p')?.textContent,name:el.querySelector('.world-chat-name')?.textContent,mine:el.classList.contains('mine')}));
        const expected=rows.map(row=>window.expectedChatMessage(row.id,channel,target));
        const days=new Map();
        for(const row of expected){if(!row)continue;const date=new Date(row.created_at.replace(' ','T')+'Z');if(!Number.isFinite(date.getTime()))continue;const key=`${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;if(!days.has(key))days.set(key,{day:key,label:ConquerLocale.formatDate(date,{year:'numeric',month:'long',day:'numeric'}),before:row.id});}
        const separators=[...document.querySelectorAll('.world-chat-log .world-chat-day-separator')].map(el=>({day:el.querySelector('time')?.getAttribute('datetime'),label:el.textContent,before:Number(el.nextElementSibling?.dataset.messageId)}));
        return{rows,expected,separators,expectedDays:[...days.values()]};
    },{channel,target});
    const ids=value.rows.map(row=>row.id);
    assert.deepEqual(ids,[...ids].sort((a,b)=>a-b),'day grouping preserves ascending server message order');
    assert.equal(new Set(ids).size,ids.length,'no duplicate message after grouping or refresh');
    value.rows.forEach((row,index)=>{
        const expected=value.expected[index];assert.ok(expected,'every displayed message has a server-fixture source');
        assert.equal(row.message,expected.message,'message '+row.id+' remains byte-for-byte unchanged');
        assert.equal(row.name,(channel==='world'&&expected.alliance_tag?'['+expected.alliance_tag+'] ':'')+expected.username,'sender names remain unchanged');
        assert.equal(row.mine,Number(expected.player_id)===1,'own-message alignment is based on sender identity');
    });
    assert.deepEqual(value.separators,value.expectedDays,'each local calendar day has one localized separator before its first message');
    assert.equal(new Set(value.separators.map(day=>day.day)).size,value.separators.length,'a day is never repeated');
    return {rows:value.rows,separators:value.separators};
}

async function assertMessageStack(page,width,height,suffix){
    // These four private fixture messages cover own/received and short/long copy.
    for(const id of [9,10,11,12]){
        const selector=`[data-message-id="${id}"]`,message=page.locator(selector);
        await message.locator('.world-chat-avatar-button').scrollIntoViewIfNeeded();
        const layout=await message.evaluate(el=>{
            const rect=node=>{const r=node.getBoundingClientRect();return{left:r.left,top:r.top,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
            const name=el.querySelector('.world-chat-name'),avatar=el.querySelector('.world-chat-avatar-button'),bubble=el.querySelector('.world-chat-bubble'),menu=el.querySelector('.world-chat-message-menu');
            return{avatar:rect(avatar),name:rect(name),bubble:rect(bubble),menu:rect(menu),nameInteractive:name.matches('button,a,[role="button"],[data-chat-profile],[tabindex]')};
        });
        const label=`${suffix} message ${id}`;
        assert.equal(layout.nameInteractive,false,label+': sender name adds no undersized interactive target');
        assert.ok(Math.abs(layout.avatar.top-layout.name.top)<=3,label+': avatar starts at sender-name height: '+JSON.stringify(layout));
        assert.ok(layout.avatar.bottom-layout.bubble.top>=8,label+': avatar extends alongside the bubble instead of sitting only beside the header: '+JSON.stringify(layout));
        for(const [name,box]of [['avatar',layout.avatar],['menu',layout.menu]])assert.ok(box.right<=layout.bubble.left+1||layout.bubble.right<=box.left+1,label+': '+name+' does not overlap the bubble horizontally');
        await assertTouch(page,selector+' .world-chat-avatar-button',width,height,label+' profile');
        await assertTouch(page,selector+' .world-chat-message-menu',width,height,label+' actions');
    }
}

async function collapsedChannels(page){
    await assertChannelPresentation(page,'world');
    const preview=page.locator('.world-chat-preview');
    for(const channel of ['alliance','private','world']){
        await preview.dispatchEvent('pointerdown',{clientX:260,clientY:30});await preview.dispatchEvent('pointerup',{clientX:90,clientY:30});await preview.dispatchEvent('click');
        await assertChannelPresentation(page,channel);assert.equal(await page.locator('.world-chat-window').isVisible(),false,'channel swipe does not open the chat');
    }
    assert.equal(await page.evaluate(()=>calls.filter(c=>c.action==='chat.read').length),0,'changing a collapsed preview consumes no read cursor');
    const messages=await page.locator('.world-chat-preview-message').evaluateAll(els=>els.map(el=>{const name=el.querySelector('.world-chat-preview-name'),text=el.querySelector('.world-chat-preview-text'),a=name.getBoundingClientRect(),b=text.getBoundingClientRect();return{name:name.textContent,text:text.textContent,inline:a.width>0&&b.width>0&&a.right<=b.left+1&&Math.max(a.top,b.top)<Math.min(a.bottom,b.bottom)};}));
    const expected=await page.evaluate(()=>[99,100].map(id=>expectedChatMessage(id,'world',0)));
    assert.deepEqual(messages.map(row=>row.text),expected.map(row=>row.message),'inline preview preserves the last two message texts');
    assert.deepEqual(messages.map(row=>row.name),expected.map(row=>'['+row.alliance_tag+'] '+row.username+':'),'inline preview preserves sender labels');
    assert.ok(messages.every(row=>row.inline),'sender and message share the same inline preview flow');
    await page.screenshot({path:path.join(out,'chat-inline-preview-390x844.png')});
    // The product suppresses the click generated by a swipe for 350 ms.
    // Let that deliberate guard expire before the existing real open-button click.
    await page.waitForTimeout(360);
}

async function privateReadingPositions(page){
    await page.setViewportSize({width:390,height:844});
    await page.evaluate(()=>{window.longPrivateHistory=true;chat.openPrivate(2,'Freya');});
    await page.waitForSelector('[data-message-id="2050"]');
    const log=page.locator('.world-chat-log');
    assert.ok(await log.evaluate(e=>e.scrollHeight-e.clientHeight)>1200,'private A contains a genuinely scrollable conversation');
    const a=await log.evaluate(e=>{e.scrollTop=240;return e.scrollTop;});
    await page.locator('[data-chat-private="3"]').click();await page.waitForSelector('[data-message-id="3050"]');
    const b=await log.evaluate(e=>{e.scrollTop=720;return e.scrollTop;});
    assert.ok(b>a+200,'private conversations use distinct reading positions');
    await page.locator('[data-chat-private="2"]').click();await page.waitForSelector('[data-message-id="2050"]');
    await page.waitForFunction(top=>Math.abs(document.querySelector('.world-chat-log').scrollTop-top)<=2,a);
    assert.equal(await page.locator('[data-message-id="3050"]').count(),0,'returning to A restores A messages, not B');

    // B responds only after the player has already returned to A.
    await page.evaluate(()=>{window.resolveDeferred=null;window.deferNext=true;chat.openPrivate(3,'Morgan');});
    await page.waitForFunction(()=>typeof window.resolveDeferred==='function');
    await page.locator('[data-chat-private="2"]').click();
    await page.evaluate(()=>window.resolveDeferred());
    await page.waitForFunction(()=>pendingChatGets===0&&document.querySelector('[data-message-id="2050"]')&&!document.querySelector('[data-message-id="3050"]'));
    assert.ok(Math.abs(await log.evaluate(e=>e.scrollTop)-a)<=2,'late B response cannot replace A reading position');
    assert.equal(await page.locator('[data-chat-heading-title]').textContent(),'Freya','late B response cannot replace the selected recipient');
    await page.locator('[data-chat-private="3"]').click();await page.waitForSelector('[data-message-id="3050"]');
    await page.waitForFunction(top=>Math.abs(document.querySelector('.world-chat-log').scrollTop-top)<=2,b);
    await page.locator('[data-chat-private="2"]').click();await page.waitForSelector('[data-message-id="2050"]');
    await page.waitForFunction(top=>Math.abs(document.querySelector('.world-chat-log').scrollTop-top)<=2,a);

    await page.evaluate(()=>{window.longPrivateHistory=false;return chat.refresh();});
    await page.waitForSelector('[data-message-id="12"]');
    assert.equal(await page.locator('[data-message-id="2050"]').count(),0,'short private fixture is restored after the regression case');
    await page.evaluate(()=>chat.openChannel('world'));await page.waitForSelector('[data-message-id="100"]');
}

async function responsiveChat(page,width,height,locale='en'){
    await page.setViewportSize({width,height});await page.evaluate(()=>chat.openChannel('world'));
    await page.waitForSelector('[data-message-id="100"]');
    if(await page.locator('[data-chat-latest]').isVisible())await page.locator('[data-chat-latest]').click();
    await page.evaluate(()=>document.fonts.ready);
    await assertChannelPresentation(page,'world');await assertTimeline(page,'world');
    const suffix=`${locale==='en'?'':locale+'-'}${width}x${height}`;
    const bounds=await page.locator('.world-chat-window').boundingBox();
    assert.ok(bounds.x>=-1&&bounds.y>=-1&&bounds.x+bounds.width<=width+1&&bounds.y+bounds.height<=height+1,`chat window fits ${suffix}: ${JSON.stringify(bounds)}`);
    assert.ok(await page.locator('.world-chat-window').evaluate(e=>e.scrollWidth-e.clientWidth)<=1,`no horizontal overflow at ${suffix}`);
    const tabs=await page.locator('[data-chat-channel]').evaluateAll(buttons=>buttons.map(button=>{
        const rect=e=>{const b=e.getBoundingClientRect();return {left:b.left,top:b.top,right:b.right,bottom:b.bottom,width:b.width,height:b.height};};
        const label=button.querySelector('.world-chat-tab-label'),badge=button.querySelector('.world-chat-badge');
        return {channel:button.dataset.chatChannel,button:rect(button),label:label?rect(label):null,labelText:label?.textContent,labelScrollWidth:label?.scrollWidth,labelClientWidth:label?.clientWidth,badge:badge&&!badge.hidden?rect(badge):null};
    }));
    for(const tab of tabs){
        assert.ok(tab.label&&tab.label.width>0&&tab.label.height>0,`${suffix}: ${tab.channel} has a visible channel label`);
        assert.ok(tab.labelScrollWidth<=tab.labelClientWidth+1,`${suffix}: ${tab.channel} label is complete, without horizontal clipping: ${JSON.stringify(tab)}`);
        for(const [name,rect] of [['label',tab.label],['badge',tab.badge]])if(rect)assert.ok(rect.left>=tab.button.left-1&&rect.top>=tab.button.top-1&&rect.right<=tab.button.right+1&&rect.bottom<=tab.button.bottom+1,`${suffix}: ${tab.channel} ${name} fits its own button: ${JSON.stringify(tab)}`);
        if(tab.badge)assert.ok(tab.label.right<=tab.badge.left||tab.badge.right<=tab.label.left||tab.label.bottom<=tab.badge.top||tab.badge.bottom<=tab.label.top,`${suffix}: ${tab.channel} label and unread badge do not overlap: ${JSON.stringify(tab)}`);
    }
    const compose=await page.evaluate(()=>{const input=document.querySelector('#world-chat-message').getBoundingClientRect(),send=document.querySelector('.world-chat-compose [type="submit"]').getBoundingClientRect();return {inputRight:input.right,sendLeft:send.left};});
    assert.ok(compose.inputRight<=compose.sendLeft,`message input does not overlap Send at ${suffix}`);
    for(const selector of ['[data-chat-channel="world"]','[data-chat-channel="alliance"]','[data-chat-channel="private"]','[data-chat-close]:visible','.world-chat-compose [type="submit"]'])await assertTouch(page,selector,width,height,`${suffix} ${selector}`);
    await page.screenshot({path:path.join(out,`chat-conversation-${suffix}.png`)});

    await page.locator('[data-chat-menu="100"]').click();
    assert.equal(await page.locator('.world-chat-log').evaluate(e=>e.inert),true,'message actions make the covered timeline inert');
    for(const selector of ['[data-chat-close]:visible','.world-chat-compose [type="submit"]','.world-chat-tools [data-chat-reply]','[data-chat-panel-close]'])await assertTouch(page,selector,width,height,`${suffix} ${selector}`);
    await page.screenshot({path:path.join(out,`chat-${suffix}.png`)});
    await page.keyboard.press('Escape');
    await page.waitForSelector('.world-chat-tools[hidden]',{state:'attached'});
    assert.equal(await page.locator('.world-chat-log').evaluate(e=>e.inert),false,'closing actions restores the timeline');
    assert.equal(await page.locator('[data-chat-menu="100"]').evaluate(e=>document.activeElement===e),true,'Escape restores message action focus');

    await page.locator('[data-chat-mute]').click();
    assert.equal(await page.locator('.world-chat-log').evaluate(e=>e.inert),true,'notification overlay isolates the covered timeline');
    for(const selector of ['[data-chat-mode="world"]','[data-chat-mode="alliance"]','[data-chat-mode="private"]','[data-chat-save-settings]','[data-chat-panel-close]'])await assertTouch(page,selector,width,height,`${suffix} ${selector}`);
    await page.screenshot({path:path.join(out,`chat-notifications-${suffix}.png`)});
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('[data-chat-mute]').evaluate(e=>document.activeElement===e),true,'Escape returns to notification settings origin');

    await page.locator('[data-chat-menu="100"]').click();await page.locator('[data-chat-reply]').click();
    assert.equal(await page.locator('[data-chat-draft-context]').isVisible(),true,'reply context remains visible above the composer');
    await assertTouch(page,'[data-chat-clear-reply]',width,height,`${suffix} cancel reply`);
    await assertTouch(page,'.world-chat-compose [type="submit"]',width,height,`${suffix} reply Send`);
    await page.screenshot({path:path.join(out,`chat-reply-${suffix}.png`)});
    await page.locator('[data-chat-clear-reply]').click();

    await page.evaluate(()=>chat.openPrivate(3,'Morgan'));await page.waitForSelector('[data-message-id="12"]');
    await assertChannelPresentation(page,'private');
    const privateTimeline=await assertTimeline(page,'private',3);
    assert.equal(privateTimeline.rows.filter(row=>row.mine).length,2,'private design fixture includes two own messages');
    assert.equal(privateTimeline.rows.filter(row=>!row.mine).length,2,'private design fixture includes two received messages');
    await assertMessageStack(page,width,height,suffix);
    if(locale==='en'&&width===390&&height===844)await page.locator('[data-message-id="12"]').screenshot({path:path.join(out,'chat-aligned-message.png')});
    assert.equal(await page.locator('[data-chat-heading-title]').textContent(),'Morgan','private heading names the actual recipient');
    assert.equal(await page.locator('[data-chat-private="3"]').getAttribute('aria-pressed'),'true','active conversation remains selected');
    for(const selector of ['[data-chat-private="2"]','[data-chat-private="3"]'])await assertTouch(page,selector,width,height,`${suffix} ${selector}`);
    await page.locator('[data-chat-private="3"]').focus();
    await page.evaluate(()=>{window.savedConversationCard=document.querySelector('[data-chat-private="3"]');window.savedConversationScroll=document.querySelector('[data-chat-private-list]').scrollLeft;});
    await page.evaluate(()=>chat.refresh());
    assert.deepEqual(await assertTimeline(page,'private',3),privateTimeline,'polling preserves message order, text and unique day separators');
    assert.equal(await page.evaluate(()=>savedConversationCard===document.querySelector('[data-chat-private="3"]')&&document.activeElement===savedConversationCard),true,'unchanged polling preserves the conversation card and keyboard focus');
    assert.ok(await page.evaluate(()=>Math.abs(savedConversationScroll-document.querySelector('[data-chat-private-list]').scrollLeft)<=1),'unchanged polling preserves conversation strip position');
    await page.screenshot({path:path.join(out,`chat-private-${suffix}.png`)});
    await page.evaluate(()=>chat.openChannel('alliance'));
    await page.waitForSelector('[data-message-id="100"] .alliance-rank-badge');
    assert.equal(await page.locator('[data-message-id="99"] .alliance-rank-badge').textContent(),'R1','received alliance message uses its sender rank');
    assert.equal(await page.locator('[data-message-id="100"] .alliance-rank-badge').textContent(),'R4','received deputy is distinct from the R3 viewer');
    assert.equal(await page.locator('[data-message-id="97"] .alliance-rank-badge').textContent(),'R3','own alliance message uses its sender rank');
    for(const id of [97,99,100]){
        const row=page.locator(`[data-message-id="${id}"]`);
        await row.locator('.world-chat-avatar-button').scrollIntoViewIfNeeded();
        const fits=await row.evaluate(el=>{
            const a=el.querySelector('.world-chat-avatar-button').getBoundingClientRect(),b=el.querySelector('.alliance-rank-badge').getBoundingClientRect(),n=el.querySelector('.world-chat-name').getBoundingClientRect();
            return b.left>=a.left-1&&b.right<=a.right+1&&b.top>=a.top-1&&b.bottom<=a.bottom+1&&Math.abs(a.top-n.top)<=3;
        });
        assert.equal(fits,true,suffix+': badge stays within avatar and preserves name alignment');
        await assertTouch(page,`[data-message-id="${id}"] .world-chat-avatar-button`,width,height,suffix+' ranked profile '+id);
    }
    await page.screenshot({path:path.join(out,`chat-alliance-ranks-${suffix}.png`)});
    await page.evaluate(()=>chat.openChannel('world'));
}

async function allianceRankHistory(browser,file,errors){
    const page=await browser.newPage({viewport:{width:390,height:844}});page.on('pageerror',e=>errors.push(e.message));
    try{
        await page.goto(pathToFileURL(file).href);await page.waitForSelector('.world-chat-preview-message');
        await page.evaluate(()=>document.fonts.ready);
        await page.evaluate(()=>chat.openChannel('alliance'));await page.waitForFunction(()=>pendingChatGets===0);
        assert.equal(await page.locator('[data-message-id="99"] [data-alliance-rank]').textContent(),'R1','older fixtures without a complete list retain valid sender ranks');
        await page.evaluate(async()=>{window.allianceMemberRoles=[{player_id:1,role:'officer'},{player_id:2,role:'member'},{player_id:3,role:'vice_leader'},{player_id:4,role:'member'}];await chat.refresh();window.deferHistory=true;});
        await page.locator('[data-chat-older]').click();await page.waitForFunction(()=>typeof resolveDeferred==='function');
        await page.evaluate(async()=>{allianceMemberRoles.find(m=>m.player_id===4).role='officer';await chat.refresh();});
        await page.evaluate(()=>resolveDeferred());await page.waitForSelector('[data-message-id="1"] [data-alliance-rank="3"]');
        assert.equal(await page.locator('[data-message-id="1"] [data-chat-profile="4"]').count(),1,'history-only author preserves their profile target');
        assert.equal(await page.locator('[data-message-id="51"] ~ [data-message-id] [data-chat-profile="4"]').count(),0,'the changed author has no message in the live snapshot');
        const before=await page.locator('.world-chat-log').evaluate(log=>{log.scrollTop=200;return{scroll:log.scrollTop,messages:[...log.querySelectorAll('[data-message-id]')].map(m=>({id:m.dataset.messageId,name:m.querySelector('.world-chat-name').textContent,text:m.querySelector('.world-chat-bubble>p').textContent}))};});
        const unchanged=async label=>{
            const after=await page.locator('.world-chat-log').evaluate(log=>({scroll:log.scrollTop,messages:[...log.querySelectorAll('[data-message-id]')].map(m=>({id:m.dataset.messageId,name:m.querySelector('.world-chat-name').textContent,text:m.querySelector('.world-chat-bubble>p').textContent}))}));
            assert.deepEqual(after.messages,before.messages,label+': text, author and order are preserved');
            assert.ok(Math.abs(after.scroll-before.scroll)<=2,label+': reading position is preserved');
        };
        await page.evaluate(async()=>{allianceMemberRoles.find(m=>m.player_id===4).role='vice_leader';await chat.refresh();});
        await page.waitForSelector('[data-message-id="1"] [data-alliance-rank="4"]');await unchanged('history-only promotion');
        await page.evaluate(async()=>{window.allianceMemberRoles=allianceMemberRoles.filter(m=>m.player_id!==4);await chat.refresh();});
        assert.equal(await page.locator('[data-message-id="1"] [data-alliance-rank]').count(),0,'a departed history-only author loses their badge on the next live snapshot');await unchanged('history-only departure');
        await page.evaluate(async()=>{window.allianceMemberRoles=[];await chat.refresh();});
        assert.equal(await page.locator('.world-chat-log [data-alliance-rank]').count(),0,'an authoritative empty list clears all cached and live badges');await unchanged('empty current membership list');
    }finally{await page.close();}
}

function fixture(){
    window.calls=[];window.queries=[];window.profiles=[];window.reports=[];window.locations=[];window.currentWorld=1;
    window.opens=0;window.closes=0;window.deferNext=false;window.failSend=false;window.longPrivateHistory=false;window.pendingChatGets=0;
    const empty={cursors:{},prefs:{world:'all',alliance:'all',private:'all'},blocked:false,receipts:{},sent:[],reactions:{},pins:[]};
    let server=JSON.parse(localStorage.getItem('chat-fixture-server')||'null')||empty;
    const save=()=>localStorage.setItem('chat-fixture-server',JSON.stringify(server));
    const row=(id,channel='world')=>({id,player_id:[97,98].includes(id)?1:id%2?2:3,username:[97,98].includes(id)?'You':id%2?'Freya <script>':'Morgan',avatar:[97,98].includes(id)?'knight':id%2?'archer':'rider',alliance_tag:'UOK',alliance_role:[97,98].includes(id)?'officer':id%2?'member':'vice_leader',message:id===99?'<img src=x onerror=alert(1)>':id===97?'Ready at the gate.':id===98?'I will bring the infantry. Please wait at the northern gate while the scouts check the road. Tue · Forschung stays exactly as written.':id===100?'The scouts found a clear path along the river. We can meet at the old bridge and travel together when everyone is ready.':channel+' message '+id,created_at:id<=50?'2026-09-29 10:00:00':'2026-09-30 10:00:00',reply_to:id===98?{id:97,player_id:2,username:'Freya',message:'Earlier message'}:null,reactions:server.reactions[channel+':'+id]||[],pinned:server.pins.includes(id),mentions:[],...(id===97?{shared_report_id:17}:{}),...(id===96?{message:'Orc · Welt 1 · X 14 / Y 27'}:{})});
    const privateRows=target=>[9,10,11,12].map((id,index)=>({...row(id,'private'),player_id:index%2?target:1,username:index%2?(target===2?'Freya':'Morgan'):'You',avatar:index%2?'rider':'knight',created_at:index<2?'2026-09-29 10:00:00':'2026-09-30 10:00:00',message:['See you soon.','I am gathering stone near the eastern forest. Send me a message when your troops return and we can plan the next rally.','My troops are on their way home. I will keep a place for you; Tue, Helfen and Forschung are player text, not interface labels.','private message 12'][index]}));
    const rows=(w,channel,target)=>channel==='private'?(window.longPrivateHistory?Array.from({length:50},(_,i)=>({...row(target*1000+i+1,'private'),player_id:target,username:target===2?'Freya':'Morgan',message:`Private conversation ${target}, entry ${i+1}`})):privateRows(target)):w===2?[{...row(201,channel),message:'Second world only'}]:[...Array.from({length:50},(_,i)=>row(i+51,channel)),...server.sent.filter(m=>m.channel===channel)].slice(-50);
    window.expectedChatMessage=(id,channel,target)=>structuredClone(channel==='private'?rows(window.currentWorld,channel,target).find(message=>Number(message.id)===Number(id)):server.sent.find(message=>message.channel===channel&&Number(message.id)===Number(id))||Array.from({length:100},(_,i)=>row(i+1,channel)).find(message=>Number(message.id)===Number(id)));
    // A real server change, observed through refresh(), rather than DOM injection.
    window.receiveMessage=(channel='world')=>{const id=101+server.sent.length;server.sent.push({...row(id,channel),channel,player_id:3,username:'Morgan',message:'New arrival '+id});save();return id;};
    const state=(w,target)=>({player_id:1,world_id:w,alliance:{id:w,name:'Union',tag:'UOK'},role:'officer',world_chat:rows(w,'world'),alliance_chat:rows(w,'alliance'),private_player:target?{id:target,username:target===2?'Freya':'Morgan'}:null,private_chat:target?rows(w,'private',target):[],private_blocked:server.blocked,can_send_private:!server.blocked,
        conversations:w===1?[{player_id:2,username:'Freya',avatar:'archer',last_message:'Saved conversation',last_at:'2026-09-30 10:00:00',unread:server.cursors['1:private:2']>=12?0:1},{player_id:3,username:'Morgan',last_message:'Another conversation',unread:1}]:[],
        preferences:{private_messages:'everyone',channels:server.prefs},read_cursors:{world:server.cursors[w+':world']||0,alliance:server.cursors[w+':alliance']||0,private:server.cursors[w+':private:'+target]||0},channel_unread:{world:server.cursors[w+':world']>=100?0:5,alliance:server.cursors[w+':alliance']>=100?0:2,private:server.cursors['1:private:2']>=12?1:2},pins:server.pins.map(id=>row(id,'alliance')),...(Array.isArray(window.allianceMemberRoles)?{alliance_member_roles:structuredClone(window.allianceMemberRoles)}:{})});
    const api=async(url,payload)=>{
        if(!payload){window.pendingChatGets++;try{window.queries.push(url);const q=new URLSearchParams(url.split('?')[1]),w=Number(q.get('world_id')),target=Number(q.get('player_id'));const result=url.startsWith('community/history')?{messages:Array.from({length:50},(_,i)=>({...row(i+1,q.get('channel')),...(i===0&&q.get('channel')==='alliance'?{player_id:4,username:'History-only member',alliance_role:'member'}:{})})),has_more:false,next_before_id:1}:state(w,target);
            if(window.deferNext||(window.deferHistory&&url.startsWith('community/history'))){window.deferNext=false;window.deferHistory=false;await new Promise(resolve=>window.resolveDeferred=()=>{window.resolveDeferred=null;resolve();});}return structuredClone(result);}finally{window.pendingChatGets--;}}
        window.calls.push(structuredClone(payload));
        if(server.receipts[payload.request_id])return server.receipts[payload.request_id];
        if(payload.action==='chat.read')server.cursors[payload.world_id+':'+payload.channel+(payload.channel==='private'?':'+payload.player_id:'')]=payload.message_id;
        if(payload.action==='preferences.save')server.prefs=payload.channels;
        if(payload.action==='block.add')server.blocked=true;
        if(payload.action==='chat.react')server.reactions[payload.channel+':'+payload.message_id]=payload.active?[{reaction:payload.reaction,count:1,mine:true}]:[];
        if(payload.action==='chat.pin')server.pins=payload.active?[payload.message_id]:[];
        if(payload.action==='chat.send')server.sent.push({...row(101+server.sent.length,payload.channel),channel:payload.channel,player_id:1,username:'You',message:payload.message,reply_to:payload.reply_to_id?row(payload.reply_to_id,payload.channel):null,mentions:(payload.mention_ids||[]).map(id=>({player_id:id,username:id===2?'Freya':'Morgan'}))});
        server.receipts[payload.request_id]={message:'OK',result:['chat.react','chat.pin'].includes(payload.action)?{chat_message:row(payload.message_id,payload.channel)}:{}};save();
        if(payload.action==='chat.send'&&window.failSend){window.failSend=false;throw new Error('Connection lost after saving. Try again.');}
        return server.receipts[payload.request_id];
    };
    window.chat=ConquerWorldChat({api,esc:value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),date:value=>Date.parse(value.replace(' ','T')+'Z'),base:window.fixtureBase,getState:()=>({city:{world_id:window.currentWorld},world:{map_profile:{width:256,height:256}}}),openPublicProfile:id=>window.profiles.push(id),openSharedReport:id=>window.reports.push(id),openSharedLocation:location=>window.locations.push(location),onOpen:()=>window.opens++,onClose:()=>window.closes++});
    chat.update(true);
}

(async()=>{
    fs.mkdirSync(out,{recursive:true});const temp=fs.mkdtempSync(path.join(os.tmpdir(),'uok-social-chat-'));
    const fixtureFile=locale=>{
        const file=path.join(temp,`fixture-${locale}.html`);
        fs.writeFileSync(file,`<!doctype html><html lang="${locale}"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="${pathToFileURL(path.join(root,'assets/css/fantasy-fonts.css'))}"><link rel="stylesheet" href="${pathToFileURL(path.join(root,'assets/css/world-chat.css'))}"><link rel="stylesheet" href="${pathToFileURL(path.join(root,'assets/css/village-theme.css'))}"><style>body{margin:0}.world-chat{box-sizing:border-box}#navigation{position:fixed;bottom:0}</style><body class="mobile-game"><nav id="navigation"><button data-id="chat"><span class="chat-dock-badge" hidden></span></button></nav><aside id="world-chat" class="world-chat" hidden></aside><script>${isolatedLocale(locale)}</script><script>window.fixtureBase=${JSON.stringify(pathToFileURL(root).href)};</script><script src="${pathToFileURL(path.join(root,'assets/js/alliance-ranks.js'))}"></script><script src="${pathToFileURL(path.join(root,'assets/js/world-chat.js'))}"></script><script>(${fixture.toString()})();</script></body></html>`);
        return file;
    };
    const file=fixtureFile('en');
    const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
    try{
        const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
        await page.goto(pathToFileURL(file).href);await page.waitForSelector('.world-chat-preview-message');
        await page.evaluate(()=>document.fonts.ready);
        assert.equal(await page.evaluate(()=>ConquerLocale.locale),'en','the real shared catalogue runs in English');
        assert.equal(await page.evaluate(()=>document.fonts.check('14px "Conquer UI"','World 123')),true,'chat uses loaded game fonts');
        assert.equal(await page.locator('.world-chat-preview-message').count(),2);
        assert.equal(await page.evaluate(()=>calls.filter(c=>c.action==='chat.read').length),0,'collapsed preview never consumes unread messages');
        await collapsedChannels(page);
        await page.locator('[data-chat-open]').click();await page.waitForFunction(()=>calls.some(c=>c.action==='chat.read'&&c.message_id===100));
        const firstTimeline=await assertTimeline(page,'world');assert.equal(firstTimeline.separators.length,1,'one current day is shown before history is loaded');
        assert.equal(firstTimeline.rows.filter(row=>[97,98].includes(row.id)&&row.mine).length,2,'world design fixture includes two own messages');
        assert.equal(firstTimeline.rows.filter(row=>[99,100].includes(row.id)&&!row.mine).length,2,'world design fixture includes two received messages');
        await page.locator('[data-chat-channel="private"]').click();await page.waitForSelector('[data-chat-private="2"]');
        assert.match(await page.locator('[data-chat-private="2"]').textContent(),/Saved conversation/,'server restores conversation list without opening a profile');
        await page.locator('[data-chat-private="2"]').click();await page.waitForSelector('[data-message-id="12"]');
        await page.waitForFunction(()=>calls.some(c=>c.action==='chat.read'&&c.player_id===2&&c.message_id===12));
        await page.reload();await page.waitForSelector('.world-chat-preview-message');await page.evaluate(()=>chat.openChannel('private'));await page.waitForSelector('[data-chat-private="2"]');
        assert.equal(await page.locator('[data-chat-private="2"] b').count(),0,'read cursor persists over reload');
        await page.evaluate(()=>chat.openChannel('world'));await page.waitForSelector('[data-message-id="100"]');
        assert.equal(await page.locator('.world-chat-bubble img[src="x"]').count(),0,'player content is escaped');
        const submit=page.locator('.world-chat-compose [type="submit"]');
        assert.equal(await submit.isDisabled(),true,'empty messages cannot be sent');
        assert.deepEqual((await page.locator('[data-chat-count]').textContent()).match(/\d+/g),['0','200']);
        await page.locator('#world-chat-message').fill('World draft');
        assert.equal(await submit.isEnabled(),true);
        assert.deepEqual((await page.locator('[data-chat-count]').textContent()).match(/\d+/g),['11','200']);
        await page.locator('[data-chat-channel="alliance"]').click();
        await page.locator('#world-chat-message').fill('Alliance draft');
        await page.locator('[data-chat-channel="world"]').click();
        assert.equal(await page.locator('#world-chat-message').inputValue(),'World draft','channel changes preserve the composer draft');
        await page.locator('#world-chat-message').fill('   ');assert.equal(await submit.isDisabled(),true,'whitespace alone cannot be sent');
        await page.locator('#world-chat-message').fill('');
        await page.evaluate(()=>{document.querySelector('.world-chat-log').scrollTop=200;});
        await page.waitForSelector('[data-chat-latest]:not([hidden])');
        const rememberedScroll=await page.locator('.world-chat-log').evaluate(e=>e.scrollTop);
        await page.locator('[data-chat-channel="alliance"]').click();
        await page.locator('[data-chat-channel="world"]').click();
        await page.waitForFunction(expected=>Math.abs(document.querySelector('.world-chat-log').scrollTop-expected)<=2,rememberedScroll);
        await page.evaluate(()=>chat.close());await page.evaluate(()=>chat.open());
        await page.waitForFunction(expected=>Math.abs(document.querySelector('.world-chat-log').scrollTop-expected)<=2,rememberedScroll);
        const incoming=await page.evaluate(()=>{const id=receiveMessage();chat.refresh();return id;});
        await page.waitForSelector(`[data-message-id="${incoming}"]`);
        assert.ok(Math.abs(await page.locator('.world-chat-log').evaluate(e=>e.scrollTop)-rememberedScroll)<=2,'arriving messages do not move the reader');
        assert.match(await page.locator('[data-chat-latest]').textContent(),/1/,'new message indicator reports the arrival while reading history');
        assert.equal(await page.evaluate(id=>calls.some(c=>c.action==='chat.read'&&c.channel==='world'&&c.message_id===id),incoming),false,'unseen arrival is not marked read');
        await page.locator('[data-chat-latest]').click();
        await page.waitForFunction(id=>calls.some(c=>c.action==='chat.read'&&c.channel==='world'&&c.message_id===id),incoming);
        await page.waitForSelector('[data-chat-latest][hidden]',{state:'attached'});
        await page.locator('[data-chat-older]').click();await page.waitForSelector('[data-message-id="1"]');assert.equal(await page.locator('.world-chat-message').count(),100,'history prepends without replacing latest rows');
        const historyTimeline=await assertTimeline(page,'world');assert.equal(historyTimeline.separators.length,2,'older history adds its earlier day exactly once');
        await page.evaluate(()=>chat.refresh());assert.deepEqual(await assertTimeline(page,'world'),historyTimeline,'refresh after prepending history preserves dates, order and player text');
        await page.locator('[data-chat-menu="1"]').click();await page.locator('.world-chat-tools [data-chat-react="like"]').click();await page.waitForSelector('[data-message-id="1"] [data-chat-react="like"][aria-pressed="true"]');
        await assertMessageFocus(page,1,'chat.react like');
        await page.locator('[data-chat-menu="99"]').click();await page.locator('[data-chat-reply]').click();
        await page.locator('[data-chat-mentions]').click();await page.locator('[data-chat-mention="2"]').check();await page.locator('[data-chat-panel-close]').click();
        await page.locator('#world-chat-message').fill('Meet at the gate');await page.evaluate(()=>window.failSend=true);await page.locator('.world-chat-compose [type="submit"]').click();
        await page.waitForSelector('.world-chat-error:not([hidden])');assert.equal(await page.locator('#world-chat-message').inputValue(),'Meet at the gate');
        await page.locator('.world-chat-compose [type="submit"]').click();await page.waitForFunction(()=>calls.filter(c=>c.action==='chat.send').length===2);
        const sends=await page.evaluate(()=>calls.filter(c=>c.action==='chat.send'));assert.equal(sends[0].request_id,sends[1].request_id,'lost-response retry preserves idempotency');assert.equal(sends[1].reply_to_id,99);assert.deepEqual(sends[1].mention_ids,[2]);
        await page.waitForFunction(()=>document.querySelector('#world-chat-message').value==='');
        await page.locator('[data-chat-menu="100"]').click();await page.locator('.world-chat-tools [data-chat-react="heart"]').click();await page.waitForSelector('[data-message-id="100"] [data-chat-react="heart"][aria-pressed="true"]');
        await assertMessageFocus(page,100,'chat.react heart');
        await page.locator('[data-chat-mute]').click();await page.locator('[data-chat-mode="world"]').selectOption('mentions');await page.locator('[data-chat-mode="private"]').selectOption('off');await page.locator('[data-chat-save-settings]').click();await page.waitForFunction(()=>calls.some(c=>c.action==='preferences.save'&&c.channels.world==='mentions'&&c.channels.private==='off'));
        await page.evaluate(()=>chat.openChannel('alliance'));await page.waitForSelector('[data-message-id="100"]');await page.locator('[data-chat-menu="100"]').click();await page.locator('[data-chat-pin]').click();await page.waitForSelector('[data-chat-pins]:not([hidden])');
        await assertMessageFocus(page,100,'chat.pin');
        await page.locator('[data-chat-menu="99"]').click();await page.locator('[data-chat-report-open]').click();await page.locator('[data-chat-reason]').selectOption('spam');await page.locator('[data-chat-report-details]').fill('Repeated spam');await page.locator('[data-chat-report-send]').click();await page.waitForFunction(()=>calls.some(c=>c.action==='report.submit'&&c.reason==='spam'&&c.details==='Repeated spam'));
        await page.locator('[data-chat-menu="99"]').click();await page.locator('[data-chat-block-open]').click();await page.locator('[data-chat-block-confirm]').click();await page.waitForFunction(()=>calls.some(c=>c.action==='block.add'&&c.player_id===2));
        await page.evaluate(()=>chat.openPrivate(2,'Freya'));await page.waitForSelector('[data-message-id="12"]');assert.equal(await page.locator('#world-chat-message').isDisabled(),true,'blocked conversation remains readable but cannot send');
        const blockedNotice=page.locator('.world-chat-error');await blockedNotice.waitFor({state:'visible'});
        assert.equal(await blockedNotice.textContent(),await page.evaluate(()=>ConquerLocale.t('social_chat.private_blocked')),'blocked private conversation explains the disabled composer with the exact localized status');
        await page.evaluate(()=>chat.openChannel('world'));await page.waitForSelector('[data-message-id="100"]');
        await blockedNotice.waitFor({state:'hidden'});assert.equal(await blockedNotice.textContent(),'','private blocked status is removed when returning to an unrestricted world channel');
        const closesBeforeProfile=await page.evaluate(()=>closes);
        await page.locator('[data-message-id="100"] .world-chat-avatar-button').click();assert.equal(await page.evaluate(()=>profiles.at(-1)),3);assert.equal(await page.evaluate(()=>closes),closesBeforeProfile,'navigation close avoids duplicate mobile back callback');
        await page.evaluate(()=>chat.openChannel('world'));await page.locator('[data-chat-report="17"]').click();assert.equal(await page.evaluate(()=>reports.at(-1)),17);
        await page.locator('[data-chat-location]').click();assert.deepEqual(await page.evaluate(()=>locations.at(-1)),{world:1,x:14,y:27});
        await page.evaluate(()=>chat.openChannel('world'));
        for(const [width,height] of [[1280,850],[390,844],[320,568],[740,360],[568,320]])await responsiveChat(page,width,height);
        await privateReadingPositions(page);
        await page.evaluate(()=>{window.deferNext=true;chat.refresh();});await page.waitForFunction(()=>typeof window.resolveDeferred==='function');
        await page.evaluate(()=>{window.currentWorld=2;chat.update(true);window.resolveDeferred();});await page.waitForSelector('[data-message-id="201"]');assert.equal(await page.locator('[data-message-id="100"]').count(),0,'late old-world response is discarded');
        const french=await browser.newPage({viewport:{width:320,height:568}});french.on('pageerror',e=>errors.push(e.message));
        await french.goto(pathToFileURL(fixtureFile('fr')).href);await french.waitForSelector('.world-chat-preview-message');
        assert.equal(await french.evaluate(()=>ConquerLocale.locale),'fr');
        for(const [width,height] of [[320,568],[568,320]])await responsiveChat(french,width,height,'fr');
        await french.close();
        await allianceRankHistory(browser,file,errors);
        assert.deepEqual(errors,[]);console.log('PASS persisted conversations/read cursors, per-channel drafts and reading positions, private A/B restoration with a delayed response, latest-message arrivals, history, safe quotes/mentions, reactions/pins with settled focus, reports, blocks, synced preferences, send retries, profile/report/location navigation, world races, five EN layouts and two FR layouts with real fonts, non-overlapping channel badges, overlays, reply and private conversations.');
    }finally{await browser.close();fs.rmSync(temp,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exitCode=1;});
