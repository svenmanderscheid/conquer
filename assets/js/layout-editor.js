(() => {
 'use strict';
 const root=document.querySelector('#layout-editor');if(!root)return;
 const $=id=>document.getElementById('layout-'+id),clone=value=>JSON.parse(JSON.stringify(value));
 const initial=JSON.parse($('editor-data').textContent),editable=root.dataset.editable==='1',catalog=initial.catalog,fields=initial.limits;
 const defaults=()=>({x:0,y:0,width:100,height:100,anchor:'auto'});
 let saved=clone(initial.profiles),draft=clone(saved),revision=initial.revision,ready=false,interact=false,busy=false,operation=null,gesture=false;
 let undo=[],redo=[],geometry={},selection=['navigation'],gestureRedo=[];
 const sizes={portrait:[[390,844],[320,568],[412,915],[768,1024],[820,1180]],landscape:[[844,390],[568,320],[915,412],[1024,600]],desktop:[[1280,800],[1440,900],[1920,1080],[2560,1440]]};
 const dirty=()=>JSON.stringify(draft)!==JSON.stringify(saved),status=text=>$('status').textContent=text;
 const post=data=>{if(ready)$('frame').contentWindow.postMessage({channel:'uok-layout',...data},location.origin);};
 function send(){post({type:'preview',profiles:draft,selection,multi:$('multi').getAttribute('aria-pressed')==='true',edit:editable&&!interact&&!busy,grid:Number($('grid').value)});}
 function remember(){undo.push(clone(draft));if(undo.length>100)undo.shift();redo=[];}
 function updateControls(){
  const value=draft[$('profile').value][$('element').value];
  $('default-note').textContent=value?'Eigene Einstellung für dieses Format.':'Standardlayout aktiv. Eine Änderung erzeugt eine eigene Einstellung für dieses Format.';
  for(const key in fields){const number=(value||defaults())[key];root.querySelector(`[data-layout-field="${key}"]`).value=number;root.querySelector(`[data-layout-number="${key}"]`).value=number;}
  $('anchor').value=value?.anchor||'auto';$('save').disabled=!editable||busy||!dirty();$('discard').disabled=busy||!dirty();$('fields').disabled=!editable||busy||!selection.length;
  $('undo').disabled=!editable||busy||!undo.length;$('redo').disabled=!editable||busy||!redo.length;$('copy').disabled=!editable||busy;$('import').disabled=!editable||busy;
  const box=geometry[$('element').value];$('selection-status').textContent=!selection.length?'Keine Auswahl. Einen Bereich anklicken.':selection.length>1?`${selection.length} Bereiche ausgewählt – gemeinsam ziehen. Größenfelder gelten für „${catalog[$('element').value].label}“.`:box?`Sichtbar: ${Math.round(box.width)} × ${Math.round(box.height)} px`:'In dieser Ansicht nicht sichtbar. Passende Ansicht öffnen oder „Spiel bedienen“ verwenden.';
 }
 function change(){operation=null;updateControls();send();status('Ungespeicherte Vorschau. Erst „Für alle Spieler speichern“ übernimmt die Änderungen.');}
 function resize(){
  const w=Number($('screen-width').value),h=Number($('screen-height').value),available=Math.max(200,$('viewport').clientWidth-24),scale=Math.min(1,available/w);
  Object.assign($('frame').style,{width:w+'px',height:h+'px',transform:`scale(${scale})`});Object.assign($('frame-space').style,{width:w*scale+'px',height:h*scale+'px'});
 }
 function sizeOptions(p){$('size').replaceChildren(...sizes[p].map(([w,h])=>new Option(`${w} × ${h}`,`${w}x${h}`)));}
 function preset(){const [w,h]=$('size').value.split('x');$('screen-width').value=w;$('screen-height').value=h;resize();}
 function profile(){sizeOptions($('profile').value);preset();updateControls();send();}
 $('profile').addEventListener('change',profile);$('size').addEventListener('change',preset);new ResizeObserver(resize).observe($('viewport'));
 $('custom-size').addEventListener('click',()=>{
  for(const id of ['screen-width','screen-height'])if(!$(id).reportValidity())return;
  const w=Number($('screen-width').value),h=Number($('screen-height').value),p=w>h&&h<=600?'landscape':w<=900?'portrait':'desktop';$('profile').value=p;sizeOptions(p);const value=w+'x'+h;if(![...$('size').options].some(o=>o.value===value))$('size').add(new Option(value+' · frei',value));$('size').value=value;resize();updateControls();send();
 });
 function select(){const key=$('element').value;if(!key)selection=[];else if($('multi').getAttribute('aria-pressed')==='true')selection=[...selection.filter(k=>k!==key),key];else selection=[key];updateControls();send();const def=catalog[key];if(def?.screen){$('screen').value=def.screen;post({type:'screen',screen:def.screen});}else if(def?.group==='Weltkarte'){$('screen').value='world';post({type:'screen',screen:'world'});}}
 function deselect(){selection=[];$('element').value='';post({type:'deselect'});updateControls();}
 $('multi').addEventListener('click',()=>{$('multi').setAttribute('aria-pressed',String($('multi').getAttribute('aria-pressed')!=='true'));send();});$('deselect').addEventListener('click',deselect);
 $('element').addEventListener('change',select);$('screen').addEventListener('change',()=>post({type:'screen',screen:$('screen').value}));$('grid').addEventListener('change',send);
 $('search').addEventListener('input',()=>{const q=$('search').value.trim().toLocaleLowerCase('de');for(const option of $('element').options)option.hidden=!option.textContent.toLocaleLowerCase('de').includes(q);const first=[...$('element').options].find(o=>!o.hidden);if(first){$('element').value=first.value;select();}});
 root.querySelectorAll('[data-layout-field],[data-layout-number]').forEach(input=>{
  let recorded=false;input.addEventListener('focus',()=>{recorded=false;});input.addEventListener('keydown',event=>{if((event.ctrlKey||event.metaKey)&&['z','y'].includes(event.key.toLowerCase()))recorded=false;});
  input.addEventListener('input',()=>{if(!editable||busy||!selection.length||input.value==='')return;const key=input.dataset.layoutField||input.dataset.layoutNumber,[min,max]=fields[key],number=Number(input.value);if(!Number.isFinite(number))return;if(!recorded){remember();recorded=true;}const p=$('profile').value,el=$('element').value;draft[p][el]={...defaults(),...draft[p][el],[key]:Math.round(Math.max(min,Math.min(max,number)))};change();});
 });
 $('anchor').addEventListener('change',()=>{remember();const p=$('profile').value,k=$('element').value;draft[p][k]={...defaults(),...draft[p][k],anchor:$('anchor').value};change();});
 $('reset-element').addEventListener('click',()=>{remember();draft[$('profile').value][$('element').value]=null;change();});
 $('reset-profile').addEventListener('click',()=>{remember();draft[$('profile').value]=Object.fromEntries(Object.keys(catalog).map(k=>[k,null]));change();});
 $('copy').addEventListener('click',()=>{const from=$('profile').value,to=$('copy-target').value;if(from===to){status('Bitte ein anderes Zielformat auswählen.');return;}remember();draft[to]=clone(draft[from]);change();status('Format kopiert. Vor dem Speichern die Vorschau des Zielformats prüfen.');});
 function history(action){if(!editable||busy||gesture)return;const source=action==='undo'?undo:redo,target=action==='undo'?redo:undo;while(source.length&&JSON.stringify(source.at(-1))===JSON.stringify(draft))source.pop();if(!source.length){updateControls();return;}target.push(clone(draft));draft=source.pop();change();}
 $('undo').addEventListener('click',()=>history('undo'));$('redo').addEventListener('click',()=>history('redo'));
 window.addEventListener('keydown',event=>{const key=event.key.toLowerCase(),text=event.target.closest?.('textarea,[contenteditable="true"],input:not([type=range]):not([type=number])');if(event.key==='Escape'){event.preventDefault();deselect();return;}if(!text&&(event.ctrlKey||event.metaKey)&&!event.altKey&&(key==='z'||key==='y')){event.preventDefault();history(key==='y'||event.shiftKey?'redo':'undo');}});
 $('discard').addEventListener('click',()=>{remember();draft=clone(saved);change();status('Ungespeicherte Änderungen verworfen.');});
 $('interact').addEventListener('click',()=>{interact=!interact;$('interact').setAttribute('aria-pressed',String(interact));$('interact').textContent=interact?'Layout bearbeiten':'Spiel bedienen / anmelden';send();});
 $('reload').addEventListener('click',()=>{ready=false;$('frame').src=root.dataset.preview.split('#')[0]+'#'+$('screen').value;status('Vorschau lädt …');});
 $('export').addEventListener('click',()=>{const url=URL.createObjectURL(new Blob([JSON.stringify({application:'union-of-kingdoms',version:2,profiles:draft},null,2)],{type:'application/json'})),a=document.createElement('a');a.href=url;a.download='union-of-kingdoms-layout.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);});
 $('import').addEventListener('click',()=>$('import-file').click());
 $('import-file').addEventListener('change',async()=>{
  const file=$('import-file').files[0];if(!file)return;
  try{if(file.size>100000)throw Error('Datei ist zu groß.');const data=JSON.parse(await file.text());if(data.application!=='union-of-kingdoms'||data.version!==2||!data.profiles||Object.keys(data.profiles).sort().join()!=='desktop,landscape,portrait')throw Error('Keine gültige Layoutdatei.');
   const result={};for(const p of Object.keys(saved)){result[p]=Object.fromEntries(Object.keys(catalog).map(k=>[k,null]));if(!data.profiles[p]||Array.isArray(data.profiles[p]))throw Error('Ungültiges Format.');for(const [k,s]of Object.entries(data.profiles[p])){if(!Object.hasOwn(catalog,k))throw Error('Unbekannter Bereich: '+k);if(s===null)continue;if(!s||Object.keys(s).some(f=>![...Object.keys(fields),'anchor'].includes(f))||!Object.keys(fields).every(f=>Number.isInteger(s[f])&&s[f]>=fields[f][0]&&s[f]<=fields[f][1])||('anchor'in s&&!initial.anchors.includes(s.anchor)))throw Error('Ungültige Werte in '+k);result[p][k]=clone(s);}}
   remember();draft=result;change();status('Layout importiert. Vorschau prüfen und anschließend speichern.');
  }catch(error){status(error.message+' Das bisherige Layout bleibt erhalten.');}finally{$('import-file').value='';}
 });
 const hello=()=>$('frame').contentWindow.postMessage({channel:'uok-layout',type:'hello'},location.origin);$('frame').addEventListener('load',hello);
 window.addEventListener('message',event=>{
  if(event.origin!==location.origin||event.source!==$('frame').contentWindow||event.data?.channel!=='uok-layout')return;const data=event.data;
  if(data.type==='ready'){ready=true;send();status(dirty()?'Ungespeicherte Vorschau.':'Vorschau bereit. Bereich auswählen, ziehen oder an den Rändern vergrößern.');}
  if(data.type==='gesture-start'&&editable&&!busy){gestureRedo=redo;remember();gesture=true;}
  if(data.type==='gesture-end'){if(gesture&&data.cancel){undo.pop();redo=gestureRedo;}gesture=false;updateControls();}
  if(data.type==='shortcut'&&['undo','redo'].includes(data.action))history(data.action);
  if(data.type==='select'&&Array.isArray(data.keys)){selection=[...new Set(data.keys)].filter(k=>Object.hasOwn(catalog,k));$('element').value=selection.at(-1)||'';updateControls();}
  if(data.type==='change'&&editable&&!busy&&Object.hasOwn(draft,data.profile)&&Object.hasOwn(catalog,data.key)&&(data.value===null||(Object.keys(fields).every(k=>Number.isInteger(data.value?.[k])&&data.value[k]>=fields[k][0]&&data.value[k]<=fields[k][1])&&initial.anchors.includes(data.value.anchor||'auto')))){draft[data.profile][data.key]=clone(data.value);operation=null;updateControls();status('Ungespeicherte Vorschau.');}
  if(data.type==='geometry'){geometry=data.boxes||{};$('warnings').textContent=Array.isArray(data.warnings)?data.warnings.join(' '):'';updateControls();}
 });
 $('save-form').addEventListener('submit',async event=>{
  event.preventDefault();if(!editable||busy||!dirty())return;busy=true;updateControls();send();status('Layout wird gespeichert …');
  if(!operation){const bytes=new Uint8Array(16);crypto.getRandomValues(bytes);operation={id:Array.from(bytes,n=>n.toString(16).padStart(2,'0')).join(''),profiles:clone(draft),reason:event.target.elements.reason.value};}
  const request=operation,body=new URLSearchParams({csrf_token:event.target.elements.csrf_token.value,operation_id:request.id,reason:request.reason,revision:String(revision),layout:JSON.stringify(request.profiles)});
  try{const response=await fetch(root.dataset.endpoint,{method:'POST',credentials:'same-origin',body,headers:{Accept:'application/json'},signal:AbortSignal.timeout(20000)});if(response.redirected)throw Error('Die Verwaltungssitzung ist abgelaufen. Bitte neu anmelden.');const data=await response.json();if(!response.ok)throw Error(data.error||'Speichern fehlgeschlagen.');saved=clone(request.profiles);revision=data.revision;operation=null;status(data.message);}
  catch(error){status(error.message+' Deine Vorschau bleibt erhalten.');}finally{busy=false;updateControls();send();}
 });
 window.addEventListener('beforeunload',event=>{if(dirty()){event.preventDefault();event.returnValue='';}});
 profile();updateControls();hello();
})();
