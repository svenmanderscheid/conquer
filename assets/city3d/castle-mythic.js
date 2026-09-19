import * as T from './vendor/three.module.js';

// Mythic landmarks reconstructed from the four supplied skin cards. All static
// pieces share one geometry per pigment, keeping the islands inexpensive to draw.
export function buildMythicCastle(skin) {
  if (!['ape','cloud','fafnir','moonlight'].includes(skin)) return null;
  const root=new T.Group();root.name=`castle-skin-${skin}`;root.userData.building='keep';
  const colors={stone:'#80929c',light:'#c2cbc9',dark:'#243340',black:'#182431',rock:'#384b5a',wood:'#75503c',sand:'#bf956b',brown:'#805435',rust:'#b24e24',red:'#d83e26',gold:'#edb32f',shine:'#ffe887',green:'#5b7f39',leaf:'#829840',jade:'#347a6d',water:'#47c7d3',ice:'#c0faff',white:'#f0f4ec',violet:'#596483'};
  const batches=new Map(), m=new T.Matrix4(),nm=new T.Matrix3(),pos=new T.Vector3(),q=new T.Quaternion(),e=new T.Euler(),sc=new T.Vector3(),p=new T.Vector3(),n=new T.Vector3();
  const shapes={box:new T.BoxGeometry(1,1,1).toNonIndexed(),ball:new T.SphereGeometry(1,10,7).toNonIndexed(),rock:new T.IcosahedronGeometry(1,0),cyl:new T.CylinderGeometry(1,1,1,12).toNonIndexed(),cone:new T.ConeGeometry(1,1,8).toNonIndexed()};
  let pieces=0;
  function add(g,c,x=0,y=0,z=0,sx=1,sy=1,sz=1,rx=0,ry=0,rz=0){
    if(!batches.has(c))batches.set(c,{p:[],n:[]});const b=batches.get(c),gp=g.attributes.position,gn=g.attributes.normal;
    m.compose(pos.set(x,y,z),q.setFromEuler(e.set(rx,ry,rz)),sc.set(sx,sy,sz));nm.getNormalMatrix(m);
    for(let i=0;i<gp.count;i++){p.fromBufferAttribute(gp,i).applyMatrix4(m);n.fromBufferAttribute(gn,i).applyMatrix3(nm).normalize();b.p.push(p.x,p.y,p.z);b.n.push(n.x,n.y,n.z);}pieces++;
  }
  const box=(c,x,y,z,w,h,d,ry=0,rz=0)=>add(shapes.box,c,x,y,z,w,h,d,0,ry,rz);
  const ball=(c,x,y,z,w,h=w,d=w)=>add(shapes.ball,c,x,y,z,w,h,d);
  const rock=(c,x,y,z,w,h=w,d=w,ry=0)=>add(shapes.rock,c,x,y,z,w,h,d,0,ry,0);
  const cyl=(c,x,y,z,r,h)=>add(shapes.cyl,c,x,y,z,r,h,r);
  const cone=(c,x,y,z,r,h)=>add(shapes.cone,c,x,y,z,r,h,r);
  function line(c,pts,r=.05){const g=new T.TubeGeometry(new T.CatmullRomCurve3(pts.map(v=>new T.Vector3(...v))),Math.max(8,pts.length*3),r,5,false).toNonIndexed();add(g,c);g.dispose();}
  function tapered(c,x,y,z,rt,rb,h,sides=10){const g=new T.CylinderGeometry(rt,rb,h,sides).toNonIndexed();add(g,c,x,y,z);g.dispose();}
  function ring(c,x,y,z,r,t=.06,rx=Math.PI/2){const g=new T.TorusGeometry(r,t,5,24).toNonIndexed();add(g,c,x,y,z,1,1,1,rx);g.dispose();}
  function rockbase(c='rock',r=2.7,height=1){
    tapered(c,0,height*.45,0,r*.85,r*.66,height,9);
    for(let i=0;i<15;i++){const a=i*Math.PI*2/15;rock(i%3?'rock':c,Math.cos(a)*r*.74,height*.4,Math.sin(a)*r*.74,.7,height*.6,.65,a);}
  }
  function tree(x,y,z,s=1){cyl('wood',x,y+s*.4,z,.08*s,s*.8);for(let i=0;i<3;i++)cone('green',x,y+s*(.8+i*.37),z,s*(.51-i*.12),s*.95);}
  function stairs(x,y,z,count=7,w=.85){for(let i=0;i<count;i++)box('light',x,y+i*.11,z-i*.15,w,.12,.26);}
  function tower(x,y,z,r,h,roof=false){
    cyl('stone',x,y+h/2,z,r,h);for(let k=0;k<5;k++)ring(k%2?'light':'rock',x,y+k*h/5,z,r+.018,.025);
    for(let k=0;k<8;k++){const a=k*Math.PI/4;box('light',x+Math.sin(a)*r*.88,y+h+.12,z+Math.cos(a)*r*.88,.23,.3,.23,a);}
    if(roof){cone('rust',x,y+h+.65,z,r*1.4,1.3);cone('gold',x,y+h+1.34,z,.06,.35);}
    for(const a of [0,Math.PI/2])box('dark',x+Math.sin(a)*r*.98,y+h*.64,z+Math.cos(a)*r*.98,.12,h*.3,.055,a);
  }
  function ape(){
    rockbase('rock',2.8,1.5);cyl('stone',0,1.77,0,1.65,1.65);cyl('light',0,2.63,0,1.8,.2);cyl('wood',0,2.8,0,1.56,.14);cyl('stone',0,3.28,-.1,1.27,.96);
    for(let row=0;row<4;row++)for(let k=0;k<22;k++){const a=(k+row*.5)*Math.PI*2/22;box(row%2?'light':'rock',Math.sin(a)*1.66,1.2+row*.35,Math.cos(a)*1.66,.35,.03,.06,a);}
    for(let k=0;k<16;k++){const a=k*Math.PI/8;box('light',Math.sin(a)*1.67,2.87,Math.cos(a)*1.67,.25,.38,.25,a);}
    tower(-1.9,1.1,1.1,.52,1.6);tower(1.9,1.1,-.1,.48,2.1);tower(-1.35,2.4,-1.05,.37,1.15,true);tower(1.05,3,-.65,.39,1.02,true);tower(.05,3.5,.05,.64,1.25,true);
    box('dark',.28,1.4,1.61,.8,1.4,.13);stairs(.27,.05,2.72,8,1.05);
    // Gorilla clings around the rear keep: shoulders, broad arms, expressive face.
    ball('black',-1.45,4.35,-.77,.85,1.05,.61);ball('black',-1.85,5.02,-.29,.64,.66,.48);
    ball('stone',-1.86,5.1,.06,.43,.41,.19);ball('black',-1.86,5.18,.2,.38,.11,.1);
    for(const dx of [-.16,.16])ball('gold',-1.86+dx,5.13,.28,.044,.034,.025);
    ball('black',-1.85,4.95,.25,.28,.19,.1);ball('red',-1.85,4.94,.325,.15,.1,.026);
    for(const dx of [-.12,.12])cone('white',-1.85+dx,4.98,.34,.045,.19);
    line('black',[[-1.9,4.61,-.6],[-2.65,4.23,-.1],[-2.73,3.5,.48]],.3);ball('black',-2.72,3.4,.53,.35,.21,.33);
    line('black',[[-1.1,4.65,-.7],[-.93,5.36,-.72],[-.3,5.55,-.43]],.28);ball('black',-.23,5.5,-.38,.33,.25,.3);
    // Jade ape guardian above the entry, with torch pillars and votive eyes.
    box('jade',1.38,.89,1.62,1.11,.68,.5);ball('green',1.37,1.65,1.7,.43,.52,.33);ball('leaf',1.37,1.72,1.98,.3,.26,.1);
    for(const dx of [-.15,.15])box('gold',1.37+dx,1.76,2.08,.09,.06,.03);
    for(const dx of [-.54,.54]){box('green',1.37+dx,1.16,1.73,.24,.83,.36);box('gold',1.37+dx,1.53,1.95,.17,.13,.05);}
    for(const x of [-1.5,1.3]){cyl('wood',x,.62,2.42,.08,.74);cone('red',x,1.1,2.42,.18,.55);ball('shine',x,1.05,2.42,.11,.23,.11);}
  }
  function cloud(){
    rockbase('stone',2.65,1.12);cyl('green',0,.94,0,2.48,.22);cyl('light',0,1.08,0,2.15,.12);cyl('water',0,1.17,0,1.97,.07);
    // White billows embrace the island, while the temple remains readable.
    for(let i=0;i<24;i++){const a=i*2.399,r=2.2+(i%3)*.18;ball(i%3?'white':'ice',Math.cos(a)*r,.48+(i%4)*.23,Math.sin(a)*r,.57,.29,.43);}
    cyl('wood',0,1.37,-.32,1.15,.4);
    for(let level=0;level<3;level++){
      const y=1.3+level*1.23,r=1.67-level*.31;
      tapered('gold',0,y+.36,-.32,r*.55,r,.63,10);ring('rust',0,y+.06,-.32,r,.065);
      cyl('wood',0,y+.88,-.32,r*.54,.61);
      for(let i=0;i<10;i++){const a=i*Math.PI/5,x=Math.sin(a)*r*.56,z=-.32+Math.cos(a)*r*.56;box('ice',x,y+.93,z,.19,.26,.045,a);box('gold',x,y+.93,z,.035,.53,.06,a);line('shine',[[Math.sin(a)*r,y+.07,-.32+Math.cos(a)*r],[Math.sin(a)*r*.74,y+.32,-.32+Math.cos(a)*r*.74],[Math.sin(a)*r*.55,y+.67,-.32+Math.cos(a)*r*.55]],.022);}
    }
    cone('gold',0,5.25,-.32,.7,.75);cyl('wood',0,5.61,-.32,.19,.32);ball('ice',0,5.92,-.32,.15,.24,.15);cone('gold',0,6.15,-.32,.07,.24);
    // Clock-like raised front gable and a bridge over the water garden.
    box('wood',.55,2.13,1,.72,1.09,.48);tapered('gold',.55,2.83,1,.1,.65,.38,4);for(const dx of [-.29,.29])box('gold',.55+dx,2.13,1.26,.045,1.04,.04);
    ring('shine',.55,2.55,1.27,.24,.055,0);ball('ice',.55,2.55,1.27,.2,.2,.025);box('gold',.55,2.6,1.3,.035,.2,.025);box('gold',.62,2.55,1.3,.14,.035,.025);
    box('light',0,1.29,1.62,.7,.14,1.43);for(const x of [-.43,.43]){for(let i=0;i<5;i++)cyl('gold',x,1.54,.99+i*.27,.035,.55);line('gold',[[x,1.82,.9],[x,1.88,1.5],[x,1.78,2.2]],.035);}
    for(const [x,z]of[[-1.9,-.6],[1.68,-1.18],[-1.78,1.12]]){line('wood',[[x,1.02,z],[x+.12,1.74,z],[x-.1,2.26,z]],.08);for(let i=0;i<4;i++)ball('green',x+Math.cos(i*2.4)*.29,2.15+(i%2)*.3,z+Math.sin(i*2.4)*.27,.38,.28,.37);}
    cyl('water',1.68,.71,1.64,.69,.1);ring('light',1.68,.71,1.64,.71,.07);line('water',[[1.84,1.17,.75],[1.9,1.07,1.3],[1.9,.78,1.56]],.12);
    for(let i=0;i<5;i++){ball('leaf',1.7+Math.sin(i)*.34,.82,1.7+Math.cos(i)*.31,.12,.025,.08);ball('white',1.7+Math.sin(i)*.34,.87,1.7+Math.cos(i)*.31,.065,.05,.065);}
  }
  function roofHall(x,y,z,w,h,d){
    const g=new T.BufferGeometry();g.setAttribute('position',new T.Float32BufferAttribute([-w/2,0,-d/2,w/2,0,-d/2,0,h,-d/2,-w/2,0,d/2,0,h,d/2,w/2,0,d/2,-w/2,0,-d/2,0,h,-d/2,0,h,d/2,-w/2,0,-d/2,0,h,d/2,-w/2,0,d/2,w/2,0,-d/2,w/2,0,d/2,0,h,d/2,w/2,0,-d/2,0,h,d/2,0,h,-d/2],3));const vertices=g.attributes.position.array;for(let i=0;i<vertices.length;i+=9)for(let j=0;j<3;j++){const swap=vertices[i+3+j];vertices[i+3+j]=vertices[i+6+j];vertices[i+6+j]=swap;}g.computeVertexNormals();add(g,'dark',x,y,z);g.dispose();
    for(let i=0;i<9;i++){const zz=z-d/2+i*d/8;line('rock',[[x-w/2,y,zz],[x,y+h,zz],[x+w/2,y,zz]],.035);}
    for(const zz of [z-d/2,z+d/2])line('sand',[[x-w/2-.12,y-.1,zz],[x,y+h+.12,zz],[x+w/2+.12,y-.1,zz]],.09);
  }
  function fafnir(){
    cyl('water',0,.08,0,2.85,.12);rockbase('stone',2.58,1.17);cyl('green',0,1.04,0,2.14,.15);
    for(let i=0;i<7;i++){const a=i*Math.PI*.29;rock('light',Math.cos(a)*2.32,.72,Math.sin(a)*2.32,.43,.8,.42,a);}
    box('wood',0,2.01,-.38,2.29,1.91,2.86);roofHall(0,2.18,-.38,3.1,2.55,3.18);
    for(const x of [-1.12,1.12])for(let i=0;i<7;i++)box('sand',x,1.92,-1.55+i*.38,.075,1.7,.045);
    for(const z of [-1.69,.96]){line('sand',[[0,4.74,z],[0,5.08,z+.12],[.31,5.27,z+.18]],.09);ball('gold',.36,5.27,z+.18,.12,.1,.11);}
    // Carved timber panels and a tower behind the dragon-fronted great hall.
    for(let i=0;i<8;i++)box('sand',-1.16,1.31+i*.15,-.38,.06,.035,2.68);
    box('wood',.47,3.39,-1.13,.73,1.23,.77);cone('violet',.47,4.54,-1.13,.63,1.72);cyl('gold',.47,5.59,-1.13,.024,1.05);box('red',.87,5.94,-1.13,.8,.23,.045,0,.11);
    box('black',0,1.67,1.1,.96,1.23,.17);for(let i=0;i<5;i++)box('sand',0,1.07+i*.06,1.93-i*.15,1.15,.09,.21);
    for(const x of [-.73,.73])box('red',x,1.71,1.22,.16,1.23,.16,0,x*.4);
    // White-blue dragon mask with curved horns over the portal.
    ball('stone',.12,2.76,1.16,.57,.3,.54);ball('ice',.12,2.66,1.6,.42,.2,.41);ball('dark',.12,2.55,1.72,.33,.045,.26);
    for(const s of [-1,1]){ball('red',.12+s*.27,2.83,1.54,.09,.07,.06);line('white',[[.12+s*.38,2.81,1.25],[.12+s*.69,3.12,1.07],[.12+s*.84,3.19,.81]],.09);cone('white',.12+s*.24,2.51,1.74,.06,.2);}
    // Curved longship beside the quay, with striped sail and shields.
    ball('wood',-1.8,.46,1.5,.49,.27,.95);ball('dark',-1.8,.63,1.5,.35,.05,.74);
    line('wood',[[-1.8,.52,.56],[-1.8,.91,.37],[-1.8,1.17,.44]],.1);line('wood',[[-1.8,.52,2.42],[-1.8,.85,2.57]],.1);
    cyl('wood',-1.8,1.25,1.52,.037,1.63);box('wood',-1.8,1.98,1.52,1.04,.04,.045);
    for(let i=0;i<6;i++){const x=-2.25+i*.18;box(i%2?'red':'white',x,1.54,1.52,.18,.88,.055,0,-.09);}
    for(let i=0;i<4;i++)ball(i%2?'gold':'red',-1.34,.56,.95+i*.34,.055,.13,.13);
    for(let i=0;i<9;i++)box('wood',-.67+i*.18,.34,2.31,.13,.08,.63);
    tree(1.45,1.03,-1.58,1.05);tree(2.01,.71,-1.1,.86);
  }
  function moonlight(){
    rockbase('brown',2.65,1.7);
    for(let i=0;i<13;i++){const a=i*2.399,r=1.75+(i%2)*.35;rock(i%3?'brown':'sand',Math.cos(a)*r,1.27+(i%4)*.38,Math.sin(a)*r,.56,.97,.49,a);}
    cyl('dark',0,2.04,0,1.42,.22);cyl('stone',0,2.22,0,1.28,.3);cyl('water',0,2.4,0,1.04,.055);cyl('ice',0,2.44,0,.72,.035);ring('light',0,2.43,0,1.19,.12);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;box('light',Math.cos(a)*1.34,2.59,Math.sin(a)*1.34,.2,.48,.25,-a);}
    rock('brown',-.75,3.08,-.85,.94,1.74,.73);rock('sand',.92,2.76,-.96,.6,1.18,.6);rock('rock',-.55,3.4,-.61,.52,1.32,.48);
    // Solid gold crescent, its inner silhouette cut cleanly through the mesh.
    const R=1.39,r=1.22,d=.65,ix=(R*R-r*r+d*d)/(2*d),iy=Math.sqrt(R*R-ix*ix),a=Math.atan2(iy,ix),b=Math.atan2(iy,ix-d);
    const moon=new T.Shape();moon.moveTo(ix,iy);moon.absarc(0,0,R,a,Math.PI*2-a,false);moon.absarc(d,0,r,Math.PI*2-b,b,true);moon.closePath();
    const g=new T.ExtrudeGeometry(moon,{depth:.22,bevelEnabled:true,bevelThickness:.04,bevelSize:.035,bevelSegments:1,curveSegments:24});add(g,'gold',0,5.07,-.46);g.dispose();
    // The crescent pours luminous strands into the circular observatory pool.
    for(let i=0;i<7;i++){const x=-.3+i*.105;line(i%2?'water':'ice',[[x,4.2,-.12],[x+.05,3.63,-.09],[x-.03,3.03,-.03],[x,2.46,.03]],i%2?.033:.02);}
    ring('ice',0,2.46,.02,.48,.035);ring('water',0,2.45,.02,.78,.025);
    stairs(-1.12,.07,2.37,11,.68);box('dark',-.94,1.11,1.47,.68,.97,.2);box('light',-.94,1.67,1.48,.91,.12,.25);
    for(const x of [-1.38,-.51])box('light',x,1.16,1.5,.13,1.01,.23);
    for(const [x,z] of [[1.47,.7],[1.1,-1.4]]){cyl('stone',x,1.81,z,.25,1.1);cyl('dark',x,2.38,z,.35,.12);cone('dark',x,2.7,z,.36,.62);box('shine',x,1.95,z+.24,.1,.44,.035);}
    for(let i=0;i<7;i++){const a=i*2.4;ball('green',Math.cos(a)*2.09,.63,Math.sin(a)*2.09,.23,.15,.22);}
    // Satellite stars are geometry too, retained in portraits and village view.
    for(const [x,y,z,s] of [[1.4,5.31,-.15,.19],[.62,6.02,.07,.11],[-1.59,3.61,.42,.12],[1.35,3.71,.15,.09]]){
      const st=new T.Shape();for(let i=0;i<10;i++){const a=Math.PI/2+i*Math.PI/5,rr=i%2?s*.43:s;const px=x+Math.cos(a)*rr,py=y+Math.sin(a)*rr;i?st.lineTo(px,py):st.moveTo(px,py);}st.closePath();const sg=new T.ExtrudeGeometry(st,{depth:.035,bevelEnabled:false});add(sg,'shine',0,0,z);sg.dispose();
    }
  }
  ({ape,cloud,fafnir,moonlight})[skin]();
  let triangles=0;
  for(const [c,b]of batches){const g=new T.BufferGeometry();g.setAttribute('position',new T.Float32BufferAttribute(b.p,3));g.setAttribute('normal',new T.Float32BufferAttribute(b.n,3));g.computeBoundingSphere();
    const material=new T.MeshStandardMaterial({color:colors[c],roughness:c==='gold'?.56:.92,metalness:c==='gold'?.16:0,emissive:['ice','shine'].includes(c)?colors[c]:'#000000',emissiveIntensity:c==='ice'?.16:c==='shine'?.1:0});
    material.onBeforeCompile=shader=>{shader.fragmentShader=shader.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};material.customProgramCacheKey=()=> 'castle-mythic-pigment-1';
    const mesh=new T.Mesh(g,material);mesh.name=`${skin}-${c}`;mesh.castShadow=true;mesh.receiveShadow=true;mesh.userData.building='keep';root.add(mesh);triangles+=b.p.length/9;
  }
  Object.values(shapes).forEach(g=>g.dispose());root.userData.skin=skin;root.userData.modelStats={drawCalls:batches.size,triangles,pieces};return root;
}
