// Pure, deterministic layout model for a read-only capacity illustration.
export const FIELD_WIDTH=768, FIELD_HEIGHT=1100;
const SCALE=Math.min(FIELD_WIDTH/1326.82,FIELD_HEIGHT/1900), ORIGIN=[136.59,100];
export const toField=([x,y])=>[(x-ORIGIN[0])*SCALE,(y-ORIGIN[1])*SCALE];
export const fromField=([x,y])=>[x/SCALE+ORIGIN[0],y/SCALE+ORIGIN[1]];
export const hash=(x,y,s=0)=>{let h=Math.imul(x+19,374761393)^Math.imul(y+71,668265263)^s;h=Math.imul(h^(h>>>13),1274126177);return(h^(h>>>16))>>>0;};
export function parseRegions(features){return features.map(f=>f.rings?{...f}:{...f,point:toField(f.point),bounds:f.bounds.map(toField),rings:f.path.split('M').slice(1).map(r=>r.replace(/Z$/,'').split('L').map(p=>toField(p.split(',').map(Number))))});}
export function contains(region,x,y){if(x<region.bounds[0][0]||x>region.bounds[1][0]||y<region.bounds[0][1]||y>region.bounds[1][1])return false;let inside=false;for(const ring of region.rings){for(let i=0,j=ring.length-1;i<ring.length;j=i++){const [xi,yi]=ring[i],[xj,yj]=ring[j];if((yi>y)!==(yj>y)&&x<(xj-xi)*(y-yi)/(yj-yi)+xi)inside=!inside;}}return inside;}
export function buildPopulation(cantons,communes,mode,hydrology){
 const homePoint=communes.find(c=>c.name==='Mersch').point,home={x:Math.round(homePoint[0]),y:Math.round(homePoint[1])};
 const inLand=(x,y)=>cantons.some(c=>contains(c,x,y));
 const items=[],cells=new Map();
 const nearby=(x,y)=>{const out=[];for(let a=Math.floor(x/12)-1;a<=Math.floor(x/12)+1;a++)for(let b=Math.floor(y/12)-1;b<=Math.floor(y/12)+1;b++)out.push(...(cells.get(`${a}:${b}`)||[]));return out;};
 const blocked=(x,y,size,gap=2)=>nearby(x,y).some(p=>Math.abs(x-p.x)<(size+p.size)/2+gap&&Math.abs(y-p.y)<(size+p.size)/2+gap);
 const fits=(x,y,size,gap=2)=>{
  const r=size/2;for(const dx of [-r,0,r])for(const dy of [-r,0,r])if(!inLand(x+dx,y+dy))return false;
  if(hydrology.intersectsRect(x-r,y-r,x+r,y+r))return false;
  return !blocked(x,y,size,gap);
 };
 const add=item=>{items.push(item);const key=`${Math.floor(item.x/12)}:${Math.floor(item.y/12)}`;if(!cells.has(key))cells.set(key,[]);cells.get(key).push(item);};
 const mersch=communes.find(c=>c.name==='Mersch');
 const drySpot=(x,y,size)=>{
  for(let r=0;r<=40;r++)for(let dy=-r;dy<=r;dy++)for(let dx=-r;dx<=r;dx++){
   if(Math.max(Math.abs(dx),Math.abs(dy))!==r)continue;
   const px=Math.round(x)+dx+(size%2?.5:0),py=Math.round(y)+dy+(size%2?.5:0);
   if(contains(mersch,px,py)&&fits(px,py,size))return {x:px,y:py};
  }
  throw new Error('No dry location near Mersch');
 };
 Object.assign(home,drySpot(home.x,home.y,4));
 add({id:'city-1',type:'city',name:'Meine Stadt',x:home.x,y:home.y,size:4,level:17,art:'water',alliance:'Waldwacht',self:true});
 for(const [id,type,name,art,dx,dy,size] of [['alliance','alliance','Halle der Waldwacht','alliance',-7,-2,5],['dungeon','dungeon','Hallen der drei Flüsse','shrine',14,-1,6]]){
  const spot=drySpot(home.x+dx,home.y+dy,size);
  add({id,type,name,art,...spot,size,level:1});
 }
 const count=mode==='live'?1000:20;
 // Sample the entire land surface, rather than circles around commune centres.
 // A slightly irregular lattice gives deterministic candidates without neat rows.
 const candidates=[];
 for(let gy=4;gy<FIELD_HEIGHT-4;gy+=8)for(let gx=4;gx<FIELD_WIDTH-4;gx+=8){
  const x=gx+hash(gx,gy,91)%5-2,y=gy+hash(gx,gy,427)%5-2;
  if(!fits(x,y,4))continue;
  const commune=communes.find(c=>contains(c,x,y));if(!commune)continue;
  const canton=cantons.find(c=>c.id===commune.canton);
  if([-1.999,0,1.999].some(dx=>[-1.999,0,1.999].some(dy=>!contains(canton,x+dx,y+dy))))continue;
  candidates.push({x,y,commune,distance:(x-home.x)**2+(y-home.y)**2});
 }
 const homeCommune=communes.find(c=>contains(c,home.x,home.y));
 const occupiedCantons=new Set([homeCommune.canton]),occupiedCommunes=new Set([homeCommune.id]);
 const names=['Eichenwacht','Talheim','Felsengrund','Morgenrot','Silberhain','Quellenfels','Dornwacht','Lindenhof'];
 for(let i=1;i<count;i++){
  // Alpha reaches every canton. Live keeps these 20 towns, reaches every
  // commune, then fills the largest remaining gaps throughout the country.
  const missingCanton=cantons.find(c=>!occupiedCantons.has(c.id));
  const missingCommune=i>=20?communes.find(c=>!occupiedCommunes.has(c.id)):null;
  let best=null;
  for(const p of candidates){
   if(p.distance<0||(missingCanton&&p.commune.canton!==missingCanton.id)||(!missingCanton&&missingCommune&&p.commune.id!==missingCommune.id))continue;
   if(best&&p.distance<=best.distance)continue;
   if(blocked(p.x,p.y,4)){p.distance=-1;continue;}
   best=p;
  }
  if(!best)throw new Error(`No space for example city ${i+1}`);
  const {x,y,commune}=best;
  add({id:`city-${i+1}`,type:'city',name:`${names[(i-1)%names.length]} ${i+1}`,x,y,size:4,level:1+hash(i,6)%25,art:i%5===1?'fire':'castle',alliance:['Waldwacht','Moselbund','Minettwacht'][i%3]});
  occupiedCantons.add(commune.canton);occupiedCommunes.add(commune.id);best.distance=-1;
  for(const p of candidates)if(p.distance>=0)p.distance=Math.min(p.distance,(p.x-x)**2+(p.y-y)**2);
 }
 return {items,cities:items.filter(p=>p.type==='city'),home,inLand,nearby,fits,waterAt:hydrology.waterAt,waterIntersects:hydrology.intersectsRect};
}

// The renderer and the placement probe use the same procedural encounters.
export function resourceSite(cx,cy){
 const kinds=['farm','lumber','quarry','gold','crystal','orc','golem'],h=hash(cx,cy,329),key=kinds[(h>>>10)%kinds.length];
 return {id:`site-${cx}-${cy}`,x:cx*9+2.5+h%5,y:cy*9+2.5+(h>>>5)%5,size:1,level:1+h%5,art:key,type:['orc','golem'].includes(key)?'monster':'resource',name:({farm:'Kornfelder',lumber:'Waldlager',quarry:'Steinbruch',gold:'Goldader',crystal:'Kristallhain',orc:'Dornensippe',golem:'Erzwächter'})[key]};
}
export function checkCityPlacement(model,cantons,communes,x,y){
 x=Math.round(x);y=Math.round(y);
 const canton=cantons.find(c=>contains(c,x,y)),commune=communes.find(c=>contains(c,x,y));
 const result={x,y,canton:canton?.name,commune:commune?.name};
 let border=false;
 // Sample every occupied field, including its corners just inside the footprint.
 for(let dx=-2;dx<2;dx++)for(let dy=-2;dy<2;dy++)for(const [a,b] of [[.001,.001],[.999,.001],[.001,.999],[.999,.999],[.5,.5]]){
  const px=x+dx+a,py=y+dy+b;
  if(!model.inLand(px,py))return {...result,ok:false,reason:'Außerhalb der bebaubaren Landesfläche.'};
  if(!canton||!contains(canton,px,py))border=true;
 }
 if(model.waterIntersects(x-2,y-2,x+2,y+2))return {...result,ok:false,reason:'Die Stadtfläche würde Wasser überdecken.'};
 if(border)return {...result,ok:false,reason:'Die 4 × 4-Fläche überschreitet eine Kantonsgrenze.'};
 const overlaps=p=>Math.abs(x-p.x)<(4+p.size)/2&&Math.abs(y-p.y)<(4+p.size)/2;
 const occupied=model.nearby(x,y).find(overlaps);
 if(occupied)return {...result,ok:false,reason:`Hier steht bereits ${occupied.name}.`};
 for(let cy=Math.floor((y-3)/9)-1;cy<=Math.ceil((y+3)/9);cy++)for(let cx=Math.floor((x-3)/9)-1;cx<=Math.ceil((x+3)/9);cx++){
  const site=resourceSite(cx,cy);
  if(overlaps(site)&&model.fits(site.x,site.y,1,2))return {...result,ok:false,reason:`Hier liegt ${site.name}.`};
 }
 return {...result,ok:true,reason:'Alle 16 Felder sind frei, trocken und im selben Kanton.'};
}
export function findCityPlacement(model,cantons,communes,x,y){
 for(let radius=0;radius<=24;radius++)for(let dy=-radius;dy<=radius;dy++)for(let dx=-radius;dx<=radius;dx++){
  if(Math.max(Math.abs(dx),Math.abs(dy))!==radius)continue;
  const p=checkCityPlacement(model,cantons,communes,x+dx,y+dy);if(p.ok)return p;
 }
 return null;
}
