// Camera movement is calculated on the ground plane, so vertical swipes do not
// lose half their travel in the isometric projection. No dependency on WebGL.
export function groundPan(dx,dy,{span,zoom,width,offset={x:14,y:18,z:22},touch=false}){
 const horizontal=Math.hypot(offset.x,offset.z),length=Math.hypot(horizontal,offset.y);
 const elevation=Math.max(.25,Math.abs(offset.y)/length),scale=span/Math.max(.001,zoom)/Math.max(1,width);
 const speed=touch?1.38:1.08,rightX=offset.z/horizontal,rightZ=-offset.x/horizontal;
 return {x:(-dx*rightX-dy*offset.x/horizontal/elevation)*scale*speed,z:(-dx*rightZ-dy*offset.z/horizontal/elevation)*scale*speed};
}
export function isTapGesture({distance,pointers,cancelled=false}){return !cancelled&&pointers===1&&distance<=7;}
