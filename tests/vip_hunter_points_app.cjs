'use strict';
// Actual main app, isolated account and database; never reads or changes live players.
const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'output','playwright','vip-hunter');
fs.mkdirSync(output,{recursive:true});
async function fixture() {
    const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
    const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--talents','--speed-bonuses','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
    let log='';
    const ready=new Promise((resolve,reject)=>{
        const timeout=setTimeout(()=>reject(Error('Fixture startup timeout: '+log)),60000);
        child.stdout.on('data',chunk=>{log+=chunk;if(log.includes('Synthetic preview ready')){clearTimeout(timeout);resolve();}});
        child.stderr.on('data',chunk=>{log+=chunk;});
        child.once('error',error=>{clearTimeout(timeout);reject(error);});
        child.once('exit',code=>{clearTimeout(timeout);reject(Error('Fixture stopped '+code+': '+log));});
    });
    return {child,ready,base:'http://127.0.0.1:'+port};
}
(async()=>{
    const test=await fixture();let browser;
    try {
        await test.ready;
        browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
        const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true});
        const page=await context.newPage(),errors=[],report=[];
        page.on('pageerror',e=>errors.push(e.message));
        page.setDefaultTimeout(15000);
        await page.goto(test.base+'/?zugang=login');
        await page.locator('[name="identifier"], [name="username"]').first().fill('PreviewPlayer');
        await page.locator('[name="password"]').fill('PreviewFixture!2026');
        await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"], #auth-submit').first().click()]);
        await page.locator('#app-start').waitFor({state:'detached'});
        await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
        assert.equal(await page.evaluate(()=>ConquerLocale.locale),'en','English is the default without an explicit language preference');
        const mastery=await page.evaluate(async()=>{
            const result=await fetch((window.CONQUER_BASE||'')+'/api/progression/state').then(r=>r.json());
            return result.data.mastery;
        });
        assert.equal(mastery.points_from_hunter,60);assert.equal(mastery.points_from_vip,9);assert.equal(mastery.earned,69);
        async function close() {for(let i=0;i<4&&await page.locator('dialog[open]').count();i++)await page.keyboard.press('Escape');}
        for(const locale of ['en','de','fr']) {
            await close();
            if(await page.evaluate(()=>ConquerLocale.locale)!==locale) {
                await Promise.all([page.waitForEvent('load'),page.evaluate(locale=>ConquerLocale.setLocale(locale),locale)]);
                await page.locator('#app-start').waitFor({state:'detached'});
            }
            const localizedDescription=JSON.parse(fs.readFileSync(path.join(root,'data','i18n',locale+'.json'),'utf8'))['talents.points_description'];
            await page.waitForFunction(({locale,description})=>window.ConquerLocale?.locale===locale&&ConquerLocale.t('talents.points_description')===description,{locale,description:localizedDescription});
            for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390]]) {
                await close();await page.setViewportSize({width,height});
                await page.locator('#hud-menu').click();
                await page.locator('#game-dialog [data-action="dialog-tab"][data-id="mastery"]').click();
                await page.locator('.talent-point-sources').waitFor();
                const expected=await page.evaluate(()=>ConquerLocale.t('talents.point_sources',{hunter:60,vip:9,total:69}));
                assert.equal(await page.locator('.talent-point-sources').textContent(),expected);
                assert.equal(await page.locator('.talent-points strong').textContent(),'69');
                const metrics=await page.locator('.talent-point-sources').evaluate(el=>{
                    const r=el.getBoundingClientRect(),d=document.querySelector('#panel-dialog').getBoundingClientRect();
                    return {visible:r.top>=d.top&&r.bottom<=d.bottom&&r.left>=d.left&&r.right<=d.right,overflow:el.scrollWidth>el.clientWidth+1,pageOverflow:document.documentElement.scrollWidth>innerWidth+1};
                });
                assert.deepEqual(metrics,{visible:true,overflow:false,pageOverflow:false},locale+' '+width+'x'+height);
                await page.screenshot({path:path.join(output,locale+'-'+width+'-hunter.png')});
                await page.locator('[data-action="talent-options"]').click();
                const description=await page.evaluate(()=>ConquerLocale.t('talents.points_description'));
                assert((await page.locator('.talent-sheet-body').textContent()).includes(description));
                await close();await page.locator('#hud-vip-button').click();await page.locator('.vip-bonuses').waitFor();
                const label=await page.evaluate(()=>ConquerLocale.t('vip.hunter_points'));
                const row=page.locator('.vip-bonuses > div').filter({has:page.locator('dt',{hasText:label})});
                assert.equal(await row.count(),1);assert.match(await row.locator('dd').textContent(),/^\+9/);assert(!(await row.locator('dd').textContent()).includes('%'),'Hunter allowance is a flat count');
                await page.screenshot({path:path.join(output,locale+'-'+width+'-vip.png')});
                report.push({locale,width,height,...metrics});
            }
        }
        assert.deepEqual(errors,[]);
        fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({checks:report,errors},null,2));
        console.log('PASS actual /city: Hunter/VIP sources and flat perks, 3 languages and 4 viewports; '+output);
    } finally {
        await browser?.close();
        if(test.child.exitCode===null)await new Promise(resolve=>{test.child.once('exit',resolve);test.child.stdin.end('\n');});
    }
})().catch(error=>{console.error(error);process.exitCode=1;});
