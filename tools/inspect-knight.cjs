const fs=require('fs');
const b=fs.readFileSync(process.argv[2]||'C:/Users/svenm/Downloads/Meshy_AI_Meshy_Merged_Animations.glb');
const j=JSON.parse(b.subarray(20,20+b.readUInt32LE(12)));const bin=20+b.readUInt32LE(12)+8;
function read(i){const a=j.accessors[i],v=j.bufferViews[a.bufferView],n={SCALAR:1,VEC2:2,VEC3:3,VEC4:4,MAT4:16}[a.type],sz={5121:1,5123:2,5125:4,5126:4}[a.componentType],fn={5121:'readUInt8',5123:'readUInt16LE',5125:'readUInt32LE',5126:'readFloatLE'}[a.componentType];return Array.from({length:a.count},(_,k)=>Array.from({length:n},(_,c)=>b[fn](bin+(v.byteOffset||0)+(a.byteOffset||0)+k*(v.byteStride||n*sz)+c*sz)));}
const p=read(0),idx=read(6).flat(),parent=p.map((_,i)=>i),find=i=>parent[i]===i?i:parent[i]=find(parent[i]),join=(a,b)=>parent[find(a)]=find(b),weld=new Map();
p.forEach((v,i)=>{const k=v.map(x=>x.toFixed(5)).join(',');if(weld.has(k))join(i,weld.get(k));else weld.set(k,i);});
for(let i=0;i<idx.length;i+=3){join(idx[i],idx[i+1]);join(idx[i],idx[i+2]);}
const groups=new Map();p.forEach((v,i)=>{const k=find(i);if(!groups.has(k))groups.set(k,[]);groups.get(k).push(i);});
console.log([...groups.values()].sort((a,b)=>b.length-a.length).map(g=>({count:g.length,min:[0,1,2].map(k=>Math.min(...g.map(i=>p[i][k]))),max:[0,1,2].map(k=>Math.max(...g.map(i=>p[i][k])))})));
