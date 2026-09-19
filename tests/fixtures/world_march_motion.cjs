'use strict';
const fs=require('fs'),path=require('path');
module.exports=(root,base='')=>`<!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Animierte Marschskins · Conquer</title>
${['world-atlas','map-overlay','castle-skins','village-theme'].map(s=>`<link rel="stylesheet" href="${base}/assets/css/${s}.css?v=${Math.floor(fs.statSync(path.join(root,'assets/css',s+'.css')).mtimeMs)}">`).join('')}
<style>body{margin:0;display:flex;flex-direction:column;height:100dvh;background:var(--ui-paper);color:var(--ui-ink);font:13px var(--ui-font)}header{display:flex;flex-wrap:wrap;gap:8px;align-items:center;padding:8px 12px;border-bottom:2px solid var(--ui-frame)}header strong{margin-right:auto}header label{display:flex;gap:4px;align-items:center}header select,header button{font:inherit;min-height:40px;border:1px solid var(--ui-line);border-radius:9px;background:var(--ui-card);color:var(--ui-ink);padding:5px}#map{flex:1;min-height:0}.atlas-shell{position:relative!important;inset:auto!important;height:100%!important;min-height:0!important}header small{color:var(--ui-muted)}@media(max-width:600px){header{gap:5px;padding:5px 8px}header strong{font-size:12px}header small{display:none}header select{max-width:138px}}</style>
<body class="mobile-game playfield-mode world-mode"><header><strong>Marsch & Ankunft</strong><small>Beispielwelt · Marsch antippen, um ihm zu folgen</small><label>Skin <select id="skin" aria-label="Marschskin"></select></label><label>Region <select id="region" aria-label="Region"><option value="forest">Wald</option><option value="ice">Eis</option><option value="sand">Sand</option><option value="lava">Lava</option></select></label><button id="replay">Angriff zeigen</button><button id="arrival-preview">Ankunft ansehen</button></header><main id="map"></main>
<script>window.ConquerTerrainData=${fs.readFileSync(path.join(root,'data/world_terrain.json'),'utf8')};</script>
${['castle-skins','march-skins','world-encounters','world-landscape','world-march-hud','march-effects','world-map'].map(s=>`<script src="${base}/assets/js/${s}.js?v=${Math.floor(fs.statSync(path.join(root,'assets/js',s+'.js')).mtimeMs)}"></script>`).join('')}
<script>
window.clockShift=0;window.demoAuto=true;window.options={host:document.querySelector('#map'),base:${JSON.stringify(base)},esc:s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),now:()=>Date.now()+clockShift,monsterArt:m=>m.definition.art,state:{}};
let serial=0;const skin=document.querySelector('#skin'),region=document.querySelector('#region');
options.onMarchRecall=async id=>{const m=options.state.marches.find(m=>m.id===id),now=options.now();m.return_time=new Date(now+Math.max(2000,now-Date.parse(m.departure_time))).toISOString();m.state='returning';ConquerWorld.render(options);};
skin.innerHTML=ConquerMarchSkins.entries.map(s=>'<option value="'+s.id+'">'+s.name+'</option>').join('');skin.value='phoenix';
window.startMarch=(kind=5,duration=12000)=>{
 const [x,y,boss]=({forest:[64,64,'grumwald'],ice:[192,64,'frostgrimm'],sand:[64,192,'sandmaul'],lava:[192,192,'glutramm']})[region.value],now=options.now();
 options.state={city:{id:1,world_id:1,coord_x:x-3,coord_y:y,castle_level:5,city_skin:skin.value,name:'Deine Stadt'},players:[],nodes:[],monsters:[{id:1,coord_x:x+2,coord_y:y,hp_current:1000,definition:{name:boss,art:'monsters/'+boss,type:'rally',biome:region.value,footprint:2,level:1,hp:1000}}],troop_defs:[{code:1,type:1}],marches:[{id:++serial,march_type:kind,target_type:3,target_x:x+2,target_y:y,troops:{1:250},state:'marching',march_skin:skin.value,departure_time:new Date(now).toISOString(),arrival_time:new Date(now+duration).toISOString(),return_time:new Date(now+duration*2+1700).toISOString()}]};
 ConquerWorld.render(options);ConquerWorld.focus(x+.5,y+.5);
};
skin.onchange=region.onchange=()=>startMarch();document.querySelector('#replay').onclick=()=>startMarch();document.querySelector('#arrival-preview').onclick=()=>startMarch(5,2400);startMarch();
setInterval(()=>{if(!demoAuto)return;const m=options.state.marches[0];if(!m)return;const now=options.now();if(now>Date.parse(m.return_time)+1000)startMarch();else if(now>Date.parse(m.arrival_time)+1700&&m.state==='marching'){m.state='returning';ConquerWorld.render(options);}},200);
</script></body></html>`;
