'use strict';
// Render the original Three.js unit models as small, consistent tier portraits.
const fs=require('fs'),path=require('path'),{chromium}=require('playwright');
const base=process.env.TRAINING_FIXTURE_URL||'http://127.0.0.1:19321';
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{const page=await browser.newPage({viewport:{width:192,height:192},deviceScaleFactor:1});
  await page.goto(base);await page.evaluate(()=>{document.body.replaceChildren();document.body.style.cssText='margin:0;background:transparent';const host=document.createElement('div');host.id='portrait-export';host.style.cssText='width:192px;height:192px';document.body.append(host);});
  const dest=path.resolve(__dirname,'../artifacts/training/icons');fs.mkdirSync(dest,{recursive:true});
  for(let type=1;type<=3;type++)for(let tier=1;tier<=10;tier++){
   await page.evaluate(async({type,tier})=>{window.exportPortrait?.destroy();const {mountTrainingPortrait}=await import('/assets/city3d/training-portrait.js');window.exportPortrait=mountTrainingPortrait(document.querySelector('#portrait-export'),{type,tier,icon:true,reducedMotion:()=>true});},{type,tier});
   await page.waitForTimeout(80);await page.locator('#portrait-export').screenshot({path:path.join(dest,`${type}-${tier}.png`),omitBackground:true});
  }
  console.log('Rendered 30 original tier portraits.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
