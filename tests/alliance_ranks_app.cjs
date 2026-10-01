'use strict';
// Uses an isolated preview supplied by run_alpha.py: --alliance-ranks --hud --appearance
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.ALLIANCE_RANKS_BASE;
const out = process.env.ALLIANCE_RANKS_OUTPUT || path.resolve(__dirname, '../artifacts/alliance-ranks-2026-10-01/app/screenshots');
assert.ok(base, 'An isolated preview URL is required');
fs.mkdirSync(out, {recursive:true});

(async () => {
    const browser = await chromium.launch({headless:true, channel:process.env.PLAYWRIGHT_CHANNEL || 'chrome'});
    const errors = [], failures = [];
    async function login(username) {
        const context = await browser.newContext({viewport:{width:390,height:844}, hasTouch:true, locale:'de-DE'});
        const page = await context.newPage();
        page.setDefaultTimeout(25000);
        page.on('pageerror', e => errors.push(e.message));
        page.on('response', r => { if(r.url().includes('/api/') && r.status()>=500) failures.push(`${r.status()} ${r.url()}`); });
        await page.goto(base+'/?zugang=login', {waitUntil:'domcontentloaded'});
        await page.locator('[name="identifier"], [name="username"]').fill(username);
        await page.locator('[name="password"]').fill('PreviewFixture!2026');
        await Promise.all([page.waitForURL('**/city', {waitUntil:'domcontentloaded'}), page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);
        await page.locator('.painted-village').waitFor();
        return page;
    }
    async function state(page) {
        const response = await page.request.get(base+'/api/community/state?world_id=1');
        assert.equal(response.status(),200);
        return (await response.json()).data;
    }
    async function members(page) {
        const navigation=await page.goto(base+'/city#alliance', {waitUntil:'domcontentloaded'});
        if(!navigation) await page.reload({waitUntil:'domcontentloaded'});
        await page.locator('.alliance-home-tabs [data-id="members"]').click();
        await page.locator('.alliance-home-members').waitFor();
    }
    async function ranks(page) {
        await members(page);
        await page.locator('[data-action="community-open"][data-id="members"]').click();
        await page.locator('.alliance-rank-member').first().waitFor();
    }
    async function change(page, player, role) {
        const form = page.locator(`[data-form="community-role"][data-id="${player}"]`);
        await form.locator('select[name="role"]').selectOption(role);
        const response = page.waitForResponse(r => r.url().includes('/api/community/action') && r.request().method()==='POST' && r.request().postDataJSON()?.action==='alliance.role');
        await form.locator('button[type="submit"],button:not([type])').click();
        assert.equal((await response).status(),200,'Rank change is accepted by the server');
        await page.waitForFunction(({player,role}) => document.querySelector(`[data-form="community-role"][data-id="${player}"] select`)?.value===role && !document.querySelector(`[data-form="community-role"][data-id="${player}"] button`)?.disabled, {player,role});
        const rank={member:1,veteran:2,officer:3,vice_leader:4}[role];
        await page.waitForFunction(({player,rank})=>Number(document.querySelector(`.alliance-rank-member[data-player-id="${player}"] [data-alliance-rank]`)?.dataset.allianceRank)===rank,{player,rank});
        const saved = (await state(page)).members.find(m=>Number(m.player_id)===player);
        assert.equal(saved.role,role,'Rank is persisted, not just changed in the select');
    }
    async function geometry(page, label) {
        const issues = await page.evaluate(() => {
            const result=[];
            for(const selector of ['#panel-dialog','.community-shell','.alliance-home-members']) {
                const el=document.querySelector(selector);
                if(el && el.scrollWidth>el.clientWidth+2) result.push(selector+' overflows horizontally');
            }
            for(const el of document.querySelectorAll('[data-form="community-role"] select,[data-form="community-role"] button')) {
                const r=el.getBoundingClientRect();
                if(r.height<44 || r.width<44) result.push('Rank control smaller than 44px');
            }
            const text=document.querySelector('#panel-dialog').innerText;
            if(/alliance_rank\./.test(text)) result.push('Untranslated rank key');
            return result;
        });
        assert.deepEqual(issues,[],label);
        const control=page.locator('[data-form="community-role"] select').last();
        if(await control.count()) {
            await control.scrollIntoViewIfNeeded();
            assert.equal(await control.evaluate(el=>{const r=el.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return hit===el||el.contains(hit);}),true,label+' last selector remains reachable');
        }
    }
    let leader;
    try {
        leader = await login('PreviewPlayer');
        assert.equal(await leader.locator('html').getAttribute('lang'),'en','English remains default in a German browser');
        const initial=await state(leader);
        assert.deepEqual(initial.members.map(m=>m.role_level).sort(),[1,2,3,4,5]);
        for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320]]) {
            await leader.setViewportSize({width,height});
            await members(leader);
            assert.deepEqual((await leader.locator('.alliance-home-members [data-alliance-rank]').evaluateAll(els=>els.map(el=>Number(el.dataset.allianceRank)))).sort(),[1,2,3,4,5]);
            await geometry(leader,`${width}x${height} member list`);
            await leader.screenshot({path:path.join(out,`members-${width}x${height}.png`)});
            await leader.locator('[data-action="community-open"][data-id="members"]').click();
            await leader.locator('.alliance-rank-member').first().waitFor();
            assert.equal(await leader.locator('[data-form="community-role"]').count(),4);
            assert.deepEqual(await leader.locator('[data-form="community-role"][data-id="2"] option').evaluateAll(els=>els.map(el=>el.value)),['member','veteran','officer','vice_leader']);
            await geometry(leader,`${width}x${height} rank management`);
            await leader.screenshot({path:path.join(out,`ranks-${width}x${height}.png`)});
        }
        await leader.setViewportSize({width:390,height:844});
        for(const role of ['veteran','officer','vice_leader','member']) await change(leader,2,role);
        await ranks(leader);
        assert.equal(await leader.locator('[data-form="community-role"][data-id="2"] select').inputValue(),'member','R1 survives reopening');
        await members(leader);
        await leader.locator('.alliance-home-members [data-action="public-profile"][data-id="2"]').click();
        await leader.locator('#game-dialog .lok-profile').waitFor();
        assert.equal(await leader.locator('#game-dialog [data-alliance-rank="1"]').count(),1,'Public profile shows the member rank');
        for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320]]) {
            await leader.setViewportSize({width,height});
            const badge=leader.locator('#game-dialog [data-alliance-rank="1"]');
            await badge.scrollIntoViewIfNeeded();
            assert.equal(await badge.evaluate(el=>{
                const r=el.getBoundingClientRect(),profile=el.closest('.lok-profile');
                return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight&&profile.scrollWidth<=profile.clientWidth+2;
            }),true,`${width}x${height}: profile rank remains visible without horizontal overflow`);
            await leader.screenshot({path:path.join(out,`profile-${width}x${height}.png`)});
        }
        await leader.setViewportSize({width:390,height:844});

        const deputy=await login('Rank deputy');
        await ranks(deputy);
        assert.equal(await deputy.locator('[data-form="community-role"]').count(),3,'R4 can manage exactly R1–R3');
        assert.deepEqual(await deputy.locator('[data-form="community-role"][data-id="2"] option').evaluateAll(els=>els.map(el=>el.value)),['member','veteran','officer']);
        await change(deputy,2,'veteran');
        const officer=await login('Rank officer');
        await ranks(officer);
        assert.equal(await officer.locator('[data-form="community-role"]').count(),0,'R3 sees the ranks without administrative controls');
        await officer.context().close();

        await members(leader);
        await leader.locator('[data-action="alliance-member-menu"][data-id="5"]').click();
        await leader.locator('[data-action="alliance-transfer"][data-id="5"]').click();
        const transfer=leader.waitForResponse(r=>r.url().includes('/api/kingdom/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action==='alliance.transfer');
        await leader.locator('[data-action="confirm-operation"]').click();
        assert.equal((await transfer).status(),200);
        await ranks(leader);
        assert.equal((await state(leader)).role_level,1,'Previous leader becomes R1');
        assert.equal(await leader.locator('[data-form="community-role"]').count(),0,'Previous leader loses rank administration');
        await ranks(deputy);
        const after=await state(deputy);
        assert.equal(after.role_level,5);
        assert.equal(after.members.filter(m=>m.role_level===5).length,1,'Leadership transfer leaves exactly one R5');
        assert.equal(await deputy.locator('[data-form="community-role"]').count(),4);

        for(const lang of ['de','fr']) {
            await Promise.all([deputy.waitForNavigation({waitUntil:'domcontentloaded'}),deputy.evaluate(lang=>ConquerLocale.setLocale(lang),lang)]);
            await ranks(deputy);
            assert.equal(await deputy.locator('html').getAttribute('lang'),lang);
            await geometry(deputy,lang+' localized ranks');
            assert.match(await deputy.locator('[data-form="community-role"][data-id="2"] option').first().innerText(),/^R1/);
            await deputy.screenshot({path:path.join(out,`ranks-${lang}-390x844.png`)});
        }
        await deputy.context().close();
        assert.deepEqual(errors,[]);
        assert.deepEqual(failures,[]);
        console.log('PASS R1–R5: actual main app at four sizes; all promotions/demotions persisted; R4/R3 permissions, profile rank, one R5 after leadership transfer, EN/DE/FR labels.');
    } catch(error) {
        for(const [i,page] of browser.contexts().flatMap(context=>context.pages()).entries()) await page.screenshot({path:path.join(out,`failure-${i}.png`)}).catch(()=>{});
        throw error;
    } finally {
        fs.writeFileSync(path.join(out,'diagnostics.json'),JSON.stringify({errors,failures},null,2));
        await browser.close();
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
