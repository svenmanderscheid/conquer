// Shared, lazy-loaded work layers. No timers, DOM overlays or gameplay changes.
// Drawing coordinates are normalized to the existing 192px marker canvas.
const assets = {
 gold: ['gold-empty', 'gold-cart'], quarry: ['quarry-empty', 'quarry-load'],
 lumber: ['lumber-worker'], farm: ['farm-worker']
};
const cache = new Map();
const smooth = t => t*t*(3-2*t);
const wave = (t, period) => (1-Math.cos(t*Math.PI*2/period))/2;
export function workLayers(id, base, ready) {
 if (!assets[id]) return null;
 const key = base+':'+id;
 if (cache.has(key)) return cache.get(key);
 const entry = {ready:false}; cache.set(key, entry);
 Promise.all(assets[id].map(name=>new Promise((resolve,reject)=>{
  const image = new Image(); image.onload=()=>resolve(image); image.onerror=()=>reject(new Error('Work sprite unavailable: '+name));
  image.src=`${base}/assets/art/fantasy-village-v1/work-v1/${name}.png`;
 }))).then(images=>{entry.images=images;entry.ready=true;ready();}).catch(error=>console.warn(error));
 return entry;
}
function worker(g,image,id,t) {
 // Hold the preparation/recovery longer than the actual working stroke.
 const phase=(t%(id==='lumber'?2.4:3.2))/(id==='lumber'?2.4:3.2);
 const frame=phase<.32?0:phase<.43?1:phase<.67?2:3;
 const cell=image.width/2;
 // Hand-authored registration keeps the feet/stump fixed between painted poses.
 const anchors=id==='lumber'?[[485,623],[428,623],[485,543],[436,543]]:[[470,596],[488,596],[560,497],[532,498]];
 const [ax,ay]=anchors[frame],size=id==='lumber'?59:60;
 // Leave the central level badge clear in the live map, not only in previews.
 const [x,y]=id==='lumber'?[151,165]:[146,169];
 g.drawImage(image,(frame%2)*cell,Math.floor(frame/2)*cell,cell,cell,x-ax/640*size,y-ay/640*size,size,size);
}
export function drawWork(g,id,entry,image,t) {
 if (id==='crystal') {
  // Tint only the existing purple facets; cached once, never sampled per frame.
  let glow=cache.get(image);
  if(!glow){
   glow=document.createElement('canvas');glow.width=glow.height=192;
   const c=glow.getContext('2d');c.drawImage(image,0,0,192,192);
   const pixels=c.getImageData(0,0,192,192),p=pixels.data;
   for(let i=0;i<p.length;i+=4){
    const x=(i/4)%192,y=Math.floor(i/4/192);
    // Exclude the purple-black doorway: light belongs to crystal facets only.
    const facet=y<83||(x<57&&y>110)||(x<100&&y>129);
    const purple=facet&&p[i+2]>95&&p[i+2]>p[i+1]*1.16&&p[i]>p[i+1]*1.10;
    p[i+3]=purple?p[i+3]:0;p[i]=228;p[i+1]=181;p[i+2]=255;
   }
   c.putImageData(pixels,0,0);cache.set(image,glow);
  }
  g.save();g.globalCompositeOperation='screen';g.globalAlpha=.14+.52*wave(t,3.8);g.drawImage(glow,0,0);g.restore();return true;
 }
 if(!entry?.ready)return false;
 const [plate,part]=entry.images;
 if(id==='gold'){
  g.clearRect(0,0,192,192);g.drawImage(plate,0,0,192,192);
  // Track runs diagonally into the entrance; the cart shrinks and darkens inside.
  const depth=smooth(wave(t,6.4)),size=65*(1-depth*.35),x=76+depth*17,y=121-depth*20;
  g.save();g.globalAlpha=1-.94*smooth(Math.max(0,(depth-.68)/.32));
  g.drawImage(part,x-size/2,y-size/2,size,size);g.restore();
 } else if(id==='quarry'){
  g.clearRect(0,0,192,192);g.drawImage(plate,0,0,192,192);
  const lift=wave(t,5.8),x=93+Math.sin(t*1.5)*1.2,y=94-lift*19;
  // The painted hook is fixed at 93,74; shorten its rope as the load rises.
  g.save();g.strokeStyle='#71522e';g.lineWidth=1.7;g.beginPath();g.moveTo(93,74);g.lineTo(x,y);g.stroke();
  g.strokeStyle='#dcc087';g.lineWidth=.65;g.stroke();g.drawImage(part,x-30,y-12,60,60);g.restore();
 } else worker(g,plate,id,t);
 return true;
}
