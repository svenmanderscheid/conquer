/* Device-local graphics preferences; never affects authoritative game state. */
'use strict';
window.ConquerGraphicsQuality=(()=>{
  const KEY='conquer:graphics-quality:v1',profiles=new Set(['auto','light','normal','high']);
  const read=()=>{try{const value=localStorage.getItem(KEY);return profiles.has(value)?value:'auto';}catch{return 'auto';}};
  const automatic=()=>{
    const memory=Number(navigator.deviceMemory)||0,cores=Number(navigator.hardwareConcurrency)||0;
    const coarse=matchMedia('(pointer: coarse)').matches,small=Math.min(innerWidth,innerHeight)<700;
    if((memory&&memory<=4)||(cores&&cores<=4)||(coarse&&small))return 'light';
    return 'normal';
  };
  let requested=read(),forced=null,effective=requested==='auto'?automatic():requested;
  function apply(){
    effective=requested==='auto'?(forced||automatic()):requested;
    document.body?.setAttribute('data-graphics-quality',effective);
    window.dispatchEvent(new CustomEvent('conquer-graphics-quality',{detail:{requested,effective}}));
  }
  function set(value){requested=profiles.has(value)?value:'auto';forced=null;try{localStorage.setItem(KEY,requested);}catch{}apply();return state();}
  function degrade(){if(requested!=='auto'||effective==='light')return false;forced='light';apply();return true;}
  function state(){return {requested,effective};}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',apply,{once:true});else apply();
  return {set,state,apply,degrade};
})();
