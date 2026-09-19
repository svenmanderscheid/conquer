import * as T from './vendor/three.module.js';
import {buildTrainingUnit} from './training-unit.js';
import {GLTFLoader} from './vendor/loaders/GLTFLoader.js';
import {createKnightShowcaseMotion} from './knight-showcase-motion.js';

let knightAssetPromise;
const knightUrl=new URL('./conquer-knight.glb?revision=loop2',import.meta.url).href;
const loadKnight=()=>knightAssetPromise??=new GLTFLoader().loadAsync(knightUrl);

export async function mountTrainingPortrait(host,{type,tier,reducedMotion,icon=false}){
 const renderer=new T.WebGLRenderer({alpha:true,antialias:true,powerPreference:'low-power'});
 renderer.setPixelRatio(Math.min(devicePixelRatio||1,1.6));renderer.outputColorSpace=T.SRGBColorSpace;
 host.append(renderer.domElement);
 const scene=new T.Scene(),camera=new T.PerspectiveCamera(33,1,.1,30);
 let unit;
 if(Number(tier)===10&&(type===2||type===3)){
   const {createT10Archer,createT10Cavalry}=await import('./meshy-t10-units.js?v=t10units2');
  unit=await (type===2?createT10Archer():createT10Cavalry());
  renderer.toneMapping=T.ACESFilmicToneMapping;renderer.toneMappingExposure=1.05;
 }else if(type===1&&Number(tier)===10){
  const {createDragonsteelKnight}=await import('./dragonsteel-knight.js');
  unit=await createDragonsteelKnight();
  renderer.toneMapping=T.ACESFilmicToneMapping;renderer.toneMappingExposure=1.05;
 }else if(type===1){
  const gltf=await loadKnight(),root=gltf.scene;
  let mesh;root.traverse(object=>{if(object.isSkinnedMesh)mesh=object;});
  if(!mesh)throw new Error('Conquer knight has no skinned mesh');
  if(!root.userData.trainingMaterial){
   root.traverse(object=>{if(!object.isMesh)return;const source=object.material;object.material=new T.MeshBasicMaterial({map:source.map,color:source.color?.clone()||new T.Color(0xffffff),side:source.side,transparent:source.transparent,opacity:source.opacity,alphaTest:source.alphaTest,toneMapped:false});object.castShadow=true;});
   root.userData.trainingMaterial=true;
  }
  mesh.skeleton.pose();root.position.set(0,0,0);root.rotation.set(0,0,0);root.updateMatrixWorld(true);
  const animate=createKnightShowcaseMotion(mesh.skeleton);
  unit={root,animate,dispose(){}};
 }else unit=buildTrainingUnit(type,tier);
 scene.add(unit.root);
 if(icon&&type===3)unit.root.getObjectByName('training-mount').visible=false;
 const bounds=new T.Box3().setFromObject(unit.root),size=bounds.getSize(new T.Vector3()),center=bounds.getCenter(new T.Vector3());
 const ambient=new T.HemisphereLight(0xfff6df,0x9a8261,3);scene.add(ambient);
 const sun=new T.DirectionalLight(0xffffff,3.5);sun.position.set(-3,5,5);scene.add(sun);
 const shadow=new T.Mesh(new T.CircleGeometry(.75,40),new T.MeshBasicMaterial({color:0x70472f,transparent:true,opacity:.1,depthWrite:false}));shadow.rotation.x=-Math.PI/2;shadow.scale.y=type===3?1.1:.62;shadow.position.y=-.018;scene.add(shadow);
 let destroyed=false,frame=0,last=0,drag=null,rotation=type===3?-.6:-.25,clock=0;
 const resize=()=>{const w=Math.max(1,host.clientWidth),h=Math.max(1,host.clientHeight);renderer.setSize(w,h,false);camera.aspect=w/h;const distance=Math.max(size.y/(2*Math.tan(T.MathUtils.degToRad(16.5))),size.x/(2*Math.tan(T.MathUtils.degToRad(16.5))*camera.aspect))*1.13;if(icon){const y=type===3?2.3:1.48;camera.position.set(0,y+.08,2.65);camera.lookAt(0,y,.15);}else{camera.position.set(0,center.y+.25,distance+center.z);camera.lookAt(0,center.y,center.z);}camera.updateProjectionMatrix();};
 const observer=new ResizeObserver(resize);observer.observe(host);resize();
 const down=e=>{if(e.button!==0)return;drag={x:e.clientX,rotation};host.setPointerCapture(e.pointerId);};
 const move=e=>{if(drag){rotation=drag.rotation+(e.clientX-drag.x)*.013;unit.root.rotation.y=rotation;}};
 const up=()=>drag=null;
 host.addEventListener('pointerdown',down);host.addEventListener('pointermove',move);host.addEventListener('pointerup',up);host.addEventListener('pointercancel',up);
 function tick(time){if(destroyed)return;if(!host.isConnected){destroy();return;}frame=requestAnimationFrame(tick);if(document.hidden||time-last<33||host.offsetWidth===0)return;clock+=Math.min(.06,(time-last)/1000);last=time;unit.animate(clock,reducedMotion());unit.root.rotation.y=rotation+(drag||reducedMotion()?0:Math.sin(clock*.5)*.06);renderer.render(scene,camera);}
 function destroy(){if(destroyed)return;destroyed=true;cancelAnimationFrame(frame);observer.disconnect();host.removeEventListener('pointerdown',down);host.removeEventListener('pointermove',move);host.removeEventListener('pointerup',up);host.removeEventListener('pointercancel',up);unit.dispose();shadow.geometry.dispose();shadow.material.dispose();renderer.dispose();renderer.forceContextLoss();renderer.domElement.remove();}
 frame=requestAnimationFrame(tick);return {destroy};
}
