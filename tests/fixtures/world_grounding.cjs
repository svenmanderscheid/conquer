'use strict';
const worldLife=require('./world_life.cjs');

// Same five workplaces and castle in every climate, without player API calls.
module.exports=function worldGroundingFixture(root,base=''){
 const nodes=[],players=[],monsters=[],regions=[['forest',64,64,'grumwald'],['ice',192,64,'frostgrimm'],['sand',64,192,'sandmaul'],['lava',192,192,'glutramm']];
 for(const [region,x,y,boss]of regions){
  const n=nodes.length;
  for(const [i,dx,dy]of [[1,-6,-2],[2,5,-1],[3,-4,5],[4,3,5],[5,0,-5]])nodes.push({id:n+i,coord_x:x+dx,coord_y:y+dy,object_type:i,level:2,resource_amount:12000,resource_max:12000});
  if(region!=='forest')players.push({id:players.length+2,coord_x:x,coord_y:y,castle_level:4,city_skin:'eclipse',display_name:'Siedlung'});
  monsters.push({id:monsters.length+1,coord_x:x-7,coord_y:y-5,hp_current:1000,definition:{name:boss,art:'monsters/'+boss,type:'rally',biome:region,footprint:2,level:1,hp:1000}});
 }
 const state={city:{id:1,coord_x:64,coord_y:64,castle_level:4,city_skin:'eclipse',name:'Deine Stadt'},players,nodes,monsters,marches:[],congress:{id:1,coord_x:128,coord_y:128,name:'Kongress',state:'neutral',can_attack:true}};
 return worldLife(root,base).replace(/window.fixtureState=.*?;window.fixtureActions=/,`window.fixtureState=${JSON.stringify(state)};window.fixtureActions=`).replace('Bewegte Vorschau ·','Regionale Böden ·');
};
