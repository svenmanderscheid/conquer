/* Explicit authored-text localization. Player prose, account values and editable fields are never translated. */
(()=>{
    'use strict';
    const script=document.currentScript;
    const inferred=script?.src?new URL(script.src).pathname.replace(/\/assets\/js\/localization\.js$/,''):'';
    const base=typeof window.CONQUER_BASE==='string'?window.CONQUER_BASE:inferred;
    const supported={en:'English',de:'Deutsch',fr:'Français'};
    const bootstrap=window.CONQUER_I18N||{},catalogs=bootstrap.catalogs||{},sourceCatalogs=bootstrap.sourceCatalogs||{},catalogLoads=new Map();
    const normalize=value=>{const code=typeof value==='string'?value.trim().toLowerCase().replace('_','-').split('-')[0]:'';return Object.hasOwn(supported,code)?code:'en';};
    let locale=normalize(window.CONQUER_I18N?.locale),observer=null,scheduled=false,installPrompt=null,savedLocale='';
    try{savedLocale=localStorage.getItem('conquer.locale')||'';if(savedLocale)locale=normalize(savedLocale);}catch{}
    // English is the primary language; only an explicit preference overrides it.
    const sourceNodes=new WeakMap(),sourceAttributes=new WeakMap(),dictionary=new Map(),templates=[],translationCache=new Map();
    // Recognise authored English catalogues and server-rendered translations too.
    // Parameters remain opaque: a player named "Forschung" must stay Forschung.
    function rebuildDictionary(){
    dictionary.clear();templates.length=0;translationCache.clear();
    for(const sourceLocale of ['de','en','fr'])for(const [key,value]of Object.entries({...sourceCatalogs[sourceLocale],...catalogs[sourceLocale]}))if(typeof value==='string'){
        const normalized=value.replace(/\u00ad/g,'').replace(/\s+/g,' ').trim();
        if(!dictionary.has(normalized))dictionary.set(normalized,key);
        if(!dictionary.has(normalized.toLocaleUpperCase()))dictionary.set(normalized.toLocaleUpperCase(),key);
        const names=[...normalized.matchAll(/\{([a-zA-Z0-9_]+)\}/g)].map(match=>match[1]);
        const literals=normalized.split(/\{[a-zA-Z0-9_]+\}/g),anchor=literals.slice().sort((a,b)=>b.length-a.length)[0];
        // Never turn a bare placeholder into a pattern matching arbitrary user text.
        if(names.length&&/[\p{L}]{2}/u.test(anchor)){
            const pattern=literals.map(s=>s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')).join('(.+?)');
            templates.push({key,names,anchor,weight:literals.join('').length,pattern:new RegExp('^'+pattern+'$','u')});
        }
    }
    templates.sort((a,b)=>b.weight-a.weight);
    }
    rebuildDictionary();
    async function fetchCatalog(url){
        const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),8000);
        try{const response=await fetch(url,{credentials:'same-origin',cache:'force-cache',signal:controller.signal});if(!response.ok)throw new Error('Language catalogue unavailable');return await response.json();}finally{clearTimeout(timeout);}
    }
    function loadCatalog(language){
        if(catalogs[language]||!bootstrap.catalogUrls?.[language])return Promise.resolve();
        if(!catalogLoads.has(language))catalogLoads.set(language,fetchCatalog(bootstrap.catalogUrls[language]).then(catalog=>{if(!catalog||typeof catalog!=='object'||Array.isArray(catalog)||Object.values(catalog).some(value=>typeof value!=='string'))throw new Error('Invalid language catalogue');catalogs[language]=catalog;rebuildDictionary();}).catch(()=>{catalogLoads.delete(language);}));
        return catalogLoads.get(language);
    }
    async function prepareLocale(language){
        await Promise.all([loadCatalog('en'),loadCatalog(language)]);
        if(!sourceCatalogs.de&&!catalogs.de&&bootstrap.sourceUrl){
            try{const source=await fetchCatalog(bootstrap.sourceUrl);if(source&&typeof source==='object'&&!Array.isArray(source)&&Object.values(source).every(value=>typeof value==='string')){sourceCatalogs.de=source;rebuildDictionary();}}catch{}
        }
    }
    const ready=prepareLocale(locale);
    const protectedContent='[data-user-content],[translate="no"],[data-i18n-ignore],script,style,code,pre,input,textarea,[contenteditable="true"],.community-message>p,.community-message>header>strong,.community-message>strong,.community-letter,.community-mail-card strong,.community-mail-card span,.community-gift-message,.profile-banner,.profile-bio,.profile-head,.profile-caption>strong,#player-hud-name,.player-name,.vs-player-name,.target-player-name,.player-card-name,.alliance-name,.alliance-title,.alliance-desc,.alliance-tag-badge,.member-username,.world-player-name,.ranking-player,.ranking-name,.chat-message-text,.chat-msg-text,.chat-msg-name,.mail-message-body,.sidebar-bottom small,td';
    const authoredSelectors=[
        '[data-i18n]','[data-i18n-attrs]','[data-i18n-scope]','[data-locale-controls]',
        '#content','#panel-content','#dialog-content','#game-dialog','#panel-dialog','#combat-details','#monster-combat-details','#toast',
        '.topbar','.hud-edge-tools','#hud-left-tools','#hud-right-tools','#hud-bottom-nav','#navigation','.hud-quickbar','.hud-side-tools','.hud-bottom','.hud-menu-button','#hud-menu','#hud-events','#hud-bug-report',
        '.active-effects-drawer','.world-chat-shell','.world-search-panel','.encounter-card',
        '.landing-page main','.landing-page>header','.landing-page>footer',
        '#navigation','.game-quick-actions','.subtabs','.subtab','#page-title','.section-heading h2',
        '.button[data-action]','button[data-action^="community-"]:not(.community-mail-card)',
        'button[data-action^="progress-"]','button[data-action^="defense-"]',
        '.community-heading','.community-tabs','.community-body>p','.community-empty','.community-form>small',
        '.community-columns>section>h3','.community-body>h3','.community-mail-card small',
        '.community-body>section>h3','.progression-content>p','.progression-content>h3',
        'form label','form>button','form button[type="submit"]',
        '.sidebar nav a','.nav-caption','.world-picker label','th',
        '.auth-switch button','#auth-submit','.welcome-form .form-note','.welcome-form .pill','.welcome-form a[href*="recover"]',
        '.painted-village','.scene-transition-label','.world-march-hud','.world-march-card','.atlas-biome-label','#atlas-search-panel','#atlas-navigation-panel','.atlas-teleport-guide','.atlas-target-actions',
        'title','.skip-link','#save-state','.loading','.hud-objective small',
        '.world-selector>p','.world-selector button','.world-selector article>strong'
    ].join(',');

    function t(key,parameters={}){
        let value=catalogs[locale]?.[key]??catalogs.en?.[key]??key;
        for(const [name,replacement]of Object.entries(parameters))if(['string','number','boolean'].includes(typeof replacement))value=value.split('{'+name+'}').join(String(replacement));
        return value;
    }
    function has(key){return Object.hasOwn(catalogs[locale]||{},key)||Object.hasOwn(catalogs.en||{},key);}
    function localeTag(){return locale==='fr'?'fr-FR':locale==='en'?'en-US':'de-DE';}
    function formatNumber(value,options={}){return new Intl.NumberFormat(localeTag(),options).format(Number(value)||0);}
    // HUD counters share short units across languages; detailed values keep formatNumber.
    function formatHudNumber(value){
        const parsed=Number(value),amount=Number.isFinite(parsed)?Math.floor(parsed):0;
        if(Math.abs(amount)<10000)return formatNumber(amount);
        const units=['thousand','million','billion','trillion'];
        let magnitude=1000,index=0;
        while(index<units.length-1&&Math.abs(amount)>=magnitude*1000){magnitude*=1000;index++;}
        // Promote after rounding too: 999,950 should read 1M, never 1,000K.
        if(Math.round(Math.abs(amount)/magnitude*10)/10>=1000){magnitude*=1000;index++;}
        if(index>=units.length)return formatNumber(amount,{notation:'scientific',maximumFractionDigits:1,useGrouping:false});
        return formatNumber(amount/magnitude,{maximumFractionDigits:1,useGrouping:false})+t('hud.number.'+units[index]);
    }
    function formatDate(value,options={}){const date=value instanceof Date?value:new Date(value);return new Intl.DateTimeFormat(localeTag(),options).format(date);}
    function formatDuration(value){
        const seconds=Math.max(0,Math.ceil(Number(value)||0)),parts=[];
        if(seconds>=3600)parts.push(Math.floor(seconds/3600)+' '+t('time.hour_short'));
        if(seconds>=60)parts.push(Math.floor(seconds%3600/60)+' '+t('time.minute_short'));
        if(seconds<60||seconds%60)parts.push(seconds%60+' '+t('time.second_short'));
        return parts.join(' ');
    }
    function textTranslation(value){
        value=String(value??'');const trimmed=value.replace(/\u00ad/g,'').replace(/\s+/g,' ').trim(),cacheKey=locale+'|'+trimmed;
        let translated=translationCache.get(cacheKey);
        if(translated===undefined){
            const key=dictionary.get(trimmed);translated=key?t(key):null;
            if(translated===null)for(const template of templates){
                if(!trimmed.includes(template.anchor))continue;
                const match=trimmed.match(template.pattern);if(!match)continue;
                const parameters={};template.names.forEach((name,index)=>parameters[name]=match[index+1]);translated=t(template.key,parameters);break;
            }
            if(translationCache.size>=3000)translationCache.clear();translationCache.set(cacheKey,translated);
        }
        return translated===null?value:value.match(/^\s*/)[0]+translated+value.match(/\s*$/)[0];
    }
    function guarded(element){
        if(!element)return true;
        // An explicit editorial label may live in a table cell. User-content
        // protection still takes precedence, including over explicit keys.
        const selectors=element.matches('[data-i18n],[data-i18n-attrs]')?protectedContent.replace(/,td$/,''):protectedContent;
        return Boolean(element.closest(selectors));
    }
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
        root.querySelectorAll?.('[placeholder],[aria-label],[title],[alt],[data-i18n-attrs]').forEach(element=>{
            if(element.matches('input,textarea')){
                if(element.closest('[data-user-content],[translate="no"],[data-i18n-ignore]'))return;
                const value=element.getAttribute('placeholder');if(value!==null){const saved=sourceAttributes.get(element)||{},record=saved.placeholder&&saved.placeholder.last===value?saved.placeholder:{source:value,last:value};const next=textTranslation(record.source);if(next!==value)element.setAttribute('placeholder',next);record.last=next;saved.placeholder=record;sourceAttributes.set(element,saved);}
            }else for(const attr of ['title','aria-label','alt'])translateAttribute(element,attr);
        });
        document.documentElement.lang=locale;
        document.querySelectorAll('[data-locale-select]').forEach(select=>{select.value=locale;});
        autoMount();
    }
    function setLocale(value){
        locale=normalize(value);try{localStorage.setItem('conquer.locale',locale);}catch{}
        document.cookie='conquer_locale='+encodeURIComponent(locale)+'; Path='+(base||'')+'/; Max-Age=31536000; SameSite=Lax'+(location.protocol==='https:'?'; Secure':'');
        const selected=locale,finish=()=>{if(locale!==selected)return;apply();document.dispatchEvent(new CustomEvent('conquer:locale',{detail:{locale}}));};
        if(catalogs[locale]||!bootstrap.catalogUrls?.[locale])finish();else prepareLocale(locale).then(finish);return locale;
    }
    function mount(container,{compact=false,install=true}={}){
        if(typeof container==='string')container=document.querySelector(container);if(!container||container.querySelector('.locale-tools'))return;
        const tools=document.createElement('section');tools.className='locale-tools'+(compact?' compact':'');tools.dataset.i18nScope='';
        const label=document.createElement('label'),caption=document.createElement('span');caption.dataset.i18n='locale.label';caption.textContent=t('locale.label');
        const select=document.createElement('select');select.dataset.localeSelect='';select.dataset.i18nAttrs='aria-label:locale.label';select.setAttribute('aria-label',t('locale.label'));
        for(const [code,name]of Object.entries(supported)){const option=document.createElement('option');option.value=code;option.textContent=code.toUpperCase()+' · '+name;option.setAttribute('translate','no');select.append(option);}select.value=locale;select.addEventListener('change',()=>setLocale(select.value));label.append(caption,select);tools.append(label);
        if(!compact){for(const key of ['locale.note','locale.coverage']){const p=document.createElement('small');p.dataset.i18n=key;p.textContent=t(key);tools.append(p);}}
        if(install){const row=document.createElement('div');row.className='locale-install';const button=document.createElement('button');button.type='button';button.className='button secondary';button.dataset.pwaInstall='';button.dataset.i18n='pwa.install';button.textContent=t('pwa.install');button.addEventListener('click',async()=>{const message=await installApp();const note=row.querySelector('[role="status"]');if(note){note.dataset.i18n=message;note.textContent=t(message);}});const note=document.createElement('small');note.setAttribute('role','status');note.dataset.i18n='pwa.offline';note.textContent=t('pwa.offline');row.append(button,note);tools.append(row);}
        container.prepend(tools);
    }
    function autoMount(){
        document.querySelectorAll('[data-locale-controls]').forEach(el=>mount(el,{compact:el.dataset.localeCompact==='true',install:el.dataset.localeInstall!=='false'}));
        const panel=document.querySelector('#panel-dialog');if(panel&&['account','settings'].includes(panel.dataset.panel)){const content=panel.querySelector('#content,#panel-content'),scroller=content?.querySelector('.progression-content');if(scroller)mount(scroller,{install:true});else if(content?.querySelector('form[data-form="settings"]'))mount(content,{install:true});}
    }
    function observe(){if(observer)return;observer=new MutationObserver(()=>{if(scheduled)return;scheduled=true;queueMicrotask(()=>{scheduled=false;apply();});});observer.observe(document.body,{subtree:true,childList:true,characterData:true,attributes:true,attributeFilter:['title','aria-label','placeholder','alt']});apply();}
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
    window.addEventListener('storage',event=>{if(event.key==='conquer.locale'){locale=normalize(event.newValue);const selected=locale;prepareLocale(locale).then(()=>{if(locale===selected){apply();document.dispatchEvent(new CustomEvent('conquer:locale',{detail:{locale}}));}});}});
    window.ConquerLocale={ready,t,text:textTranslation,has,apply,mount,setLocale,normalize,formatNumber,formatHudNumber,formatDate,formatDuration,get locale(){return locale;},get supported(){return{...supported};}};
    window.ConquerPWA={register,install:installApp};
    const start=()=>{ready.then(()=>{observe();register();});};if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
