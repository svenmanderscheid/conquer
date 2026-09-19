'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),crypto=require('crypto'),sharp=require('sharp');
const root=path.resolve(__dirname,'..');
const ids=['ironkeep','rosehall','sandspire','tidewatch','winterhold','jadecourt','emberforge','ravenloft','clockwork','sapphire','astral','leviathan','yggdrasil','tempest','eclipse'];
(async()=>{
 const hashes=new Set();
 for(const id of ids){
  const file=path.join(root,'assets','art','marches',`march-${id}.webp`);assert(fs.existsSync(file),`${id}: sprite missing`);
  const buffer=fs.readFileSync(file),meta=await sharp(buffer).metadata(),stats=await sharp(buffer).stats();
  assert.deepEqual([meta.width,meta.height],[512,512],`${id}: map sprite must be 512 square`);
  assert(meta.hasAlpha&&stats.channels[3].min===0&&stats.channels[3].max>=250,`${id}: sprite needs real transparency`);
  assert(buffer.length<400000,`${id}: sprite is too large for mobile`);
  hashes.add(crypto.createHash('sha256').update(buffer).digest('hex'));
 }
 assert.equal(hashes.size,ids.length,'every premium march needs distinct artwork');
 console.log(`PASS ${ids.length} distinct premium creature sprites are transparent, 512px and mobile-sized.`);
})().catch(error=>{console.error(error);process.exitCode=1;});
