const sharp=require('sharp'),path=require('path');
const dir=path.resolve(__dirname,'../artifacts/knight-review');
const base=path.join(dir,'texture-base.png'),mark=path.join(dir,'mouth-mark.png'),output=path.join(dir,'texture-mouth.png');
(async()=>{
 const size=2048,uv=[.8573870027,.1166945368];
 const mouth=await sharp(mark).trim({background:{r:0,g:0,b:0,alpha:0}}).resize(34,13,{fit:'fill'}).flip().rotate(37,{background:{r:0,g:0,b:0,alpha:0}}).png().toBuffer();
 const {width,height}=await sharp(mouth).metadata();
 // GLTF textures keep flipY=false, so the PNG row follows the UV's V value.
 const left=Math.round(uv[0]*size-width/2),top=Math.round(uv[1]*size-height/2);
 await sharp(base).composite([{input:mouth,left,top}]).png().toFile(output);
 await sharp(output).extract({left:left-40,top:Math.max(0,top-40),width:width+80,height:height+80}).resize(684,558,{kernel:'nearest'}).png().toFile(path.join(dir,'mouth-uv-preview.png'));
 console.log(JSON.stringify({output,left,top,width,height}));
})().catch(e=>{console.error(e);process.exitCode=1;});
