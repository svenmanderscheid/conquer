'use strict';
// Release gate for offline-rendered march loops. It never rewrites artwork.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),sharp=require('sharp');
const root=path.resolve(__dirname,'..'),directory=path.join(root,'assets','art','marches');
const definitions={
 ironkeep:256,rosehall:256,sandspire:256,tidewatch:256,winterhold:256,jadecourt:256,emberforge:256,ravenloft:256,
 clockwork:256,sapphire:256,astral:256,leviathan:256,yggdrasil:256,tempest:256,eclipse:256,
 phoenix:384,dragon:384
};
const files=id=>definitions[id]===384?{motion:`flight-${id}.webp`,still:`flight-${id}.png`}:{motion:`animated-march-${id}.webp`,still:`march-${id}.webp`};
(async()=>{
 const report=[];
 for(const [id,size] of Object.entries(definitions)){
  const names=files(id),motionFile=path.join(directory,names.motion),stillFile=path.join(directory,names.still);
  assert(fs.existsSync(stillFile),`${id}: static fallback ${names.still} is missing`);
  assert(fs.existsSync(motionFile),`${id}: articulated loop ${names.motion} is missing`);
  const bytes=fs.readFileSync(motionFile),meta=await sharp(bytes,{animated:true}).metadata();
  assert.equal(bytes.subarray(0,4).toString(),'RIFF',`${id}: motion asset is not RIFF WebP`);
  assert.equal(bytes.subarray(8,12).toString(),'WEBP',`${id}: motion asset is not WebP`);
  assert(bytes.includes(Buffer.from('ANIM')),`${id}: WebP has no animation header`);
  const frames=Number(meta.pages)||0;
  assert(frames>=8,`${id}: articulated loop needs at least 8 distinct poses, found ${frames}`);
  assert.equal(meta.width,size,`${id}: width must stay ${size}px`);
  assert.equal(meta.pageHeight||meta.height,size,`${id}: each frame must stay ${size}px high`);
  assert(meta.hasAlpha,`${id}: transparent background is required`);
  assert(bytes.length<=1_000_000,`${id}: ${bytes.length} bytes exceeds the mobile budget`);
  report.push({id,frames,width:meta.width,height:meta.pageHeight||meta.height,bytes:bytes.length,still:names.still,motion:names.motion});
 }
 const totalBytes=report.reduce((sum,row)=>sum+row.bytes,0);
 assert(totalBytes<=12_000_000,`animated march library uses ${totalBytes} bytes; mobile budget is 12000000`);
 console.log(JSON.stringify({status:'PASS',assets:report,totalBytes},null,2));
})().catch(error=>{console.error(error);process.exitCode=1;});
