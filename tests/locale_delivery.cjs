'use strict';
// Pure CLI/VM regression checks: no browser, app fixture, database or network listener.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),{spawnSync}=require('node:child_process');
const root=path.resolve(__dirname,'..'),php=process.env.PHP_BINARY||'php';
function phpRun(args){const result=spawnSync(php,args,{cwd:root,encoding:'utf8',maxBuffer:32*1024*1024});assert.equal(result.status,0,result.stderr||result.stdout);return result;}
function phpString(value){return "'"+String(value).replaceAll('\\','\\\\').replaceAll("'","\\'")+"'";}
const fixture=JSON.parse(phpRun(['tests/locale_delivery.php','--fixture','--base=/nested/conquer']).stdout);
for(const locale of ['en','de','fr']){
    const page={window:{}};vm.createContext(page);vm.runInContext(fixture.bootstrap[locale],page);
    for(const name of ['en',...(locale==='en'?[]:[locale]),...(locale==='de'?[]:['sources-de'])])vm.runInContext(fixture.scripts[name],page);
    assert.equal(page.window.CONQUER_I18N.locale,locale);
    assert.equal(page.window.CONQUER_I18N.catalogs.en['common.save'],'Save');
    assert.ok(page.window.CONQUER_I18N.catalogs[locale]);
    assert.equal(Boolean(page.window.CONQUER_I18N.catalogs.fr),locale==='fr','unused target locale stays unloaded');
    if(locale!=='de')assert.equal(page.window.CONQUER_I18N.sourceCatalogs.de['common.save'],'Speichern');
    assert.equal(page.window.CONQUER_I18N.catalogUrls.fr,fixture.urls.fr);
}
// Exercise index.php itself, proving public assets exit before database/session bootstrap.
for(const base of ['', '/nested/conquer']){
    const requestPath=fixture.urls.en.replace('/nested/conquer',base);
    const source='$_SERVER["REQUEST_URI"]='+phpString(requestPath)+';$_SERVER["SCRIPT_NAME"]='+phpString(base+'/index.php')+';$_SERVER["REQUEST_METHOD"]="GET";$_COOKIE["conquer_session"]="PRIVATE_SENTINEL";register_shutdown_function(static function(){fwrite(STDERR,json_encode(["bootstrapLoaded"=>class_exists('+phpString('Conquer\\Bootstrap')+',false),"sessionActive"=>session_status()===PHP_SESSION_ACTIVE]));});require '+phpString(path.join(root,'index.php'))+';';
    const response=phpRun(['-r',source]);
    assert.equal(JSON.parse(response.stdout)['common.save'],'Save');
    assert.deepEqual(JSON.parse(response.stderr),{bootstrapLoaded:false,sessionActive:false});
    assert.ok(!response.stdout.includes('PRIVATE_SENTINEL'));
}
const workerSource=fs.readFileSync(path.join(root,'service-worker.js'),'utf8');
async function checkWorker(base){
    const origin='https://example.invalid',scope=origin+base+'/',listeners={},requests=[];
    const key=request=>typeof request==='string'?request:request.url;
    const cachesByName=new Map();
    function cache(name){if(!cachesByName.has(name))cachesByName.set(name,{map:new Map(),async put(request,response){this.map.set(key(request),response.clone());},async match(request){return this.map.get(key(request))?.clone();},async keys(){return [...this.map.keys()].map(url=>new Request(url));},async delete(request){return this.map.delete(key(request));}});return cachesByName.get(name);}
    const context={URL,Request,Response,Map,Set,console,caches:{async open(name){return cache(name);},async keys(){return [...cachesByName.keys()];},async delete(name){return cachesByName.delete(name);},async match(request){for(const c of cachesByName.values()){const result=await c.match(request);if(result)return result;}}},self:{registration:{scope},addEventListener(name,callback){listeners[name]=callback;},async skipWaiting(){},clients:{async claim(){}}},fetch:async request=>{requests.push(request);return new Response('{"public":true}',{headers:{'Content-Type':'application/json','Cache-Control':'public, max-age=31536000, immutable'}});}};
    vm.createContext(context);vm.runInContext(workerSource+'\nglobalThis.probe={cacheable,publicFetch,store,CACHE,OFFLINE,MAX_BYTES};',context);
    const probe=context.probe,asset='locale-assets/en.'+'a'.repeat(20)+'.json',request=new Request(scope+asset,{headers:{'X-CSRF-Token':'PRIVATE_CSRF','Cookie':'PRIVATE_COOKIE'},credentials:'include'});
    assert.equal(probe.cacheable(request),true);
    for(const suffix of ['api/state','admin','admin/players','auth/local','city','index.php','data/i18n/en.json','locale-assets/en.json','locale-assets/en.'+'a'.repeat(20)+'.html',asset+'?v=1'])assert.equal(probe.cacheable(new Request(scope+suffix)),false,suffix);
    assert.equal(probe.cacheable(new Request('https://other.invalid/'+asset)),false);
    assert.equal(probe.cacheable(new Request(scope+asset,{headers:{Authorization:'secret'}})),false);
    assert.equal(probe.cacheable(new Request(scope+asset,{method:'POST',body:'x'})),false);
    assert.equal(probe.cacheable({url:scope+asset,method:'GET',mode:'navigate',headers:new Headers()}),false);
    await probe.publicFetch(request);const sanitized=requests.pop();assert.equal(sanitized.credentials,'omit');assert.equal(sanitized.cache,'default');assert.deepEqual([...sanitized.headers],[]);
    await probe.publicFetch(new Request(scope+'assets/css/localization.css?v=1'));assert.equal(requests.pop().cache,'no-cache');
    async function dispatch(req){let response;listeners.fetch({request:req,respondWith(value){response=value;}});return response?await response:null;}
    assert.equal(await dispatch(new Request(scope+'api/state')),null,'private API bypasses service worker interception');
    const first=await dispatch(request),second=await dispatch(request);assert.equal(await first.text(),await second.text());assert.equal(requests.length,1,'versioned locale cache hit avoids another request');
    const current=await context.caches.open(probe.CACHE);assert.equal(current.map.size,1);
    const invalidResponses=[new Response('<html>PRIVATE</html>',{headers:{'Content-Type':'text/html'}}),new Response('{}',{headers:{'Cache-Control':'private, no-store'}}),new Response('{}',{headers:{'Set-Cookie':'private=1'}}),new Response('x'.repeat(probe.MAX_BYTES+1),{headers:{'Content-Type':'application/json'}})];
    for(let i=0;i<invalidResponses.length;i++)await probe.store(new Request(scope+asset+'?bad='+i),invalidResponses[i]);
    assert.equal(current.map.size,1,'HTML, private, cookies and large files never enter the cache');
    await probe.store(new Request(probe.OFFLINE),new Response('<html>Public offline page</html>',{headers:{'Content-Type':'text/html'}}));
    for(let i=0;i<40;i++)await probe.store(new Request(scope+'assets/css/localization.css?v='+i),new Response('body{}',{headers:{'Content-Type':'text/css'}}));
    assert.equal(current.map.size,32);assert.ok(await current.match(probe.OFFLINE),'bounded eviction preserves public offline fallback');
    assert.ok([...current.map.keys()].every(url=>url.startsWith(scope)),'installation caches remain scoped');
}
(async()=>{await checkWorker('');await checkWorker('/nested/conquer');console.log('PASS executable locale assets, complete EN fallback/current locale, real front-controller DB/session bypass, immutable cache hits, sanitized requests, route exclusions and unchanged cache bounds at root and nested installation paths.');})().catch(error=>{console.error(error);process.exitCode=1;});
