'use strict';
// Mechanically crops and compresses the approved transparent atlas; no repainting.
const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const sharp=require('sharp');
const root=path.resolve(__dirname,'..');
const input=path.resolve(process.argv[2]||path.join(root,'artifacts/talent-icons-v1'));
const layout=JSON.parse(fs.readFileSync(path.join(input,'runtime-registration.json'),'utf8'));
const source=fs.readFileSync(path.join(input,layout.source));
const out=path.join(root,'assets/art/talents/painted-v1');
const hash=buffer=>crypto.createHash('sha256').update(buffer).digest('hex');
(async()=>{
    const {data,info}=await sharp(source).ensureAlpha().raw().toBuffer({resolveWithObject:true});
    fs.mkdirSync(out,{recursive:true});
    const icons={};
    for(const [index,key] of layout.icons.entries()){
        const [x0,x1]=layout.columns[index%3],[y0,y1]=layout.rows[Math.floor(index/3)];
        let left=x1,top=y1,right=x0,bottom=y0;
        // Ignore barely visible alpha noise for bounds, retaining the original alpha in the crop.
        for(let y=y0;y<y1;y++)for(let x=x0;x<x1;x++)if(data[(y*info.width+x)*4+3]>8){
            left=Math.min(left,x);top=Math.min(top,y);right=Math.max(right,x);bottom=Math.max(bottom,y);
        }
        if(right<left||bottom<top)throw Error('Empty icon: '+key);
        if(left===x0||right===x1-1||top===y0||bottom===y1-1)throw Error('Crop touches neighbouring cell: '+key);
        left=Math.max(x0,left-3);top=Math.max(y0,top-3);
        right=Math.min(x1-1,right+3);bottom=Math.min(y1-1,bottom+3);
        const crop={left,top,width:right-left+1,height:bottom-top+1};
        const packed=await sharp(source).extract(crop)
            .resize(240,240,{fit:'contain',background:{r:0,g:0,b:0,alpha:0}})
            .extend({top:8,bottom:8,left:8,right:8,background:{r:0,g:0,b:0,alpha:0}})
            .webp({quality:88,alphaQuality:100,effort:6}).toBuffer();
        fs.writeFileSync(path.join(out,key+'.webp'),packed);
        icons[key]={width:256,height:256,bytes:packed.length,sha256:hash(packed),crop};
    }
    fs.writeFileSync(path.join(out,'manifest.json'),JSON.stringify({version:1,
        approval:'User approved the revised talent icons and requested game integration on 2026-10-05',
        method:'Built-in image generation: background extraction; mechanical crop/resize/WebP compression',
        source:'artifacts/talent-icons-v1/'+layout.source,sourceSha256:hash(source),icons},null,2)+'\n');
    console.log(JSON.stringify({icons:Object.keys(icons).length,bytes:Object.values(icons).reduce((n,i)=>n+i.bytes,0),output:out}));
})().catch(error=>{console.error(error);process.exitCode=1;});
