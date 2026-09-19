// Small transparent renders of the village's actual toon models.
// PNG is the still pose; WebP contains only local character/prop movement.
window.ConquerWorldEncounters=(()=>{
 const kinds=new Set(['farm','lumber','quarry','gold','crystal','orc','skeleton','golem','goblin']);
 const resources=new Set(['farm','lumber','quarry','gold','crystal']),biomes=new Set(['forest','ice','sand','lava']);
 const illustratedMonsters=new Set(['orc','skeleton','golem','goblin']);
 function image(base,kind,still=false,biome=null){
  if(!kinds.has(kind))return '';
  const region=resources.has(kind)&&biomes.has(biome)?'-'+biome:'';
  const edition=illustratedMonsters.has(kind)?'-v2':'';
  return `${base}/assets/art/map/life-${kind}${region}${edition}.${still?'png':'webp'}?v=${window.CONQUER_WORLD_LIFE_VERSION||'20260919-monsters-v2'}`;
 }
 return Object.freeze({image,supports:kind=>kinds.has(kind)});
})();
