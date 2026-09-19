'use strict';
// Reproducibly turns the retained Imagegen source cutouts into map-ready sprites.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),sharp=require('sharp');
const root=path.resolve(__dirname,'..');
const ids=['ironkeep','rosehall','sandspire','tidewatch','winterhold','jadecourt','emberforge','ravenloft','clockwork','sapphire','astral','leviathan','yggdrasil','tempest','eclipse'];
const sourceRoot=path.join(root,'asset-workflow','01-source','marches');
const outputRoot=path.join(root,'assets','art','marches');
const staging=path.join(root,'artifacts','march-skins','staging');
fs.mkdirSync(outputRoot,{recursive:true});fs.mkdirSync(staging,{recursive:true});

(async()=>{
 const report=[];
 for(const id of ids){
  const source=path.join(sourceRoot,id,'march-source.png');
  assert(fs.existsSync(source),`${id}: retained source is missing`);
  const input=sharp(source).ensureAlpha().trim({background:{r:0,g:0,b:0,alpha:0},threshold:8});
  const trimmed=await input.png().toBuffer({resolveWithObject:true});
  const scale=Math.min(448/trimmed.info.width,448/trimmed.info.height);
  const width=Math.max(1,Math.round(trimmed.info.width*scale));
  const height=Math.max(1,Math.round(trimmed.info.height*scale));
  const sprite=await sharp(trimmed.data).resize(width,height,{fit:'fill'}).png().toBuffer();
  const left=Math.floor((512-width)/2),top=Math.floor((512-height)/2);
  const target=path.join(staging,`march-${id}.webp`);
  await sharp({create:{width:512,height:512,channels:4,background:{r:0,g:0,b:0,alpha:0}}})
   .composite([{input:sprite,left,top}]).webp({quality:88,alphaQuality:100,effort:6}).toFile(target);
  const meta=await sharp(target).metadata(),stats=await sharp(target).stats();
  assert.equal(meta.width,512);assert.equal(meta.height,512);assert(meta.hasAlpha);
  assert.equal(stats.channels[3].min,0);assert(stats.channels[3].max>=250);
  report.push({id,width,height,left,top,bytes:fs.statSync(target).size});
 }
 for(const row of report)fs.copyFileSync(path.join(staging,`march-${row.id}.webp`),path.join(outputRoot,`march-${row.id}.webp`));
 const reportFile=path.join(root,'artifacts','march-skins','premium-art-validation.json');
 fs.writeFileSync(reportFile,JSON.stringify({generatedAt:new Date().toISOString(),assets:report},null,2)+'\n');
 console.log(JSON.stringify({count:report.length,totalBytes:report.reduce((sum,row)=>sum+row.bytes,0),report:reportFile},null,2));
})().catch(error=>{console.error(error);process.exitCode=1;});
