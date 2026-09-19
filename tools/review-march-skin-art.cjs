'use strict';
// Visual QA of final game assets at map scale and collection-preview scale.
const fs=require('fs'),path=require('path'),http=require('http'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'artifacts/march-skins');
const manifest=JSON.parse(fs.readFileSync(path.join(root,'docs/march-skin-art-prompts.json'),'utf8'));
const catalog=JSON.parse(fs.readFileSync(path.join(root,'data/march_skins.json'),'utf8')).entries;
for(const asset of manifest.assets)asset.name=catalog.find(entry=>entry.id===asset.id)?.name||asset.name;
const sprite=id=>['phoenix','dragon'].includes(id)?`/assets/art/marches/flight-${id}.png`:`/assets/art/marches/march-${id}.webp`;
const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const html=`<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Conquer · Marsch-Skins</title><style>
*{box-sizing:border-box}body{margin:0;padding:32px;background:#f6ecd8;color:#493b33;font:16px 'Trebuchet MS',sans-serif}header{margin-bottom:24px}h1{font-size:30px;margin:0 0 8px}p{margin:0;color:#786345}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}.card{border:2px solid #c7ab7b;border-radius:16px;background:#fff6df;overflow:hidden}.card h2{font-size:17px;margin:0;padding:12px 14px;background:#2a72c9;color:#fff6df}.art{height:215px;display:flex;justify-content:center;align-items:center}.art img{width:208px;height:208px;object-fit:contain}.terrains{display:grid;grid-template-columns:repeat(4,1fr)}.terrains span{height:90px;display:grid;place-items:center}.terrains img{width:75px;height:75px;object-fit:contain}small{display:block;padding:10px 14px;color:#786345}@media(max-width:800px){body{padding:16px}.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.art{height:170px}.art img{width:165px;height:165px}.terrains span{height:55px}.terrains img{width:44px;height:44px}}
</style><header><h1>Marsch-Skins · Die vollständige Sammlung</h1><p>${manifest.assets.length} passende Skins · jeweils +5 % Marschgeschwindigkeit beim Anlegen</p></header><div class="grid">${manifest.assets.map(a=>`<article class="card"><h2>${escape(a.name)}</h2><div class="art"><img src="${sprite(a.id)}" alt="${escape(a.name)}"></div><div class="terrains">${['#b9c985','#dce9e5','#e6cc96','#ad9990'].map(c=>`<span style="background:${c}"><img src="${sprite(a.id)}" alt=""></span>`).join('')}</div><small>${escape(a.id)} · Wald / Eis / Sand / Asche</small></article>`).join('')}</div></html>`;
fs.mkdirSync(output,{recursive:true});fs.writeFileSync(path.join(output,'index.html'),html);
(async()=>{
 const server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://127.0.0.1');if(url.pathname==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(html);return;}
  const file=path.resolve(root,'.'+url.pathname),allowed=/\.(webp|png)$/i.test(file);if(!file.startsWith(root+path.sep)||!allowed||!fs.existsSync(file)){res.writeHead(404).end();return;}
  res.setHeader('Content-Type',file.endsWith('.png')?'image/png':'image/webp');fs.createReadStream(file).pipe(res);
 });await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:'+server.address().port);await page.locator('img').last().waitFor();
  await page.waitForFunction(()=>[...document.images].every(img=>img.complete));
  assert.deepEqual(await page.locator('img').evaluateAll(images=>images.filter(img=>!img.naturalWidth).map(img=>img.src)),[]);
  await page.screenshot({path:path.join(output,'collection.png'),fullPage:true});
  await page.locator('.card').filter({has:page.locator('h2',{hasText:'Phönixgarde'})}).screenshot({path:path.join(output,'phoenix-preview.png')});
  assert.deepEqual(errors,[]);console.log(`PASS all ${manifest.assets.length} transparent assets load; collection and four terrain previews rendered.`);
 }finally{await browser?.close();await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);process.exitCode=1});
