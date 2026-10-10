// Measure the label against the visible artwork, independently of its CSS inset.
module.exports=async building=>building.evaluate(async node=>{
 const target=node.getBoundingClientRect(),label=node.querySelector('.painted-building-label').getBoundingClientRect();
 let roofTop=target.top;
 if(node.classList.contains('is-empty')){
  const marker=getComputedStyle(node,'::after');
  roofTop=target.bottom-parseFloat(marker.bottom)-parseFloat(marker.height)-parseFloat(marker.borderTopWidth)-parseFloat(marker.borderBottomWidth);
 }else{
  const working=node.classList.contains('has-construction-art'),artwork=node.querySelector(working?'.painted-construction-art':'.painted-building-sprite');
  if(artwork){
   let image=artwork;
   if(working){image=new Image();image.src=artwork.querySelector('image').getAttribute('href');await image.decode();}
   const width=image.naturalWidth,height=image.naturalHeight,canvas=document.createElement('canvas');canvas.width=width;canvas.height=height;
   const ctx=canvas.getContext('2d');ctx.drawImage(image,0,0);const pixels=ctx.getImageData(0,0,width,height).data;
   let top=0;outer:for(let y=0;y<height;y++)for(let x=0;x<width;x++)if(pixels[(y*width+x)*4+3]>32){top=y;break outer;}
   const box=artwork.getBoundingClientRect(),frameWidth=width/(working?4:1),scale=Math.min(box.width/frameWidth,box.height/height);
   roofTop=box.bottom-height*scale+top*scale;
  }
 }
 return {code:node.dataset.id,roofTop,labelBottom:label.bottom,gap:roofTop-label.bottom};
});
