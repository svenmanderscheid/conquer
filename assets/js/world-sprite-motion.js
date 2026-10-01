// Shared 2D mesh rig: moving wing/arm regions, stable torso and planted feet.
// Frames are cached once at map resolution, never rebuilt during animation.
const clamp=n=>Math.max(0,Math.min(1,n));
const smooth=n=>{const t=clamp(n);return t*t*(3-2*t);};
export function posePoint(id,x,y,phase){
 const wave=Math.sin(phase*Math.PI*2);
 if(id.includes('dragon')){
  const edge=id==='red-dragon'?.57:id==='green-dragon'?.66:.73;
  const w=smooth((.58-x)/.23)*smooth((edge-y)/.13);
  // Small shoulder-led sweep, no horizontal folding/squeezing of the membrane.
  const angle=.075*wave*w,dx=x-.56,dy=y-(edge-.06);
  return [.56+dx*Math.cos(angle)-dy*Math.sin(angle),edge-.06+dx*Math.sin(angle)+dy*Math.cos(angle)];
 }
 if(id==='magdar'){
  const limit=.49-.10*smooth((y-.66)/.13);
  const w=smooth((limit-x)/.08)*smooth((y-.43)/.14);
  const angle=(.20+.20*wave)*w,dx=x-.43,dy=y-.56;
  const px=.43+dx*Math.cos(angle)-dy*Math.sin(angle),py=.56+dx*Math.sin(angle)+dy*Math.cos(angle);
  const chest=smooth((y-.22)/.15)*smooth((.82-y)/.15)*smooth((x-.35)/.12)*smooth((.95-x)/.13);
  return [px+(x-.63)*.025*wave*chest,py-.012*wave*chest];
 }
 if(id==='castle'){
  // Flags wave above stationary roofs; poles, masonry and foundations stay fixed.
  let dy=0;
  for(const [left,right,top,bottom] of [[.395,.46,.035,.10],[.54,.615,.08,.14],[.79,.86,.21,.26]]){
   const w=smooth((x-left)/(right-left))*smooth((right+.02-x)/.02)*smooth((y-top)/.015)*smooth((bottom-y)/.015);
   dy+=Math.sin(phase*Math.PI*2+(x-left)*35)*.020*w;
  }
  return [x,y+dy];
 }
 return [x,y];
}
function triangle(ctx,image,src,dst,size){
 const [s0,s1,s2]=src,[d0,d1,d2]=dst;
 const sx1=s1[0]-s0[0],sy1=s1[1]-s0[1],sx2=s2[0]-s0[0],sy2=s2[1]-s0[1],det=sx1*sy2-sx2*sy1;
 const dx1=d1[0]-d0[0],dy1=d1[1]-d0[1],dx2=d2[0]-d0[0],dy2=d2[1]-d0[1];
 const a=(dx1*sy2-dx2*sy1)/det,c=(dx2*sx1-dx1*sx2)/det,b=(dy1*sy2-dy2*sy1)/det,d=(dy2*sx1-dy1*sx2)/det;
 ctx.save();ctx.beginPath();
 // Tiny overlap removes antialias hairlines at shared triangle edges.
 const cx=(d0[0]+d1[0]+d2[0])/3,cy=(d0[1]+d1[1]+d2[1])/3;
 dst.forEach((p,i)=>{const dx=p[0]-cx,dy=p[1]-cy,len=Math.hypot(dx,dy),ex=p[0]+dx/len*.35,ey=p[1]+dy/len*.35;i?ctx.lineTo(ex,ey):ctx.moveTo(ex,ey);});ctx.closePath();ctx.clip();
 ctx.transform(a,b,c,d,d0[0]-a*s0[0]-c*s0[1],d0[1]-b*s0[0]-d*s0[1]);ctx.drawImage(image,0,0,size,size);ctx.restore();
}
// Share one work queue: several newly visible sprites must not each spend a
// full rendering budget in the same turn. Timers also progress without idle
// callbacks or animation frames, including in background WebViews.
let motionBuildQueue=Promise.resolve();
async function nextBuildSlice(shouldPause){
 do {await new Promise(resolve=>setTimeout(resolve,shouldPause()?50:0));}while(shouldPause());
 return performance.now()+4;
}
export async function buildMotionFrames(image,id,{shouldPause=()=>false}={}){
 if(!id.includes('dragon')&&id!=='magdar'&&id!=='castle')return null;
 const task=motionBuildQueue.then(()=>buildFrames(image,id,shouldPause));
 motionBuildQueue=task.catch(()=>{});
 return task;
}
async function buildFrames(image,id,shouldPause){
 const size=256,steps=20,count=24,frames=[];
 let deadline=await nextBuildSlice(shouldPause),cells=0;
 for(let f=0;f<count;f++){
  const canvas=document.createElement('canvas');canvas.width=canvas.height=size;const g=canvas.getContext('2d');
  if(id==='castle'){
   g.drawImage(image,0,0,size,size);g.save();g.beginPath();
   for(const [x,y,w,h] of [[.39,.015,.095,.11],[.535,.055,.105,.11],[.785,.185,.10,.10]])g.rect(x*size,y*size,w*size,h*size);
   g.clip();g.clearRect(0,0,size,size);
  }
  for(let y=0;y<steps;y++)for(let x=0;x<steps;x++){
   const points=[[x/steps,y/steps],[(x+1)/steps,y/steps],[(x+1)/steps,(y+1)/steps],[x/steps,(y+1)/steps]];
   const src=points.map(p=>p.map(n=>n*size)),dst=points.map(p=>posePoint(id,...p,f/count).map(n=>n*size));
   for(const indices of [[0,1,2],[0,2,3]])triangle(g,image,indices.map(i=>src[i]),indices.map(i=>dst[i]),size);
   // Check within a frame, not after thousands of triangle draws. The cell
   // limit also bounds a slice when the browser coarsens performance.now().
   if(++cells>=256||performance.now()>=deadline){deadline=await nextBuildSlice(shouldPause);cells=0;}
  }
  if(id==='castle')g.restore();
  frames.push(canvas);
 }
 return frames;
}
export function motionFrame(item,time,reduced=false){if(!item.motionFrames||reduced)return item.image;const duration=item.id==='magdar'?2.5:item.id.includes('dragon')?2.8:2;return item.motionFrames[Math.floor((time%duration)/duration*item.motionFrames.length)];}

export function matchDragonSize(item){
 if(!item.id.includes('dragon'))return;
 const img=item.image,c=document.createElement('canvas');c.width=img.naturalWidth;c.height=img.naturalHeight;const g=c.getContext('2d');g.drawImage(img,0,0);
 const pixels=g.getImageData(0,0,c.width,c.height).data;let top=c.height,bottom=0;
 for(let y=0;y<c.height;y++)for(let x=0;x<c.width;x++)if(pixels[(y*c.width+x)*4+3]>32){top=Math.min(top,y);bottom=Math.max(bottom,y);}
 if(bottom<=top)return;
 // Equal bounding heights still make the wide green silhouette look larger.
 // Keep green unchanged; compensate the narrower red/gold silhouettes optically.
 const height=170*({'red-dragon':1.14,'gold-dragon':1.10}[item.id]||1);
 item.w=item.h=height*c.height/(bottom-top+1);item.groundPadding=(c.height-bottom-1)/c.height;item.visibleHeight=height;
}
