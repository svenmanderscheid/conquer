import * as T from './vendor/three.module.js';

export const CASTLE_SKIN_IDS=Object.freeze(["default","ironkeep","rosehall","sandspire","tidewatch","winterhold","jadecourt","emberforge","ravenloft","clockwork","sapphire","phoenix","astral","leviathan","yggdrasil","tempest","eclipse","dragon"]);
const colors={phoenix:'#ff8a42',astral:'#9bafff',leviathan:'#55f4df',yggdrasil:'#7cff9b',tempest:'#8edbff',eclipse:'#cb91ff',dragon:'#63cfff'};
const mythics=new Set(['phoenix','astral','leviathan','yggdrasil','tempest','eclipse','dragon']);

// All effects are local to the active castle. No lights, timers or postprocessing.
export function addCastleEffects(root,skin){
  if(skin==='default')return;
  if(!mythics.has(skin)){
    const animateModel=root.userData.animate;let phase=0;
    root.userData.animate=time=>{phase=time;animateModel?.(time);};
    root.userData.effectState=()=>({rarity:'legendary',particles:0,rings:0,runes:0,glow:0,phase,animations:root.userData.animationCount||0});
    root.userData.animate(0);return;
  }
  const color=new T.Color(colors[skin]||'#ffd45d'),mythic=mythics.has(skin);
  const fx=new T.Group();fx.name=`castle-effects-${skin}`;fx.userData.cosmeticEffect=true;
  root.add(fx);
  const animateModel=root.userData.animate;
  const rings=[];let glow=null,runes=null;
  if(mythic){
    const material=new T.ShaderMaterial({transparent:true,depthWrite:false,side:T.DoubleSide,blending:T.AdditiveBlending,
      uniforms:{tint:{value:color},strength:{value:.64},phase:{value:0}},
      vertexShader:'varying vec2 p; void main(){p=uv*2.0-1.0;gl_Position=projectionMatrix*modelViewMatrix*vec4(position,1.0);}',
      fragmentShader:'varying vec2 p; uniform vec3 tint; uniform float strength; uniform float phase;void main(){float r=length(p);float rays=.8+.2*sin(atan(p.y,p.x)*6.0-phase);float a=(1.0-smoothstep(.52,1.0,r))*smoothstep(.12,.53,r)*rays;gl_FragColor=vec4(tint,a*strength);}'
    });
    glow=material;
    const aura=new T.Mesh(new T.PlaneGeometry(8.5,8.5),material);aura.rotation.x=-Math.PI/2;aura.position.y=.09;aura.raycast=()=>{};fx.add(aura);
    for(let i=0;i<2;i++){
      const ring=new T.Mesh(new T.TorusGeometry(3.05+i*.39,.05,5,72,Math.PI*(i?1.35:1.67)),new T.MeshBasicMaterial({color,transparent:true,opacity:.5-i*.12,depthWrite:false,blending:T.AdditiveBlending}));
      ring.rotation.x=-Math.PI/2;ring.position.y=.17+i*.12;ring.raycast=()=>{};fx.add(ring);rings.push(ring);
    }
  }
  if(mythic){
    runes=new T.InstancedMesh(new T.OctahedronGeometry(.14,0),new T.MeshBasicMaterial({color,transparent:true,opacity:.87,depthWrite:false,blending:T.AdditiveBlending}),12);runes.name='orbiting-aura-runes';runes.raycast=()=>{};runes.frustumCulled=false;fx.add(runes);
  }
  const count=mythic?32:skin==='forest'?18:12;
  const geometry=skin==='forest'?new T.SphereGeometry(.07,5,3):new T.OctahedronGeometry(mythic?.1:.07,0);
  const particles=new T.InstancedMesh(geometry,new T.MeshBasicMaterial({color,transparent:true,opacity:mythic?.9:.8,depthWrite:false,blending:skin==='forest'?T.NormalBlending:T.AdditiveBlending}),count);
  particles.name=mythic?'aura-motes':skin==='forest'?'falling-petals':skin==='royal'?'dragon-embers':'enchanted-sparks';particles.raycast=()=>{};particles.frustumCulled=false;fx.add(particles);
  const transform=new T.Object3D();let phase=0;
  root.userData.animate=time=>{
    phase=time;animateModel?.(time);const orbit=time*Math.PI/2;
    if(glow){glow.uniforms.phase.value=orbit;glow.uniforms.strength.value=.64+Math.sin(orbit)*.17;}
    if(runes){for(let i=0;i<12;i++){const a=i*Math.PI/6+orbit;transform.position.set(Math.cos(a)*3.4,.42+Math.sin(orbit+i*Math.PI/3)*.22,Math.sin(a)*3.4);transform.rotation.set(0,a,Math.PI/4);transform.scale.set(1,.8,1);transform.updateMatrix();runes.setMatrixAt(i,transform.matrix);}runes.instanceMatrix.needsUpdate=true;}
    for(let i=0;i<count;i++){
      const seed=i/count,angle=seed*Math.PI*2+(mythic?orbit:time*.2),cycle=(time*(mythic?.25:skin==='forest'?.095:.13)+seed*2.37)%1;
      const r=2.4+Math.sin(i*3.7)*.6;
      transform.position.set(Math.cos(angle)*r,skin==='forest'?5.8-cycle*5.3:.35+cycle*5.1,Math.sin(angle)*r);
      transform.rotation.set(time*.6+i,time*.4,angle);transform.scale.setScalar(.45+Math.sin(cycle*Math.PI)*.85);if(skin==='forest')transform.scale.y*=.4;
      transform.updateMatrix();particles.setMatrixAt(i,transform.matrix);
    }
    particles.instanceMatrix.needsUpdate=true;
    rings.forEach((ring,i)=>{ring.rotation.z=orbit*(i?-1:1);ring.material.opacity=.6+Math.sin(orbit+i)*.16;});
  };
  root.userData.effectState=()=>({rarity:mythic?'mythic':'legendary',particles:count,rings:rings.length,runes:runes?.count||0,glow:glow?.uniforms.strength.value||0,phase});
  root.userData.animate(0);
}
