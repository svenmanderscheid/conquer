/* Saved percentage templates. Dispatch still validates and reserves troops on the server. */
window.ConquerFormationComposition=(()=>{
    'use strict';
    const types=[1,2,3];
    const typeOf=t=>Number(t.type)||Math.floor(Number(t.code)/100000)%10;
    const amount=n=>Number.isSafeInteger(Number(n))?Math.max(0,Number(n)):0;
    function totals(troops,counts){return Object.fromEntries(types.map(type=>[type,troops.filter(t=>typeOf(t)===type).reduce((sum,t)=>sum+amount(counts[t.code]),0)]));}
    function percentages(troops,counts){
        const sums=totals(troops,counts),total=Object.values(sums).reduce((sum,n)=>sum+n,0);
        if(!total)return {1:0,2:0,3:0};
        const result=Object.fromEntries(types.map(type=>[type,Math.floor(sums[type]*100/total)]));
        let left=100-Object.values(result).reduce((sum,n)=>sum+n,0);
        [...types].sort((a,b)=>(sums[b]*100%total)-(sums[a]*100%total)||a-b).forEach(type=>{if(left>0){result[type]++;left--;}});
        return result;
    }
    function valid(composition){return Boolean(composition&&Number.isSafeInteger(composition.total)&&composition.total>0&&types.every(type=>Number.isInteger(composition.percentages?.[type])&&composition.percentages[type]>=0&&composition.percentages[type]<=100)&&types.reduce((sum,type)=>sum+composition.percentages[type],0)===100);}
    function allocate(troops,stocks,capacity,composition){
        if(!valid(composition))return {};
        const available=totals(troops,stocks),p=composition.percentages;
        let total=Math.min(amount(capacity),composition.total);
        types.forEach(type=>{if(p[type]>0)total=Math.min(total,Math.floor(available[type]*100/p[type]));});
        const quotas=Object.fromEntries(types.map(type=>[type,Math.floor(total*p[type]/100)]));
        let left=total-Object.values(quotas).reduce((sum,n)=>sum+n,0);
        [...types].sort((a,b)=>(total*p[b]%100)-(total*p[a]%100)||a-b).forEach(type=>{if(left>0&&p[type]>0){quotas[type]++;left--;}});
        const counts={};
        types.forEach(type=>{let remaining=quotas[type];troops.filter(t=>typeOf(t)===type).sort((a,b)=>Number(b.tier)-Number(a.tier)||Number(a.code)-Number(b.code)).forEach(t=>{const count=Math.min(remaining,amount(stocks[t.code]));if(count>0)counts[t.code]=count;remaining-=count;});});
        return counts;
    }
    return {types,typeOf,totals,percentages,valid,allocate};
})();
