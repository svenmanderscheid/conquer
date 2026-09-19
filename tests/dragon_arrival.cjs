'use strict';
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),fixture=require('./fixtures/world_march_motion.cjs'),output=path.join(root,'artifacts/dragon-arrival-20260913');fs.mkdirSync(output,{recursive:true});

(async()=>{const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture(root));return;}const file=path.resolve(root,'.'+name);if(!name.startsWith('/assets/')||!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.webp')?'image/webp':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));});await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
try{
 browser=await chromium.launch({headless:true,channel:'chrome'});const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto('http://127.0.0.1:'+server.address().port);await page.evaluate(()=>{demoAuto=false;document.querySelector('#skin').value='dragon';window.paintCalls=0;const ground=ConquerLandscape.ground;ConquerLandscape.ground=(...args)=>{paintCalls++;return ground(...args)};});

 await page.evaluate(()=>startMarch(5,220));const before=await page.evaluate(()=>paintCalls);await page.waitForSelector('.atlas-dragon-impact');
 const canvas=page.locator('.atlas-dragon-impact');assert.equal(await canvas.count(),1,'dragon attack uses its dedicated canvas effect');assert.equal(await page.locator('.atlas-impact-emblem').count(),0,'dragon effect does not use a text glyph');
 await page.waitForTimeout(170);await page.screenshot({path:path.join(output,'dragon-arrival-390x844.png')});
 const first=await canvas.evaluate(node=>node.toDataURL());await page.waitForTimeout(260);assert.notEqual(await canvas.evaluate(node=>node.toDataURL()),first,'dive, breath and embers progress through distinct frames');assert.equal(await page.evaluate(()=>paintCalls),before,'arrival does not redraw terrain');
 await page.waitForTimeout(1700);assert.equal(await canvas.count(),0,'dragon canvas is released after the afterglow');

 for(let i=0;i<5;i++){await page.evaluate(()=>startMarch(5,70));await page.waitForTimeout(115);}
 assert((await page.locator('.atlas-march-impact').count())<=4,'world effect budget keeps at most four simultaneous arrivals');
 await page.waitForTimeout(1950);assert.equal(await page.locator('.atlas-dragon-impact').count(),0,'all limited effects are disposed');

 await page.emulateMedia({reducedMotion:'reduce'});await page.evaluate(()=>startMarch(5,70));await page.waitForTimeout(150);assert.equal(await page.locator('.atlas-dragon-impact').count(),0,'reduced motion suppresses the dragon arrival');assert.equal(await page.locator('.atlas-party-skin').evaluate(el=>getComputedStyle(el).rotate),'0deg','reduced motion also suppresses the dive pose');
 assert.deepEqual(errors,[]);console.log('PASS dragon dive, emerald-amber breath, cleanup, terrain isolation, effect limit and reduced motion. Screenshot: '+output);
}finally{if(browser)await browser.close();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}})().catch(error=>{console.error(error);process.exitCode=1});
