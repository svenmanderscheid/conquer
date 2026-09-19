import * as T from './vendor/three.module.js';

// Five original legendary keeps. Each owns one four-second architectural
// animation; there are deliberately no aura, emissive or particle materials.
export function buildNewLegendaryA(skin) {
  if (!['ironkeep', 'rosehall', 'sandspire', 'tidewatch', 'winterhold'].includes(skin)) return null;
  const root = new T.Group();
  root.name = `castle-skin-${skin}`;
  Object.assign(root.userData, { building: 'keep', skin, animationCount: 1 });
  const colors = {
    earth: '#6c7350', grass: '#728e56', moss: '#445e42', stone: '#85909a', pale: '#cbd3d4',
    dark: '#273441', iron: '#465365', wood: '#805d3b', oak: '#b48c59', gold: '#daa84d',
    cream: '#ece0c5', ivory: '#f4e9d7', red: '#a75146', pink: '#d8839b', rose: '#993d68',
    leaf: '#42735b', sand: '#cea267', sandstone: '#e3c591', ochre: '#a47a49', teal: '#387b77',
    blue: '#487797', navy: '#294a66', aqua: '#7cabb6', white: '#e8eee8', snow: '#eef4ef',
    ice: '#92c8d9', crystal: '#5aa5c6', shadow: '#345164', window: '#e2b974'
  };
  const shapes = {
    box: new T.BoxGeometry(1, 1, 1).toNonIndexed(),
    cylinder: new T.CylinderGeometry(1, 1, 1, 12).toNonIndexed(),
    cone: new T.ConeGeometry(1, 1, 8).toNonIndexed(),
    ball: new T.SphereGeometry(1, 12, 8).toNonIndexed(),
    rock: new T.IcosahedronGeometry(1),
    crystal: new T.OctahedronGeometry(1)
  };
  const batches = new Map(), materials = new Map();
  const matrix = new T.Matrix4(), normalMatrix = new T.Matrix3(), position = new T.Vector3(), scale = new T.Vector3();
  const quaternion = new T.Quaternion(), euler = new T.Euler(), point = new T.Vector3(), normal = new T.Vector3();
  let target = root, pieces = 0, animate = () => {};
  function add(geometry, color, x = 0, y = 0, z = 0, sx = 1, sy = 1, sz = 1, rx = 0, ry = 0, rz = 0) {
    if (!batches.has(target)) batches.set(target, new Map());
    const pigments = batches.get(target);
    if (!pigments.has(color)) pigments.set(color, { p: [], n: [] });
    const batch = pigments.get(color), gp = geometry.attributes.position, gn = geometry.attributes.normal;
    matrix.compose(position.set(x, y, z), quaternion.setFromEuler(euler.set(rx, ry, rz)), scale.set(sx, sy, sz));
    normalMatrix.getNormalMatrix(matrix);
    for (let i = 0; i < gp.count; i++) {
      point.fromBufferAttribute(gp, i).applyMatrix4(matrix);
      normal.fromBufferAttribute(gn, i).applyMatrix3(normalMatrix).normalize();
      batch.p.push(point.x, point.y, point.z); batch.n.push(normal.x, normal.y, normal.z);
    }
    pieces++;
  }
  const box = (c, x, y, z, w, h, d, ry = 0, rz = 0) => add(shapes.box, c, x, y, z, w, h, d, 0, ry, rz);
  const cyl = (c, x, y, z, r, h) => add(shapes.cylinder, c, x, y, z, r, h, r);
  const ball = (c, x, y, z, w, h = w, d = w) => add(shapes.ball, c, x, y, z, w, h, d);
  const cone = (c, x, y, z, r, h) => add(shapes.cone, c, x, y, z, r, h, r);
  function taper(c, x, y, z, top, bottom, h, sides = 12) {
    const g = new T.CylinderGeometry(top, bottom, h, sides).toNonIndexed(); add(g, c, x, y, z); g.dispose();
  }
  function ring(c, x, y, z, r, t = .04, rx = Math.PI / 2) {
    const g = new T.TorusGeometry(r, t, 5, 28).toNonIndexed(); add(g, c, x, y, z, 1, 1, 1, rx); g.dispose();
  }
  function line(c, pts, r = .04) {
    const g = new T.TubeGeometry(new T.CatmullRomCurve3(pts.map(v => new T.Vector3(...v))), Math.max(8, pts.length * 3), r, 5, false).toNonIndexed();
    add(g, c); g.dispose();
  }
  function moving(name, x, y, z, build, update) {
    const group = new T.Group(); group.name = name; group.position.set(x, y, z); group.userData.animatedPart = true;
    root.add(group); target = group; build(group); target = root;
    root.userData.animationName = name; animate = seconds => update(group, seconds * Math.PI / 2);
    return group;
  }
  function base(ground = 'earth', rim = 'stone') {
    taper(ground, 0, .18, 0, 2.82, 2.64, .32, 12);
    cyl(rim, 0, .35, 0, 2.60, .12);
    for (let i = 0; i < 17; i++) {
      const a = i * 2.399, r = 2.54 + (i % 3) * .05;
      add(shapes.rock, ground, Math.sin(a) * r, .19, Math.cos(a) * r, .29, .14, .25, 0, a);
    }
    for (let i = 0; i < 6; i++) box(rim, 0, .045 + i * .052, 2.94 - i * .14, 1.06, .12, .19);
  }
  function window(x, y, z, w = .20, h = .53, trim = 'pale', fill = 'dark', ry = 0) {
    box(trim, x, y, z, w + .12, h + .13, .055, ry);
    box(fill, x, y, z + .035, w, h, .06, ry);
    box(trim, x, y, z + .078, .028, h, .025, ry);
    box(trim, x, y, z + .082, w, .025, .03, ry);
  }
  function portal(x, y, z, w, h, trim = 'pale', door = 'wood') {
    box('dark', x, y + h * .48, z, w * 1.10, h, .15);
    box(door, x, y + h * .43, z + .085, w * .83, h * .88, .08);
    for (const s of [-1, 1]) box(trim, x + s * w * .59, y + h * .44, z + .08, w * .15, h, .24);
    line(trim, [[x - w * .6, y + h * .89, z + .12], [x - w * .36, y + h * 1.1, z + .12], [x, y + h * 1.18, z + .12], [x + w * .36, y + h * 1.1, z + .12], [x + w * .6, y + h * .89, z + .12]], .10);
    for (const dy of [.2, .67]) box('iron', x, y + h * dy, z + .14, w * .83, .05, .045);
    for (const s of [-1, 1]) ball('gold', x + s * w * .12, y + h * .47, z + .18, .035);
  }
  function roundTower(x, z, h, r, stone = 'stone', trim = 'pale', roof = null) {
    const y = .42;
    cyl(stone, x, y + h * .5, z, r, h);
    cyl(trim, x, y + .08, z, r + .08, .16);
    cyl(trim, x, y + h - .08, z, r + .10, .18);
    for (let row = 1; row < h / .36; row++) ring(trim, x, y + row * .34, z, r + .003, .012);
    window(x, y + h * .58, z + r, .14, .50, trim, 'dark');
    if (roof) {
      cone(roof, x, y + h + .45, z, r * 1.40, .95);
      ring(trim, x, y + h, z, r * 1.38, .035);
      ball('gold', x, y + h + .96, z, .065);
    } else {
      for (let i = 0; i < 10; i++) { const a = i * Math.PI / 5; box(trim, x + Math.sin(a) * r, y + h + .13, z + Math.cos(a) * r, .20, .29, .18, a); }
    }
  }
  function hipRoof(c, trim, x, y, z, w, d, h) {
    // Four sloped planes with an actual ridge, trim, and visible tile courses.
    const verts = [[-w/2,0,d/2], [w/2,0,d/2], [w*.18,h,0], [-w*.18,h,0], [-w/2,0,-d/2], [w/2,0,-d/2]];
    const g = new T.BufferGeometry();
    g.setAttribute('position', new T.Float32BufferAttribute([0,1,2,0,2,3,5,4,3,5,3,2,1,5,2,4,0,3].flatMap(i => verts[i]), 3));
    g.computeVertexNormals(); add(g, c, x, y, z); g.dispose();
    for (const s of [-1,1]) {
      line(trim, [[x-w/2,y,z+s*d/2], [x+w/2,y,z+s*d/2]], .055);
      for(let j=1;j<6;j++){const t=j/6;box(trim,x,y+h*t+.018,z+s*d/2*(1-t),w*(1-.64*t),.023,.023);}
    }
    line(trim, [[x-w*.18-.07,y+h+.03,z],[x+w*.18+.07,y+h+.03,z]], .055);
  }
  function ironkeep() {
    base('grass', 'stone');
    box('iron', 0, .53, -.13, 4.47, .20, 3.77);
    box('stone', 0, 1.67, -.58, 2.4, 2.32, 2.27);
    box('pale', 0, 2.88, -.58, 2.66, .20, 2.49);
    hipRoof('red', 'oak', 0, 3.04, -.58, 2.8, 2.70, 1.05);
    for (const s of [-1,1]) {
      roundTower(s*1.90, 1.25, 2.05, .46);
      roundTower(s*1.76, -1.60, s<0?3.26:2.50, .49, 'iron', 'stone');
      box('stone', s*1.8, 1.00, -.15, .36, 1.07, 2.58);
      for(let j=0;j<8;j++)box('pale',s*1.8,1.67,-1.34+j*.35,.39,.26,.18);
      for(let row=0;row<5;row++)box('pale',s*.97,.76+row*.4,.575,.17,.16,.045);
      window(s*.62,2.14,.585,.21,.66);
      box('oak',s*.92,1.16,.64,.13,1.16,.10);
    }
    for (const x of [-1.22,1.22]) box('iron',x,.95,1.36,1.25,1.00,.39);
    portal(0,.45,1.57,.91,1.18);
    for(let i=0;i<6;i++)box('iron',(i-2.5)*.13,1.09,1.75,.043,1.00,.055);
    for(let i=0;i<4;i++)box('gold',0,1.43+i*.14,1.76,.73,.025,.025);
    // Raised mill on the rear bastion is the only moving landmark.
    box('wood',-1.76,3.98,-1.60,.83,.83,.83);
    hipRoof('red','oak',-1.76,4.40,-1.60,1.07,.99,.56);
    moving('turning-windmill',-1.76,4.02,-1.12,()=>{
      for(let i=0;i<4;i++){
        const a=i*Math.PI/2, x=Math.sin(a), y=Math.cos(a);
        box('wood',x*.55,y*.55,0,.085,1.23,.075,0,-a);
        box('cream',x*.76+y*.12,y*.76-x*.12,.025,.28,.67,.055,0,-a);
        for(let j=0;j<5;j++)box('oak',x*(.49+j*.13)+y*.12,y*(.49+j*.13)-x*.12,.062,.31,.025,.035,0,-a);
      }
      add(shapes.cylinder,'gold',0,0,.10,.14,.20,.14,Math.PI/2);
    },(g,t)=>{g.rotation.z=-t;});
    for(const x of [1.8,2.15]) {box('wood',x,.65,.14,.22,.5,.30);box('oak',x,.92,.14,.27,.07,.35);}
  }
  function rosehall() {
    base('grass','cream');
    box('ivory',0,1.61,-.42,2.48,2.38,2.25);
    hipRoof('rose','pink',0,2.89,-.42,2.78,2.58,1.02);
    for(const s of[-1,1]) {
      roundTower(s*1.74,.65,2.35,.48,'ivory','cream','rose');
      roundTower(s*1.17,-1.52,3.15,.37,'ivory','gold','pink');
      box('ivory',s*1.05,.79,1.45,1.03,.73,.46);
      for(let i=0;i<4;i++)window(s*(.65+i*.21),.86,1.70,.10,.39,'cream','rose');
      for(let i=0;i<3;i++)window(s*.69,1.07+i*.60,.727,.23,.42,'cream','navy');
      box('cream',s*1.22,1.63,.70,.16,2.42,.20);
      box('cream',s*.68,1.50,.78,.72,.10,.20);
      for(let i=0;i<4;i++){const z=.14-i*.45;ball('leaf',s*2.19,.62,z,.28,.24,.3);ball(i%2?'pink':'rose',s*2.20,.82,z,.16,.10,.17);}
      box('cream',s*1.02,.46,2.08,.66,.12,.57);
      for(let i=0;i<4;i++)ball('pink',s*(.8+i*.13),.62,2.08,.1,.12,.16);
    }
    portal(0,.44,.93,.87,1.35,'cream','rose');
    ball('gold',0,2.19,1.03,.16,.16,.055);
    for(let i=0;i<6;i++){const a=i*Math.PI/3;ball('pink',Math.cos(a)*.12,2.19+Math.sin(a)*.12,1.08,.078,.078,.035);}
    cyl('ivory',0,3.8,-.52,.50,1.10);cone('rose',0,4.72,-.52,.72,.91);ring('gold',0,4.29,-.52,.70,.05);
    cyl('gold',0,5.30,-.52,.029,.90);
    moving('waving-rose-banner',0,5.50,-.52,(group)=>{
      const geometry=new T.PlaneGeometry(.85,.48,14,4);geometry.translate(.45,-.15,0);
      const material=new T.MeshStandardMaterial({color:colors.rose,side:T.DoubleSide,roughness:.86});
      material.onBeforeCompile=s=>{s.fragmentShader=s.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};
      const cloth=new T.Mesh(geometry,material);cloth.name='rose-banner-cloth';cloth.castShadow=true;group.add(cloth);
      group.userData.cloth=cloth;group.userData.rest=Float32Array.from(geometry.attributes.position.array);
      box('gold',.07,-.16,.014,.035,.49,.028);
    },(g,t)=>{
      const cloth=g.userData.cloth,p=cloth.geometry.attributes.position,rest=g.userData.rest;
      for(let i=0;i<p.count;i++){const x=rest[i*3],y=rest[i*3+1];p.setXYZ(i,x,y,Math.sin(x*8-t*2+y*2)*.115*Math.min(1,x/.3));}
      p.needsUpdate=true;cloth.geometry.computeVertexNormals();
    });
  }
  function sandspire() {
    base('sand','ochre');
    box('sandstone',0,.68,-.1,4.19,.56,3.70);
    box('sandstone',0,1.72,-.37,2.70,1.68,2.40);
    box('ochre',0,2.58,-.37,2.85,.16,2.55);
    cyl('sandstone',0,3.02,-.45,.91,.74);ball('teal',0,3.41,-.45,1.13,.64,1.13);ring('gold',0,3.38,-.45,1.13,.047);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;line('gold',[[Math.sin(a)*1.07,3.46,-.45+Math.cos(a)*1.07],[Math.sin(a)*.79,3.84,-.45+Math.cos(a)*.79],[0,4.07,-.45]],.023);}
    for(const s of[-1,1]) {
      for(const z of[-1.42,1.18]) {
        taper('sandstone',s*1.79,1.65,z,.31,.45,2.0,8);
        cyl('ochre',s*1.79,2.66,z,.49,.17);cyl('sandstone',s*1.79,2.98,z,.28,.57);
        ball('teal',s*1.79,3.29,z,.39,.27,.39);cone('gold',s*1.79,3.70,z,.055,.54);
        for(let i=0;i<6;i++){const a=i*Math.PI/3;box('gold',s*1.79+Math.sin(a)*.35,2.81,z+Math.cos(a)*.35,.035,.26,.035);}
        window(s*1.79,1.98,z+.38,.14,.55,'ochre','dark');
      }
      for(let i=0;i<4;i++){const z=-1.28+i*.62;box('ochre',s*1.40,1.68,z,.15,1.83,.18);}
      window(s*.84,1.84,.864,.22,.69,'ochre','teal');
      box('ochre',s*.99,.93,1.61,.73,.14,.40);
      for(let i=0;i<3;i++)ball('sand',s*(.77+i*.22),1.06,1.61,.11,.18,.13);
    }
    portal(0,.46,1.24,1.02,1.49,'ochre','teal');
    for(let i=0;i<7;i++)box('gold',0,2.22+i*.10,1.30,.58-i*.07,.027,.04);
    cyl('gold',0,4.12,-.45,.062,.25);
    moving('rotating-sun-ornament',0,4.83,-.45,()=>{
      ball('gold',0,0,0,.42,.42,.12);ring('ochre',0,0,0,.46,.06,0);
      for(let i=0;i<12;i++){const a=i*Math.PI/6;add(shapes.crystal,'gold',Math.sin(a)*.64,Math.cos(a)*.64,0,.10,.25,.075,0,0,-a);}
      ball('sandstone',0,0,.13,.18,.18,.05);
    },(g,t)=>{g.rotation.z=t;});
  }
  function tidewatch() {
    base('stone','pale');
    box('navy',0,.54,-.06,4.05,.22,3.63);
    box('white',.48,1.35,-.43,2.37,1.52,2.35);hipRoof('navy','blue',.48,2.19,-.43,2.66,2.63,.91);
    for(const s of[-1,1]) {
      roundTower(s*1.66,1.21,1.57,.47,'white','blue','navy');
      box('white',s*1.59,1.02,-.44,.40,.81,2.61);
      for(let i=0;i<7;i++)box('blue',s*1.59,1.47,-1.54+i*.34,.43,.18,.15);
      window(.48+s*.67,1.48,.771,.21,.58,'pale','navy');
      for(let i=0;i<4;i++){box('wood',s*.58,.55,1.91+i*.22,.07,.30,.07);box('oak',0,.54,1.91+i*.22,1.18,.10,.19);}
      line('wood',[[s*.58,.73,1.87],[s*.58,.73,2.60]],.037);
    }
    portal(.22,.61,1.03,.78,1.01,'pale','navy');
    const x=-1.13,z=-.92;
    taper('white',x,2.33,z,.48,.72,3.55);
    for(let i=0;i<5;i++)cyl(i%2?'navy':'blue',x,1.02+i*.63,z,.675-i*.041,.17);
    cyl('navy',x,4.11,z,.83,.16);
    for(let i=0;i<12;i++){const a=i*Math.PI/6;cyl('pale',x+Math.sin(a)*.79,4.32,z+Math.cos(a)*.79,.026,.35);}
    ring('pale',x,4.50,z,.80,.035);
    cyl('blue',x,4.37,z,.49,.33);
    for(let i=0;i<8;i++){const a=i*Math.PI/4;cyl('gold',x+Math.sin(a)*.50,4.90,z+Math.cos(a)*.50,.027,.72);}
    cone('navy',x,5.43,z,.72,.55);ring('pale',x,5.14,z,.71,.045);ball('gold',x,5.78,z,.07);
    // A brass reflector visibly revolves inside the open lantern. No light beam.
    moving('rotating-lighthouse-beacon',x,4.82,z,()=>{
      ball('gold',0,0,0,.24,.27,.15);ball('cream',0,0,.13,.16,.19,.04);
      box('gold',0,0,-.16,.48,.41,.065);box('navy',0,0,-.205,.54,.44,.035);
      for(const s of[-1,1])box('gold',s*.27,0,-.055,.035,.47,.24);
    },(g,t)=>{g.rotation.y=t;});
    for(const[x,z]of[[1.92,-.8],[2.16,-.95],[1.94,-1.3]]){cyl('wood',x,.74,z,.14,.40);ring('iron',x,.64,z,.14,.016);ring('iron',x,.86,z,.14,.016);}
  }
  function winterhold() {
    base('snow','shadow');
    box('pale',0,.68,-.05,4.24,.49,3.72);
    box('white',0,1.82,-.55,2.47,2.04,2.05);
    hipRoof('navy','ice',0,2.91,-.55,2.90,2.52,1.30);
    for(const s of[-1,1]) {
      roundTower(s*1.75,1.17,2.28,.45,'pale','snow','navy');
      roundTower(s*1.56,-1.40,2.90,.36,'white','ice','navy');
      box('shadow',s*1.88,.90,-.08,.31,.68,2.48);box('snow',s*1.88,1.28,-.08,.43,.14,2.55);
      for(let i=0;i<7;i++)box('snow',s*1.88,1.44,-1.21+i*.35,.38,.23,.17);
      for(let i=0;i<4;i++)box('ice',s*1.26,1.10+i*.46,.50,.14,.14,.065);
      window(s*.71,2.06,.517,.26,.71,'ice','shadow');
      line('snow',[[s*1.45,2.94,.69],[s*.51,4.25,-.54]],.065);
      for(let i=0;i<6;i++)add(shapes.cone,'ice',s*(.49+i*.15),2.77,.72,.04,.24+(i%3)*.07,.04,Math.PI);
      for(let i=0;i<4;i++){const z=-.59-i*.28;add(shapes.rock,'snow',s*2.32,.60,z,.23,.16,.22,0,i);}
    }
    portal(0,.72,1.09,.98,1.47,'ice','navy');
    box('snow',0,2.39,1.17,1.44,.14,.29);
    for(let i=0;i<7;i++){const x=(i-3)*.17;add(shapes.cone,'ice',x,2.18,1.29,.04,.30+(i%2)*.11,.04,Math.PI);}
    cyl('shadow',0,4.19,-.57,.39,.22);cyl('pale',0,4.37,-.57,.31,.17);
    for(let i=0;i<4;i++){const a=i*Math.PI/2;line('pale',[[Math.sin(a)*.30,4.41,-.57+Math.cos(a)*.30],[Math.sin(a)*.50,4.70,-.57+Math.cos(a)*.50],[Math.sin(a)*.40,5.01,-.57+Math.cos(a)*.40]],.065);}
    moving('turning-frost-crystal',0,5.10,-.57,()=>{
      add(shapes.crystal,'crystal',0,0,0,.36,.79,.36);
      add(shapes.crystal,'ice',.04,.08,.07,.25,.56,.25,0,Math.PI/4);
      ring('pale',0,-.01,0,.39,.025);
    },(g,t)=>{g.rotation.y=t;});
  }
  ({ ironkeep, rosehall, sandspire, tidewatch, winterhold })[skin]();
  let drawCalls=0,triangles=0;
  for(const [parent,pigments]of batches)for(const[color,batch]of pigments){
    if(!materials.has(color)){
      const material=new T.MeshStandardMaterial({color:colors[color],roughness:color==='gold'?.55:.84,metalness:color==='gold'?.20:0});
      material.onBeforeCompile=s=>{s.fragmentShader=s.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.42;\n#include <opaque_fragment>');};
      material.customProgramCacheKey=()=> 'castle-new-legendary-a-1';materials.set(color,material);
    }
    const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(batch.p,3));geometry.setAttribute('normal',new T.Float32BufferAttribute(batch.n,3));geometry.computeBoundingSphere();
    const mesh=new T.Mesh(geometry,materials.get(color));mesh.name=`${skin}-${color}`;mesh.castShadow=true;mesh.receiveShadow=true;mesh.userData.building='keep';parent.add(mesh);
    drawCalls++;triangles+=batch.p.length/9;
  }
  // The single cloth mesh is intentionally separate so its vertices can wave.
  if(skin==='rosehall'){drawCalls++;triangles+=14*4*2;}
  Object.values(shapes).forEach(g=>g.dispose());
  root.userData.modelStats={pieces,triangles,drawCalls};
  root.userData.animate=animate;animate(0);
  return root;
}
