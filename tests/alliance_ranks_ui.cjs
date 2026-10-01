'use strict';
// Actual presentation helpers and renderer, without browser, network or database.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'..'),read=file=>fs.readFileSync(path.join(root,file),'utf8');
const locales=['en','de','fr','lb'],catalogs=Object.fromEntries(locales.map(code=>[code,JSON.parse(read('data/i18n/'+code+'.json'))]));
const roleOrder=['member','veteran','officer','vice_leader','leader'];
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let checks=0;
const equal=(actual,expected,message)=>{assert.deepEqual(actual,expected,message);checks++;};
function runtime(locale='en'){
 const window={ConquerLocale:{locale,t(key,params={}){return(catalogs[this.locale]?.[key]||catalogs.en[key]||key).replace(/\{(\w+)\}/g,(_,name)=>params[name]??'');}}};
 const sandbox={window};vm.createContext(sandbox);vm.runInContext(read('assets/js/alliance-ranks.js'),sandbox);return sandbox;
}
async function renderMembers(actor,member,options,actorLevel=99){
 const sandbox=runtime(),host={innerHTML:'',dataset:{},querySelectorAll:()=>[],contains:()=>false,querySelector(selector){return selector==='.community-shell'&&this.innerHTML.includes('community-shell')?{}:null;}};
 Object.assign(sandbox,{document:{querySelector:()=>host,addEventListener(){},activeElement:null},setTimeout:()=>1,clearTimeout(){}});
 vm.runInContext(read('assets/js/community-panel.js'),sandbox);
 const state={world_id:1,player_id:1,role:actor,role_level:actorLevel,alliance:{id:1},members:[{player_id:member.id??2,username:'<img src=x onerror=alert(1)>',role:member.role,role_level:99,coord_x:1,coord_y:2,...(options===undefined?{}:{assignable_roles:options})}]};
 let reads=0;const panel=sandbox.window.ConquerCommunity({esc,fmt:String,getState:()=>({city:{world_id:1}}),api:async()=>{reads++;return state;},navigate:tab=>panel.render(tab)});
 panel.onClick({dataset:{action:'community-open',id:'members'}});await new Promise(setImmediate);
 equal(reads,1,'Uses the existing community read model');
 return host.innerHTML;
}
async function main(){
 const sandbox=runtime(),ranks=sandbox.window.ConquerAllianceRanks;
 for(const locale of locales){
  sandbox.window.ConquerLocale.locale=locale;
  for(const[at,role]of roleOrder.entries()){
   equal(ranks.level(role),at+1,locale+' rank number');
   equal(ranks.name(role),catalogs[locale]['alliance_rank.role.'+role],locale+' role name');
   equal(ranks.label(role),catalogs[locale]['alliance_rank.label.'+role],locale+' complete label');
   const badge=ranks.badge(role);assert.ok(badge.includes('data-alliance-rank="'+(at+1)+'"'));checks++;
   assert.ok(badge.includes('title="'+esc(ranks.label(role))+'"'));checks++;
   assert.ok(badge.includes('title:alliance_rank.label.'+role+';aria-label:alliance_rank.label.'+role));checks++;
  }
  equal(Object.keys(catalogs[locale]).filter(key=>key.startsWith('alliance_rank.')).sort(),Object.keys(catalogs.en).filter(key=>key.startsWith('alliance_rank.')).sort(),locale+' rank key parity');
 }
 for(const invalid of [null,undefined,0,5,'r4','r5','5','__proto__','constructor','<script>',{},[]]){
  equal(ranks.level(invalid),0,'Unknown roles have no claimed rank');equal(ranks.label(invalid),'','Unknown roles have no label');equal(ranks.badge(invalid),'','Unknown roles have no markup');
 }
 sandbox.window.ConquerLocale.t=()=>'<script>"&\'bad</script>';
 assert.ok(!ranks.badge('member').includes('<script>'));checks++;assert.ok(ranks.badge('member').includes('&lt;script&gt;&quot;&amp;&#39;bad&lt;/script&gt;'));checks++;
 sandbox.window.ConquerLocale=undefined;equal(ranks.label('leader'),'R5 · Alliance leader','English fallback without locale runtime');
 const leader=await renderMembers('leader',{role:'member'},['veteran','officer','leader','forged']);
 equal([...leader.matchAll(/<option value="([^"]+)"/g)].map(m=>m[1]),['veteran','officer'],'Only server-offered, known, lower ranks appear');
 assert.ok(leader.includes('class="community-card alliance-rank-member" data-player-id="2"'));checks++;
 assert.ok(leader.includes('data-form="community-role"'));checks++;
 assert.ok(leader.includes('&lt;img src=x onerror=alert(1)&gt;'));checks++;
 equal((leader.match(/data-alliance-rank="[1-5]"/g)||[]).length,6,'Five explanatory ranks plus actual member rank');
 const deputy=await renderMembers('vice_leader',{role:'officer'},roleOrder);
 equal([...deputy.matchAll(/<option value="([^"]+)"/g)].map(m=>m[1]),['member','veteran','officer'],'Deputy cannot assign deputy or leader');
 for(const[actor,member,options]of[
  ['officer',{role:'member'},roleOrder],['vice_leader',{role:'vice_leader'},roleOrder],['leader',{role:'leader'},roleOrder],
  ['leader',{id:1,role:'member'},roleOrder],['leader',{role:'unknown'},roleOrder],['leader',{role:'member'},undefined]
 ])equal((await renderMembers(actor,member,options)).includes('data-form="community-role"'),false,'No invented authorization from role_level or absent server options');
 console.log('PASS '+checks+' alliance rank presentation checks: four catalogues, strict roles, safe labels, actual member renderer and server-supplied rank choices.');
}
main().catch(error=>{console.error(error);process.exitCode=1;});
