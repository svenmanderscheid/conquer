'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),{execFileSync}=require('node:child_process');
const root=path.resolve(__dirname,'..'),sandbox={window:{}};vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/reward-dialog.js'),'utf8'),sandbox);
const definitions=JSON.parse(execFileSync(process.env.PHP_BINARY||'php',['-r',"define('ROOT_DIR',getcwd());require 'src/Autoloader.php';(new \\Conquer\\Autoloader(ROOT_DIR.'/src'))->register();echo json_encode(['items'=>array_values(\\Conquer\\Game\\Inventory\\InventoryService::allDefs()),'treasures'=>array_values(\\Conquer\\Game\\Treasure\\TreasureData::all())]);"],{cwd:root,encoding:'utf8'}));
const catalog=definitions.items.map(i=>({...i,item_code:i.code})),treasures=definitions.treasures.map(i=>({...i,treasure_code:i.code}));
const kingdom={inventory_catalog:catalog,treasures:{items:treasures}},resolve=sandbox.window.ConquerRewards.resolve;
for(const item of catalog){
 const reward=resolve({item_code:item.code,count:7,label:'Gold'},kingdom,'/conquer');assert.equal(reward.quantity,7);
 const specialty=['building','training','research','healing'].includes(item.subcategory)?'-'+item.subcategory:'';
 const scale=Number(item.amount)>=(item.resource==='gems'?1000:1000000)?'cart':Number(item.amount)>=(item.resource==='gems'?100:100000)?'crate':'bundle';
 const expected=item.category==='speedup'&&!item.icon_framed&&(!item.icon||item.icon==='speedup.svg')?`backpack/speedup${specialty}.svg`:item.category==='resource_pack'&&!item.icon&&['food','lumber','stone','gold','gems'].includes(item.resource)?`backpack/${item.resource}-${scale}.svg`:item.icon;
 assert(reward.icon.includes('/'+expected+'?'),'Inventory presentation icon '+item.code);assert(fs.existsSync(path.join(root,'assets/art/items',expected)),'Real item asset '+item.code);assert.equal(reward.name,item.name_de||item.name);
}
for(const item of treasures){const reward=resolve({type:'fragment',treasure_code:item.code,quantity:3},kingdom);assert.equal(reward.kind,'Reliktfragmente');assert(reward.icon.includes('/'+item.icon+'?'));}
vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/item-art.js'),'utf8'),sandbox);
const uniqueIcons=new Set();
const speedupArt={generic:'painted-v2/10103001.webp',building:'painted-v2/10103011.webp',research:'painted-v2/10103021.webp',training:'painted-v2/10103031.webp',healing:'painted-v2/10103041.webp'};
for(const item of catalog){
 const expected=item.category==='speedup'?speedupArt[item.subcategory]:`painted-v2/${item.code}.webp`;
 assert.equal(sandbox.window.ConquerItemArt.forItem(item),expected);
 assert.equal(sandbox.window.ConquerItemArt.forItem({code:item.code}),expected);
 const reward=resolve({item_code:item.code,count:7},kingdom,'/conquer');
 assert(reward.icon.includes('/'+expected+'?'),'Approved reward identity '+item.code);
 assert(fs.existsSync(path.join(root,'assets/art/items',expected)));
 uniqueIcons.add(expected);
}
const speedups=catalog.filter(item=>item.category==='speedup');
assert.equal(uniqueIcons.size,catalog.length-speedups.length+Object.keys(speedupArt).length,'Only durations of the same speedup type share artwork');
for(const [seconds,tier,label]of [[60,'grey','1m'],[300,'grey','5m'],[600,'grey','10m'],[3599,'grey',null],[3600,'blue','1h'],[86399,'blue',null],[86400,'violet','1d'],[259200,'violet','3d'],[604799,'violet',null],[604800,'orange','7d'],[2592000,'orange','30d']]){
 const item={category:'speedup',duration_seconds:seconds};
 assert.equal(sandbox.window.ConquerItemArt.speedupTier(item),tier,'Duration boundary '+seconds);
 if(label)assert.equal(sandbox.window.ConquerItemArt.speedupLabel(item),label);
}
assert.equal(sandbox.window.ConquerItemArt.speedupTier({category:'boost',duration_seconds:86400}),null,'Timed buffs keep their own rarity');
assert.equal(sandbox.window.ConquerItemArt.speedupTier({category:'speedup',duration_seconds:NaN}),null);
assert.equal(sandbox.window.ConquerItemArt.speedupTier({category:'speedup',duration_seconds:0}),null);
for(const [type,image]of Object.entries(speedupArt)){
 assert.equal(sandbox.window.ConquerItemArt.forItem({category:'speedup',subcategory:type,code:999999999}),image,'Future durations reuse the family motif');
 assert.equal(sandbox.window.ConquerItemArt.file(`backpack/speedup${type==='generic'?'':'-'+type}.svg`),image,'Summary uses the same family motif');
}
assert.equal(sandbox.window.ConquerItemArt.forItem({code:999999999}),null);
for(const item of treasures){const reward=resolve({type:'fragment',treasure_code:item.code,quantity:3},kingdom);assert(reward.icon.includes('/'+item.icon+'?'));}
vm.runInNewContext(fs.readFileSync(path.join(root,'assets/js/relic-presentation.js'),'utf8'),sandbox);
for(const item of treasures){
 const drop={type:'fragment',treasure_code:item.code,quantity:5,name:item.name_de||item.name,icon:item.icon,rarity:item.grade,grade:item.grade};
 for(const state of [kingdom,{}]){
  const reward=resolve(drop,state,'/conquer'),presentation=sandbox.window.ConquerRelicPresentation;
  assert.equal(reward.name,presentation.name(item),'Approved relic name '+item.code);
  assert.equal(reward.icon,presentation.image('/conquer',item),'Approved relic portrait overrides historical receipt icon '+item.code);
  assert.equal(reward.rarity,presentation.grade(item),'Approved relic rarity '+item.code);
  assert.equal(reward.quantity,5,'Credited fragment quantity remains unchanged');
  assert.equal(reward.kind,'Reliktfragmente');
  assert(item.legacy_only||reward.icon.includes('/relics-v4-storybook/'),'Active relic has approved artwork '+item.code);
  assert(fs.existsSync(path.join(root,reward.icon.split('?')[0].slice('/conquer/'.length))));
 }
}
assert(resolve({treasure_code:60100101,quantity:5},{}).icon.includes('/rel-003-zeichen-des-steinmetzen.png'),'Hammer receipt uses its approved identity without type or catalog');
assert(resolve({type:'fragment',treasure_code:99999999,icon:'fragment.svg',quantity:2},{}).icon.includes('/items/fragment.svg?'),'Unknown future relic keeps fallback artwork');
assert.equal(sandbox.window.ConquerRewards.asset('','../private.png'),'');assert.equal(sandbox.window.ConquerRewards.asset('','https://outside.invalid/icon.png'),'');
console.log(`PASS ${catalog.length} inventory and ${treasures.length} relic reward icons, names, amounts, ambiguous labels and safe asset paths.`);
