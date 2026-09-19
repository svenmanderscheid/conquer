import * as T from './vendor/three.module.js';
import {storybookColors,createStorybookMaterial,addStorybookOutline} from './storybook-style.js?v=storybook5';

const themes={
 default:['#e9e1d2','#2a72c9','#e4af38','#fff6df'],
 ironkeep:['#9ca7ac','#3d667f','#deb161','#e4ded0'],
 rosehall:['#f6e7d8','#b64f7b','#dfb45f','#f0a7b5'],
 sandspire:['#e6c18a','#398c89','#ddb057','#f7e2b6'],
 tidewatch:['#ece4d2','#337b96','#d6a34d','#79bec7'],
 winterhold:['#d6e5e2','#41819f','#c2d4dd','#f1f6e9'],
 jadecourt:['#ebe1ca','#36826a','#e3b85e','#a8c48b'],
 emberforge:['#756d69','#b96337','#d99b49','#edb15b'],
 ravenloft:['#9b969c','#765591','#c6a475','#d5bdd9'],
 clockwork:['#e7d4ad','#367b76','#c79248','#f3d27c'],
 sapphire:['#ece4d6','#347ed0','#e8b955','#9dcfe9'],
 phoenix:['#776b68','#c74730','#e8b148','#ffe5a0'],
 astral:['#ede3c9','#4662aa','#e1b65c','#bedbea'],
 leviathan:['#e5dfc8','#3a989a','#d8b06b','#f2a5a0'],
 yggdrasil:['#987349','#4c874c','#d4b46a','#bdd386'],
 tempest:['#d9e3df','#4b80b6','#dbb963','#b4e8ed'],
 eclipse:['#776b83','#7758a6','#d5ae63','#c7a9de'],
 dragon:['#eadfc9','#2c86d7','#d9a74e','#d65c2d']
};

// Every theme has its own architecture. All additions remain inside the castle
// plot; the open front approach, saved building and animation hooks are retained.
export function addEpicCastleDetails(root,skin){
 if(root.userData.epicDetails||!themes[skin])return;
 const [stone,accent,gold,light]=themes[skin];
 const colors={stone,accent,gold,light,ink:storybookColors.dark,wood:storybookColors.wood,leaf:storybookColors.leaf,cream:storybookColors.cream};
 const group=new T.Group();group.name=`epic-architecture-${skin}`;root.add(group);
 const materials=new Map(),batches=new Map();let pieces=0,uniqueTriangles=0;
 const sourceMaterials=new Map(),oldMaterials=new Set();
 // Older skins used physical materials without UVs. Give their architecture
 // the same matte, subtly painted toon surface as the surrounding village.
 root.traverse(object=>{
  if(!object.isMesh||object.userData.storybookInk)return;
  const convert=source=>{
   if(!source.isMeshStandardMaterial)return source;
   if(!sourceMaterials.has(source)){
    sourceMaterials.set(source,createStorybookMaterial(source.color,{side:source.side,transparent:source.transparent,opacity:source.opacity,emissive:source.emissive,emissiveIntensity:Math.min(.2,source.emissiveIntensity||0)}));oldMaterials.add(source);
   }
   return sourceMaterials.get(source);
  };
  object.material=Array.isArray(object.material)?object.material.map(convert):convert(object.material);
  const geometry=object.geometry,p=geometry.attributes.position,n=geometry.attributes.normal;
  if(!geometry.attributes.uv&&p&&n){
   const uv=new Float32Array(p.count*2);
   for(let i=0;i<p.count;i++){const vertical=Math.abs(n.getY(i))>.65,side=Math.abs(n.getX(i))>.65;uv[i*2]=(side?p.getZ(i):p.getX(i))*.33;uv[i*2+1]=(vertical?p.getZ(i):p.getY(i))*.33;}
   geometry.setAttribute('uv',new T.BufferAttribute(uv,2));
  }
 });
 oldMaterials.forEach(material=>material.dispose());
 function material(color){if(!materials.has(color))materials.set(color,createStorybookMaterial(colors[color]||color));return materials.get(color);}
 const shapes={box:new T.BoxGeometry(1,1,1),ball:new T.SphereGeometry(1,12,8),cyl:new T.CylinderGeometry(1,1,1,12),gem:new T.OctahedronGeometry(1),cone:new T.ConeGeometry(1,1,10)};
 const dummy=new T.Object3D();
 function stamp(shape,c,x,y,z,sx,sy=sx,sz=sx,rx=0,ry=0,rz=0){
  const key=shape+':'+c;if(!batches.has(key))batches.set(key,{shape,c,matrices:[]});
  dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(rx,ry,rz);dummy.updateMatrix();batches.get(key).matrices.push(dummy.matrix.clone());pieces++;
 }
 const box=(c,x,y,z,w,h,d,ry=0,rz=0)=>stamp('box',c,x,y,z,w,h,d,0,ry,rz);
 const ball=(c,x,y,z,w,h=w,d=w)=>stamp('ball',c,x,y,z,w,h,d);
 const cyl=(c,x,y,z,r,h)=>stamp('cyl',c,x,y,z,r,h,r);
 const gem=(c,x,y,z,w,h=w,d=w,rz=0)=>stamp('gem',c,x,y,z,w,h,d,0,0,rz);
 const cone=(c,x,y,z,r,h)=>stamp('cone',c,x,y,z,r,h,r);
 function unique(geometry,c,x=0,y=0,z=0,outline=true){
  const mesh=new T.Mesh(geometry,material(c));mesh.position.set(x,y,z);mesh.castShadow=mesh.receiveShadow=true;group.add(mesh);if(outline)addStorybookOutline(mesh,.025);pieces++;uniqueTriangles+=(geometry.index?.count??geometry.attributes.position.count)/3;return mesh;
 }
 function line(c,points,r=.07){return unique(new T.TubeGeometry(new T.CatmullRomCurve3(points.map(p=>new T.Vector3(...p))),Math.max(8,points.length*3),r,6,false),c,0,0,0,false);}
 function ring(c,x,y,z,r,t=.05,vertical=false){const mesh=unique(new T.TorusGeometry(r,t,6,28),c,x,y,z,false);if(!vertical)mesh.rotation.x=-Math.PI/2;return mesh;}
 function plate(c,points,x,y,z,depth=.12){const s=new T.Shape();points.forEach(([a,b],i)=>i?s.lineTo(a,b):s.moveTo(a,b));s.closePath();const g=new T.ExtrudeGeometry(s,{depth,bevelEnabled:true,bevelSize:.025,bevelThickness:.025,bevelSegments:1,curveSegments:6});g.translate(0,0,-depth/2);return unique(g,c,x,y,z);}
 function arch(c,x,y,z,w,h,thickness=.16,depth=.22){
  const outer=w/2,inner=outer-thickness,spring=h-outer,s=new T.Shape();
  s.moveTo(-outer,0);s.lineTo(-inner,0);s.lineTo(-inner,spring);s.absarc(0,spring,inner,Math.PI,0,true);s.lineTo(inner,0);s.lineTo(outer,0);s.lineTo(outer,spring);s.absarc(0,spring,outer,0,Math.PI,false);s.closePath();
  const g=new T.ExtrudeGeometry(s,{depth,bevelEnabled:true,bevelSize:.025,bevelThickness:.025,bevelSegments:1,curveSegments:12});g.translate(0,0,-depth/2);return unique(g,c,x,y,z);
 }
 function crest(x,y,z,kind='gem',scale=1){
  const shield=plate('gold',[[-.34,.3],[.34,.3],[.3,-.12],[0,-.42],[-.3,-.12]],x,y,z);shield.scale.setScalar(scale);
  const field=plate('accent',[[-.26,.23],[.26,.23],[.23,-.09],[0,-.31],[-.23,-.09]],x,y,z+.09*scale,.035);field.scale.setScalar(scale);
  if(kind==='rose'){for(let i=0;i<5;i++){const a=i*Math.PI*2/5;ball('light',x+Math.sin(a)*.12*scale,y+Math.cos(a)*.12*scale,z+.15*scale,.095*scale,.095*scale,.04*scale);}ball('gold',x,y,z+.2*scale,.07*scale);}
  else if(kind==='swords'){for(const s of[-1,1]){box('light',x,y,z+.16*scale,.045*scale,.45*scale,.025*scale,0,s*.65);box('gold',x+s*.13*scale,y-.1*scale,z+.19*scale,.19*scale,.045*scale,.04*scale,0,s*.65);}}
  else gem('light',x,y,z+.17*scale,.10*scale,.18*scale,.05*scale);
 }
 function banner(x,y,z,length=1.15){
  cyl('gold',x,y+.13,z,.045,.38);box('gold',x,y,z,.76,.09,.10);
  plate('gold',[[-.31,0],[.31,0],[.3,-length+.18],[0,-length],[-.3,-length+.18]],x,y-.03,z,.055);
  plate('accent',[[-.24,-.05],[.24,-.05],[.23,-length+.23],[0,-length+.09],[-.23,-length+.23]],x,y-.03,z+.05,.03);
  gem('light',x,y-length*.43,z+.10,.09,.17,.035);
 }
 function column(x,z,height,r=.32){
  cyl('stone',x,.42+height/2,z,r,height);cyl('gold',x,.52,z,r+.1,.13);cyl('light',x,height+.39,z,r+.13,.20);
  for(const s of[-1,1])box('light',x+s*r*.66,.55+height*.48,z+r*.6,.08,height*.79,.07);
  arch('gold',x,.92,z+r,.31,.69,.065,.045);box('accent',x,1.22,z+r+.01,.15,.42,.035);
 }
 function spire(x,z,height,cap='roof'){
  column(x,z,height,.32);
  if(cap==='crystal'){gem('accent',x,height+1.03,z,.36,.7,.34);gem('light',x+.04,height+1.11,z+.18,.13,.46,.10);}
  else if(cap==='dome'){ball('accent',x,height+.72,z,.53,.43,.53);cyl('gold',x,height+1.2,z,.035,.42);gem('gold',x,height+1.46,z,.10,.2,.1);}
  else{cone('accent',x,height+.96,z,.57,1.05);ring('gold',x,height+.46,z,.56,.055);cone('gold',x,height+1.64,z,.075,.38);}
 }
 function wings(x,y,z,spread=1.2,c='gold',up=.7){
  for(const side of[-1,1]){
   const points=[[.04,-.12],[.16,.2],[.47,.4],[.76,up],[1,up+.1],[.94,.28],[.77,.33],[.7,.02],[.54,.11],[.4,-.16],[.29,-.02],[.16,-.24]].map(([a,b])=>[a*side*spread,b*spread]);
   plate(c,points,x,y,z,.11);
   for(let i=0;i<4;i++){const a=.3+i*.16;line('light',[[x+side*a*spread,y+(a*up+.1)*spread,z+.075],[x+side*(a-.04)*spread,y+(a*up-.13)*spread,z+.1]],.035);}
  }
 }
 function guardian(x,z,kind='griffin',height=.7){
  cyl('stone',x,.52,z,.46,.36);cyl('gold',x,.72,z,.49,.10);
  ball('stone',x,.91+height*.28,z,.23,height*.45,.23);ball('gold',x,1.06+height*.71,z+.06,.22,.22,.23);
  for(const s of[-1,1]){box('light',x+s*.16,.87,z+.19,.15,.24,.28);ball('ink',x+s*.09,1.1+height*.73,z+.255,.027);}
  if(kind==='griffin'){wings(x,1.1,z-.13,.52,'gold',.8);cone('gold',x,1.43+height*.5,z,.065,.24);gem('gold',x,1.02+height*.71,z+.32,.09,.07,.16);}
  else if(kind==='lion'){for(let i=0;i<7;i++){const a=i*Math.PI*2/7;ball('light',x+Math.sin(a)*.2,1.04+height*.7+Math.cos(a)*.2,z-.04,.09,.12,.1);}ball('stone',x,1.02+height*.71,z+.27,.13,.10,.13);}
  else{for(const s of[-1,1])line('gold',[[x+s*.1,1.2+height*.7,z],[x+s*.3,1.52+height*.7,z-.1],[x+s*.4,1.67+height*.7,z-.17]],.055);}
 }
 function gateway(z=2.0,w=1.65,h=2.25,kind='gem'){
  arch('stone',0,.45,z,w,h,.23,.42);arch('gold',0,.47,z+.24,w-.08,h-.045,.085,.07);
  for(const s of[-1,1]){box('light',s*(w/2-.1),.70,z+.14,.37,.3,.58);banner(s*(w/2+.37),h+.05,z-.08,.98);}
  crest(0,h+.6,z+.16,kind,.8);
 }
 function terrace(radius=2.9,y=.42){
  // Broken colonnades leave the south entrance fully open.
  for(let i=0;i<14;i++){const a=.55+i*(Math.PI*2-1.1)/13,x=Math.sin(a)*radius,z=Math.cos(a)*radius;
   cyl('stone',x,y+.15,z,.13,.44);ball('gold',x,y+.41,z,.15,.10,.15);
   if(i<13){const b=a+(Math.PI*2-1.1)/13;line('light',[[x,y+.33,z],[Math.sin((a+b)/2)*radius,y+.3,Math.cos((a+b)/2)*radius],[Math.sin(b)*radius,y+.33,Math.cos(b)*radius]],.055);}
  }
 }
 function flourish(x,y,z,side=1,c='gold'){
  line(c,[[x,y,z],[x+side*.6,y+.12,z],[x+side*.84,y+.45,z],[x+side*.65,y+.69,z],[x+side*.39,y+.57,z]],.075);ball('light',x+side*.39,y+.57,z,.105);
 }
 function halo(x,y,z,r,c='gold'){
  ring(c,x,y,z,r,.085,true);
  for(let i=0;i<12;i++){const a=i*Math.PI/6;gem(i%3?'light':'gold',x+Math.sin(a)*r,y+Math.cos(a)*r,z+.02,.07,.16,.06,-a);}
 }

 const designs={
  default(){
   for(const s of[-1,1]){guardian(s*1.16,3.25,'lion',.48);banner(s*2.3,3.6,1.85,1.15);}
   wings(0,3.93,2.52,.67);crest(0,4.05,2.67,'swords',.85);
  },
  ironkeep(){
   gateway(1.98,1.76,2.43,'swords');
   for(const s of[-1,1]){
    column(s*2.34,-.66,3.35,.43);cyl('accent',s*2.34,3.82,-.66,.55,.21);
    for(let i=0;i<7;i++){const a=i*Math.PI*2/7;box('light',s*2.34+Math.sin(a)*.48,4.04,-.66+Math.cos(a)*.48,.22,.34,.21,a);}
    guardian(s*2.32,1.72,'griffin',1.05);banner(s*2.35,3.4,-.18,1.32);
    box('stone',s*1.43,1.32,.82,.37,1.63,.55);for(let i=0;i<5;i++)gem('gold',s*1.43,.79+i*.29,1.12,.085,.10,.035);
   }
  },
  rosehall(){
   gateway(1.92,1.73,2.7,'rose');terrace(2.8);
   for(const s of[-1,1]){spire(s*2.23,-1.03,3.18,'dome');flourish(s*.83,3.22,1.87,s);guardian(s*1.85,1.9,'lion',.58);
    for(let i=0;i<6;i++){const x=s*(.9+i*.19),y=3.5+Math.sin(i*.6)*.25;for(let j=0;j<5;j++){const a=j*Math.PI*2/5;ball('light',x+Math.sin(a)*.08,y+Math.cos(a)*.08,1.83,.08,.08,.045);}ball('gold',x,y,1.9,.05);}
   }
  },
  sandspire(){
   gateway(2.05,1.87,2.65);terrace(2.88);
   for(const s of[-1,1]){spire(s*2.4,-.62,3.62,'dome');guardian(s*2.08,1.89,'lion',.72);banner(s*2.39,3.58,-.21,1.4);}
   for(const s of[-1,1]){plate('gold',[[0,0],[s*.48,.1],[s*.74,.9],[s*.43,1.8],[s*.18,1.57],[s*.35,.82]],s*.89,2.93,-.85);}
   halo(0,4.83,-.64,.88);for(let i=0;i<6;i++)gem('accent',(i-2.5)*.24,2.47,2.29,.08,.13,.045);
  },
  tidewatch(){
   gateway(2.01,1.65,2.18);terrace(2.85);
   for(const s of[-1,1]){
    column(s*2.34,-.40,2.4,.33);ball('accent',s*2.34,3.11,-.4,.46,.4,.46);cone('gold',s*2.34,3.75,-.4,.06,.69);
    line('gold',[[s*2.34,3.57,-.4],[s*2.69,3.16,-.38],[s*2.34,3.05,-.37],[s*2.02,3.16,-.38]],.06);
    for(let i=0;i<3;i++)flourish(s*(.95+i*.46),.72,2.11-i*.28,s,'accent');
    box('wood',s*1.43,.49,2.63,.66,.14,.53);cyl('gold',s*1.43,.75,2.65,.07,.5);
   }
   ring('gold',.49,2.64,.94,.34,.065,true);for(let i=0;i<8;i++){const a=i*Math.PI/4;line('gold',[[.49+Math.sin(a)*.23,2.64+Math.cos(a)*.23,.94],[.49+Math.sin(a)*.47,2.64+Math.cos(a)*.47,.94]],.042);}
  },
  winterhold(){
   gateway(2.0,1.81,2.58);terrace(2.9);
   for(const s of[-1,1]){spire(s*2.28,-.67,3.4,'crystal');guardian(s*1.86,1.87,'lion',.65);
    for(let i=0;i<4;i++){gem('accent',s*(2.25+Math.sin(i*2)*.27),.74+i*.12,.52-i*.52,.18,.42+(i%2)*.25,.21,s*.2);ball('light',s*2.28,.56,.6-i*.54,.4,.14,.38);}
    line('light',[[s*.86,3.0,1.92],[s*1.36,3.44,1.47],[s*1.48,3.95,.75]],.09);
   }
   for(let i=0;i<6;i++){const a=i*Math.PI/3;line('light',[[0,3.27,2.08],[Math.sin(a)*.37,3.27+Math.cos(a)*.37,2.08]],.035);}
  },
  jadecourt(){
   gateway(2.03,1.88,2.52);terrace(2.83);
   for(const s of[-1,1]){
    column(s*2.3,-.68,3.16,.3);cone('accent',s*2.3,3.97,-.68,.68,.78);ring('gold',s*2.3,3.64,-.68,.69);
    guardian(s*2.23,1.69,'dragon',.81);
    line('gold',[[s*.52,4.75,-.9],[s*1.30,5.08,-.87],[s*1.78,5.77,-.89],[s*1.55,6.16,-.74]],.14);
    ball('gold',s*1.53,6.14,-.70,.22,.17,.28);gem('light',s*1.55,6.11,-.38,.13,.08,.19);
    line('gold',[[s*1.52,6.29,-.8],[s*1.74,6.58,-1.01],[s*1.94,6.54,-1.04]],.048);
    for(let i=0;i<4;i++)gem('accent',s*(.68+i*.25),4.9+i*.2,-.75,.11,.25,.09,-s*.45);
    cyl('gold',s*1.65,2.42,1.16,.025,.51);ball('accent',s*1.65,2.04,1.16,.19,.26,.19);cyl('gold',s*1.65,1.71,1.16,.035,.25);
   }
  },
  emberforge(){
   gateway(2.02,1.91,2.66,'swords');
   for(const s of[-1,1]){
    column(s*2.35,-.81,3.55,.42);cyl('accent',s*2.35,4.1,-.81,.58,.4);cyl('ink',s*2.35,4.31,-.81,.38,.07);
    for(let i=0;i<5;i++){box('gold',s*2.35,1.25+i*.49,-.35,.72,.14,.12);ball('light',s*2.35-.27,1.25+i*.49,-.27,.055);ball('light',s*2.35+.27,1.25+i*.49,-.27,.055);}
    line('gold',[[s*2.35,3.49,-.83],[s*1.65,3.58,-.95],[s*1.56,4.67,-1.01]],.13);
    box('stone',s*1.91,.79,1.99,.8,.48,.69);box('gold',s*1.91,1.07,1.99,.74,.13,.59);box('accent',s*1.91,1.21,1.99,.49,.20,.32);
   }
   wings(0,3.53,1.99,.91,'gold',.32);crest(0,3.52,2.18,'swords',.84);
  },
  ravenloft(){
   gateway(1.96,1.74,2.88);terrace(2.72);
   for(const s of[-1,1]){spire(s*2.19,-.86,3.77);line('stone',[[s*2.19,2.55,-.84],[s*1.77,2.81,-.6],[s*1.30,3.94,-.38]],.18);guardian(s*1.97,1.74,'griffin',.8);banner(s*2.2,3.47,-.45,1.54);}
   wings(0,3.58,1.18,1.08,'accent',.6);crest(0,3.58,1.35,'gem',.77);
  },
  clockwork(){
   gateway(1.97,1.69,2.17);terrace(2.81);
   for(const s of[-1,1]){
    spire(s*2.27,-.88,3.13,'dome');line('gold',[[s*2.27,3.0,-.58],[s*1.89,3.13,-.47],[s*1.88,4.32,-.6],[s*1.14,4.52,-.68]],.12);
    ring('gold',s*2.27,1.65,-.33,.31,.08,true);for(let i=0;i<8;i++){const a=i*Math.PI/4;box('light',s*2.27+Math.sin(a)*.34,1.65+Math.cos(a)*.34,-.31,.13,.16,.12,0,-a);}
    banner(s*1.98,2.5,1.03,.98);
   }
   arch('gold',0,3.73,-1.42,2.27,2.17,.11,.12);gem('light',0,5.9,-1.42,.16,.25,.11);
  },
  sapphire(){
   gateway(2.03,1.83,2.75);terrace(2.89);
   for(const s of[-1,1]){spire(s*2.34,-.49,3.14,'dome');guardian(s*1.95,1.86,'griffin',.73);flourish(s*.88,3.02,1.65,s);}
   for(let i=0;i<7;i++){const a=-Math.PI*.8+i*Math.PI*1.6/6,x=Math.sin(a)*1.26,z=-.28+Math.cos(a)*1.26;line('gold',[[x,4.05,z],[x*1.09,4.56,z],[x*.84,4.87,z]],.07);gem('accent',x*.84,4.93,z,.11,.23,.10);}
  },
  phoenix(){
   terrace(3.06,.54);
   for(const s of[-1,1]){
    plate('gold',[[0,0],[s*.53,.22],[s*.69,1.84],[s*.22,3.7],[0,3.2],[s*.19,1.45]],s*2.51,1.08,-.84,.18);
    plate('accent',[[0,0],[s*.28,.27],[s*.4,1.76],[s*.16,2.89],[s*.10,1.38]],s*2.52,1.15,-.70,.08);
    wings(s*1.93,3.17,1.95,.6,'gold',1.05);guardian(s*1.66,2.22,'griffin',.91);
    banner(s*1.57,2.93,1.24,1.3);
   }
   halo(0,5.06,-.75,1.47);crest(0,2.89,1.31,'swords',1.1);
   for(let i=0;i<5;i++)gem('gold',(i-2)*.30,3.19,1.33,.13,.26,.06);
  },
  astral(){
   terrace(3.03,.62);
   for(const s of[-1,1]){
    column(s*2.55,-1.23,2.85,.27);
    line('gold',[[s*2.55,3.33,-1.23],[s*2.68,4.47,-1.34],[s*2.18,5.80,-1.36],[s*1.08,6.73,-1.18],[s*.36,6.92,-1.04]],.14);
    line('accent',[[s*2.37,3.63,-1.26],[s*2.35,4.67,-1.28],[s*1.89,5.68,-1.3],[s*1.1,6.24,-1.22]],.08);
    gem('gold',s*.36,6.92,-1.04,.19,.30,.16,-s*.65);
    for(let i=0;i<5;i++){const a=.3+i*.22,x=s*(2.55*Math.cos(a)),y=3.83+3.0*Math.sin(a);gem('light',x,y,-1.31,.09,.20,.055,-s*a);}
    banner(s*1.9,2.97,.74,1.23);guardian(s*1.68,2.2,'griffin',.54);
   }
   halo(0,3.16,.84,.71);crest(0,2.05,2.08,'gem',.73);
  },
  leviathan(){
   terrace(3.04,.55);
   for(const s of[-1,1]){
    line('accent',[[s*2.78,.59,.89],[s*3.1,1.82,.43],[s*2.73,3.0,-.1],[s*2.29,3.19,.04],[s*2.03,2.86,.29]],.23);
    for(let i=0;i<7;i++){const a=i*.34;ball('light',s*(2.83+.13*Math.sin(a)),.93+i*.24,.56-i*.08,.10,.09,.06);}
    for(let i=0;i<4;i++)flourish(s*(.87+i*.49),.9,2.20-i*.24,s,'light');
    guardian(s*1.51,2.11,'dragon',.68);banner(s*1.83,2.39,1.23,.95);
   }
   plate('gold',[[-.96,0],[-.88,.88],[-.48,.53],[0,1.24],[.48,.53],[.88,.88],[.96,0]],0,2.32,1.55);
   gem('light',0,3.08,1.69,.21,.30,.10);
  },
  yggdrasil(){
   arch('wood',0,.7,1.44,1.88,2.04,.25,.26);arch('gold',0,.77,1.63,1.66,1.86,.075,.07);
   for(const s of[-1,1]){
    line('wood',[[s*2.76,.41,1.06],[s*2.51,1.12,.85],[s*2.58,2.68,.46],[s*1.91,4.16,-.18]],.21);
    line('gold',[[s*2.71,.63,1.12],[s*2.55,1.43,.91],[s*2.49,2.53,.61]],.055);
    guardian(s*1.83,1.82,'dragon',.53);
    for(let i=0;i<5;i++){const x=s*(2.05+Math.sin(i*1.6)*.3),y=3.8+i*.48,z=-.47+Math.cos(i*2)*.35;line('wood',[[s*.75,3.64,-.5],[x*.83,y-.3,z],[x,y,z]],.10);ball('leaf',x,y,z,.55,.31,.54);ball('light',x+.15,y+.04,z+.17,.35,.23,.37);}
    for(let i=0;i<4;i++){const x=s*(1.36+i*.27),y=3.8+i*.1;line('gold',[[x,y,-.05],[x-.08,y-.75,.1]],.022);gem('gold',x-.08,y-.85,.1,.095,.15,.06);}
   }
   crest(0,2.98,1.40,'gem',.86);halo(0,5.46,-1.02,1.57,'gold');
  },
  tempest(){
   terrace(3.0,.98);
   for(const s of[-1,1]){
    column(s*2.47,-.56,2.85,.27);wings(s*1.48,3.65,-.78,1.19,'light',1.0);
    line('gold',[[s*2.47,2.42,-.56],[s*2.72,3.63,-.74],[s*2.44,4.50,-.74],[s*2.73,5.09,-.78]],.1);
    gem('light',s*2.73,5.26,-.78,.16,.32,.13);banner(s*1.52,3.07,.95,1.15);
    guardian(s*1.62,2.19,'griffin',.58);
   }
   crest(0,2.93,1.03,'gem',.98);arch('gold',0,4.56,-1.06,2.12,1.82,.1,.13);
  },
  eclipse(){
   terrace(3.0,.66);
   for(const s of[-1,1]){
    plate('accent',[[0,0],[s*.42,.45],[s*.73,2.42],[s*.39,4.73],[s*.15,4.22],[s*.36,2.63],[s*.17,1.24]],s*2.33,.89,-1.04,.24);
    line('gold',[[s*2.35,1.41,-.86],[s*2.78,3.31,-.88],[s*2.57,5.38,-.9]],.065);
    guardian(s*1.74,2.01,'griffin',.71);banner(s*1.66,3.21,.94,1.3);
   }
   arch('gold',0,.82,1.41,1.53,2.05,.12,.11);crest(0,2.98,1.52,'gem',1.04);
   for(let i=0;i<9;i++){const a=Math.PI*.53+i*Math.PI*.94/8;gem('light',Math.cos(a)*1.79,4.95+Math.sin(a)*1.69,-.72,.1,.25,.07,-a+Math.PI/2);}
  },
  dragon(){
   gateway(1.97,1.84,2.38,'gem');terrace(2.94,.54);
   for(const s of[-1,1]){
    column(s*2.28,-.72,2.95,.34);cone('stone',s*2.28,4.07,-.72,.55,1.06);cone('light',s*2.28,4.66,-.72,.09,.34);
    guardian(s*1.71,1.93,'dragon',.68);banner(s*1.72,2.72,1.15,1.1);
    line('gold',[[s*.66,3.04,1.37],[s*1.07,3.48,.87],[s*1.35,3.95,.11]],.075);
    for(let i=0;i<5;i++)gem(i%2?'accent':'gold',s*(2.42+.12*Math.sin(i*1.7)),.82+i*.33,.38-i*.31,.10,.19,.08,s*.3);
   }
   crest(0,2.95,1.48,'gem',.92);
  }
 };
 designs[skin]();
 // Combine fixed arches, scrolls and rails by pigment. Small repeated pieces
 // below remain instanced; a detailed facade does not need a call per ornament.
 const surfaces=new Map();group.updateMatrixWorld(true);
 for(const mesh of [...group.children]){
  if(!mesh.isMesh)continue;
  const outlined=Boolean(mesh.userData.storybookOutline),key=mesh.material.uuid+':'+outlined;
  if(!surfaces.has(key))surfaces.set(key,{material:mesh.material,outlined,parts:[]});
  const geometry=mesh.geometry.index?mesh.geometry.toNonIndexed():mesh.geometry.clone();
  mesh.updateMatrix();geometry.applyMatrix4(mesh.matrix);surfaces.get(key).parts.push(geometry);
  mesh.removeFromParent();mesh.geometry.dispose();
 }
 for(const {material,outlined,parts} of surfaces.values()){
  const geometry=new T.BufferGeometry();
  for(const [name,size]of [['position',3],['normal',3],['uv',2]]){
   const count=parts.reduce((sum,part)=>sum+part.attributes.position.count,0),values=new Float32Array(count*size);let offset=0;
   for(const part of parts){const a=part.attributes[name];if(a)values.set(a.array,offset);offset+=part.attributes.position.count*size;}
   geometry.setAttribute(name,new T.BufferAttribute(values,size));
  }
  parts.forEach(part=>part.dispose());geometry.computeBoundingSphere();
  const mesh=new T.Mesh(geometry,material);mesh.castShadow=mesh.receiveShadow=true;group.add(mesh);if(outlined)addStorybookOutline(mesh,.025);
 }
 let triangles=uniqueTriangles;
 const usedShapes=new Set();
 for(const {shape,c,matrices} of batches.values()){
  usedShapes.add(shape);const geometry=shapes[shape],mesh=new T.InstancedMesh(geometry,material(c),matrices.length);
  matrices.forEach((matrix,i)=>mesh.setMatrixAt(i,matrix));mesh.computeBoundingSphere();mesh.castShadow=mesh.receiveShadow=true;mesh.name=`epic-${skin}-${shape}-${c}`;group.add(mesh);
  triangles+=(geometry.index?.count??geometry.attributes.position.count)/3*matrices.length;
 }
 for(const [name,geometry]of Object.entries(shapes))if(!usedShapes.has(name))geometry.dispose();
 // Outline only a few large old architecture batches, leaving fine relief quiet.
 const architecture=[];root.traverse(object=>{if(object.isMesh&&!object.isInstancedMesh&&!object.userData.storybookInk&&!object.userData.storybookOutline&&object.material.isMeshToonMaterial&&object.parent!==group)architecture.push(object);});
 architecture.sort((a,b)=>b.geometry.attributes.position.count-a.geometry.attributes.position.count);
 if(skin!=='default')architecture.slice(0,3).forEach(mesh=>addStorybookOutline(mesh,.022));
 root.userData.epicDetails={version:1,theme:skin,pieces,triangles:Math.round(triangles),batches:group.children.length};
}
