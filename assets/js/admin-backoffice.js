/* Shared illustrated item picker and backoffice interactions. No remote dependencies. */
(async () => {
    'use strict';
    if(window.ConquerLocale?.ready)await window.ConquerLocale.ready;
    const $ = (s, root=document) => root.querySelector(s);
    const $$ = (s, root=document) => [...root.querySelectorAll(s)];
    const rewardText = (key, values={}) => window.ConquerLocale.t('admin.drops.'+key, values);
    const rewardNumber = value => value.toLocaleString(window.ConquerLocale?.locale??'en',{maximumFractionDigits:2});
    const catalog = JSON.parse($('#admin-item-catalog')?.textContent || '[]');
    const directCategories=['fragments','specific_fragments','relics'];
    catalog.forEach(item=>{
        if(!item.treasure_code||!window.ConquerRelicPresentation)return;
        const relic={treasure_code:item.treasure_code};
        item.name=rewardText(item.category==='relics'?'whole_named':'fragments_named',{name:window.ConquerRelicPresentation.name(relic)});
        item.image=window.ConquerRelicPresentation.image(item.image.split('/assets/art/')[0],relic);
    });
    const byCode = new Map(catalog.map(i=>[String(i.code),i]));
    $$('.item-select').forEach(container=>{
        const item=byCode.get($('input',container)?.value);if(!item?.treasure_code)return;
        $('strong',container).textContent=item.name;$('img',container).src=item.image;
    });
    const dialog = $('#item-picker-dialog');
    const esc = v => String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const menu = $('.mobile-menu');
    menu?.addEventListener('click',()=>{const open=menu.getAttribute('aria-expanded')!=='true';menu.setAttribute('aria-expanded',String(open));$('#admin-nav').classList.toggle('is-open',open);});
    const worldCreate=$('[data-world-create]');
    if(worldCreate){
        const name=$('[data-world-name]',worldCreate),slug=$('[data-world-slug]',worldCreate);let slugChanged=Boolean(slug?.value);
        const slugify=value=>value.toLocaleLowerCase('de').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/ß/g,'ss').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,20).replace(/-+$/,'');
        slug?.addEventListener('input',()=>{slugChanged=slug.value!=='';});
        name?.addEventListener('input',()=>{if(!slugChanged)slug.value=slugify(name.value);});
        const updateWeight=group=>{const total=$$('input[type="number"]',group).reduce((sum,input)=>sum+(Number(input.value)||0),0),output=$('[data-weight-total]',group);output.value=`${total} %`;output.classList.toggle('is-valid',total===100);output.classList.toggle('is-invalid',total!==100);};
        $$('[data-weight-group]',worldCreate).forEach(group=>{updateWeight(group);group.addEventListener('input',()=>updateWeight(group));});
    }
    let activePicker=null;
    function pickerResults(){
        if(!activePicker)return;
        const query=$('#item-picker-search').value.trim().toLocaleLowerCase('de');
        const category=$('#item-picker-category').value;
        const choices=catalog.filter(i=>(activePicker.dataset.fragments==='1'||!directCategories.includes(i.category))&&(!category||i.category===category)&&`${i.name} ${i.code} ${i.description}`.toLocaleLowerCase('de').includes(query));
        $('.picker-count').textContent=`${choices.length} Gegenstände gefunden`;
        $('.picker-results').innerHTML=(activePicker.dataset.optional==='1'?'<button type="button" class="secondary picker-result" data-pick-item="0">Kein Gegenstand</button>':'')+choices.map(i=>`<button type="button" class="secondary picker-result" data-pick-item="${esc(i.code)}"><span class="item-tile rarity-${esc(i.rarity)}"><img src="${esc(i.image)}" alt="" loading="lazy"></span><span><strong>${esc(i.name)}</strong><small>${esc(i.category_name)} · Nr. ${esc(i.code)}</small></span></button>`).join('')+(choices.length?'':'<p class="empty">Keine Treffer. Versuche einen anderen Namen oder eine andere Kategorie.</p>');
    }
    function chooseItem(container,value){
        const item=byCode.get(value),button=$('[data-item-picker]',container),input=$('input',container);
        const row=container.closest('.drop-row');
        if(row?.id&&input.value!==value){row.removeAttribute('id');row.classList.remove('is-focused');$('[data-drop-focus-note]').hidden=true;}
        input.value=value;
        $('strong',button).textContent=item?.name||'Kein Gegenstand';$('small',button).textContent=`${item?.category_name||'Optional'} · Auswählen`;
        if(item)$('img',button).src=item.image;
        button.setAttribute('aria-label',item?'Gegenstand ändern: '+item.name:'Gegenstand auswählen');
        input.dispatchEvent(new Event('change',{bubbles:true}));
    }
    document.addEventListener('click',event=>{
        const picker=event.target.closest('[data-item-picker]');
        if(picker){
            activePicker=picker.closest('.item-select');$('#item-picker-search').value='';$('#item-picker-category').value='';
            $$('#item-picker-category option').forEach(option=>{option.hidden=activePicker.dataset.fragments!=='1'&&directCategories.includes(option.value);});
            pickerResults();dialog.showModal();$('#item-picker-search').focus();return;
        }
        const selected=event.target.closest('[data-pick-item]');
        if(selected && activePicker){
            chooseItem(activePicker,selected.dataset.pickItem);dialog.close();$('[data-item-picker]',activePicker).focus();
        }
    });
    $('[data-picker-close]')?.addEventListener('click',()=>dialog.close());
    dialog?.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();dialog.close();}});
    $('#item-picker-search')?.addEventListener('input',pickerResults);
    $('#item-picker-category')?.addEventListener('change',pickerResults);
    const sourceSearch=$('[data-source-search]');
    const sourceBrowser=$('[data-source-browser]');
    const compactSources=window.matchMedia('(max-width: 1100px)');
    function sizeSourceBrowser(){if(sourceBrowser&&!sourceBrowser.hasAttribute('data-source-table'))sourceBrowser.open=!compactSources.matches;}
    function revealSource(){
        const selected=$('.source-choice.is-selected'),list=$('.source-list');
        if(sourceBrowser?.open&&selected&&list)list.scrollTop+=selected.getBoundingClientRect().top-list.getBoundingClientRect().top-list.clientHeight/2+selected.clientHeight/2;
    }
    sizeSourceBrowser();revealSource();
    compactSources.addEventListener('change',()=>{sizeSourceBrowser();revealSource();});
    sourceBrowser?.addEventListener('toggle',revealSource);
    const rewardScope=$('[data-reward-scope]');
    function showScopeWorld(){const field=$('[data-scope-world]');if(field)field.hidden=rewardScope.value!=='world';}
    rewardScope?.addEventListener('change',showScopeWorld);if(rewardScope)showScopeWorld();
    const matrix=$('[data-matrix-view]');
    const matrixRows=matrix?$$('[data-matrix-drops]',matrix).map(row=>({row,drops:JSON.parse(row.dataset.matrixDrops)})):[];
    const matrixFormat=value=>Number(value).toLocaleString(window.ConquerLocale?.locale??'en',{maximumFractionDigits:4});
    const sourceLevel=$('[data-source-level]'),sourceType=$('[data-source-type]'),sourceRule=$('[data-source-rule]');
    const sourceScroll=matrix?.closest('.monster-table-scroll')||$('.source-list');
    const sourceStateKey='admin-reward-view:'+location.pathname+':'+(sourceBrowser?.dataset.sourceContext||'');
    let sourceState={};try{sourceState=JSON.parse(sessionStorage.getItem(sourceStateKey)||'{}')||{};}catch{}
    function saveSourceView(){
        if(!sourceBrowser)return;
        sourceState={...sourceState,search:sourceSearch.value,level:sourceLevel?.value||'',type:sourceType?.value||'',rule:sourceRule?.value||'',
            top:sourceScroll.scrollTop,left:sourceScroll.scrollLeft,view:matrix?.dataset.matrixView,
            items:matrix?$$('[data-compare-item]',matrix).map(s=>s.value):[]};
        try{sessionStorage.setItem(sourceStateKey,JSON.stringify(sourceState));}catch{}
    }
    function updateMatrixColumn(select){
        const column=select.dataset.compareItem,option=select.selectedOptions[0],image=$(`[data-compare-icon="${column}"]`,matrix);
        if(option?.dataset.image)image.src=option.dataset.image;
        select.title=option?.textContent||'';
        $(`[data-compare-name="${column}"]`,matrix).textContent=option?.textContent||rewardText('table_empty');
        matrixRows.forEach(({row,drops})=>{
            const drop=drops[select.value],cell=$(`[data-matrix-cell="${column}"]`,row);
            const link=$('.matrix-drop-link',cell),linked=Boolean(select.value&&(drop||matrix.dataset.canEdit==='1'));
            $('.matrix-quantity',cell).textContent=drop?matrixFormat(drop.quantity)+'×':'—';
            $('.matrix-chance',cell).textContent=drop?matrixFormat(drop.chance)+'%':rewardText(linked?'add_drop':'matrix_no_drop');
            if(linked)link.href=row.dataset.editorUrl+'#drop-item-'+encodeURIComponent(select.value);else link.removeAttribute('href');
            link.setAttribute('aria-label',rewardText(drop?'edit_drop':'add_drop_for',{item:option?.textContent||'',source:row.dataset.sourceLabel}));
            cell.classList.toggle('is-guaranteed',drop?.chance===100);
            cell.classList.toggle('is-off',Boolean(drop&&drop.chance<=0));
            cell.classList.toggle('is-missing',!drop);
        });
    }
    function setMatrixView(view){
        if(!matrix)return;
        matrix.dataset.matrixView=view==='resources'?'resources':'items';
        $$('[data-matrix-switch]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.matrixSwitch===matrix.dataset.matrixView)));
        $('[data-matrix-legend]').textContent=rewardText(view==='resources'?'matrix_resource_hint':'matrix_hint');
        $('.monster-table-scroll').scrollLeft=0;
    }
    if(matrix){
        $$('[data-compare-item]',matrix).forEach((select,index)=>{
            if(sourceState.items?.[index]&&[...select.options].some(o=>o.value===sourceState.items[index]))select.value=sourceState.items[index];
            updateMatrixColumn(select);
            select.addEventListener('change',()=>{updateMatrixColumn(select);saveSourceView();});
        });
        setMatrixView(sourceState.view||'items');
        $$('[data-matrix-switch]').forEach(b=>b.addEventListener('click',()=>{setMatrixView(b.dataset.matrixSwitch);saveSourceView();}));
    }
    function filterSources(changed=false){
        if(!sourceSearch)return;
        const q=sourceSearch.value.toLocaleLowerCase().trim(),level=sourceLevel?.value,type=sourceType?.value,rule=sourceRule?.value;let count=0;
        $$('[data-source-name]').forEach(e=>{
            e.hidden=!e.dataset.sourceName.toLocaleLowerCase().includes(q)
                ||Boolean(level&&e.dataset.sourceLevelValue!==level)
                ||Boolean(type&&e.dataset.sourceTypeValue!==type)
                ||(rule==='custom'&&e.dataset.sourceCustom!=='1')
                ||(rule==='default'&&e.dataset.sourceCustom==='1')
                ||(rule==='empty'&&e.dataset.sourceItems!=='0');
            if(!e.hidden)count++;
        });
        $('[data-source-count]').textContent=rewardText('found',{count});$('[data-source-empty]').hidden=count>0;
        $('[data-source-reset]').hidden=!q&&!level&&!type&&!rule;
        if(changed){sourceScroll.scrollTop=0;saveSourceView();}
    }
    if(sourceSearch){
        if(typeof sourceState.search==='string')sourceSearch.value=sourceState.search;
        for(const [field,value] of [[sourceLevel,sourceState.level],[sourceType,sourceState.type],[sourceRule,sourceState.rule]]){
            if(field&&[...field.options].some(o=>o.value===value))field.value=value;
        }
        filterSources();
        requestAnimationFrame(()=>{sourceScroll.scrollTop=Number(sourceState.top)||0;sourceScroll.scrollLeft=Number(sourceState.left)||0;});
    }
    sourceSearch?.addEventListener('input',()=>filterSources(true));
    $$('[data-source-level],[data-source-type],[data-source-rule]').forEach(e=>e.addEventListener('change',()=>filterSources(true)));
    $('[data-source-reset]')?.addEventListener('click',()=>{
        sourceSearch.value='';if(sourceLevel)sourceLevel.value='';if(sourceType)sourceType.value='';sourceRule.value='';filterSources(true);sourceSearch.focus();
    });
    $('.source-list')?.addEventListener('click',event=>{
        const link=event.target.closest('a[href]');if(!link)return;
        sourceState.source=link.closest('[data-source-key]')?.dataset.sourceKey;
        sourceState.column=link.closest('[data-matrix-cell]')?.dataset.matrixCell;
        saveSourceView();
    });
    window.addEventListener('pagehide',saveSourceView);
    $('.monster-table-back')?.addEventListener('click',event=>{
        event.preventDefault();sourceBrowser.open=true;
        const row=matrixRows.find(({row})=>row.dataset.sourceKey===sourceState.source)?.row;
        const link=row?.querySelector(`[data-matrix-cell="${sourceState.column}"] a[href]`)||row?.querySelector('.matrix-action-col a');
        sourceScroll.scrollIntoView({block:'start'});
        if(link&&!row.hidden&&link.getClientRects().length)link.focus({preventScroll:true});else sourceScroll.focus({preventScroll:true});
        history.replaceState(null,'','#monster-drop-table');
    });
    function filterCatalog(){
        if(!$('[data-catalog-search]'))return;
        const q=$('[data-catalog-search]').value.toLocaleLowerCase('de').trim(),category=$('[data-catalog-category]').value,rarity=$('[data-catalog-rarity]').value;let count=0;
        $$('.catalog-item').forEach(e=>{e.hidden=!e.dataset.search.includes(q)||(category && e.dataset.category!==category)||(rarity && e.dataset.rarity!==rarity);if(!e.hidden)count++;});
        $('[data-catalog-count]').textContent=`${count} Gegenstände`;$('[data-catalog-empty]').hidden=count>0;
    }
    ['[data-catalog-search]','[data-catalog-category]','[data-catalog-rarity]'].forEach(s=>$(s)?.addEventListener(s.includes('search')?'input':'change',filterCatalog));
    const editor=$('[data-reward-editor]'), form=editor?.closest('form');let dirty=false,submitting=false;
    function markDirty(){dirty=true;const s=$('[data-save-status]');if(s){s.textContent='Ungespeicherte Änderungen';s.classList.add('is-dirty');}}
    if(editor?.dataset.hasDraft==='1')markDirty();
    function groupDropRows(container){
        if(!container)return;
        const rows=$$('.drop-row',container);
        for(const category of ['items','speedups','boosts','resources']){
            let section=$(`[data-drop-group="${category}"]`,container);
            if(!section){section=document.createElement('section');section.className='drop-category';section.dataset.dropGroup=category;const heading=document.createElement('h4');heading.textContent=rewardText('group_'+category);const entries=document.createElement('div');entries.dataset.dropGroupEntries='';section.append(heading,entries);container.append(section);}
            const entries=$('[data-drop-group-entries]',section);
            for(const row of rows){const code=$('input[name$="[target]"]',row).value,group=byCode.get(code)?.drop_group||'items';if(group===category&&row.parentElement!==entries)entries.append(row);}
            section.hidden=!$$('.drop-row',section).some(row=>!row.hidden);
        }
    }
    function updateRows(){
        if(!editor)return;
        updateFragmentRows();
        updateRelicRows();
        const rows=$$('.drop-row',editor),primaryRows=rows.filter(row=>!row.hasAttribute('data-chest-bonus')),weighted=['chest','dungeon'].includes(editor.dataset.rewardEditor);
        const total=rows.filter(r=>!r.hasAttribute('data-chest-bonus')).reduce((sum,r)=>sum+Math.max(0,Number($('input[name$="[weight]"]',r)?.value)||0),0);
        const query=$('[data-drop-search]').value.trim().toLocaleLowerCase('de');let visible=0;
        let active=0,guaranteed=0,expected=0;
        const dungeon=editor.dataset.rewardEditor==='dungeon',rolls=editor.dataset.rewardEditor==='chest'?Math.max(0,Number($('[name="config[rolls]"]',editor)?.value)||0):1;
        rows.forEach(row=>{
            const label=$('.drop-probability',row),weight=Math.max(0,Number($('input[name$="[weight]"]',row)?.value)||0);
            const rowWeighted=weighted&&!row.hasAttribute('data-chest-bonus'),rowRolls=row.hasAttribute('data-chest-bonus')?1:rolls;
            const share=rowWeighted?(total?weight/total:0):Math.min(1,Math.max(0,Number($('input[name$="[chance]"]',row)?.value)||0)/100);
            const probability=share*(dungeon?Math.min(100,Math.max(0,Number($('[name="config[item_chance]"]',editor)?.value)||0))/100:1);
            const maximum=Math.max(0,Number(dungeon?$('[name="config[item_quantity]"]',editor)?.value:$('input[name$="[quantity]"]',row)?.value)||0),minimum=$('input[name$="[quantity_min]"]',row)?.value;
            const quantity=minimum?(maximum+Number(minimum))/2:maximum;
            if(probability>0)active++;if(probability===1)guaranteed++;
            expected+=probability*quantity*100*rowRolls;
            label.textContent=rowWeighted?rewardNumber(share*100)+' %':rewardText(probability===1?'certain':probability===0?'off':'random');
            let outcome=$('.drop-outcome',row);
            if(!outcome){outcome=document.createElement('small');outcome.className='drop-outcome';row.append(outcome);}
            outcome.textContent=rewardText(dungeon?'row_dungeon':'row_expected',{chance:rewardNumber(probability*100),count:rewardNumber(probability*quantity*100*rowRolls)});
            const code=$('input[type="hidden"]',row).value;
            row.hidden=!`${byCode.get(code)?.name||''} ${code}`.toLocaleLowerCase('de').includes(query);if(!row.hidden&&!row.hasAttribute('data-chest-bonus'))visible++;
        });
        groupDropRows($('[data-drop-rows]',editor));groupDropRows($('[data-bonus-rows]',editor));
        $('[data-drop-active]').textContent=String(active);
        $('[data-drop-guaranteed]').textContent=String(guaranteed);
        $('[data-drop-expected]').textContent=rewardNumber(expected);
        $('[data-drop-count]').textContent=query?`${visible} / ${primaryRows.length} Einträge`:`${primaryRows.length} Einträge`;$('[data-drop-empty]').hidden=primaryRows.length>0;
        $('[data-drop-no-match]').hidden=visible>0||!primaryRows.length;
        $('[data-add-drop]').disabled=primaryRows.length>=200||form.querySelector('fieldset').disabled;
        const bonusButton=$('[data-add-bonus]');if(bonusButton)bonusButton.disabled=rows.length-primaryRows.length>=50||form.querySelector('fieldset').disabled;
    }
    function appendDrop(){
        if(!editor||form.querySelector('fieldset').disabled||$$('.drop-row',$('[data-drop-rows]',editor)).length>=200)return null;
        const indexes=$$('.drop-row input[type="hidden"]',editor).map(i=>Number(i.name.match(/\[rows\]\[(\d+)\]/)?.[1])||0);
        const index=Math.max(-1,...indexes)+1;
        $('[data-drop-search]').value='';
        $('[data-drop-rows]').insertAdjacentHTML('beforeend',$('#drop-row-template').innerHTML.replaceAll('__ROW__',String(index)));
        return $$('.drop-row',$('[data-drop-rows]',editor)).at(-1);
    }
    $('[data-add-drop]')?.addEventListener('click',()=>{
        const row=appendDrop();if(!row)return;
        markDirty();updateRows();$('[data-item-picker]',row).click();
    });
    $('[data-add-bonus]')?.addEventListener('click',()=>{
        if(form.querySelector('fieldset').disabled)return;
        const container=$('[data-bonus-rows]',editor),rows=$$('.drop-row',container);if(rows.length>=50)return;
        const index=Math.max(-1,...rows.map(row=>Number($('input[name$="[target]"]',row).name.match(/\[bonus_rows\]\[(\d+)\]/)?.[1])||0))+1;
        container.insertAdjacentHTML('beforeend',$('#bonus-row-template').innerHTML.replaceAll('__ROW__',String(index)));
        const row=$$('.drop-row',container).at(-1);$('input[name$="[chance]"]',row).value='0';markDirty();updateRows();$('[data-item-picker]',row).click();
    });
    function updateFragmentRows(){
        const container=$('[data-fragment-rows]',editor);if(!container)return;
        const rows=$$('.fragment-row',container);let active=0,expected=0;
        rows.forEach(row=>{
            const select=$('select',row);
            if(window.ConquerRelicPresentation&&!select.dataset.relicNames){
                [...select.options].forEach(option=>{if(option.value.startsWith('treasure:'))option.textContent=window.ConquerRelicPresentation.name({treasure_code:Number(option.value.slice(9))});});
                select.dataset.relicNames='1';
            }
            const chance=Math.min(100,Math.max(0,Number($('input[name$="[chance]"]',row).value)||0));
            const maximum=Math.max(0,Number($('input[name$="[quantity]"]',row).value)||0),minimum=$('input[name$="[quantity_min]"]',row)?.value;
            const quantity=minimum?(maximum+Number(minimum))/2:maximum;
            if($('select',row).value&&chance>0)active++;
            const count=$('select',row).value?quantity*chance:0;expected+=count;
            $('.fragment-outcome',row).textContent=rewardText('fragment_outcome',{chance:matrixFormat(chance),count:matrixFormat(count)});
        });
        $('[data-fragment-empty]',editor).hidden=rows.length>0;
        $('[data-fragment-summary]',editor).textContent=rewardText('fragment_summary',{active,count:matrixFormat(expected)});
        $('[data-add-fragment]',editor).disabled=rows.length>=100||form.querySelector('fieldset').disabled;
    }
    $('[data-add-fragment]')?.addEventListener('click',()=>{
        if(form.querySelector('fieldset').disabled)return;
        const rows=$$('.fragment-row',editor);if(rows.length>=100)return;
        const index=Math.max(-1,...rows.map(row=>Number($('select',row).name.match(/\[fragment_rows\]\[(\d+)\]/)?.[1])||0))+1;
        $('[data-fragment-rows]',editor).insertAdjacentHTML('beforeend',$('#fragment-row-template').innerHTML.replaceAll('__ROW__',String(index)));
        markDirty();updateFragmentRows();$$('.fragment-row select',editor).at(-1).focus();
    });
    function updateRelicRows(){
        const container=$('[data-relic-rows]',editor);if(!container)return;
        const rows=$$('.relic-row',container);let active=0,expected=0;
        rows.forEach(row=>{
            const select=$('select',row);
            if(window.ConquerRelicPresentation&&!select.dataset.relicNames){
                [...select.options].forEach(option=>{if(option.value.startsWith('relic:'))option.textContent=window.ConquerRelicPresentation.name({treasure_code:Number(option.value.slice(6))});});
                select.dataset.relicNames='1';
            }
            const chance=Math.min(100,Math.max(0,Number($('input[name$="[chance]"]',row).value)||0));
            const quantity=Math.max(0,Number($('input[name$="[quantity]"]',row).value)||0);
            if(select.value&&chance>0)active++;
            const count=select.value?quantity*chance:0;expected+=count;
            $('.relic-outcome',row).textContent=rewardText('relic_outcome',{chance:matrixFormat(chance),count:matrixFormat(count)});
        });
        $('[data-relic-empty]',editor).hidden=rows.length>0;
        $('[data-relic-summary]',editor).textContent=rewardText('relic_summary',{active,count:matrixFormat(expected)});
        $('[data-add-relic]',editor).disabled=rows.length>=100||form.querySelector('fieldset').disabled;
    }
    $('[data-add-relic]')?.addEventListener('click',()=>{
        if(form.querySelector('fieldset').disabled)return;
        const rows=$$('.relic-row',editor);if(rows.length>=100)return;
        const index=Math.max(-1,...rows.map(row=>Number($('select',row).name.match(/\[relic_rows\]\[(\d+)\]/)?.[1])||0))+1;
        $('[data-relic-rows]',editor).insertAdjacentHTML('beforeend',$('#relic-row-template').innerHTML.replaceAll('__ROW__',String(index)));
        markDirty();updateRelicRows();$$('.relic-row select',editor).at(-1).focus();
    });
    function focusLinkedDrop(){
        if(!matrix||!editor||$('#reward-editor').hidden)return;
        const code=location.hash.match(/^#drop-item-(\d+)$/)?.[1],item=byCode.get(code);
        if(!item)return;
        let row=$$('.drop-row',editor).find(r=>$('input[name$="[target]"]',r).value===code),added=false;
        const note=$('[data-drop-focus-note]');
        if(!row){
            row=appendDrop();
            if(!row){
                if(!form.querySelector('fieldset').disabled){note.textContent=rewardText('drop_limit');note.hidden=false;note.scrollIntoView({block:'center'});}
                return;
            }
            $('input[name$="[chance]"]',row).value='0';
            chooseItem($('.item-select',row),code);added=true;
        }
        $('[data-drop-search]').value='';updateRows();
        $$('.drop-row.is-focused',editor).forEach(r=>r.classList.remove('is-focused'));
        row.classList.add('is-focused');row.id='drop-item-'+code;
        const source=matrixRows.find(({row})=>row.dataset.sourceKey===$('[name="source_key"]',form).value)?.row.dataset.sourceLabel||$('.reward-hero h2').textContent;
        note.textContent=rewardText(added?'drop_added_note':'drop_focus_note',{item:window.ConquerLocale.text(item.name),source});note.hidden=false;
        requestAnimationFrame(()=>{
            row.scrollIntoView({block:'center'});
            const input=$('input[name$="[quantity]"]',row);
            if(input&&!input.matches(':disabled'))input.focus({preventScroll:true});else{row.tabIndex=-1;row.focus({preventScroll:true});}
        });
    }
    $('[data-drop-search]')?.addEventListener('input',updateRows);
    editor?.addEventListener('click',e=>{const relic=e.target.closest('[data-remove-relic]');if(relic){relic.closest('.relic-row').remove();markDirty();updateRelicRows();}const fragment=e.target.closest('[data-remove-fragment]');if(fragment){fragment.closest('.fragment-row').remove();markDirty();updateFragmentRows();}const remove=e.target.closest('.remove-drop');if(remove){remove.closest('.drop-row').remove();markDirty();updateRows();}});
    form?.addEventListener('input',e=>{if(e.target.name)markDirty();updateRows();});form?.addEventListener('change',e=>{if(e.target.name)markDirty();updateRows();});
    form?.addEventListener('invalid',e=>{for(let detail=e.target.closest('details');detail;detail=detail.parentElement?.closest('details'))detail.open=true;const row=e.target.closest('.drop-row');if(row?.hidden){$('[data-drop-search]').value='';updateRows();row.scrollIntoView({block:'center'});}},true);
    function previewTreasure(){const select=$('[data-treasure-select]');if(!select)return;const option=select.selectedOptions[0];if(option){$('.treasure-preview img').src=option.dataset.image;$('[data-treasure-name]').textContent=option.textContent;}}
    $$('[data-fragment-relic-code]').forEach(node=>{if(window.ConquerRelicPresentation)node.textContent=window.ConquerRelicPresentation.name({treasure_code:Number(node.dataset.fragmentRelicCode)});});
    $('[data-treasure-select]')?.addEventListener('change',previewTreasure);previewTreasure();updateRows();
    focusLinkedDrop();window.addEventListener('hashchange',focusLinkedDrop);
    window.addEventListener('beforeunload',e=>{if(dirty&&!submitting){e.preventDefault();e.returnValue='';}});
    $$('.admin-form').forEach(f=>f.addEventListener('submit',e=>{
        if(f===form){
            const missing=$$('input[name$="[target]"]',form).find(i=>!i.value);
            if(missing){e.preventDefault();missing.closest('.item-select').querySelector('button').click();return;}
        }
        submitting=true;const button=$('button[type="submit"]',f);if(button){button.disabled=true;button.textContent=f.getAttribute('action')?.endsWith('/world-delete')?(window.ConquerLocale?.t('admin.world_delete.deleting')||'Deleting world…'):'Wird gespeichert …';}
    }));
    window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});
})();
