import * as T from './vendor/three.module.js';

// Original legendary residences. Each has exactly one moving architectural
// ornament; all other geometry and all non-emissive pigments remain static.
export function buildNewLegendaryB(skin) {
  if (!['jadecourt','emberforge','ravenloft','clockwork','sapphire'].includes(skin)) return null;
  const root=new T.Group();root.name=`castle-skin-${skin}`;root.userData.building='keep';root.userData.skin=skin;
  const P={stone:'#b2aaa0',ivory:'#e4dfcc',white:'#f6eee1',dark:'#26303c',slate:'#53606b',coal:'#353036',iron:'#697480',wood:'#73503b',timber:'#392d2e',bronze:'#ad7640',gold:'#e8b654',goldLight:'#f6d884',jade:'#277566',mint:'#71b299',red:'#923e35',rust:'#ae5536',orange:'#d78238',black:'#272633',purple:'#69607e',blue:'#315b8c',sapphire:'#327cb4',ice:'#a7cfda',grass:'#708758',rock:'#7b7967'};
  const G={box:new T.BoxGeometry(1,1,1).toNonIndexed(),cyl:new T.CylinderGeometry(1,1,1,12).toNonIndexed(),cone:new T.ConeGeometry(1,1,10).toNonIndexed(),ball:new T.SphereGeometry(1,10,7).toNonIndexed(),gem:new T.OctahedronGeometry(1),rock:new T.IcosahedronGeometry(1)};
  const batches=new Map(),matrix=new T.Matrix4(),nm=new T.Matrix3(),pos=new T.Vector3(),size=new T.Vector3(),rot=new T.Quaternion(),euler=new T.Euler(),p=new T.Vector3(),n=new T.Vector3();
  let target=root,pieces=0,motion=null,animationGroup=null;
  function add(g,c,x=0,y=0,z=0,w=1,h=1,d=1,rx=0,ry=0,rz=0){
    if(!batches.has(target))batches.set(target,new Map());const group=batches.get(target);if(!group.has(c))group.set(c,{p:[],n:[]});const b=group.get(c);
    matrix.compose(pos.set(x,y,z),rot.setFromEuler(euler.set(rx,ry,rz)),size.set(w,h,d));nm.getNormalMatrix(matrix);
    for(let i=0;i<g.attributes.position.count;i++){p.fromBufferAttribute(g.attributes.position,i).applyMatrix4(matrix);n.fromBufferAttribute(g.attributes.normal,i).applyMatrix3(nm).normalize();b.p.push(p.x,p.y,p.z);b.n.push(n.x,n.y,n.z);}pieces++;
  }
  const box=(c,x,y,z,w,h,d,ry=0,rz=0)=>add(G.box,c,x,y,z,w,h,d,0,ry,rz);
  const cyl=(c,x,y,z,r,h)=>add(G.cyl,c,x,y,z,r,h,r);
  const cone=(c,x,y,z,r,h)=>add(G.cone,c,x,y,z,r,h,r);
  const ball=(c,x,y,z,r,h=r,d=r)=>add(G.ball,c,x,y,z,r,h,d);
  const gem=(c,x,y,z,r,h=r,d=r,ry=0,rz=0)=>add(G.gem,c,x,y,z,r,h,d,0,ry,rz);
  function taper(c,x,y,z,top,bottom,h,sides=12,ry=0){const g=new T.CylinderGeometry(top,bottom,h,sides).toNonIndexed();add(g,c,x,y,z,1,1,1,0,ry);g.dispose();}
  function ring(c,x,y,z,r,t=.05,rx=Math.PI/2){const g=new T.TorusGeometry(r,t,6,32).toNonIndexed();add(g,c,x,y,z,1,1,1,rx);g.dispose();}
  function line(c,points,r=.035){const g=new T.TubeGeometry(new T.CatmullRomCurve3(points.map(v=>new T.Vector3(...v))),Math.max(8,points.length*3),r,5,false).toNonIndexed();add(g,c);g.dispose();}
  function animate(name,x,y,z,build,update){if(motion)throw new Error('A legendary castle can have only one animation');const group=new T.Group();group.name=name;group.userData.animatedPart=true;group.position.set(x,y,z);root.add(group);target=group;build();target=root;animationGroup=group;motion=t=>update(group,t);}
  function foundation(body='stone',trim='ivory'){
    taper('rock',0,.18,0,2.8,2.58,.35,12);cyl('grass',0,.32,0,2.77,.13);box(body,0,.43,0,4.65,.30,4.35);box(trim,0,.62,0,4.8,.12,4.5);
    for(let i=0;i<10;i++){const a=i*2.399;add(G.rock,'rock',Math.cos(a)*2.56,.21,Math.sin(a)*2.4,.32,.20,.28,0,a);}
    for(let i=0;i<7;i++)box(body,0,.055+i*.084,2.87-i*.135,1.15,.1,.18);
    for(const s of[-1,1]){box(trim,s*.68,.40,2.34,.14,.45,.58);ball(trim,s*.68,.68,2.08,.13);}
  }
  function windows(x,y,z,w,h,body='gold',inside='dark'){
    box(body,x,y,z,w+.12,h+.12,.08);box(inside,x,y,z+.05,w,h,.045);box(body,x,y,z+.08,.035,h,.03);box(body,x,y-.06,z+.08,w,.035,.03);
  }
  function door(x,y,z,w=1,h=1.35,trim='ivory',body='wood'){
    box('dark',x,y+h*.5,z,w+.12,h+.08,.15);box(body,x,y+h*.47,z+.09,w*.9,h*.91,.07);
    for(let i=0;i<6;i++)box(trim,x+(i-2.5)*w*.14,y+h*.47,z+.14,.025,h*.86,.025);
    for(const s of[-1,1])box(trim,x+s*w*.57,y+h*.48,z+.08,.14,h*1.04,.2);
    box(trim,x,y+h,z+.08,w*1.24,.16,.22);for(const yy of[.24,.65])box(trim,x,y+h*yy,z+.17,w*.88,.07,.04);ball('gold',x+w*.15,y+h*.48,z+.21,.045);
  }
  function masonry(x,y,z,w,h,d,c='stone',trim='ivory'){
    box(c,x,y+h/2,z,w,h,d);box(trim,x,y+.06,z,w+.12,.12,d+.12);box(trim,x,y+h-.06,z,w+.17,.13,d+.17);
    for(let row=1;row<h/.31;row++){const yy=y+row*.31;box(trim,x,yy,z+d*.5+.013,w,.017,.02);box(trim,x+w*.5+.013,yy,z,.02,.017,d);for(let col=0;col<w/.48;col++){const xx=x-w*.5+((col+.5+(row%2)*.5)*.48);if(xx<x+w*.5)box(trim,xx,yy-.15,z+d*.5+.016,.016,.3,.02);}}
  }
  function merlons(x,y,z,w,d,c='ivory'){
    box(c,x,y,z,w+.12,.16,d+.12);for(const s of[-1,1]){for(let i=0;i<Math.round(w/.38);i++)box(c,x-w*.43+i*w*.86/(Math.round(w/.38)-1),y+.23,z+s*d*.48,.22,.38,.22);for(let i=1;i<Math.round(d/.4)-1;i++)box(c,x+s*w*.48,y+.23,z-d*.43+i*d*.86/(Math.round(d/.4)-1),.22,.38,.22);}
  }
  function pagoda(x,y,z,w,d,h,c='jade'){
    // Upturned, ribbed hips with a narrow ridge rather than a generic cone.
    const v=[];function sample(side,u,t){const a=w*.5*(1-t)+w*.12*t,b=d*.5*(1-t);let xx,zz;if(side===0){xx=u*a;zz=b;}else if(side===1){xx=a;zz=-u*b;}else if(side===2){xx=-u*a;zz=-b;}else{xx=-a;zz=u*b;}return[x+xx,y+h*t+.18*(1-t)**5*(.3+.7*Math.abs(u)**4),z+zz];}
    for(let s=0;s<4;s++){for(let row=0;row<6;row++)for(let col=0;col<8;col++){const u=-1+col*.25,t=row/6;const a=sample(s,u,t),b=sample(s,u+.25,t),cc=sample(s,u+.25,t+1/6),dd=sample(s,u,t+1/6);v.push(...a,...b,...cc,...a,...cc,...dd);}for(let i=0;i<=12;i++)line(i%3?'mint':'gold',Array.from({length:7},(_,j)=>sample(s,-1+i/6,j/6)),.019);line('gold',Array.from({length:9},(_,j)=>sample(s,-1+j*.25,0)),.036);}
    const g=new T.BufferGeometry();g.setAttribute('position',new T.Float32BufferAttribute(v,3));g.computeVertexNormals();add(g,c);g.dispose();line('gold',[[x-w*.17,y+h+.19,z],[x-w*.12,y+h+.04,z],[x+w*.12,y+h+.04,z],[x+w*.17,y+h+.19,z]],.05);
  }
  function jadecourt(){
    foundation('stone','ivory');masonry(0,.68,-.2,2.75,1.60,2.30,'white','stone');
    for(const s of[-1,1]){box('red',s*1.16,1.52,1,.14,1.66,.14);box('red',s*.53,1.52,1,.12,1.66,.14);windows(s*.84,1.55,1.04,.4,.63,'wood');}
    door(0,.69,1.08,.78,1.25,'gold','red');pagoda(0,2.27,-.2,3.60,3.17,.74);
    box('white',0,3.36,-.36,1.65,.88,1.54);for(const s of[-1,1]){box('red',s*.77,3.35,.38,.11,.93,.11);windows(s*.37,3.36,.43,.35,.52,'gold');}pagoda(0,3.84,-.36,2.55,2.35,.74);
    for(const s of[-1,1]){masonry(s*1.80,.68,.4,.58,1.09,1.4,'white','stone');pagoda(s*1.80,1.77,.4,1.13,1.91,.5);for(let i=0;i<4;i++)cyl('red',s*1.80,1.17,-.17+i*.38,.045,.97);}
    for(const s of[-1,1]){box('jade',s*1.60,.75,-1.55,.70,.14,.42);for(let i=0;i<3;i++)ball('mint',s*(1.34+i*.24),.89,-1.55,.20,.23,.20);}
    // Open ceremonial belfry crowns the pagoda. Only the suspended bell sways.
    for(const s of[-1,1])cyl('red',s*.55,4.94,-.36,.065,.89);box('gold',0,5.39,-.36,1.32,.13,.19);pagoda(0,5.38,-.36,1.83,1.31,.48);
    animate('swaying-ceremonial-bell',0,5.31,-.34,()=>{ring('gold',0,-.10,0,.09,.025,0);taper('bronze',0,-.43,0,.19,.31,.52,14);ring('gold',0,-.69,0,.32,.037);cyl('gold',0,-.41,0,.23,.055);ball('dark',0,-.72,0,.10);},(g,t)=>{g.rotation.z=Math.sin(t)*.48;});
  }
  function emberforge(){
    foundation('coal','iron');masonry(0,.68,-.37,3.19,1.96,2.28,'coal','slate');merlons(0,2.68,-.37,3.25,2.33,'iron');
    for(const s of[-1,1]){masonry(s*1.81,.68,.91,.77,2.10,.82,'rust','iron');merlons(s*1.81,2.79,.91,.81,.88,'iron');box('bronze',s*1.81,1.83,1.35,.37,.81,.06);for(let i=0;i<3;i++)box('coal',s*1.81,1.61+i*.22,1.40,.29,.08,.03);}
    door(0,.68,.80,1.15,1.57,'iron','dark');box('orange',0,1.45,.96,.72,.82,.03);for(let i=0;i<5;i++)box('iron',(i-2)*.16,1.50,1.01,.04,1.04,.05);
    for(const [x,z,h]of[[-1.05,-.87,2.25],[.99,-.97,2.9]]){masonry(x,2.72,z,.72,h,.73,'rust','coal');box('iron',x,2.72+h,z,.92,.2,.94);box('dark',x,2.85+h,z,.65,.03,.65);for(let row=0;row<4;row++)box('bronze',x,3.00+row*.42,z+.39,.80,.1,.12);}
    for(const s of[-1,1]){box('iron',s*.94,3.19,-.19,.22,1.0,.28);box('bronze',s*.94,3.72,-.19,.4,.14,.41);}box('iron',0,3.8,-.19,2.29,.28,.39);
    // An oversized forge hammer is the one kinetic landmark, above a static anvil.
    box('coal',0,2.91,.26,1.21,.34,.79);box('iron',0,3.17,.26,1.12,.18,.54);taper('iron',0,3.08,.26,.44,.21,.31,4,Math.PI/4);
    animate('working-forge-hammer',0,3.76,-.19,()=>{box('wood',0,-.02,.52,.14,.14,1.24);box('bronze',0,-.03,1.03,.52,.50,.57);box('iron',0,-.03,1.36,.54,.52,.12);box('iron',0,-.03,.74,.54,.52,.10);},(g,t)=>{g.rotation.x=-.27+.53*(.5+.5*Math.cos(t));});
    for(const s of[-1,1]){cyl('bronze',s*1.25,.85,1.62,.23,.33);ring('iron',s*1.25,1.02,1.62,.24,.045);box('iron',s*1.25,1.11,1.62,.07,.18,.41);}
    for(let i=0;i<4;i++)box('iron',1.86,.74+i*.1,-1.7,.45,.08,.47-i*.05);
  }
  function ravenloft(){
    foundation('slate','stone');masonry(0,.68,-.34,2.11,2.88,2.04,'stone','slate');
    // Steep violet roofs, narrow lancets and asymmetric buttressed spires.
    taper('purple',0,4.03,-.34,.02,1.69,1.23,4,Math.PI/4);box('black',0,4.0,.73,.10,1.02,.09);
    for(const s of[-1,1]){windows(s*.55,2.25,.72,.22,1.08,'ivory','black');gem('ivory',s*.55,2.98,.72,.16,.23,.05);box('slate',s*1.12,1.61,.86,.24,1.85,.42);gem('slate',s*1.12,2.61,.86,.21,.34,.34);}
    door(0,.68,.74,.75,1.43,'stone','timber');
    for(const [x,z,h,r]of[[-1.54,.64,2.40,.43],[1.44,.26,3.24,.50],[-1.03,-1.32,3.27,.36]]){cyl('slate',x,.68+h/2,z,r,h);for(let i=0;i<Math.floor(h/.35);i++)ring('stone',x,.82+i*.35,z,r+.006,.02);cyl('stone',x,.68+h,z,r+.1,.18);cone('purple',x,1.30+h,z,r*1.49,1.22);cyl('iron',x,2.06+h,z,.03,.44);windows(x,.68+h*.63,z+r,.14,.76,'stone','black');}
    for(const s of[-1,1]){box('black',s*1.08,3.33,.77,.12,.45,.46);ball('slate',s*1.08,3.41,1.02,.13,.14,.24);gem('slate',s*1.08,3.51,1.17,.06,.10,.13);}
    cyl('iron',0,4.91,-.34,.037,.69);ring('iron',0,4.94,-.34,.24,.027,0);
    animate('rotating-raven-weather-vane',0,5.33,-.34,()=>{box('iron',0,0,0,1.73,.06,.06);gem('iron',.96,0,0,.25,.17,.06);box('iron',-.76,0,0,.34,.25,.035);ball('black',0,.22,0,.31,.24,.19);ball('black',.28,.43,0,.17);gem('bronze',.46,.41,0,.15,.08,.06);gem('black',-.25,.14,0,.31,.12,.12,0,-.32);for(const s of[-1,1])gem('black',-.08,.27,s*.25,.27,.13,.29,0,s*.2);for(const x of[-.12,.13])cyl('iron',x,.07,0,.021,.20);},(g,t)=>{g.rotation.y=t;});
    for(const s of[-1,1]){cyl('stone',s*1.83,.84,1.71,.18,.33);ball('black',s*1.83,1.08,1.71,.16,.18,.20);}
  }
  function clockwork(){
    foundation('stone','bronze');masonry(0,.68,-.28,2.24,3.26,2.15,'ivory','bronze');box('wood',0,1.05,.88,2.03,.10,.11);box('bronze',0,3.50,.88,2.36,.15,.15);
    for(const s of[-1,1]){box('wood',s*.99,2.31,.87,.11,3.13,.13);windows(s*.57,1.89,.90,.30,.61,'bronze');masonry(s*1.71,.68,.26,.89,1.46,1.66,'rust','bronze');taper('jade',s*1.71,2.47,.26,.04,.86,.68,4,Math.PI/4);}
    door(0,.68,.89,.70,1.15,'bronze','wood');
    taper('jade',0,4.37,-.28,.22,1.74,.98,4,Math.PI/4);cyl('bronze',0,4.99,-.28,.09,.38);ball('gold',0,5.24,-.28,.19);
    // Stationary clock face and hands make the moving exposed gear unambiguous.
    add(G.cyl,'wood',0,2.92,.96,.72,.15,.72,Math.PI/2);ring('gold',0,2.92,1.07,.68,.07,0);add(G.cyl,'ivory',0,2.92,1.09,.58,.03,.58,Math.PI/2);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;box('bronze',Math.sin(a)*.50,2.92+Math.cos(a)*.50,1.13,.035,i%3?.08:.13,.025,0,-a);}box('dark',.10,3.07,1.15,.04,.38,.025,0,-.54);box('dark',-.14,2.96,1.16,.31,.045,.025,0,.25);ball('bronze',0,2.92,1.19,.07,.07,.03);
    for(const s of[-1,1]){cyl('bronze',s*.98,4.32,-.87,.07,.87);line('bronze',[[s*.98,4.71,-.87],[s*1.27,4.71,-.87],[s*1.27,3.31,-.87]],.06);}
    box('wood',1.62,3.13,.52,.20,1.82,.30);box('bronze',1.62,3.96,.52,.53,.16,.33);
    animate('rotating-clock-gear',1.62,3.32,.78,()=>{ring('bronze',0,0,0,.57,.14,0);for(let i=0;i<14;i++){const a=i*Math.PI/7;box('gold',Math.sin(a)*.70,Math.cos(a)*.70,0,.18,.22,.18,0,-a);}for(let i=0;i<6;i++){const a=i*Math.PI/3;box('bronze',Math.sin(a)*.28,Math.cos(a)*.28,0,.075,.56,.09,0,-a);}add(G.cyl,'iron',0,0,0,.13,.19,.13,Math.PI/2);},(g,t)=>{g.rotation.z=-t;});
    for(const s of[-1,1]){box('wood',s*1.63,.78,1.70,.54,.23,.39);for(let i=0;i<3;i++)cyl('bronze',s*(1.46+i*.16),1.04,1.70,.042,.30);}
  }
  function sapphire(){
    foundation('white','blue');cyl('ivory',0,.97,-.28,1.66,.60);ring('gold',0,1.29,-.28,1.70,.06);cyl('white',0,2.46,-.28,1.26,2.25);cyl('blue',0,3.64,-.28,1.42,.18);
    for(let i=0;i<12;i++){const a=i*Math.PI/6,x=Math.sin(a),z=Math.cos(a);box('gold',x*1.27,2.37,-.28+z*1.27,.065,1.95,.065,a);box('blue',x*1.29,2.73,-.28+z*1.29,.19,.81,.055,a);box('ice',x*1.31,2.78,-.28+z*1.31,.11,.59,.04,a);}
    ball('sapphire',0,3.70,-.28,1.40,.75,1.40);for(let i=0;i<12;i++){const a=i*Math.PI/6;line('gold',[[Math.sin(a)*1.37,3.72,-.28+Math.cos(a)*1.37],[Math.sin(a)*1.01,4.21,-.28+Math.cos(a)*1.01],[0,4.46,-.28]],.027);}cyl('gold',0,4.57,-.28,.065,.28);gem('sapphire',0,4.86,-.28,.17,.30,.17);
    for(const [x,z,h]of[[-1.65,.74,2.03],[1.65,.74,2.03],[-1.27,-1.47,2.57],[1.27,-1.47,2.57]]){cyl('ivory',x,.68+h/2,z,.32,h);cyl('blue',x,.81,z,.43,.25);cyl('gold',x,.68+h,z,.42,.10);ball('sapphire',x,.79+h,z,.42,.35,.42);cone('gold',x,1.23+h,z,.045,.41);windows(x,.83+h*.51,z+.31,.14,.65,'gold','blue');}
    door(0,.68,1.32,.82,1.38,'gold','blue');for(const s of[-1,1]){cyl('ivory',s*.67,1.4,1.34,.075,1.45);ball('gold',s*.67,2.14,1.34,.11);}line('gold',[[-.71,2.17,1.34],[0,2.50,1.34],[.71,2.17,1.34]],.065);
    // A single large cut sapphire circles the crown. No trail, glow or particles.
    animate('orbiting-sapphire',0,4.56,-.28,()=>{gem('sapphire',1.17,0,0,.33,.52,.33);gem('ice',1.17,.12,.16,.15,.27,.11);ring('gold',1.17,0,0,.34,.028);},(g,t)=>{g.rotation.y=t;});
    for(const s of[-1,1]){box('blue',s*1.52,.74,1.71,.49,.16,.44);gem('sapphire',s*1.52,.98,1.71,.17,.23,.17);}
  }
  ({jadecourt,emberforge,ravenloft,clockwork,sapphire})[skin]();
  let triangles=0,drawCalls=0;const materials=new Map();
  for(const[group,pigments]of batches)for(const[c,b]of pigments){
    const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(b.p,3));geometry.setAttribute('normal',new T.Float32BufferAttribute(b.n,3));geometry.computeBoundingSphere();
    if(!materials.has(c)){const material=new T.MeshStandardMaterial({color:P[c],roughness:['gold','bronze','iron'].includes(c)?.56:.84,metalness:['gold','bronze','iron'].includes(c)?.18:0,emissive:0x000000,emissiveIntensity:0});material.onBeforeCompile=s=>{s.fragmentShader=s.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};material.customProgramCacheKey=()=> 'castle-legendary-new-b-1';materials.set(c,material);}
    const mesh=new T.Mesh(geometry,materials.get(c));mesh.name=`${skin}-${c}`;mesh.castShadow=true;mesh.receiveShadow=true;mesh.userData.building='keep';group.add(mesh);triangles+=b.p.length/9;drawCalls++;
  }
  Object.values(G).forEach(g=>g.dispose());root.userData.animate=seconds=>motion(seconds*Math.PI/2);root.userData.animate(0);
  root.userData.animationCount=1;root.userData.animationName=animationGroup.name;root.userData.modelStats={pieces,triangles,drawCalls};return root;
}
