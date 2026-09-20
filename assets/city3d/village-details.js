import * as T from './vendor/three.module.js';
import {CASTLE_SKIN_IDS} from './castle-effects.js?v=collection6';
import {storybookMaterials as M} from './storybook-style.js?v=storybook1';

const geometry={box:new T.BoxGeometry(1,1,1),ball:new T.SphereGeometry(1,8,6),cyl:new T.CylinderGeometry(1,1,1,10),cone:new T.ConeGeometry(1,1,12),rock:new T.DodecahedronGeometry(1,0)};
const roofShape=new T.Shape();roofShape.moveTo(-.5,0);roofShape.lineTo(0,.5);roofShape.lineTo(.5,0);roofShape.closePath();
geometry.roof=new T.ExtrudeGeometry(roofShape,{depth:1,bevelEnabled:false});geometry.roof.translate(0,0,-.5);

// Architectural additions belong to the existing building roots, so picking and
// saved building levels still use the same fourteen authoritative buildings.
export function enrichVillage({scene,buildings}){
 let parts=0,batchCount=0;
 function decorate(root,code){
  const batches=new Map(),dummy=new T.Object3D();
  const add=(shape,color,x,y,z,sx,sy,sz,ry=0,rz=0)=>{const key=shape+color;if(!batches.has(key))batches.set(key,{shape,color,matrices:[]});dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(0,ry,rz);dummy.updateMatrix();batches.get(key).matrices.push(dummy.matrix.clone());parts++;};
  const box=(color,x,y,z,w,h,d,ry=0,rz=0)=>add('box',color,x,y,z,w,h,d,ry,rz);
  const barrel=(x,z)=>{add('cyl','timber',x,.51,z,.3,.75,.3);for(const y of [.21,.76])add('cyl','dark',x,y,z,.315,.055,.315);};
  function crate(x,z,y=.43){box('timber',x,y,z,.63,.63,.63);for(const side of [-1,1]){box('wood',x+side*.24,y,z,.06,.65,.65);box('wood',x,y+side*.24,z,.65,.05,.65);}box('wood',x,y,z+.34,.72,.06,.04,0,.73);}
  function planter(x,z,w=1.5){box('stone',x,.24,z,w,.35,.57);for(let i=0;i<5;i++){add('ball','leaf',x-w*.39+i*w*.195,.48,z,.25,.27,.25);add('ball',i%2?'red':'glow',x-w*.39+i*w*.195,.73,z,.065,.07,.065);}}
  function fence(x,z,width,ry=0){for(let i=0;i<=Math.ceil(width/.6);i++){const n=-width/2+i*width/Math.ceil(width/.6);box('timber',x+Math.cos(ry)*n,.5,z-Math.sin(ry)*n,.085,.82,.085);}for(const y of [.3,.66])box('wood',x,y,z,width,.08,.08,ry);}
  function lamp(x,z){add('cyl','wood',x,1.12,z,.055,2,.055);box('gold',x,2.17,z,.34,.08,.34);box('glow',x,1.97,z,.19,.32,.19);add('cone','blue',x,2.31,z,.26,.24,.26);}
  function roof(x,y,z,w,d,color='orange'){add('roof',color,x,y,z,w,1,d);box('dark',x,y+.51,z,.09,.09,d+.12);for(const side of [-1,1])for(let row=0;row<3;row++)box(color==='blue'?'blueDark':'redDark',x+side*w*(row+.5)/6,y+.53-(row+.5)/6,z,Math.hypot(w/2,.5)/3,.035,d+.06,0,-side*Math.atan2(.5,w/2));}
  function cart(x,z,load='timber'){
   box('wood',x,.47,z,1.35,.15,1.9);for(const side of [-1,1]){box('timber',x+side*.65,.75,z,.08,.55,1.9);for(const dz of [-.65,.65]){add('cyl','dark',x+side*.79,.45,z+dz,.39,.11,.39,0,Math.PI/2);add('cyl','gold',x+side*.86,.45,z+dz,.11,.13,.11,0,Math.PI/2);}}
   for(const dz of [-.9,.9])box('wood',x,.75,z+dz,1.3,.5,.07);for(let i=0;i<4;i++)add('rock',load,x+(i%2-.5)*.52,.89,z+(Math.floor(i/2)-.5)*.55,.3,.32,.35);box('wood',x,.42,z+1.6,.11,.09,1.5);
  }
  function cottage(x,z,color='orange',s=1){
   const y=.22;box('stone',x,y,z,2.8*s,.3,2.5*s);box('cream',x,1.16*s,z,2.55*s,1.9*s,2.25*s);roof(x,2.1*s,z,3.05*s,2.65*s,color);
   box('wood',x,.92*s,z+1.15*s,.65*s,1.35*s,.08);box('gold',x+.2*s,.89*s,z+1.21*s,.055,.055,.05);
   for(const side of [-1,1]){box('dark',x+side*.83*s,1.34*s,z+1.145*s,.53*s,.7*s,.09);box('glow',x+side*.83*s,1.34*s,z+1.2*s,.39*s,.54*s,.03);box('wood',x+side*.83*s,1.34*s,z+1.225*s,.035,.56*s,.025);box('wood',x+side*.83*s,1.32*s,z+1.228*s,.41*s,.035,.025);box('timber',x+side*1.18*s,1.15*s,z+1.16*s,.09,1.83*s,.08);}
   box('stone',x+.81*s,2.53*s,z-.57*s,.41*s,.85*s,.43*s);box('trim',x+.81*s,2.98*s,z-.57*s,.53*s,.15,.55*s);box('dark',x+.81*s,3.06*s,z-.57*s,.31*s,.02,.3*s);
   planter(x-1.2*s,z+1.5*s,.8*s);
  }
  function flush(){for(const {shape,color,matrices} of batches.values()){const batch=new T.InstancedMesh(geometry[shape],M[color],matrices.length);matrices.forEach((matrix,i)=>batch.setMatrixAt(i,matrix));batch.computeBoundingSphere();batch.receiveShadow=true;batch.castShadow=false;batch.name='village-detail-'+code+'-'+shape+'-'+color;root.add(batch);batchCount++;}}
  if(code.startsWith('home-')){
   cottage(0,0,code.slice(5),.9);fence(0,-1.8,3.9);barrel(1.75,.8);planter(-1.8,0,.9);
  }else if(code==='castle'){
   for(let x=-6;x<=6;x++)for(let z=-5;z<=6;z++)if(Math.abs(x)>4||z>4)box((x+z)%3?'stone':'trim',x,.06,z,.94,.075,.94);
   for(const side of [-1,1]){planter(side*5.2,4.9,2);lamp(side*5.3,5.7);fence(side*5.9,.2,8.4,Math.PI/2);for(let i=0;i<6;i++)add('ball','leaf',side*5.85,.52,-3.6+i*1.1,.56,.7,.55);}
  }else if(code==='academy'){
   add('cyl','stone',-3,.27,-.5,1.35,.34,1.35);add('cyl','cream',-3,1.3,-.5,1.08,2,1.08);add('cone','purple',-3,2.76,-.5,1.42,1.14,1.42);add('ball','gold',-3,3.41,-.5,.1,.13,.1);
   for(const x of [-3.5,-2.5]){box('dark',x,1.4,.46,.36,.7,.06);box('glow',x,1.4,.5,.23,.55,.035);}
   box('timber',.7,.71,2.8,1.15,.13,.75);for(const side of [-1,1])box('wood',.7+side*.45,.43,2.8,.08,.57,.08);box('trim',.47,.84,2.8,.41,.08,.54,0,-.13);box('trim',.9,.84,2.8,.41,.08,.54,0,.13);
   add('cyl','gold',-3.2,3.6,-.45,.08,.68,.08);add('cyl','blueDark',-3.1,4,-.4,.12,.83,.12,0,-.9);planter(2.7,2.2);lamp(-2.2,2.7);
  }else if(code==='hospital'){
   for(const side of [-1,1]){fence(side*3.6,0,4.6,Math.PI/2);for(let i=0;i<3;i++)planter(side*3.15,-1.4+i*1.1,.6);}
   box('trim',3,.67,2.7,1.4,.15,.67);for(const x of [2.5,3.5])box('stone',x,.38,2.7,.13,.6,.6);lamp(-3,3);
  }else if(code==='storage'){crate(-3.15,1.2);crate(-3.15,1.2,1.1);for(let i=0;i<5;i++)add('ball','wheat',-3.35+(i%2)*.55,.48+Math.floor(i/4)*.5,2.2+Math.floor(i/2)*.4,.32,.49,.31);
  }else if(code==='treasure_house'){
   for(let i=0;i<10;i++){const a=i/10*Math.PI*2;add('ball','gold',Math.sin(a)*.9,2.98,Math.cos(a)*.9,.07,.36,.07);}
   for(const side of [-1,1]){box('stone',side*3,.4,1.6,.75,.6,.75);add('ball','gold',side*3,.96,1.6,.24,.35,.24);planter(side*3,-.7,1.4);}
  }else if(code==='hall_of_alliance'){
   for(const side of [-1,1]){box('stone',side*2.7,.35,3.6,.9,.5,.9);add('cyl','cream',side*2.7,.99,3.6,.2,.82,.2);add('ball','gold',side*2.7,1.6,3.6,.23,.28,.23);box('blue',side*2.7,1.15,3.84,.45,.6,.09);}
   for(let i=0;i<4;i++)box(i%2?'stone':'trim',0,.07,3.25+i*.63,2.5,.12,.58);planter(-3,-.5);planter(3,-.5);
  }else if(code==='trading_post'){
   // A broad paved market court makes every stall and merchant prop part of
   // one legible destination instead of a loose roadside building.
   add('cyl','stone',0,.04,0,3.7,.08,3.35);
   for(let i=0;i<3;i++){crate(3.3,-1+i*.67);barrel(-3.3,1.3+i*.7);}lamp(2.3,3.25);lamp(-2.3,3.25);
  }else if(code==='farm'){
   for(const z of [-1.6,1.1]){box('wood',5,.15,z,3.8,.12,2);for(let x=0;x<8;x++)for(let row=0;row<4;row++){add('cyl','wheat',3.45+x*.44,.48,z-.7+row*.45,.033,.62,.033);add('ball',z<0?'wheat':'leafLight',3.45+x*.44,.84,z-.7+row*.45,.11,.17,.11);}}
   fence(5,2.45,4.2);barrel(3.4,3.05);
  }else if(code==='lumber_camp'){
   for(let i=0;i<9;i++){const x=-3.1+(i%3)*.49,y=.37+Math.floor(i/3)*.38;add('cyl','wood',x,y,-1.5,.24,2.6,.24,0,Math.PI/2);add('cyl','wheat',x+1.32,y,-1.5,.19,.035,.19,0,Math.PI/2);}
   fence(-3,-3.2,4);barrel(1,2.6);
  }else if(code==='quarry'||code==='gold_mine'){
   for(const x of [-.52,.52])box('dark',x,.19,3.65,.055,.07,3.2);for(let i=0;i<7;i++)box('timber',0,.14,2.2+i*.48,1.5,.06,.15);
   for(let i=0;i<6;i++)add('rock',code==='gold_mine'?'gold':'stone',-3+(i%2)*.6,.45+Math.floor(i/4)*.4,-.7+Math.floor(i/2)*.7,.55,.4,.6);
  }
  flush();
 }
 for(const [code,root] of buildings)decorate(root,code);
 // Keep the eastern dwelling; the military lawn stays open between its three
 // training buildings and their entrance paths.
 const homes=new T.Group();homes.name='village-residential-courts';scene.add(homes);
 for(const [x,z,color] of [[17,16,'red']]){
  const lot=new T.Group();lot.position.set(x,.12,z);homes.add(lot);
  const tiles=new T.Mesh(new T.CylinderGeometry(3.9,4,.09,8),M.stone);tiles.position.y=.04;tiles.scale.z=.76;tiles.receiveShadow=true;lot.add(tiles);
  // Reuse the same detailed dwelling vocabulary as the market's merchant home.
  decorate(lot,'home-'+color);lot.scale.setScalar(.9);lot.rotation.y=.18;
 }
 return {parts,batches:batchCount,homes:1};
}

// Cosmetics never mutate shared terrain pigments or saved gameplay values.
export function createVillageSkins(buildings){
 const families=new Map([['blue','blue'],['blueLight','blueLight'],['blueDark','blueDark'],['red','red'],['redDark','redDark'],['purple','purple'],['purpleDark','purpleDark'],['teal','teal'],['orange','orange'],['orangeDark','orangeDark'],['gold','gold']].map(([name,key])=>[M[name],key]));
 const palette={jadecourt:{blue:'#497955',blueLight:'#78a769',blueDark:'#284b3c',red:'#69824a',redDark:'#435731',purple:'#487269',purpleDark:'#2b4a46',teal:'#498565',orange:'#b99a4b',orangeDark:'#826736',gold:'#d0b271'},sapphire:{blue:'#6253ab',blueLight:'#9276d0',blueDark:'#3c346f',red:'#9b405f',redDark:'#67263f',purple:'#8955b9',purpleDark:'#522d7b',teal:'#526da3',orange:'#c29a48',orangeDark:'#8b642c',gold:'#ffce57'}};
 const clones=new Map(),records=[];let current='default';
 for(const [,root] of buildings)root.traverse(object=>{
  if(!object.isMesh||object.userData.storybookInk)return;
  const convert=source=>{let family=families.get(source);if(!family&&object.geometry.type==='PlaneGeometry'&&source.color)family='teal';if(!family)return source;
   if(!clones.has(source)){const material=source.clone();material.onBeforeCompile=source.onBeforeCompile;material.customProgramCacheKey=source.customProgramCacheKey;clones.set(source,material);records.push({material,family,original:source.color.clone()});}return clones.get(source);};
  object.material=Array.isArray(object.material)?object.material.map(convert):convert(object.material);
 });
 return {apply(skin){if(!CASTLE_SKIN_IDS.includes(skin))return false;current=skin;for(const [code,root] of buildings)if(code==='castle')root.userData.setCastleSkin?.(skin);for(const {material,family,original} of records)material.color.copy(palette[skin]?new T.Color(palette[skin][family]):original);return true;},animate(time){for(const [code,root] of buildings)if(code==='castle')root.userData.animateCastle?.(time);},getState:()=>({skin:current,effect:buildings.find(([code])=>code==='castle')?.[1].userData.castleEffectState?.()??null,castle:buildings.find(([code])=>code==='castle')?.[1].userData.castleSkin??null,materials:records.length})};
}
