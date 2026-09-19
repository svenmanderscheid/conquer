/* Explicit authored-text localization. Player prose, account values and editable fields are never translated. */
(()=>{
    'use strict';
    const script=document.currentScript;
    const inferred=script?.src?new URL(script.src).pathname.replace(/\/assets\/js\/localization\.js$/,''):'';
    const base=typeof window.CONQUER_BASE==='string'?window.CONQUER_BASE:inferred;
    const supported={de:'Deutsch',fr:'Français',lb:'Lëtzebuergesch'};
    const catalogs=window.CONQUER_I18N?.catalogs||{};
    const normalize=value=>{const code=typeof value==='string'?value.toLowerCase().replace('_','-').split('-')[0]:'';return code==='lu'?'lb':Object.hasOwn(supported,code)?code:'de';};
    let locale=normalize(window.CONQUER_I18N?.locale),observer=null,scheduled=false,installPrompt=null;
    try{const saved=localStorage.getItem('conquer.locale');if(saved)locale=normalize(saved);}catch{}
    const sourceNodes=new WeakMap(),sourceAttributes=new WeakMap(),dictionary=new Map();
    for(const [key,value]of Object.entries(catalogs.de||{}))if(typeof value==='string')dictionary.set(value.replace(/\s+/g,' ').trim(),key);
    const protectedContent='[data-user-content],[translate="no"],[data-i18n-ignore],script,style,code,pre,input,textarea,[contenteditable="true"],.community-message,.community-letter,.community-mail-card strong,.community-mail-card span,.community-gift-message,.community-card>h3,.community-card>strong,.profile-banner,.profile-bio,.profile-head,.member-identity,#player-hud-name,.player-name,.alliance-name,.world-player-name,.ranking-player,.ranking-name,.sidebar-bottom small,td';
    const authoredSelectors=[
        '[data-i18n]','[data-i18n-attrs]','[data-i18n-scope]','[data-locale-controls]',
        '#navigation','.game-quick-actions','.subtabs','.subtab','#page-title','.section-heading h2',
        '.button[data-action]','button[data-action^="community-"]:not(.community-mail-card)',
        'button[data-action^="progress-"]','button[data-action^="defense-"]',
        '.community-heading','.community-tabs','.community-body>p','.community-empty','.community-form>small',
        '.community-columns>section>h3','.community-body>h3','.community-mail-card small',
        '.community-body>section>h3','.progression-content>p','.progression-content>h3',
        'form label','form>button','form button[type="submit"]',
        '.sidebar nav a','.nav-caption','.world-picker label','th',
        '.auth-switch button','#auth-submit','.welcome-form .form-note','.welcome-form .pill','.welcome-form a[href*="recover"]',
        '.skip-link','#save-state','.loading','.hud-objective small',
        '.world-selector>p','.world-selector button','.world-selector article>strong'
    ].join(',');

    function t(key,parameters={}){
        let value=catalogs[locale]?.[key]??catalogs.de?.[key]??key;
        for(const [name,replacement]of Object.entries(parameters))if(['string','number','boolean'].includes(typeof replacement))value=value.split('{'+name+'}').join(String(replacement));
        return value;
    }
    function textTranslation(value){const trimmed=value.replace(/\s+/g,' ').trim(),key=dictionary.get(trimmed);if(!key)return value;return value.match(/^\s*/)[0]+t(key)+value.match(/\s*$/)[0];}
    function guarded(element){return !element||Boolean(element.closest(protectedContent));}
    function translateText(node){
        if(guarded(node.parentElement)||node.parentElement.closest('[data-i18n]'))return;
        let record=sourceNodes.get(node);if(!record||node.nodeValue!==record.last)record={source:node.nodeValue,last:node.nodeValue};
        const next=textTranslation(record.source);if(next!==node.nodeValue)node.nodeValue=next;record.last=next;sourceNodes.set(node,record);
    }
    function translateAttribute(element,name,key=null){
        const isField=element.matches('input,textarea');if((isField?element.closest('[data-user-content],[translate="no"],[data-i18n-ignore]'):guarded(element))||!['title','aria-label','placeholder','alt'].includes(name))return;
        if(!element.hasAttribute(name)&&!key)return;const map=sourceAttributes.get(element)||{};let record=map[name],value=element.getAttribute(name)||'';
        if(!record||record.last!==value)record={source:value,last:value};if(key)record.key=key;const next=record.key?t(record.key):textTranslation(record.source);
        if(value!==next)element.setAttribute(name,next);record.last=next;map[name]=record;sourceAttributes.set(element,map);
    }
    function apply(root=document){
        const elements=[];if(root.nodeType===1&&root.matches(authoredSelectors))elements.push(root);
        if(root.querySelectorAll)elements.push(...root.querySelectorAll(authoredSelectors));
        const seen=new Set();for(const element of elements){
            if(guarded(element)&&!element.matches('input[data-i18n-attrs],textarea[data-i18n-attrs]'))continue;
            if(element.dataset.i18n){let params={};try{params=JSON.parse(element.dataset.i18nParams||'{}');}catch{}const value=t(element.dataset.i18n,params);if(element.textContent!==value)element.textContent=value;continue;}
            if(element.dataset.i18nAttrs){for(const pair of element.dataset.i18nAttrs.split(';')){const [attr,key]=pair.split(':');if(attr&&key)translateAttribute(element,attr.trim(),key.trim());}}
            for(const attr of ['title','aria-label','placeholder','alt'])translateAttribute(element,attr);
            const walker=document.createTreeWalker(element,NodeFilter.SHOW_TEXT);let node;while(node=walker.nextNode()){if(seen.has(node))continue;seen.add(node);translateText(node);}
        }
        // Only authored placeholders and accessibility labels are dictionary-matched; input values are untouched.
        root.querySelectorAll?.('[placeholder],[aria-label],[title],[data-i18n-attrs]').forEach(element=>{
            if(element.matches('input,textarea')){
                if(element.closest('[data-user-content],[translate="no"],[data-i18n-ignore]'))return;
                const value=element.getAttribute('placeholder');if(value!==null){const saved=sourceAttributes.get(element)||{},record=saved.placeholder&&saved.placeholder.last===value?saved.placeholder:{source:value,last:value};const next=textTranslation(record.source);if(next!==value)element.setAttribute('placeholder',next);record.last=next;saved.placeholder=record;sourceAttributes.set(element,saved);}
            }else for(const attr of ['title','aria-label'])translateAttribute(element,attr);
        });
        document.documentElement.lang=locale;
        document.querySelectorAll('[data-locale-select]').forEach(select=>{select.value=locale;});
        autoMount();
    }
    function setLocale(value){
        locale=normalize(value);try{localStorage.setItem('conquer.locale',locale);}catch{}
        document.cookie='conquer_locale='+encodeURIComponent(locale)+'; Path='+(base||'')+'/; Max-Age=31536000; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');
        apply();document.dispatchEvent(new CustomEvent('conquer:locale',{detail:{locale}}));return locale;
    }
    function mount(container,{compact=false,install=true}={}){
        if(typeof container==='string')container=document.querySelector(container);if(!container||container.querySelector('.locale-tools'))return;
        const tools=document.createElement('section');tools.className='locale-tools'+(compact?' compact':'');tools.dataset.i18nScope='';
        const label=document.createElement('label'),caption=document.createElement('span');caption.dataset.i18n='locale.label';caption.textContent=t('locale.label');
        const select=document.createElement('select');select.dataset.localeSelect='';select.dataset.i18nAttrs='aria-label:locale.label';select.setAttribute('aria-label',t('locale.label'));
        for(const [code,name]of Object.entries(supported)){const option=document.createElement('option');option.value=code;option.textContent=(code==='lb'?'LU':code.toUpperCase())+' · '+name;option.setAttribute('translate','no');select.append(option);}select.value=locale;select.addEventListener('change',()=>setLocale(select.value));label.append(caption,select);tools.append(label);
        if(!compact){for(const key of ['locale.note','locale.coverage']){const p=document.createElement('small');p.dataset.i18n=key;p.textContent=t(key);tools.append(p);}}
        if(install){const row=document.createElement('div');row.className='locale-install';const button=document.createElement('button');button.type='button';button.className='button secondary';button.dataset.pwaInstall='';button.dataset.i18n='pwa.install';button.textContent=t('pwa.install');button.addEventListener('click',async()=>{const message=await installApp();const note=row.querySelector('[role="status"]');if(note){note.dataset.i18n=message;note.textContent=t(message);}});const note=document.createElement('small');note.setAttribute('role','status');note.dataset.i18n='pwa.offline';note.textContent=t('pwa.offline');row.append(button,note);tools.append(row);}
        container.prepend(tools);
    }
    function autoMount(){
        document.querySelectorAll('[data-locale-controls]').forEach(el=>mount(el,{compact:el.dataset.localeCompact==='true',install:el.dataset.localeInstall!=='false'}));
        const panel=document.querySelector('#panel-dialog');if(panel&&['account','settings'].includes(panel.dataset.panel)){const content=document.querySelector('#content'),scroller=content?.querySelector('.progression-content');if(scroller)mount(scroller,{install:true});else if(content?.querySelector('form[data-form="settings"]'))mount(content,{install:true});}
    }
    function observe(){if(observer)return;observer=new MutationObserver(()=>{if(scheduled)return;scheduled=true;queueMicrotask(()=>{scheduled=false;apply();});});observer.observe(document.body,{subtree:true,childList:true,characterData:true});apply();}
    async function register(){
        if(!('serviceWorker'in navigator)||!window.isSecureContext)return null;
        try{return await navigator.serviceWorker.register(base+'/service-worker.js',{scope:base+'/',updateViaCache:'none'});}catch{return null;}
    }
    async function installApp(){
        if(matchMedia('(display-mode: standalone)').matches||navigator.standalone)return'pwa.installed';
        if(!window.isSecureContext)return'pwa.secure';
        if(!installPrompt)return'pwa.hint';const prompt=installPrompt;installPrompt=null;await prompt.prompt();const result=await prompt.userChoice;return result.outcome==='accepted'?'pwa.installed':'pwa.hint';
    }
    window.addEventListener('beforeinstallprompt',event=>{event.preventDefault();installPrompt=event;});
    window.addEventListener('appinstalled',()=>{installPrompt=null;document.dispatchEvent(new CustomEvent('conquer:installed'));});
    window.addEventListener('storage',event=>{if(event.key==='conquer.locale'){locale=normalize(event.newValue);apply();}});
    window.ConquerLocale={t,apply,mount,setLocale,normalize,get locale(){return locale;},get supported(){return{...supported};}};
    window.ConquerPWA={register,install:installApp};
    const start=()=>{observe();register();};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
