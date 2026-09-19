'use strict';
// Read-only catalogue and renderer regression checks. No account or database mutations.
const fs = require('fs'), path = require('path'), vm = require('vm'), assert = require('assert'), cp = require('child_process');
const root = path.resolve(__dirname,'..');
const trees = ['production','battle','advanced'];
const expected = {production:[34,245],battle:[43,306],advanced:[40,400]};
const defs = trees.flatMap(tree => JSON.parse(fs.readFileSync(path.join(root,'data/research',tree+'.json'),'utf8')).nodes.map(node => ({...node,tree})));
function check(label, fn) { fn(); process.stdout.write('PASS '+label+'\n'); }
check('all three complete source catalogues retain 117 technologies and 951 levels', () => {
    assert.equal(defs.length,117); assert.equal(defs.reduce((sum,node)=>sum+node.levels.length,0),951);
    for (const tree of trees) { const nodes=defs.filter(node=>node.tree===tree); assert.deepEqual([nodes.length,nodes.reduce((sum,node)=>sum+node.levels.length,0)],expected[tree]); }
    assert.equal(new Set(defs.map(node=>node.code)).size,defs.length);
});
const byCode = new Map(defs.map(node=>[node.code,node]));
check('every requirement resolves to a real reachable level; opening graph contains no cycle', () => {
    for (const node of defs) {
        assert.equal(node.max_level,node.levels.length);
        node.levels.forEach((entry,index) => {
            assert.equal(entry.level,index+1);
            for (const req of entry.requirements) {
                assert(Number.isInteger(req.level) && req.level>0);
                if (req.type==='research') { assert(byCode.has(req.code),'Unknown predecessor '+req.code); assert(req.level<=byCode.get(req.code).max_level); }
            }
        });
    }
    const done=new Set();
    function visit(code,path=new Set()) { if(done.has(code))return; assert(!path.has(code),'Circular prerequisite at '+code);path.add(code);for(const req of byCode.get(code).levels[0].requirements.filter(req=>req.type==='research'&&req.code!==code))visit(req.code,new Set(path));done.add(code); }
    defs.forEach(node=>visit(node.code));
});
check('both resource protection technologies remain distinct and legacy advanced levels retain their meaning', () => {
    assert.equal(byCode.get('resource_protect').tree,'advanced'); assert.equal(byCode.get('resource_protect').max_level,10);
    assert.equal(byCode.get('production_resource_protect').tree,'production'); assert.equal(byCode.get('production_resource_protect').source_code,'resource_protect');
    assert.equal(byCode.get('production_resource_protect').max_level,5); assert.equal(byCode.get('production_resource_protect').stat,'resource_protect');
    assert(defs.filter(node=>node.tree==='production').every(node=>node.levels.every(entry=>entry.requirements.every(req=>req.code!=='resource_protect'))));
});
check('original development academy thresholds and predecessor levels are restored', () => {
    const academy = code=>byCode.get(code).levels.map(entry=>entry.requirements.find(req=>req.type==='academy').level);
    assert.deepEqual(academy('research_speed'),[17,17,18,18,19]); assert.deepEqual(academy('construction_speed'),[19,19,20,20,21]);
    assert.deepEqual(byCode.get('research_speed').levels[0].requirements.filter(req=>req.type==='research').map(req=>[req.code,req.level]),[['infantry_storage',2],['ranged_storage',2],['cavalry_storage',2]]);
    assert.deepEqual(byCode.get('construction_speed').levels[0].requirements.filter(req=>req.type==='research').map(req=>[req.code,req.level]),[['research_speed',2]]);
});
check('actual PHP loader exposes all nodes without silently overwriting an ID', () => {
    const php=process.env.PHP_BINARY || (process.platform==='win32'?'C:/xampp/php/php.exe':'php');
    const code="define('ROOT_DIR',getcwd());require 'src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();echo json_encode(array_values(\\Conquer\\Game\\Research\\ResearchData::allNodes()));";
    const loaded=JSON.parse(cp.execFileSync(php,['-r',code],{cwd:root,encoding:'utf8'}));
    assert.equal(loaded.length,117);assert.equal(loaded.reduce((sum,node)=>sum+node.levels.length,0),951);
    assert.deepEqual(loaded.map(node=>node.code).sort(),defs.map(node=>node.code).sort());
});
const env={window:{}};
vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/research-tree.js'),'utf8'),env);
const ui=env.window.ConquerResearch;
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const state={research_defs:defs,research:{},research_queue:[],buildings:{academy:{level:1}},city:{food:5000,lumber:5000,stone:5000,gold:5000},server_time:Date.now()/1000};
const options={state,base:'/conquer',esc,fmt:String,duration:value=>value+' Sek.',countdown:String};
const branchTrees=[['economy','production'],['military','battle'],['development','advanced']];
check('all 117 discoveries belong to explicit meaningful chapters exactly once',()=>{
    for(const [branch,tree] of branchTrees){
        const chapters=ui.getChapters(defs,branch),codes=chapters.flatMap(ch=>Array.from(ch.codes));
        assert(!chapters.some(ch=>ch.id.startsWith('extra-')),'Source technology lacks a designed chapter');
        assert.equal(new Set(codes).size,codes.length);
        assert.deepEqual(codes.sort(),defs.filter(node=>node.tree===tree).map(node=>node.code).sort());
    }
});
check('each continuous tab renders every technology once at every viewport without pages',()=>{
    for(const viewport of [{width:1000,height:520},{width:276,height:530},{width:500,height:430},{width:600,height:280}]){
        for(const [branch,tree] of branchTrees){
            ui.selectBranch(branch);const html=ui.render({...options,viewport}),info=ui.getTreeInfo();
            assert.equal(info.branch,branch);assert.equal(info.columns,3);assert(info.rows>3);assert.equal(info.direction,'vertical');
            assert.equal((html.match(/class="rt-node rt-/g)||[]).length,info.codes.length);
            assert(!html.includes('NaN'));assert(!html.includes('Infinity'));assert(html.includes('class="rt-scroll"'));
            assert(!html.includes('rt-pagination'));assert(!html.includes('research-page'));assert(!html.includes('research-chapter'));
            assert(html.includes('role="region"'));assert(html.includes('tabindex="0"'));
            for(const code of info.codes){
                const actual=byCode.get(code).levels[0].requirements.filter(req=>req.type==='research'&&req.code!==code);
                const displayed=info.edges.filter(edge=>edge.to===code);
                assert.deepEqual(Array.from(displayed,edge=>[edge.from,edge.level]).sort(),actual.map(req=>[req.code,req.level]).sort());
                for(const edge of displayed)assert.equal(edge.external,!info.codes.includes(edge.from));
            }
            assert.equal(new Set(info.codes).size,info.codes.length);
            assert.deepEqual(Array.from(info.codes).sort(),defs.filter(node=>node.tree===tree).map(node=>node.code).sort());
        }
    }
});
check('three troop columns progress downwards from HP to DEF to ATK even in short landscape',()=>{
    for(const viewport of [{width:1000,height:520},{width:276,height:280}]){
        ui.selectBranch('military');const html=ui.render({...options,viewport});
        ['infantry','ranged','cavalry'].forEach((type,col)=>['hp','def','atk'].forEach((stat,row)=>{
            assert(html.includes(`data-id="${type}_${stat}" data-row="${row}" data-col="${col}"`));
        }));
        assert(html.indexOf('data-id="infantry_hp"')<html.indexOf('data-id="infantry_def"'));
        assert(html.indexOf('data-id="infantry_def"')<html.indexOf('data-id="infantry_atk"'));
    }
});
check('deep links reach distant nodes directly, including before first render',()=>{
    const fresh={window:{}};vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/research-tree.js'),'utf8'),fresh);
    const freshUi=fresh.window.ConquerResearch;
    assert(freshUi.focus('march_limit',defs));assert.equal(freshUi.getBranch(),'military');freshUi.render(options);assert(freshUi.getTreeInfo().codes.includes('march_limit'));
    for(const node of defs){assert(ui.focus(node.code,defs));const html=ui.render({...options,viewport:{width:276,height:530}});assert(ui.getTreeInfo().codes.includes(node.code));assert(html.includes(`rt-focused"`));}
    assert.equal(ui.focus('missing',defs),false);
});
check('global search shows every result without pagination and links back into the tree',()=>{
    ui.selectBranch('military');ui.setSearch('Wissensdurst');let html=ui.render(options);
    assert(html.includes('data-action="research-focus" data-id="research_speed"'));assert(html.includes('data-action="research-focus" data-id="advanced_research_speed"'));
    assert.equal(ui.getTreeInfo().edges.length,0);assert.equal((html.match(/class="rt-board"/g)||[]).length,1);
    ui.setSearch('Infanterie');html=ui.render({...options,viewport:{width:276,height:280}});const info=ui.getTreeInfo();
    assert(info.codes.length>6);assert(!html.includes('data-action="research-dialog" data-id='));
    const expectedMatches=defs.filter(node=>[ui.title(node),node.name,node.code,ui.bonus(node,node.levels[0]).label].join(' ').toLowerCase().includes('infanterie')).map(node=>node.code);
    assert.deepEqual(Array.from(info.codes).sort(),expectedMatches.sort());
    ui.setSearch('not_a_real_technology');html=ui.render(options);assert(html.includes('Keine Forschung gefunden'));assert.equal(ui.getTreeInfo().codes.length,0);
    ui.setSearch('');
});
check('new catalogue entries remain visible without adding a layout slot',()=>{
    const extra={...byCode.get('food_production'),code:'future_production'};
    ui.selectBranch('economy');const html=ui.render({...options,state:{...state,research_defs:[...defs,extra]}});
    assert(html.includes('data-id="future_production"'));assert.equal(ui.getTreeInfo().codes.length,35);
});
check('all names remain readable and bonus formats match actual effects', () => {
    for(const node of defs) { assert(!ui.title(node).includes('_')); assert.notEqual(ui.title(node),node.code.replace(/_/g,' ')); }
    assert.equal(ui.bonus(byCode.get('infantry_training_cost'),byCode.get('infantry_training_cost').levels[0]).value,'−1 %');
    assert.equal(ui.bonus(byCode.get('march_limit'),byCode.get('march_limit').levels[0]).value,'+1 Marschplatz');
    assert(!ui.bonus(byCode.get('march_limit'),byCode.get('march_limit').levels[0]).value.includes('%'));
    assert(ui.bonus(byCode.get('hospital_capacity'),byCode.get('hospital_capacity').levels[0]).value.includes('1 %'));
    assert(ui.bonus(byCode.get('castle_defending_infantrys_hp'),byCode.get('castle_defending_infantrys_hp').levels[0]).note.includes('geschützt'));
});
check('illustrated details retain every actual research and Academy prerequisite',()=>{
    const node=byCode.get('research_speed'),html=ui.renderRequirements({requirements:node.levels[0].requirements,state,base:'/conquer',esc});
    for(const req of node.levels[0].requirements){const code=req.type==='academy'?'academy':req.code;assert(html.includes(`data-id="${code}"`));assert(html.includes(`Stufe ${req.level} benötigt`));}
    assert.equal((html.match(/class="rt-node-art/g)||[]).length,node.levels[0].requirements.length);
});
check('next-level locked and active/completed node states use actual requirements and queue',()=>{
    ui.selectBranch('military');const altered={...state,buildings:{academy:{level:30}},research:{infantry_hp:5,ranged_hp:1,cavalry_hp:1,infantry_def:1},research_queue:[{research_code:'ranged_def',finishes_at:'2026-09-10T13:00:00Z'}]};
    const html=ui.render({...options,state:altered,viewport:{width:1000,height:520}});
    assert(/class="rt-node rt-complete[^>]*data-id="infantry_hp"/.test(html));
    assert(/class="rt-node rt-running[^>]*data-id="ranged_def"/.test(html));
    assert(/class="rt-node rt-locked[^>]*data-id="ranged_atk"/.test(html));
    for(const edge of ui.getTreeInfo().edges)assert.equal(edge.met,(altered.research[edge.from]||0)>=edge.level);
});
check('every technology has a real subject-specific illustration and counter icons show the beneficiary',()=>{
    const renderedArt=code=>ui.renderRequirements({requirements:[{type:'research',code,level:1}],state,base:'/conquer',esc});
    for(const node of defs){
        const html=renderedArt(node.code),images=[...html.matchAll(/src="\/conquer\/([^\"]+)"/g)].map(match=>match[1]);
        assert(images.length>0,'Missing illustration for '+node.code);
        for(const image of images)assert(fs.existsSync(path.join(root,image)),'Missing asset '+image);
        assert(!/\/art\/(knight|archer|rider)\.png/.test(html),'Generic player portrait for '+node.code);
    }
    assert(renderedArt('infantry_hp').includes('data-art="infantry-hp"'));
    assert(renderedArt('infantry_def').includes('data-art="infantry-def"'));
    assert(renderedArt('infantry_atk').includes('data-art="infantry-atk"'));
    assert(renderedArt('cavalry_hp_against_infantry').includes('data-art="cavalry-hp"'));
    assert(renderedArt('archer_def_against_cavalry').includes('data-art="ranged-def"'));
    assert(renderedArt('hospital_capacity').includes('data-art="hospital"'));
    assert(renderedArt('research_speed').includes('data-art="research"'));
    assert(renderedArt('construction_speed').includes('data-art="construction"'));
    assert(renderedArt('food_capacity').includes('data-art="resource-capacity"'));
    assert(renderedArt('food_production').includes('data-art="resource-production"'));
    assert(renderedArt('food_gathering_speed').includes('data-art="resource-gathering"'));
    for(const code of ['warrior','knight','guardian','crusader','longbow_man','ranger','crossbow_man','sniper','horseman','heavy_cavalry','iron_cavalry','dragoon'])assert(!byCode.has(code),code+' must be absent from research');
});
process.stdout.write('ALL FULL RESEARCH CATALOGUE CHECKS PASSED\n');
