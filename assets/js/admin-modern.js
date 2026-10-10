/* Admin navigation and atomic table editing. No game rules or payouts run here. */
(async()=>{
 'use strict';if(window.ConquerLocale?.ready)await window.ConquerLocale.ready;
 const $=(s,r=document)=>r.querySelector(s),$$=(s,r=document)=>[...r.querySelectorAll(s)];
 const t=(key,values={})=>window.ConquerLocale.t('admin.modern.'+key,values);
 const context=$('[data-modern-context]');
 if(context){const world=$('[data-modern-world]',context),scope=$('[name=scope]',context);const update=()=>{world.hidden=scope.value!=='world';};scope.addEventListener('change',update);update();}
 const editor=$('[data-batch-editor]');if(!editor)return;
 const data=JSON.parse($('[data-batch-data]',editor).textContent),form=$('[data-batch-form]',editor);
 const sources=new Map(data.sources.map(s=>[s.key,s])),baseline=new Map();let category='items',submitting=false;
 const dropText=key=>window.ConquerLocale.t('admin.drops.'+key);
 const itemGroups=new Map(data.targets.rows.map(target=>[target.value,target.drop_group]));
 const viewKey='admin-drop-groups:'+location.pathname+':'+editor.dataset.context;
 let view={};try{view=JSON.parse(sessionStorage.getItem(viewKey)||'{}')||{};}catch{}
 if(['items','speedups','boosts','resources'].includes(view.category))category=view.category;
 for(const field of ['search','level','type','rule']){const input=$('[data-batch-'+field+']',editor);if(typeof view[field]==='string'&&(field==='search'||[...input.options].some(option=>option.value===view[field])))input.value=view[field];}
 const number=n=>Number(n).toLocaleString(window.ConquerLocale.locale||'en',{maximumFractionDigits:4});
 const normalized=rows=>rows.map(r=>({target:String(r.target),quantity:Number(r.quantity),chance:Number(r.chance),...(r.quantity_min!=null&&r.quantity_min!==''?{quantity_min:Number(r.quantity_min)}:{}),...(r.exclusive_group?{exclusive_group:r.exclusive_group}:{})}));
 const read=group=>$$('.batch-entry',group).map(row=>({target:$('[data-field=target]',row).value,quantity:Number($('[data-field=quantity]',row).value),chance:Number($('[data-field=chance]',row).value),...($('[data-field=quantity_min]',row)?.value?{quantity_min:Number($('[data-field=quantity_min]',row).value)}:{}),...(row.dataset.exclusiveGroup?{exclusive_group:row.dataset.exclusiveGroup}:{})}));
 const signature=rows=>JSON.stringify(normalized(rows));
 const targets=group=>data.targets[group].map(target=>({...target,name:/^(treasure|relic):/.test(target.value)&&window.ConquerRelicPresentation?window.ConquerRelicPresentation.name({treasure_code:Number(target.value.split(':')[1])}):target.name}));
 function populate(select){if(select.dataset.loaded)return;const selected=select.value,group=select.closest('[data-batch-group]').dataset.batchGroup;
  select.replaceChildren(new Option(t('select_reward'),''));
  if(group==='rows'){for(const category of ['items','speedups','boosts','resources']){const options=targets(group).filter(target=>target.drop_group===category);if(!options.length)continue;const optgroup=document.createElement('optgroup');optgroup.label=dropText('group_'+category);optgroup.append(...options.map(o=>new Option(o.name,o.value)));select.append(optgroup);}}
  else select.append(...targets(group).map(o=>new Option(o.name,o.value)));
  select.value=selected;select.dataset.loaded='1';
 }
 function rowMarkup(drop){const row=document.createElement('div');row.className='batch-entry';row.dataset.emptyCategory=category;if(drop.exclusive_group)row.dataset.exclusiveGroup=drop.exclusive_group;
  for(const [field,key] of [['target','reward'],['quantity','quantity'],['chance','chance']]){const label=document.createElement('label');label.append(document.createTextNode(t(key)));let input;
   if(field==='target'){input=document.createElement('select');input.append(new Option(t('select_reward'),''));if(drop.target)input.append(new Option(drop.target,drop.target,true,true));}
   else{input=document.createElement('input');input.type='number';input.min=field==='quantity'?'1':'0';input.max=field==='quantity'?'100000':'100';input.step=field==='quantity'?'1':'.0001';input.value=drop[field];}
   input.dataset.field=field;input.required=true;label.append(input);if(field==='quantity'){const details=document.createElement('details');details.open=drop.quantity_min!=null;const summary=document.createElement('summary');summary.textContent=window.ConquerLocale.t('admin.drops.quantity_min');const min=document.createElement('input');min.type='number';min.min='1';min.max='100000';min.step='1';min.dataset.field='quantity_min';min.value=drop.quantity_min??'';min.setAttribute('aria-label',summary.textContent);details.append(summary,min);label.append(details);}row.append(label);
  }const label=document.createElement('label');label.append(document.createTextNode(t('average')));const output=document.createElement('output');output.dataset.batchAverage='';label.append(output);row.append(label);
  const button=document.createElement('button');button.type='button';button.className='secondary batch-remove';button.dataset.batchRemove='';button.setAttribute('aria-label',t('remove'));button.textContent='×';row.append(button);return row;
 }
 function changes(){const updates=[];for(const row of $$('[data-batch-source]',editor)){const source=sources.get(row.dataset.batchSource),config={};for(const group of $$('[data-batch-group]',row)){const name=group.dataset.batchGroup,current=read(group);if(signature(current)!==baseline.get(source.key+':'+name))config[name]=current;}if(Object.keys(config).length)updates.push({source_key:source.key,revision:source.revision,parent_revision:source.parent_revision,config});}return updates;}
 function update(row,refresh=true){for(const group of $$('[data-batch-group]',row)){for(const entry of $$('.batch-entry',group)){const qty=Number($('[data-field=quantity]',entry).value),chance=Number($('[data-field=chance]',entry).value);$('[data-batch-average]',entry).value=number((($('[data-field=quantity_min]',entry)?.value?Number($('[data-field=quantity_min]',entry).value):qty)+qty)/2*chance);}$('.batch-empty',group).hidden=$$('.batch-entry',group).length>0;}if(!refresh)return;
  const updates=changes();for(const entry of $$('[data-batch-source]',editor))entry.classList.toggle('is-dirty',updates.some(u=>u.source_key===entry.dataset.batchSource));
  const status=$('[data-batch-status]',editor);status.textContent=updates.length?t('dirty',{count:updates.length}):t('clean');status.classList.toggle('is-dirty',updates.length>0);$('[data-batch-save]',editor).disabled=updates.length===0;
 }
 function filter(){const query=$('[data-batch-search]',editor).value.trim().toLocaleLowerCase(),level=$('[data-batch-level]',editor).value,type=$('[data-batch-type]',editor).value,rule=$('[data-batch-rule]',editor).value;let count=0;
  for(const button of $$('[data-batch-category]',editor))button.setAttribute('aria-pressed',String(button.dataset.batchCategory===category));
  $('[data-batch-relic-help]',editor).hidden=category!=='items';
  for(const row of $$('[data-batch-source]',editor)){const rewardNames=[];
   for(const group of $$('[data-batch-group]',row)){const name=group.dataset.batchGroup;group.hidden=name!=='rows'&&category!=='items';let visible=0;
    for(const entry of $$('.batch-entry',group)){const target=$('[data-field=target]',entry).value;entry.hidden=name==='rows'&&(itemGroups.get(target)||entry.dataset.emptyCategory||'items')!==category;if(!entry.hidden&&!group.hidden){visible++;rewardNames.push(data.targets[name].find(t=>t.value===target)?.name||'');}}
    $('.batch-empty',group).hidden=visible>0;if(name==='rows')$('[data-batch-group-title]',group).textContent=dropText('group_'+category);
   }
   const direct=$('[data-batch-resources]',row);if(direct)direct.hidden=category!=='resources';
   row.hidden=!(row.dataset.name+' '+rewardNames.join(' ').toLocaleLowerCase()).includes(query)||Boolean(type&&type!==row.dataset.type)||Boolean(level&&level!==row.dataset.level)||(rule==='custom'&&row.dataset.custom!=='1')||(rule==='default'&&row.dataset.custom==='1');if(!row.hidden)count++;
  }
  $('[data-batch-count]',editor).textContent=window.ConquerLocale.t('admin.drops.found',{count});$('[data-batch-empty]',editor).hidden=count>0;
  try{sessionStorage.setItem(viewKey,JSON.stringify({category,search:$('[data-batch-search]',editor).value,level,type,rule}));}catch{}
 }
 for(const row of $$('[data-batch-source]',editor)){const source=sources.get(row.dataset.batchSource);for(const group of $$('[data-batch-group]',row)){const name=group.dataset.batchGroup;baseline.set(source.key+':'+name,signature(source.config[name]));for(const select of $$('select',group)){const target=targets(name).find(t=>t.value===select.value);if(target)select.selectedOptions[0].textContent=target.name;}}
 }
 for(const restored of (Array.isArray(data.restored)?data.restored:[]).slice(0,data.limit)){if(!restored||typeof restored!=='object')continue;const row=$$('[data-batch-source]',editor).find(r=>r.dataset.batchSource===restored.source_key);if(!row)continue;for(const name of ['rows','fragment_rows','relic_rows'])if(Array.isArray(restored.config?.[name])){const group=$(`[data-batch-group="${name}"]`,row),entries=$('[data-batch-entries]',group);const rows=restored.config[name].slice(0,name==='rows'?200:100).filter(r=>r&&typeof r==='object'&&!Array.isArray(r)).map(r=>({target:typeof r.target==='string'?r.target:'',quantity:typeof r.quantity==='number'||typeof r.quantity==='string'?r.quantity:'',chance:typeof r.chance==='number'||typeof r.chance==='string'?r.chance:'',...(typeof r.quantity_min==='number'||typeof r.quantity_min==='string'?{quantity_min:r.quantity_min}:{}),...(typeof r.exclusive_group==='string'?{exclusive_group:r.exclusive_group}:{})}));entries.replaceChildren(...rows.map(rowMarkup));for(const select of $$('select',group))populate(select);}}
 for(const row of $$('[data-batch-source]',editor))update(row,false);update($('[data-batch-source]',editor));filter();
 editor.addEventListener('focusin',e=>{if(e.target.matches('select[data-field=target]'))populate(e.target);});
 editor.addEventListener('pointerdown',e=>{if(e.target.matches('select[data-field=target]'))populate(e.target);});
 editor.addEventListener('input',e=>{const row=e.target.closest('[data-batch-source]');if(row)update(row);});
 editor.addEventListener('change',e=>{const row=e.target.closest('[data-batch-source]');if(row){update(row);filter();}});
 editor.addEventListener('click',e=>{const add=e.target.closest('[data-batch-add]'),remove=e.target.closest('[data-batch-remove]'),tab=e.target.closest('[data-batch-category]');
  if(tab){category=tab.dataset.batchCategory;filter();}
  if(add){const group=add.closest('[data-batch-group]'),entry=rowMarkup({target:'',quantity:1,chance:0});$('[data-batch-entries]',group).append(entry);populate($('select',entry));update(group.closest('[data-batch-source]'));filter();$('select',entry).focus();}
  if(remove){const row=remove.closest('[data-batch-source]');remove.closest('.batch-entry').remove();update(row);filter();}
 });
 $('[data-batch-search]',editor).addEventListener('input',filter);for(const select of $$('[data-batch-level],[data-batch-type],[data-batch-rule]',editor))select.addEventListener('change',filter);
 // Prevent hidden rows/groups from blocking native validation; validate all dirty data explicitly.
 form.noValidate=true;
 form.addEventListener('submit',e=>{const updates=changes(),note=$('[name=reason]',form);if(submitting){e.preventDefault();return;}
  let invalid=updates.length<1||updates.length>data.limit;for(const update of updates)for(const rows of Object.values(update.config))if(rows.some(r=>!r.target||!Number.isInteger(r.quantity)||r.quantity<1||r.quantity>100000||!Number.isFinite(r.chance)||r.chance<0||r.chance>100||(r.quantity_min!=null&&(!Number.isInteger(r.quantity_min)||r.quantity_min<1||r.quantity_min>r.quantity))))invalid=true;
  if(invalid||!note.checkValidity()){e.preventDefault();$('[data-batch-status]',editor).textContent=invalid?t('validation'):t('reason');if(!note.checkValidity())note.reportValidity();return;}
  $('[name=updates_json]',form).value=JSON.stringify(updates);submitting=true;$('[data-batch-save]',form).disabled=true;
 });
 window.addEventListener('beforeunload',e=>{if(changes().length&&!submitting){e.preventDefault();e.returnValue='';}});
})();
