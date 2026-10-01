// A self-contained visual concept. All ownership, cities and encounters are examples.
// No game APIs, sessions, timers, or account writes are used.
const $ = selector => document.querySelector(selector);
const stage = $('#world-stage'), canvas = $('#world-canvas'), ctx = canvas.getContext('2d');
const markerLayer = $('#map-markers'), labelLayer = $('#map-labels');
const placeDialog = $('#place-dialog'), aboutDialog = $('#about-dialog');
const W = 1600, H = 2100;
const state = {zoom:1, x:0, y:0, layer:'landscape', selected:null};
let geo, art, terrain, land, fit=1, width=1, height=1, ready=false, frame=0;
let markers=[], labels=[], pointers=new Map(), gesture=null, moved=false, historyOwned=false;
const colours = {ink:'#443549',green:'#498047',blue:'#2a72c9',gold:'#c5a361',red:'#b43c34',purple:'#8c4ac4'};
const legends = {
 '01':['Spiegelburg von Koerich','Zwischen alten Mauern und verwunschenen Hainen bewachen Spiegelritter die Wege des Westens.'],
 '02':['Herzschmiede des Minett','Unter den roten Erdhügeln schlägt die Herzschmiede. Erzadern nähren die Werkstätten und wecken steinerne Wächter.'],
 '03':['Melusinas gebrochener Eid','Unter den Felsen der Kronfestung flüstert Melusinas Legende durch die verborgenen Gänge.'],
 '04':['Hallen der drei Flüsse','Wege und Wasserläufe treffen im Herzen des Landes zusammen. Ein guter Ort für Handel und gemeinsame Aufmärsche.'],
 '05':['Abtei des letzten Liedes','Über den Wäldern des Nordens klingt der letzte Gesang einer verzauberten Abtei.'],
 '06':['Nachtwacht von Bourscheid','Eine alte Burg wacht über die Täler. Ihre erloschenen Signalfeuer müssen neu entzündet werden.'],
 '07':['Steinchronik von Useldingen','Die Ruinen des Westens bergen eine Chronik, deren Runen nur bei Mondschein sichtbar werden.'],
 '08':['Gerberei der Dornen','Dornen überwuchern die Werkstätten am Wasser. Dahinter lauern die Hüter des dunklen Hains.'],
 '09':['Schwur der Sturmfeste','Auf steilen Felsen halten die Ritter der Sturmfeste ihre uralte Wacht.'],
 '10':['Das lebende Skriptorium','Zwischen Felswänden und Waldpfaden erwachen die Zeichen eines vergessenen Skriptoriums.'],
 '11':['Tor der freien Stadt','Händler ziehen durch das Tor am östlichen Handelsweg. Wer es sichert, verbindet die Reiche.'],
 '12':['Masken von Ricciacum','Unter den sanften Hügeln ruhen die Masken einer versunkenen Siedlung. Ihre Träger kennen den Weg in die Tiefe.']
};
const escapeHTML = value => String(value).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const random = (()=>{let seed=92461;return()=>{seed=(Math.imul(seed,1664525)+1013904223)>>>0;return seed/4294967296;};})();
const clamp = (v,min,max)=>Math.max(min,Math.min(max,v));
const distance = (a,b)=>Math.hypot(a[0]-b[0],a[1]-b[1]);
const image = name => new Promise((resolve,reject)=>{const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>reject(new Error(`Bild fehlt: ${name}`));img.src=`art/${name}.webp`;});
const canton = id=>geo.cantons.find(c=>c.id===id);
const commune = name=>geo.communes.find(c=>c.name===name);
const point = name=>commune(name)?.point;
const toScreen = ([x,y])=>[width/2+(x-W/2)*fit*state.zoom+state.x,height/2+(y-H/2)*fit*state.zoom+state.y];
const toWorld = ([x,y])=>[(x-width/2-state.x)/(fit*state.zoom)+W/2,(y-height/2-state.y)/(fit*state.zoom)+H/2];
const maxZoom = ()=>Math.max(5,.9/fit);
function requestDraw(){if(!frame)frame=requestAnimationFrame(()=>{frame=0;draw();});}
function size(){const r=stage.getBoundingClientRect(), old=fit, previousWidth=width; width=r.width;height=r.height;fit=Math.min((width-32)/W,(height-66)/H);fit=Math.max(.045,fit);if(previousWidth>1&&state.zoom>1.05){state.zoom=clamp(state.zoom*old/fit,1,maxZoom());}else if(old>0){state.x*=fit/old;state.y=-20;}const dpr=Math.min(devicePixelRatio||1,2);canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr);constrain();requestDraw();}
function constrain(){const k=fit*state.zoom;state.x=clamp(state.x,-W*k*.62,W*k*.62);state.y=clamp(state.y,-H*k*.62,H*k*.62);}
function zoomTo(next, anchor=[width/2,height/2]){const before=toWorld(anchor);state.zoom=clamp(next,1,maxZoom());const after=toScreen(before);state.x+=anchor[0]-after[0];state.y+=anchor[1]-after[1];constrain();requestDraw();}
function focusPoint(p,zoom=3){state.zoom=clamp(Math.max(zoom,.7/fit),1,maxZoom());state.x=-(p[0]-W/2)*fit*state.zoom;state.y=-(p[1]-H/2)*fit*state.zoom;constrain();requestDraw();}
function overview(){state.zoom=1;state.x=0;state.y=-20;state.selected=null;$('#place-select').value='';setLayer(state.layer);requestDraw();}

function createMarkers(){
 const mersch=point('Mersch')||canton('04').point, lux=point('Luxembourg')||canton('03').point;
 const echternach=point('Echternach')||canton('10').point;
 const add=(id,name,type,asset,p,options={})=>markers.push({id,name,type,asset,p,...options});
 add('crown','Krounbuerg','crown','crown',lux,{canton:'03',overview:true});
 add('home','Meine Stadt','city','castle',[mersch[0]-65,mersch[1]+18],{canton:'04',overview:true,owner:'Deine Stadt · Waldwacht'});
 add('vianden','Sturmfeste','dungeon','tower',point('Vianden')||canton('09').point,{canton:'09',overview:true});
 add('echternach','Skriptorium','dungeon','dungeon',[echternach[0]-25,echternach[1]-18],{canton:'10',overview:true});
 add('clervaux','Nordwacht','city','castle',point('Clervaux')||canton('05').point,{canton:'05',overview:true,owner:'Verbündete · Waldwacht'});
 add('minett','Herzschmiede','dungeon','quarry',point('Esch-sur-Alzette')||canton('02').point,{canton:'02',overview:true});
 add('wiltz','Dornenhain','dungeon','dungeon',point('Wiltz')||canton('08').point,{canton:'08',overview:true});
 add('alliance','Halle des Bundes','alliance','alliance',[mersch[0]+72,mersch[1]+26],{canton:'04'});
 add('ally1','Eichenwacht','city','castle',[mersch[0]+10,mersch[1]-108],{canton:'04',owner:'Verbündete · Waldwacht'});
 add('ally2','Talheim','city','castle',[mersch[0]-114,mersch[1]+157],{canton:'04',owner:'Verbündete · Moselbund'});
 add('orcs','Dornensippe','monster','orc',[mersch[0]-205,mersch[1]-36],{canton:'04'});
 add('golem','Erzwächter','monster','golem',[canton('02').point[0]+145,canton('02').point[1]-65],{canton:'02'});
 add('crystal','Kristallhain','resource','crystal',[mersch[0]+161,mersch[1]-62],{canton:'04'});
 add('farm','Kornfelder','resource','farm',[mersch[0]+64,mersch[1]+153],{canton:'04'});
 add('lumber','Waldlager','resource','lumber',[mersch[0]-164,mersch[1]-164],{canton:'04'});
 for(const m of markers){
  // Avoid decorative placements spilling outside the real country outline.
  if(!ctx.isPointInPath(land,...m.p))m.p=canton(m.canton).point.slice();
  const b=document.createElement('button');b.type='button';b.className='map-marker';b.dataset.type=m.type;b.dataset.place=m.id;
  b.setAttribute('aria-label',`${m.name} – ${m.type==='city'?'Spielerstadt':m.type==='dungeon'?'Dungeon':m.type==='crown'?'Kronfestung':m.type==='monster'?'PvE-Monster':m.type==='resource'?'Rohstoffplatz':'Allianzgebäude'}`);
  b.style.setProperty('--marker-color',colours[m.type==='city'||m.type==='alliance'?'blue':m.type==='monster'?'red':m.type==='dungeon'?'purple':'gold']);
  b.innerHTML=`<img src="art/${m.asset}.webp" alt="" draggable="false"><span class="marker-caption">${escapeHTML(m.name)}</span>`;
  b.addEventListener('click',e=>{e.stopPropagation();if(!moved)openPlace(m.id);});markerLayer.append(b);m.button=b;
 }
 for(const c of geo.cantons){const el=document.createElement('span');el.className='region-label';el.textContent=c.name;labelLayer.append(el);labels.push({el,p:[c.point[0],c.point[1]+77],canton:c.id});}
 for(const [name,p] of [['Die Wälder des Éislek',[440,500]],['Tal der drei Flüsse',[770,1320]],['Die Hügel des Minett',[485,1940]]]){const el=document.createElement('span');el.className='terrain-label';el.textContent=name;labelLayer.append(el);labels.push({el,p,terrain:true});}
}

function curve(points){const p=new Path2D();p.moveTo(...points[0]);for(let i=1;i<points.length-1;i++){const next=points[i+1];p.quadraticCurveTo(...points[i],(points[i][0]+next[0])/2,(points[i][1]+next[1])/2);}p.lineTo(...points.at(-1));return p;}
function paintTerrain(){
 terrain=document.createElement('canvas');terrain.width=W;terrain.height=H;const g=terrain.getContext('2d');
 g.save();g.fillStyle='#b9c985';g.shadowColor='#75634355';g.shadowBlur=18;g.shadowOffsetY=10;g.fill(land);g.restore();
 g.save();g.clip(land);g.fillStyle='#b9c985';g.fillRect(0,0,W,H);
 // Reuse the approved grass plate; gentle regional washes keep one continuous world.
 g.drawImage(art.terrain,0,0,W,H);g.fillStyle='#81a56a25';g.fillRect(0,0,W,970);
 const wash=(x,y,r,c)=>{const grd=g.createRadialGradient(x,y,0,x,y,r);grd.addColorStop(0,c);grd.addColorStop(1,'transparent');g.fillStyle=grd;g.fillRect(x-r,y-r,r*2,r*2);};
 wash(570,550,590,'#51876166');wash(540,1900,330,'#bc765552');wash(1230,1620,300,'#ecd49360');
 const roads=[];for(const [a,b] of [['05','08'],['05','09'],['08','06'],['06','09'],['06','04'],['07','04'],['07','01'],['01','03'],['04','03'],['04','10'],['10','11'],['11','12'],['03','12'],['03','02']]){
  const x=canton(a).point,y=canton(b).point;roads.push(curve([x,[(x[0]+y[0])/2+24,(x[1]+y[1])/2-32],y]));
 }
 for(const m of markers.filter(m=>['city','alliance','crown'].includes(m.type))){const p=canton(m.canton).point;roads.push(curve([m.p,[(m.p[0]+p[0])/2+10,(m.p[1]+p[1])/2],p]));}
 g.lineCap='round';g.lineJoin='round';for(const p of roads){g.strokeStyle='#aa976064';g.lineWidth=18;g.stroke(p);g.strokeStyle='#ecd09a';g.lineWidth=12;g.stroke(p);}
 const rivers=[
  curve([[1040,510],[850,670],[920,905],[1120,1015],[1340,1090],[1420,1320],[1260,1650],[1240,1940]]),
  curve([[270,900],[430,1000],[640,890],[840,1000],[920,1100],[870,1310],[980,1470],[912,1600],[1070,1820]]),
  curve([[620,1540],[758,1510],[828,1580],[940,1570],[1050,1660]])
 ];
 for(const p of rivers){g.lineWidth=24;g.strokeStyle='#59846470';g.stroke(p);g.lineWidth=17;g.strokeStyle='#3793bd';g.stroke(p);g.lineWidth=11;g.strokeStyle='#72bfd1';g.stroke(p);g.lineWidth=3;g.strokeStyle='#c5eff070';g.stroke(p);}
 const lake=curve([[480,850],[480,805],[570,785],[626,823],[603,863],[555,881],[480,850]]);g.fillStyle='#63b4c9';g.fill(lake);g.strokeStyle='#3793bd';g.lineWidth=6;g.stroke(lake);
 // Small bridges emphasize traversable connections without imposing live movement rules.
 for(const [x,y,angle] of [[865,1210,.2],[839,998,-.1],[924,1565,-.35],[1287,1630,.3]]){g.save();g.translate(x,y);g.rotate(angle);g.fillStyle='#a88859';g.fillRect(-29,-12,58,24);g.fillStyle='#edd09a';g.fillRect(-28,-8,56,16);g.strokeStyle='#8b714e';g.lineWidth=2;for(let v=-23;v<28;v+=8){g.beginPath();g.moveTo(v,-8);g.lineTo(v,8);g.stroke();}g.restore();}
 const scenery=[];
 for(let n=0;n<2100;n++){
  const x=120+random()*1360,y=110+random()*1880,p=[x,y];
  if(!g.isPointInPath(land,x,y)||markers.some(m=>distance(p,m.p)<(m.type==='crown'?112:77)))continue;
  g.lineWidth=36;if(roads.some(r=>g.isPointInStroke(r,x,y))||rivers.some(r=>g.isPointInStroke(r,x,y)))continue;
  const density=y<900?.75:y>1720?.16:.37;
  if(random()>density||scenery.some(t=>distance(p,[t.x,t.y])<30))continue;
  scenery.push({x,y,key:y<940&&random()>.23?'pine':'oak',s:55+random()*45});
 }
 // Smaller settlements and resource sites are scenery; the labeled sample locations are interactive.
 let placed=0;for(let n=0;n<300&&placed<42;n++){
  const x=220+random()*1120,y=270+random()*1660;
  if(!g.isPointInPath(land,x,y)||markers.some(m=>distance([x,y],m.p)<110)||scenery.some(t=>distance([x,y],[t.x,t.y])<48))continue;
  g.lineWidth=27;if(rivers.some(r=>g.isPointInStroke(r,x,y)))continue;
  const keys=['farm','lumber','quarry','castle','gold','crystal'];scenery.push({x,y,key:keys[placed%keys.length],s:placed%6===3?89:68});placed++;
 }
 scenery.sort((a,b)=>a.y-b.y);for(const t of scenery){const im=art[t.key];g.drawImage(im,t.x-t.s/2,t.y-t.s*.88,t.s,t.s);}
 // Clearings reserve comfortable space for the large landmarks.
 for(const m of markers){g.fillStyle='#e8dba42b';g.beginPath();g.ellipse(m.p[0],m.p[1]+4,m.type==='crown'?70:39,m.type==='crown'?25:14,0,0,Math.PI*2);g.fill();}
 g.restore();
}

function draw(){
 if(!ready)return;
 const ratio=canvas.width/width,k=fit*state.zoom;ctx.setTransform(ratio,0,0,ratio,0,0);ctx.clearRect(0,0,width,height);
 ctx.save();ctx.translate(width/2+state.x-W*k/2,height/2+state.y-H*k/2);ctx.scale(k,k);ctx.drawImage(terrain,0,0);
 if(state.layer!=='landscape'){
  const features=state.layer==='cantons'?geo.cantons:geo.communes;
  ctx.lineJoin='round';ctx.lineWidth=state.layer==='cantons'?1.5/k:.9/k;
  ctx.strokeStyle=state.layer==='cantons'?'#5c4270aa':'#6653378c';
  for(const f of features){if(state.layer==='communes'){const c=Number(f.id)%3;ctx.fillStyle=c===0?'#b43c3417':c===1?'#2a72c91b':'#f1e2bd22';ctx.fill(f.shape);}ctx.stroke(f.shape);}
 }
 if(state.selected){const f=geo.communes.find(c=>c.id===state.selected);if(f){ctx.fillStyle='#f1e2bd6b';ctx.fill(f.shape);ctx.strokeStyle='#5c4270';ctx.lineWidth=2.5/k;ctx.stroke(f.shape);}}
 ctx.restore();
 const occupied=[];
 for(const m of markers){
  const p=toScreen(m.p), important=m.id==='crown'||m.id==='home';
  const show=(m.overview&&(k>.14&&(width>480||important||state.zoom>=1.8)||m.id==='crown')||k>=.38)&&p[0]>-80&&p[0]<width+80&&p[1]>-50&&p[1]<height+65;
  m.button.hidden=!show;if(!show)continue;
  const base=m.type==='crown'?170:m.type==='dungeon'?110:100;
  const s=clamp(base*k,important?55:44,m.type==='crown'?128:90);
  m.button.style.left=p[0]+'px';m.button.style.top=p[1]+'px';m.button.style.setProperty('--marker-size',s+'px');
  // Captions have priority over passive labels in tight views.
  occupied.push([p[0]-Math.max(s/2,51),p[1]-s*.85,p[0]+Math.max(s/2,51),p[1]+25]);
 }
 for(const l of labels){
  const p=toScreen(l.p), textWidth=l.el.offsetWidth||95, rect=[p[0]-textWidth/2,p[1]-9,p[0]+textWidth/2,p[1]+9];
  const visible=(l.terrain?state.layer==='landscape'&&state.zoom>1.5:state.zoom<3.5||state.layer!=='landscape')&&p[0]>35&&p[0]<width-35&&p[1]>20&&p[1]<height-60;
  const overlap=occupied.some(r=>rect[0]<r[2]+5&&rect[2]>r[0]-5&&rect[1]<r[3]+5&&rect[3]>r[1]-5);
  l.el.hidden=!visible||overlap;if(!l.el.hidden){l.el.style.left=p[0]+'px';l.el.style.top=p[1]+'px';occupied.push(rect);}
 }
 stage.classList.toggle('zoomed',state.zoom>1.2);$('#zoom-level').value=`${state.zoom.toFixed(1).replace('.',',')}×`;
 $('#zoom-in').disabled=state.zoom>=maxZoom();$('#zoom-out').disabled=state.zoom<=1;
 $('#map-hint').textContent=state.layer==='communes'?'Gemeinde antippen · Städte einzeln auswählen':state.layer==='cantons'?'Kanton antippen und seinen Dungeon entdecken':'Ziehen zum Erkunden · Zoomen für mehr Details';
}

function setLayer(layer){state.layer=layer;document.querySelectorAll('[data-layer]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.layer===layer)));$('#map-status').textContent=layer==='communes'?'100 Gemeinden · Blau: verbündet · Rot: PvE · Beige: frei':layer==='cantons'?'12 Kantone · je ein eigener Dungeon':'Luxemburg · 12 Kantone · 100 Gemeinden';requestDraw();}
function showDialog(dialog){for(const d of [placeDialog,aboutDialog])if(d!==dialog&&d.open)d.close();if(!dialog.open)dialog.showModal();}
function setHash(value){if(location.hash!==`#${value}`){history.pushState({worldPreview:true},'',`#${value}`);historyOwned=true;}renderHash();}
function closeDialog(){if(historyOwned){historyOwned=false;history.back();}else{history.replaceState(null,'',location.pathname+location.search);renderHash();}}
function openPlace(id){setHash(`ort-${id}`);}
function openCommune(id){state.selected=id;requestDraw();setHash(`gemeinde-${id}`);}
function detail(kind,title,html,cantonId){$('#place-kind').textContent=kind;$('#place-title').textContent=title;$('#place-content').innerHTML=html;const link=$('#dungeon-link');link.hidden=!cantonId;if(cantonId)link.href=`../dungeon-preview/#kanton-${cantonId}`;showDialog(placeDialog);}
function renderHash(){
 const hash=location.hash.slice(1);
 if(hash==='entwurf'){showDialog(aboutDialog);return;}
 if(hash.startsWith('gemeinde-')){
  const c=geo.communes.find(c=>c.id===hash.slice(9));if(c){state.selected=c.id;requestDraw();const status=Number(c.id)%3;
   detail(`Gemeinde · Kanton ${canton(c.canton).name}`,c.name,`<span class="detail-status ${status===0?'hostile':status===1?'safe':''}">${status===0?'Von PvE-Monstern besetzt':status===1?'Vom Bund gesichert':'Noch ungebunden'} · Beispiel</span><p>Ein gemeinsames Gebiet mit Platz für mehrere Spielerstädte, Rohstoffplätze und Begegnungen.</p><p>${status===0?'Späher erkunden die Bedrohung, Verbündete liefern Nachschub und eine gemeinsame Armee befreit die Gemeinde.':status===1?'Die Städte deiner Verbündeten sichern die Wege. Eine Garnison hilft bei angekündigten Gegenangriffen.':'Hier kann dein Bund einen neuen Stützpunkt gründen und die nächsten Wege erschließen.'}</p><div class="detail-facts"><span><strong>Mehrere Städte</strong>je Gemeinde</span><span><strong>Ein Dungeon</strong>im Kanton ${escapeHTML(canton(c.canton).name)}</span></div><p class="dialog-note">Fantasy-Entwurf. Besitzstände sind Beispiele; persönliche Städte und Fortschritt bleiben bei einem Gebietsverlust erhalten.</p>`,c.canton);return;}
 }
 if(hash.startsWith('kanton-')){
  const c=canton(hash.slice(7));if(c){detail(`Kanton ${c.name}`,legends[c.id][0],`<img class="detail-art" src="art/dungeon.webp" alt=""><p>${legends[c.id][1]}</p><p>Die Gemeinden bilden das eroberbare Gebiet. Der Kantonsdungeon ist das gemeinsame Abenteuer und könnte ein Siegel für den Weg zur Krone verleihen.</p><p class="dialog-note">Namen und Regeln sind ein Fantasy-Entwurf. Die historischen Anregungen und Beute findest du im Dungeonatlas.</p>`,c.id);return;}
 }
 if(hash.startsWith('ort-')){
  const m=markers.find(m=>m.id===hash.slice(4));if(m){
   const img=`<img class="detail-art" src="art/${m.asset}.webp" alt="">`;
   if(m.type==='crown')detail('Der Sitz der Krone · Luxemburg','Krounbuerg',`${img}<span class="detail-status">Gemeinsames Weltziel</span><p>Hoch über den alten Gängen erhebt sich die Kronfestung. Hier endet der Weg eures Bundes – und beginnt die Herrschaft eines neuen Grand-Duc oder einer neuen Grande-Duchesse.</p><div class="detail-facts"><span><strong>6 Siegel</strong>als mögliche Zugangsvoraussetzung</span><span><strong>1 Kandidat</strong>vom Bund gemeinsam bestimmt</span></div><p>Gemeinden befreien, Kantonsprüfungen bestehen, gemeinsam die Festung einnehmen. Die Krone soll Zusammenarbeit belohnen.</p><p class="dialog-note">Konzept, noch keine festgelegte Spielregel. Die Burgillustration ist ein Platzhalter aus den vorhandenen Spielgrafiken.</p>`,'03');
   else if(m.type==='city')detail(`Spielerstadt · Kanton ${canton(m.canton).name}`,m.name,`${img}<span class="detail-status safe">${escapeHTML(m.owner)}</span><p>Deine eigene Stadt innerhalb eines größeren Gemeindegebiets. Dein persönlicher Ausbau bleibt bestehen, während dein Bund um die umliegenden Gebiete kämpft.</p><p>Zwischen den Städten liegen Rohstoffplätze, Verbindungswege und Begegnungen. In der endgültigen Spielwelt wäre jede Gemeinde groß genug für mehrere Spieler.</p><p class="dialog-note">Beispielstadt; die Abstände sind für diese Übersicht vereinfacht. Keine Verbindung zu deinem echten Spielstand.</p>`,m.canton);
   else if(m.type==='dungeon')detail(`Dungeon · Kanton ${canton(m.canton).name}`,legends[m.canton][0],`${img}<p>${legends[m.canton][1]}</p><p>Eine feste Landmarke auf der Weltkarte. Ein Tipp öffnet das Dungeonfenster mit Geschichte, Gegnern und Beute.</p><p class="dialog-note">Im Dungeonatlas kannst du den ausführlichen Entwurf ansehen.</p>`,m.canton);
   else if(m.type==='monster')detail('PvE-Bedrohung · Beispiel',m.name,`${img}<span class="detail-status hostile">Feindliches Lager</span><p>Von hier könnte ein angekündigter Rückeroberungszug gegen nahe Gemeinden starten. Euer Bund entscheidet, ob er das Lager angreift oder eine Garnison zur Verteidigung entsendet.</p><p>Angriffe hätten feste Vorwarnzeiten. Niemand müsste die Karte rund um die Uhr bewachen.</p><p class="dialog-note">Nur ein gezeigtes Ereignis; hier läuft kein echter Angriff.</p>`);
   else if(m.type==='alliance')detail('Allianzgebäude · Beispiel',m.name,`${img}<p>Der Treffpunkt eures Bundes: Nachschub sammeln, gemeinsame Angriffe planen und Garnisonen verteilen.</p><p>Die umliegenden Städte gehören unterschiedlichen Spielern. Die Gemeinde zu sichern ist eine gemeinsame Aufgabe.</p><p class="dialog-note">Beispielbündnis: Waldwacht und Moselbund.</p>`);
   else detail('Rohstoffplatz · Beispiel',m.name,`${img}<p>Ein Sammelplatz zwischen den Spielerstädten. Sichere Wege und verbündete Gemeinden erleichtern die Versorgung eures Bundes.</p><p>Rohstoffe bleiben über die ganze Welt verteilt, damit kleine und große Allianzen Entwicklungsmöglichkeiten haben.</p><p class="dialog-note">Gestaltungsvorschau; Sammeln ist hier nicht aktiv.</p>`);
   return;
  }
 }
 for(const d of [placeDialog,aboutDialog])if(d.open)d.close();
}

function bindControls(){
 document.querySelectorAll('[data-layer]').forEach(b=>b.addEventListener('click',()=>setLayer(b.dataset.layer)));
 $('#zoom-in').addEventListener('click',()=>zoomTo(state.zoom*1.4));$('#zoom-out').addEventListener('click',()=>zoomTo(state.zoom/1.4));$('#overview').addEventListener('click',overview);
 $('#my-city').addEventListener('click',()=>{focusPoint(markers.find(m=>m.id==='home').p,3.7);$('#map-status').textContent='Beispielregion Mersch · Städte, Bündnis und Rohstoffe';});
 $('#crown-jump').addEventListener('click',()=>{focusPoint(markers.find(m=>m.id==='crown').p,3);$('#map-status').textContent='Krounbuerg · Das gemeinsame Ziel eures Bundes';});
 $('#about-open').addEventListener('click',()=>setHash('entwurf'));
 $('#place-select').addEventListener('change',e=>{const c=geo.communes.find(c=>c.id===e.target.value);if(c){focusPoint(c.point,3.5);setLayer('communes');openCommune(c.id);}});
 document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',closeDialog));
 for(const dialog of [placeDialog,aboutDialog]){
  dialog.addEventListener('cancel',e=>{e.preventDefault();closeDialog();});
  dialog.addEventListener('click',e=>{if(e.target!==dialog)return;const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeDialog();});
 }
 window.addEventListener('popstate',()=>{historyOwned=Boolean(history.state?.worldPreview);renderHash();});
 window.addEventListener('hashchange',renderHash);
 stage.addEventListener('wheel',e=>{if(e.target.closest('button'))return;e.preventDefault();const r=stage.getBoundingClientRect();zoomTo(state.zoom*Math.exp(-e.deltaY*.0015),[e.clientX-r.left,e.clientY-r.top]);},{passive:false});
 stage.addEventListener('keydown',e=>{if(e.target!==stage)return;const dirs={ArrowLeft:[65,0],ArrowRight:[-65,0],ArrowUp:[0,65],ArrowDown:[0,-65]};if(dirs[e.key]){e.preventDefault();state.x+=dirs[e.key][0];state.y+=dirs[e.key][1];constrain();requestDraw();}else if(['+','=','-','Home'].includes(e.key)){e.preventDefault();if(e.key==='Home')overview();else zoomTo(state.zoom*(e.key==='-'?1/1.3:1.3));}});
 const position=e=>{const r=stage.getBoundingClientRect();return[e.clientX-r.left,e.clientY-r.top];};
 function startGesture(){const p=[...pointers.values()];gesture={points:p.map(x=>x.slice()),x:state.x,y:state.y,zoom:state.zoom};if(p.length===2){gesture.distance=distance(...p);gesture.anchor=[(p[0][0]+p[1][0])/2,(p[0][1]+p[1][1])/2];gesture.world=toWorld(gesture.anchor);}}
 stage.addEventListener('pointerdown',e=>{if(e.target.closest('button,select')){moved=false;return;}pointers.set(e.pointerId,position(e));stage.setPointerCapture(e.pointerId);moved=false;startGesture();});
 stage.addEventListener('pointermove',e=>{if(!pointers.has(e.pointerId)||!gesture)return;pointers.set(e.pointerId,position(e));const p=[...pointers.values()];if(p.length===2&&gesture.distance){const mid=[(p[0][0]+p[1][0])/2,(p[0][1]+p[1][1])/2];state.zoom=clamp(gesture.zoom*distance(...p)/Math.max(1,gesture.distance),1,maxZoom());state.x=mid[0]-width/2-(gesture.world[0]-W/2)*fit*state.zoom;state.y=mid[1]-height/2-(gesture.world[1]-H/2)*fit*state.zoom;moved=true;}else if(p.length===1){const dx=p[0][0]-gesture.points[0][0],dy=p[0][1]-gesture.points[0][1];if(Math.hypot(dx,dy)>5)moved=true;state.x=gesture.x+dx;state.y=gesture.y+dy;}stage.classList.toggle('dragging',moved);constrain();requestDraw();});
 stage.addEventListener('pointerup',e=>{const wasTracked=pointers.has(e.pointerId);pointers.delete(e.pointerId);if(!wasTracked)return;if(pointers.size){startGesture();return;}gesture=null;stage.classList.remove('dragging');if(!moved&&state.layer!=='landscape'){const p=toWorld(position(e));ctx.save();ctx.setTransform(1,0,0,1,0,0);const features=state.layer==='communes'?geo.communes:geo.cantons;const f=features.find(c=>ctx.isPointInPath(c.shape,...p));ctx.restore();if(f){if(state.layer==='communes')openCommune(f.id);else setHash(`kanton-${f.id}`);}}});
 const cancelPointer=e=>{pointers.delete(e.pointerId);if(pointers.size)startGesture();else{gesture=null;stage.classList.remove('dragging');}};
 stage.addEventListener('pointercancel',cancelPointer);stage.addEventListener('lostpointercapture',cancelPointer);
 document.addEventListener('visibilitychange',()=>{pointers.clear();gesture=null;stage.classList.remove('dragging');if(!document.hidden)requestDraw();});
 new ResizeObserver(size).observe(stage);
}

async function init(){
 try{
  const response=await fetch('geography.json');if(!response.ok)throw new Error('Grenzdaten nicht erreichbar');geo=await response.json();
  land=new Path2D(geo.cantons.map(c=>c.path).join(''));
  for(const kind of ['cantons','communes'])for(const c of geo[kind])c.shape=new Path2D(c.path);
  const keys=['terrain','oak','pine','castle','crown','alliance','farm','lumber','quarry','gold','crystal','tower','dungeon','orc','golem'];
  art=Object.fromEntries(await Promise.all(keys.map(async k=>[k,await image(k)])));
  for(const c of [...geo.communes].sort((a,b)=>a.name.localeCompare(b.name,'de'))){const o=document.createElement('option');o.value=c.id;o.textContent=c.name;$('#place-select').append(o);}
  createMarkers();paintTerrain();bindControls();ready=true;size();renderHash();$('#map-loading').hidden=true;
 }catch(error){$('#map-loading').textContent='Die Vorschau konnte nicht geladen werden. Bitte lade die Seite erneut.';console.error('Luxembourg preview:',error);}
}
init();
