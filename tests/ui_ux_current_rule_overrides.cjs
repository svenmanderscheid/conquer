'use strict';
// Audit-only exact updates for six reviewed legacy suites.
// This module never starts a browser, server, database or original test.
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
module.exports=function currentRules(original,suite,root){
 if(!['hospital_app','game_comfort_app','march_compact_app','hospital_quick_heal_app','gathering_occupation_app','combat_report_app'].includes(suite))throw Error('--current-rules is reviewed only for hospital_app, game_comfort_app, march_compact_app, hospital_quick_heal_app, gathering_occupation_app and combat_report_app');
 let source=original;const replacements=[],evidence=[];
 const change=(before,after,reason,files,kind='current-rule-expectation')=>{
  const eol=original.includes('\r\n')?'\r\n':'\n';before=before.replace(/\r?\n/g,eol);after=after.replace(/\r?\n/g,eol);
  if(source.split(before).length!==2)throw Error('Current-rules recipe requires exactly one match: '+before.slice(0,100));
  source=source.replace(before,after);replacements.push({kind,before,after,reason});
  for(const relative of files){if(evidence.some(e=>e.file===relative))continue;const content=fs.readFileSync(path.join(root,relative));evidence.push({file:relative,sha256:crypto.createHash('sha256').update(content).digest('hex')});}
 };
 if(suite==='hospital_app'){
  const marker='assert.equal(kingdom.hospital.active,null);';
  change(marker,marker+`
  // Audit: current T1 catalog is 0.5 seconds each; validate the server quote before using it.
  const auditHospital=kingdom.hospital.wounded;
  const auditCatalog=JSON.parse(fs.readFileSync(path.resolve(__dirname,'../data/troops.json'),'utf8')).troops;
  for(const code of [50100101,50200101,50300101]){
   assert.equal(auditCatalog.find(t=>Number(t.code)===code).heal_time,0.5,'Current T1 base healing time');
   assert.equal(auditHospital.find(w=>Number(w.troop_code)===code).seconds_per_troop,0.5,'Unbuffed fixture server rate matches current catalog');
  }
  const auditQuote=counts=>{const cost={food:0,lumber:0,stone:0,gold:0};let seconds=0;for(const [code,count]of Object.entries(counts)){const row=auditHospital.find(w=>Number(w.troop_code)===Number(code));assert(row);seconds+=count*row.seconds_per_troop;for(const key of Object.keys(cost))cost[key]+=count*Number(row.resources[key]||0);}return {seconds:Math.ceil(seconds),cost};};
  const auditInitial=auditQuote({50100101:620,50200101:245,50300101:85}),audit600=auditQuote({50100101:600});
  assert.equal(auditInitial.seconds,475);assert.equal(audit600.seconds,300);
  assert.deepEqual(audit600.cost,{food:1800,lumber:0,stone:3600,gold:1200},'Current T1 resource cost, rounded per troop');
  const auditClock=seconds=>[Math.floor(seconds/3600),Math.floor(seconds%3600/60),seconds%60].map(x=>String(x).padStart(2,'0')).join(':');
  fs.writeFileSync(path.join(output,'current-healing-quotes.json'),JSON.stringify({initial:auditInitial,selection600:audit600},null,2));`,
  'Validate current catalog and unbuffed server rate, then compare the UI and paid operation with that exact authoritative quote.', ['data/troops.json','src/Game/Hospital/HospitalService.php','assets/js/hospital-panel.js','tools/preview-feature-fixture.php']);
  change("assert.equal(await page.locator('[data-hospital-duration]').innerText(),'00:15:50');","assert.equal(await page.locator('[data-hospital-duration]').innerText(),auditClock(auditInitial.seconds));",'950 T1 troops now require 475 seconds, not the archived 950 seconds.',[]);
  change("assert.equal(await page.locator('[data-hospital-duration]').innerText(),'00:10:00');","assert.equal(await page.locator('[data-hospital-duration]').innerText(),auditClock(audit600.seconds));",'600 T1 infantry now require 300 seconds; selection behavior and exact comparison are retained.',[]);
  change("assert.equal(started.duration_seconds,600);assert.deepEqual(started.resources_spent,{food:3000,lumber:1800,stone:0,gold:0});","assert.equal(started.duration_seconds,audit600.seconds);assert.deepEqual(started.resources_spent,audit600.cost);",'Paid server result must equal the previously validated current quote; all later resource, speedup and healed-count checks remain.',[]);
 }
 if(suite==='game_comfort_app'){
  change("await page.locator('[data-comfort=goal]').click();await page.locator('#panel-dialog[data-panel=research]').waitFor();\n  await page.keyboard.press('Escape');",`
  const auditJourney=(await(await context.request.get(base+'/api/game/state')).json()).data;
  assert.equal(auditJourney.beginner_journey.progress.gather,false,'An old empty return does not complete the delivery goal');
  assert(Number(auditJourney.trained_total)>=20);
  for(const code of ['castle','farm','lumber_camp','quarry','gold_mine'])assert(Number(auditJourney.buildings[code].level)>=2);
  assert(Object.values(auditJourney.troops).some(count=>Number(count)>0),'Gathering has available troops');
  const auditGoalText=await page.locator('[data-comfort=goal] strong').textContent();
  const auditExpectedTitle=await page.evaluate(()=>ConquerLocale.text('Deine erste Sammelbeute nach Hause bringen'));
  assert.equal(auditGoalText,auditExpectedTitle,'HUD selects the current first unfinished ready goal');
  const auditGoalWrites=[];const auditGoalRequest=request=>{if(request.method()==='POST'&&request.url().includes('/api/'))auditGoalWrites.push(request.url());};page.on('request',auditGoalRequest);
  await page.locator('[data-comfort=goal]').click();
  await page.waitForFunction(()=>location.hash==='#world'&&document.body.classList.contains('world-mode'));
  await page.locator('.atlas-shell').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
  assert.deepEqual(auditGoalWrites,[],'Following a goal opens its destination without dispatching troops');page.off('request',auditGoalRequest);
  fs.writeFileSync(path.join(out,'current-journey-destination.json'),JSON.stringify({goal:auditGoalText,progress:auditJourney.beginner_journey.progress,destination:'world',writes:auditGoalWrites},null,2));
  // Return to the same city playfield from which the original later comfort checks start.
  await page.keyboard.press('Escape');await page.locator('#navigation [data-id=city]').click();
  await page.waitForFunction(()=>location.hash==='#city'&&document.body.classList.contains('city-mode')&&!document.querySelector('.scene-transition.is-active'));`,
  'Nine-step journey now recommends actual gathering before research for --comfort: the historical completed march contains survivors but no loot. Assert that exact state, title, destination and no writes, then restore the original city precondition.', ['tools/preview-feature-fixture.php','src/Game/Tutorial/BeginnerJourney.php','assets/js/beginner-guide.js','assets/js/game-comfort.js']);
 }
 if(suite==='march_compact_app'){
  change("assert.equal(await page.locator('.march-unit-row').count(),30);",`
  const auditRoster=JSON.parse(fs.readFileSync(path.join(root,'data/troops.json'),'utf8'));
  assert.equal(auditRoster.max_tier,5);assert.equal(auditRoster.troops.length,15);
  for(const tier of [1,2,3,4,5])assert.equal(auditRoster.troops.filter(t=>Number(t.tier)===tier).length,3);
  assert.equal(await page.locator('.march-unit-row').count(),15);`,
  'Current authoritative roster is three troop types at each of five active tiers. Verify the catalog and the exact rendered count.', ['data/troops.json','src/Game/City/TroopData.php','tools/preview-feature-fixture.php','docs/UI_STYLE_GUIDE.md']);
  change('const expectedTiers=Array.from({length:10},(_,i)=>10-i).flatMap(t=>[t,t,t]);','const expectedTiers=Array.from({length:5},(_,i)=>5-i).flatMap(t=>[t,t,t]);','Expected active ordering is T5 through T1, three roles each; the exact ordering and colors are still asserted.',[]);
  change("assert.equal(await page.locator('.march-selected-card').count(),30,'Each selected type and tier has its own card');","assert.equal(await page.locator('.march-selected-card').count(),15,'Each active selected type and tier has its own card');",'Selected army must show all 15 active combinations. Every viewport, target switch, input, formation, scroll and reachable-dispatch assertion remains.',[]);
 }
 if(suite==='hospital_quick_heal_app'){
  change('await page.goto(base);await page.locator(\'[data-auth-target="login"]\').first().click();',
   "await page.goto(base+'/?zugang=login');",
   'The current play-login route renders identifier/password directly and has no data-auth-target=login toggle. Open that same login form directly; retain all login, touch, healing API and result assertions. The root runner separately supplies the required --hospital fixture.',
   ['index.php','views/play_login.php','tools/preview-feature-fixture.php'],'current-login-entry');
 }
 if(suite==='gathering_occupation_app'){
  change("const art=n.querySelector('canvas').getBoundingClientRect(),label=n.querySelector('.atlas-gathering-badge').getBoundingClientRect()",
   "const art=n.querySelector('canvas').getBoundingClientRect(),label=n.querySelector(n.dataset.occupation==='free'?'.atlas-marker-level':'.atlas-gathering-badge').getBoundingClientRect()",
   'Screenshot geometry must measure the same visible plaque already used by the preceding placement assertion. Free nodes intentionally hide the gathering badge and show atlas-marker-level. Only screenshot clipping changes; every gameplay, layout and motion assertion remains.',
   ['tests/gathering_occupation_app.cjs','assets/js/world-map.js','assets/css/village-theme.css'],'screenshot-geometry');
 }
 if(suite==='combat_report_app'){
  change('assert.match(await page.evaluate(()=>navigator.clipboard.readText()),/Conquer · Kampfbericht/);',
   'assert.match(await page.evaluate(()=>navigator.clipboard.readText()),/Union of Kingdoms · Kampfbericht/);',
   'The public game name is Union of Kingdoms. The completed DE recheck observed that exact correct clipboard prefix. Change only the obsolete expected brand; preserve the clipboard assertion and every later report-flow assertion.',
   ['AGENTS.md','assets/js/combat-report.js'],'current-public-name');
 }
 const assertionChanges=!['hospital_quick_heal_app','gathering_occupation_app'].includes(suite);
 return {source,replacements,evidence,assertionsChanged:assertionChanges,expectationMode:assertionChanges?'current-authoritative-rules':suite==='hospital_quick_heal_app'?'original-assertions-current-login-entry':'original-assertions-visible-screenshot-plaque'};
};
