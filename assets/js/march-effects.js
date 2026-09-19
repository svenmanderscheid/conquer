// Short, world-anchored effects. One small canvas per arrival, no scene-wide
// blur, camera shake or new WebGL context. The map owns timing and disposal.
window.ConquerMarchEffects=(()=>{
 const clamp=(n,a=0,b=1)=>Math.max(a,Math.min(b,n)),ease=n=>1-Math.pow(1-clamp(n),3);
 const themedConfigs=Object.freeze({
  ironkeep:{motif:'shield',particle:'shard',primary:'#71899d',light:'#d9e4e8',dark:'#3d4e5b',duration:1500},
  rosehall:{motif:'rose',particle:'petal',primary:'#cf668a',light:'#ffd3dc',dark:'#713d55',duration:1650},
  sandspire:{motif:'sun',particle:'sand',primary:'#dfa646',light:'#ffe39a',dark:'#80552e',duration:1550},
  tidewatch:{motif:'wave',particle:'bubble',primary:'#3daec5',light:'#b8f0e8',dark:'#28677b',duration:1700},
  winterhold:{motif:'snow',particle:'crystal',primary:'#8bcce9',light:'#edfaff',dark:'#517b9b',duration:1650},
  jadecourt:{motif:'jade',particle:'leaf',primary:'#58a887',light:'#cbf0ca',dark:'#356b57',duration:1550},
  emberforge:{motif:'anvil',particle:'spark',primary:'#cc7045',light:'#ffd178',dark:'#633d35',duration:1500},
  ravenloft:{motif:'raven',particle:'feather',primary:'#8974ae',light:'#d8caea',dark:'#3e354c',duration:1650},
  clockwork:{motif:'gears',particle:'steam',primary:'#bd9553',light:'#f3deb1',dark:'#65543e',duration:1750},
  sapphire:{motif:'gem',particle:'facet',primary:'#527fc9',light:'#c8e1ff',dark:'#334d83',duration:1550},
  astral:{motif:'stars',particle:'star',primary:'#899df0',light:'#eef0ff',dark:'#514d91',duration:1800},
  leviathan:{motif:'tentacles',particle:'drop',primary:'#36cbb9',light:'#c5fff2',dark:'#267477',duration:1750},
  yggdrasil:{motif:'tree',particle:'leaf',primary:'#5ebd72',light:'#d8f2ae',dark:'#426846',duration:1800},
  tempest:{motif:'lightning',particle:'spark',primary:'#67bfe8',light:'#effcff',dark:'#4d608d',duration:1500},
  eclipse:{motif:'eclipse',particle:'star',primary:'#a36bd8',light:'#ead3ff',dark:'#312a49',duration:1800}
 });
 const biomeDust={ice:'#d5e9ed',sand:'#d4b780',lava:'#84716b',forest:'#8a8d63'};
 function release(canvas,c){let disposed=false;return {paintable:()=>!disposed,dispose(){if(disposed)return;disposed=true;c.setTransform(1,0,0,1,0,0);c.clearRect(0,0,canvas.width,canvas.height);canvas.width=1;canvas.height=1;}};}
 function path(c,points,close=true){c.beginPath();points.forEach((p,i)=>(i?c.lineTo(...p):c.moveTo(...p)));if(close)c.closePath();}
 function star(c,x,y,r,inner=r*.45,points=5,turn=-Math.PI/2){c.beginPath();for(let i=0;i<points*2;i++){const a=turn+i*Math.PI/points,d=i%2?inner:r;(i?c.lineTo: c.moveTo).call(c,x+Math.cos(a)*d,y+Math.sin(a)*d);}c.closePath();}
 function ellipse(c,x,y,rx,ry,color,alpha=1,rotation=0){c.save();c.globalAlpha=alpha;c.fillStyle=color;c.beginPath();c.ellipse(x,y,Math.max(.01,rx),Math.max(.01,ry),rotation,0,Math.PI*2);c.fill();c.restore();}
 function gear(c,x,y,r,turn,color,alpha){c.save();c.translate(x,y);c.rotate(turn);c.globalAlpha=alpha;c.fillStyle=color;for(let i=0;i<10;i++){c.rotate(Math.PI/5);c.fillRect(r*.72,-r*.14,r*.42,r*.28);}c.beginPath();c.arc(0,0,r,0,Math.PI*2);c.fill();c.globalCompositeOperation='destination-out';c.beginPath();c.arc(0,0,r*.38,0,Math.PI*2);c.fill();c.restore();}
 function drawParticle(c,kind,x,y,size,turn,config,alpha){
  c.save();c.translate(x,y);c.rotate(turn);c.globalAlpha=alpha;c.fillStyle=config.light;c.strokeStyle=config.dark;c.lineWidth=1;
  if(kind==='bubble'||kind==='drop'){c.globalAlpha=alpha*.72;c.beginPath();kind==='drop'?c.moveTo(0,-size*1.5):c.arc(0,0,size,0,Math.PI*2);if(kind==='drop'){c.quadraticCurveTo(size*1.35,0,0,size*1.5);c.quadraticCurveTo(-size*1.35,0,0,-size*1.5);}c.stroke();}
  else if(kind==='petal'||kind==='leaf'||kind==='feather'){c.beginPath();c.moveTo(0,-size*1.7);c.quadraticCurveTo(size*1.4,-size*.15,0,size*1.3);c.quadraticCurveTo(-size*.8,-size*.05,0,-size*1.7);c.fill();c.stroke();}
  else if(kind==='steam'){c.globalAlpha=alpha*.48;c.beginPath();c.arc(0,0,size*1.35,0,Math.PI*2);c.fill();}
  else if(kind==='star'){star(c,0,0,size*1.45,size*.55,4);c.fill();}
  else if(kind==='sand'){ellipse(c,0,0,size*1.4,size*.65,config.light,alpha);}
  else if(kind==='spark'){path(c,[[-size*.45,-size*2],[size*.2,-size*.35],[-size*.05,-size*.35],[size*.5,size*1.5],[-size*.25,size*.1],[0,size*.1]]);c.fill();}
  else{path(c,[[0,-size*1.6],[size*.75,-size*.2],[size*.2,size*1.4],[-size*.7,size*.5]]);c.fill();c.stroke();}
  c.restore();
 }
 function drawMotif(c,config,t,open,alpha){
  const p=config.primary,l=config.light,d=config.dark;c.save();c.globalAlpha=alpha;c.lineCap='round';c.lineJoin='round';c.strokeStyle=d;c.lineWidth=3;
  if(config.motif==='shield'){
   c.translate(0,-28-open*10);c.scale(.65+open*.35,.65+open*.35);c.fillStyle=p;path(c,[[-32,-34],[0,-47],[32,-34],[27,12],[0,42],[-27,12]]);c.fill();c.stroke();c.strokeStyle=l;c.lineWidth=5;c.beginPath();c.moveTo(0,-30);c.lineTo(0,25);c.moveTo(-18,-5);c.lineTo(18,-5);c.stroke();
  }else if(config.motif==='rose'){
   c.translate(0,-28);for(let i=0;i<8;i++){c.save();c.rotate(i*Math.PI/4+t*.35);ellipse(c,0,-(12+open*20),11+open*5,22,p,alpha);c.restore();}ellipse(c,0,0,13+open*5,13+open*5,l,1);c.strokeStyle=d;c.beginPath();c.arc(0,0,7+open*5,0,Math.PI*1.65);c.stroke();
  }else if(config.motif==='sun'){
   c.translate(0,-29);c.rotate(t*.25);for(let i=0;i<12;i++){c.save();c.rotate(i*Math.PI/6);c.fillStyle=i%2?l:p;path(c,[[-4,-30-open*14],[4,-30-open*14],[0,-47-open*18]]);c.fill();c.restore();}ellipse(c,0,0,27+open*5,27+open*5,p,1);ellipse(c,-7,-8,9,7,l,.72);
  }else if(config.motif==='wave'){
   c.strokeStyle=p;c.lineWidth=12;c.beginPath();c.moveTo(-58,4);c.bezierCurveTo(-26,-24,-10,-65,23,-48);c.bezierCurveTo(48,-36,42,-7,20,-4);c.bezierCurveTo(2,-2,0,-23,15,-27);c.stroke();c.strokeStyle=l;c.lineWidth=4;c.beginPath();c.moveTo(-48,-1);c.bezierCurveTo(-19,-26,-8,-51,23,-42);c.stroke();
  }else if(config.motif==='snow'){
   c.translate(0,-29);c.strokeStyle=l;c.lineWidth=5;for(let i=0;i<3;i++){c.save();c.rotate(i*Math.PI/3+t*.14);c.beginPath();c.moveTo(-46-open*9,0);c.lineTo(46+open*9,0);for(const s of [-1,1])for(const x of [-29,29]){c.moveTo(x,0);c.lineTo(x+s*9,-10);c.moveTo(x,0);c.lineTo(x+s*9,10);}c.stroke();c.restore();}ellipse(c,0,0,7,7,p,1);
  }else if(config.motif==='jade'){
   c.translate(0,-28);c.strokeStyle=p;c.lineWidth=13;c.beginPath();c.arc(-5,0,37,-2.25,.85);c.stroke();c.strokeStyle=l;c.lineWidth=4;c.beginPath();c.arc(-5,0,37,-2.15,.55);c.stroke();c.fillStyle=p;path(c,[[29,-31],[47,-18],[36,3],[20,-10]]);c.fill();c.stroke();ellipse(c,-20,30,7,10,l,1);
  }else if(config.motif==='anvil'){
   c.translate(0,-18);c.fillStyle=d;path(c,[[-47,-11],[-16,-22],[40,-20],[51,-9],[25,1],[18,25],[-25,25],[-18,2],[-47,-4]]);c.fill();c.fillStyle=p;c.fillRect(-25,25,44,10);c.save();c.translate(6,-32);c.rotate(-.9+open*.75);c.fillStyle=l;c.fillRect(-5,-40,10,44);c.fillStyle=p;c.fillRect(-22,-45,44,18);c.restore();
  }else if(config.motif==='raven'){
   c.translate(0,-28);c.fillStyle=d;path(c,[[0,7],[-17,-10],[-48,-39],[-40,-5],[-65,-17],[-44,15],[-18,25],[-3,19]]);c.fill();path(c,[[0,7],[16,-10],[49,-39],[40,-5],[66,-17],[43,15],[18,25],[3,19]]);c.fill();ellipse(c,0,4,20,26,p,1);path(c,[[12,-9],[37,-2],[15,5]]);c.fillStyle=l;c.fill();ellipse(c,7,-8,2.6,2.6,l,1);
  }else if(config.motif==='gears'){
   gear(c,-23,-25,25,t*4,p,1);gear(c,25,-3,20,-t*5,l,.95);gear(c,-6,25,14,t*6,d,.9);
  }else if(config.motif==='gem'){
   c.translate(0,-27);c.fillStyle=p;path(c,[[0,-50],[38,-17],[24,30],[0,48],[-24,30],[-38,-17]]);c.fill();c.stroke();c.strokeStyle=l;c.lineWidth=3;c.beginPath();c.moveTo(0,-50);c.lineTo(0,48);c.moveTo(-38,-17);c.lineTo(38,-17);c.moveTo(-24,30);c.lineTo(0,-17);c.lineTo(24,30);c.stroke();
  }else if(config.motif==='stars'){
   c.translate(0,-28);c.rotate(t*.75);c.strokeStyle=p;c.lineWidth=3;c.beginPath();c.ellipse(0,0,58,23,.35,0,Math.PI*2);c.stroke();c.rotate(-t*1.5);star(c,0,0,25,10,6);c.fillStyle=l;c.fill();for(const [x,y,r]of [[-46,-15,6],[48,8,5],[22,-31,4]]){star(c,x,y,r,r*.42,4);c.fillStyle=p;c.fill();}
  }else if(config.motif==='tentacles'){
   c.translate(0,-18);for(let i=0;i<4;i++){const s=i%2?-1:1,x=(i-1.5)*13;c.strokeStyle=i%2?l:p;c.lineWidth=11-i;c.beginPath();c.moveTo(x,20);c.bezierCurveTo(x+s*(22+open*13),-4,x-s*18,-37-open*13,x+s*13,-55);c.stroke();}ellipse(c,0,20,35,15,d,.9);ellipse(c,-10,14,4,4,l,1);
  }else if(config.motif==='tree'){
   c.translate(0,-17);c.strokeStyle=d;c.lineWidth=13;c.beginPath();c.moveTo(0,34);c.bezierCurveTo(-3,4,-13,-14,-28,-34);c.moveTo(0,14);c.bezierCurveTo(8,-8,17,-20,31,-38);c.stroke();for(const [x,y,r,color]of [[-28,-38,25,p],[2,-49,29,l],[31,-34,23,p],[-2,-22,24,l]])ellipse(c,x,y,r,r*.75,color,.95);c.strokeStyle=p;c.lineWidth=6;c.beginPath();c.moveTo(0,31);c.quadraticCurveTo(-20,39,-43,35);c.moveTo(0,31);c.quadraticCurveTo(20,42,45,32);c.stroke();
  }else if(config.motif==='lightning'){
   c.translate(0,-28);for(const [x,y,r]of [[-28,-15,22],[-4,-27,29],[27,-13,23],[3,-6,30]])ellipse(c,x,y,r,r*.58,l,.92);c.fillStyle=p;path(c,[[7,-1],[-15,34],[2,31],[-8,61],[31,18],[11,20],[25,-1]]);c.fill();c.stroke();
  }else if(config.motif==='eclipse'){
   c.translate(0,-29);for(let i=0;i<12;i++){c.save();c.rotate(i*Math.PI/6+t*.22);c.fillStyle=i%2?p:l;path(c,[[-3,-36-open*8],[3,-36-open*8],[0,-54-open*13]]);c.fill();c.restore();}ellipse(c,0,0,38,38,p,.94);ellipse(c,8,-5,35,35,d,1);c.strokeStyle=l;c.lineWidth=3;c.beginPath();c.arc(0,0,42,-1.6,1.6);c.stroke();
  }
  c.restore();
 }
 function themed(id,{biome='forest'}={}){
  const config=themedConfigs[id];if(!config)return null;
  const canvas=document.createElement('canvas'),width=320,height=260,ratio=Math.min(window.devicePixelRatio||1,1.35);canvas.className=`atlas-themed-impact atlas-${id}-impact`;canvas.dataset.marchTheme=id;canvas.setAttribute('aria-hidden','true');Object.assign(canvas.style,{position:'absolute',left:`-${width/2}px`,top:'-202px',width:`${width}px`,height:`${height}px`,pointerEvents:'none'});canvas.width=Math.round(width*ratio);canvas.height=Math.round(height*ratio);
  const c=canvas.getContext('2d'),life=release(canvas,c),dust=biomeDust[biome]||biomeDust.forest;
  const particles=Array.from({length:14},(_,i)=>({angle:i*2.39996+(id.length%5)*.17,speed:42+(i%5)*13,lift:30+(i%4)*15,delay:(i%4)*.025,size:2.4+(i%3)*1.1}));
  function paint(elapsed){
   if(!life.paintable())return;const t=clamp(elapsed/config.duration),open=ease(t/.32),fade=1-ease((t-.58)/.42),motifAlpha=Math.sin(Math.PI*clamp((t+.035)/.86))*fade;
   c.setTransform(ratio,0,0,ratio,0,0);c.clearRect(0,0,width,height);c.translate(width/2,198);
   c.save();c.scale(1,.34);const halo=c.createRadialGradient(0,0,4,0,0,112);halo.addColorStop(0,config.light+'dd');halo.addColorStop(.35,config.primary+'75');halo.addColorStop(1,config.primary+'00');c.globalAlpha=Math.sin(Math.PI*clamp(t/.78))*.62*fade;c.fillStyle=halo;c.fillRect(-115,-115,230,230);c.restore();
   for(let i=0;i<6;i++){const a=i*Math.PI/3+(id.length%3)*.13,r=22+open*70;ellipse(c,Math.cos(a)*r,Math.sin(a)*r*.22+8,12+t*12,4+t*3,dust,Math.sin(Math.PI*t)*.16);}
   drawMotif(c,config,t,open,motifAlpha);
   for(const item of particles){const u=clamp((t-item.delay)/.96),alpha=Math.sin(Math.PI*u)*(1-u*.38)*fade;if(alpha<=0)continue;const x=Math.cos(item.angle)*item.speed*Math.sin(u*Math.PI/2),y=Math.sin(item.angle)*item.speed*.2-item.lift*Math.sin(u*Math.PI*.82);drawParticle(c,config.particle,x,y,item.size,item.angle+u*2,config,alpha*.9);}
  }
  return {canvas,duration:config.duration,paint,dispose:life.dispose};
 }
 function phoenix({biome='forest'}={}){
  const canvas=document.createElement('canvas');canvas.className='atlas-phoenix-impact';canvas.setAttribute('aria-hidden','true');
  const width=336,height=292,ratio=Math.min(devicePixelRatio||1,1.5),duration=2100;
  canvas.width=Math.round(width*ratio);canvas.height=Math.round(height*ratio);
  const c=canvas.getContext('2d'),dust={ice:'#d5e9ed',sand:'#d4b780',lava:'#84716b',forest:'#8a8d63'}[biome]||'#8a8d63';
  const particles=Array.from({length:18},(_,i)=>({angle:i*2.39996,speed:44+(i%5)*17,lift:40+(i%4)*18,delay:(i%3)*.025,size:3+(i%3)}));
  function oval(x,y,rx,ry,color,alpha=1){c.globalAlpha=alpha;c.fillStyle=color;c.beginPath();c.ellipse(x,y,Math.max(.01,rx),Math.max(.01,ry),0,0,Math.PI*2);c.fill();}
  function plume(x,y,length,width,angle,alpha){
   c.save();c.translate(x,y);c.rotate(angle);c.globalAlpha=alpha;
   const fill=c.createLinearGradient(0,0,0,-length);fill.addColorStop(0,'#c3482c');fill.addColorStop(.46,'#f39c36');fill.addColorStop(.84,'#ffe292');fill.addColorStop(1,'#fff6ce');
   c.fillStyle=fill;c.beginPath();c.moveTo(0,0);c.bezierCurveTo(-width*1.1,-length*.16,-width*.95,-length*.55,0,-length);c.bezierCurveTo(-width*.12,-length*.48,width*1.18,-length*.39,0,0);c.fill();
   c.strokeStyle='#fff1b0';c.lineWidth=.8;c.beginPath();c.moveTo(0,-length*.14);c.quadraticCurveTo(-width*.15,-length*.49,0,-length*.85);c.stroke();c.restore();
  }
  function paint(elapsed){
   const t=clamp(elapsed/duration),opening=ease(t/.32),fade=1-ease((t-.48)/.52);
   c.setTransform(ratio,0,0,ratio,0,0);c.clearRect(0,0,width,height);c.translate(width/2,218);
   // The warm contact light stays on the ground as the feathers rise away.
   c.save();c.scale(1,.36);const halo=c.createRadialGradient(0,0,3,0,0,132);halo.addColorStop(0,'#fff0a9');halo.addColorStop(.28,'#f9a746a0');halo.addColorStop(.7,'#ef782142');halo.addColorStop(1,'#ed682000');c.globalAlpha=(.26+.5*Math.sin(Math.min(1,t/.44)*Math.PI))*fade;c.fillStyle=halo;c.fillRect(-135,-135,270,270);c.restore();
   for(let i=0;i<6;i++){const a=i*Math.PI/3,r=25+opening*76;oval(Math.cos(a)*r,Math.sin(a)*r*.23+8,15+t*15,5+t*4,dust,Math.sin(Math.PI*t)*.19);}
   // A gilded solar wreath, with interrupted edges instead of a UI circle.
   c.save();c.scale(1,.36);const radius=20+opening*110;c.globalAlpha=fade*.78;
   for(let i=0;i<12;i++){
    const a=i*Math.PI/6;c.strokeStyle=i%2?'#ffd477':'#f79b36';c.lineWidth=4*(1-t)+.5;c.beginPath();c.arc(0,0,radius,a+.05,a+.44);c.stroke();
    c.save();c.rotate(a);c.translate(radius-8,0);c.fillStyle='#fff1bd';c.beginPath();c.moveTo(-4,0);c.lineTo(0,-5);c.lineTo(4,0);c.lineTo(0,5);c.closePath();c.fill();c.restore();
   }c.restore();
   // A brief flame crown opens into two wings, then dissolves into feathers.
   const wing=clamp(t/.17),wingFade=Math.sin(Math.PI*clamp((t+.018)/.78))*.83;
   if(t<.77){
    for(const s of [-1,1])for(let i=6;i>=0;i--){const u=i/6,x=s*(9+u*(20+wing*51)),y=-10-u*15-opening*10;plume(x,y,27+(1-u)*38+wing*26,12+(1-u)*11,s*(.12+u*(.28+wing*.55)),Math.max(0,wingFade));}
    plume(0,-5-opening*8,42+opening*36,23,Math.sin(t*6)*.07,Math.max(0,wingFade));
   }
   // Low, warm flash: no white screen flash or shaking the followed camera.
   const flash=Math.max(0,1-t/.20);oval(0,-5,34*(1-flash)+5,12*(1-flash)+3,'#fff1b4',flash*.68);
   for(const p of particles){
    const u=clamp((t-p.delay)/.95),alpha=Math.sin(Math.PI*u)*(1-u*.45);if(alpha<=0)continue;
    const x=Math.cos(p.angle)*p.speed*Math.sin(u*Math.PI/2),y=Math.sin(p.angle)*p.speed*.22-p.lift*Math.sin(u*Math.PI*.78);
    c.save();c.translate(x,y);c.rotate(p.angle+u*1.4);c.globalAlpha=alpha*.82;c.fillStyle=p.size===3?'#fff1b1':'#eeb354';c.beginPath();c.moveTo(0,-p.size*2);c.quadraticCurveTo(p.size*1.1,0,0,p.size);c.quadraticCurveTo(-p.size*.6,0,0,-p.size*2);c.fill();c.restore();
   }
   c.globalAlpha=1;
  }
  const life=release(canvas,c),draw=paint;return {canvas,duration,paint:elapsed=>{if(life.paintable())draw(elapsed);},dispose:life.dispose};
 }
 function dragon({biome='forest'}={}){
  const canvas=document.createElement('canvas');canvas.className='atlas-dragon-impact';canvas.setAttribute('aria-hidden','true');
  const width=360,height=280,ratio=Math.min(devicePixelRatio||1,1.5),duration=1900;
  canvas.width=Math.round(width*ratio);canvas.height=Math.round(height*ratio);
  const c=canvas.getContext('2d'),dust={ice:'#d5e9ed',sand:'#d4b780',lava:'#84716b',forest:'#8a8d63'}[biome]||'#8a8d63';
  const embers=Array.from({length:16},(_,i)=>({angle:-2.85+i*.37,speed:42+(i%4)*16,lift:28+(i%5)*12,delay:(i%4)*.018,size:2.4+(i%3)*1.25}));
  function oval(x,y,rx,ry,color,alpha=1){c.globalAlpha=alpha;c.fillStyle=color;c.beginPath();c.ellipse(x,y,Math.max(.01,rx),Math.max(.01,ry),0,0,Math.PI*2);c.fill();}
  function dragonShape(x,y,scale,bank,alpha){
   c.save();c.translate(x,y);c.rotate(bank);c.scale(scale,scale);c.globalAlpha=alpha;c.lineJoin='round';c.strokeStyle='#493b33';c.lineWidth=2.8;
   // One compact, unmistakable diving silhouette: swept wings, horned head and hooked tail.
   c.fillStyle='#32785f';c.beginPath();c.moveTo(-8,-4);c.bezierCurveTo(-45,-35,-78,-36,-99,-25);c.bezierCurveTo(-67,-7,-52,9,-18,14);c.lineTo(-2,7);c.closePath();c.fill();c.stroke();
   c.beginPath();c.moveTo(8,-5);c.bezierCurveTo(44,-34,75,-32,96,-18);c.bezierCurveTo(61,-5,49,12,17,15);c.lineTo(1,7);c.closePath();c.fill();c.stroke();
   c.fillStyle='#274f45';c.beginPath();c.moveTo(-22,4);c.bezierCurveTo(-2,-16,28,-13,43,2);c.bezierCurveTo(25,17,-7,20,-27,10);c.closePath();c.fill();c.stroke();
   c.fillStyle='#a8763f';c.beginPath();c.moveTo(19,-7);c.lineTo(38,-16);c.lineTo(54,-9);c.lineTo(48,7);c.lineTo(30,10);c.closePath();c.fill();c.stroke();
   c.fillStyle='#d99a42';c.beginPath();c.moveTo(32,-16);c.lineTo(35,-28);c.lineTo(41,-17);c.moveTo(44,-14);c.lineTo(51,-24);c.lineTo(50,-10);c.stroke();
   c.fillStyle='#ffd37a';oval(45,-6,3.2,2.1,'#ffd37a',1);
   c.strokeStyle='#493b33';c.lineWidth=4;c.beginPath();c.moveTo(-23,8);c.bezierCurveTo(-49,23,-54,40,-35,49);c.bezierCurveTo(-23,55,-18,47,-25,43);c.stroke();
   c.restore();c.globalAlpha=1;
  }
  function fireBreath(x,y,length,width,angle,alpha){
   c.save();c.translate(x,y);c.rotate(angle);c.globalAlpha=alpha;
   const outer=c.createLinearGradient(0,0,length,0);outer.addColorStop(0,'#f8c55d');outer.addColorStop(.5,'#d99a42');outer.addColorStop(1,'#4fa77e00');
   c.fillStyle=outer;c.beginPath();c.moveTo(0,0);c.bezierCurveTo(length*.25,-width*.72,length*.72,-width,length,0);c.bezierCurveTo(length*.68,width*.78,length*.22,width*.54,0,0);c.fill();
   const core=c.createLinearGradient(0,0,length*.78,0);core.addColorStop(0,'#fff0a1');core.addColorStop(.38,'#75c697');core.addColorStop(1,'#32785f00');
   c.fillStyle=core;c.beginPath();c.moveTo(2,0);c.bezierCurveTo(length*.24,-width*.25,length*.56,-width*.36,length*.78,0);c.bezierCurveTo(length*.48,width*.28,length*.19,width*.2,2,0);c.fill();c.restore();
  }
  function paint(elapsed){
   const t=clamp(elapsed/duration),dive=ease(t/.32),fade=1-ease((t-.57)/.43),breath=clamp((t-.13)/.22),breathFade=1-ease((t-.43)/.22);
   c.setTransform(ratio,0,0,ratio,0,0);c.clearRect(0,0,width,height);c.translate(width/2,218);
   // The contact glow and dust sit above the existing terrain canvas and never repaint it.
   c.save();c.scale(1,.34);const halo=c.createRadialGradient(0,0,4,0,0,105);halo.addColorStop(0,'#ffd77d');halo.addColorStop(.32,'#d99a427d');halo.addColorStop(.7,'#4fa77e38');halo.addColorStop(1,'#32785f00');c.globalAlpha=Math.sin(Math.PI*clamp(t/.72))*.64*fade;c.fillStyle=halo;c.fillRect(-108,-108,216,216);c.restore();
   for(let i=0;i<7;i++){const a=-Math.PI+i*Math.PI/6,r=24+dive*72;oval(Math.cos(a)*r,Math.sin(a)*r*.2+8,13+t*13,4+t*3,dust,Math.sin(Math.PI*t)*.17);}
   if(t<.68){
    const x=-132+dive*100,y=-176+dive*116,bank=-.12+dive*.24,scale=.62+dive*.24;
    dragonShape(x,y,scale,bank,Math.min(1,t/.06)*fade);
    if(breath>0&&breathFade>0)fireBreath(x+43*scale,y-2*scale,18+breath*118,8+breath*26,.54-dive*.08,breathFade*.92);
   }
   const flash=Math.max(0,1-Math.abs(t-.37)/.12);oval(0,-3,11+flash*27,4+flash*9,'#ffe49a',flash*.58);
   for(const p of embers){
    const u=clamp((t-.29-p.delay)/.67),alpha=Math.sin(Math.PI*u)*(1-u*.38)*fade;if(alpha<=0)continue;
    const x=Math.cos(p.angle)*p.speed*Math.sin(u*Math.PI/2),y=Math.sin(p.angle)*p.speed*.18-p.lift*Math.sin(u*Math.PI*.8);
    c.save();c.translate(x,y);c.rotate(p.angle+u*2);c.globalAlpha=alpha*.9;c.fillStyle=p.size<3?'#79c89b':'#e7a744';c.beginPath();c.moveTo(0,-p.size*1.7);c.quadraticCurveTo(p.size,0,0,p.size);c.quadraticCurveTo(-p.size*.7,0,0,-p.size*1.7);c.fill();c.restore();
   }
   c.globalAlpha=1;
  }
  const life=release(canvas,c),draw=paint;return {canvas,duration,paint:elapsed=>{if(life.paintable())draw(elapsed);},dispose:life.dispose};
 }
 const create=(id,options)=>id==='phoenix'?phoenix(options):id==='dragon'?dragon(options):themed(id,options);
 const api={phoenix,dragon,create,ids:Object.freeze(['phoenix','dragon',...Object.keys(themedConfigs)])};
 for(const id of Object.keys(themedConfigs))api[id]=options=>themed(id,options);
 return Object.freeze(api);
})();
