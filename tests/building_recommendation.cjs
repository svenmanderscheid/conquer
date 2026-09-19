'use strict';
const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert');
const source=fs.readFileSync(path.join(__dirname,'..','assets/js/game-overlay.js'),'utf8');
const context={window:{}};vm.createContext(context);vm.runInContext(source,context);
const next=context.window.ConquerBuildingOrder.next;
const building=(level,requirements={})=>({level,requirements});
const state=(buildings,build_queue=[])=>({buildings,build_queue,plot_queue:[]});

assert.equal(next(state({castle:building(1,{wall:1,barrack:1}),wall:building(1),barrack:building(1)})),'castle','The first recommendation advances the castle');
assert.equal(next(state({castle:building(2,{wall:2,trading_post:2}),wall:building(1,{castle:2,quarry:2}),quarry:building(1,{castle:2}),trading_post:building(1,{castle:2,lumber_camp:2}),lumber_camp:building(1,{castle:2})})),'quarry','Nested prerequisites are recommended before the wall');
assert.equal(next(state({castle:building(2,{wall:2,trading_post:2}),wall:building(1,{castle:2,quarry:2}),quarry:building(1,{castle:2}),trading_post:building(1,{castle:2,lumber_camp:2}),lumber_camp:building(1,{castle:2})},[{building_code:'quarry'}])),'lumber_camp','A second slot receives another available prerequisite while quarry is queued');
assert.equal(next(state({castle:building(2,{wall:2,trading_post:2}),wall:building(1,{castle:2,quarry:2}),quarry:building(2,{castle:2}),trading_post:building(2,{castle:2,lumber_camp:2}),lumber_camp:building(2,{castle:2})})),'wall','Completed prerequisites advance to their parent building');
assert.equal(next(state({castle:building(30),wall:building(29),farm:building(12),quarry:building(12)})),'farm','After the castle maximum, the lowest ready building is recommended');
console.log('PASS suggested building order follows castle prerequisites, parallel queues and max-level catch-up.');
