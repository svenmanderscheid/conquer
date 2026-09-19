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
assert.equal(sandbox.window.ConquerRewards.asset('','../private.png'),'');assert.equal(sandbox.window.ConquerRewards.asset('','https://outside.invalid/icon.png'),'');
console.log(`PASS ${catalog.length} inventory and ${treasures.length} relic reward icons, names, amounts, ambiguous labels and safe asset paths.`);
