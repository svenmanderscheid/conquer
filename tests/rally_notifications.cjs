'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const window={};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/rally-notifications.js'),'utf8'),{window});
let time=Date.parse('2030-01-01T12:00:00Z');
const notices=[],context={playerId:7,worldId:1,allianceId:2};
const tracker=window.ConquerRallyNotifications({now:()=>time,announce:rows=>notices.push(Array.from(rows,r=>r.id))});
const row=(id,extra={})=>({id,world_id:1,result:{alliance_id:2},leader_player_id:8,status:'gathering',created_at:new Date(time).toISOString(),...extra});
const observe=(rows,extra={})=>tracker.observe(rows,{...context,serverTime:time/1000,...extra});
observe([row(1)]);assert.deepEqual(notices,[],'Initial rallies are a silent baseline');
time+=1000;observe([row(1),row(2),row(3,{leader_player_id:7})]);
assert.deepEqual(notices,[[2,3]],'Both member and leader receive fresh rallies in a batch');
observe([row(3),row(2)]);observe([]);observe([row(1),row(2)]);
assert.equal(notices.length,1,'Repeated, reordered and temporarily absent rows cannot replay');
observe([row(4,{status:'marching'})]);assert.deepEqual(notices.at(-1),[4],'Fast launches still announce once');
observe([row(5,{status:'returning'}),row(6,{status:'cancelled'}),row(7,{created_at:''}),row(8,{created_at:'2030-01-01 11:00:00'}),row(9,{world_id:2}),row(10,{result:{alliance_id:3}})]);
assert.equal(notices.length,2,'Historical, invalid, closed and out-of-scope rallies are ignored');
tracker.pause();observe([row(11)]);assert.equal(notices.length,2,'Returning to the app starts a quiet baseline');
time+=1000;observe([row(12)]);assert.deepEqual(notices.at(-1),[12]);
time+=91000;observe([row(13)]);assert.equal(notices.length,3,'Long reconnect does not announce an old backlog');
observe([row(14)],{allianceId:0});observe([row(15)],{allianceId:3});observe([row(16)],{worldId:2});observe([row(17)],{playerId:9});
assert.equal(notices.length,3,'Player, world and alliance changes reset notification scope');
for(const language of ['en','de','fr','lb']){
 const catalog=JSON.parse(fs.readFileSync(path.join(__dirname,'../data/i18n',language+'.json'),'utf8'));
 assert.deepEqual(catalog['rally.notice.started'].match(/\{\w+\}/g).sort(),['{monster}','{player}'],`${language}: complete notice placeholders`);
}
console.log('PASS rally notices: leader/member, baseline, duplicates, fast launch, timestamps, scope, app return and all languages.');
