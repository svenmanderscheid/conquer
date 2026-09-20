'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net');
const {spawn}=require('node:child_process'),{chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/audio-2026-09-20');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[root+'/tools/preview-feature-fixture.php','--port='+port],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='',page;const errors=[],checks=[];let downloads=0;
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Preview timeout')),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(timer);reject(Error(log));});});
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const context=await browser.newContext({viewport:{width:390,height:844},hasTouch:true,locale:'de-DE'}),base='http://127.0.0.1:'+port;
  page=await context.newPage();page.setDefaultTimeout(20000);page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(r.url().endsWith('.wav'))downloads++;});
  await page.goto(base+'/?zugang=login');await page.locator('[name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.goto(base+'/city#settings');await page.locator('.audio-settings').waitFor();
  const status=()=>page.evaluate(()=>ConquerAudio.status());
  assert.equal((await status()).context,'idle');assert.equal(downloads,0,'no audio download or context before a game gesture');
  await page.locator('[data-audio-start]').tap();await page.waitForFunction(()=>ConquerAudio.status().musicPlaying);
  assert.equal(downloads,1);assert.equal((await status()).settings.musicVolume,20);
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.locator('.audio-settings').scrollIntoViewIfNeeded();
   const layout=await page.locator('.audio-settings').evaluate(el=>{const r=el.getBoundingClientRect(),controls=[...el.querySelectorAll('button,input[type=range]')].map(e=>e.getBoundingClientRect());return {fits:r.left>=0&&r.right<=innerWidth+1,overflow:el.scrollWidth-el.clientWidth,controlsFit:controls.every(c=>c.width>=44&&c.height>=44&&c.left>=r.left&&c.right<=r.right+1)};});
   assert(layout.fits&&layout.overflow<=1&&layout.controlsFit,JSON.stringify({width,height,...layout}));checks.push({screen:'settings',width,height,...layout});
   await page.locator('[data-audio-start]').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(out,`settings-${width}x${height}.png`)});
  }
  await page.setViewportSize({width:390,height:844});
  for(const kind of ['confirm','training','building','reward']){await page.locator(`[data-audio-demo=${kind}]`).tap();assert.equal((await status()).lastSound,kind);await page.waitForFunction(()=>ConquerAudio.status().voices===0);}
  const setVolume=async(key,value)=>{await page.locator(`[data-audio-setting=${key}]`).evaluate((el,value)=>{el.value=value;el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}));},value);};
  await setVolume('musicVolume',13);await setVolume('effectsVolume',34);
  await page.locator('[data-audio-setting=effects]').uncheck();assert(await page.locator('[data-audio-demo=training]').isDisabled());await page.locator('[data-audio-setting=effects]').check();
  // App lifecycle suspends the native audio graph and resumes the existing buffer.
  await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'));});
  await page.waitForFunction(()=>ConquerAudio.status().context==='suspended');assert.equal((await status()).musicPlaying,false);assert.equal((await status()).voices,0);
  await page.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:false});document.dispatchEvent(new Event('visibilitychange'));});
  await page.waitForFunction(()=>ConquerAudio.status().musicPlaying);assert.equal(downloads,1,'resume reuses decoded buffer');
  await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pagehide',{persisted:true})));
  await page.waitForFunction(()=>ConquerAudio.status().context==='suspended');assert.equal((await status()).musicPlaying,false);
  // Resume and an immediate second pagehide must not leave a pending resume playing.
  await page.evaluate(()=>{window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}));window.dispatchEvent(new PageTransitionEvent('pagehide',{persisted:true}));});
  await page.waitForTimeout(150);assert.equal((await status()).context,'suspended');assert.equal((await status()).musicPlaying,false);
  await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));await page.waitForFunction(()=>ConquerAudio.status().musicPlaying);
  await page.locator('[data-audio-mute]').tap();await page.waitForFunction(()=>ConquerAudio.status().context==='suspended');assert.equal((await status()).musicPlaying,false);
  await page.reload();await page.locator('.audio-settings').waitFor();assert.equal((await status()).context,'idle');assert.equal((await status()).settings.muted,true);assert.equal((await status()).settings.musicVolume,13);assert.equal((await status()).settings.effectsVolume,34);assert.equal(downloads,1);
  await page.locator('[data-audio-mute]').tap();await page.waitForFunction(()=>ConquerAudio.status().musicPlaying);
  // Resource details remain tappable beside the task HUD on small screens.
  await page.keyboard.press('Escape');await page.evaluate(()=>location.hash='city');
  for(const [width,height]of [[390,844],[320,568],[568,320]]){
   await page.setViewportSize({width,height});await page.locator('[data-action=resource][data-id=food]').tap();await page.locator('[data-action=building][data-id=farm]').waitFor();await page.keyboard.press('Escape');
  }
  await page.setViewportSize({width:390,height:844});
  // Real training: no success cue while the server response is pending.
  await page.evaluate(()=>location.hash='army');await page.locator('#train-count').waitFor();await page.locator('#train-count').fill('100');await page.locator('#train-count').press('Tab');
  let trainingPosts=0;await page.route('**/api/troops/train',async route=>{trainingPosts++;await new Promise(r=>setTimeout(r,900));await route.continue();});
  const before=(await status()).playedCount;await page.locator('#train-confirm').tap();assert.equal((await status()).playedCount,before);
  await page.waitForFunction(()=>ConquerAudio.status().lastSound==='training');assert.equal(trainingPosts,1);await page.waitForFunction(()=>ConquerAudio.status().voices===0);
  // Real building order through the existing game controls.
  await page.keyboard.press('Escape');await page.evaluate(()=>location.hash='city');await page.locator('[data-action=resource][data-id=food]').tap();await page.locator('[data-action=building][data-id=farm]').tap();
  await page.locator('[data-action=upgrade][data-id=farm]').tap();await page.waitForFunction(()=>ConquerAudio.status().lastSound==='building');await page.waitForFunction(()=>ConquerAudio.status().voices===0);
  assert.equal((await status()).playedCount,before+2,'exactly one successful cue for each accepted order');
  // Settings remain translated after dynamic state updates and reloads.
  await page.evaluate(()=>location.hash='settings');await page.locator('.audio-settings').waitFor();
  for(const [locale,title,reward]of [['en','Music & sounds','Reward'],['fr','Musique et sons','Récompense'],['de','Musik & Klänge','Belohnung']]){
   await page.evaluate(locale=>ConquerLocale.setLocale(locale),locale);await page.waitForFunction(title=>document.querySelector('.audio-settings h2')?.textContent===title,title);
   assert.equal(await page.locator('[data-audio-demo=reward]').textContent(),reward);
  }
  checks.push({gesture:true,downloads,lifecycle:true,savedMute:true,trainingPosts,confirmedOrders:true,locales:['de','en','fr']});
  // Isolated engine exercises completion snapshots, exclusions and failure recovery.
  const isolated=await context.newPage();isolated.on('pageerror',e=>errors.push(e.message));
  await isolated.route('**/__audio-fixture',route=>route.fulfill({contentType:'text/html',body:'<!doctype html><html><body></body></html>'}));
  await isolated.goto(base+'/__audio-fixture');await isolated.addScriptTag({url:base+'/assets/js/game-audio.js'});
  await isolated.evaluate(()=>{window.testAudio=ConquerAudio.create({base:''});document.body.innerHTML=testAudio.controls();});await isolated.locator('[data-audio-start]').click();await isolated.waitForFunction(()=>ConquerAudio.status().musicPlaying);
  const quiet=()=>isolated.waitForFunction(()=>ConquerAudio.status().voices===0);
  await isolated.evaluate(()=>{testAudio.confirmed('march/preview',{kind:'monsters'},{});testAudio.confirmed('game/state',null,{});testAudio.confirmed('world-chat/send',{message:'test'},{});});assert.equal(await isolated.evaluate(()=>ConquerAudio.status().playedCount),0);
  await isolated.evaluate(()=>{window.sample={city:{player_id:99,world_id:1},server_time:1000,buildings:{farm:{level:7}},trained_total:0,research:{}};testAudio.observe(sample);});assert.equal(await isolated.evaluate(()=>ConquerAudio.status().playedCount),0);
  await isolated.evaluate(()=>testAudio.observe({...sample,server_time:1001,buildings:{farm:{level:8}}}));assert.equal(await isolated.evaluate(()=>ConquerAudio.status().lastSound),'complete');await quiet();
  await isolated.evaluate(()=>testAudio.observe({...sample,server_time:1002,trained_total:10}));assert.equal(await isolated.evaluate(()=>ConquerAudio.status().lastSound),'trained');await quiet();
  await isolated.evaluate(()=>testAudio.observe({...sample,server_time:1003,research:{food_production:1}}));assert.equal(await isolated.evaluate(()=>ConquerAudio.status().lastSound),'research');await quiet();
  await isolated.evaluate(()=>{testAudio.observe({...sample,server_time:1004,buildings:{farm:{level:9}},return_summary:{}});testAudio.observe({...sample,server_time:1405,buildings:{farm:{level:10}}});testAudio.observe({...sample,server_time:1406,city:{player_id:99,world_id:2},buildings:{farm:{level:11}}});});assert.equal(await isolated.evaluate(()=>ConquerAudio.status().playedCount),3,'return summary, absence and world switches do not replay completion sounds');
  await isolated.close();
  const fallback=await context.newPage();fallback.on('pageerror',e=>errors.push(e.message));let failedLoads=0;
  await fallback.route('**/__audio-fixture',route=>route.fulfill({contentType:'text/html',body:'<!doctype html><html><body></body></html>'}));
  await fallback.route('**/assets/audio/village-meadow-v1.wav',route=>{failedLoads++;return route.fulfill({status:503,body:'Audio temporarily unavailable'});});
  await fallback.goto(base+'/__audio-fixture');await fallback.addScriptTag({url:base+'/assets/js/game-audio.js'});
  await fallback.evaluate(()=>{window.testAudio=ConquerAudio.create({base:''});document.body.innerHTML=testAudio.controls();});await fallback.locator('[data-audio-start]').click();await fallback.waitForFunction(()=>ConquerAudio.status().loadFailed);
  await fallback.locator('[data-audio-demo=training]').click();assert.equal(await fallback.evaluate(()=>ConquerAudio.status().lastSound),'training','effects survive a missing music file');
  await fallback.evaluate(()=>{window.dispatchEvent(new Event('pageshow'));document.dispatchEvent(new Event('visibilitychange'));});assert.equal(failedLoads,1,'no retry loop on failed music');
  await fallback.unroute('**/assets/audio/village-meadow-v1.wav');await fallback.locator('[data-audio-start]').click();await fallback.waitForFunction(()=>ConquerAudio.status().musicPlaying);
  await fallback.evaluate(()=>{testAudio.destroy();window.AudioContext=window.webkitAudioContext=undefined;window.testAudio=ConquerAudio.create({base:''});document.body.innerHTML=testAudio.controls();testAudio.updateControls();});
  await fallback.locator('[data-audio-start]').click();assert.equal(await fallback.evaluate(()=>ConquerAudio.status().supported),false);assert(await fallback.locator('[data-audio-demo=training]').isDisabled());
  await fallback.close();checks.push({missingMusicEffects:true,noRetryLoop:true,explicitRetry:true,unsupportedBrowser:true});assert.deepEqual(errors,[]);
  fs.writeFileSync(path.join(out,'report.json'),JSON.stringify({checks,errors},null,2));console.log('PASS audio: real training/building confirmations, native music playback, effect cleanup, gesture policy, mute persistence, lifecycle/resume race, completion snapshots, translations and 5 viewports.');
 }catch(error){if(page){console.error(await page.locator('dialog[open]').allTextContents());console.error(await page.evaluate(()=>window.ConquerAudio?.status()).catch(()=>null));await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});}console.error(errors);throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});
