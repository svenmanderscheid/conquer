const fs=require('node:fs'),path=require('node:path');
const pw=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{const browser=await pw.chromium.launch({headless:true,channel:'msedge'});try{
 const page=await browser.newPage({viewport:{width:1200,height:1000}});
 const dir=path.resolve(__dirname,'../assets/art/village-layered-v2/runtime');
 const files=fs.readdirSync(dir).filter(n=>n!== 'terrain.webp');
 await page.setContent('<style>body{background:#bbc989;display:grid;grid-template-columns:repeat(4,1fr);font:16px sans-serif}figure{margin:5px;text-align:center}img{width:260px;height:210px;object-fit:contain}</style>'+files.map(n=>`<figure><img src="http://localhost/conquer/assets/art/village-layered-v2/runtime/${n}"><figcaption>${n}</figcaption></figure>`).join(''));
 await page.waitForFunction(()=>[...document.images].every(i=>i.complete&&i.naturalWidth));
 fs.mkdirSync(path.resolve(__dirname,'../artifacts/layered-village'),{recursive:true});
 await page.screenshot({path:path.resolve(__dirname,'../artifacts/layered-village/asset-review.png'),fullPage:true});
}finally{await browser.close();}})();
