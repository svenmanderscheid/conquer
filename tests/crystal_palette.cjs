const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/crystal-palette');fs.mkdirSync(out,{recursive:true});
const icons=['items/gems.svg',...['bundle','crate','cart'].map(s=>'items/backpack/gems-'+s+'.svg'),'map/crystal.svg'];
for(const icon of icons){const text=fs.readFileSync(path.join(root,'assets/art',icon),'utf8');assert(text.includes('#ad65df'));assert(!/#ed643f|#de674a|Facettierter Rubin/.test(text));}
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{const page=await browser.newPage();
 await page.goto('http://localhost/conquer/assets/world-preview/index.html');
 await page.setContent('<html><head><base href="http://localhost/conquer/"><link rel="stylesheet" href="assets/css/fantasy-fonts.css"><link rel="stylesheet" href="assets/css/game-overlay.css"><link rel="stylesheet" href="assets/css/village-theme.css"></head><body class="mobile-game"><main style="padding:18px;background:var(--ui-paper);min-height:100vh"><h1>Kristalle · Amethyst</h1><button id="hud-gems" class="hud-gems"><img src="assets/art/items/gems.svg" alt="Kristall"><strong>1.234</strong><span>Kristalle</span></button><div class="crystal-shop-summary"><img src="assets/art/items/gems.svg" alt=""><span><small>Dein Bestand</small><strong>1.234 Kristalle</strong></span></div><div style="display:flex;flex-wrap:wrap;gap:12px">'+icons.map(i=>'<img style="width:80px;height:80px" src="assets/art/'+i+'" alt="Kristallsymbol">').join('')+'</div></main></body></html>');
 for(const [w,h] of [[1280,800],[390,844],[320,700],[844,390]]){await page.setViewportSize({width:w,height:h});await page.evaluate(async()=>{await document.fonts.ready;await Promise.all([...document.images].map(i=>i.decode()));});
  assert.equal(await page.locator('#hud-gems').evaluate(e=>getComputedStyle(e).color),'rgb(105, 52, 150)');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));await page.screenshot({path:path.join(out,w+'x'+h+'.png')});
 }
 console.log('5 amethyst SVGs, HUD colour, four responsive sizes: OK');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
