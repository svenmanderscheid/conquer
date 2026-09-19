import * as T from './vendor/three.module.js';
import {createCharacter} from './character-family.js?v=infantry2';
import {characterGLB} from './character-export.js';
const stage=document.querySelector('#stage'),renderer=new T.WebGLRenderer({antialias:true,alpha:true,powerPreference:'low-power'});
renderer.setPixelRatio(Math.min(devicePixelRatio,1.5));renderer.outputColorSpace=T.SRGBColorSpace;stage.append(renderer.domElement);
const scene=new T.Scene(),camera=new T.PerspectiveCamera(32,1,.1,30);
scene.add(new T.HemisphereLight(0xfff6df,0x9a8261,3));const light=new T.DirectionalLight(0xffffff,3.5);light.position.set(-3,5,5);scene.add(light);
let unit,rotation=-.3,drag=null,clock=0,last=0,frame;
const reduced=matchMedia('(prefers-reduced-motion: reduce)');document.querySelector('#pause').checked=reduced.matches;
reduced.addEventListener('change',e=>{document.querySelector('#pause').checked=e.matches;});
function load(){unit?.dispose();unit=createCharacter({role:document.querySelector('#role').value});unit.setAnimation(document.querySelector('#motion').value);scene.add(unit.root);document.querySelector('#metrics').textContent=`${unit.mesh.geometry.index.count/3} Dreiecke · ${unit.skeleton.bones.length} Knochen`;}
load();document.querySelector('#role').addEventListener('change',load);document.querySelector('#motion').addEventListener('change',e=>unit.setAnimation(e.target.value,document.querySelector('#pause').checked));
const resize=()=>{renderer.setSize(stage.clientWidth,stage.clientHeight,false);camera.aspect=stage.clientWidth/stage.clientHeight;camera.position.set(0,1.3,Math.max(4.2,2.1/camera.aspect));camera.lookAt(0,1,0);camera.updateProjectionMatrix();};const observer=new ResizeObserver(resize);observer.observe(stage);resize();
stage.addEventListener('pointerdown',e=>{if(e.button!==0)return;drag={x:e.clientX,rotation};stage.setPointerCapture(e.pointerId);});
stage.addEventListener('pointermove',e=>{if(drag)rotation=drag.rotation+(e.clientX-drag.x)*.012;});
for(const name of ['pointerup','pointercancel','lostpointercapture'])stage.addEventListener(name,()=>drag=null);
stage.addEventListener('keydown',e=>{if(['ArrowLeft','ArrowRight'].includes(e.key)){rotation+=e.key==='ArrowLeft'?-.15:.15;e.preventDefault();}});
document.querySelector('#reset').addEventListener('click',()=>rotation=-.3);
document.querySelector('#export').addEventListener('click',()=>{const url=URL.createObjectURL(new Blob([characterGLB(unit)],{type:'model/gltf-binary'})),a=document.createElement('a');a.href=url;a.download=unit.root.name+'.glb';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});
function render(time){frame=requestAnimationFrame(render);const dt=last?Math.min(.05,(time-last)/1000):0;last=time;if(document.hidden)return;if(!document.querySelector('#pause').checked)clock+=dt;unit.animate(clock);unit.root.rotation.y=rotation;renderer.render(scene,camera);}
frame=requestAnimationFrame(render);
addEventListener('pagehide',event=>{if(event.persisted)return;cancelAnimationFrame(frame);observer.disconnect();unit.dispose();renderer.dispose();});
// Read-only diagnostic/export surface for the production asset checks.
window.characterWorkshop={get character(){return unit;},exportGLB:()=>characterGLB(unit)};
