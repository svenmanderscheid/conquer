'use strict';
// Isolated rally-join behavior regression. All API writes are mocked; no account or database.
// Run: node tests/rally_join.cjs, optionally with PLAYWRIGHT_MODULE and PLAYWRIGHT_CHANNEL.
// Layout and localized integration are covered by rally_windows.cjs.
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const scripts=require('./fixtures/isolated_locale.cjs')('en')+'\n'+['castle-skins','reward-dialog','march-panel'].map(name=>fs.readFileSync(path.join(root,'assets/js',name+'.js'),'utf8')).join('\n');
const html=`<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><body class="mobile-game"><dialog id="game-dialog"><div id="dialog-content"></div></dialog><script>${scripts}</script></body></html>`;

(async()=>{
    const browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})});
    const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[];
    page.on('pageerror',error=>errors.push(error.message));
    await page.route('http://rally-join.test/**',route=>{
        const pathname=new URL(route.request().url()).pathname;
        if(pathname==='/')return route.fulfill({status:200,contentType:'text/html',body:html});
        const file=path.join(root,pathname);
        if(pathname.startsWith('/assets/')&&fs.existsSync(file))return route.fulfill({path:file});
        return route.fulfill({status:404,body:''});
    });
    try{
        await page.goto('http://rally-join.test/');
        await page.evaluate(()=>{
            window.mockTime=Date.UTC(2026,8,30,12);
            const defs=[];
            for(let type=1;type<=3;type++)for(let tier=1;tier<=5;tier++)defs.push({code:50100000+type*100+tier,type,tier,attack:10*tier,gather_carry:10,march_speed:11,monster_rally_speed:15+type*10,pvp_rally_speed:50,monster_rally_power:11*tier,monster_rally_power_single_type:13*tier});
            window.state={troop_defs:defs,troops:Object.fromEntries(defs.map(t=>[t.code,5000])),army_limits:{march_capacity:50000,march_slots:3},city:{player_id:42,world_id:1,coord_x:20,coord_y:20,action_points:200},world:{map_profile:{travel_scale:0.1}},marches:[],players:[],monsters:[],nodes:[]};
            window.sent=[];window.notices=[];window.resolveSend=null;
            window.march=ConquerMarch({
                base:'',esc:value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])),fmt:n=>String(n),unitName:t=>'Unit '+t.code,getState:()=>state,toast:message=>notices.push(message),now:()=>mockTime,
                action:(requestPath,payload)=>{sent.push({path:requestPath,payload});return new Promise(resolve=>window.resolveSend=resolve);},
                openDialog:content=>{const dialog=document.querySelector('#game-dialog');delete dialog.dataset.march;document.querySelector('#dialog-content').innerHTML=content;if(!dialog.open)dialog.showModal();}
            });
            window.stamp=seconds=>new Date(mockTime+seconds*1000).toISOString().slice(0,19).replace('T',' ');
            window.joinOptions=()=>({rally_id:421,rally_target_kind:'monster',rally_launch_at:stamp(300),rally_capacity_remaining:1500,rally_status:'gathering',target:{id:99,coord_x:170,coord_y:260,display_name:'Forschung',castle_level:8},onRallyBack:()=>window.backCalled=true});
            window.openJoin=options=>{localStorage.clear();march.open(99,'rally-join',{...joinOptions(),...options});};
            openJoin();
        });

        assert.equal(await page.locator('#march-selected').textContent(),'1500','Default selection fits remaining capacity');
        assert.equal(await page.locator('#march-capacity').textContent(),'1500');
        assert.equal(await page.locator('#march-confirm').isDisabled(),false);
        assert.match(await page.locator('#march-confirm').textContent(),/Deploy/);
        assert.match(await page.locator('.march-target .march-pvp-note').textContent(),/protection stays active/);
        assert.doesNotMatch(await page.locator('.march-target .march-pvp-note').textContent(),/Angriffe beenden|Joining ends/);
        assert.equal(await page.locator('#march-travel-time').textContent(),'1:53','Monster rally uses its own authoritative speed and world travel scale');
        assert.equal(await page.locator('#march-rally-countdown').textContent(),'5:00','SQL UTC timestamp uses the supplied server-adjusted clock');
        assert.notEqual(await page.locator('.march-target h3').getAttribute('data-user-content'),null);
        assert.equal(await page.locator('.march-target h3').textContent(),'Forschung');
        assert.equal(await page.locator('#march-action-points').textContent(),'0 / 200');
        assert.equal(await page.locator('#march-action-points').evaluate(element=>element.previousElementSibling.textContent),'AP cost / available');
        assert.equal(await page.evaluate(()=>sent.length),0,'Opening or preselecting never sends troops');

        await page.evaluate(()=>{state.players=[{id:99,coord_x:21,coord_y:20,display_name:'Replacement'}];march.update();});
        assert.equal(await page.locator('#march-travel-time').textContent(),'1:53','Map updates must not replace the rally leader snapshot');
        await page.evaluate(()=>{
            document.querySelector('#march-unit-50100105').value='321';
            march.updateRallies([{id:421,target_kind:'monster',status:'gathering',launch_at:stamp(300),capacity:2000,troops:{50100101:1000},leader_player_id:99,participants:[{player_id:17,status:'joining',troops:{50100101:900}},{player_id:18,status:'cancelled',troops:{50100101:9000}}]}]);
        });
        assert.equal(await page.locator('#march-unit-50100105').inputValue(),'321','Polling preserves typed amounts');
        assert.equal(await page.locator('#march-capacity').textContent(),'100','Only active participants use rally capacity');
        assert.equal(await page.locator('#march-confirm').isDisabled(),true);
        assert.match(await page.locator('#march-forecast').textContent(),/Room for 100/);
        await page.evaluate(()=>march.onClick('march-max',{}));
        assert.equal(await page.locator('#march-selected').textContent(),'100');
        assert.equal(await page.locator('#march-confirm').isDisabled(),false);

        await page.evaluate(()=>{openJoin({rally_launch_at:stamp(10)});march.onClick('march-send',{});});
        assert.equal(await page.locator('#march-confirm').isDisabled(),true);
        assert.match(await page.locator('#march-forecast').textContent(),/after departure/);
        assert.equal(await page.evaluate(()=>sent.length),0,'A late army cannot be dispatched');
        await page.evaluate(()=>openJoin({rally_launch_at:stamp(113)}));
        assert.equal(await page.locator('#march-confirm').isDisabled(),false,'Arrival exactly at departure remains valid');
        await page.evaluate(()=>{mockTime+=1000;march.update();});
        assert.equal(await page.locator('#march-confirm').isDisabled(),true,'A subsequent tick invalidates a now-late selection');
        await page.evaluate(()=>openJoin({rally_launch_at:stamp(-1)}));
        assert.equal(await page.locator('#march-confirm').isDisabled(),true);
        assert.match(await page.locator('#march-forecast').textContent(),/has ended/);
        await page.evaluate(()=>openJoin({rally_capacity_remaining:0}));
        assert.equal(await page.locator('#march-selected').textContent(),'0');
        assert.equal(await page.locator('#march-confirm').isDisabled(),true);
        assert.match(await page.locator('#march-forecast').textContent(),/Rally full/);
        await page.evaluate(()=>{openJoin();march.updateRallies([]);});
        assert.equal(await page.locator('#march-confirm').isDisabled(),true);
        assert.match(await page.locator('#march-forecast').textContent(),/no longer gathering/);
        await page.evaluate(()=>{openJoin();march.updateRallies([{id:421,target_kind:'monster',status:'gathering',launch_at:stamp(300),capacity:2000,troops:{50100101:100},leader_player_id:99,participants:[{player_id:42,status:'joining',troops:{50100101:10}}]}]);});
        assert.equal(await page.locator('#march-confirm').isDisabled(),true);
        assert.match(await page.locator('#march-forecast').textContent(),/already taking part/);

        await page.evaluate(()=>openJoin({rally_target_kind:'city',rally_capacity_remaining:null}));
        assert.match(await page.locator('.march-target .march-pvp-note').textContent(),/Joining ends your city protection/);
        assert.equal(await page.locator('#march-travel-time').textContent(),'0:56','PvP rally uses PvP rally speed');
        assert.equal(await page.locator('#march-selected').textContent(),'50000','City rally still respects own march capacity');
        for(const kind of ['city','territory','monster']){
            await page.evaluate(kind=>{
                openJoin({rally_target_kind:kind,rally_capacity_remaining:50000});
                march.updateRallies([{id:421,target_kind:kind,status:'gathering',launch_at:stamp(300),capacity:3500000,troops:{50100101:3000000},leader_player_id:99,participants:[{player_id:17,status:'joining',troops:{50100101:490000}},{player_id:18,status:'returned',troops:{50100101:50000}}]}]);
            },kind);
            assert.equal(await page.locator('#march-capacity').textContent(),'10000',kind+' rally retains its saved total capacity after polling');
            assert.equal(await page.locator('#march-confirm').isDisabled(),true,'Existing selection must be reduced after other allies fill the rally');
            await page.evaluate(()=>march.onClick('march-max',{}));
            assert.equal(await page.locator('#march-selected').textContent(),'10000');
        }
        await page.evaluate(()=>{
            openJoin({rally_target_kind:'city',rally_capacity_remaining:null});
            march.updateRallies([{id:421,target_kind:'city',status:'gathering',launch_at:stamp(300),capacity:null,troops:{50100101:3000000},leader_player_id:99,participants:[]}]);
        });
        assert.equal(await page.locator('#march-capacity').textContent(),'50000','Legacy city rally without a capacity snapshot retains the personal limit');
        for(const kind of ['rally','monsters']){
            await page.evaluate(kind=>{
                localStorage.clear();
                state.buildings={hall_of_alliance:{rally_capacity:{total:1200}}};
                state.monsters=[{id:88,coord_x:21,coord_y:20,hp_current:100,hp_max:100,monster_type:'rally',definition:{name:'Golem',level:1,art:'golem',required_power:10000,resource_reward:{},drops:[]}}];
                march.open(kind==='rally'?99:88,kind);
            },kind);
            assert.equal(await page.locator('#march-capacity').textContent(),'1200',kind+' start limits the captain to the server-provided Hall capacity');
            await page.evaluate(()=>march.onClick('march-max',{}));
            assert.equal(await page.locator('#march-selected').textContent(),'1200');
        }
        await page.evaluate(()=>{delete state.buildings;state.monsters=[];});
        await page.evaluate(()=>{openJoin();march.onClick('march-send',{});march.onClick('march-send',{});march.update();});
        await page.waitForFunction(()=>sent.length===1);
        assert.equal(await page.locator('#march-confirm').isDisabled(),true);
        assert.deepEqual(await page.evaluate(()=>({path:sent[0].path,id:sent[0].payload.rally_id,count:Object.values(sent[0].payload.troops).reduce((sum,n)=>sum+n,0)})),{path:'rally/join',id:421,count:1500});
        await page.evaluate(()=>{document.querySelector('#march-confirm').textContent='Confirming';march.update();march.onClick('march-send',{});});
        assert.equal(await page.evaluate(()=>sent.length),1,'Temporary busy button markup and ticks cannot clear the submission guard');
        await page.evaluate(()=>resolveSend(null));
        await page.waitForFunction(()=>!document.querySelector('#march-confirm').disabled);
        await page.evaluate(()=>{openJoin();march.onClick('march-send',{});});
        await page.waitForFunction(()=>sent.length===2);
        await page.evaluate(()=>resolveSend({joined:true}));
        await page.waitForFunction(()=>document.querySelector('#march-forecast').textContent.includes('already taking part'));
        assert.equal(await page.locator('#march-confirm').isDisabled(),true,'A successful join cannot be resubmitted from an open mock dialog');
        await page.evaluate(()=>{openJoin();march.onClick('march-rally-back',{});});
        assert.equal(await page.evaluate(()=>backCalled),true);
        assert.deepEqual(errors,[],'No browser errors');
        console.log('PASS: capacity and defaults; monster/PvP speed and protection; leader snapshot; input-preserving polling; late/expired/full/unavailable/already-joined states; exact arrival boundary; explicit dispatch; duplicate guard; failure recovery; success; back.');
    }finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
