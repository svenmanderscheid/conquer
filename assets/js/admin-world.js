/* Section navigation only; the existing authenticated forms own every write. */
(()=>{
    'use strict';
    const workspace=document.querySelector('[data-world-workspace]');
    if(!workspace)return;
    const panels=[...workspace.querySelectorAll('[data-world-panel]')];
    const links=[...workspace.querySelectorAll('[data-world-link]')];
    const form=workspace.querySelector('form[action$="/world-save"]');
    const status=workspace.querySelector('[data-world-dirty-status]');
    const sections=new Set(links.map(link=>link.dataset.worldLink));
    const settingsSections=new Set(['settings','spawns','territories']);
    const storageKey='admin-world-section:'+location.pathname+':'+workspace.dataset.worldId;
    let current='overview',submitting=false;
    const values=()=>JSON.stringify([...new FormData(form)].filter(([name])=>!['csrf_token','operation_id','reason'].includes(name)));
    const initial=values();
    const dirty=()=>values()!==initial;
    function updateStatus(){
        const key='admin.world_workspace.'+(dirty()?'unsaved':'no_changes');
        const label=status.querySelector('[data-i18n]');
        label.dataset.i18n=key;
        if(window.ConquerLocale)label.textContent=window.ConquerLocale.t(key);
        status.classList.toggle('is-dirty',dirty());
    }
    function sectionForHash(){
        if(location.hash==='#world-delete')return 'management';
        if(location.hash==='#extra-event-settings')return 'events';
        const section=location.hash.replace(/^#world-/,'');
        return sections.has(section)?section:null;
    }
    function show(section){
        current=sections.has(section)?section:'overview';
        for(const panel of panels)panel.hidden=panel.dataset.worldPanel!==current;
        form.hidden=!settingsSections.has(current);
        for(const link of links){
            if(link.dataset.worldLink===current)link.setAttribute('aria-current','location');
            else link.removeAttribute('aria-current');
        }
        try{sessionStorage.setItem(storageKey,current);}catch{}
    }
    function revealHash(){
        show(sectionForHash()||'overview');
        const target=document.getElementById(location.hash.slice(1));
        if(target){
            target.scrollIntoView({block:'start',behavior:'instant'});
            const heading=target.querySelector('h2');
            if(heading){heading.tabIndex=-1;heading.focus({preventScroll:true});}
        }
    }
    let remembered=null;
    try{remembered=sessionStorage.getItem(storageKey);}catch{}
    show(sectionForHash()||(new URLSearchParams(location.search).has('event_id')?'events':remembered)||'overview');
    if(location.hash)revealHash();
    window.addEventListener('hashchange',revealHash);
    // Native fragment links still work without JavaScript; clicking the current
    // link also returns to its section after scrolling through a long editor.
    workspace.addEventListener('click',event=>{
        const link=event.target.closest('a[href^="#world-"]');
        if(link&&link.hash===location.hash)revealHash();
    });
    form.addEventListener('input',updateStatus);
    form.addEventListener('change',updateStatus);
    if(window.ConquerLocale?.ready)window.ConquerLocale.ready.then(updateStatus);
    // Validate the complete payload, including fields in other sections. Reveal
    // the first invalid field before requesting native focus/validation feedback.
    form.noValidate=true;
    form.addEventListener('submit',event=>{
        if(submitting){event.preventDefault();event.stopImmediatePropagation();return;}
        const invalid=[...form.elements].find(input=>input.willValidate&&!input.validity.valid);
        if(invalid){
            event.preventDefault();
            event.stopImmediatePropagation();
            const panel=invalid.closest('[data-world-panel]');
            if(panel){
                show(panel.dataset.worldPanel);
                history.replaceState(null,'','#world-'+panel.dataset.worldPanel);
            }
            for(let ancestor=invalid.parentElement;ancestor&&ancestor!==form;ancestor=ancestor.parentElement){
                if(ancestor.tagName==='DETAILS')ancestor.open=true;
            }
            invalid.scrollIntoView({block:'center',behavior:'instant'});
            invalid.reportValidity();
            return;
        }
        submitting=true;
    },true);
    workspace.addEventListener('submit',event=>{
        if(event.target===form||!dirty())return;
        // Ask before the shared backoffice handler disables a different form's
        // submit button, so cancelling keeps that form usable and the draft intact.
        if(!window.confirm(window.ConquerLocale.t('admin.world_workspace.discard_changes'))){
            event.preventDefault();event.stopImmediatePropagation();return;
        }
        submitting=true;
    },true);
    window.addEventListener('beforeunload',event=>{
        if(!submitting&&dirty()){event.preventDefault();event.returnValue='';}
    });
})();
