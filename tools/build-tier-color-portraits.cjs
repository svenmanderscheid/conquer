'use strict';
// Derive delivery sizes from Imagegen masters without recoloring or flattening alpha.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const sharp=require(process.env.SHARP_MODULE||'sharp');
const root=path.resolve(__dirname,'..'),dir=path.join(root,'assets/art/characters/tier-colors-v1');
const jobs=JSON.parse(fs.readFileSync(path.join(root,'docs/TROOP_TIER_COLORS.prompts.json'),'utf8')).jobs;
const partial=process.argv.includes('--partial');
(async()=>{
 const records=[];
 for(const job of jobs){
  const source=path.join(dir,job.id+'.png');
  if(!fs.existsSync(source)&&partial)continue;
  const meta=await sharp(source).metadata();
  assert(meta.hasAlpha,job.id+': missing transparency');
  const {data,info}=await sharp(source).ensureAlpha().raw().toBuffer({resolveWithObject:true});
  let left=info.width,top=info.height,right=0,bottom=0,transparent=0;
  for(let y=0;y<info.height;y++)for(let x=0;x<info.width;x++){
   const alpha=data[(y*info.width+x)*info.channels+3];
   if(alpha===0)transparent++;
   if(alpha>8){left=Math.min(left,x);right=Math.max(right,x);top=Math.min(top,y);bottom=Math.max(bottom,y);}
  }
  assert(transparent/(info.width*info.height)>.1,job.id+': background is not transparent');
  assert(right>left&&bottom>top,job.id+': empty image');
  const crop={left,top,width:right-left+1,height:bottom-top+1},files={};
  for(const [kind,size,pad]of [['thumb',192,6],['report',512,14],['ui',768,20]]){
   const output=path.join(dir,job.id+'-'+kind+'.webp');
   if(!fs.existsSync(output)||fs.statSync(output).mtimeMs<fs.statSync(source).mtimeMs){
    await sharp(source).extract(crop).resize(size-pad*2,size-pad*2,{fit:'contain',background:'#00000000'}).extend({top:pad,bottom:pad,left:pad,right:pad,background:'#00000000'}).webp({quality:88,alphaQuality:100,effort:4}).toFile(output);
   }
   files[kind]={file:path.basename(output),bytes:fs.statSync(output).size,width:size,height:size};
  }
  records.push({id:job.id,tier:job.tier,type:job.type,color:job.color,source:path.basename(source),transparentFraction:transparent/(info.width*info.height),files});
 }
 if(!partial)assert.equal(records.length,30);
 fs.writeFileSync(path.join(dir,'manifest.json'),JSON.stringify({generator:'built-in image_gen',derivatives:'sharp; resize and alpha-preserving WebP encoding only',records},null,2)+'\n');
 console.log(JSON.stringify({portraits:records.length,thumbnailsBytes:records.reduce((n,r)=>n+r.files.thumb.bytes,0),output:dir},null,2));
})().catch(error=>{console.error(error);process.exitCode=1;});
