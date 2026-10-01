'use strict';
// Pure renderer contract: real localization and catalogues; no browser, server or database.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const root=path.resolve(__dirname,'..');
const catalogs=Object.fromEntries(['en','de','fr'].map(locale=>[locale,JSON.parse(fs.readFileSync(path.join(root,'data/i18n',locale+'.json'),'utf8'))]));
const rules=[
 {id:'frostgrimm_ice_armor',version:1,required_power_percent:12,counter_type:'infantry',counter_power_percent:50},
 {id:'sandmaul_sandstorm',version:1,army_power_reduction_percent:10,counter_type:'cavalry',counter_power_percent:50},
 {id:'glutramm_ember_backlash',version:1,injury_increase_percent:20,counter_type:'ranged',counter_power_percent:50},
 {id:'daemmerhorn_runic_barrier',version:1,required_power_percent:15,active_above_hp_percent:50,counter_type:'balanced',counter_power_percent:30},
];
const prefixes=['boss.frostgrimm.','boss.sandmaul.','boss.glutramm.','boss.daemmerhorn.'];
const decode=text=>text.replace(/&quot;/g,'"').replace(/&#39;/g,"'").replace(/&lt;/g,'<').replace(/&gt;/g,'>').replace(/&amp;/g,'&');
const labels=html=>[...html.matchAll(/<span data-i18n="([^"]+)" data-i18n-params="([^"]*)">([^<]*)<\/span>/g)].map(([,key,parameters,text])=>({key,parameters:JSON.parse(decode(parameters)),text:decode(text)}));
async function runtime(locale){
 const window={CONQUER_BASE:'',CONQUER_I18N:{locale,catalogs:structuredClone(catalogs)},addEventListener(){}};
 const sandbox={window,document:{currentScript:null,readyState:'loading',addEventListener(){}},localStorage:{getItem:()=>null},navigator:{language:'de-DE'},location:{protocol:'http:'},Intl,URL,AbortController,setTimeout,clearTimeout,queueMicrotask,console,fetch:()=>{throw Error('Unexpected network in pure renderer test');}};
 vm.createContext(sandbox);
 for(const file of ['localization.js','boss-mechanic.js'])vm.runInContext(fs.readFileSync(path.join(root,'assets/js',file),'utf8'),sandbox,{filename:file});
 await window.ConquerLocale.ready;
 return{locale:window.ConquerLocale,render:window.ConquerBossMechanic.render};
}
async function main(){
 let checks=0;
 for(const language of ['en','de','fr']){
  const {locale,render}=await runtime(language),format=value=>locale.formatNumber(value,{maximumFractionDigits:2});
  for(const [index,rule]of rules.entries()){
   const prefix=prefixes[index],phase=rule.counter_type==='balanced';
   const resolved={...rule,counter_power_share_percent:17.625,type_power_share_percent:{infantry:21.125,ranged:32.5,cavalry:46.375},required_power_before:12345,required_power_after:13827,army_power_before:9876.5,army_power_after:8888.85,injury_ratio_before:.125,injury_ratio_after:.15};
   const cases=[['rule',rule],['active',{...resolved,countered:false,active:true,phase_active:true}],['countered',{...resolved,countered:true,active:false,phase_active:true}],['inactive',{...resolved,countered:false,active:false,phase_active:true}]];
   if(phase)cases.push(['phase-inactive',{...resolved,countered:false,active:false,phase_active:false}],['phase-inactive',{...resolved,countered:true,active:false,phase_active:false}]);
   for(const [state,payload]of cases){
    const before=JSON.stringify(payload),html=render(payload,{rally:true}),parts=labels(html),get=key=>parts.find(part=>part.key===key);
    assert.equal(JSON.stringify(payload),before,'Renderer never changes a server result');
    assert.ok(html.includes('data-boss-mechanic="'+rule.id+'"')&&html.includes('data-boss-state="'+state+'"'),language+' '+rule.id+' '+state);
    for(const part of parts){assert.equal(typeof catalogs[language][part.key],'string',language+' has '+part.key);assert.equal(part.text,locale.t(part.key,part.parameters),'Exact localized text: '+part.key);assert.doesNotMatch(part.text,/\{(?:effect|threshold|hp|before|after|share|infantry|ranged|cavalry)\}/,'No unresolved parameters');}
    assert.equal(get(prefix+'title').text,catalogs[language][prefix+'title']);
    assert.equal(get(prefix+'rule').parameters.effect,format(rule.required_power_percent??rule.army_power_reduction_percent??rule.injury_increase_percent));
    assert.equal(get(prefix+'rule').parameters.threshold,format(rule.counter_power_percent));
    assert.ok(get('boss.effects.counter_basis'),'Counter explains base power rather than headcount or buffed power');
    assert.ok(get(prefix+'rally'),'Rally preview scope is explicit');
    if(phase)assert.equal(get(prefix+'rule').parameters.hp,format(50));
    if(state!=='rule'){
     assert.ok(get(prefix+(state==='phase-inactive'?'phase_inactive':state)),'State uses the server flag');
     assert.equal(get(prefix+'share').parameters.share,format(17.625),'Share is displayed, never recalculated');
     const effect=rule.required_power_percent?'required_power':rule.army_power_reduction_percent?'army_power':'injury_ratio',multiplier=effect==='injury_ratio'?100:1;
     assert.deepEqual(get('boss.effects.'+effect).parameters,{before:format(payload[effect+'_before']*multiplier),after:format(payload[effect+'_after']*multiplier)},'Saved before/after values are rendered verbatim');
     if(phase){assert.deepEqual(get(prefix+'formation').parameters,Object.fromEntries(Object.entries(payload.type_power_share_percent).map(([key,value])=>[key,format(value)])));if(payload.countered)assert.ok(get(prefix+'countered'),'Phase and formation facts remain separately visible');}
    }
    assert.ok(!render(payload).includes('data-i18n="'+prefix+'rally"'),'Solo rendering does not claim a rally');checks++;
   }
   for(const invalid of [{...rule,version:2},{...rule,version:undefined},{...rule,counter_type:'invalid'},{...rule,counter_power_percent:NaN},{...rule,[rule.required_power_percent?'required_power_percent':rule.army_power_reduction_percent?'army_power_reduction_percent':'injury_increase_percent']:'12'}])assert.equal(render(invalid),'','Unsupported or malformed rule is not invented');
   const historical={...rule,counter_power_percent:phase?20:30};
   assert.equal(labels(render(historical)).find(part=>part.key===prefix+'rule').parameters.threshold,format(historical.counter_power_percent),'Saved pre-balance thresholds are displayed without upgrading them');checks++;
  }
  for(const countered of [true,false]){
   const old={id:'grumwald_regeneration',heal_percent:20,counter_power_percent:30,countered,counter_power_share_percent:12.25,hp_restored:countered?0:321};
   const html=render(old,{rally:true}),parts=labels(html);
   assert.ok(html.includes('class="boss-mechanic"'));assert.ok(!html.includes('data-boss-mechanic='),'Legacy Grumwald markup remains compatible');
   for(const suffix of ['title','rule',countered?'countered':'active','share','restored','rally'])assert.ok(parts.some(part=>part.key==='boss.grumwald.'+suffix));
   assert.equal(parts.find(part=>part.key==='boss.grumwald.rule').parameters.heal,locale.formatNumber(20,{maximumFractionDigits:1}),'Historical Grumwald parameter is not replaced with current 12%');checks++;
  }
  assert.equal(render(null),'');assert.equal(render({id:'unknown',version:1}),'');assert.equal(render({id:'<script>alert(1)</script>',version:1}),'');
 }
 console.log('PASS '+checks+' localized boss states: EN/DE/FR, exact saved parameters/effects, server-owned counter/phase, rally scope, version allowlist and legacy Grumwald.');
}
main().catch(error=>{console.error(error);process.exitCode=1;});
