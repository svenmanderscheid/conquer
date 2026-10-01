const assert=require('node:assert/strict'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
(async()=>{const browser=await chromium.launch({channel:'msedge',headless:true});try{
 const page=await browser.newPage({viewport:{width:1024,height:1375}});
 await page.goto('http://localhost/conquer/assets/world-preview/index.html?v=9');await page.waitForFunction(()=>worldPreview.state().ready);
 await page.locator('#animation').click();
 const result=await page.evaluate(async()=>{
  const {buildMotionFrames,posePoint,matchDragonSize}=await import('./monster-motion.js?v=10');
  const sheet=document.createElement('canvas');sheet.width=1024;sheet.height=1375;const ctx=sheet.getContext('2d');ctx.fillStyle='#bdce85';ctx.fillRect(0,0,1024,1375);
  const checks=[];let row=0;
  for(const id of ['green-dragon','red-dragon','gold-dragon','magdar','castle']){
   const img=new Image();img.src=id==='castle'?'../art/fantasy-village-v1/world-castle-v2.png':'../art/monsters/2.5d/bright-v2/'+id+'.png';await img.decode();const frames=await buildMotionFrames(img,id);
   const item={id,image:img};matchDragonSize(item);const target=170*({'red-dragon':1.14,'gold-dragon':1.10}[id]||1);if(id.includes('dragon')&&Math.abs(item.visibleHeight-target)>.001)throw Error('dragon optical size mismatch');
   if(id==='magdar'&&JSON.stringify(posePoint(id,.63,.50,.25))===JSON.stringify(posePoint(id,.63,.50,.75)))throw Error('chest must breathe');
   for(let col=0;col<4;col++){ctx.drawImage(frames[col*6],col*256,row*275);ctx.fillStyle='#443549';ctx.font='16px serif';ctx.fillText(id+' / '+col*6,col*256+12,row*275+269);}
   checks.push({id,feetStill:[.42,.55,.65,.85].every(x=>JSON.stringify(posePoint(id,x,.95,.25))===JSON.stringify(posePoint(id,x,.95,.75))),frames:frames.length,different:frames[6].toDataURL()!==frames[18].toDataURL()});row++;
  }
  document.body.replaceChildren(sheet);document.body.style.cssText='margin:0;display:block;overflow:auto';sheet.style.cssText='width:1024px;height:1375px';return checks;
 });
 for(const r of result){assert.equal(r.frames,24);assert(r.feetStill,r.id+' planted feet');assert(r.different,r.id+' pose change');}
 await page.screenshot({path:path.resolve(__dirname,'../artifacts/world-style-preview/motion-poses.png')});console.log('five rigs: distinct poses, fixed feet, chest motion, normalized dragon height OK');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
