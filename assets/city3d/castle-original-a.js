import * as T from './vendor/three.module.js';

// Original mythic landmarks. Static architecture is merged by pigment, while
// celestial mechanisms, fire and water have their own four-second motion loop.
export function buildOriginalMythicA(skin) {
  if (!['phoenix','astral','leviathan'].includes(skin)) return null;
  const root=new T.Group();root.name=`castle-skin-${skin}`;root.userData.building='keep';root.userData.skin=skin;
  const P={obsidian:'#282634',slate:'#515368',crimson:'#a32d36',red:'#e34b35',gold:'#e6b044',bronze:'#9a6d35',ivory:'#e8dfc4',navy:'#29365e',blue:'#466ca2',teal:'#299c9d',aqua:'#59ced0',coral:'#e78298',pink:'#f2b0ae',pearl:'#e9f2df',orange:'#ff9235',sun:'#ffe48b',cyan:'#a3fff7',violet:'#af99f3'};
  const G={box:new T.BoxGeometry(1,1,1).toNonIndexed(),cyl:new T.CylinderGeometry(1,1,1,12).toNonIndexed(),cone:new T.ConeGeometry(1,1,10).toNonIndexed(),ball:new T.SphereGeometry(1,12,8).toNonIndexed(),gem:new T.OctahedronGeometry(1),rock:new T.IcosahedronGeometry(1)};
  const matrix=new T.Matrix4(),normal=new T.Matrix3(),p=new T.Vector3(),n=new T.Vector3(),position=new T.Vector3(),quaternion=new T.Quaternion(),rotation=new T.Euler(),scale=new T.Vector3();
  let batches=new Map(),pieces=0,triangles=0,drawCalls=0;const movements=[];
  function add(g,c,x=0,y=0,z=0,w=1,h=1,d=1,rx=0,ry=0,rz=0){
    if(!batches.has(c))batches.set(c,{p:[],n:[]});const b=batches.get(c);matrix.compose(position.set(x,y,z),quaternion.setFromEuler(rotation.set(rx,ry,rz)),scale.set(w,h,d));normal.getNormalMatrix(matrix);
    for(let i=0;i<g.attributes.position.count;i++){p.fromBufferAttribute(g.attributes.position,i).applyMatrix4(matrix);n.fromBufferAttribute(g.attributes.normal,i).applyMatrix3(normal).normalize();b.p.push(p.x,p.y,p.z);b.n.push(n.x,n.y,n.z);}pieces++;
  }
  const box=(c,x,y,z,w,h,d,ry=0,rz=0)=>add(G.box,c,x,y,z,w,h,d,0,ry,rz);
  const cyl=(c,x,y,z,r,h)=>add(G.cyl,c,x,y,z,r,h,r);
  const cone=(c,x,y,z,r,h)=>add(G.cone,c,x,y,z,r,h,r);
  const ball=(c,x,y,z,r,h=r,d=r)=>add(G.ball,c,x,y,z,r,h,d);
  const gem=(c,x,y,z,r,h)=>add(G.gem,c,x,y,z,r,h,r);
  function line(c,points,r=.045){const g=new T.TubeGeometry(new T.CatmullRomCurve3(points.map(v=>new T.Vector3(...v))),Math.max(10,points.length*4),r,6,false).toNonIndexed();add(g,c);g.dispose();}
  function ring(c,x,y,z,r,t=.05,rx=Math.PI/2,ry=0){const g=new T.TorusGeometry(r,t,6,32).toNonIndexed();add(g,c,x,y,z,1,1,1,rx,ry);g.dispose();}
  function flush(parent){for(const[c,b]of batches){const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(b.p,3));geometry.setAttribute('normal',new T.Float32BufferAttribute(b.n,3));geometry.computeBoundingSphere();const glow=c.startsWith('!'),name=glow?c.slice(1):c;const material=glow?new T.MeshBasicMaterial({color:P[name]}):new T.MeshStandardMaterial({color:P[name],roughness:.76,metalness:name==='gold'?.16:0});if(!glow){material.onBeforeCompile=s=>{s.fragmentShader=s.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};material.customProgramCacheKey=()=> 'castle-original-a-pigment-1';}const mesh=new T.Mesh(geometry,material);mesh.name=`${skin}-${name}`;mesh.castShadow=!glow;mesh.receiveShadow=true;mesh.userData.building='keep';parent.add(mesh);triangles+=b.p.length/9;drawCalls++;}batches=new Map();}
  function moving(name,build,animate){const old=batches;batches=new Map();const g=new T.Group();g.name=name;build();flush(g);batches=old;root.add(g);movements.push(t=>animate(g,t*Math.PI/2));}
  function steps(c,x,z,w,count=8,y=.05,height=.65,depth=1){for(let i=0;i<count;i++)box(c,x,y+height*(i+.5)/count,z-depth*(i+.5)/count,w,height/count,depth/count+.03);}
  function foundation(c,trim){cyl(c,0,.23,0,2.88,.40);cyl(trim,0,.46,0,2.85,.10);cyl(c,0,.57,0,2.64,.15);for(let i=0;i<24;i++){const a=i*Math.PI/12;box(trim,Math.sin(a)*2.81,.26,Math.cos(a)*2.81,.055,.19,.10,a);}}
  function shaft(c,x,z,y,h,r,trim='gold',light='!sun'){
    cyl(c,x,y+h/2,z,r,h);cyl(trim,x,y+.1,z,r+.065,.16);cyl(trim,x,y+h,z,r+.08,.15);
    for(let row=1;row<Math.floor(h/.34);row++)ring(trim,x,y+row*.34,z,r+.003,.012);
    for(let i=0;i<8;i++){const a=i*Math.PI/4,xx=x+Math.sin(a)*r,zz=z+Math.cos(a)*r;box(trim,xx,y+h*.51,zz,.045,h*.82,.045,a);box(light,x+Math.sin(a)*(r+.013),y+h*.65,z+Math.cos(a)*(r+.013),.12,h*.25,.025,a);}
  }
  function portal(x,y,z,w,h,c='gold',inner='navy'){
    box(inner,x,y+h*.45,z,w,h*.9,.08);for(let s of[-1,1])box(c,x+s*w*.57,y+h*.45,z+.05,w*.14,h*.95,.15);line(c,[[x-w*.56,y+h*.9,z+.05],[x-w*.38,y+h*1.14,z+.05],[x,y+h*1.3,z+.05],[x+w*.38,y+h*1.14,z+.05],[x+w*.56,y+h*.9,z+.05]],w*.07);
    for(let i=0;i<5;i++)box(c,x+(i-2)*w*.17,y+h*.43,z+.06,.035,h*.75,.035);ball('!sun',x,y+h*.98,z+.11,.10,.13,.045);
  }
  function phoenix(){
    foundation('obsidian','gold');box('obsidian',0,1.83,-.22,2.9,2.45,2.75);box('crimson',0,2.05,-.22,2.97,1.48,2.8);box('gold',0,3.10,-.22,3.18,.17,3.03);box('obsidian',0,3.29,-.22,2.66,.24,2.54);
    for(let s of[-1,1]){for(let i=0;i<5;i++){const zz=-1.30+i*.54;box('gold',s*1.53,1.85,zz,.07,1.95,.09);box('!orange',s*1.55,2.04,zz+.14,.025,1.05,.15);}for(let i=0;i<6;i++)box('gold',s*.82,1.01+i*.3,1.20,.34,.025,.025);}
    for(let s of[-1,1])for(let z of[-1.94,1.86]){shaft('crimson',s*1.97,z,.65,2.0,.39);cone('obsidian',s*1.97,3.15,z,.58,1.05);ring('gold',s*1.97,2.68,z,.57,.045);gem('gold',s*1.97,3.87,z,.1,.28);}
    portal(0,.67,1.20,1.10,1.57,'gold','obsidian');steps('slate',0,2.97,1.35,9,.05,.66,1.5);
    for(let s of[-1,1]){line('gold',[[s*.78,.68,2.05],[s*.78,.93,1.7],[s*.78,1.12,1.43]],.06);for(let i=0;i<4;i++){cyl('gold',s*1.62,.93+i*.24,2.13,.06,.14);}}
    cyl('obsidian',0,3.60,-.25,.91,.56);cyl('gold',0,3.91,-.25,1.01,.10);
    // Monumental phoenix: extended articulated wings, layered flight feathers,
    // long tail, hooked beak and blazing crown visible even at map scale.
    ball('gold',0,4.64,-.24,.38,.68,.31);ball('sun',0,5.20,-.10,.24,.30,.22);add(G.cone,'bronze',0,5.19,.25,.15,.4,.13,Math.PI/2);ball('!sun',-.14,5.28,.079,.048);ball('!sun',.14,5.28,.079,.048);
    for(let s of[-1,1]){
      const wing=new T.Shape();const contour=[[.23,4.77],[.84,5.27],[1.66,5.73],[2.35,5.79],[2.57,5.31],[2.27,5.34],[2.21,5.07],[1.91,5.13],[1.86,4.84],[1.57,4.95],[1.49,4.64],[1.21,4.82],[1.04,4.50],[.83,4.69],[.60,4.36],[.33,4.48]];contour.forEach(([x,y],i)=>i?wing.lineTo(s*x,y):wing.moveTo(s*x,y));wing.closePath();const featherPlate=new T.ExtrudeGeometry(wing,{depth:.11,bevelEnabled:true,bevelSize:.025,bevelThickness:.025,bevelSegments:1});add(featherPlate,'bronze',0,0,-.46);featherPlate.dispose();
      line('gold',[[s*.19,4.86,-.3],[s*.84,5.27,-.31],[s*1.66,5.73,-.36],[s*2.35,5.79,-.46]],.16);
      for(let i=0;i<8;i++){const u=i/7,x=s*(.45+u*1.86),y=5.18+u*.58;line(i%2?'gold':'sun',[[x,y,-.34],[x+s*.24,y-.34,-.38],[x+s*(.12+.13*u),y-.91+u*.43,-.26]],.08+(1-u)*.018);}
      for(let i=0;i<3;i++)line('crimson',[[s*.22,4.45,-.21],[s*(.27+i*.14),4.12,.02],[s*(.38+i*.24),3.99,.40+i*.08]],.065);
      line('gold',[[s*.16,4.2,-.22],[s*.22,4.0,.01],[s*.35,3.99,.18]],.06);
    }
    for(let i=0;i<3;i++)gem('!orange',(i-1)*.14,5.59,-.13,.065,.26+(i===1?.18:0));
    moving('phoenix-flame-plumes',()=>{for(let i=0;i<7;i++){const a=i*2.399,r=.65+(i%2)*.45;gem('!orange',Math.cos(a)*r,.20+(i%3)*.13,Math.sin(a)*r,.12,.47);gem('!sun',Math.cos(a)*r,.11+(i%3)*.13,Math.sin(a)*r+.03,.06,.23);}},(g,t)=>{g.position.set(0,3.93,-.25);g.rotation.y=t;g.scale.y=1+Math.sin(t*3)*.20;});
    moving('phoenix-braziers',()=>{for(let s of[-1,1]){gem('!orange',s*1.62,.24,2.13,.20,.5);gem('!sun',s*1.62,.1,2.17,.1,.24);}},(g,t)=>{g.position.y=1.88;g.scale.y=1+Math.sin(t*4)*.16;});
  }
  function astral(){
    foundation('navy','gold');cyl('ivory',0,.91,0,2.24,.58);ring('gold',0,1.22,0,2.27,.07);cyl('navy',0,1.53,0,1.88,.58);cyl('gold',0,1.85,0,1.99,.10);
    for(let i=0;i<16;i++){const a=i*Math.PI/8;box('gold',Math.sin(a)*1.9,1.56,Math.cos(a)*1.9,.055,.48,.08,a);ball('!cyan',Math.sin(a)*1.92,1.56,Math.cos(a)*1.92,.065);}
    shaft('ivory',0,-.27,1.85,2.53,.98,'gold','!cyan');cyl('navy',0,4.53,-.27,1.25,.23);ball('blue',0,4.59,-.27,1.19,.52,1.19);ring('gold',0,4.62,-.27,1.2,.05);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;line('gold',[[Math.sin(a)*1.18,4.66,-.27+Math.cos(a)*1.18],[Math.sin(a)*.88,4.98,-.27+Math.cos(a)*.88],[Math.sin(a)*.24,5.12,-.27+Math.cos(a)*.24]],.025);}
    for(let s of[-1,1]){shaft('ivory',s*1.88,.33,.65,2.3,.35,'gold','!cyan');cone('navy',s*1.88,3.39,.33,.56,.87);ball('gold',s*1.88,3.86,.33,.105);shaft('navy',s*1.39,-1.63,.65,1.50,.36,'gold','!cyan');cone('blue',s*1.39,2.51,-1.63,.53,.73);}
    portal(0,.65,1.91,.83,.91,'gold','navy');steps('ivory',0,2.96,.98,10,.04,.69,.98);
    for(let s of[-1,1])for(let i=0;i<6;i++){const a=s*(.38+i*.2);cyl('ivory',Math.sin(a)*2.40,.89,Math.cos(a)*2.40,.065,.47);ball('gold',Math.sin(a)*2.40,1.18,Math.cos(a)*2.40,.10);}
    // The raised celestial instrument rotates independently of the observatory.
    cyl('gold',0,5.19,-.27,.11,.43);
    moving('eternal-armillary',()=>{ring('gold',0,0,0,.76,.055,0);ring('gold',0,0,0,.76,.044,Math.PI/3,Math.PI/3);ring('gold',0,0,0,.76,.043,Math.PI/2);ball('!sun',0,0,0,.24);for(let i=0;i<8;i++){const a=i*Math.PI/4;gem('gold',Math.sin(a)*.76,Math.cos(a)*.76,0,.055,.10);}},(g,t)=>{g.position.set(0,5.72,-.27);g.rotation.y=t;g.rotation.z=.25;});
    moving('orbiting-planets',()=>{ball('!cyan',1.33,.0,0,.18);ring('gold',1.33,0,0,.28,.024,Math.PI/3);ball('!violet',-1.28,.35,0,.13);ball('!sun',0,-.11,1.25,.085);},(g,t)=>{g.position.set(0,5.55,-.27);g.rotation.y=-t;});
    // A second instrument on the lower terrace gives this silhouette depth.
    cyl('gold',-1.15,1.48,1.17,.07,.6);ring('gold',-1.15,1.94,1.17,.37,.045,Math.PI/3);ball('!cyan',-1.15,1.94,1.17,.10);
    for(let i=0;i<7;i++){const a=i*Math.PI*2/7;box('gold',Math.sin(a)*2.30,.67,Math.cos(a)*2.30,.19,.025,.31,a);}
  }
  function leviathan(){
    foundation('teal','ivory');cyl('aqua',0,.66,0,2.48,.06);cyl('ivory',0,.80,-.18,1.76,.23);cyl('teal',0,1.15,-.18,1.58,.50);cyl('ivory',0,1.43,-.18,1.7,.12);
    shaft('ivory',0,-.53,1.42,2.20,.95,'gold','!cyan');ball('teal',0,3.73,-.53,1.18,.50,1.18);ring('gold',0,3.69,-.53,1.19,.04);cone('aqua',0,4.23,-.53,.82,.98);gem('pearl',0,4.88,-.53,.22,.34);
    for(let i=0;i<10;i++){const a=i*Math.PI/5;line('gold',[[Math.sin(a)*1.13,3.74,-.53+Math.cos(a)*1.13],[Math.sin(a)*.63,4.23,-.53+Math.cos(a)*.63],[0,4.73,-.53]],.025);}
    for(let s of[-1,1]){
      shaft('teal',s*1.82,.70,.66,1.72,.43,'ivory','!cyan');ball('ivory',s*1.82,2.46,.70,.49,.22,.49);
      // Organic coral towers branch outward rather than repeating cone roofs.
      line('coral',[[s*1.82,2.50,.70],[s*1.9,3.17,.67],[s*2.24,3.62,.58],[s*2.15,4.11,.54]],.13);
      line('pink',[[s*1.9,3.02,.70],[s*1.44,3.39,.63],[s*1.36,3.73,.65]],.075);
      line('coral',[[s*2.13,3.45,.60],[s*2.57,3.58,.51],[s*2.72,3.91,.46]],.075);
      for(const[x,y,z]of[[s*2.15,4.11,.54],[s*1.36,3.73,.65],[s*2.72,3.91,.46]])ball('!cyan',x,y,z,.105);
      shaft('ivory',s*1.33,-1.75,.66,1.54,.34,'gold','!cyan');cone('aqua',s*1.33,2.61,-1.75,.50,.81);
    }
    portal(0,.88,1.45,1.0,1.18,'ivory','teal');steps('ivory',0,2.96,1.05,11,.04,.90,1.40);
    for(let s of[-1,1])line('gold',[[s*.68,.61,2.42],[s*.65,.82,1.96],[s*.63,1.18,1.45]],.045);
    // A sea serpent curls above the rear roofs: pale segmented belly, fins,
    // crown horns and swept whiskers provide an unmistakable creature outline.
    const body=[[-2.25,.88,-1.45],[-2.40,2.6,-1.61],[-1.9,4.40,-1.8],[-.85,5.68,-1.64],[.46,5.98,-1.45],[1.31,5.70,-1.12],[1.53,5.10,-.61]];
    line('teal',body,.28);line('ivory',body.map(([x,y,z])=>[x,y-.17,z+.15]),.13);
    for(let i=0;i<14;i++){const t=i/13,a=-.18+t*2.48,x=-.2-2.05*Math.cos(a),y=2.78+3.14*Math.sin(a),z=-1.72;gem('aqua',x,y+.24,z,.14,.28);}
    ball('teal',1.51,5.11,-.49,.37,.28,.51);ball('ivory',1.50,4.98,-.19,.27,.11,.32);for(let s of[-1,1]){ball('!sun',1.51+s*.28,5.20,-.31,.07);line('gold',[[1.51+s*.22,5.30,-.65],[1.51+s*.42,5.59,-.93],[1.51+s*.42,5.81,-1.02]],.055);line('ivory',[[1.51+s*.19,5.02,-.05],[1.51+s*.47,4.95,.26],[1.51+s*.58,5.12,.37]],.026);}
    for(let i=0;i<8;i++){const a=i*2.399;ball('pearl',Math.sin(a)*2.35,.76,Math.cos(a)*2.35,.09);}
    moving('tidal-pearls',()=>{for(let i=0;i<8;i++){const a=i*Math.PI/4;ball('!cyan',Math.sin(a)*2.30,.25+(i%3)*.22,Math.cos(a)*2.30,.07+(i%2)*.04);}},(g,t)=>{g.position.y=.87+Math.sin(t)*.12;g.rotation.y=t;});
    moving('palace-water-rings',()=>{ring('!cyan',0,0,0,2.4,.036);ring('!cyan',0,.02,0,2.16,.021);},(g,t)=>{g.position.y=.70;g.scale.setScalar(1+Math.sin(t)*.045);});
  }
  ({phoenix,astral,leviathan})[skin]();flush(root);Object.values(G).forEach(g=>g.dispose());
  root.userData.animate=time=>{for(const animate of movements)animate(time);};root.userData.animate(0);
  root.userData.modelStats={pieces,triangles,drawCalls};return root;
}
