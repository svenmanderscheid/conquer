import * as T from './vendor/three.module.js';

// Original mythic landmarks. Pigment batches and ornament groups are owned by
// each castle, so disposing one skin never releases another skin's resources.
export function buildOriginalMythicB(skin) {
  if (!['yggdrasil','tempest','eclipse'].includes(skin)) return null;
  const root=new T.Group();root.name=`castle-skin-${skin}`;root.userData.building='keep';
  const colors={earth:'#534934',bark:'#765335',wood:'#ac7f4b',moss:'#386b48',leaf:'#579851',jade:'#24785e',copper:'#bf9252',gold:'#f3bd45',light:'#c6d3d0',white:'#eaf3e5',blue:'#486b9b',rock:'#56677a',dark:'#273044',black:'#181b32',purple:'#57457b',violet:'#9265db',ice:'#8bdaef',glow:'#aaff96',shine:'#fff0a5'};
  const batches=new Map(),materials=new Map(),motions=[];
  const matrix=new T.Matrix4(),normalMatrix=new T.Matrix3(),position=new T.Vector3(),scale=new T.Vector3(),quaternion=new T.Quaternion(),euler=new T.Euler(),point=new T.Vector3(),normal=new T.Vector3();
  const shapes={box:new T.BoxGeometry(1,1,1).toNonIndexed(),ball:new T.SphereGeometry(1,10,7).toNonIndexed(),rock:new T.IcosahedronGeometry(1,0),cylinder:new T.CylinderGeometry(1,1,1,12).toNonIndexed(),cone:new T.ConeGeometry(1,1,8).toNonIndexed(),crystal:new T.OctahedronGeometry(1,0)};
  let target=root,pieces=0;
  function add(geometry,color,x=0,y=0,z=0,sx=1,sy=1,sz=1,rx=0,ry=0,rz=0){
    let pigments=batches.get(target);if(!pigments){pigments=new Map();batches.set(target,pigments);}if(!pigments.has(color))pigments.set(color,{p:[],n:[]});const batch=pigments.get(color),gp=geometry.attributes.position,gn=geometry.attributes.normal;
    matrix.compose(position.set(x,y,z),quaternion.setFromEuler(euler.set(rx,ry,rz)),scale.set(sx,sy,sz));normalMatrix.getNormalMatrix(matrix);
    for(let i=0;i<gp.count;i++){point.fromBufferAttribute(gp,i).applyMatrix4(matrix);normal.fromBufferAttribute(gn,i).applyMatrix3(normalMatrix).normalize();batch.p.push(point.x,point.y,point.z);batch.n.push(normal.x,normal.y,normal.z);}pieces++;
  }
  const box=(c,x,y,z,w,h,d,ry=0,rz=0)=>add(shapes.box,c,x,y,z,w,h,d,0,ry,rz);
  const ball=(c,x,y,z,w,h=w,d=w)=>add(shapes.ball,c,x,y,z,w,h,d);
  const rock=(c,x,y,z,w,h=w,d=w,ry=0)=>add(shapes.rock,c,x,y,z,w,h,d,0,ry,0);
  const crystal=(c,x,y,z,w,h=w,d=w,ry=0,rz=0)=>add(shapes.crystal,c,x,y,z,w,h,d,0,ry,rz);
  const cyl=(c,x,y,z,r,h)=>add(shapes.cylinder,c,x,y,z,r,h,r);
  const cone=(c,x,y,z,r,h)=>add(shapes.cone,c,x,y,z,r,h,r);
  function taper(c,x,y,z,rt,rb,h,sides=12){const g=new T.CylinderGeometry(rt,rb,h,sides).toNonIndexed();add(g,c,x,y,z);g.dispose();}
  function line(c,pts,r=.045){const g=new T.TubeGeometry(new T.CatmullRomCurve3(pts.map(v=>new T.Vector3(...v))),Math.max(8,pts.length*3),r,5,false).toNonIndexed();add(g,c);g.dispose();}
  function ring(c,x,y,z,r,t=.055,rx=Math.PI/2,ry=0,rz=0){const g=new T.TorusGeometry(r,t,5,32).toNonIndexed();add(g,c,x,y,z,1,1,1,rx,ry,rz);g.dispose();}
  function moving(name,x,y,z,build,animate){const group=new T.Group();group.name=name;group.position.set(x,y,z);root.add(group);target=group;build();target=root;motions.push(t=>animate(group,t));return group;}
  function stairs(x,y,z,count,w=.9){for(let i=0;i<count;i++)box('light',x,y+i*.105,z-i*.14,w,.13,.23);}
  function foundation(c='rock',r=2.5,h=.8){taper(c,0,h*.55,0,r,r*.63,h,10);for(let i=0;i<13;i++){const a=i*2.399;rock(c,Math.cos(a)*r*.73,h*.65,Math.sin(a)*r*.73,.65,h*.65,.55,a);}}
  function window(x,y,z,w=.17,h=.48,c='shine',ry=0){box('dark',x,y,z,w+.12,h+.13,.07,ry);box(c,x,y,z+.015,w,h,.08,ry);}
  function yggdrasil(){
    foundation('earth',2.55,.7);cyl('moss',0,.69,0,2.35,.15);
    // A living trunk wraps around a habitable, terraced heartwood keep.
    taper('bark',0,2.3,-.32,.58,1.09,3.5,11);ball('bark',.08,3.8,-.38,.74,.81,.66);
    for(let i=0;i<11;i++){const a=i*Math.PI*2/11,x=Math.sin(a),z=Math.cos(a);line(i%3?'bark':'wood',[[x*2.42,.54,z*2.42],[x*1.55,.76,z*1.55-.1],[x*.83,1.39,z*.83-.25],[x*.66,2.9,z*.66-.32]],.17+(i%2)*.045);}
    for(let i=0;i<7;i++){const a=i*2.399;line('wood',[[Math.sin(a)*.96,1.05,Math.cos(a)*.96-.32],[Math.sin(a)*.8,1.9,Math.cos(a)*.8-.32],[Math.sin(a+.12)*.57,3.6,Math.cos(a+.12)*.57-.32]],.035);}
    for(const [x,y,z] of [[-2,4.35,-.2],[1.9,4.4,-.5],[-1.3,5.05,-1.1],[.8,5.4,-.9],[.1,5.75,-.4]]){
      line('bark',[[0,2.8,-.32],[x*.45,3.8,z*.65],[x,y-.2,z]],.2);line('wood',[[x*.3,3.6,z*.5],[x*.62,4.2,z*.7],[x,y,z]],.06);
      for(let i=0;i<5;i++){const a=i*2.4;rock(i%3?'leaf':'moss',x+Math.cos(a)*.44,y+(i%2)*.28,z+Math.sin(a)*.33,.7,.49,.65,a);}
      for(let i=0;i<3;i++){const xx=x+Math.sin(i)*.38,zz=z+Math.cos(i)*.28;line('moss',[[xx,y-.25,zz],[xx+.05,y-.64,zz],[xx-.08,y-.95,zz]],.032);}
    }
    // Copper balconies bridge emerald turrets grown into the roots.
    for(const [x,y,z,r,h]of[[-1.34,.82,.67,.48,1.82],[1.24,.88,.8,.52,2.24],[-1.18,1.02,-1.19,.41,2.36]]){
      cyl('wood',x,y+h*.5,z,r,h);for(let i=0;i<5;i++)ring('copper',x,y+i*h/5,z,r+.02,.025);
      taper('jade',x,y+h+.4,z,.065,r*1.45,.91,8);ring('copper',x,y+h-.05,z,r*1.42,.047);cone('gold',x,y+h+.95,z,.065,.24);
      window(x,y+h*.59,z+r,.19,.6);for(const dx of[-.2,.2])box('copper',x+dx,y+h*.61,z+r+.07,.035,.8,.04);
    }
    for(const y of [1.08,2.58]){cyl('wood',0,y,.25,1.23,.16);ring('copper',0,y+.13,.25,1.25,.06);for(let i=0;i<15;i++){const a=i*Math.PI*2/15;if(Math.cos(a)<-.4)continue;cyl('copper',Math.sin(a)*1.24,y+.32,.25+Math.cos(a)*1.24,.023,.42);}ring('copper',0,y+.52,.25,1.24,.034);}
    box('dark',0,1.49,.79,.63,1.23,.15);box('shine',0,1.71,.9,.27,.65,.06);for(const x of[-.37,.37])line('copper',[[x,1.04,.95],[x,1.83,.92],[x*.45,2.15,.85]],.07);ball('gold',0,2.18,.82,.12,.14,.07);
    stairs(0,.64,2.17,5,.72);box('wood',0,1.08,1.72,.8,.12,.65);
    // Radiant sap veins remain visible under the branches.
    line('glow',[[.73,.82,.25],[.8,1.56,.14],[.52,2.05,.31],[.57,2.85,.07],[.27,3.62,.04]],.034);
    for(let i=0;i<7;i++){const a=i*2.399;ball('glow',Math.cos(a)*1.95,.9+(i%3)*.24,Math.sin(a)*1.93,.07,.1,.065);}
    moving('orbiting-leaf-runes',0,3.55,-.05,()=>{for(let i=0;i<8;i++){const a=i*Math.PI/4,x=Math.cos(a)*2.47,z=Math.sin(a)*2.47,y=Math.sin(i*1.7)*.32;crystal('glow',x,y,z,.13,.24,.06,-a,.45);line('gold',[[x-.09,y-.06,z],[x,y+.16,z],[x+.09,y-.06,z]],.018);}},(g,t)=>{g.rotation.y=t;g.position.y=3.55+Math.sin(t*2)*.16;});
    moving('woodland-fireflies',0,0,0,()=>{for(let i=0;i<10;i++){const a=i*2.399;ball('shine',Math.cos(a)*2.55,1.45+(i%4)*.62,Math.sin(a)*2.45,.047);}},(g,t)=>{g.rotation.y=-t;g.position.y=Math.sin(t*2)*.1;});
  }
  function tempest(){
    // Exposed, shattered underside makes the white citadel appear suspended.
    foundation('rock',2.41,1.1);taper('blue',0,.86,0,2.11,1.55,.48,10);cyl('light',0,1.14,0,2.25,.24);ring('gold',0,1.29,0,2.17,.052);
    for(let i=0;i<10;i++){const a=i*2.399;crystal('rock',Math.cos(a)*1.85,.71,Math.sin(a)*1.85,.41,.65,.4,a,.2);line('ice',[[Math.cos(a)*2.18,.98,Math.sin(a)*2.18],[Math.cos(a+.07)*1.88,.63,Math.sin(a+.07)*1.88],[Math.cos(a)*1.78,.32,Math.sin(a)*1.78]],.025);}
    cyl('white',0,2.53,-.24,.94,2.62);cyl('blue',0,1.56,-.24,1.08,.35);cyl('light',0,3.83,-.24,1.04,.22);cone('blue',0,4.39,-.24,1.14,1.02);ring('gold',0,3.93,-.24,1.1,.055);
    for(let i=0;i<8;i++){const a=i*Math.PI/4,x=Math.sin(a),z=Math.cos(a);box('light',x*.92,2.48,-.24+z*.92,.12,2.38,.13,a);box('ice',x*.96,2.69,-.24+z*.96,.17,.88,.055,a);box('gold',x*.99,3.25,-.24+z*.99,.2,.05,.07,a);}
    for(const [x,z,h]of[[-1.49,.82,1.72],[1.5,.64,2.03],[-1.13,-1.34,2.31],[1.2,-1.24,2.06]]){
      cyl('white',x,1.35+h*.5,z,.38,h);cyl('blue',x,1.45,z,.45,.3);ring('gold',x,1.35+h,z,.45,.045);cone('blue',x,1.73+h,z,.52,.85);
      window(x,1.51+h*.51,z+.375,.13,.53,'ice');cyl('gold',x,2.09+h,z,.025,.64);crystal('ice',x,2.48+h,z,.11,.21,.11);
      for(const s of[-1,1])line('gold',[[x+s*.17,1.96+h,z],[x+s*.27,2.32+h,z],[x+s*.15,2.56+h,z]],.025);
    }
    box('dark',0,1.88,.74,.65,1.08,.17);box('ice',0,2.13,.85,.32,.49,.08);for(const x of[-.46,.46])box('light',x,1.94,.84,.17,1.27,.28);box('gold',0,2.61,.85,1.03,.13,.28);stairs(0,.35,2.65,9,.99);
    for(const s of[-1,1]){line('light',[[s*.75,1.35,1.65],[s*1.45,1.35,1.39],[s*1.87,1.35,.68]],.1);for(let i=0;i<3;i++)box('gold',s*(.86+i*.28),1.57,1.59-i*.21,.075,.42,.075);}
    // The lightning crown is a separate rotating architectural landmark.
    moving('storm-crown',0,5.36,-.24,()=>{ring('gold',0,0,0,.68,.062);ring('ice',0,.05,0,.73,.035);for(let i=0;i<7;i++){const a=i*Math.PI*2/7,x=Math.sin(a),z=Math.cos(a);line('gold',[[x*.66,0,z*.66],[x*.87,.25,z*.87],[x*.65,.43,z*.65],[x*.77,.72,z*.77]],.052);crystal('ice',x*.77,.77,z*.77,.075,.15,.075);}},(g,t)=>{g.rotation.y=t;g.position.y=5.36+Math.sin(t*2)*.09;});
    moving('storm-orbit',0,4.96,-.24,()=>{ring('ice',0,0,0,1.48,.04);for(let i=0;i<8;i++){const a=i*Math.PI/4;crystal('ice',Math.cos(a)*1.48,0,Math.sin(a)*1.48,.085,.18,.085,a,.25);}},(g,t)=>{g.rotation.set(.2+Math.sin(t)*.14,-t,.24);});
    moving('orbiting-storm-clouds',0,0,0,()=>{for(let i=0;i<12;i++){const a=i*2.399;ball(i%3?'white':'ice',Math.cos(a)*2.25,.91+(i%3)*.14,Math.sin(a)*2.25,.42,.16,.31);}},(g,t)=>{g.rotation.y=-t;g.position.y=Math.sin(t*2)*.055;});
  }
  function eclipse(){
    foundation('black',2.52,.74);cyl('purple',0,.75,0,2.28,.21);cyl('black',0,.89,0,2.12,.09);ring('gold',0,.95,0,2.08,.04);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;box('violet',Math.sin(a)*2.18,.87,Math.cos(a)*2.18,.12,.12,.36,a);}
    // A tiered obsidian sanctum, framed by blade-like pinnacles.
    taper('dark',0,1.52,-.18,.96,1.47,1.12,4);box('black',0,2.3,-.31,1.57,1.46,1.56);taper('purple',0,3.34,-.31,.45,1.23,1.15,4);cone('gold',0,3.96,-.31,.075,.31);
    for(const x of[-.86,.86]){box('gold',x,2.05,.46,.055,1.8,.055);line('violet',[[x,1.24,.57],[x*.72,2.09,.57],[x*.72,2.84,.41]],.035);}
    for(const [x,z,h]of[[-1.59,.54,2.73],[1.52,.55,2.94],[-1.11,-1.31,3.37],[1.13,-1.22,3.12]]){
      taper('dark',x,1.37,z,.31,.57,.79,6);taper('black',x,1.71+h*.32,z,.2,.36,h*.65,6);crystal('purple',x,1.35+h,z,.37,.77,.32);crystal('gold',x,1.69+h,z,.045,.54,.045);
      box('violet',x,1.67+h*.25,z+.31,.12,h*.54,.045);ring('gold',x,1.1,z,.52,.037);
    }
    box('black',0,1.78,.98,.78,1.36,.2);box('violet',0,1.84,1.095,.4,.92,.055);box('gold',0,1.84,1.14,.045,.88,.065);for(const x of[-.51,.51])box('gold',x,1.8,1.05,.09,1.45,.17);box('gold',0,2.54,1.04,1.05,.1,.17);
    stairs(0,.12,2.55,9,1.03);for(const s of[-1,1]){box('dark',s*.7,.87,1.8,.2,.48,.98);crystal('violet',s*.73,1.27,2.08,.15,.33,.14);}
    // Huge eclipsed sun, held above the sanctum by carved crescent pylons.
    for(const s of[-1,1])line('purple',[[s*.91,2.6,-.59],[s*1.44,3.57,-.59],[s*1.47,4.53,-.59],[s*1.09,5.12,-.59]],.13);
    const eclipseMount=new T.Group();eclipseMount.position.set(0,4.92,-.56);eclipseMount.rotation.y=.45;root.add(eclipseMount);target=eclipseMount;
    add(shapes.cylinder,'black',0,0,0,1.07,.18,1.07,Math.PI/2);ring('gold',0,0,.1,1.12,.068,0);ring('shine',0,0,.115,1.2,.032,0);ring('purple',0,0,.13,.83,.034,0);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;box('purple',Math.sin(a)*.91,Math.cos(a)*.91,.115,.035,.13,.035,0,-a);}
    target=root;
    const corona=new T.Group();corona.name='eclipse-corona';eclipseMount.add(corona);target=corona;
    for(let i=0;i<20;i++){const a=i*Math.PI/10,r=1.27;crystal(i%2?'gold':'shine',Math.sin(a)*r,Math.cos(a)*r,.06,.044,i%2?.16:.23,.035,0,-a);}
    target=root;motions.push(t=>{corona.rotation.z=t;eclipseMount.rotation.y=.45+Math.sin(t)*.12;});
    moving('obsidian-satellites',0,2.64,0,()=>{for(let i=0;i<6;i++){const a=i*Math.PI/3,x=Math.cos(a)*2.42,z=Math.sin(a)*2.42;crystal('black',x,Math.sin(a*2)*.28,z,.23,.51,.22,a,.22);crystal('violet',x,Math.sin(a*2)*.28,z+.11,.11,.31,.09,a,.22);ring('gold',x,Math.sin(a*2)*.28,z,.27,.024);}},(g,t)=>{g.rotation.y=-t;g.position.y=2.64+Math.sin(t*2)*.18;});
  }
  ({yggdrasil,tempest,eclipse})[skin]();
  let triangles=0,drawCalls=0;
  for(const [group,pigments]of batches)for(const [color,batch]of pigments){
    const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(batch.p,3));geometry.setAttribute('normal',new T.Float32BufferAttribute(batch.n,3));geometry.computeBoundingSphere();
    if(!materials.has(color)){const luminous=['ice','glow','shine','violet'].includes(color),material=new T.MeshStandardMaterial({color:colors[color],roughness:['gold','copper'].includes(color)?.52:.88,metalness:['gold','copper'].includes(color)?.18:0,emissive:luminous?colors[color]:'#000000',emissiveIntensity:luminous?.36:0});material.onBeforeCompile=shader=>{shader.fragmentShader=shader.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};material.customProgramCacheKey=()=> 'castle-original-mythic-b-1';materials.set(color,material);}
    const mesh=new T.Mesh(geometry,materials.get(color));mesh.name=`${skin}-${color}`;mesh.castShadow=true;mesh.receiveShadow=true;mesh.userData.building='keep';group.add(mesh);triangles+=batch.p.length/9;drawCalls++;
  }
  Object.values(shapes).forEach(g=>g.dispose());root.userData.skin=skin;root.userData.modelStats={drawCalls,triangles,pieces};
  // Each ornament returns to its initial pose after four seconds.
  root.userData.animate=time=>{const phase=time*Math.PI/2;for(const animate of motions)animate(phase);};root.userData.animate(0);
  return root;
}
