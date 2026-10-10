'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict'),path=require('node:path');
const context={window:{}};vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../assets/js/formation-composition.js'),'utf8'),context);
const C=context.window.ConquerFormationComposition;
const defs=JSON.parse(fs.readFileSync(path.join(__dirname,'../data/troops.json'),'utf8')).troops;
const normalize=value=>JSON.parse(JSON.stringify(value));
const ratio=(a,b,c,total)=>({percentages:{1:a,2:b,3:c},total});
let cases=0;
function check(stocks,capacity,choice,expected){const got=normalize(C.allocate(defs,stocks,capacity,choice));assert.deepEqual(got,expected);cases++;}
check({50100101:1000,50200101:1000,50300101:1000},1000,ratio(70,30,0,1000),{50100101:700,50200101:300});
check({50100101:200,50100501:500,50200501:400},1000,ratio(70,30,0,1000),{50100501:500,50100101:200,50200501:300});
check({50100101:1000,50200101:1000,50300101:1000},700,ratio(70,30,0,1000),{50100101:490,50200101:210});
check({50100101:70,50200101:900,50300101:900},1000,ratio(70,30,0,1000),{50100101:70,50200101:30});
check({50100101:1000,50300101:1000},1000,ratio(70,30,0,1000),{});
check({50200101:1000},1000,ratio(0,100,0,501),{50200101:501});
check({50100101:1000,50200101:1000,50300101:1000},1000,ratio(34,33,33,2),{50100101:1,50200101:1});
check({50100101:1000,50200101:1000},1000,ratio(70,40,0,1000),{});
// Property checks cover every percentage split, odd capacities, missing types and tier priority.
for(let infantry=0;infantry<=100;infantry++)for(let archers=0;archers<=100-infantry;archers++){
 const choice=ratio(infantry,archers,100-infantry-archers,997),stocks={50100101:183,50100501:67,50200101:237,50200501:149,50300101:101,50300501:93};
 const counts=normalize(C.allocate(defs,stocks,313,choice)),total=Object.values(counts).reduce((a,n)=>a+n,0),sums=C.totals(defs,counts);
 assert(total<=313);
 for(const [code,count] of Object.entries(counts))assert(Number.isInteger(count)&&count>0&&count<=stocks[code]);
 for(const type of [1,2,3]){assert(Math.abs(sums[type]-total*choice.percentages[type]/100)<1);if(choice.percentages[type]===0)assert.equal(sums[type],0);}
 cases++;
}
console.log('PASS '+cases+' percentage allocation cases.');
