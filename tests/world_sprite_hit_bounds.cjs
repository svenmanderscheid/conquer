'use strict';
// No browser/server: exercise real binding, source races and image alpha math.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'..'),pending=new Map(),loads=new Map(),reads=new Map();
class ImageStub {
 set src(src){this.source=src;loads.set(src,(loads.get(src)||0)+1);const waiting=pending.get(src)||[];waiting.push(this);pending.set(src,waiting);}
}
const sandbox={Image:ImageStub,console,window:{ConquerCastleSkins:{get:()=>({id:'test-skin'})}},getComputedStyle:img=>({width:String(img.boxWidth),height:String(img.boxHeight)}),document:{createElement:()=>({getContext(){let image;return{drawImage:value=>{image=value;},getImageData:()=>{reads.set(image.source,(reads.get(image.source)||0)+1);if(image.fail)throw Error('unreadable pixels');return{data:image.pixels};}};}})}};
vm.createContext(sandbox);
const source=fs.readFileSync(path.join(root,'assets/js/world-painted.js'),'utf8').replace(/^import .*;\r?\n/gm,'').replace(/export /g,'');
vm.runInContext(source+'\nglobalThis.testBind=bind;',sandbox);
function marker(src,width=100,height=100){
 const properties=new Map(),listeners=new Map(),img={src,boxWidth:width,boxHeight:height,style:{cssText:''},getAttribute:name=>name==='src'?img.src:null,addEventListener:(name,fn)=>listeners.set(name,fn)};
 const button={dataset:{},style:{setProperty:(k,v)=>properties.set(k,v),removeProperty:k=>properties.delete(k)},querySelector:selector=>selector==='img'?img:null};
 return{button,img,properties,load:()=>listeners.get('load')?.()};
}
const target={kind:'monsters',art:'regional-test.png',data:{}};
const bind=(item,t=target)=>sandbox.testBind(item.button,t);
function resolve(src,{width=10,height=10,points=[],fail=false}={}){
 const pixels=new Uint8ClampedArray(width*height*4);for(const[x,y,alpha=255]of points)pixels[(y*width+x)*4+3]=alpha;
 for(const image of pending.get(src)||[]){Object.assign(image,{naturalWidth:width,naturalHeight:height,pixels,fail});image.onload();}pending.delete(src);
}
async function settle(){for(let i=0;i<5;i++)await Promise.resolve();}
function insets(item){return item.properties.get('--sprite-hit-clip').match(/[\d.]+(?=%)/g).map(Number);}
(async()=>{
 const a=marker('/a.png'),b=marker('/a.png');bind(a);bind(b);
 assert.equal(a.button.dataset.spriteHitBounds,'pending');assert.equal(loads.get('/a.png'),1,'same source shares one image read');
 resolve('/a.png',{points:[[2,1,1],[7,8,255]]});await settle();
 assert.deepEqual(insets(a),[10,20,10,20]);assert.deepEqual(insets(b),[10,20,10,20]);assert.equal(reads.get('/a.png'),1);
 assert.equal(a.img.style.clipPath,undefined,'clip is not copied into animated canvas inline geometry');
 assert.equal(a.img.style.cssText,'','source image geometry is unchanged');
 bind(a);assert.equal(reads.get('/a.png'),1,'polling does not rescan pixels');
 const tall=marker('/a.png',100,200);bind(tall);await settle();assert.deepEqual(insets(tall),[30,20,30,20],'contain letterboxing preserves every alpha pixel');
 // A 1-tile neighbour lies outside the clipped orc image, but the head remains clickable.
 const orc=marker('/orc.png',145/70*44,145/70*44);bind(orc);
 resolve('/orc.png',{width:512,height:512,points:[[56,54,1],[436,464,255]]});await settle();
 const cut=insets(orc),size=145/70,left=-size/2+size*cut[3]/100;
 assert(left>-1&&left<0,'fully transparent orc margin no longer reaches adjacent tile centre');
 assert(cut[0]/100<.18&&1-cut[2]/100>.18,'existing upper-body image tap remains inside hit bounds');
 // All village kinds expose their visible roof above the occupied ground tiles.
 // A roof in the northern neighbour's tile must remain part of this castle.
 const villages=['home','players','neutral_villages'].map(kind=>{const item=marker('/castle-roof.png',3.7125*44,3.78*44);bind(item,{kind,data:{city_skin:'test-skin'}});return item;});
 assert(villages.every(item=>item.button.dataset.spriteHitBounds==='pending'));
 assert.equal(loads.get('/castle-roof.png'),1,'village kinds share the castle alpha read');
 resolve('/castle-roof.png',{width:100,height:100,points:[[20,5],[79,89]]});await settle();
 for(const village of villages){
  const inset=insets(village),box=village.img,ratio=Math.min(box.boxWidth/100,box.boxHeight/100),letterbox=(box.boxHeight-100*ratio)/2;
  assert(Math.abs(inset[0]/100*box.boxHeight-(letterbox+5*ratio))<.00001,'castle contain padding is excluded');
  const imageTop=1.5*44-.245*44-box.boxHeight,roof=imageTop+inset[0]/100*box.boxHeight;
  assert(roof<-1.5*44,'visible castle roof remains hittable above the footprint');
  assert.equal(village.properties.get('--sprite-hit-clip').startsWith('inset('),true);
  assert.equal(village.img.style.cssText,'','castle illustration geometry stays unchanged');
 }
 assert.equal(reads.get('/castle-roof.png'),1);
 // Resolve old and new sources out of order; old alpha data cannot win.
 const changed=marker('/old.png');bind(changed);changed.img.src='/new.png';bind(changed);
 resolve('/new.png',{points:[[0,0],[9,9]]});await settle();assert.deepEqual(insets(changed),[0,0,0,0]);
 resolve('/old.png',{points:[[4,4],[5,5]]});await settle();assert.deepEqual(insets(changed),[0,0,0,0]);
 // Source replacements made between world polls are caught by the image load event.
 changed.img.src='/event.png';changed.load();assert.equal(changed.properties.has('--sprite-hit-clip'),false);
 resolve('/event.png',{points:[[3,2],[8,7]]});await settle();assert.deepEqual(insets(changed),[20,10,20,30]);
 bind(changed,{kind:'charms',data:{}});assert.equal(changed.properties.has('--sprite-hit-clip'),false);assert.equal(changed.button.dataset.spriteHitBounds,undefined);
 const blank=marker('/blank.png');bind(blank);resolve('/blank.png');await settle();assert.deepEqual(insets(blank),[50,50,50,50],'invisible image cannot intercept neighbours');
 const failed=marker('/failed.png');bind(failed);resolve('/failed.png',{fail:true});await settle();assert.equal(failed.button.dataset.spriteHitBounds,undefined,'failed read does not leave image hits pending');
 assert.equal(failed.properties.has('--sprite-hit-clip'),false);
 const css=fs.readFileSync(path.join(root,'assets/css/world-atlas.css'),'utf8');
 const hitRule=css.match(/\.atlas-marker:is\(([^)]+)\)>img\{([^}]+)\}/);
 assert(hitRule,'hit clip applies to images, never visible animation canvases');
 for(const kind of ['monsters','nodes','home','players','neutral_villages'])assert(hitRule[1].split(',').includes(`.atlas-marker--${kind}`),`${kind} artwork forwards taps to its marker`);
 assert.match(hitRule[2],/pointer-events:auto!important/);assert.match(hitRule[2],/clip-path:var\(--sprite-hit-clip,none\)/);
 console.log('PASS world sprite hit bounds: faint pixels, shared source cache, neighbouring charm centre, village roofs, portrait taps, letterboxing, source races, blank and unreadable images.');
})().catch(error=>{console.error(error);process.exitCode=1;});
