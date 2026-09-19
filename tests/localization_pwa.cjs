'use strict';
// Browser and service-worker checks on an isolated HTTP fixture. No game account is accessed.
const fs=require('fs'),path=require('path'),os=require('os'),http=require('http'),assert=require('assert'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-localization-pwa-'));
const catalogs=Object.fromEntries(['de','fr','lb'].map(locale=>[locale,JSON.parse(fs.readFileSync(root+'/data/i18n/'+locale+'.json','utf8'))]));
for(const locale of ['fr','lb'])assert.deepEqual(Object.keys(catalogs[locale]),Object.keys(catalogs.de),'catalog key parity');
const requests=[];
function pageHtml(base){return `<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><link rel="manifest" href="${base}/manifest.php"><link rel="stylesheet" href="${base}/assets/css/localization.css?v=test"></head><body><main><h1 id="page-title">Gemeinschaft</h1><div data-locale-controls></div><button data-action="community-help-give">Helfen</button><button data-action="community-chat-send">Nachricht senden</button><div class="community-message"><p>Helfen</p><strong>Forschung</strong></div><div class="player-name">Forschung</div><div data-user-content><button data-action="community-help-give">Helfen</button><p data-i18n="common.save">Forschung</p></div><form><label>Betreff<input id="subject" value="Forschung" placeholder="Bis zu 200 Zeichen"></label><textarea>Helfen</textarea><input id="explicit-attribute" data-i18n-attrs="aria-label:account.current_password" aria-label="Aktuelles Passwort"></form><span id="semantic" data-i18n="worlds.rule"></span><div id="dynamic"></div><section class="world-selector"><p>${catalogs.de['worlds.rule']}</p><article><h2>Forschung</h2><p>Helfen</p><button data-action="worlds-select">Welt öffnen</button></article></section><p id="private">PRIVATE_CITY_123</p></main><script>window.CONQUER_BASE=${JSON.stringify(base)};window.CONQUER_I18N=${JSON.stringify({locale:'de',catalogs}).replaceAll('<','\\u003c')};</script><script src="${base}/assets/js/localization.js?v=test" defer></script></body></html>`;}
const server=http.createServer((req,res)=>{
    const url=new URL(req.url,'http://fixture.test'),base=url.pathname.startsWith('/conquer/')?'/conquer':'',relative=url.pathname.slice(base.length+1);requests.push({url:req.url,headers:req.headers});
    if(relative==='manifest.php'){
        const php=`$_SERVER['SCRIPT_NAME']=${JSON.stringify(base+'/manifest.php')};require ${JSON.stringify(root.replaceAll('\\','/')+'/manifest.php')};`;
        res.setHeader('Content-Type','application/manifest+json');res.end(execFileSync(process.env.PHP_BINARY||'php',['-r',php]));return;
    }
    if(['api/state','admin','admin/players','auth/local'].includes(relative)){res.setHeader('Content-Type','application/json');res.setHeader('Cache-Control','no-store');res.end(JSON.stringify({secret:'PRIVATE_API_TOKEN'}));return;}
    const allowed=['service-worker.js','offline.html','assets/js/localization.js','assets/css/localization.css','assets/icons/conquer.svg','assets/icons/conquer-192.png','assets/icons/conquer-512.png'];
    if(allowed.includes(relative)){
        const ext=path.extname(relative),types={'.js':'application/javascript','.css':'text/css','.html':'text/html','.png':'image/png','.svg':'image/svg+xml'};res.setHeader('Content-Type',types[ext]||'text/plain');
        if(url.searchParams.get('v')==='private')res.setHeader('Cache-Control','private, no-store');
        res.end(url.searchParams.get('v')==='large'?'x'.repeat(600*1024):fs.readFileSync(root+'/'+relative));return;
    }
    res.setHeader('Content-Type','text/html; charset=utf-8');res.setHeader('Cache-Control','private, no-store');res.end(pageHtml(base));
});
(async()=>{await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port;const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});try{
    for(const base of ['', '/conquer']){
        const context=await browser.newContext(),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.goto(origin+base+'/city');
        await page.waitForSelector('[data-locale-select]');await page.evaluate(()=>navigator.serviceWorker.ready);await page.reload();await page.waitForFunction(()=>Boolean(navigator.serviceWorker.controller));
        const manifest=await page.evaluate(async(base)=>await(await fetch(base+'/manifest.php')).json(),base);assert.equal(manifest.start_url,base+'/?source=pwa');assert.equal(manifest.scope,base+'/');assert.equal(manifest.id,base+'/');assert.equal(manifest.icons[1].sizes,'512x512');
        for(const icon of manifest.icons){const r=await page.request.get(origin+icon.src);assert.equal(r.status(),200);const bytes=await r.body();assert.equal(bytes.readUInt32BE(16),Number(icon.sizes.split('x')[0]));assert.equal(bytes.readUInt32BE(20),Number(icon.sizes.split('x')[1]));}
        await page.locator('[data-locale-select]').selectOption('fr');await page.waitForFunction(()=>document.documentElement.lang==='fr');
        assert.equal(await page.locator('body>main>button[data-action="community-help-give"]').textContent(),'Aider');
        assert.equal(await page.locator('.community-message p').textContent(),'Helfen');assert.equal(await page.locator('.player-name').textContent(),'Forschung');assert.equal(await page.locator('[data-user-content] p').textContent(),'Forschung');assert.equal(await page.locator('#subject').inputValue(),'Forschung');assert.equal(await page.locator('textarea').inputValue(),'Helfen');assert.equal(await page.locator('#subject').getAttribute('placeholder'),'Jusqu’à 200 caractères');assert.equal(await page.locator('#explicit-attribute').getAttribute('aria-label'),'Mot de passe actuel');
        assert.equal(await page.locator('.world-selector h2').textContent(),'Forschung');assert.equal(await page.locator('.world-selector article p').textContent(),'Helfen');assert.equal(await page.locator('.world-selector button').textContent(),'Ouvrir le monde');assert.equal(await page.locator('.world-selector>p').textContent(),catalogs.fr['worlds.rule']);
        await page.evaluate(()=>{document.querySelector('#dynamic').innerHTML='<button data-action="community-reload">Aktualisieren</button>';});await page.waitForFunction(()=>document.querySelector('#dynamic button').textContent==='Actualiser');
        assert.ok((await page.evaluate(()=>document.cookie)).includes('conquer_locale=fr'));await page.reload();await page.waitForFunction(()=>document.documentElement.lang==='fr');assert.equal(await page.locator('[data-locale-select]').inputValue(),'fr');
        await page.evaluate(()=>ConquerLocale.setLocale('lu'));await page.waitForFunction(()=>document.documentElement.lang==='lb');assert.equal(await page.locator('body>main>button[data-action="community-chat-send"]').textContent(),'Message schécken');assert.equal(await page.locator('[data-locale-select]').getAttribute('aria-label'),'Sprooch');
        await page.evaluate(()=>ConquerLocale.setLocale('de'));await page.waitForFunction(()=>document.querySelector('body>main>button[data-action="community-help-give"]').textContent==='Helfen');
        await page.setViewportSize({width:390,height:844});await page.screenshot({path:out+'/'+(base?'subdirectory':'root')+'-locale.png',fullPage:true});
        await context.addCookies([{name:'conquer_session',value:'PRIVATE_COOKIE_TOKEN',url:origin+base+'/'}]);
        await page.evaluate(async(base)=>{
            for(const p of ['api/state','admin','admin/players','auth/local'])await fetch(base+'/'+p,{credentials:'include'});
            await fetch(base+'/assets/css/localization.css?v=sanitized',{headers:{'X-CSRF-Token':'PRIVATE_CSRF_TOKEN'},credentials:'include'});
            for(let i=0;i<40;i++)await fetch(base+'/assets/css/localization.css?v=bounded'+i);
            await fetch(base+'/assets/css/localization.css?v=private');await fetch(base+'/assets/css/localization.css?v=large');
        },base);
        const cacheState=await page.evaluate(async()=>{
            const names=await caches.keys();
            return Promise.all(names.map(async name=>{
                const c=await caches.open(name),keys=await c.keys();
                const entries=await Promise.all(keys.map(async k=>({url:k.url,headers:[...k.headers],body:(await c.match(k)).headers.get('content-type')})));
                return {name,entries};
            }));
        });
        assert.equal(cacheState.length,1);assert.ok(cacheState[0].entries.length<=32);assert.ok(cacheState[0].entries.some(e=>e.url===origin+base+'/offline.html'));
        assert.ok(cacheState[0].entries.every(e=>!e.url.includes('/api/')&&!e.url.includes('/admin')&&!e.url.includes('/auth/')&&!e.url.includes('/city')&&!e.url.includes('v=private')&&!e.url.includes('v=large')));
        assert.ok(cacheState[0].entries.every(e=>e.headers.every(([k])=>!['cookie','authorization','x-csrf-token'].includes(k.toLowerCase()))));
        const sanitized=requests.findLast(r=>r.url===base+'/assets/css/localization.css?v=sanitized');assert.ok(sanitized);assert.equal(sanitized.headers.cookie,undefined);assert.equal(sanitized.headers['x-csrf-token'],undefined);
        await page.evaluate(()=>ConquerLocale.setLocale('fr'));await context.setOffline(true);await page.goto(origin+base+'/nested/world');await page.waitForSelector('#retry');assert.equal(await page.locator('html').getAttribute('lang'),'fr');assert.ok(!(await page.content()).includes('PRIVATE_CITY_123'));assert.equal(await page.locator('#retry').textContent(),'Réessayer');
        await page.screenshot({path:out+'/'+(base?'subdirectory':'root')+'-offline.png',fullPage:true});
        await context.setOffline(false);assert.deepEqual(errors,[]);await context.close();
    }
    console.log('PASS 290 shared catalog keys, DE/FR/LU persistence and dynamic labels, protected user content, root/subdirectory manifests and PNG dimensions, sanitized bounded static cache, private-route exclusion and French offline fallback. '+out);
}finally{await browser.close();await new Promise(resolve=>server.close(resolve));}})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
