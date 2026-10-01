'use strict';
// State-driven recommendations: no browser, network, database or game mutations.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const context={window:{},localStorage:{getItem:()=>null,setItem:()=>{}},document:{querySelector:()=>null}};
vm.createContext(context);vm.runInContext(fs.readFileSync(path.join(__dirname,'../assets/js/beginner-guide.js'),'utf8'),context);
const makeState=()=>({
 city:{player_id:1,world_id:1,coord_x:20,coord_y:20,food:1000,lumber:1000,stone:1000,gold:1000,action_points:200},world:{status:'running'},
 buildings:Object.fromEntries(['castle','wall','farm','lumber_camp','quarry','gold_mine','barrack','academy'].map(code=>[code,{level:1,cost:{food:100},requirements:{},item_requirements:[]}])) ,
 vip:{level:1},build_queue:[],plot_queue:[],troop_queue:[],research_queue:[],marches:[],troops:{50100101:100},army_limits:{march_slots:3,gather_march_slots:0},
 troop_defs:[{unlocked:true,training_building:'barrack',barrack_slot:1,training:{cost:{food:1},max_count:500}}],
 research_defs:[{code:'food',levels:[{level:1,resources:{food:100},requirements:[{type:'academy',level:1}]}]}],research:{},trained_total:0,charms:[],nodes:[],monsters:[],
 beginner_journey:{player_id:1,world_id:1,alliance_member:false,help_available:false,progress:{gather:false,monster:false,charm:false,alliance_help:false}}
});
let state=makeState();const opened=[];
context.window.ConquerWorld={locate:(...args)=>opened.push(JSON.parse(JSON.stringify(['locate',...args])))};
const guide=context.window.ConquerBeginnerGuide({base:'',esc:String,fmt:String,getState:()=>state,getKingdom:()=>null,getHost:()=>({querySelector:()=>null}),navigate:id=>opened.push(['nav',id]),openWorldTarget:(target,world)=>{opened.push(['nav','world']);opened.push(['locate',target.x,target.y,[target.kind],target.id]);},openDialog:()=>{},buildingDialog:id=>opened.push(['building',id]),buildingFunction:id=>opened.push(['training',id]),openCommunity:id=>opened.push(['community',id]),labels:{castle:'Castle',wall:'Wall',farm:'Farm'},buildingImage:()=>''});
const finishBasics=()=>{for(const b of Object.values(state.buildings))b.level=2;state.trained_total=20;state.research={food:1};state.beginner_journey.alliance_member=true;};
assert.equal(guide.nextGoal().id,'castle');
state.buildings.castle.requirements={wall:2};assert.equal(guide.nextGoal().action[2],'wall','a buildable prerequisite is selected directly');guide.openNextGoal();assert.deepEqual(opened.pop(),['building','wall']);
state.build_queue=[{building_code:'wall'}];assert.equal(guide.nextGoal().id,'training','busy builders do not block an independent training goal');
state.troop_queue=[{barrack_slot:1}];assert.equal(guide.nextGoal().id,'gather','busy builders and training allow gathering');
state.marches=[{march_type:9}];assert.equal(guide.nextGoal().id,'monster','a running gather is skipped');
state.marches.push({march_type:5});assert.equal(guide.nextGoal().id,'research','a running hunt is skipped');
state.research_queue=[{id:1}];assert.equal(guide.nextGoal().id,'alliance','research wait allows finding allies');
state.beginner_journey.alliance_member=true;assert.equal(guide.nextGoal().ready,false,'all blocked goals explain a wait');assert.equal(guide.nextGoal().action[1],'guide-tab','waiting hint opens the guide instead of repeating a blocked command');
state=makeState();finishBasics();state.troops={};assert.equal(guide.nextGoal().ready,false,'no troops means no recommended march');
state=makeState();finishBasics();state.beginner_journey.progress.gather=true;state.monsters=[{id:7,hp_current:10,definition:{action_point_cost:0}}];state.city.action_points=0;assert.equal(guide.nextGoal().id,'monster','free catalogued encounters remain available at zero AP');
state.monsters[0].definition.action_point_cost=30;assert.equal(guide.nextGoal().ready,false,'insufficient AP waits for regeneration');
state=makeState();finishBasics();state.beginner_journey.progress.gather=true;state.beginner_journey.progress.monster=true;state.charms=[{id:42,x:25,y:21,collectible:true}];assert.equal(guide.nextGoal().id,'charm');guide.openNextGoal();assert.deepEqual(opened.slice(-2),[['nav','world'],['locate',25,21,['charms'],42]],'charm navigation focuses the existing target without dispatching');
state.beginner_journey.progress.charm=true;state.beginner_journey.help_available=true;assert.equal(guide.nextGoal().id,'alliance_help');guide.openNextGoal();assert.deepEqual(opened.pop(),['community','help'],'help opens its exact community tab');
state.beginner_journey.progress.alliance_help=true;assert.equal(guide.nextGoal(),null,'completed journey creates no endless checklist');
state.beginner_journey.world_id=2;assert.equal(guide.nextGoal(),null,'foreign-world milestones never become actionable goals');
state=makeState();state.city.food=0;assert.equal(guide.nextGoal().id,'gather','unaffordable queues are skipped');
state=makeState();state.world.status='paused';assert.equal(guide.nextGoal().ready,false,'paused world does not recommend a new action');
state=makeState();finishBasics();state.beginner_journey.progress.gather=true;state.monsters=[{id:1,coord_x:21,coord_y:20,hp_current:10,definition:{level:10,action_point_cost:10}},{id:2,coord_x:24,coord_y:20,hp_current:10,definition:{level:1,action_point_cost:10}},{id:3,coord_x:20,coord_y:20,hp_current:10,monster_type:'rally',definition:{level:1,action_point_cost:0}}];guide.openNextGoal();assert.equal(opened.at(-1).at(-1),2,'new hunter is directed to a lower-level solo target before distance');
console.log('PASS beginner journey selection: prerequisites, independent queues, resources, march limits, AP, world scope, completed state and safe destination navigation.');
