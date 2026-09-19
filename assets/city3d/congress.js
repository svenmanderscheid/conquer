import * as T from './vendor/three.module.js';

// The neutral Congress is a single floating council island. Architecture is
// merged by pigment; only the island lift and its lower mechanism move.
export function buildCongress() {
  const root=new T.Group();root.name='world-congress';root.userData.landmark='congress';
  const island=new T.Group();island.name='congress-floating-island';root.add(island);
  const palette={ivory:'#eee5cf',white:'#fff3db',stone:'#b5b3ac',rock:'#596276',shadow:'#354256',gold:'#e8b64f',bronze:'#a67d37',blue:'#3679b0',navy:'#284f80',azure:'#6fbace',cyan:'#a3e3e0',grass:'#6d9478',leaf:'#3f7862',wood:'#8a6442',dark:'#233955',window:'#79b4c4'};
  const forms={box:new T.BoxGeometry(1,1,1).toNonIndexed(),cyl:new T.CylinderGeometry(1,1,1,16).toNonIndexed(),ball:new T.SphereGeometry(1,16,10).toNonIndexed(),cone:new T.ConeGeometry(1,1,8).toNonIndexed(),rock:new T.IcosahedronGeometry(1),gem:new T.OctahedronGeometry(1)};
  const batches=new Map(),materials=new Map();let target=island,pieces=0;
  const matrix=new T.Matrix4(),normalMatrix=new T.Matrix3(),position=new T.Vector3(),scale=new T.Vector3(),q=new T.Quaternion(),rotation=new T.Euler(),p=new T.Vector3(),n=new T.Vector3();
  function add(g,c,x=0,y=0,z=0,w=1,h=1,d=1,rx=0,ry=0,rz=0){
    if(!batches.has(target))batches.set(target,new Map());const pigments=batches.get(target);if(!pigments.has(c))pigments.set(c,{p:[],n:[]});const b=pigments.get(c);
    matrix.compose(position.set(x,y,z),q.setFromEuler(rotation.set(rx,ry,rz)),scale.set(w,h,d));normalMatrix.getNormalMatrix(matrix);
    for(let i=0;i<g.attributes.position.count;i++){p.fromBufferAttribute(g.attributes.position,i).applyMatrix4(matrix);n.fromBufferAttribute(g.attributes.normal,i).applyMatrix3(normalMatrix).normalize();b.p.push(...p);b.n.push(...n);}pieces++;
  }
  const box=(c,x,y,z,w,h,d,ry=0,rz=0)=>add(forms.box,c,x,y,z,w,h,d,0,ry,rz);
  const cyl=(c,x,y,z,r,h)=>add(forms.cyl,c,x,y,z,r,h,r);
  const ball=(c,x,y,z,r,h=r,d=r)=>add(forms.ball,c,x,y,z,r,h,d);
  const gem=(c,x,y,z,r,h=r,d=r,ry=0,rz=0)=>add(forms.gem,c,x,y,z,r,h,d,0,ry,rz);
  function taper(c,x,y,z,rt,rb,h,sides=16,sz=1){const g=new T.CylinderGeometry(rt,rb,h,sides).toNonIndexed();add(g,c,x,y,z,1,1,sz);g.dispose();}
  function ring(c,x,y,z,r,t=.05,rx=Math.PI/2,sz=1){const g=new T.TorusGeometry(r,t,6,48).toNonIndexed();add(g,c,x,y,z,1,1,sz,rx);g.dispose();}
  function line(c,pts,r=.04){const g=new T.TubeGeometry(new T.CatmullRomCurve3(pts.map(p=>new T.Vector3(...p))),Math.max(10,pts.length*4),r,5,false).toNonIndexed();add(g,c);g.dispose();}
  function column(x,y,z,h,r=.105){cyl('ivory',x,y+h/2,z,r,h);cyl('gold',x,y+.10,z,r*1.5,.12);cyl('white',x,y+h-.07,z,r*1.55,.14);cyl('stone',x,y+.025,z,r*1.6,.07);for(let i=0;i<6;i++){const a=i*Math.PI/3;line('white',[[x+Math.sin(a)*r,y+.2,z+Math.cos(a)*r],[x+Math.sin(a)*r,y+h-.2,z+Math.cos(a)*r]],.012);}}
  function arch(x,y,z,w,h,ry=0,fill=true){
    const transform=(dx,dy,dz=0)=>[x+dx*Math.cos(ry)+dz*Math.sin(ry),y+dy,z-dx*Math.sin(ry)+dz*Math.cos(ry)];
    if(fill){box('dark',x,y+h*.38,z,w,h*.77,.08,ry);const shape=new T.Shape();shape.absarc(0,0,w/2,0,Math.PI,false);shape.lineTo(w/2,0);const g=new T.ShapeGeometry(shape).toNonIndexed();add(g,'dark',x,y+h*.77,z,1,1,1,0,ry);g.dispose();}
    for(const s of[-1,1]){const a=transform(s*w*.60,h*.39,.05);box('white',...a,.12,h*.81,.17,ry);}
    const points=[];for(let i=0;i<=12;i++){const a=Math.PI*i/12;points.push(transform(Math.cos(a)*w*.6,h*.77+Math.sin(a)*w*.6,.09));}line('gold',points,.057);
    if(fill){const v=transform(0,h*.46,.065);box('window',...v,w*.69,h*.52,.06,ry);const m=transform(0,h*.46,.10);box('gold',...m,.024,h*.55,.025,ry);}
  }
  // Faceted island tapers to exposed floating rock and inset crystal veins.
  taper('rock',0,-.60,0,4.35,1.72,1.60,14,.83);
  taper('shadow',0,-1.38,0,1.78,.43,.82,9,.85);
  for(let i=0;i<19;i++){const a=i*2.399,r=2.8+(i%3)*.46;add(forms.rock,i%3?'rock':'shadow',Math.sin(a)*r,-.38-(i%4)*.16,Math.cos(a)*r*.82,.70,.65,.54,0,a);}
  for(let i=0;i<7;i++){const a=i*Math.PI*2/7;gem('azure',Math.sin(a)*2.3,-.83,Math.cos(a)*1.87,.13,.50,.15,a,.2);}
  taper('stone',0,.21,0,4.39,4.25,.21,24,.83);
  taper('ivory',0,.37,0,4.31,4.39,.13,24,.83);
  taper('gold',0,.48,0,4.22,4.26,.07,24,.83);
  taper('ivory',0,.56,0,4.17,4.22,.09,24,.83);
  // Gold compass inlay and radial terrace paving.
  for(let i=0;i<24;i++){const a=i*Math.PI/12;box('stone',Math.sin(a)*3.61,.613,Math.cos(a)*2.99,.023,.015,.64,a);}
  for(let i=0;i<8;i++){const a=i*Math.PI/4;gem('gold',Math.sin(a)*2.66,.63,Math.cos(a)*2.25,.15,.015,.39,a);}
  // Broad central senate chamber, drum windows and fluted pilasters.
  cyl('stone',0,.83,-.54,2.05,.45);cyl('ivory',0,1.78,-.54,1.86,1.59);cyl('gold',0,2.58,-.54,1.94,.12);
  for(let i=0;i<16;i++){const a=i*Math.PI/8,x=Math.sin(a)*1.87,z=-.54+Math.cos(a)*1.87;arch(x,1.27,z,.38,1.02,a);const b=a+Math.PI/16;column(Math.sin(b)*1.88,1.04,-.54+Math.cos(b)*1.88,1.43,.065);}
  cyl('white',0,2.76,-.54,2.03,.20);cyl('blue',0,3.02,-.54,1.74,.34);ring('gold',0,3.18,-.54,1.75,.052);
  for(let i=0;i<20;i++){const a=i*Math.PI/10;box('gold',Math.sin(a)*1.747,3.00,-.54+Math.cos(a)*1.747,.07,.28,.045,a);}
  // Dominant ribbed azure dome, deliberately wider than nearby castle roofs.
  ball('blue',0,3.23,-.54,1.86,1.20,1.86);ring('gold',0,3.24,-.54,1.87,.065);
  for(let i=0;i<20;i++){const a=i*Math.PI/10,pts=[];for(let j=0;j<9;j++){const t=j*Math.PI/16;pts.push([Math.sin(a)*1.87*Math.cos(t),3.25+1.21*Math.sin(t),-.54+Math.cos(a)*1.87*Math.cos(t)]);}line(i%2?'gold':'azure',pts,.032);}
  cyl('white',0,4.47,-.54,.48,.24);ring('gold',0,4.61,-.54,.50,.056);
  for(let i=0;i<8;i++){const a=i*Math.PI/4;line('gold',[[Math.sin(a)*.45,4.62,-.54+Math.cos(a)*.45],[Math.sin(a)*.59,4.91,-.54+Math.cos(a)*.59],[Math.sin(a)*.40,5.12,-.54+Math.cos(a)*.40]],.065);ball('gold',Math.sin(a)*.40,5.13,-.54+Math.cos(a)*.40,.065);}
  gem('cyan',0,5.19,-.54,.23,.64,.23);gem('white',.055,5.22,-.45,.12,.45,.11);
  // Symmetric arcaded council wings flank the central portico.
  for(const s of[-1,1]){
    box('stone',s*2.70,.88,-.11,1.35,.39,2.99);
    box('ivory',s*2.70,1.50,-.11,1.30,.88,2.92);
    box('white',s*2.70,2.07,-.11,1.51,.19,3.12);
    box('navy',s*2.70,2.29,-.11,1.39,.26,3.02);
    box('gold',s*2.70,2.44,-.11,1.51,.075,3.13);
    for(let j=0;j<5;j++){const z=-1.29+j*.58;arch(s*3.367,1.10,z,.40,.69,s*Math.PI/2);column(s*3.48,.98,z-.29,1.05,.066);}
    for(const z of[-1.59,1.37]){arch(s*2.70,1.08,z,.69,.77,0);for(const dx of[-.50,.50])column(s*2.70+dx,.99,z+.11,1.03,.08);}
    // Corner cupolas and treaty pavilions.
    for(const z of[-1.56,1.39]){
      cyl('ivory',s*2.70,2.65,z,.47,.35);ball('blue',s*2.70,2.88,z,.58,.37,.58);ring('gold',s*2.70,2.86,z,.59,.042);gem('gold',s*2.70,3.32,z,.075,.18);
    }
    // Trim hedges and open balustrades leave the main stairs unobstructed.
    for(let i=0;i<5;i++){const z=-1.43+i*.68;ball('leaf',s*3.75,.80,z,.20,.20,.28);box('stone',s*3.75,.64,z,.42,.11,.52);}
    for(let i=0;i<8;i++){const a=s*(.25+i*.10),x=Math.sin(a)*4.02,z=Math.cos(a)*3.34;column(x,.62,z,.43,.032);}
    line('gold',Array.from({length:16},(_,i)=>{const a=s*(.23+i*.05);return[Math.sin(a)*4.02,1.08,Math.cos(a)*3.34];}),.035);
  }
  // A ceremonial entrance: eight columns, triangular pediment, winged crest.
  box('stone',0,.83,1.95,2.55,.42,1.17);
  for(let i=0;i<10;i++)box('ivory',0,.65+i*.041,3.46-i*.13,2.27,.075,.16);
  for(const s of[-1,1])for(const x of[.37,.83,1.12])column(s*x,1.05,2.23,1.22,.095);
  box('white',0,2.35,2.10,2.73,.22,.79);box('gold',0,2.49,2.10,2.88,.095,.88);
  const pediment=new T.BufferGeometry();pediment.setAttribute('position',new T.Float32BufferAttribute([-1.46,0,.12,1.46,0,.12,0,.65,.12,-1.46,0,-.12,0,.65,-.12,1.46,0,-.12],3));pediment.computeVertexNormals();add(pediment,'ivory',0,2.53,2.29);pediment.dispose();
  line('gold',[[-1.49,2.53,2.43],[0,3.23,2.43],[1.49,2.53,2.43]],.07);
  ball('gold',0,2.76,2.47,.16,.16,.04);
  for(const s of[-1,1])for(let i=0;i<4;i++)gem('gold',s*(.21+i*.11),2.75+i*.048,2.47,.07,.17-i*.018,.025,0,s*.95);
  arch(0,1.07,1.40,.92,1.09,0,true);
  // Four small obelisks emphasize the civic, ceremonial silhouette.
  for(const s of[-1,1])for(const z of[-2.49,2.35]){const x=s*2.02;box('stone',x,.73,z,.41,.23,.41);taper('ivory',x,1.17,z,.105,.17,.73,4);gem('gold',x,1.63,z,.12,.17,.12);}
  // The underside mechanism slowly turns below the entire floating structure.
  const mechanism=new T.Group();mechanism.name='congress-levitation-ring';island.add(mechanism);target=mechanism;
  ring('gold',0,-1.02,0,2.76,.065);ring('azure',0,-1.08,0,2.62,.035);
  for(let i=0;i<12;i++){const a=i*Math.PI/6;gem('cyan',Math.sin(a)*2.75,-1.03,Math.cos(a)*2.75,.10,.22,.10,a,.3);}
  target=island;
  let drawCalls=0,triangles=0;
  for(const[parent,pigments]of batches)for(const[color,b]of pigments){
    if(!materials.has(color)){const m=new T.MeshStandardMaterial({color:palette[color],roughness:color==='gold'?.53:.82,metalness:color==='gold'?.18:0});m.onBeforeCompile=s=>{s.fragmentShader=s.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};m.customProgramCacheKey=()=> 'congress-pigment-1';materials.set(color,m);}
    const g=new T.BufferGeometry();g.setAttribute('position',new T.Float32BufferAttribute(b.p,3));g.setAttribute('normal',new T.Float32BufferAttribute(b.n,3));g.computeBoundingSphere();const mesh=new T.Mesh(g,materials.get(color));mesh.name=`congress-${color}`;mesh.castShadow=true;mesh.receiveShadow=true;parent.add(mesh);drawCalls++;triangles+=b.p.length/9;
  }
  Object.values(forms).forEach(g=>g.dispose());
  root.userData.modelStats={pieces,triangles,drawCalls};
  root.userData.animate=seconds=>{const phase=seconds*Math.PI/2;island.position.y=Math.sin(phase)*.10;mechanism.rotation.y=phase;};root.userData.animate(0);
  return root;
}
