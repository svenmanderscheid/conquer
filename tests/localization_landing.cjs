'use strict';
const fs=require('node:fs'),path=require('node:path'),http=require('node:http'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'artifacts/localization');fs.mkdirSync(output,{recursive:true});
const html=execFileSync(process.env.PHP_BINARY||'php',[path.join(__dirname,'fixtures/localization_landing.php')],{encoding:'utf8',maxBuffer:8e6});
const localeAssets=new Map();
const server=http.createServer((req,res)=>{
 const pathname=new URL(req.url,'http://localhost').pathname;
 if(pathname.startsWith('/locale-assets/')){
  // The public catalogue builder never starts the app or a database connection.
  if(!localeAssets.has(pathname))localeAssets.set(pathname,JSON.parse(execFileSync(process.env.PHP_BINARY||'php',['-r',"define('ROOT_DIR',getcwd());require 'src/Game/Locale.php';echo json_encode(\\Conquer\\Game\\Locale::assetResponse($argv[1]));",pathname],{cwd:root,encoding:'utf8',maxBuffer:8e6})));
  const asset=localeAssets.get(pathname);res.writeHead(asset.status,asset.headers);res.end(asset.body);return;
 }
 if(pathname==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.setHeader('Content-Security-Policy',"default-src 'self'; script-src 'self' 'nonce-localization-test'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'");res.end(html);return;}
 const file=path.resolve(root,'.'+decodeURIComponent(pathname));
 if(file.startsWith(root+path.sep+'assets'+path.sep)&&fs.existsSync(file)&&fs.statSync(file).isFile()){
  res.setHeader('Content-Type',({'.css':'text/css','.js':'text/javascript','.svg':'image/svg+xml','.png':'image/png','.webp':'image/webp','.woff2':'font/woff2'})[path.extname(file)]||'application/octet-stream');res.end(fs.readFileSync(file));return;
 }
 res.statusCode=404;res.end();
});
(async()=>{
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const browser=await chromium.launch({channel:process.env.PLAYWRIGHT_CHANNEL||'chrome',headless:true});
 try{
  const page=await browser.newPage({locale:'en-US'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:'+server.address().port);await page.waitForSelector('[data-locale-select]');
  const selectLocale=async lang=>{await page.locator('[data-locale-select]').selectOption(lang);await page.waitForFunction(value=>document.documentElement.lang===value&&document.querySelector('#waitlist-form [name="locale"]').value===value,lang);};
  assert.equal(await page.locator('input[type="password"],form[action$="/auth/local"]').count(),0,'website has no credential form');
  assert.equal(await page.locator('.lp-sign-in').getAttribute('href'),'https://play.unionofkingdoms.com/');
  for(const lang of ['en','fr','de']){
   await selectLocale(lang);
   assert.equal(await page.locator('html').getAttribute('lang'),lang);
   assert.equal(await page.locator('#waitlist-form [name="locale"]').inputValue(),lang);
   assert.equal(await page.locator('#hero-title span').textContent(),{en:'Your kingdom.',fr:'Votre royaume.',de:'Dein Königreich.'}[lang]);
   assert.equal(await page.locator('[name="first_name"]').inputValue(),'');
   await page.locator('[name="first_name"]').fill('Forschung');
   await selectLocale(lang==='fr'?'en':'fr');
   assert.equal(await page.locator('[name="first_name"]').inputValue(),'Forschung');
   await selectLocale(lang);await page.locator('[name="first_name"]').fill('');
   for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390]]){
    await page.setViewportSize({width,height});await page.evaluate(()=>document.fonts.ready);
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),`${lang} ${width}: page overflow`);
    const box=await page.locator('[data-locale-select]').boundingBox();assert(box&&box.x>=0&&box.x+box.width<=width+1,`${lang} ${width}: language selector reachable`);
    await page.evaluate(()=>window.scrollTo(0,0));
    await page.screenshot({path:path.join(output,`landing-${lang}-${width}.png`)});
   }
  }
  assert.deepEqual(errors,[]);console.log('PASS landing DE/EN/FR, CSP, live switching, submitted locale, untouched names and four viewports.');
 }finally{await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);server.close();process.exitCode=1;});
