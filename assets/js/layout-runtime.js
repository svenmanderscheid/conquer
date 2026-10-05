/* Union of Kingdoms: reversible, bounded overrides for the shared interface. */
(() => {
 'use strict';
 const catalog=JSON.parse(document.querySelector('#uok-layout-catalog').textContent);
 const limits={x:[-4096,4096],y:[-4096,4096],width:[25,300],height:[25,300]};
 const anchors=['auto','top-left','top','top-right','left','center','right','bottom-left','bottom','bottom-right'];
 const empty=()=>Object.fromEntries(['portrait','landscape','desktop'].map(p=>[p,Object.fromEntries(Object.keys(catalog).map(k=>[k,null]))]));
 const defaults=()=>({x:0,y:0,width:100,height:100,anchor:'auto'});
 function clean(data){const result=empty();for(const p in result)for(const key in catalog){const s=data?.[p]?.[key];if(s&&Object.keys(limits).every(k=>Number.isInteger(s[k])&&s[k]>=limits[k][0]&&s[k]<=limits[k][1])&&(!Object.hasOwn(s,'anchor')||anchors.includes(s.anchor)))result[p][key]={...defaults(),...Object.fromEntries(Object.keys(limits).map(k=>[k,s[k]])),anchor:s.anchor||'auto'};}return result;}
 let profiles=clean(JSON.parse(document.querySelector('#uok-layout-config').textContent));
 const preview=parent!==window&&new URLSearchParams(location.search).get('layout_preview')==='1';
 if(!preview&&!Object.values(profiles).some(p=>Object.values(p).some(Boolean)))return;
 let edit=false,selected='navigation',selection=['navigation'],multi=false,timer=0,layer=null,drag=null,announced=false,grid=1,metrics={},boxes={};
 const originals=new Map();
 const profile=()=>innerWidth>innerHeight&&innerHeight<=600?'landscape':innerWidth<=900?'portrait':'desktop';
 const screen=()=>location.hash.slice(1).split('?')[0]||'city';
 const send=data=>{if(preview)parent.postMessage({channel:'uok-layout',...data},location.origin);};
 const clamp=(v,min,max)=>Math.max(Math.min(min,max),Math.min(max,v));
 const rect=el=>{const r=el.getBoundingClientRect();return{x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom};};
 const zoom=el=>{let value=1;for(let node=el;node;node=node.parentElement)value*=parseFloat(getComputedStyle(node).zoom)||1;return value;};
 const visible=el=>el&&el.getClientRects().length&&!el.closest('[hidden]')&&getComputedStyle(el).visibility!=='hidden';
 function set(el,key,value){if(!originals.has(el))originals.set(el,new Map());const map=originals.get(el);if(!map.has(key))map.set(key,[el.style.getPropertyValue(key),el.style.getPropertyPriority(key)]);el.style.setProperty(key,value,'important');}
 function restore(){for(const [el,map]of originals)for(const [key,[value,priority]]of map)value?el.style.setProperty(key,value,priority):el.style.removeProperty(key);originals.clear();}
 const safe=document.createElement('div');safe.style.cssText='position:fixed;visibility:hidden;pointer-events:none;padding:env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left)';document.body.append(safe);
 function schedule(){if(!timer)timer=setTimeout(apply,40);}
 function apply(){
  timer=0;restore();metrics={};boxes={};const elements={},p=profile(),settings=profiles[p],css=getComputedStyle(safe);
  const edge={left:Math.max(3,parseFloat(css.paddingLeft)||0),right:Math.max(3,parseFloat(css.paddingRight)||0),top:Math.max(6,parseFloat(css.paddingTop)||0),bottom:Math.max(6,parseFloat(css.paddingBottom)||0)};
  const w=innerWidth,h=innerHeight,modal=[...document.querySelectorAll('dialog[open]')].filter(visible).at(-1);
  for(const [key,def]of Object.entries(catalog)){
   if(!preview&&!settings[key]&&!(['navigation','chat'].includes(key)&&(settings.chat||settings.navigation)))continue;
   if(def.screen&&def.screen!==screen())continue;
   const el=document.querySelector(def.selector);if(!visible(el))continue;
   // Mobile pages have a fixed viewport frame, independent of saved floating-window geometry.
   if(matchMedia('(max-width:700px), (max-width:1100px) and (max-height:520px) and (orientation:landscape)').matches&&el.matches('#panel-dialog,#game-dialog.mobile-detail-page,.world-chat.is-open'))continue;
   const base=rect(el);if(!base.width||!base.height)continue;
   elements[key]=el;metrics[key]=base;const s=settings[key];if(!s)continue;
   const availableW=w-edge.left-edge.right,availableH=h-edge.top-edge.bottom,z=zoom(el);
   const width=clamp(base.width*s.width/100,def.minWidth,availableW),height=clamp(base.height*s.height/100,def.minHeight,availableH);
   if(def.mode==='scale'){set(el,'transform-origin','0 0');set(el,'scale',`${width/base.width} ${height/base.height}`);}
   else{
    for(const [prop,value]of Object.entries({width:width/z+'px',height:height/z+'px','max-width':availableW/z+'px','max-height':availableH/z+'px','min-width':'0','min-height':'0'}))set(el,prop,value);
    if(key==='navigation')el.querySelectorAll('.game-dock-item').forEach(child=>set(child,'height',Math.max(44,height/z-9)+'px'));
    if(key==='resources')el.querySelectorAll('.resource').forEach(child=>{set(child,'min-width','0');set(child,'flex','1 1 0');set(child,'height',height/z+'px');});
    if(def.group==='Spielfenster')set(el,'overflow','auto');
   }
   const now=rect(el);let x=base.x+(base.width-now.width)/2,y=['navigation','chat'].includes(key)?base.bottom-now.height:base.y;
   const anchor=s.anchor||'auto';
   if(anchor!=='auto'){x=anchor.includes('left')?edge.left:anchor.includes('right')?w-edge.right-now.width:(w-now.width)/2;y=anchor.includes('top')?edge.top:anchor.includes('bottom')?h-edge.bottom-now.height:(h-now.height)/2;}
   x=clamp(x+s.x,edge.left,w-edge.right-now.width);y=clamp(y+s.y,edge.top,h-edge.bottom-now.height);
   let sx=z,sy=z;for(let ancestor=el.parentElement;ancestor&&ancestor!==document.body;ancestor=ancestor.parentElement){const scale=getComputedStyle(ancestor).scale.split(' ').map(Number);if(Number.isFinite(scale[0])){sx*=scale[0];sy*=scale[1]||scale[0];}}
   set(el,'translate',`${(x-now.x)/sx}px ${(y-now.y)/sy}px`);
  }
  const nav=elements.navigation,chat=elements.chat;
  if(nav&&chat&&(settings.chat||settings.navigation)&&(!settings.chat?.anchor||settings.chat.anchor==='auto')){
   const n=rect(nav),c=rect(chat);if(c.bottom>n.y-8&&c.x<n.right&&c.right>n.x){const t=getComputedStyle(chat).translate.split(' ').map(parseFloat);set(chat,'translate',`${t[0]||0}px ${(t[1]||0)+(Math.max(edge.top,n.y-c.height-8)-c.y)/zoom(chat)}px`);}
  }
  for(const [key,el]of Object.entries(elements))if(!modal||el===modal||modal.contains(el))boxes[key]=rect(el);
  const warnings=[];
  if(selected in boxes){const a=boxes[selected],target=elements[selected];for(const [key,b]of Object.entries(boxes)){if(key===selected||target.contains(elements[key])||elements[key].contains(target))continue;if(a.x<b.right-2&&a.right>b.x+2&&a.y<b.bottom-2&&a.bottom>b.y+2){warnings.push('Überlagerung mit '+catalog[key].label+'.');if(warnings.length===3)break;}}}
  if(preview){if(!announced&&nav?.querySelector('.game-dock-item')&&elements.resources?.querySelector('.resource')){announced=true;send({type:'ready'});}draw(modal);send({type:'geometry',profile:p,screen:screen(),boxes,warnings});}
 }
 function choose(keys){selection=[...new Set(keys)].filter(k=>Object.hasOwn(catalog,k));selected=selection.at(-1)||'';send({type:'select',keys:selection,key:selected});schedule();}
 function movable(){return selection.filter(k=>boxes[k]&&!selection.some(other=>other!==k&&boxes[other]&&document.querySelector(catalog[other].selector)?.contains(document.querySelector(catalog[k].selector))));}
 function finish(cancel=false){if(!drag)return;const d=drag;drag=null;if(d.started){if(cancel)for(const [key,item]of Object.entries(d.items)){profiles[d.profile][key]=item.original;send({type:'change',profile:d.profile,key,value:item.original});}send({type:'gesture-end',cancel});}schedule();}
 function shortcut(event){
  if(!edit||event.target.closest?.('input,textarea,[contenteditable="true"]'))return;
  const key=event.key.toLowerCase(),history=(event.ctrlKey||event.metaKey)&&!event.altKey&&(key==='z'||key==='y');
  if(event.key!=='Escape'&&!history)return;event.preventDefault();event.stopImmediatePropagation();
  if(event.key==='Escape'){finish(true);choose([]);}else{finish();send({type:'shortcut',action:key==='y'||event.shiftKey?'redo':'undo'});}
 }
 if(preview)window.addEventListener('keydown',shortcut,true);
 function draw(modal){
  if(!layer){
   layer=document.createElement('div');layer.id='uok-layout-layer';layer.setAttribute('popover','manual');layer.setAttribute('aria-label','Layout bearbeiten');
   for(const [key,def]of Object.entries(catalog)){
    const box=document.createElement('div');box.dataset.layoutElement=key;box.className='layout-outline';
    const move=document.createElement('button');move.type='button';move.className='layout-move';move.textContent=def.label;move.setAttribute('aria-label',def.label+' verschieben');move.style.setProperty('background','transparent','important');move.style.setProperty('box-shadow','none','important');box.append(move);
    for(const direction of ['n','e','s','w','ne','nw','se','sw']){const handle=document.createElement('button');handle.type='button';handle.className='layout-resize layout-resize-'+direction;handle.dataset.resize=direction;handle.setAttribute('aria-label',def.label+' Größe ändern '+direction);box.append(handle);}
    layer.append(box);
    box.addEventListener('pointerdown',event=>{
     if(event.button!==0||!edit)return;event.preventDefault();event.stopPropagation();move.focus({preventScroll:true});
     if(event.ctrlKey||event.metaKey||event.shiftKey||multi){choose(selection.includes(key)?selection.filter(k=>k!==key):[...selection,key]);return;}
     if(!selection.includes(key))choose([key]);
     const direction=event.target.dataset.resize||'',keys=direction?[key]:movable();
     drag={key,profile:profile(),items:Object.fromEntries(keys.map(k=>[k,{original:profiles[profile()][k]?{...profiles[profile()][k]}:null,start:{...defaults(),...profiles[profile()][k]},rect:{...boxes[k]}}])),start:{...defaults(),...profiles[profile()][key]},x:event.clientX,y:event.clientY,base:{...metrics[key]},direction,started:false};
     box.setPointerCapture(event.pointerId);schedule();
    });
    box.addEventListener('pointermove',event=>{
     if(!drag||drag.key!==key)return;event.preventDefault();const d=drag;let dx=Math.round((event.clientX-d.x)/grid)*grid,dy=Math.round((event.clientY-d.y)/grid)*grid;const s={...d.start};
     if(!d.started){if(!dx&&!dy)return;d.started=true;send({type:'gesture-start'});}
     if(!d.direction){
      const items=Object.values(d.items),css=getComputedStyle(safe),left=Math.max(3,parseFloat(css.paddingLeft)||0),right=Math.max(3,parseFloat(css.paddingRight)||0),top=Math.max(6,parseFloat(css.paddingTop)||0),bottom=Math.max(6,parseFloat(css.paddingBottom)||0);
      dx=Math.round(clamp(dx,Math.max(...items.map(i=>Math.max(left-i.rect.x,limits.x[0]-i.start.x))),Math.min(...items.map(i=>Math.min(innerWidth-right-i.rect.right,limits.x[1]-i.start.x)))));
      dy=Math.round(clamp(dy,Math.max(...items.map(i=>Math.max(top-i.rect.y,limits.y[0]-i.start.y))),Math.min(...items.map(i=>Math.min(innerHeight-bottom-i.rect.bottom,limits.y[1]-i.start.y)))));
      for(const [k,item]of Object.entries(d.items)){const value={...item.start,x:item.start.x+dx,y:item.start.y+dy};profiles[d.profile][k]=value;send({type:'change',profile:d.profile,key:k,value});}schedule();return;
     }else{
      const horizontal=/[ew]/.test(d.direction),vertical=/[ns]/.test(d.direction),left=d.direction.includes('w'),top=d.direction.includes('n');
      if(horizontal){const delta=left?-dx:dx,factor=s.anchor.includes('left')?0:s.anchor.includes('right')?1:.5;s.width=Math.round(d.start.width+delta/d.base.width*100);s.x+=Math.round((left?dx:0)+factor*delta);}
      if(vertical){const delta=top?-dy:dy,factor=s.anchor==='auto'?(['navigation','chat'].includes(key)?1:0):s.anchor.includes('top')?0:s.anchor.includes('bottom')?1:.5;s.height=Math.round(d.start.height+delta/d.base.height*100);s.y+=Math.round((top?dy:0)+factor*delta);}
     }
     for(const k in limits)s[k]=Math.round(clamp(s[k],...limits[k]));
     profiles[d.profile][key]=s;send({type:'change',profile:d.profile,key,value:s});schedule();
    });
    box.addEventListener('pointerup',()=>finish());box.addEventListener('pointercancel',()=>finish(true));box.addEventListener('lostpointercapture',()=>finish());
    box.addEventListener('keydown',event=>{const delta={ArrowLeft:[-1,0],ArrowRight:[1,0],ArrowUp:[0,-1],ArrowDown:[0,1]}[event.key];if(!edit||!delta||!selection.length)return;event.preventDefault();event.stopPropagation();const step=event.shiftKey?10:1;send({type:'gesture-start'});for(const k of movable()){const s={...defaults(),...profiles[profile()][k]};s.x=clamp(s.x+delta[0]*step,...limits.x);s.y=clamp(s.y+delta[1]*step,...limits.y);profiles[profile()][k]=s;send({type:'change',profile:profile(),key:k,value:s});}send({type:'gesture-end'});schedule();});
   }
  }
  const host=modal||document.body;if(layer.parentElement!==host){if(layer.matches(':popover-open'))layer.hidePopover();host.append(layer);}
  if(edit&&!layer.matches(':popover-open'))layer.showPopover();else if(!edit&&layer.matches(':popover-open'))layer.hidePopover();
  for(const box of layer.children){const key=box.dataset.layoutElement,r=boxes[key];box.hidden=!r;box.classList.toggle('is-selected',selection.includes(key));box.querySelector('.layout-move').setAttribute('aria-pressed',String(selection.includes(key)));if(r)Object.assign(box.style,{left:r.x+'px',top:r.y+'px',width:r.width+'px',height:r.height+'px',zIndex:selection.includes(key)?'3':catalog[key].group==='Gruppen'?'0':'1'});}
 }
 if(preview)window.addEventListener('message',event=>{
  if(event.origin!==location.origin||event.source!==parent||event.data?.channel!=='uok-layout')return;const data=event.data;
  if(data.type==='hello'){if(announced)send({type:'ready'});schedule();}
  if(data.type==='preview'){if(!drag)profiles=clean(data.profiles);edit=data.edit===true;multi=data.multi===true;grid=[1,4,8,16].includes(data.grid)?data.grid:1;selection=(Array.isArray(data.selection)?data.selection:[data.selected]).filter(k=>Object.hasOwn(catalog,k));selected=selection.at(-1)||'';schedule();}
  if(data.type==='deselect'){finish(true);choose([]);}
  if(data.type==='screen'&&data.screen!==screen()&&(['city','world',...Object.values(catalog).map(d=>d.screen).filter(Boolean)].includes(data.screen))){location.hash=data.screen;schedule();}
 });
 window.addEventListener('resize',()=>{finish();schedule();});window.addEventListener('hashchange',schedule);window.visualViewport?.addEventListener('resize',schedule);
 new MutationObserver(records=>{if(records.some(r=>r.target!==layer&&!layer?.contains(r.target)&&!safe.contains(r.target)&&(r.type==='childList'||r.target===document.body||r.attributeName==='open'||r.attributeName==='hidden'||r.target.matches?.('.world-chat'))))schedule();}).observe(document.body,{subtree:true,childList:true,attributes:true,attributeFilter:['class','open','hidden']});
 schedule();
})();
