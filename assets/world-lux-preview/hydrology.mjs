// Shared geometry for drawing, dry-land spawns and the teleport footprint probe.
const CELL=32;
const distance2=(x,y,a,b)=>{const dx=b[0]-a[0],dy=b[1]-a[1],t=dx||dy?Math.max(0,Math.min(1,((x-a[0])*dx+(y-a[1])*dy)/(dx*dx+dy*dy))):0;return(x-a[0]-t*dx)**2+(y-a[1]-t*dy)**2;};
const pointBoxDistance2=(p,b)=>Math.max(b.left-p[0],0,p[0]-b.right)**2+Math.max(b.top-p[1],0,p[1]-b.bottom)**2;
function crossesBox(a,b,box){let lo=0,hi=1;for(const [axis,min,max] of [[0,box.left,box.right],[1,box.top,box.bottom]]){const d=b[axis]-a[axis];if(!d){if(a[axis]<min||a[axis]>max)return false;continue;}const t0=(min-a[axis])/d,t1=(max-a[axis])/d;lo=Math.max(lo,Math.min(t0,t1));hi=Math.min(hi,Math.max(t0,t1));if(lo>hi)return false;}return true;}
function segmentHitsBox(s,b){
 if(crossesBox(s.a,s.b,b))return true;
 const d=Math.min(pointBoxDistance2(s.a,b),pointBoxDistance2(s.b,b),...[[b.left,b.top],[b.right,b.top],[b.left,b.bottom],[b.right,b.bottom]].map(p=>distance2(...p,s.a,s.b)));
 return d<=s.radius*s.radius;
}
function lakeContains(l,x,y){
 if(x<l.bounds[0][0]||x>l.bounds[1][0]||y<l.bounds[0][1]||y>l.bounds[1][1])return false;
 let inside=false;for(const ring of l.rings)for(let i=0,j=ring.length-1;i<ring.length;j=i++){const a=ring[i],b=ring[j];if((a[1]>y)!==(b[1]>y)&&x<(b[0]-a[0])*(y-a[1])/(b[1]-a[1])+a[0])inside=!inside;}return inside;
}
export function createHydrology(data){
 const segments=new Map(),lakeCells=new Map(),rivers=data.rivers,lakes=data.lakes;
 const put=(map,item,left,top,right,bottom)=>{for(let y=Math.floor(top/CELL);y<=Math.floor(bottom/CELL);y++)for(let x=Math.floor(left/CELL);x<=Math.floor(right/CELL);x++){const key=`${x}:${y}`;if(!map.has(key))map.set(key,[]);map.get(key).push(item);}};
 for(const river of rivers)for(let i=1;i<river.points.length;i++){
  const a=river.points[i-1],b=river.points[i],radius=river.width/2,s={a,b,radius,river};
  put(segments,s,Math.min(a[0],b[0])-radius,Math.min(a[1],b[1])-radius,Math.max(a[0],b[0])+radius,Math.max(a[1],b[1])+radius);
 }
 for(const l of lakes)put(lakeCells,l,...l.bounds[0],...l.bounds[1]);
 const query=(map,b)=>{const out=new Set();for(let y=Math.floor(b.top/CELL);y<=Math.floor(b.bottom/CELL);y++)for(let x=Math.floor(b.left/CELL);x<=Math.floor(b.right/CELL);x++)for(const item of map.get(`${x}:${y}`)||[])out.add(item);return out;};
 const waterAt=(x,y)=>{
  const key=`${Math.floor(x/CELL)}:${Math.floor(y/CELL)}`;
  return (segments.get(key)||[]).some(s=>distance2(x,y,s.a,s.b)<=s.radius*s.radius)||(lakeCells.get(key)||[]).some(l=>lakeContains(l,x,y));
 };
 const intersectsRect=(left,top,right,bottom)=>{
  const b={left,top,right,bottom};
  for(const s of query(segments,b))if(segmentHitsBox(s,b))return true;
  for(const l of query(lakeCells,b)){
   if([[left,top],[right,top],[left,bottom],[right,bottom]].some(p=>lakeContains(l,...p)))return true;
   for(const ring of l.rings)for(let i=0;i<ring.length;i++)if(crossesBox(ring[i],ring[(i+1)%ring.length],b))return true;
  }
  return false;
 };
 const visible=b=>({rivers:[...new Set([...query(segments,b)].map(s=>s.river))],lakes:[...query(lakeCells,b)]});
 return {rivers,lakes,visits:data.visits,waterAt,intersectsRect,visible};
}
