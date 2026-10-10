'use strict';
// Never put authenticated documents, API responses, admin screens, or auth requests in CacheStorage.
const BUILD='union-of-kingdoms-public-v13-device-notifications';
const ROOT=new URL(self.registration.scope),PREFIX=ROOT.pathname,CACHE=BUILD+':'+PREFIX;
const OFFLINE=new URL('offline.html',ROOT).href;
const PRELOAD=['offline.html','favicon.ico','apple-touch-icon.png','assets/icons/conquer-32.png','assets/icons/conquer-maskable-512.png','assets/icons/conquer-192.png','assets/icons/conquer-512.png'];
const STATIC=new Set([
    ...PRELOAD,'assets/js/localization.js','assets/css/localization.css','assets/css/fantasy-fonts.css',
    'assets/fonts/bree-serif-v18-400-latin.woff2','assets/fonts/bree-serif-v18-400-latin-ext.woff2',
    'assets/fonts/nunito-v32-latin.woff2','assets/fonts/nunito-v32-latin-ext.woff2',
    'assets/css/game.css','assets/css/game-theme.css','assets/css/admin-backoffice.css',
    'assets/css/community-panel.css','assets/css/progression-panel.css','assets/css/defense-panel.css',
    'assets/js/game.js','assets/js/community-panel.js','assets/js/progression-panel.js','assets/js/defense-panel.js',
]);
const MAX_ENTRIES=32,MAX_BYTES=512*1024;
function localPath(url){return url.origin===ROOT.origin&&url.pathname.startsWith(PREFIX)?url.pathname.slice(PREFIX.length):null;}
function forbidden(path){return path===null||/^(?:api|admin|auth)(?:\/|$)/i.test(path)||/^(?:index|manifest)\.php$/i.test(path);}
function immutableLocale(url){return url.search===''&&/^locale-assets\/(?:en|de|fr|sources-de)\.[a-f0-9]{20}\.(?:json|js)$/.test(localPath(url)||'');}
function cacheable(request){const url=new URL(request.url),path=localPath(url);return request.method==='GET'&&request.mode!=='navigate'&&!request.headers.has('Authorization')&&!forbidden(path)&&(immutableLocale(url)||(STATIC.has(path)&&[...url.searchParams.keys()].every(k=>k==='v')));}
async function publicFetch(request){return fetch(new Request(request.url,{credentials:'omit',cache:immutableLocale(new URL(request.url))?'default':'no-cache',mode:'same-origin'}));}
async function store(request,response){
    if(!response.ok||response.type==='opaque'||response.redirected||/private|no-store/i.test(response.headers.get('Cache-Control')||'')||response.headers.has('Set-Cookie'))return;
    const type=response.headers.get('Content-Type')||'';
    if(/text\/html/i.test(type)&&new URL(request.url||request).href!==OFFLINE)return;
    const length=Number(response.headers.get('Content-Length')||0);if(length>MAX_BYTES)return;
    const copy=response.clone();if((await copy.arrayBuffer()).byteLength>MAX_BYTES)return;
    const cache=await caches.open(CACHE);await cache.put(new Request(request.url||request,{credentials:'omit'}),response.clone());const keys=await cache.keys();
    if(keys.length>MAX_ENTRIES){for(const key of keys){if(new URL(key.url).href===OFFLINE)continue;await cache.delete(key);if((await cache.keys()).length<=MAX_ENTRIES)break;}}
}
async function offlinePage(){
    const cached=await caches.match(OFFLINE);
    if(!cached)return new Response('Offline',{status:503,headers:{'Content-Type':'text/plain; charset=utf-8'}});
    // Navigation keeps its requested URL; resolve offline assets from the install scope.
    const base=ROOT.href.replace(/&/g,'&amp;').replace(/"/g,'&quot;');
    const html=(await cached.text()).replace(/<head>/i,`<head><base href="${base}">`);
    const headers=new Headers(cached.headers);
    for(const name of ['Content-Length','Content-Encoding','ETag'])headers.delete(name);
    return new Response(html,{status:cached.status,statusText:cached.statusText,headers});
}
self.addEventListener('install',event=>event.waitUntil((async()=>{
    const cache=await caches.open(CACHE);
    for(const path of PRELOAD){const request=new Request(new URL(path,ROOT),{credentials:'omit'});try{const response=await publicFetch(request);if(response.ok)await store(request,response);}catch{if(path==='offline.html')throw new Error('Offline fallback unavailable');}}
    if(!await cache.match(OFFLINE))throw new Error('Offline fallback unavailable');
    await self.skipWaiting();
})()));
self.addEventListener('activate',event=>event.waitUntil((async()=>{
    for(const key of await caches.keys())if((key.startsWith('conquer-public-')||key.startsWith('union-of-kingdoms-public-'))&&key.endsWith(':'+PREFIX)&&key!==CACHE)await caches.delete(key);
    await self.clients.claim();
})()));
self.addEventListener('fetch',event=>{
    const request=event.request,url=new URL(request.url),path=localPath(url);
    // Authenticated/administrative routes always reach the browser network stack untouched.
    if(request.method!=='GET'||forbidden(path)||url.origin!==ROOT.origin||request.headers.has('Authorization'))return;
    if(request.mode==='navigate'){
        event.respondWith((async()=>{try{return await fetch(request);}catch{return await offlinePage();}})());return;
    }
    if(!cacheable(request))return;
    event.respondWith((async()=>{
        const cache=await caches.open(CACHE);
        // Content hashes make these public files reusable without a network round trip.
        if(immutableLocale(url)){const cached=await cache.match(request);if(cached)return cached;}
        try{const response=await publicFetch(request);if(response.ok)await store(request,response);return response;}catch{return await cache.match(request)||Response.error();}
    })());
});

// Push payloads contain generic, server-localized notices, never player messages or resources.
// A notification click only opens a read-only game view; it cannot submit a game action.
function notificationTarget(value){
    const fallback=new URL('city#city',ROOT).href;
    try{
        const url=new URL(typeof value==='string'?value:'city#city',ROOT);
        if(url.origin!==ROOT.origin||url.username||url.password||url.search||localPath(url)!=='city')return fallback;
        if(!['#city','#reports','#settings','#worlds','#army','#research'].includes(url.hash))return fallback;
        return url.href;
    }catch{return fallback;}
}
function notificationText(value,fallback,max){return typeof value==='string'&&value.trim()?value.slice(0,max):fallback;}
self.addEventListener('push',event=>event.waitUntil((async()=>{
    let payload={};
    try{const value=event.data?.json();if(value&&typeof value==='object'&&!Array.isArray(value))payload=value;}catch{}
    const title='Union of Kingdoms';
    const body=notificationText(payload.body,'There is news from your kingdom. Open the game to view it.',240);
    const tag=typeof payload.tag==='string'&&/^[a-zA-Z0-9_-]{1,80}$/.test(payload.tag)?payload.tag:'uok-update';
    await self.registration.showNotification(title,{
        body,tag,icon:new URL('assets/icons/conquer-192.png',ROOT).href,
        badge:new URL('assets/icons/conquer-32.png',ROOT).href,
        data:{url:notificationTarget(payload.url)},
    });
})()));
self.addEventListener('notificationclick',event=>{
    event.notification.close();
    const target=notificationTarget(event.notification.data?.url);
    event.waitUntil((async()=>{
        const windows=await self.clients.matchAll({type:'window',includeUncontrolled:true});
        for(const client of windows){
            try{
                if(localPath(new URL(client.url))!=='city')continue;
                // Do not redirect an admin, another scoped app, or an unrelated website.
                const navigated=await client.navigate(target);
                if(navigated){await navigated.focus();return;}
            }catch{}
        }
        await self.clients.openWindow(target);
    })());
});
