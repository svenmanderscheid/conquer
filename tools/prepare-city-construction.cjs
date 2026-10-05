'use strict';
// Packs approved four-pose artwork; this does not generate or repaint images.
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const sharp=require('sharp');
const input=process.argv[2];
if(!input)throw Error('Usage: node tools/prepare-city-construction.cjs <approved-source-directory>');
const sources=JSON.parse(fs.readFileSync(path.join(input,'sources.json'))),registration=JSON.parse(fs.readFileSync(path.join(input,'registration.json')));
const out=path.resolve(__dirname,'../assets/art/city-construction-v1');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true});try{const page=await browser.newPage();const buildings={};
 for(const [code,file] of Object.entries(sources)){
  const source=fs.readFileSync(file),layout=registration[code];
  // The original sawmill pose 3 moves its stationary beam and trestles.
  // Repeat coherent approved poses so only workers and their saw move.
  const poseOrder=code==='lumber_camp'?[0,1,3,1]:[0,1,2,3];
  const packed=await page.evaluate(async({src,layout,poseOrder})=>{
   const image=new Image();image.src=src;await image.decode();
   const [x0,y0,x1,y1]=layout.bounds,bw=x1-x0,bh=y1-y0,scale=368/Math.max(bw,bh),w=Math.ceil(bw*scale)+16,h=Math.ceil(bh*scale)+16;
   const canvas=document.createElement('canvas');canvas.width=w*4;canvas.height=h;const ctx=canvas.getContext('2d');
   const cw=image.naturalWidth/2,ch=image.naturalHeight/2;
   for(let phase=0;phase<4;phase++){
    const sourcePhase=poseOrder[phase];
    ctx.save();ctx.beginPath();ctx.rect(phase*w,0,w,h);ctx.clip();ctx.translate(phase*w+8-x0*scale,8-y0*scale);ctx.scale(scale,scale);ctx.transform(...layout.transforms[sourcePhase]);
    ctx.drawImage(image,(sourcePhase%2)*cw,Math.floor(sourcePhase/2)*ch,cw,ch,0,0,1,1);ctx.restore();
   }
   return {w,h,png:canvas.toDataURL('image/png').split(',')[1]};
  },{src:'data:image/png;base64,'+source.toString('base64'),layout,poseOrder});
  const atlas=await sharp(Buffer.from(packed.png,'base64')).webp({quality:85,alphaQuality:100,effort:6}).toBuffer();fs.writeFileSync(path.join(out,code+'.webp'),atlas);
  buildings[code]={width:packed.w,height:packed.h,frames:4,bytes:atlas.length,sha256:crypto.createHash('sha256').update(atlas).digest('hex'),sourceSha256:crypto.createHash('sha256').update(source).digest('hex'),registration:layout,...(code==='lumber_camp'?{poseOrder:poseOrder.map(n=>n+1)}:{})};
 }
 fs.writeFileSync(path.join(out,'manifest.json'),JSON.stringify({version:1,approval:'User approved fitted scaffolding designs on 2026-10-05',source:'Built-in image generation; packed from the approved four-phase previews',buildings},null,2)+'\n');
 console.log(JSON.stringify({buildings:16,bytes:Object.values(buildings).reduce((n,b)=>n+b.bytes,0),sizes:Object.fromEntries(Object.entries(buildings).map(([c,b])=>[c,[b.width,b.height]]))}));
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exitCode=1;});
