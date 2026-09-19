import * as T from './vendor/three.module.js';

// Reference silhouettes: The Cherry Blossom and Oriental Golden Dragon.
// Everything is merged by material: tile ribs, masonry, blossoms and dragon
// ornaments do not add one draw call per decorative piece.
export function buildCastleSkin(skin) {
  if (!['forest', 'royal'].includes(skin)) return null;
  const root = new T.Group();
  root.name = `castle-skin-${skin}`;
  root.userData.building = 'keep';
  const palette = {
    stone:'#d4c8ae', stoneLight:'#e8dfca', mortar:'#a69e89', white:'#f0eee1',
    charcoal:'#202e38', timber:'#714b34', jade:'#278f79', jadeLight:'#60bca2',
    gold:'#f9ba22', goldLight:'#ffe373', goldDark:'#b9780d', red:'#a92325',
    scarlet:'#e23b2d', slate:'#52677e', slateLight:'#95a8bc', dark:'#172336',
    pink:'#ea86b3', pinkLight:'#ffbdd9', pinkDark:'#cb538c', leaf:'#4a7453'
  };
  const batches = new Map(), matrix = new T.Matrix4(), normal = new T.Matrix3();
  const position = new T.Vector3(), scale = new T.Vector3(), rotation = new T.Quaternion(), euler = new T.Euler();
  const point = new T.Vector3(), direction = new T.Vector3();
  const shapes = {
    box:new T.BoxGeometry(1,1,1).toNonIndexed(),
    ball:new T.SphereGeometry(1,9,6).toNonIndexed(),
    rock:new T.IcosahedronGeometry(1,0),
    cylinder:new T.CylinderGeometry(1,1,1,9).toNonIndexed(),
    cone:new T.ConeGeometry(1,1,8).toNonIndexed()
  };
  let pieces = 0;
  function add(geometry, color, x=0,y=0,z=0,sx=1,sy=1,sz=1,rx=0,ry=0,rz=0) {
    if (!batches.has(color)) batches.set(color,{p:[],n:[]});
    const batch=batches.get(color), p=geometry.attributes.position, n=geometry.attributes.normal;
    matrix.compose(position.set(x,y,z), rotation.setFromEuler(euler.set(rx,ry,rz)), scale.set(sx,sy,sz));
    normal.getNormalMatrix(matrix);
    for(let i=0;i<p.count;i++) {
      point.fromBufferAttribute(p,i).applyMatrix4(matrix); direction.fromBufferAttribute(n,i).applyMatrix3(normal).normalize();
      batch.p.push(point.x,point.y,point.z); batch.n.push(direction.x,direction.y,direction.z);
    }
    pieces++;
  }
  const box=(color,x,y,z,w,h,d,ry=0,rz=0)=>add(shapes.box,color,x,y,z,w,h,d,0,ry,rz);
  const ball=(color,x,y,z,w,h=w,d=w)=>add(shapes.ball,color,x,y,z,w,h,d);
  const cylinder=(color,x,y,z,r,h)=>add(shapes.cylinder,color,x,y,z,r,h,r);
  function line(color, coords, radius=.035) {
    const curve=new T.CatmullRomCurve3(coords.map(p=>new T.Vector3(...p)));
    const geometry=new T.TubeGeometry(curve,Math.max(4,coords.length),radius,4,false).toNonIndexed();
    add(geometry,color); geometry.dispose();
  }
  function coneBetween(color, a,b,r) {
    const start=new T.Vector3(...a),end=new T.Vector3(...b),delta=end.clone().sub(start);
    const q=new T.Quaternion().setFromUnitVectors(new T.Vector3(0,1,0),delta.clone().normalize());
    const angles=new T.Euler().setFromQuaternion(q),middle=start.add(end).multiplyScalar(.5);
    add(shapes.cone,color,middle.x,middle.y,middle.z,r,delta.length(),r,angles.x,angles.y,angles.z);
  }
  function roof(x,y,z,w,d,h,color='jade',rib='jadeLight',ridgeRatio=.24) {
    const ridge=w*ridgeRatio, vertices=[], segments=8, rows=7;
    function sample(side,u,v) {
      const a=w/2+(ridge-w/2)*v, b=d/2*(1-v);
      let px,pz;
      if(side===0){px=u*a;pz=b;}else if(side===1){px=a;pz=-u*b;}
      else if(side===2){px=-u*a;pz=-b;}else{px=-a;pz=u*b;}
      return [x+px,y+h*Math.pow(v,.8)+.19*Math.pow(1-v,5)*(.35+.65*Math.pow(Math.abs(u),4)),z+pz];
    }
    for(let side=0;side<4;side++) {
      for(let row=0;row<rows;row++)for(let col=0;col<segments;col++) {
        const u=-1+2*col/segments, v=row/rows, nextU=u+2/segments,nextV=v+1/rows;
        const a=sample(side,u,v),b=sample(side,nextU,v),c=sample(side,nextU,nextV),e=sample(side,u,nextV);
        vertices.push(...a,...b,...c,...a,...c,...e);
      }
      const count=Math.max(5,Math.round((side%2?d:w)/.17));
      for(let i=0;i<=count;i++) {
        const u=-1+2*i/count;
        line(i%4===0?color:rib,Array.from({length:7},(_,j)=>{const p=sample(side,u,j/6);p[1]+=.018;return p;}),.024);
      }
      line('gold',Array.from({length:9},(_,i)=>sample(side,-1+2*i/8,0)),.042);
      line('gold',Array.from({length:8},(_,i)=>sample(side,1,i/7)),.039);
    }
    const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(vertices,3));geometry.computeVertexNormals();
    add(geometry,color);geometry.dispose();
    line('goldLight',[[x-ridge-.16,y+h+.24,z],[x-ridge,y+h+.07,z],[x,y+h+.035,z],[x+ridge,y+h+.07,z],[x+ridge+.16,y+h+.24,z]],.058);
    for(const s of [-1,1]) coneBetween('gold',[x+s*(ridge+.12),y+h+.18,z],[x+s*(ridge+.24),y+h+.4,z],.065);
  }
  function tier(x,y,z,w,d,h,body='white') {
    box(body,x,y+h/2,z,w,h,d);
    for(const dy of [.055,h-.065])box('charcoal',x,y+dy,z,w+.06,.11,d+.06);
    for(const side of [-1,1]) {
      for(let i=0;i<Math.max(3,Math.floor(w/.37));i++) {
        const n=Math.max(3,Math.floor(w/.37)),px=x-w*.44+(i+.5)*w*.88/n;
        box('charcoal',px,y+h*.57,z+side*(d/2+.014),.17,h*.46,.045);
        box('white',px,y+h*.59,z+side*(d/2+.043),.018,h*.48,.025);
      }
      for(let i=0;i<Math.max(3,Math.floor(d/.37));i++) {
        const n=Math.max(3,Math.floor(d/.37)),pz=z-d*.44+(i+.5)*d*.88/n;
        box('charcoal',x+side*(w/2+.014),y+h*.57,pz,.045,h*.46,.17);
      }
      box('charcoal',x+side*w*.47,y+h/2,z,.07,h,d+.08);
    }
  }
  function blossom(x,z,mirror) {
    line('timber',[[x,.12,z],[x+.1*mirror,.9,z],[x-.1*mirror,1.7,z-.08],[x+.25*mirror,2.5,z-.15]],.1);
    const branches=[[-.55,1.7,.18],[.53,1.85,-.23],[-.3,2.15,-.5],[.38,2.6,.15]];
    branches.forEach(([dx,y,dz],n)=>{
      line('timber',[[x,1.1,z],[x+dx*.55,y-.35,z+dz*.6],[x+dx,y,z+dz]],.052);
      for(let i=0;i<9;i++) {
        const angle=i*2.399+n,spread=.12+(i%3)*.11;
        const bx=x+dx+Math.cos(angle)*spread,bz=z+dz+Math.sin(angle)*spread,by=y+.08+(i%3)*.14;
        add(shapes.rock,['pink','pinkLight','pinkDark'][i%3],bx,by,bz,.24,.19,.22);
        for(let p=0;p<3;p++)ball('pinkLight',bx+Math.cos(p*2.1)*.16,by+.13,bz+Math.sin(p*2.1)*.16,.048);
      }
    });
    for(let i=0;i<10;i++)ball('pink',x+Math.cos(i*2.4)*.55,.055,z+Math.sin(i*2.4)*.45,.06,.022,.04);
  }
  function flag(x,z) {
    cylinder('gold',x,1.32,z,.023,2.5);ball('goldLight',x,2.61,z,.055);
    box('white',x+.16,2.04,z,.31,.92,.028);box('red',x+.16,2.1,z+.02,.3,.36,.016);
    box('gold',x,2.51,z,.44,.025,.035);
  }
  function cherry() {
    // Slightly sloped, cut-stone Japanese tenshu foundation.
    const base=new T.CylinderGeometry(2.96,3.31,1.34,4,1,false,Math.PI/4).toNonIndexed();
    add(base,'stone',0,.74,0);base.dispose();
    box('mortar',0,.1,0,4.82,.17,4.82);
    for(const side of [-1,1])for(let row=0;row<4;row++)for(let i=0;i<6;i++) {
      const y=.27+row*.29,edge=2.34-row*.065,p=-2.06+i*.78+(row%2)*.18;
      if(Math.abs(p)>edge-.12)continue;
      if(!(side===1&&Math.abs(p)<.55))box((row+i)%3?'stoneLight':'mortar',p,y,side*edge,.7,.015,.025);
      box((row+i)%3?'stoneLight':'mortar',side*edge,y,p,.025,.015,.7);
      if(!(side===1&&Math.abs(p)<.55))box('mortar',p+.32,y+.12,side*(edge-.025),.015,.23,.024);
    }
    box('charcoal',0,1.42,0,4.5,.18,4.5);
    box('timber',0,.73,2.19,.67,1.12,.14);box('dark',0,.74,2.275,.51,.99,.03);
    for(const s of [-1,1])box('stoneLight',s*.41,.72,2.29,.16,1.31,.22);
    box('stoneLight',0,1.32,2.3,.98,.17,.22);
    for(let i=0;i<5;i++)box('stoneLight',0,.09+i*.095,2.96-i*.13,1.05,.15,.3);
    tier(0,1.49,0,4.24,4.05,.58);
    roof(0,2.02,0,5.18,4.92,.54);
    tier(0,2.55,0,3.32,3.05,.57);
    roof(0,3.05,0,4.33,4.04,.58);
    tier(0,3.63,0,2.58,2.43,.65);
    roof(0,4.22,0,3.52,3.24,.66);
    tier(0,4.9,0,1.94,1.73,.76);
    roof(0,5.59,0,2.62,2.43,.57);
    // Raised miniature gables punctuate the tiered green roofs.
    roof(0,3.04,1.23,1.4,1.35,.54,'jade','jadeLight',.12);
    roof(0,4.26,1,1.13,1.05,.48,'jade','jadeLight',.12);
    blossom(-2.22,1.2,1);blossom(2.22,.6,-1);
    flag(-1.4,2.72);flag(1.44,2.72);
  }
  function lantern(x,y,z) {
    ball('scarlet',x,y,z,.23,.22,.23);cylinder('gold',x,y+.21,z,.085,.045);cylinder('gold',x,y-.21,z,.07,.04);
    line('gold',[[x,y-.21,z],[x,y-.42,z]],.017);
    for(let i=0;i<3;i++)line('gold',[[x+(i-1)*.03,y-.4,z],[x+(i-1)*.05,y-.53,z]],.009);
    for(let i=0;i<8;i++){const angle=i*Math.PI/4;line('red',[[x+Math.cos(angle)*.04,y+.2,z+Math.sin(angle)*.04],[x+Math.cos(angle)*.23,y,z+Math.sin(angle)*.23],[x+Math.cos(angle)*.04,y-.2,z+Math.sin(angle)*.04]],.011);}
  }
  function dragon() {
    box('goldDark',0,2.38,2.24,1.31,.12,1.11);box('gold',0,2.49,2.24,1.18,.15,1.01);
    // Coiled tail, rising serpentine neck, long snout and branching antlers.
    line('gold',[[.45,2.69,2.14],[.59,2.66,2.57],[.13,2.65,2.75],[-.44,2.7,2.47],[-.29,2.88,2.08],[.04,3.02,2.16],[.02,3.34,2.28],[-.09,3.57,2.43]],.18);
    ball('gold',-.09,3.61,2.48,.27,.23,.25);ball('goldLight',-.06,3.58,2.73,.18,.12,.22);
    box('goldDark',-.06,3.49,2.83,.28,.04,.2);
    for(const side of [-1,1]) {
      ball('dark',-.09+side*.19,3.68,2.62,.033,.035,.022);
      line('goldLight',[[side*.12-.09,3.79,2.42],[side*.23-.09,4.01,2.24],[side*.38-.09,4.15,2.22]],.043);
      coneBetween('goldLight',[side*.23-.09,4.01,2.24],[side*.2-.09,4.18,2.07],.03);
      line('goldLight',[[side*.17-.06,3.59,2.82],[side*.37-.06,3.68,2.93],[side*.49-.06,3.86,2.85]],.022);
      line('gold',[[side*.13,3.18,2.26],[side*.43,3.09,2.5],[side*.45,2.88,2.66]],.065);
      for(let i=0;i<3;i++)coneBetween('goldLight',[side*.45,2.89,2.63+i*.04],[side*(.57+i*.035),2.83,2.75+i*.04],.026);
      coneBetween('gold',[side*.13,3.69,2.3],[side*.42,3.9,2.16],.1);
    }
    for(let i=0;i<6;i++)coneBetween('goldLight',[0,2.89+i*.13,2.02],[0,2.96+i*.14,1.83],.067);
    for(let i=0;i<8;i++)ball('goldLight',Math.sin(i*1.7)*.09,2.78+i*.08,2.39,.045,.035,.026);
  }
  function royal() {
    box('slate',0,.1,0,6.12,.2,6.12);box('stone',0,.24,0,5.65,.15,5.65);
    // Open courtyard framed by a red fort with blue-grey battlements.
    for(const side of [-1,1]) {
      box('red',side*2.62,1.03,0,.53,1.65,5.58);box('slate',side*2.64,.32,0,.73,.36,5.68);
      box('slateLight',side*2.62,1.88,0,.77,.23,5.69);
      box('red',0,1.03,side*2.62,5.58,1.65,.53);box('slate',0,.32,side*2.64,5.68,.36,.73);
      box('slateLight',0,1.88,side*2.62,5.69,.23,.77);
      for(let i=0;i<11;i++) {
        const v=-2.52+i*.504;
        box('slate',side*2.84,2.1,v,.25,.3,.26);box('slate',v,2.1,side*2.84,.26,.3,.25);
        if(i%2===0){box('scarlet',side*2.925,1.05,v,.075,1.42,.15);box('scarlet',v,1.05,side*2.925,.15,1.42,.075);}
      }
      for(const end of [-1,1]) {
        const x=side*2.54,z=end*2.54;
        box('red',x,1.23,z,.85,2.1,.85);box('slate',x,.31,z,1.03,.38,1.03);
        box('slateLight',x,2.29,z,1.07,.22,1.07);
        for(const px of [-.28,.28])box('red',x+px,2.62,z,.09,.54,.64);
        roof(x,2.81,z,1.24,1.24,.36,'gold','goldLight',.18);
      }
    }
    box('red',0,1.14,2.84,1.59,2.07,.58);box('slateLight',0,2.23,2.85,1.86,.3,.83);
    box('dark',0,.84,3.14,.68,1.3,.035);box('goldDark',0,.84,3.17,.055,1.3,.02);
    for(const side of [-1,1]) {
      box('scarlet',side*.58,1.02,3.01,.26,1.63,.42,0,side*.1);
      box('slate',side*.64,.28,3.02,.43,.35,.48);
      for(let i=0;i<4;i++)ball('gold',side*.18,.4+i*.24,3.18,.028);
    }
    box('slate',0,.48,-.29,2.64,.4,2.46);
    box('red',0,1.65,-.29,2.35,2.18,2.13);
    for(const y of [.72,1.53,2.55])box('goldDark',0,y,-.29,2.44,.12,2.22);
    for(const side of [-1,1])for(let i=0;i<3;i++) {
      box('gold',-.8+i*.8,1.18,side*.8-.29,.46,.42,.035);
      box('gold',side*1.19,1.18,-1.02+i*.73,.03,.42,.44);
    }
    tier(0,2.63,-.29,2.37,2.13,.69,'red');
    roof(0,3.21,-.29,3.46,3.16,.61,'gold','goldLight');
    tier(0,3.91,-.29,1.78,1.62,.82,'red');
    for(const side of [-1,1])for(const end of [-1,1])box('gold',side*.85,4.28,-.29+end*.77,.095,.82,.095);
    roof(0,4.67,-.29,2.99,2.77,.98,'gold','goldLight',.16);
    coneBetween('gold',[0,5.62,-.29],[0,6.37,-.29],.08);
    dragon();
    for(const side of [-1,1]) {
      cylinder('goldDark',side*1.88,2.04,1.65,.035,2.53);
      line('goldDark',[[side*1.88,3.3,1.65],[side*1.54,3.39,1.65],[side*1.47,3.13,1.65]],.024);
      lantern(side*1.48,2.89,1.65);
      lantern(side*1.34,4.53,-.22);
    }
  }
  if(skin==='forest')cherry();else royal();
  let triangles=0;
  for(const [name,batch] of batches) {
    const geometry=new T.BufferGeometry();
    geometry.setAttribute('position',new T.Float32BufferAttribute(batch.p,3));
    geometry.setAttribute('normal',new T.Float32BufferAttribute(batch.n,3));
    geometry.computeBoundingSphere();
    const material=new T.MeshStandardMaterial({color:palette[name],roughness:name.startsWith('gold')?.55:.9,metalness:name.startsWith('gold')?.16:0});
    material.onBeforeCompile=shader=>{shader.fragmentShader=shader.fragmentShader.replace('#include <opaque_fragment>', 'outgoingLight *= 0.42;\n#include <opaque_fragment>');};
    material.customProgramCacheKey=()=> 'castle-skin-pigment-1';
    const mesh=new T.Mesh(geometry,material);mesh.name=`${skin}-${name}`;mesh.castShadow=true;mesh.receiveShadow=true;
    mesh.userData.building='keep';root.add(mesh);triangles+=batch.p.length/9;
  }
  Object.values(shapes).forEach(g=>g.dispose());
  root.userData.skin=skin;
  root.userData.modelStats={drawCalls:batches.size,triangles,pieces};
  return root;
}
