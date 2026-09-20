/* Shared illustrated item picker and backoffice interactions. No remote dependencies. */
(() => {
    'use strict';
    const $ = (s, root=document) => root.querySelector(s);
    const $$ = (s, root=document) => [...root.querySelectorAll(s)];
    const catalog = JSON.parse($('#admin-item-catalog')?.textContent || '[]');
    const byCode = new Map(catalog.map(i=>[String(i.code),i]));
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
        const choices=catalog.filter(i=>(activePicker.dataset.fragments==='1'||i.category!=='fragments')&&(!category||i.category===category)&&`${i.name} ${i.code} ${i.description}`.toLocaleLowerCase('de').includes(query));
        $('.picker-count').textContent=`${choices.length} Gegenstände gefunden`;
        $('.picker-results').innerHTML=(activePicker.dataset.optional==='1'?'<button type="button" class="secondary picker-result" data-pick-item="0">Kein Gegenstand</button>':'')+choices.map(i=>`<button type="button" class="secondary picker-result" data-pick-item="${esc(i.code)}"><span class="item-tile rarity-${esc(i.rarity)}"><img src="${esc(i.image)}" alt="" loading="lazy"></span><span><strong>${esc(i.name)}</strong><small>${esc(i.category_name)} · Nr. ${esc(i.code)}</small></span></button>`).join('')+(choices.length?'':'<p class="empty">Keine Treffer. Versuche einen anderen Namen oder eine andere Kategorie.</p>');
    }
    document.addEventListener('click',event=>{
        const picker=event.target.closest('[data-item-picker]');
        if(picker){
            activePicker=picker.closest('.item-select');$('#item-picker-search').value='';$('#item-picker-category').value='';
            pickerResults();dialog.showModal();$('#item-picker-search').focus();return;
        }
        const selected=event.target.closest('[data-pick-item]');
        if(selected && activePicker){
            const value=selected.dataset.pickItem,item=byCode.get(value),button=$('[data-item-picker]',activePicker),input=$('input',activePicker);
            input.value=value;
            $('strong',button).textContent=item?.name||'Kein Gegenstand';$('small',button).textContent=`${item?.category_name||'Optional'} · Auswählen`;
            if(item)$('img',button).src=item.image;
            button.setAttribute('aria-label',item?'Gegenstand ändern: '+item.name:'Gegenstand auswählen');
            input.dispatchEvent(new Event('change',{bubbles:true}));dialog.close();button.focus();
        }
    });
    $('[data-picker-close]')?.addEventListener('click',()=>dialog.close());
    dialog?.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();dialog.close();}});
    $('#item-picker-search')?.addEventListener('input',pickerResults);
    $('#item-picker-category')?.addEventListener('change',pickerResults);
    const sourceSearch=$('[data-source-search]');
    const sourceBrowser=$('[data-source-browser]');
    const compactSources=window.matchMedia('(max-width: 1100px)');
    function sizeSourceBrowser(){if(sourceBrowser)sourceBrowser.open=!compactSources.matches;}
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
    sourceSearch?.addEventListener('input',()=>{
        const q=sourceSearch.value.toLocaleLowerCase('de').trim();let count=0;
        $$('[data-source-name]').forEach(e=>{e.hidden=!e.dataset.sourceName.includes(q);if(!e.hidden)count++;});
        $('[data-source-count]').textContent=`${count} Quellen gefunden`;$('[data-source-empty]').hidden=count>0;
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
    function updateRows(){
        if(!editor)return;
        const rows=$$('.drop-row',editor),weighted=['chest','dungeon'].includes(editor.dataset.rewardEditor);
        const total=rows.reduce((sum,r)=>sum+Math.max(0,Number($('input[name$="[weight]"]',r)?.value)||0),0);
        const query=$('[data-drop-search]').value.trim().toLocaleLowerCase('de');let visible=0;
        rows.forEach(row=>{const label=$('.drop-probability',row);if(weighted){const value=Number($('input[name$="[weight]"]',row)?.value)||0;label.textContent=(total?value/total*100:0).toLocaleString('de-DE',{maximumFractionDigits:2})+' %';}else{const chance=Number($('input[name$="[chance]"]',row)?.value);label.textContent=chance===100?'Garantiert':chance===0?'Aus':'Zufällig';}const code=$('input[type="hidden"]',row).value;row.hidden=!`${byCode.get(code)?.name||''} ${code}`.toLocaleLowerCase('de').includes(query);if(!row.hidden)visible++;});
        $('[data-drop-count]').textContent=query?`${visible} / ${rows.length} Einträge`:`${rows.length} Einträge`;$('[data-drop-empty]').hidden=rows.length>0;
        $('[data-drop-no-match]').hidden=visible>0||!rows.length;
        $('[data-add-drop]').disabled=rows.length>=200||form.querySelector('fieldset').disabled;
    }
    $('[data-add-drop]')?.addEventListener('click',()=>{
        if($$('.drop-row',editor).length>=200)return;
        const indexes=$$('.drop-row input[type="hidden"]',editor).map(i=>Number(i.name.match(/\[rows\]\[(\d+)\]/)?.[1])||0);
        const index=Math.max(-1,...indexes)+1;
        $('[data-drop-search]').value='';
        $('[data-drop-rows]').insertAdjacentHTML('beforeend',$('#drop-row-template').innerHTML.replaceAll('__ROW__',String(index)));
        markDirty();updateRows();$$('.drop-row [data-item-picker]',editor).at(-1).click();
    });
    $('[data-drop-search]')?.addEventListener('input',updateRows);
    editor?.addEventListener('click',e=>{const remove=e.target.closest('.remove-drop');if(remove){remove.closest('.drop-row').remove();markDirty();updateRows();}});
    form?.addEventListener('input',e=>{if(e.target.name)markDirty();updateRows();});form?.addEventListener('change',e=>{if(e.target.name)markDirty();updateRows();});
    form?.addEventListener('invalid',e=>{for(let detail=e.target.closest('details');detail;detail=detail.parentElement?.closest('details'))detail.open=true;const row=e.target.closest('.drop-row');if(row?.hidden){$('[data-drop-search]').value='';updateRows();row.scrollIntoView({block:'center'});}},true);
    function previewTreasure(){const select=$('[data-treasure-select]');if(!select)return;const option=select.selectedOptions[0];if(option){$('.treasure-preview img').src=option.dataset.image;$('[data-treasure-name]').textContent=option.textContent;}}
    $('[data-treasure-select]')?.addEventListener('change',previewTreasure);previewTreasure();updateRows();
    window.addEventListener('beforeunload',e=>{if(dirty&&!submitting){e.preventDefault();e.returnValue='';}});
    $$('.admin-form').forEach(f=>f.addEventListener('submit',e=>{
        if(f===form){
            const missing=$$('input[name$="[target]"]',form).find(i=>!i.value);
            if(missing){e.preventDefault();missing.closest('.item-select').querySelector('button').click();return;}
        }
        submitting=true;const button=$('button[type="submit"]',f);if(button){button.disabled=true;button.textContent='Wird gespeichert …';}
    }));
    window.addEventListener('pageshow',e=>{if(e.persisted)location.reload();});
})();
