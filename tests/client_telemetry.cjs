'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const code=fs.readFileSync(require('node:path').join(__dirname,'../assets/js/client-telemetry.js'),'utf8');
let checks=0;
function ck(value,label){assert.ok(value,label);checks++;console.log('PASS '+label);}
function environment(saved=new Map()){
    let now=1800000000000,mode='ok';
    const calls=[],events=new Map(),documentEvents=new Map();
    class Clock extends Date {static now(){return now;}}
    const context={Date:Clock,URL,Request,Response,Headers,AbortSignal,JSON,Math,Number,String,Set,Object,Infinity,
        location:{href:'https://game.test/conquer/city#city',origin:'https://game.test',hash:'#city'},
        navigator:{onLine:true,userAgent:'Mozilla Android Chrome/129'},
        document:{visibilityState:'visible',addEventListener:(key,fn)=>documentEvents.set(key,fn)},
        sessionStorage:{getItem:key=>saved.get(key)||null,setItem:(key,val)=>saved.set(key,val)},
        CONQUER_BASE:'/conquer',CONQUER_TELEMETRY:{player:7,world:3,csrf:'private-token',release:'release-test'},
        crypto:{randomUUID:()=>require('node:crypto').randomUUID()},
        addEventListener:(key,fn)=>events.set(key,fn),setInterval:()=>0,
        fetch:async(input,init)=>{
            const url=typeof input==='string'?input:input.url;
            calls.push({url,input,init});
            if(mode==='offline' || mode==='telemetry-fail'&&url.endsWith('/telemetry'))throw new TypeError('private error message');
            if(mode==='timeout'){const error=new Error('private timeout');error.name='TimeoutError';throw error;}
            if(mode==='abort'){const error=new Error('navigated away');error.name='AbortError';throw error;}
            return new Response(JSON.stringify({ok:true,data:{}}),{status:200});
        }};
    context.window=context;vm.createContext(context);vm.runInContext(code,context);
    return {context,calls,events,saved,advance:ms=>now+=ms,mode:value=>mode=value,queue:()=>JSON.parse(saved.get('uok-diagnostics:7')||'[]')};
}
(async()=>{
    const e=environment(),{context:c}=e;
    const body='{"password":"never-log-me"}',headers={'Content-Type':'application/json','X-CSRF-Token':'private-token'};
    const response=await c.fetch('/conquer/api/kingdom/action?token=private-url',{method:'POST',headers,body,credentials:'same-origin'});
    ck(response.status===200&&e.calls[0].init.body===body&&e.calls[0].init.method==='POST','Observer preserves gameplay response, method and body');
    ck(e.calls[0].init.headers.get('X-CSRF-Token')==='private-token'&&e.calls[0].init.headers.get('X-Client-Request-ID'),'Existing authentication headers survive and correlation ID is added');
    ck(e.queue().length===0,'Successful routine polling does not create a diagnostics firehose');
    e.mode('offline');await assert.rejects(c.fetch('/conquer/api/game/state?token=private-url'));
    ck(e.queue()[0].code==='NETWORK_FAILURE'&&e.queue()[0].route==='/api/game/state','Failed request records a query-free connection signal');
    ck(!JSON.stringify(e.queue()).includes('private')&&!JSON.stringify(e.queue()).includes('never-log'),'Request bodies, CSRF values, URL query and exception messages stay out of queue');
    e.advance(45000);e.mode('ok');await c.fetch('/conquer/api/game/state');
    ck(e.queue().some(row=>row.code==='CONNECTION_RESTORED'&&row.duration_ms===45000),'Successful response closes an outage with observed recovery duration');
    await c.ConquerTelemetry.flush();
    ck(e.queue().length===0&&e.calls.at(-1).url==='/conquer/api/telemetry','Batch is removed after successful ingestion');
    ck(JSON.parse(e.calls.at(-1).init.body).events.every(row=>row.world_id===3),'Offline observations preserve the page world for server validation');
    e.mode('abort');await assert.rejects(c.fetch('/conquer/api/game/state'));
    ck(e.queue().length===0,'Intentional AbortError does not masquerade as network loss');
    e.mode('timeout');await assert.rejects(c.fetch('/conquer/api/game/state'));
    ck(e.queue()[0].code==='NETWORK_TIMEOUT','Timeout and network failure are distinct');
    c.navigator.onLine=false;e.advance(30000);const before=e.calls.length;await c.ConquerTelemetry.flush();
    ck(e.calls.length===before&&e.queue().length===1,'Offline ingestion stays queued without repeated sends');
    e.events.get('online')();
    ck(e.queue().some(row=>row.code==='BROWSER_ONLINE')&&!e.queue().some(row=>row.code==='CONNECTION_RESTORED'),'Browser online flag alone does not claim server recovery');
    c.navigator.onLine=true;e.mode('telemetry-fail');e.advance(30000);await c.ConquerTelemetry.flush();
    ck(e.queue().filter(row=>row.code==='NETWORK_FAILURE').length===0,'Collector failure does not recursively report its own request');
    e.events.get('error')({target:null,filename:'https://game.test/conquer/assets/js/game.js?secret=hidden',lineno:24,colno:6,error:{name:'TypeError'},message:'private body'});
    e.events.get('unhandledrejection')({reason:new TypeError('private rejection')});
    ck(e.queue().some(row=>row.code==='JAVASCRIPT_ERROR'&&row.context.file==='/assets/js/game.js')&&e.queue().some(row=>row.code==='UNHANDLED_REJECTION'),'Browser script errors and unhandled rejections retain only safe metadata');
    for(let i=0;i<80;i++){e.advance(11000);c.ConquerTelemetry.report('NETWORK_FAILURE',{route:'/conquer/api/game/state'});}
    ck(e.queue().length===50,'Offline queue is capped at fifty observations');
    e.mode('ok');e.advance(60000);await c.ConquerTelemetry.flush();
    ck(e.queue().some(row=>row.code==='CLIENT_QUEUE_DROPPED'&&row.context.queue_dropped>0),'Queue truncation becomes an explicit diagnostic gap');
    e.advance(86400001);e.events.get('pagehide')();
    ck(e.queue().length===0,'Expired diagnostics are discarded after twenty-four hours');
    const request=new Request('https://game.test/conquer/api/kingdom/action',{method:'POST',body:'safe',headers:{'X-Existing':'yes'}});
    await c.fetch(request);
    ck(e.calls.at(-1).input===request&&e.calls.at(-1).init.headers.get('X-Existing')==='yes','Request objects preserve their original body, method and headers');
    console.log('PASS '+checks+' client telemetry checks');
})().catch(error=>{console.error(error);process.exitCode=1;});
