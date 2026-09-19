'use strict';
// Real PHP view and browser modules; only city state/network are fixtures.
const fs=require('fs'),path=require('path'),assert=require('assert'),{spawnSync}=require('child_process');
const os=require('os');const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-building-actions-'));
const root=path.resolve(__dirname,'..'),pw=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const codes=['castle','academy','barrack','hospital','storage','treasure_house','hall_of_alliance','trading_post','farm','lumber_camp','quarry','gold_mine','wall'];
function html(embedded){
 const script=`require '${path.join(root,'src/Game/World/WorldContext.php').replaceAll('\\','/')}'; \\Conquer\\Game\\World\\WorldContext::bind(7); define('APP_BASE',''); $session=['username'=>'Fixture']; $_GET=${embedded?"['embed'=>'1']":"[]"}; include '${path.join(root,'views/city3d.php').replaceAll('\\','/')}';`;
 const out=spawnSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['-r',script],{encoding:'utf8'});assert.equal(out.status,0,out.stderr);
 return out.stdout.replace(/<script[^>]*src="[^"]*(?:scene|hud)\.js[^>]*><\/script>/g,'').replace(/<link[^>]*>/g,'')
  .replace('</head>','<style>[hidden]{display:none!important}.building-label{display:inline-block;margin:2px}#building-command{position:relative}</style></head>');
}
const state={server_time:Date.now()/1000,player:{name:'Fixture',csrf:'fixture'},resources:{food:1000,lumber:1000,stone:1000,gold:1000},build_queue:[],research_queue:[],troop_queue:[],buildings:Object.fromEntries(codes.map(code=>[code,{name:code,level:1,power:1,next_power:2,cost:{food:10},requirements:[],reasons:[],seconds:60,can_upgrade:true}]))};
(async()=>{const browser=await pw.chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});const errors=[],worldHeaders=[];let checks=0;
try{const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://buildings.fixture/**',route=>{const url=new URL(route.request().url());
  if(url.pathname==='/host')return route.fulfill({contentType:'text/html',body:'<script>window.messages=[];addEventListener("message",e=>messages.push(e.data));</script><iframe src="/embedded" style="width:1100px;height:800px"></iframe>'});
  if(url.pathname==='/embedded'||url.pathname==='/standalone')return route.fulfill({contentType:'text/html',body:html(url.pathname==='/embedded')});
  if(url.pathname==='/api/city3d/state'){worldHeaders.push(route.request().headers()['x-world-id']);return route.fulfill({json:{ok:true,data:state}});}
  if(url.pathname==='/city')return route.fulfill({contentType:'text/html',body:'Destination fixture'});
  const file=path.resolve(root,'.'+url.pathname);if(file.startsWith(root+path.sep)&&fs.existsSync(file))return route.fulfill({contentType:'text/javascript',body:fs.readFileSync(file)});return route.abort();
 });
 await page.goto('https://buildings.fixture/host');const frame=page.frames().find(f=>f.url().endsWith('/embedded'));await frame.waitForFunction(()=>document.querySelector('#connection').textContent.includes('Fixture'));
 const select=async code=>{await frame.evaluate(code=>window.dispatchEvent(new CustomEvent('conquer-building-select',{detail:{id:code}})),code);assert.equal(await frame.locator('#building-command').isVisible(),true);assert.equal(await frame.locator('#upgrade-dialog').isVisible(),false);checks+=2;};
 for(const code of ['treasure_house','trading_post','castle']){
  await page.evaluate(()=>messages=[]);await select(code);assert.deepEqual(await page.evaluate(()=>messages),[],'Initial tap must show action menu only');checks++;
  for(const [button,type]of [['building-function','conquer:building-function'],['building-info','conquer:building'],['building-upgrade','conquer:building']]){
   await select(code);await page.evaluate(()=>messages=[]);await frame.locator('#'+button).click();await page.waitForFunction(()=>messages.length>0);const msg=await page.evaluate(()=>messages.at(-1));if(button==='building-function'&&code!=='castle'){assert.deepEqual(msg,{type:'conquer:building-panel',tab:code==='treasure_house'?'treasures':'market',panel:code==='treasure_house'?'equipment':'caravan'});}else{assert.equal(msg.type,type);assert.equal(msg.code,code);}checks+=2;
  }
 }
 for(const [code,tab,panel]of [['treasure_house','treasures','chests'],['trading_post','market','vip']]){
  await select(code);await page.evaluate(()=>messages=[]);const extra=frame.locator('#building-extra-action');assert.equal(await extra.getAttribute('data-building-panel'),panel);await extra.click();await page.waitForFunction(()=>messages.length>0);assert.deepEqual(await page.evaluate(()=>messages.at(-1)),{type:'conquer:building-panel',tab,panel});checks+=2;
 }
 for(const [code,button,hash]of [['treasure_house','building-function','treasures'],['treasure_house','building-extra-action','treasures?section=chests'],['trading_post','building-function','market'],['trading_post','building-extra-action','market?section=vip']]){
  await page.goto('https://buildings.fixture/standalone');await page.waitForFunction(()=>document.querySelector('#connection').textContent.includes('Fixture'));await page.evaluate(code=>window.dispatchEvent(new CustomEvent('conquer-building-select',{detail:{id:code}})),code);await page.locator('#'+button).click();await page.waitForURL('https://buildings.fixture/city#'+hash);checks++;
 }
 for(const width of [1280,390]){
  await page.setViewportSize({width,height:844});await page.goto('https://buildings.fixture/standalone');await page.waitForFunction(()=>document.querySelector('#connection').textContent.includes('Fixture'));
  for(const css of ['assets/city3d/style.css','assets/city3d/play.css','assets/city3d/hud.css','assets/css/embedded-city-hud.css','assets/css/village-theme.css'])await page.addStyleTag({content:fs.readFileSync(path.join(root,css),'utf8')});
  await page.addStyleTag({content:'body{background:#bdcc89}body>*{display:none!important}#building-command:not([hidden]){display:block!important;position:fixed!important;left:50%!important;top:50%!important;transform:translate(-50%,-50%)!important;opacity:1!important;visibility:visible!important}'});
  for(const code of ['treasure_house','trading_post']){await page.evaluate(code=>window.dispatchEvent(new CustomEvent('conquer-building-select',{detail:{id:code}})),code);const layout=await page.locator('#building-command .quick-actions').evaluate(el=>({width:el.getBoundingClientRect().width,buttons:[...el.querySelectorAll('button:not([hidden])')].map(b=>({height:b.getBoundingClientRect().height,label:getComputedStyle(b.lastElementChild).display}))}));assert(layout.width<=width);assert(layout.buttons.every(b=>b.height>=44&&b.label!=='none'));checks+=2;await page.screenshot({path:path.join(output,code+'-'+width+'.png')});}
 }
 console.log('Isolated menu screenshots: '+output);
 assert(worldHeaders.length>=5);assert(worldHeaders.every(h=>h==='7'),'Every state request must carry bound world ID 7');checks+=2;
 assert.deepEqual(errors,[]);console.log(`PASS ${checks} building action checks; real PHP view, real play module, embedded iframe; no browser errors`);
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
