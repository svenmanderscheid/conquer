'use strict';
// Real main-app acceptance against an isolated database; only the final Deploy writes.
const fs = require('node:fs'), path = require('node:path'), net = require('node:net');
const assert = require('node:assert/strict'), {spawn} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const output = path.resolve(process.env.RALLY_JOIN_OUTPUT || path.join(root, 'artifacts/rally-joining'));
const report = {viewports: [], art: [], writes: [], unexpected_writes: [], browser_errors: [], response_errors: []};
const selectedTroops = {'50100101': 11, '50200101': 17, '50300101': 23};
fs.mkdirSync(output, {recursive: true});

async function data(page, base, endpoint) {
    const response = await page.request.get(base + '/api/' + endpoint);
    assert.equal(response.status(), 200, endpoint + ' responds successfully');
    const payload = await response.json();
    assert.equal(payload.ok, true, endpoint + ' returns game data');
    return payload.data;
}
async function reachable(locator, label, minimum = 44) {
    const geometry = await locator.evaluate(element => {
        const r = element.getBoundingClientRect();
        return {x:r.x, y:r.y, width:r.width, height:r.height, right:r.right, bottom:r.bottom,
            screen:[innerWidth,innerHeight], hit:element.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2))};
    });
    assert.ok(geometry.width >= minimum - 1 && geometry.height >= minimum - 1, label + ' has a touch target: ' + JSON.stringify(geometry));
    assert.ok(geometry.x >= -1 && geometry.y >= -1 && geometry.right <= geometry.screen[0] + 1 && geometry.bottom <= geometry.screen[1] + 1, label + ' stays visible: ' + JSON.stringify(geometry));
    assert.equal(geometry.hit, true, label + ' receives its center tap');
    return geometry;
}
async function fits(page, selector) {
    const bounds = await page.locator(selector).evaluate(element => {
        const r=element.getBoundingClientRect();
        return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:innerWidth,height:innerHeight,overflow:element.scrollWidth>element.clientWidth+2};
    });
    assert.ok(bounds.left >= -1 && bounds.right <= bounds.width + 1 && bounds.top >= -1 && bounds.bottom <= bounds.height + 1 && !bounds.overflow, selector + ' fits: ' + JSON.stringify(bounds));
}
async function images(page) {
    await page.waitForFunction(() => [...document.querySelectorAll('#game-dialog img')].every(image => image.complete));
    assert.deepEqual(await page.locator('#game-dialog img').evaluateAll(elements => elements.filter(element => !element.naturalWidth).map(element => element.src)), [], 'Dialog images load');
}
async function targetPresentation(locator, label) {
    const bounds = await locator.evaluate(element => {
        const rectangle = node => {const r=node.getBoundingClientRect();return {left:r.left,top:r.top,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
        return {viewport:[innerWidth,innerHeight],art:rectangle(element.querySelector('.rally-target-art img')),copy:rectangle(element.querySelector('.rally-target-copy')),
            overflow:element.scrollWidth>element.clientWidth+2};
    });
    assert.equal(bounds.overflow,false,label+' has no horizontal overflow');
    assert.ok(bounds.art.width>=80 && bounds.art.height>=80,label+' gives the target illustration a readable size: '+JSON.stringify(bounds));
    assert.ok(bounds.art.right<=bounds.copy.left+1,label+' keeps illustration left of its information without overlap: '+JSON.stringify(bounds));
    report.art.push({label,...bounds});
    return bounds;
}
async function close(page) {
    const desktopClose=page.locator('#game-dialog .dialog-close:visible,#game-dialog [data-action=close-dialog]:visible').first();
    if(await desktopClose.isVisible())await desktopClose.click();
    else {
        if(await page.locator('.rally-detail').isVisible()) {
            await page.locator('#game-dialog .mobile-page-back:visible').click();await page.locator('.rally-browser').waitFor();
        }
        await page.locator('#game-dialog .mobile-page-back:visible').click();
    }
    await page.locator('#game-dialog').waitFor({state:'hidden'});
    await page.waitForFunction(() => history.state?.conquerMobilePage?.overlay !== 'dialog');
}
async function chooseTroops(page) {
    await page.locator('[data-action=march-clear]').click();
    for (const [code, count] of Object.entries(selectedTroops)) await page.locator('#march-unit-' + code).fill(String(count));
    await page.waitForFunction(() => document.querySelector('#march-selected')?.textContent === '51');
}
async function openList(page, rallyId, count) {
    await reachable(page.locator('#hud-alliance-rallies'), 'Map rally menu');
    assert.equal(await page.locator('#hud-rally-count').textContent(), String(count));
    await page.locator('#hud-alliance-rallies').click();
    await page.locator('.rally-browser').waitFor();
    assert.equal(await page.locator('.rally-card[data-rally-id]').count(), count, 'All active alliance rallies appear');
    const card = page.locator('.rally-card[data-rally-id="' + rallyId + '"]');
    await card.scrollIntoViewIfNeeded();
    return card;
}
async function details(page, rallyId, participants) {
    await page.locator('.rally-card[data-rally-id="' + rallyId + '"] .rally-card-open').click();
    await page.locator('.rally-detail').waitFor();
    await images(page);
    const overview=await targetPresentation(page.locator('.rally-matchup--detail'),'Detail overview');
    if(participants===2)await page.screenshot({path:path.join(output,overview.viewport.join('x')+'-details-overview.png')});
    assert.equal(await page.locator('details.rally-member').count(), participants, 'Rally roster shows each participant');
    const leader = page.locator('details.rally-member').filter({hasText:'RallyCaptain'});
    assert.equal(await leader.getAttribute('open'), '', 'Captain troops are initially expanded');
    assert.ok((await leader.locator('.rally-troop strong').allTextContents()).includes('123'), 'Captain infantry count is real');
    const companion = page.locator('details.rally-member').filter({hasText:'ScoutCompanion'});
    await companion.locator('summary').scrollIntoViewIfNeeded();
    await companion.locator('summary').click();
    assert.equal(await companion.getAttribute('open'), '', 'Participant row expands with touch');
    assert.deepEqual((await companion.locator('.rally-troop strong').allTextContents()).sort(), ['29', '51']);
    await companion.locator('.rally-member-troops').scrollIntoViewIfNeeded();
    assert.equal(await companion.locator('.rally-member-troops').isVisible(),true,'Expanded troop details can be viewed');
    await fits(page, '#game-dialog');
    await images(page);
}

(async () => {
    const port = await new Promise(resolve => {const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
    const fixture = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [path.join(root,'tools/preview-feature-fixture.php'),'--appearance','--rally-joining','--port='+port], {cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
    const base='http://127.0.0.1:'+port;
    let browser, page, fixtureLog='', allowDeploy=false;
    try {
        await new Promise((resolve,reject)=>{
            const timer=setTimeout(()=>reject(Error('Preview did not start: '+fixtureLog)),90000);
            fixture.stdout.on('data',chunk=>{fixtureLog+=chunk;if(fixtureLog.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});
            fixture.stderr.on('data',chunk=>fixtureLog+=chunk);
            fixture.once('exit',()=>{clearTimeout(timer);reject(Error(fixtureLog));});
            fixture.once('error',error=>{clearTimeout(timer);reject(error);});
        });
        browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
        const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true,locale:'de-DE'});
        await context.route('**/api/**',async route=>{
            const request=route.request(),endpoint=new URL(request.url()).pathname;
            if (!['GET','HEAD','OPTIONS'].includes(request.method())) {
                const write={method:request.method(),endpoint,payload:request.postDataJSON()};
                report.writes.push(write);
                if (!(endpoint==='/api/march/preview' || (allowDeploy && endpoint==='/api/rally/join'))) {
                    report.unexpected_writes.push(write);await route.abort('blockedbyclient');return;
                }
            }
            await route.continue();
        });
        page=await context.newPage();page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
        page.on('pageerror',error=>report.browser_errors.push(error.message));
        page.on('response',response=>{if(response.status()>=400&&response.url().startsWith(base))report.response_errors.push({url:response.url(),status:response.status()});});
        await page.goto(base+'/?zugang=login');
        await page.locator('[name=identifier]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
        await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
        await page.locator('.painted-village').waitFor();await page.evaluate(()=>ConquerLocale.ready);
        assert.equal(await page.locator('html').getAttribute('lang'),'en','English remains default with a German browser');
        assert.equal(await page.locator('#hud-alliance-rallies').isVisible(),false,'Rally menu stays on the world map');
        const initial=await data(page,base,'game/state'), rallies=(await data(page,base,'rally/list')).rallies;
        assert.equal(rallies.length,2,'Fixture supplies two active ally rallies');
        const rally=rallies.find(row=>Number(row.leader_player_id)===2);assert.ok(rally);
        assert.equal(rally.participants.length,1);assert.equal(rally.participants[0].status,'pending');
        await page.locator('#navigation [data-id=world]').click();await page.locator('.atlas-shell').waitFor();
        for (const [width,height] of [[1280,800],[390,844],[320,568],[568,320]]) {
            const label=width+'x'+height;await page.setViewportSize({width,height});
            const card=await openList(page,rally.id,rallies.length);
            assert.match(await card.textContent(),/RallyCaptain/,'The rally card identifies its captain');
            await targetPresentation(card,label+' rally card');
            const overlay=await card.locator('.rally-card-open').evaluate(element=>{const style=getComputedStyle(element);return {color:style.backgroundColor,image:style.backgroundImage};});
            assert.equal(overlay.image,'none','The full-card click target has no opaque button gradient');
            assert.ok(overlay.color==='rgba(0, 0, 0, 0)'||/\/ 0\.0[0-9]\)$/.test(overlay.color),'The card click target has at most a subtle transparent hover tint: '+overlay.color);
            const join=card.locator('[data-action=rally-join]');
            await join.scrollIntoViewIfNeeded();
            await reachable(join, label+' green plus');assert.match(await join.textContent(),/\+/);
            assert.ok(await join.getAttribute('aria-label'),'Join plus has an accessible name');
            assert.equal(await join.evaluate(element=>{const style=getComputedStyle(element),color=(style.backgroundImage.match(/rgba?\([^)]+\)/)||[])[0]||style.backgroundColor,numbers=color.match(/[\d.]+/g).map(Number);return numbers[1]>numbers[0]&&numbers[1]>numbers[2];}),true,'Join uses the shared green action color');
            await fits(page,'#game-dialog');await images(page);
            await page.locator('.rally-list').evaluate(element=>element.scrollTop=0);
            await page.screenshot({path:path.join(output,label+'-list.png')});
            await join.click();await page.locator('.march-command.is-rally-join').waitFor();
            await chooseTroops(page);await reachable(page.locator('#march-confirm'),label+' Deploy');
            assert.match(await page.locator('#march-confirm').textContent(),/Deploy/);
            assert.equal(await page.locator('#march-confirm').isEnabled(),true);
            assert.equal(await page.locator('.march-target h3').textContent(),'RallyCaptain','Contribution targets the host');
            await fits(page,'#game-dialog');await images(page);
            await page.locator('.march-unit-list').evaluate(element=>element.scrollTop=0);
            await page.screenshot({path:path.join(output,label+'-troops.png')});
            await page.locator('[data-action=march-rally-back]').click();await page.locator('.rally-detail').waitFor();
            await page.locator('.rally-detail [data-action=rally-list]').click();await page.locator('.rally-browser').waitFor();
            await details(page,rally.id,2);
            await page.locator('.rally-detail-scroll').evaluate(element=>element.scrollTop=0);
            await page.screenshot({path:path.join(output,label+'-details.png')});
            await close(page);
            const unchanged=await data(page,base,'game/state');
            assert.deepEqual(unchanged.troops,initial.troops,'Browsing and choosing troops never reserves an army');
            assert.equal(report.writes.filter(write=>write.endpoint==='/api/rally/join').length,0,'Only explicit Deploy can join');
            report.viewports.push({width,height,passed:true});console.log('PASS '+label+': map menu, both rallies, plus, shared troop selection, back, expandable roster.');
        }
        await page.setViewportSize({width:320,height:568});
        const card=await openList(page,rally.id,rallies.length);await card.locator('[data-action=rally-join]').click();
        await page.locator('.march-command.is-rally-join').waitFor();await chooseTroops(page);
        const chosenNames=await page.locator('.march-unit-row').evaluateAll((rows,codes)=>rows.filter(row=>codes.includes(row.dataset.unit)).map(row=>row.querySelector('.march-unit-name strong').textContent.trim()).sort(),Object.keys(selectedTroops));
        allowDeploy=true;
        const responsePromise=page.waitForResponse(response=>response.url().endsWith('/api/rally/join')&&response.request().method()==='POST');
        await page.locator('#march-confirm').click();
        const response=await responsePromise;assert.equal(response.status(),200,await response.text());assert.equal((await response.json()).ok,true);
        await page.locator('#game-dialog').waitFor({state:'hidden'});await page.waitForFunction(()=>history.state?.conquerMobilePage?.overlay!=='dialog');
        allowDeploy=false;
        const after=await data(page,base,'game/state'), detail=await data(page,base,'rally/'+rally.id);
        const own=detail.participants.find(row=>Number(row.player_id)===1);assert.ok(own,'Deploy adds the current player');
        assert.deepEqual(own.troops,selectedTroops,'Server roster keeps exactly the selected amounts');
        assert.equal(own.status,'joining','Troops travel to the host first');
        for(const [code,count] of Object.entries(selectedTroops))assert.equal(Number(after.troops[code]),Number(initial.troops[code])-count,'Only the deployed amount leaves the city');
        const route=after.marches.find(row=>row.march_type==='rally_join'&&Number(row.rally_id)===Number(rally.id));assert.ok(route,'Join has a real march route');
        assert.equal(Number(route.target_x),Number(rally.leader.coord_x));assert.equal(Number(route.target_y),Number(rally.leader.coord_y));
        assert.notDeepEqual([Number(route.target_x),Number(route.target_y)],[Number(rally.target_x),Number(rally.target_y)],'Join march goes to the captain before the monster');
        const joinedCard=await openList(page,rally.id,rallies.length);assert.equal(await joinedCard.locator('[data-action=rally-join]').isDisabled(),true,'Joined card cannot deploy twice');
        await details(page,rally.id,3);
        const self=page.locator('details.rally-member').filter({hasText:'PreviewPlayer'});await self.locator('summary').scrollIntoViewIfNeeded();await self.locator('summary').click();
        assert.deepEqual((await self.locator('.rally-troop strong').allTextContents()).sort(),['11','17','23']);
        const rosterNames=(await self.locator('.rally-troop small').allTextContents()).map(name=>name.replace(/\s*·\s*T\d+\s*$/,'').trim()).sort();
        assert.deepEqual(rosterNames,chosenNames,'Roster uses exactly the same troop names as selection');
        assert.match(await self.textContent(),/On the way|Travelling|Traveling/);
        await self.locator('.rally-member-troops').scrollIntoViewIfNeeded();
        await page.screenshot({path:path.join(output,'320x568-deployed.png')});await close(page);
        await page.reload();await page.locator('.atlas-shell').waitFor();
        const persisted=await data(page,base,'rally/'+rally.id);assert.equal(persisted.participants.filter(row=>Number(row.player_id)===1).length,1,'Reload never repeats joining');
        assert.equal(report.writes.filter(write=>write.endpoint==='/api/rally/join').length,1,'Exactly one explicit Deploy request');
        assert.deepEqual(report.unexpected_writes,[]);assert.deepEqual(report.browser_errors,[]);assert.deepEqual(report.response_errors,[]);
        report.deploy={rally_id:rally.id,troops:selectedTroops,troop_names:rosterNames,host:[route.target_x,route.target_y],passed:true};
        console.log('PASS real Deploy: exact troop reservation, march to rally host, live participant roster, and reload without repeat.');
    } catch(error) {
        report.error=error.stack||error.message;
        if(page)await page.screenshot({path:path.join(output,'failure.png')}).catch(()=>{});
        throw error;
    } finally {
        fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(report,null,2));
        if(browser)await browser.close();
        if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
