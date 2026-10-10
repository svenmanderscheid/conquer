'use strict';
// Mechanical exports only: retain the main cutout, fit, compress, record provenance.
const fs=require('fs'),path=require('path'),crypto=require('crypto'),sharp=require('sharp');
const root=path.resolve(__dirname,'../assets/art/territory-v4');
const recipe=JSON.parse(fs.readFileSync(path.join(root,'source.json'),'utf8'));
const hash=buffer=>crypto.createHash('sha256').update(buffer).digest('hex');

function mainCutout(data,width,height){
  const count=width*height,seen=new Uint8Array(count),queue=new Int32Array(count);
  let largest=[],components=0;
  for(let index=0;index<count;index++){
    if(seen[index]||data[index*4+3]<8)continue;
    components++;let start=0,end=1;queue[0]=index;seen[index]=1;
    while(start<end){
      const current=queue[start++],x=current%width,y=Math.floor(current/width);
      for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++){
        const nx=x+dx,ny=y+dy;if(nx<0||ny<0||nx>=width||ny>=height)continue;
        const next=ny*width+nx;if(!seen[next]&&data[next*4+3]>=8){seen[next]=1;queue[end++]=next;}
      }
    }
    if(end>largest.length)largest=Array.from(queue.subarray(0,end));
  }
  if(largest.length<count*.15)throw new Error('No substantial transparent building cutout');
  const kept=new Uint8Array(count);let left=width,top=height,right=0,bottom=0;
  for(const index of largest){kept[index]=1;const x=index%width,y=Math.floor(index/width);left=Math.min(left,x);right=Math.max(right,x);top=Math.min(top,y);bottom=Math.max(bottom,y);}
  // Preserve antialiased neighbours, discard only disconnected background fragments.
  const edge=kept.slice();for(const index of largest){const x=index%width,y=Math.floor(index/width);for(let dy=-1;dy<=1;dy++)for(let dx=-1;dx<=1;dx++){const nx=x+dx,ny=y+dy;if(nx>=0&&ny>=0&&nx<width&&ny<height)edge[ny*width+nx]=1;}}
  let removed=0;for(let index=0;index<count;index++)if(!edge[index]&&data[index*4+3]){data[index*4+3]=0;removed++;}
  left=Math.max(0,left-2);top=Math.max(0,top-2);right=Math.min(width-1,right+2);bottom=Math.min(height-1,bottom+2);
  return {data,bounds:{left,top,width:right-left+1,height:bottom-top+1},removed,components};
}

(async()=>{
  const entries=[];
  for(const entry of recipe.entries){
    const source=fs.readFileSync(path.join(root,entry.source)),metadata=await sharp(source).metadata();
    if(!metadata.hasAlpha)throw new Error(entry.key+' must have genuine alpha');
    const raw=await sharp(source).ensureAlpha().raw().toBuffer({resolveWithObject:true});
    const cleaned=mainCutout(raw.data,raw.info.width,raw.info.height);
    const image=await sharp(cleaned.data,{raw:{width:raw.info.width,height:raw.info.height,channels:4}})
      .extract(cleaned.bounds).resize(480,480,{fit:'inside',withoutEnlargement:true}).png().toBuffer();
    const fitted=await sharp(image).metadata();
    const output=await sharp({create:{width:512,height:512,channels:4,background:{r:0,g:0,b:0,alpha:0}}})
      .composite([{input:image,left:Math.floor((512-fitted.width)/2),top:Math.floor((512-fitted.height)/2)}])
      .webp({quality:85,alphaQuality:100,effort:6}).toBuffer();
    const file=entry.key+'.webp';fs.writeFileSync(path.join(root,file),output);
    const exported=await sharp(output).ensureAlpha().raw().toBuffer(),last=512*512-1;
    const corners=[0,511,511*512,last].map(index=>exported[index*4+3]);
    if(corners.some(Boolean)||output.length>150000)throw new Error(file+' fails alpha/size limit');
    entries.push({key:entry.key,file,source:entry.source,sourceSha256:hash(source),sourceDimensions:{width:metadata.width,height:metadata.height},export:{width:512,height:512,bytes:output.length,sha256:hash(output),cornerAlpha:corners,removedDisconnectedPixels:cleaned.removed}});
    console.log(file+' '+output.length+' bytes');
  }
  fs.writeFileSync(path.join(root,'manifest.json'),JSON.stringify({generated:recipe.generated,mode:recipe.mode,processing:'Retain main alpha cutout and antialiasing; discard disconnected background speckles; proportional fit within 480px and 512px transparent canvas; WebP quality 85, alphaQuality 100. No repainting.',entries},null,2)+'\n');
})().catch(error=>{console.error(error);process.exitCode=1;});
