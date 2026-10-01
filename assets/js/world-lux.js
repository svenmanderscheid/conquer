/* The live Luxembourg map uses the same exported field grid as the server.
 * No preview cities, resources, or owners are generated here. */
window.ConquerLuxWorld=(()=>{
  'use strict';
  const cache=new Map();
  const shape=rings=>{const p=new Path2D();for(const ring of rings||[]){ring.forEach(([x,y],i)=>i?p.lineTo(x,y):p.moveTo(x,y));p.closePath();}return p;};
  async function load(base,profile){
    const key=`${base}:${profile.version}:${profile.geography_url}:${profile.hydrology_url}`;
    if(cache.has(key))return cache.get(key);
    const task=(async()=>{
      const local=url=>`${base}/${String(url).replace(/^\//,'')}`;
      const [geo,water,{createHydrology}]=await Promise.all([
        fetch(local(profile.geography_url),{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('Die Gemeindegrenzen konnten nicht geladen werden.');return r.json();}),
        fetch(local(profile.hydrology_url),{credentials:'same-origin'}).then(r=>{if(!r.ok)throw new Error('Die Gewässer konnten nicht geladen werden.');return r.json();}),
        import(`${base}/assets/world-lux-preview/hydrology.mjs?v=1`)
      ]);
      const grid=geo.grid,rows=grid.rows.map(r=>{const values=new Uint16Array(grid.width);let col=0;for(let i=0;i<r.length;i+=2){values.fill(r[i],col,col+r[i+1]);col+=r[i+1];}if(col!==grid.width)throw new Error('Die Kartendefinition ist unvollständig.');return values;});
      const communes=geo.communes.map(r=>({...r,shape:shape(r.rings)})),cantons=geo.cantons.map(r=>({...r,shape:shape(r.rings)})),land=new Path2D();cantons.forEach(c=>land.addPath(c.shape));
      const hydro=createHydrology(water);for(const river of hydro.rivers){river.shape=new Path2D();river.points.forEach(([x,y],i)=>i?river.shape.lineTo(x,y):river.shape.moveTo(x,y));}for(const lake of hydro.lakes)lake.shape=shape(lake.rings);
      const at=(x,y)=>{const index=rows[Math.floor(y/grid.step)]?.[Math.floor(x/grid.step)]||0;return index?communes[index-1]:null;};
      const validFootprint=(x,y,size)=>{const offset=size%2===0?.5:0,left=x+offset-size/2,top=y+offset-size/2,right=left+size,bottom=top+size,region=at(x,y);if(!region)return 'Der Bauplatz liegt außerhalb der Landesfläche.';for(let yy=Math.floor(top/grid.step);yy<=Math.floor((bottom-.000001)/grid.step);yy++)for(let xx=Math.floor(left/grid.step);xx<=Math.floor((right-.000001)/grid.step);xx++){const id=rows[yy]?.[xx]||0;if(!id)return 'Die vollständige Fläche muss auf dem Kontinent liegen.';if(communes[id-1].canton!==region.canton)return 'Die vollständige Fläche muss im selben Kanton liegen.';}if(hydro.intersectsRect(left,top,right,bottom))return 'Der Bauplatz würde Wasser überdecken.';return null;};
      const visible=(r,b)=>r.bounds[1][0]>=b.left&&r.bounds[0][0]<=b.right&&r.bounds[1][1]>=b.top&&r.bounds[0][1]<=b.bottom;
      const ownership=(targets,id)=>targets.find(t=>t.id===id);
      function draw(c,project,s,bounds,{painted,targets=[],allianceId=0,gridLines=false,overview=false,occupied=()=>false}={}){
        const [ox,oy]=project(0,0);c.save();c.fillStyle='#dbe3d4';const [a,b]=project(bounds.left,bounds.top),[d,e]=project(bounds.right,bounds.bottom);c.fillRect(a,b,d-a,e-b);
        c.save();c.translate(ox,oy);c.scale(s,s);c.clip(land);c.fillStyle='#b9c985';c.fillRect(0,0,profile.width,profile.height);c.restore();
        if(!overview&&painted){c.save();c.translate(ox,oy);c.scale(s,s);c.clip(land);c.scale(1/s,1/s);c.translate(-ox,-oy);painted.ground(c,project,s,bounds);c.restore();}
        c.save();c.translate(ox,oy);c.scale(s,s);c.lineJoin='round';
        for(const region of communes){if(!visible(region,bounds))continue;const target=ownership(targets,`commune:${region.id}`),owner=Number(target?.owner_alliance_id||0);if(owner){c.fillStyle=owner===Number(allianceId)?'#2a72c91c':'#b43c3418';c.fill(region.shape,'evenodd');}c.lineWidth=overview?.65/s:1.4/s;c.strokeStyle='#75608088';c.setLineDash(overview?[]:[4/s,4/s]);c.stroke(region.shape);}
        c.setLineDash([]);for(const region of cantons){if(!visible(region,bounds))continue;c.lineWidth=overview?1.15/s:3/s;c.strokeStyle='#756080';c.stroke(region.shape);c.lineWidth=overview?.45/s:1/s;c.strokeStyle='#f8edce';c.stroke(region.shape);}
        const waters=overview?hydro:hydro.visible(bounds);c.lineCap='round';for(const river of waters.rivers){c.strokeStyle='#edd09a';c.lineWidth=river.width+.2;c.stroke(river.shape);c.strokeStyle='#3793bd';c.lineWidth=overview?Math.max(.8/s,river.width):river.width;c.stroke(river.shape);if(!overview){c.strokeStyle='#72bfd1';c.lineWidth=river.width*.78;c.stroke(river.shape);}}for(const lake of waters.lakes){c.fillStyle='#72bfd1';c.strokeStyle='#edd09a';c.lineWidth=.3;c.stroke(lake.shape);c.fill(lake.shape,'evenodd');}
        if(gridLines&&!overview){c.clip(land);c.beginPath();for(let x=Math.floor(bounds.left);x<=bounds.right;x++){c.moveTo(x-.5,bounds.top);c.lineTo(x-.5,bounds.bottom);}for(let y=Math.floor(bounds.top);y<=bounds.bottom;y++){c.moveTo(bounds.left,y-.5);c.lineTo(bounds.right,y-.5);}c.strokeStyle='#70472f26';c.lineWidth=1/s;c.stroke();}c.restore();
        if(!overview&&painted)painted.scenery(c,project,s,{...bounds,worldWidth:profile.width,worldHeight:profile.height},(x,y,pad)=>!at(x,y)||hydro.waterAt(x,y)||occupied(x,y,pad));
        c.restore();
      }
      function overview(c,width,height,targets,allianceId,viewport,owners){
        const s=Math.min(width/profile.width,height/profile.height),ox=(width-profile.width*s)/2,oy=(height-profile.height*s)/2,project=(x,y)=>[ox+x*s,oy+y*s];
        c.clearRect(0,0,width,height);draw(c,project,s,{left:0,top:0,right:profile.width,bottom:profile.height},{targets:owners||targets.filter(t=>t.kind==='territory').map(t=>t.data),allianceId,overview:true});
        for(const t of targets){if(!['home','players','territory'].includes(t.kind))continue;const [x,y]=project(t.x,t.y),r=t.kind==='home'?3:t.data.kind==='crown'?2.7:1.4;c.fillStyle=t.kind==='home'?'#ed8a31':t.kind==='players'?'#443549':Number(t.data.owner_alliance_id)===Number(allianceId)&&allianceId?'#2a72c9':'#756080';c.beginPath();c.arc(x,y,r,0,Math.PI*2);c.fill();}
        if(viewport){const [x,y]=project(viewport.left,viewport.top);c.strokeStyle='#85342b';c.lineWidth=1.5;c.strokeRect(x,y,(viewport.right-viewport.left)*s,(viewport.bottom-viewport.top)*s);}
        return {s,ox,oy};
      }
      return {at,validFootprint,draw,overview,cantons,communes,waterAt:hydro.waterAt,source:geo.source};
    })();cache.set(key,task);task.catch(()=>cache.delete(key));return task;
  }
  return {load};
})();
