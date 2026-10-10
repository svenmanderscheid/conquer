'use strict';
// Real service-worker lifecycle and cache boundaries, using a local public-only fixture.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),http=require('node:http');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const publicFiles=new Set(['service-worker.js','offline.html','favicon.ico','apple-touch-icon.png',
    'assets/icons/conquer-32.png','assets/icons/conquer-maskable-512.png','assets/icons/conquer-192.png','assets/icons/conquer-512.png']);
const server=http.createServer((req,res)=>{
    const url=new URL(req.url,'http://fixture.test'),base=url.pathname.startsWith('/conquer/')?'/conquer':'',relative=url.pathname.slice(base.length+1);
    if(publicFiles.has(relative)){
        res.setHeader('Content-Type',relative.endsWith('.js')?'application/javascript':relative.endsWith('.html')?'text/html':'image/png');
        res.end(fs.readFileSync(path.join(root,relative)));return;
    }
    res.setHeader('Cache-Control','private, no-store');
    if(['api/push/status','admin','auth/local'].includes(relative)){
        res.setHeader('Content-Type','application/json');res.end('{"private":"fixture-only"}');return;
    }
    res.setHeader('Content-Type','text/html');
    res.end('<!doctype html><html><head><meta charset="utf-8"></head><body><h1>Isolated notification worker test</h1></body></html>');
});
(async()=>{
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
    const origin='http://127.0.0.1:'+server.address().port;
    let browser;
    try{
        browser=await chromium.launch({headless:true,channel:'chrome'});
        for(const base of ['', '/conquer']){
            const context=await browser.newContext(),page=await context.newPage(),errors=[];
            page.on('pageerror',e=>errors.push(e.message));
            await page.goto(origin+base+'/city');
            await page.evaluate(async base=>{
                await navigator.serviceWorker.register(base+'/service-worker.js',{scope:base+'/',updateViaCache:'none'});
                await navigator.serviceWorker.ready;
            },base);
            await page.waitForFunction(()=>Boolean(navigator.serviceWorker.controller));
            assert.equal(await page.evaluate(()=>Notification.permission),'default','Registering never requests permission');
            for(const route of ['api/push/status','admin','auth/local'])await page.evaluate(async url=>{
                const response=await fetch(url);if(!response.ok)throw Error('Fixture request failed');
            },base+'/'+route);
            const state=await page.evaluate(async()=>Promise.all((await caches.keys()).map(async name=>({name,urls:(await (await caches.open(name)).keys()).map(r=>r.url)}))));
            assert.equal(state.length,1);
            assert(state[0].name.startsWith('union-of-kingdoms-public-v13-device-notifications:'));
            assert(state[0].urls.includes(origin+base+'/offline.html'));
            assert(state[0].urls.every(url=>!/(?:\/api\/|\/auth\/|\/admin|\/city)/.test(url)),'No authenticated content in cache');
            await context.setOffline(true);
            await page.goto(origin+base+'/city');
            await page.locator('#retry').waitFor();
            assert.equal(await page.locator('html').getAttribute('lang'),'en');
            assert.equal(await page.locator('#retry').innerText(),'Try again');
            assert.equal(await page.evaluate(()=>document.baseURI),origin+base+'/');
            assert.deepEqual(errors,[]);
            await context.close();
        }
        console.log('PASS real notification service worker: root/subdirectory scope, no permission prompt, private cache exclusion and offline fallback.');
    }finally{
        if(browser)await browser.close();
        await new Promise(resolve=>server.close(resolve));
    }
})().catch(e=>{console.error(e);process.exitCode=1;});
