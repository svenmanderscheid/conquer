'use strict';
// Component contracts only: no browser, account, network or clipboard mutations.
const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert');
const root=path.resolve(__dirname,'..');
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const state={player:{id:1,name:'Wächter'},city:{id:11,player_id:1,coord_x:80,coord_y:70,castle_level:3},players:[{id:2,coord_x:91,coord_y:74,castle_level:4,display_name:'Fremde Burg'}]};
let html='',actions=[],marches=[],selectedText=false,rally={id:9,leader_player_id:1,leader_name:'Anführer',target_player_id:2,target_name:'Fremde Burg',target_x:91,target_y:74,status:'gathering',launch_at:'2030-01-01T00:00:00Z',troops:{50100101:10}},participants=[{player_id:3,username:'Verbündeter',troops:{50200101:4}}];
const sandbox={window:{},innerHeight:844,Number,String,Object,Array,Math,Date,console,navigator:{},document:{body:{classList:{contains:()=>false}},querySelector:()=>({select(){selectedText=true;}})}};
vm.createContext(sandbox);
for(const file of ['castle-skins.js','march-skins.js','village-menu.js','rally-panel.js'])vm.runInContext(fs.readFileSync(path.join(root,'assets/js',file),'utf8'),sandbox);
const ctx={base:'/conquer',esc,fmt:String,date:v=>new Date(v),duration:s=>String(s),getState:()=>state,getKingdom:()=>({profile:{display_name:'Wächter',city_skin:'ironkeep'}}),openDialog:value=>{html=value;},navigate(){},toast(){},action:async(...args)=>{actions.push(args);return {};},marchPanel:{open:(...args)=>marches.push(args)},api:async route=>route==='rally/list'?{rallies:[rally]}:{rally,participants}};
function check(label,fn){fn();console.log('PASS '+label);}
async function settle(){await Promise.resolve();await Promise.resolve();}
async function main(){
 const village=sandbox.window.ConquerVillage(ctx);
 check('own village exposes profile, skins and exact coordinates',()=>{village.open({kind:'home'});assert(html.includes('data-id="profile"'));assert(html.includes('data-action="city-skins"'));assert(html.includes('data-x="80" data-y="70"'));assert(!html.includes('village-attack'));});
 check('foreign village exposes profile, solo and rally for the correct player',()=>{village.open({kind:'players',id:2});assert(html.includes('data-action="public-profile" data-id="2"'));assert(html.includes('data-action="village-attack" data-id="2"'));assert(html.includes('data-action="village-rally" data-id="2"'));assert(!html.includes('data-action="city-skins"'));});
 check('both attack choices use the shared troop composer',()=>{village.onClick('village-attack',{dataset:{id:'2'}});village.onClick('village-rally',{dataset:{id:'2'}});assert.deepStrictEqual(marches.map(a=>Array.from(a)),[['2','players'],['2','rally']]);});
 check('skin selection displays the saved skin and submits only its identifier',()=>{village.onClick('city-skins',{dataset:{}});assert(html.includes('data-id="ironkeep" aria-pressed="true"'));village.onClick('city-skin-save',{dataset:{id:'rosehall'}});assert.strictEqual(actions[0][0],'kingdom/action');assert.strictEqual(actions[0][1].action,'skin.save');assert.strictEqual(actions[0][1].city_skin,'rosehall');});
 check('insecure LAN share falls back to selectable coordinates',()=>{village.onClick('share-coordinates',{dataset:{x:'91',y:'74'}});assert(html.includes('Conquer · Welt 1 · X 91 / Y 74'));assert(selectedText);});
 check('invalid coordinates never open a share destination',()=>{html='unchanged';village.onClick('share-coordinates',{dataset:{x:'-1',y:'74'}});assert.strictEqual(html,'unchanged');});
 const rallies=sandbox.window.ConquerRallies(ctx);
 await rallies.list();check('single-page rally list disables both paging controls',()=>{assert((html.match(/disabled/g)||[]).length===2);assert(html.includes('data-action="rally-detail" data-id="9"'));});
 rallies.onClick('rally-detail',{dataset:{id:'9'}});await settle();
 check('leader sees own reserved troops, participant troops and management controls',()=>{assert(html.includes('Anführer'));assert(html.includes('10 Truppen'));assert(html.includes('4 Truppen'));assert(html.includes('data-action="rally-launch"'));assert(html.includes('data-action="rally-cancel"'));assert(!html.includes('data-action="rally-join"'));});
 state.player.id=4;rallies.onClick('rally-detail',{dataset:{id:'9'}});await settle();
 check('eligible nonparticipant sees Join, never leader controls',()=>{assert(html.includes('data-action="rally-join"'));assert(!html.includes('data-action="rally-launch"'));assert(!html.includes('data-action="rally-cancel"'));});
 check('joining passes exact rally id and target to composer',()=>{rallies.onClick('rally-join',{dataset:{id:'9'}});const args=marches.at(-1);assert.strictEqual(args[0],2);assert.strictEqual(args[1],'rally-join');assert.strictEqual(args[2].rally_id,9);assert.strictEqual(args[2].target.coord_x,91);});
 state.player.id=3;rallies.onClick('rally-detail',{dataset:{id:'9'}});await settle();
 check('existing participants cannot join twice through the UI',()=>assert(!html.includes('data-action="rally-join"')));
 rally={...rally,status:'marching'};state.player.id=1;rallies.onClick('rally-detail',{dataset:{id:'9'}});await settle();
 check('departed rally exposes no gather-phase actions',()=>{assert(!html.includes('data-action="rally-join"'));assert(!html.includes('data-action="rally-launch"'));assert(!html.includes('data-action="rally-cancel"'));});
 console.log('ALL VILLAGE MENU CONTRACTS PASSED');
}
main().catch(e=>{console.error(e);process.exitCode=1;});
