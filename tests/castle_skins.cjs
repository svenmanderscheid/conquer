'use strict';
// Isolated geometry, persistence-contract and asset checks; never changes an account.
const assert=require('assert'),fs=require('fs'),path=require('path'),vm=require('vm');
const {pathToFileURL}=require('url');
const root=path.resolve(__dirname,'..');
// Toon materials create painted canvases; geometry checks do not render them.
global.document={createElement:()=>({getContext:()=>({fillRect(){},beginPath(){},ellipse(){},fill(){}})})};
(async()=>{
 const moduleUrl=name=>pathToFileURL(path.join(root,'assets/city3d',name)).href;
 const T=await import(moduleUrl('vendor/three.module.js'));
 const {CASTLE_SKIN_IDS}=await import(moduleUrl('castle-effects.js'));
 const {buildFortress}=await import(moduleUrl('fortress-cartoon.js'));
 const sandbox={window:{}};vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/castle-skins.js'),'utf8'),sandbox);
 const catalog=sandbox.window.ConquerCastleSkins;
 assert.deepStrictEqual([...catalog.entries.map(e=>e.id)].sort(),[...CASTLE_SKIN_IDS].sort());
 assert.equal(catalog.entries.filter(e=>e.rarity==='legendary').length,10);
 assert.equal(catalog.entries.filter(e=>e.rarity==='mythic').length,7);
 const server=fs.readFileSync(path.join(root,'src/Game/Kingdom/KingdomService.php'),'utf8');
 const saveSkin=server.slice(server.indexOf('function saveSkin'),server.indexOf('function saveSkin')+1300);
 for(const retired of ['forest','royal','flame','grandeur','empyrean','sunshine','heavenly','bastion','magisters','frost','crescent','darkness','sunbless','ape','cloud','fafnir','moonlight']){assert(!catalog.ids.includes(retired));assert(!saveSkin.includes(`'${retired}'`),retired+' must no longer be selectable');}
 const scene=new T.Scene(),castle=buildFortress({parent:scene,flag:()=>new T.Mesh(new T.PlaneGeometry(1,1),new T.MeshBasicMaterial()),emblem:()=>null});
 for(const entry of catalog.entries){
  assert(saveSkin.includes(`'${entry.id}'`),`${entry.id} server persistence missing`);
  castle.userData.setCastleSkin(entry.id);assert.equal(castle.userData.castleSkin,entry.id);
  const visible=castle.children.filter(o=>o.visible);assert.equal(visible.length,1,`${entry.id} has overlapping castles`);
  const details=visible[0].userData.epicDetails;assert.equal(details.theme,entry.id);assert(details.pieces>40);
  assert(details.batches<=32,`${entry.id} unbounded ornament draw calls`);
  const bounds=new T.Box3();visible[0].updateWorldMatrix(true,true);
  visible[0].traverse(object=>{
   if(!object.isMesh||object.userData.storybookInk)return;
   for(let p=object;p;p=p.parent)if(p.userData.cosmeticEffect)return;
   for(const material of Array.isArray(object.material)?object.material:[object.material])assert(!material.isMeshStandardMaterial,`${entry.id} must use painted toon surfaces`);
   const geometry=object.geometry;
   if(object.isInstancedMesh){object.computeBoundingBox();bounds.union(object.boundingBox.clone().applyMatrix4(object.matrixWorld));}
   else{geometry.computeBoundingBox();bounds.union(geometry.boundingBox.clone().applyMatrix4(object.matrixWorld));}
   if(geometry.attributes.uv)assert([...geometry.attributes.uv.array].every(Number.isFinite));
  });
  assert(bounds.min.x>=castle.position.x-3.7&&bounds.max.x<=castle.position.x+3.7,`${entry.id} covers neighboring paths`);
  assert(bounds.max.z<=castle.position.z+4.1&&bounds.max.y<8.4,`${entry.id} exceeds the castle approach or camera framing`);
  let vertices=0;
  visible[0].traverse(o=>{if(o.geometry?.attributes.position){const coords=o.geometry.attributes.position.array;assert([...coords].every(Number.isFinite));vertices+=coords.length/3;}});
  assert(vertices>1000,`${entry.id} model missing`);
  if(entry.rarity!=='common'){
   castle.userData.animateCastle(0);const before=castle.userData.castleEffectState();
   const moving=visible[0].children.filter(o=>o.userData.animatedPart);
   const motionSnapshot=()=>{const state=[];moving.forEach(root=>root.traverse(o=>state.push([...o.position,...o.quaternion,...o.scale],o.geometry?.attributes.position?.array?Array.from(o.geometry.attributes.position.array):[])));return JSON.stringify(state);};
   const frameBefore=motionSnapshot();
   castle.userData.animateCastle(1.2);const after=castle.userData.castleEffectState();assert(after.phase>before.phase);
   assert.equal(after.rarity,entry.rarity);
   if(entry.rarity==='legendary'){assert.equal(after.particles,0);assert.equal(after.rings,0);assert.equal(after.glow,0);assert.equal(after.animations,1);assert.equal(moving.length,1,entry.id+' needs exactly one animated part');assert.notEqual(frameBefore,motionSnapshot(),entry.id+' ornament must actually move');}
   else assert(after.particles>=12);
   if(entry.rarity==='mythic'){assert.equal(after.rings,2);assert.equal(after.runes,12);assert.notEqual(before.glow,after.glow,'aura must visibly pulse');}
   if(entry.id==='dragon'){
    const dragon=visible[0].userData.dragonMotionState();assert.equal(moving.length,1);assert.equal(dragon.smoke,3);
    assert.notEqual(frameBefore,motionSnapshot(),'dragon wings, breath and smoke must actually move');assert(dragon.emberScale>.7);
   }
  }
  assert(castle.children.length<=4,'skin browsing leaks unlimited models');
  assert(fs.statSync(path.join(root,'assets/art/map',`castle-${entry.id}.png`)).size>5000);
  if(entry.rarity!=='common')assert(fs.statSync(path.join(root,'assets/art/map',`castle-${entry.id}.webp`)).size>5000);
  console.log(`PASS ${entry.id}: model, rarity/effects, server selection, image`);
 }
 castle.userData.setCastleSkin('not-a-skin');assert.equal(castle.userData.castleSkin,'default');
 // Rebuilding a skin after eviction must leave it drawable and animated.
 castle.userData.setCastleSkin('ironkeep');castle.userData.animateCastle(2);assert.equal(castle.userData.castleEffectState().phase,2);
 const scaffold=new T.Group();castle.add(scaffold);const placement=castle.position.clone();
 castle.userData.setCastleSkin('phoenix');
 assert(scaffold.visible&&scaffold.parent===castle,'skin switch hides or removes construction');
 assert(castle.position.equals(placement));assert.equal(castle.userData.building,'keep');
 assert.equal(castle.userData.castleModel().name,'castle-skin-phoenix');
 console.log('PASS stable building identity, placement and construction across skin changes');
 console.log('ALL CASTLE SKIN CHECKS PASSED');
})().catch(error=>{console.error(error);process.exitCode=1;});
