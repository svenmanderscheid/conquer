'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const {test}=require('node:test');
const source=fs.readFileSync(require('node:path').join(__dirname,'../service-worker.js'),'utf8');

function worker(base='https://kingdom.test/conquer/'){
    const listeners={},notices=[],opened=[];
    let windows=[];
    const self={registration:{scope:base,showNotification:async(title,options)=>notices.push({title,...options})},
        addEventListener:(name,fn)=>listeners[name]=fn,
        clients:{matchAll:async()=>windows,openWindow:async url=>opened.push(url)}};
    vm.runInNewContext(source,{self,URL,Set,Request,Response,Headers});
    return {listeners,notices,opened,setWindows:value=>windows=value,
        async push(value,invalid=false){let waiting;listeners.push({data:invalid?{json(){throw Error('invalid');}}:{json:()=>value},waitUntil:p=>waiting=p});await waiting;},
        async click(url){let waiting,closed=false;listeners.notificationclick({notification:{data:{url},close:()=>closed=true},waitUntil:p=>waiting=p});await waiting;assert(closed);}};
}

test('push uses scope-relative approved branding, bounded content and generic defaults',async()=>{
    for(const base of ['https://kingdom.test/','https://kingdom.test/conquer/']){
        const w=worker(base);
        await w.push({title:'Untrusted title',body:'A building is ready.',tag:'uok-42',url:'city#reports'});
        assert.equal(w.notices[0].title,'Union of Kingdoms');
        assert.equal(w.notices[0].body,'A building is ready.');
        assert.equal(w.notices[0].icon,base+'assets/icons/conquer-192.png');
        assert.equal(w.notices[0].data.url,base+'city#reports');
        assert.equal(w.notices[0].tag,'uok-42');
        await w.push(null,true);
        assert.equal(w.notices[1].body,'There is news from your kingdom. Open the game to view it.');
        await w.push({body:'x'.repeat(1000),tag:'bad tag!',url:'city#city'});
        assert.equal(w.notices[2].body.length,240);
        assert.equal(w.notices[2].tag,'uok-update');
    }
});

test('notification clicks cannot leave the scope or perform authenticated actions',async()=>{
    const w=worker();
    for(const target of ['https://evil.test/city#city','//evil.test/city#city','javascript:alert(1)',
        '/city#city','/conquer-other/city#city','api/kingdom/action','admin','city?action=delete#city',
        'city#unknown','https://user:pass@kingdom.test/conquer/city#city','../city#city']){
        await w.click(target);
        assert.equal(w.opened.at(-1),'https://kingdom.test/conquer/city#city',target);
    }
});

test('click reuses only an in-scope game window, focusing after navigation',async()=>{
    const w=worker(),actions=[];
    w.setWindows([
        {url:'https://kingdom.test/admin',navigate:()=>assert.fail('admin was navigated')},
        {url:'https://kingdom.test/other/city',navigate:()=>assert.fail('other scope was navigated')},
        {url:'https://evil.test/conquer/city',navigate:()=>assert.fail('other origin was navigated')},
        {url:'https://kingdom.test/conquer/city#world',navigate:async url=>{actions.push(url);return {focus:async()=>actions.push('focus')};}},
    ]);
    await w.click('city#reports');
    assert.deepEqual(actions,['https://kingdom.test/conquer/city#reports','focus']);
    assert.deepEqual(w.opened,[]);
});

test('failed navigation falls back to opening the game',async()=>{
    const w=worker();
    w.setWindows([{url:'https://kingdom.test/conquer/city',navigate:async()=>{throw Error('closed');}}]);
    await w.click('city#city');
    assert.deepEqual(w.opened,['https://kingdom.test/conquer/city#city']);
});
