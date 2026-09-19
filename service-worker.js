'use strict';
// Never put authenticated documents, API responses, admin screens, or auth requests in CacheStorage.
const BUILD='conquer-public-v3';
const ROOT=new URL(self.registration.scope),PREFIX=ROOT.pathname,CACHE=BUILD+':'+PREFIX;
const OFFLINE=new URL('offline.html',ROOT).href;
const PRELOAD=['offline.html','assets/icons/conquer.svg','assets/icons/conquer-192.png','assets/icons/conquer-512.png'];
const STATIC=new Set([
    ...PRELOAD,'assets/js/localization.js','assets/css/localization.css','assets/css/fantasy-fonts.css',
    'assets/fonts/almendra-400-latin.woff2','assets/fonts/almendra-400-latin-ext.woff2',
    'assets/fonts/almendra-700-latin.woff2','assets/fonts/almendra-700-latin-ext.woff2',
    'assets/fonts/lora-latin.woff2','assets/fonts/lora-latin-ext.woff2',
    'data/i18n/de.json','data/i18n/fr.json','data/i18n/en.json',
    'assets/css/game.css','assets/css/game-theme.css','assets/css/admin-backoffice.css',
    'assets/css/community-panel.css','assets/css/progression-panel.css','assets/css/defense-panel.css',
    'assets/js/game.js','assets/js/community-panel.js','assets/js/progression-panel.js','assets/js/defense-panel.js',
]);
const MAX_ENTRIES=32,MAX_BYTES=512*1024;
function localPath(url){return url.origin===ROOT.origin&&url.pathname.startsWith(PREFIX)?url.pathname.slice(PREFIX.length):null;}
function forbidden(path){return path===null||/^(?:api|admin|auth)(?:\/|$)/i.test(path)||/^(?:index|manifest)\.php$/i.test(path);}
function cacheable(request){const url=new URL(request.url),path=localPath(url);return request.method==='GET'&&request.mode!=='navigate'&&!request.headers.has('Authorization')&&!forbidden(path)&&STATIC.has(path)&&[...url.searchParams.keys()].every(k=>k==='v');}
async function publicFetch(request){return fetch(new Request(request.url,{credentials:'omit',cache:'no-cache',mode:'same-origin'}));}
async function store(request,response){
    if(!response.ok||response.type==='opaque'||response.redirected||/private|no-store/i.test(response.headers.get('Cache-Control')||'')||response.headers.has('Set-Cookie'))return;
    const type=response.headers.get('Content-Type')||'';
    if(/text\/html/i.test(type)&&new URL(request.url||request).href!==OFFLINE)return;
    const length=Number(response.headers.get('Content-Length')||0);if(length>MAX_BYTES)return;
    const copy=response.clone();if((await copy.arrayBuffer()).byteLength>MAX_BYTES)return;
    const cache=await caches.open(CACHE);await cache.put(new Request(request.url||request,{credentials:'omit'}),response.clone());const keys=await cache.keys();
    if(keys.length>MAX_ENTRIES){for(const key of keys){if(new URL(key.url).href===OFFLINE)continue;await cache.delete(key);if((await cache.keys()).length<=MAX_ENTRIES)break;}}
}
self.addEventListener('install',event=>event.waitUntil((async()=>{
    const cache=await caches.open(CACHE);
    for(const path of PRELOAD){const request=new Request(new URL(path,ROOT),{credentials:'omit'});try{const response=await publicFetch(request);if(response.ok)await store(request,response);}catch{if(path==='offline.html')throw new Error('Offline fallback unavailable');}}
    if(!await cache.match(OFFLINE))throw new Error('Offline fallback unavailable');
    await self.skipWaiting();
})()));
self.addEventListener('activate',event=>event.waitUntil((async()=>{
    for(const key of await caches.keys())if(key.startsWith('conquer-public-')&&key.endsWith(':'+PREFIX)&&key!==CACHE)await caches.delete(key);
    await self.clients.claim();
})()));
self.addEventListener('fetch',event=>{
    const request=event.request,url=new URL(request.url),path=localPath(url);
    // Authenticated/administrative routes always reach the browser network stack untouched.
    if(request.method!=='GET'||forbidden(path)||url.origin!==ROOT.origin||request.headers.has('Authorization'))return;
    if(request.mode==='navigate'){
        event.respondWith((async()=>{try{return await fetch(request);}catch{return await caches.match(OFFLINE)||new Response('Offline',{status:503,headers:{'Content-Type':'text/plain; charset=utf-8'}});}})());return;
    }
    if(!cacheable(request))return;
    event.respondWith((async()=>{try{const response=await publicFetch(request);if(response.ok)await store(request,response);return response;}catch{return await(await caches.open(CACHE)).match(request)||Response.error();}})());
});
