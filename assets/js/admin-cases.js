(()=>{
    'use strict';
    const root=document.querySelector('.case-workspace');if(!root)return;
    const legacy=location.hash.match(/^#meldung-(\d+)$/);
    if(legacy){const url=new URL(location.href);url.searchParams.set('source','bug');url.searchParams.set('case_id',legacy[1]);url.hash='case-detail';location.replace(url.href);return;}
    let dirty=false,submitting=false;
    root.addEventListener('input',event=>{if(event.target.closest('[data-case-form]'))dirty=true;});
    root.addEventListener('click',event=>{const claim=event.target.closest('[data-case-claim]');if(!claim)return;const select=claim.closest('form').querySelector('[name="assigned_to"]');select.value=claim.dataset.caseClaim;dirty=true;select.focus();});
    root.addEventListener('submit',event=>{if(event.target.matches('[data-case-form]')&&event.target.checkValidity())submitting=true;});
    window.addEventListener('beforeunload',event=>{if(dirty&&!submitting){event.preventDefault();event.returnValue='';}});
})();
