'use strict';
// Run with the repository served at CHARACTER_BASE; --export writes production GLBs.
const {chromium}=require('playwright'),assert=require('assert/strict'),fs=require('fs'),path=require('path');
const base=process.env.CHARACTER_BASE||'http://127.0.0.1:19347';
const output=path.resolve(__dirname,'../artifacts/character-family');fs.mkdirSync(output,{recursive:true});
(async()=>{const browser=await chromium.launch({channel:'chrome',headless:true});try{
 const page=await browser.newPage({viewport:{width:1100,height:820}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/assets/city3d/character-workshop.html');await page.waitForFunction(()=>window.characterWorkshop);
 const report=[];
 for(const role of ['infantry','guard','worker','citizen']){
  await page.selectOption('#role',role);await page.selectOption('#motion','idle');await page.waitForTimeout(200);
  const stats=await page.evaluate(()=>{const u=characterWorkshop.character,g=u.mesh.geometry,w=g.attributes.skinWeight,s=g.attributes.skinIndex;
   for(let i=0;i<w.count;i++){if(w.getX(i)!==1||s.getX(i)>=u.skeleton.bones.length)throw Error('Invalid skin weights');}
   return {triangles:g.index.count/3,bones:u.skeleton.bones.map(b=>b.name),meshes:u.root.children.filter(c=>c.isMesh).length};});
  assert(stats.triangles<12000);assert.equal(stats.meshes,2);assert.equal(stats.bones.length,16);
  const bytes=Buffer.from(await page.evaluate(()=>Array.from(new Uint8Array(characterWorkshop.exportGLB()))));
  assert.equal(bytes.readUInt32LE(0),0x46546c67);assert.equal(bytes.readUInt32LE(8),bytes.length);
  const jsonLength=bytes.readUInt32LE(12),doc=JSON.parse(bytes.subarray(20,20+jsonLength).toString());
  assert.deepEqual(doc.animations.map(a=>a.name),['idle','walk','attack','work']);assert.equal(doc.skins[0].joints.length,16);
  const bin=20+jsonLength+8;for(const v of doc.bufferViews){assert.equal(v.byteOffset%4,0);assert(v.byteOffset+v.byteLength<=doc.buffers[0].byteLength);}
  for(const a of doc.accessors){const size={SCALAR:1,VEC2:2,VEC3:3,VEC4:4,MAT4:16}[a.type],byteSize={5123:2,5125:4,5126:4}[a.componentType];assert.equal(a.count*size*byteSize,doc.bufferViews[a.bufferView].byteLength);}
  for(const a of doc.animations)for(const c of a.channels)assert(doc.nodes[c.target.node].name);
  assert(bin+doc.buffers[0].byteLength<=bytes.length);
  const exportPath=process.argv.includes('--export')?path.resolve(__dirname,'../assets/art/characters'):output;fs.mkdirSync(exportPath,{recursive:true});fs.writeFileSync(path.join(exportPath,role+'.glb'),bytes);
  report.push({role,triangles:stats.triangles,bones:16,bytes:bytes.length});
  await page.locator('#stage').screenshot({path:path.join(output,role+'.png')});
 }
 await page.selectOption('#role','infantry');
 for(const animation of ['walk','attack','work']){
  await page.selectOption('#motion',animation);await page.waitForTimeout(240);
  const a=await page.evaluate(()=>characterWorkshop.character.bones.upperR.quaternion.toArray());await page.waitForTimeout(180);
  const b=await page.evaluate(()=>characterWorkshop.character.bones.upperR.quaternion.toArray());assert.notDeepEqual(a,b,animation+' changes actual rig');
 }
 await page.check('#pause');await page.waitForTimeout(200);const a=await page.locator('#stage').screenshot();await page.waitForTimeout(200);assert(a.equals(await page.locator('#stage').screenshot()),'pause freezes pose');
 await page.emulateMedia({reducedMotion:'reduce'});assert(await page.isChecked('#pause'));
 await page.selectOption('#motion','idle');
 for(const [width,height]of [[1100,820],[390,844],[320,700],[844,390]]){
  await page.setViewportSize({width,height});await page.waitForTimeout(100);
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no horizontal overflow');
  for(const selector of ['#role','#motion','#reset','#export']){await page.locator(selector).scrollIntoViewIfNeeded();const b=await page.locator(selector).boundingBox();assert(b.x>=0&&b.x+b.width<=width+1&&b.height>=44);}
  await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(output,`workshop-${width}.png`)});
 }
 const city=await page.evaluate(async()=>{const T=await import('/assets/city3d/vendor/three.module.js'),{buildCityLife}=await import('/assets/city3d/city-life.js');const life=buildCityLife({scene:new T.Scene()});life.animate(0);const before=life.characters.map(c=>c.root.position.toArray());life.animate(.08);life.animate(.16);return {count:life.peopleCount,roles:life.characters.map(c=>c.root.name),moved:life.characters.filter((c,i)=>c.root.position.toArray().some((v,j)=>v!==before[i][j])).length};});
 assert.equal(city.count,22);assert.equal(city.roles.filter(x=>x==='character-guard').length,3);assert.equal(city.moved,15,'only pedestrians and guards move along routes');
 assert.deepEqual(errors,[]);fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({models:report,city},null,2));console.log(JSON.stringify({models:report,city}));
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
