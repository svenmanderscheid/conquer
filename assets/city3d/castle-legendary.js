import * as T from './vendor/three.module.js';

// Hand-built silhouettes from the supplied League of Kingdoms skin cards.
// Static ornament is merged by pigment; only the explicitly animated parts move.
export function buildLegendaryCastle(skin) {
  if (!['flame','grandeur','empyrean','sunshine','heavenly','bastion','magisters','frost','crescent','darkness','sunbless'].includes(skin)) return null;
  const root=new T.Group();root.name=`castle-skin-${skin}`;root.userData.building='keep';root.userData.skin=skin;
  const P={ivory:'#e4dfcf',stone:'#9fa8b4',light:'#d1d9e2',slate:'#455268',dark:'#202031',black:'#292132',gold:'#e7ad28',shine:'#ffe375',bronze:'#a57523',blue:'#2476ce',navy:'#273983',cyan:'#45cfeb',ice:'#a3f3fb',purple:'#9955dc',pink:'#f283cf',red:'#b32641',orange:'#ff7d16',lava:'#ffcd37',green:'#72f029',teal:'#25998d',sand:'#c4a16b',rock:'#685142',wood:'#563824'};
  const G={box:new T.BoxGeometry(1,1,1).toNonIndexed(),cyl:new T.CylinderGeometry(1,1,1,12).toNonIndexed(),cone:new T.ConeGeometry(1,1,12).toNonIndexed(),ball:new T.SphereGeometry(1,12,8).toNonIndexed(),gem:new T.OctahedronGeometry(1,0),rock:new T.IcosahedronGeometry(1,0)};
  const matrix=new T.Matrix4(),nm=new T.Matrix3(),p=new T.Vector3(),n=new T.Vector3(),pos=new T.Vector3(),q=new T.Quaternion(),rot=new T.Euler(),scale=new T.Vector3();
  let batches=new Map(),triangles=0,pieces=0,drawCalls=0;const movements=[];
  function add(g,color,x=0,y=0,z=0,w=1,h=1,d=1,rx=0,ry=0,rz=0){
    if(!batches.has(color))batches.set(color,{p:[],n:[]});const b=batches.get(color);
    matrix.compose(pos.set(x,y,z),q.setFromEuler(rot.set(rx,ry,rz)),scale.set(w,h,d));nm.getNormalMatrix(matrix);
    for(let i=0;i<g.attributes.position.count;i++){p.fromBufferAttribute(g.attributes.position,i).applyMatrix4(matrix);n.fromBufferAttribute(g.attributes.normal,i).applyMatrix3(nm).normalize();b.p.push(p.x,p.y,p.z);b.n.push(n.x,n.y,n.z);}pieces++;
  }
  const box=(c,x,y,z,w,h,d,ry=0,rz=0)=>add(G.box,c,x,y,z,w,h,d,0,ry,rz);
  const cyl=(c,x,y,z,r,h)=>add(G.cyl,c,x,y,z,r,h,r);
  const cone=(c,x,y,z,r,h)=>add(G.cone,c,x,y,z,r,h,r);
  const ball=(c,x,y,z,r,sy=r,sz=r)=>add(G.ball,c,x,y,z,r,sy,sz);
  const gem=(c,x,y,z,r,h)=>add(G.gem,c,x,y,z,r,h,r);
  function line(c,points,r=.045){const g=new T.TubeGeometry(new T.CatmullRomCurve3(points.map(v=>new T.Vector3(...v))),Math.max(6,points.length*3),r,5,false).toNonIndexed();add(g,c);g.dispose();}
  function ring(c,x,y,z,r,t=.04,rx=Math.PI/2){const g=new T.TorusGeometry(r,t,5,28).toNonIndexed();add(g,c,x,y,z,1,1,1,rx);g.dispose();}
  function flush(parent){for(const[c,b]of batches){const g=new T.BufferGeometry();g.setAttribute('position',new T.Float32BufferAttribute(b.p,3));g.setAttribute('normal',new T.Float32BufferAttribute(b.n,3));g.computeBoundingSphere();const glow=c.startsWith('!'),name=glow?c.slice(1):c;const m=glow?new T.MeshBasicMaterial({color:P[name]||name}):new T.MeshStandardMaterial({color:P[name]||name,roughness:.74,metalness:name==='gold'?.15:0});if(!glow){m.onBeforeCompile=s=>{s.fragmentShader=s.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};m.customProgramCacheKey=()=> 'castle-skin-pigment-1';}const mesh=new T.Mesh(g,m);mesh.castShadow=!glow;mesh.receiveShadow=true;mesh.userData.building='keep';parent.add(mesh);triangles+=b.p.length/9;drawCalls++;}batches=new Map();}
  function moving(name,fn,animate){const old=batches;batches=new Map();const group=new T.Group();group.name=name;fn();flush(group);batches=old;root.add(group);movements.push(t=>animate(group,t));return group;}
  function plinth(c='slate',size=5.6,y=.15){box(c,0,y,0,size,.3,size);box('bronze',0,y+.18,0,size+.09,.09,size+.09);}
  function steps(c,x,z,width,count=7,height=.65,depth=1.2,y=.3){for(let i=0;i<count;i++)box(c,x,y+height*(i+.5)/count,z+depth*(1-(i+.5)/count),width,height/count,depth/count+.025);}
  function masonry(c,x,y,z,w,h,d){
    box(c,x,y+h/2,z,w,h,d);const seam=c==='black'?'slate':c==='sand'?'bronze':'stone';
    for(let side of[-1,1]){
      box(seam,x,y+.09,z+side*(d/2+.01),w+.06,.13,.055);box(seam,x,y+h-.07,z+side*(d/2+.01),w+.08,.14,.07);
      for(let row=0;row<Math.floor(h/.36);row++){
        const yy=y+.19+row*.36;box(seam,x,yy,z+side*(d/2+.016),w,.020,.023);
        for(let i=0;i<Math.floor(w/.48);i++){const xx=x-w/2+.21+i*.48+(row%2)*.2;if(xx<x+w/2-.12)box(seam,xx,yy+.15,z+side*(d/2+.016),.025,.27,.024);}
      }
      for(let row=0;row<Math.floor(h/.46);row++)for(let corner of[-1,1])box(c==='black'?'slate':'light',x+corner*(w/2-.09),y+.22+row*.46,z+side*(d/2+.025),.20,.16,.05);
      box(seam,x+side*(w/2+.02),y+.09,z,.055,.13,d);box(seam,x+side*(w/2+.02),y+h-.07,z,.07,.14,d+.08);
      for(let row=0;row<Math.floor(h/.36);row++){const yy=y+.19+row*.36;box(seam,x+side*(w/2+.014),yy,z,.024,.02,d);for(let i=0;i<Math.floor(d/.48);i++){const zz=z-d/2+.21+i*.48+(row%2)*.2;if(zz<z+d/2-.12)box(seam,x+side*(w/2+.014),yy+.15,zz,.024,.27,.024);}}
    }
  }
  function windows(x,y,z,w,h,d,color='!cyan'){for(let side of[-1,1])for(let i=0;i<3;i++){const xx=x+(i-1)*w*.27;box('dark',xx,y+h*.55,z+side*(d/2+.02),w*.15,h*.5,.035);box(color,xx,y+h*.57,z+side*(d/2+.045),w*.10,h*.35,.02);}for(let side of[-1,1])for(let i=0;i<3;i++)box(color,x+side*(w/2+.025),y+h*.57,z+(i-1)*d*.26,.035,h*.35,d*.1);}
  function battlements(x,y,z,w,d,c='stone'){for(let i=0;i<7;i++)for(let s of[-1,1])box(c,x-w*.46+i*w*.92/6,y,z+s*d*.47,w*.075,.26,.22);for(let i=1;i<6;i++)for(let s of[-1,1])box(c,x+s*w*.47,y,z-d*.46+i*d*.92/6,.22,.26,d*.075);}
  function wall(c='stone',trim='gold',height=1.4){for(let s of[-1,1]){box(c,s*2.35,.3+height/2,0,.25,height,4.7);box(trim,s*2.35,.32+height,0,.34,.13,4.9);}box(c,0,.3+height/2,-2.35,4.7,height,.25);box(trim,0,.32+height,-2.35,4.9,.13,.34);for(let s of[-1,1]){box(c,s*1.62,.3+height/2,2.35,1.45,height,.27);box(trim,s*1.62,.32+height,2.35,1.55,.13,.34);}battlements(0,height+.51,0,4.8,4.8,c);
    const seam=c==='black'?'slate':c==='sand'?'bronze':c==='ivory'?'stone':c==='teal'?'slate':'navy';
    for(let side of[-1,1]){
      box(trim,side*2.35,.43,0,.32,.13,4.8);
      for(let row=0;row<Math.floor(height/.33);row++){
        const yy=.50+row*.33;box(seam,side*2.487,yy,0,.02,.024,4.5);
        for(let i=0;i<10;i++)box(seam,side*2.49,yy+.14,-2.10+i*.44+(row%2)*.12,.023,.24,.026);
      }
      for(let i=0;i<4;i++){const zz=-1.7+i*1.12;box(c,side*2.51,.45+height*.48,zz,.15,height*.88,.18);box(trim,side*2.51,height+.25,zz,.20,.12,.25);}
      for(let i=0;i<4;i++){const xx=side*(1.02+i*.34);box(seam,xx,.40+height*.46,2.492,.026,height*.62,.025);}
      box(trim,side*1.61,.43,2.37,1.5,.12,.3);
    }
  }
  function tower(x,z,h,c='ivory',roof='blue',r=.48){cyl('slate',x,.43,z,r+.12,.36);cyl(c,x,.5+h/2,z,r,h);cyl('gold',x,.75,z,r+.035,.12);cyl('gold',x,h+.43,z,r+.08,.13);for(let row=0;row<Math.floor(h/.42);row++)cyl(c==='black'?'slate':'stone',x,.67+row*.42,z,r+.006,.025);for(let i=0;i<8;i++){const a=i*Math.PI/4;box('gold',x+Math.cos(a)*r,h+.32,z+Math.sin(a)*r,.075,.20,.075);}for(let side of[-1,1]){box('navy',x+side*r*.7,h*.7+.5,z+r*.72,.12,.44,.10);box('!cyan',x+side*r*.7,h*.7+.52,z+r*.79,.06,.3,.05);}cone(roof,x,h+1.08,z,r+.16,1.18);cyl('gold',x,h+1.72,z,.033,.25);ball('shine',x,h+1.88,z,.07);for(let i=0;i<4;i++)cyl(roof==='blue'?'navy':'bronze',x,h+.58+i*.2,z,(r+.16)*(1-i*.16),.035);}
  function banner(x,y,z,c='blue',phase=0){cyl('gold',x,y-.6,z,.025,1.5);moving('fluttering-banner',()=>{box(c,.22,0,0,.44,.62,.035);box('gold',.22,-.29,0,.44,.055,.045);},(g,t)=>{g.position.set(x,y,z);g.rotation.y=Math.sin(t*1.7+phase)*.24;});}
  function flameAt(x,y,z,c='orange',phase=0){moving('living-flame',()=>{gem('!'+c,0,.25,0,.19,.47);gem('!lava',0,.13,.04,.1,.27);},(g,t)=>{g.position.set(x,y,z);g.scale.set(1+Math.sin(t*3+phase)*.10,1+Math.sin(t*4+phase)*.18,1);g.rotation.y=t*.6+phase;});}
  function pyramid(c,x,y,z,size,height){const g=new T.CylinderGeometry(0,size/Math.sqrt(2),height,4,1,false,Math.PI/4).toNonIndexed();add(g,c,x,y+height/2,z);g.dispose();}
  function crystal(x,y,z,r,h,c='cyan'){gem(c,x,y,z,r,h);for(let i=0;i<4;i++){const a=i*Math.PI/2;line('ice',[[x+Math.cos(a)*r*.8,y,z+Math.sin(a)*r*.8],[x,y+h,z]],.018);}}

  if(skin==='flame'||skin==='darkness'){
    const fire=skin==='flame',glow=fire?'orange':'green';plinth('black');wall('black','slate',1.8);
    masonry('black',0,.35,-.2,2.8,3.1,2.7);box('slate',0,3.55,-.2,3.1,.2,3);
    for(let s of[-1,1])for(let k of[-1,1]){const x=s*2.22,z=k*2.18;tower(x,z,1.9,'black','black',.34);cone('black',x,3.65,z,.30,1.0);line('!'+glow,[[x,1,z+.37],[x,2.1,z+.36]],.035);}
    for(let i=0;i<5;i++){const x=(i-2)*.54;box('!'+glow,x,1.96,1.17,.045,2.5,.025);box('black',x,3.62,1.28,.22,.55,.38);cone('black',x,4.12,1.27,.16,.65);}
    for(let s of[-1,1])for(let i=0;i<4;i++)box('!'+glow,s*1.42,1.8,-1.2+i*.65,.03,2.6,.045);
    if(fire){cyl('black',0,4.18,-.28,.78,1.25);cone('black',0,5.34,-.28,1.02,1.4);for(let i=0;i<5;i++){const a=i*Math.PI*2/5;cone('black',Math.cos(a)*.79,5.22,-.28+Math.sin(a)*.79,.21,.85);}flameAt(0,5.55,-.28,'orange');for(let x of[-.51,.51])box('!orange',x,4.6,.29,.27,.12,.1);}
    else{cyl('black',0,4.0,-.3,.83,1.0);cyl('slate',0,4.47,-.3,1.05,.2);ring('black',0,4.77,-.3,.75,.13);moving('necromantic-orb',()=>{ball('!green',0,0,0,.42);ring('slate',0,0,0,.60,.055,0);},(g,t)=>{g.position.set(0,5.0+Math.sin(t*1.2)*.09,-.3);g.rotation.y=t*.3;});for(let i=0;i<8;i++){const a=i*Math.PI/4;cone('black',Math.cos(a)*.92,4.86,-.3+Math.sin(a)*.92,.11,.67);}cone('black',0,5.81,-.3,.09,.4);}
    box('dark',0,1.17,2.49,1.2,1.7,.11);for(let i=0;i<5;i++)box('slate',(i-2)*.22,1.17,2.56,.05,1.7,.08);steps('slate',0,2.5,1.3,7,.65,.62,.04);for(let x of[-1.48,1.48])flameAt(x,2.0,2.37,glow,x);
  }
  if(skin==='grandeur'){
    plinth();wall('blue','gold',1.35);masonry('ivory',0,.4,-.2,2.6,2.1,2.6);windows(0,.4,-.2,2.6,2.1,2.6,'!cyan');pyramid('blue',0,2.55,-.2,3.0,1.2);
    for(const[x,z,c,h]of[[-2.2,2.2,'green',2.5],[2.2,2.2,'cyan',2.8],[-2.2,-2.2,'orange',2.8],[2.2,-2.2,'purple',3.3]]){tower(x,z,h,'red','red',.4);cyl('gold',x,h+1.5,z,.34,.22);moving('prismatic-jewel',()=>crystal(0,0,0,.30,.7,c),(g,t)=>{g.position.set(x,h+1.98+Math.sin(t*1.4+x)*.06,z);g.rotation.y=t*.28;});}
    box('gold',0,1.1,2.4,1.4,1.7,.3);box('navy',0,1.04,2.58,.98,1.38,.06);ring('gold',0,3.1,1.31,.72,.10,0);for(let i=0;i<8;i++){const a=i*Math.PI/4;gem(['pink','cyan','green','orange'][i%4],Math.cos(a)*.76,3.1+Math.sin(a)*.76,1.35,.13,.17);}gem('shine',0,3.1,1.42,.43,.43);steps('ivory',0,2.6,1.4,6,.6,.6,.05);
  }
  if(skin==='empyrean'){
    plinth('stone');wall('ivory','blue',1.2);masonry('ivory',0,.4,-.45,2.3,2.9,2.2);windows(0,.4,-.45,2.3,2.9,2.2);tower(0,-.52,4.25,'ivory','blue',.78);
    for(const[x,z,h]of[[-2,1.9,2.0],[2,1.9,2.0],[-2,-1.9,2.6],[2,-1.9,2.6]]){tower(x,z,h);banner(x,h+1.7,z,'blue',x+z);}
    for(let s of[-1,1]){box('gold',s*.66,1.35,2.49,.14,1.9,.2);cone('blue',s*.67,2.67,2.44,.3,.7);}box('navy',0,1.13,2.5,1.1,1.6,.12);for(let i=0;i<4;i++)box('gold',(i-1.5)*.22,1.13,2.58,.035,1.55,.05);steps('ivory',0,2.5,1.4,7,.6,.65,.05);
  }
  if(skin==='sunshine'){
    plinth('sand');wall('sand','gold',.72);
    for(let i=0;i<4;i++){const s=4.15-i*.77,y=.55+i*.67;box('sand',0,y+.28,0,s,.57,s);box('gold',0,y+.59,0,s+.14,.10,s+.14);for(let side of[-1,1])for(let j=0;j<5-i;j++)box('bronze',side*(s/2+.02),y+.29,(j-(4-i)/2)*.46,.05,.32,.16);}
    masonry('sand',0,3.25,0,1.43,.96,1.43);box('dark',0,3.66,.73,.59,.8,.05);pyramid('red',0,4.22,0,2.05,.88);gem('shine',0,5.13,0,.09,.11);
    for(let side of[-1,1])for(let i=0;i<7;i++){const u=-.88+i*.293;line('bronze',[[u,4.25,side*1.0],[u*.12,5.04,side*.1]],.025);line('bronze',[[side*1.0,4.25,u],[side*.1,5.04,u*.12]],.025);}
    for(let side of[-1,1])for(let i=0;i<5;i++)box('bronze',side*.735,3.65,-.5+i*.25,.04,.48,.045);
    for(let s of[-1,1])for(let z of[-2.3,2.3]){box('gold',s*2.3,1.27,z,.20,1.94,.20);pyramid('shine',s*2.3,2.24,z,.27,.29);}
    for(let i=0;i<15;i++)box('gold',0,.36+i*.20,2.29-i*.102,.77,.17,.22);
    for(let s of[-1,1])banner(s*1.05,3.28,-.43,'gold',s);moving('sun-disc',()=>{ring('gold',0,0,0,.31,.045,0);for(let i=0;i<8;i++){const a=i*Math.PI/4;gem('!shine',Math.cos(a)*.33,Math.sin(a)*.33,0,.055,.08);}},(g,t)=>{g.position.set(0,3.67,.79);g.rotation.z=t*.12;});
  }
  if(skin==='heavenly'){
    add(G.rock,'rock',0,.4,0,3.05,.9,3.05);box('sand',0,1.04,0,5.0,.22,5);box('blue',0,1.2,0,4.55,.17,4.55);wall('stone','blue',2.0);
    for(let s of[-1,1])for(let z of[-2.12,2.12]){masonry('stone',s*2.12,1.1,z,.6,2.28,.6);for(let i=0;i<3;i++)box('!cyan',s*2.12,1.53+i*.6,z+.32,.32,.18,.04);gem('gold',s*2.12,3.62,z,.2,.34);}
    for(let i=0;i<4;i++)box('navy',0,1.51+i*.46,-.35,2.3-i*.27,.48,2.45-i*.3);
    line('gold',[[-1.39,3.2,.2],[-.96,4.08,-.1],[-.65,4.73,-.27],[0,4.98,-.31],[.58,5.5,-.4],[.92,5.52,-.4],[1.12,5.28,-.4]],.14);
    line('ivory',[[-1.35,3.24,.27],[-.89,4.10,-.05],[-.60,4.71,-.2],[0,4.93,-.24],[.58,5.43,-.32],[.94,5.43,-.32]],.074);
    box('gold',.42,2.47,1.47,.4,2.6,.35,0,.48);box('gold',-.4,2.21,1.65,.7,.3,1.5);box('cyan',0,1.17,1.45,.62,.065,2.4);
    moving('falling-water',()=>{box('!cyan',0,0,0,.57,1.25,.07);for(let i=0;i<5;i++)box('!ice',(i-2)*.10,-.3+(i%3)*.18,.045,.018,.24,.015);},(g,t)=>{g.position.set(0,.46+Math.sin(t*2)*.035,2.55);g.scale.x=1+Math.sin(t*2.4)*.04;});
    // The reference's swept celestial ribs rise from an open blue sanctuary.
    for(let side of[-1,1]){
      line('ivory',[[side*1.28,1.48,.76],[side*1.18,3.32,.42],[side*.66,4.07,.13],[0,4.42,.02]],.16);
      line('gold',[[side*1.39,1.47,.82],[side*1.31,3.34,.46],[side*.73,4.20,.17],[0,4.53,.06]],.072);
      for(let i=0;i<5;i++){
        const yy=2.07+i*.35;line('gold',[[side*.67,yy,.96],[side*(1.11+i*.09),yy+.20,.67],[side*(1.39+i*.08),yy+.53,.51]],.047);
      }
      box('gold',side*.57,1.85,2.45,.14,1.29,.19);
      line('gold',[[side*.57,2.43,2.46],[side*.35,2.82,2.46],[0,3.07,2.46]],.075);
      for(let z of[-1.43,-.43,.57,1.57]){box('navy',side*2.50,1.68,z,.045,.67,.43);box('!cyan',side*2.53,1.69,z,.025,.42,.20);box('gold',side*2.54,1.31,z,.08,.09,.48);}
    }
    gem('!cyan',0,3.04,2.5,.15,.22);
    for(let i=0;i<5;i++){const a=i*2.4;moving('floating-island-stone',()=>add(G.rock,'rock',0,0,0,.16,.22,.15),(g,t)=>g.position.set(Math.cos(a)*2.75,.35+Math.sin(t+i)*.07,Math.sin(a)*2.75));}
  }
  if(skin==='bastion'){
    cyl('slate',0,.45,0,2.7,.65);cyl('stone',0,.85,0,2.45,.24);cyl('navy',0,1.1,0,2.20,.30);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;box('stone',Math.cos(a)*2.45,.7,Math.sin(a)*2.45,.5,.75,.25,-a);box('blue',Math.cos(a)*2.48,.61,Math.sin(a)*2.48,.12,.42,.06,-a);}
    masonry('ivory',0,1.15,0,3.2,1.62,2.9);windows(0,1.15,0,3.2,1.62,2.9,'dark');
    // Tall gabled Korean hall, white gable verge and closely spaced black tiles.
    for(let side of[-1,1]){box('black',side*.86,3.36,0,2.1,.18,3.42,0,-side*.58);line('ivory',[[side*1.82,2.88,1.75],[0,4.04,1.75]],.07);line('ivory',[[side*1.82,2.88,-1.75],[0,4.04,-1.75]],.065);for(let i=0;i<15;i++)line('slate',[[0,4.0,-1.54+i*.22],[side*1.80,2.86,-1.54+i*.22]],.035);}
    line('ivory',[[0,4.13,-1.86],[0,4.08,-1.6],[0,4.08,1.6],[0,4.18,1.9]],.08);box('wood',0,1.8,1.48,.86,1.25,.08);for(let x of[-.31,0,.31])box('gold',x,1.8,1.54,.04,1.22,.025);
    for(const[x,z,c]of[[-2.35,-1.2,'purple'],[2.35,-1.2,'orange'],[-2.35,1.4,'blue'],[2.35,1.4,'red']])banner(x,2.42,z,c,x);
    steps('stone',0,1.55,1.1,7,1.05,1.45,.1);
  }
  if(skin==='magisters'){
    plinth('ivory');box('!cyan',0,.39,0,5.54,.065,5.54);wall('ivory','bronze',.62);box('slate',0,1.24,-.1,3.15,1.55,3.1);windows(0,.48,-.1,3.15,1.55,3.1,'!purple');ball('navy',0,2.3,-.1,1.52,1.22,1.50);ring('gold',0,2.12,-.1,1.53,.07);for(let i=0;i<8;i++){const a=i*Math.PI/4;line('stone',[[Math.cos(a)*1.48,2.2,-.1+Math.sin(a)*1.48],[Math.cos(a)*.97,3.11,-.1+Math.sin(a)*.97],[0,3.59,-.1]],.044);}
    for(const[x,z,h]of[[-2.03,1.94,2.3],[2.03,1.94,2.3],[-2.03,-1.94,2.7],[2.03,-1.94,2.7],[0,-.12,3.84]]){cyl('ivory',x,.65+h/2,z,.26,h);for(let i=0;i<4;i++)cyl('bronze',x,.78+i*h/4,z,.31,.1);cyl('gold',x,h+.57,z,.35,.22);moving('levitating-magister-crystal',()=>{crystal(0,0,0,.26,.59,'purple');gem('!pink',0,0,.035,.16,.48);},(g,t)=>{g.position.set(x,h+1.23+Math.sin(t*1.3+x+z)*.095,z);g.rotation.y=t*.24;});}
    box('ivory',0,1.1,2.42,1.26,1.55,.28);box('dark',0,.99,2.59,.82,1.31,.05);gem('!purple',0,1.82,2.61,.15,.24);steps('ivory',0,2.5,1.2,6,.58,.6,.03);
  }
  if(skin==='frost'){
    cyl('ice',0,.17,0,2.7,.28);cyl('blue',0,.36,0,2.4,.27);
    for(let i=0;i<17;i++){const a=i*2.399,r=.65+(i%4)*.54,x=Math.cos(a)*r,z=Math.sin(a)*r,h=1.3+(i%5)*.45;crystal(x,h*.61+.3,z,.44,h*.57,i%3===0?'cyan':'blue');}
    crystal(0,2.67,-.39,1.18,2.42,'blue');crystal(-.37,3.07,-.22,.40,2.0,'cyan');crystal(.42,3.55,-.53,.30,2.13,'ice');
    for(const[x,y,z,r]of[[0,1.45,2.04,.42],[1.24,2.23,.96,.32],[-1.12,1.28,1.34,.3],[.25,3.55,.23,.3]]){box('navy',x,y,z,r*1.4,r*2,.09);line('gold',[[x-r,y-r,z+.07],[x-r,y+.22,z+.07],[x,y+r*1.8,z+.07],[x+r,y+.22,z+.07],[x+r,y-r,z+.07]],.055);box('!cyan',x,y,z+.075,.08,r*1.6,.025);}
    ring('gold',.43,5.35,-.52,.28,.045,0);gem('shine',.43,5.83,-.52,.07,.11);
    moving('orbiting-frost',()=>{for(let i=0;i<6;i++){const a=i*Math.PI/3;gem('!ice',Math.cos(a)*1.5,(i%3)*.25,Math.sin(a)*1.5,.065,.14);}},(g,t)=>{g.position.y=2.7;g.rotation.y=t*.17;});
  }
  if(skin==='crescent'){
    plinth('sand');wall('ivory','gold',.65);masonry('ivory',0,.47,-.18,2.75,2.46,2.6);windows(0,.47,-.18,2.75,2.46,2.6,'navy');
    const dome=(x,z,y,r)=>{cyl('gold',x,y,z,r,.17);ball('gold',x,y+.56*r,z,r,r*.88,r);cone('gold',x,y+1.29*r,z,r*.54,.8*r);cyl('bronze',x,y+1.83*r,z,.032,.47);};
    dome(0,-.2,3.13,1.16);for(let s of[-1,1])for(let z of[-2.04,2.04]){cyl('ivory',s*2.03,1.81,z,.38,2.65);for(let j=0;j<3;j++){cyl('gold',s*2.03,.74+j*.8,z,.44,.12);box('navy',s*2.03,1.22+j*.65,z+.38,.14,.35,.035);}dome(s*2.03,z,3.09,.49);}
    for(let x of[-.87,0,.87]){box('navy',x,1.73,1.15,.43,1.25,.05);cone('gold',x,2.55,1.19,.26,.45);box('gold',x-.25,1.7,1.22,.06,1.48,.07);box('gold',x+.25,1.7,1.22,.06,1.48,.07);}
    steps('sand',0,1.48,1.2,8,1.08,1.20,.1);moving('celestial-crescent',()=>{line('gold',[[.13,.23,0],[-.10,.29,0],[-.26,.14,0],[-.22,-.13,0],[.03,-.25,0],[.22,-.13,0]],.075);gem('!shine',.12,.06,.02,.07,.09);},(g,t)=>{g.position.set(0,5.59,-.2);g.rotation.y=Math.sin(t*.6)*.17;});
  }
  if(skin==='sunbless'){
    plinth('teal');wall('teal','gold',1.32);
    for(let i=0;i<3;i++){const size=4.0-i*.9,y=.46+i*.91;box('sand',0,y+.43,0,size,.86,size);box('gold',0,y+.89,0,size+.14,.14,size+.14);for(let side of[-1,1])for(let j=0;j<5-i;j++){box('teal',side*(size/2+.025),y+.45,(j-(4-i)/2)*.52,.06,.45,.24);box('teal',(j-(4-i)/2)*.52,y+.45,side*(size/2+.025),.24,.45,.06);}}
    box('sand',0,3.65,0,1.28,.95,1.28);box('gold',0,4.13,0,1.5,.13,1.5);box('dark',0,3.63,.65,.62,.85,.05);pyramid('gold',0,4.20,0,1.57,.39);
    for(let side of[-1,1])for(let i=0;i<6;i++){const u=-.65+i*.26;line('bronze',[[u,4.23,side*.74],[u*.1,4.57,side*.08]],.022);line('bronze',[[side*.74,4.23,u],[side*.08,4.57,u*.1]],.022);}
    for(let side of[-1,1])for(let i=0;i<3;i++){box('teal',side*.653,3.62,-.39+i*.39,.05,.55,.09);gem('gold',side*.70,3.62,-.39+i*.39,.06,.12);}
    for(let i=0;i<14;i++){box('gold',0,.46+i*.235,2.1-i*.096,.75,.15,.22);box('gold',2.1-i*.096,.46+i*.235,0,.22,.15,.75);}
    for(const[x,z]of[[-2.35,-2.35],[-2.35,0],[-2.35,2.35],[0,-2.35],[2.35,-2.35],[2.35,0],[2.35,2.35]]){cyl('slate',x,1.18,z,.23,1.7);for(let j=0;j<4;j++)cyl('red',x,.51+j*.48,z,.25,.16);ring('pink',x,2.08,z,.25,.055);}
    for(let s of[-1,1]){box('teal',s*.82,1.25,2.45,.27,2.3,.27);box('gold',s*.82,2.48,2.45,.48,.21,.47);pyramid('gold',s*.82,2.59,2.45,.55,.48);}box('teal',0,1.16,2.45,1.45,1.89,.18);
    moving('solar-seal',()=>{ring('gold',0,0,0,.32,.055,0);for(let i=0;i<8;i++){const a=i*Math.PI/4;box('gold',Math.cos(a)*.47,Math.sin(a)*.47,0,.08,.27,.07,0,-a);}gem('!shine',0,0,.03,.17,.2);},(g,t)=>{g.position.set(0,1.27,2.59);g.rotation.z=Math.sin(t*.4)*.035;});
  }
  flush(root);Object.values(G).forEach(g=>g.dispose());root.userData.animate=time=>{const t=Number.isFinite(time)?time:0;movements.forEach(fn=>fn(t));};root.userData.animate(0);root.userData.modelStats={drawCalls,triangles,pieces};return root;
}
