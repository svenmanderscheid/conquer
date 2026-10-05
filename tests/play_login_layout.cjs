'use strict';
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/login-preview');
fs.mkdirSync(out,{recursive:true});
const pages=Object.fromEntries(['login','register'].map(mode=>[mode,execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'fixtures/play_login.php'),mode],{encoding:'utf8',maxBuffer:8e6})]));
const localeAssets=new Map();
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://localhost');
 if(url.pathname.startsWith('/conquer/locale-assets/')){
  // Use the pure public-asset responder, without game bootstrap or a database.
  const assetPath=url.pathname.replace(/^\/conquer/,'');
  if(!localeAssets.has(assetPath))localeAssets.set(assetPath,JSON.parse(execFileSync(process.env.PHP_BINARY||'php',['-r',"define('ROOT_DIR',getcwd());require 'src/Game/Locale.php';echo json_encode(\\Conquer\\Game\\Locale::assetResponse($argv[1]));",assetPath],{cwd:root,encoding:'utf8',maxBuffer:8e6})));
  const asset=localeAssets.get(assetPath);res.writeHead(asset.status,asset.headers);res.end(asset.body);return;
 }
 if(url.pathname==='/conquer/service-worker.js'){
  res.setHeader('Content-Type','text/javascript');res.end(fs.readFileSync(path.join(root,'service-worker.js')));return;
 }
 if(url.pathname==='/conquer/offline.html'){
  res.setHeader('Content-Type','text/html');res.end(fs.readFileSync(path.join(root,'offline.html')));return;
 }
 if(url.pathname==='/conquer/'){
  res.setHeader('Content-Type','text/html; charset=utf-8');
  res.setHeader('Content-Security-Policy',"default-src 'self'; script-src 'self' 'nonce-login-layout-test'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'");
  res.end(pages[url.searchParams.get('mode')==='register'?'register':'login']);return;
 }
 const file=path.resolve(root,'.'+decodeURIComponent(url.pathname.replace(/^\/conquer/,'')));
 if(file.startsWith(root+path.sep+'assets'+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile()){
  res.setHeader('Content-Type',({'.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml','.png':'image/png','.webp':'image/webp','.woff2':'font/woff2'})[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));return;
 }
 if(['/conquer/favicon.ico','/conquer/apple-touch-icon.png'].includes(url.pathname)){res.setHeader('Content-Type',path.extname(file)==='.ico'?'image/x-icon':'image/png');res.end(fs.readFileSync(file));return;}
 if(url.pathname==='/conquer/manifest.php'){res.setHeader('Content-Type','application/manifest+json');res.end(execFileSync(process.env.PHP_BINARY||'php',['-r',"define('APP_BASE','/conquer');require 'manifest.php';"],{cwd:root}));return;}
 res.statusCode=404;res.end();
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 try{
  const page=await browser.newPage({locale:'de-DE'}),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  page.on('console',m=>{if(m.type()==='error')errors.push(m.text());});
  for(const mode of ['login','register']){
   await page.goto('http://127.0.0.1:'+server.address().port+'/conquer/?mode='+mode);
   await page.locator('[data-locale-select]').waitFor();
   for(const locale of ['de','en','fr']){
    await page.locator('[data-locale-select]').selectOption(locale);
    for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
     await page.setViewportSize({width,height});await page.evaluate(()=>document.fonts.ready);
     assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${mode} ${locale} ${width}: no horizontal overflow`);
     const card=await page.locator('.play-card').boundingBox();
     assert(Math.abs(card.x+card.width/2-width/2)<2,'authentication window centered');
     for(const control of await page.locator('input:not([type=hidden]),button[type=submit],.play-tabs a,.play-home,[data-locale-select]').all()){
      await control.scrollIntoViewIfNeeded();const box=await control.boundingBox();
      assert(box.height>=43&&box.x>=0&&box.x+box.width<=width+1&&box.y>=-1&&box.y+box.height<=height+1,'touch control fully reachable');
     }
     assert(await page.locator('.play-logo').evaluate(img=>img.complete&&img.naturalWidth>0),'logo loaded');
     await page.evaluate(()=>scrollTo(0,0));
     if(locale==='de')await page.screenshot({path:path.join(out,`spiel-${mode}-${width}x${height}.png`),fullPage:true});
    }
   }
  }
  assert.deepEqual(errors,[]);console.log('PASS game login/registration: 30 layouts, DE/EN/FR, touch controls, subfolder assets and CSP.');
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});
