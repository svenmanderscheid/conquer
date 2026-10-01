'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../assets/js/game.js'),'utf8');
// Exercise the same poll/event acceptance functions without a game session or database.
const helpers=source.slice(source.indexOf('    function rallyContext()'),source.indexOf('    async function refresh(renderPage'));
assert(helpers.includes('function acceptRallies('));
const fixture=vm.runInNewContext(`(()=>{
    let state,kingdom,allianceRallies=[],rallyRequestSequence=0,rallyAppliedSequence=0,rallyDataScope='';
    const window={CONQUER_WORLD:1},updates=[],marchUpdates=[];
    const rallyPanel={sync:rows=>updates.push(rows.map(row=>row.id))},marchPanel={updateRallies:rows=>marchUpdates.push(rows.map(row=>row.id))};
    ${helpers}
    return {beginRallyRequest,acceptRallies,syncRallyScope,
        set:(player,world,alliance)=>{state={city:{player_id:player,world_id:world}};kingdom={alliance:alliance?{id:alliance}:null};},
        ids:()=>allianceRallies.map(row=>row.id).join(','),updates,marchUpdates};
})()`);
const row=(id,world=1,alliance=2)=>({id,world_id:world,result:{alliance_id:alliance}});
const initial=fixture.beginRallyRequest();fixture.set(7,1,2);
assert.equal(fixture.acceptRallies({rallies:[row(1)]},initial),true,'First load can establish the player and alliance');
assert.equal(fixture.ids(),'1');
const oldPoll=fixture.beginRallyRequest(),eventRefresh=fixture.beginRallyRequest();
assert.equal(fixture.acceptRallies({rallies:[row(2)]},eventRefresh),true);
assert.equal(fixture.acceptRallies({rallies:[row(1)]},oldPoll),false,'A slow poll cannot undo a newer rally event');
assert.equal(fixture.ids(),'2');
fixture.syncRallyScope();assert.equal(fixture.ids(),'2','A temporary request failure retains the same-scope snapshot');
const oldAlliance=fixture.beginRallyRequest();fixture.set(7,1,3);fixture.syncRallyScope();
assert.equal(fixture.ids(),'','Alliance change clears the old menu even when the next list request fails');
assert.equal(fixture.acceptRallies({rallies:[row(3)]},oldAlliance),false,'Late old-alliance responses stay discarded');
assert.equal(fixture.acceptRallies({rallies:[row(4,1,3),row(5),row(6,2,3)]},fixture.beginRallyRequest()),true);
assert.equal(fixture.ids(),'4','Response rows must belong to the current world and alliance');
const oldWorld=fixture.beginRallyRequest();fixture.set(7,2,3);
assert.equal(fixture.acceptRallies({rallies:[row(4,1,3)]},oldWorld),false);assert.equal(fixture.ids(),'');
assert.equal(fixture.acceptRallies({rallies:[row(7,2,3)]},fixture.beginRallyRequest()),true);
const oldPlayer=fixture.beginRallyRequest();fixture.set(8,2,3);
assert.equal(fixture.acceptRallies({rallies:[row(7,2,3)]},oldPlayer),false);assert.equal(fixture.ids(),'');
fixture.set(8,2,null);fixture.syncRallyScope();
assert.equal(fixture.acceptRallies({rallies:[row(8,2,3)]},fixture.beginRallyRequest()),true);assert.equal(fixture.ids(),'','Leaving the alliance hides all previous rallies');
assert.deepEqual(Array.from(fixture.updates,ids=>ids.join(',')),Array.from(fixture.marchUpdates,ids=>ids.join(',')),'List and troop picker receive identical accepted snapshots');
console.log('PASS rally HUD state: first load, poll/event ordering, transient failure, alliance/world/player changes, stale response filtering.');
