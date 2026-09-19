'use strict';
// Resize and encode the original Imagegen cutouts without changing their artwork or alpha.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const sharp=require('sharp');
const root=path.resolve(__dirname,'..');
const manifest=JSON.parse(fs.readFileSync(path.join(root,'docs/march-skin-art-prompts.json'),'utf8'));
const output=path.join(root,'assets/art/marches');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const report=[];
 for(const asset of manifest.assets){
  if(!asset.source)continue;
  assert(/^[a-z]+$/.test(asset.id));
  const image=sharp(asset.source),meta=await image.metadata();
  assert(meta.hasAlpha,asset.id+': source must have real transparency');
  const stats=await image.stats();
  assert(stats.channels[3].min===0&&stats.channels[3].max===255,asset.id+': alpha must contain transparent and opaque pixels');
  const target=path.join(output,'march-'+asset.id+'.webp');
  await image.resize(512,512,{fit:'inside',withoutEnlargement:true}).webp({quality:88,alphaQuality:100,effort:6}).toFile(target);
  const saved=await sharp(target).metadata();
  assert(saved.hasAlpha&&saved.width<=512&&saved.height<=512);
  report.push({id:asset.id,width:saved.width,height:saved.height,bytes:fs.statSync(target).size,hasAlpha:saved.hasAlpha});
 }
 fs.mkdirSync(path.join(root,'artifacts/march-skins'),{recursive:true});
 fs.writeFileSync(path.join(root,'artifacts/march-skins/art-validation.json'),JSON.stringify(report,null,2)+'\n');
 console.log(JSON.stringify({count:report.length,totalBytes:report.reduce((s,a)=>s+a.bytes,0),assets:report},null,2));
})().catch(error=>{console.error(error);process.exitCode=1;});
