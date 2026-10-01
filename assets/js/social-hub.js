/* Conversations, contacts and official news. No player text is translated. */
window.ConquerSocialHub=function(ctx){
    'use strict';
    const {esc,fmt,api,toast,navigate,getState}=ctx;
    const t=(key,args={})=>window.ConquerLocale?.t('social.'+key,args)||key;
    const host=()=>document.querySelector('#content');
    const world=()=>Number(getState()?.city?.world_id||0);
    let tab='overview',data=null,news=null,error='',loading=false,working=false,query='',timer=null,version=0,reloadRequested=false,scopeWorld=0,forceNextDraw=false;
    const receipts=new Map(),drafts=new Map();
    const active=()=>Boolean(host()?.querySelector('.social-hub'));
    const button=(label,action,id='',extra='')=>`<button type="button" class="button secondary" data-action="social-${action}" data-id="${esc(id)}" ${extra}>${esc(t(label))}</button>`;
    const empty=key=>`<p class="social-empty">${esc(t(key))}</p>`;
    const name=p=>p.username||p.display_name||String(p.player_id||p.id);
    const pid=p=>Number(p.player_id||p.id);
    const date=value=>value?new Date(/[zZ]|[+-]\d\d:\d\d$/.test(value)?value:value.replace(' ','T')+'Z').toLocaleString(window.ConquerLocale?.locale||'en',{dateStyle:'medium',timeStyle:'short'}):'';
    const isFriend=id=>(data?.friends||[]).some(p=>pid(p)===id);
    const isBlocked=id=>(data?.blocks||[]).some(p=>pid(p)===id);
    const incoming=id=>(data?.requests?.incoming||[]).some(p=>pid(p)===id);
    const outgoing=id=>(data?.requests?.outgoing||[]).some(p=>pid(p)===id);
    const draftKey=form=>`${scopeWorld}:${form.dataset.form}`;
    function remember(){if(!active())return;for(const form of host().querySelectorAll('form[data-form^="social-"]')){if(form.matches('[data-social-dirty]'))drafts.set(draftKey(form),Object.fromEntries(new FormData(form)));}}
    function restore(){for(const form of host().querySelectorAll('form[data-form^="social-"]')){const values=drafts.get(draftKey(form));if(!values)continue;form.dataset.socialDirty='true';for(const [name,value]of Object.entries(values)){const field=form.elements.namedItem(name);if(field)field.value=value;}}}
    const discord=()=>news?.discord?.invite_url?`<a class="button social-discord" href="${esc(news.discord.invite_url)}" target="_blank" rel="noopener noreferrer">${esc(t('discord_join'))} ↗</a>`:'';
    function contact(p,mode='search'){
        const id=pid(p),blocked=isBlocked(id);
        let controls=blocked?button('unblock','unblock',id):button('message','message',id,`data-name="${esc(name(p))}"`);
        if(!blocked){
            controls+=incoming(id)?button('accept','accept',id)+button('decline','decline',id):isFriend(id)?button('remove_friend','remove',id):outgoing(id)?`<span class="social-status">${esc(t('request_pending'))}</span>${button('cancel_request','decline',id)}`:button('add_friend','request',id);
            controls+=`<details class="social-more"><summary>${esc(t('more'))}</summary>${button('block','block',id)}${button('report','report',id)}</details>`;
        }
        return `<article class="social-contact"><button type="button" class="social-person" data-action="social-profile" data-id="${id}"><span aria-hidden="true">♟</span><strong data-user-content>${esc(name(p))}</strong><small>#${fmt(id)}</small></button><div class="social-actions">${controls}</div></article>`;
    }
    function conversations(){
        return `<div class="social-actions">${button('world_chat','chat','world')}${button('alliance_chat','chat','alliance')}${button('letters','mail')}</div><div class="social-list">${(data.conversations||[]).map(p=>`<button type="button" class="social-conversation" data-action="social-message" data-id="${pid(p)}" data-name="${esc(name(p))}"><strong data-user-content>${esc(name(p))}</strong><time>${esc(date(p.last_at))}</time><span data-user-content>${esc(p.last_message||t('start_conversation'))}</span>${Number(p.unread)>0?`<b class="social-count">${fmt(p.unread)}</b>`:''}</button>`).join('')||empty('no_conversations')}</div>`;
    }
    function overview(){
        const unread=(data.conversations||[]).reduce((n,c)=>n+Number(c.unread||0),0),requests=data.requests?.incoming?.length||0;
        return `<div class="social-welcome"><div><h2>${esc(t('welcome'))}</h2><p>${esc(t('intro'))}</p></div>${discord()}</div><div class="social-summary"><button data-action="social-tab" data-id="conversations"><strong>${fmt(unread)}</strong><span>${esc(t('unread'))}</span></button><button data-action="social-tab" data-id="friends"><strong>${fmt(data.friends?.length||0)}</strong><span>${esc(t('friends'))}</span></button><button data-action="social-tab" data-id="friends"><strong>${fmt(requests)}</strong><span>${esc(t('requests'))}</span></button></div><div class="social-actions">${button('alliance_planning','alliance')}${button('help','help')}</div>${requests?`<h3>${esc(t('requests'))}</h3>${data.requests.incoming.slice(0,3).map(p=>contact(p)).join('')}`:''}<h3>${esc(t('recent_conversations'))}</h3>${conversations()}${news?.posts?.length?`<h3>${esc(t('latest_news'))}</h3>${post(news.posts[0])}`:''}`;
    }
    function friends(){return `<form data-form="social-search" class="social-search"><label>${esc(t('find_players'))}<input type="search" name="query" maxlength="40" minlength="2" value="${esc(query)}" placeholder="${esc(t('search_placeholder'))}" required></label><button class="button">${esc(t('search'))}</button></form>${query?`<h3>${esc(t('search_results'))}</h3>${(data.search||[]).map(p=>contact(p)).join('')||empty('no_results')}`:''}<h3>${esc(t('requests'))}</h3>${(data.requests?.incoming||[]).map(p=>contact(p)).join('')||empty('no_requests')}<h3>${esc(t('friends'))}</h3>${(data.friends||[]).map(p=>contact(p,'friends')).join('')||empty('no_friends')}${data.requests?.outgoing?.length?`<h3>${esc(t('sent_requests'))}</h3>${data.requests.outgoing.map(p=>contact(p)).join('')}`:''}`;}
    function post(p){return `<article class="social-post"><h3 data-user-content>${esc(p.title)}</h3><time>${esc(date(p.created_at))}</time><p data-user-content>${esc(p.body)}</p></article>`;}
    function settings(){
        const prefs=data.preferences||{},channels=prefs.channels||{};
        return `<form data-form="social-settings" class="social-settings"><label>${esc(t('private_messages'))}<select name="private_messages">${['everyone','friends','alliance','nobody'].map(v=>`<option value="${v}" ${prefs.private_messages===v?'selected':''}>${esc(t('privacy_'+v))}</option>`).join('')}</select></label><fieldset><legend>${esc(t('notifications'))}</legend>${['world','alliance','private'].map(channel=>`<label>${esc(t(channel+'_chat'))}<select name="${channel}">${['all','mentions','off'].map(v=>`<option value="${v}" ${(channels[channel]||'all')===v?'selected':''}>${esc(t('notify_'+v))}</option>`).join('')}</select></label>`).join('')}</fieldset><button class="button">${esc(t('save'))}</button></form><h3>${esc(t('blocked_players'))}</h3>${(data.blocks||[]).map(p=>contact(p,'blocked')).join('')||empty('no_blocks')}`;
    }
    function draw(){
        if(!active())return;
        const root=host(),scroll=root.scrollTop,focused=document.activeElement,focusForm=focused?.closest('form[data-form]'),focusName=root.contains(focused)?focused.name:null,selection=typeof focused?.selectionStart==='number'?[focused.selectionStart,focused.selectionEnd]:null;remember();
        const tabs=['overview','conversations','friends','news','settings'];
        root.innerHTML=`<section class="social-hub"><nav class="social-tabs" aria-label="${esc(t('navigation'))}">${tabs.map(key=>`<button data-action="social-tab" data-id="${key}" aria-pressed="${tab===key}">${esc(t(key))}${key==='friends'&&data?.requests?.incoming?.length?` <b>${fmt(data.requests.incoming.length)}</b>`:''}</button>`).join('')}</nav><div class="social-toolbar"><h2>${esc(t(tab))}</h2>${button('refresh','refresh')}</div><p class="social-error" role="alert" ${error?'':'hidden'}>${esc(error)}</p><div class="social-body">${data?({overview,conversations,friends,news:()=>`${discord()}<p>${esc(t('news_intro'))}</p>${(news?.posts||[]).map(post).join('')||empty('no_news')}`,settings})[tab]():empty('loading')}</div></section>`;
        restore();if(focusForm&&focusName){const field=[...root.querySelectorAll('form[data-form]')].find(f=>f.dataset.form===focusForm.dataset.form)?.elements.namedItem(focusName);if(field){field.focus({preventScroll:true});if(selection&&typeof field.setSelectionRange==='function')try{field.setSelectionRange(...selection);}catch{}}}root.scrollTop=scroll;
    }
    async function load(forceDraw=false){
        if(!active()||document.hidden)return;if(loading){reloadRequested=true;forceNextDraw=forceNextDraw||forceDraw;return;}loading=true;reloadRequested=false;forceDraw=forceDraw||forceNextDraw;forceNextDraw=false;const w=world(),v=version;
        try{
            const results=await Promise.allSettled([api(`community/social?world_id=${w}&query=${encodeURIComponent(query)}`),api(`community/news?world_id=${w}`)]);
            if(w!==world()||v!==version||!active())return;
            if(results[0].status==='rejected')throw results[0].reason;
            data=results[0].value;news=results[1].status==='fulfilled'?results[1].value:news;error=results[1].status==='rejected'?results[1].reason.message:'';
            if(!working&&(forceDraw||(!host().contains(document.activeElement?.closest('input,select,textarea'))&&host().dataset.dirty!=='true')))draw();
        }catch(e){if(v===version&&w===world()){error=e.message;if(!working)draw();}}
        finally{loading=false;clearTimeout(timer);if(active()&&!document.hidden){if(reloadRequested||v!==version||w!==world())load();else timer=setTimeout(load,15000);}}
    }
    function render(route){if(route!=='community')return false;const changed=scopeWorld!==world();if(changed){remember();scopeWorld=world();data=null;news=null;query='';error='';version++;}if(active()&&!changed){load();return true;}host().innerHTML='<section class="social-hub"></section>';draw();load();return true;}
    async function execute(payload,element){
        if(working)return false;working=true;const w=world(),v=version,key=JSON.stringify([w,payload]),form=element?.closest('form[data-form]'),disabled=[];
        if(!receipts.has(key))receipts.set(key,crypto.randomUUID());
        remember();for(const control of form?form.elements:element?[element]:[]){disabled.push([control,control.disabled]);control.disabled=true;}
        try{await api('community/social-action',{...payload,world_id:w,expected_world_id:w,request_id:receipts.get(key)});receipts.delete(key);if(w!==world())return false;error='';if(form){drafts.delete(`${w}:${form.dataset.form}`);delete form.dataset.socialDirty;}if(active()){if(v===version)delete host().dataset.dirty;working=false;await load(v===version);}ctx.chatChanged?.();if(payload.action==='preferences.save')toast(t('saved'));return true;}
        catch(e){if(w===world()&&v===version){error=e.message;toast(e.message);}return false;}
        finally{working=false;for(const [control,wasDisabled]of disabled)if(control.isConnected)control.disabled=wasDisabled;}
    }
    function report(id){ctx.openDialog(`<h2>${esc(t('report_player'))}</h2><form data-form="social-report" data-id="${id}" data-world="${world()}"><label>${esc(t('reason'))}<select name="reason">${['harassment','spam','cheating','inappropriate','other'].map(reason=>`<option value="${reason}">${esc(t('reason_'+reason))}</option>`).join('')}</select></label><label>${esc(t('details'))}<textarea name="details" maxlength="2000" rows="4"></textarea></label><button class="button">${esc(t('send_report'))}</button></form>`);}
    function onClick(action,el){
        if(!action?.startsWith('social-'))return false;const id=Number(el.dataset.id);
        if(action==='social-tab'){if(!['overview','conversations','friends','news','settings'].includes(el.dataset.id))return true;remember();tab=el.dataset.id;version++;delete host().dataset.dirty;draw();load();return true;}
        if(action==='social-refresh'){remember();delete host().dataset.dirty;load(true);return true;}
        if(action==='social-profile'){ctx.openPublicProfile(id);return true;}
        if(action==='social-message'){ctx.openPrivate(id,el.dataset.name||'');return true;}
        if(action==='social-chat'){ctx.openChat(el.dataset.id);return true;}
        if(action==='social-alliance'){navigate('alliance-community');return true;}
        if(action==='social-mail'){ctx.openMailbox();return true;}
        if(action==='social-help'){navigate('help');return true;}
        if(action==='social-report'){report(id);return true;}
        const actions={'social-request':'friend.request','social-accept':'friend.accept','social-decline':'friend.decline','social-remove':'friend.remove','social-block':'block.add','social-unblock':'block.remove'};
        if(actions[action])execute({action:actions[action],player_id:id},el);return true;
    }
    function onSubmit(form){
        const type=form.dataset.form;if(!type?.startsWith('social-'))return false;const f=new FormData(form);
        if(type==='social-search'){query=String(f.get('query')||'').trim();drafts.delete(draftKey(form));delete form.dataset.socialDirty;delete host().dataset.dirty;version++;load(true);}
        if(type==='social-settings')execute({action:'preferences.save',private_messages:f.get('private_messages'),channels:Object.fromEntries(['world','alliance','private'].map(c=>[c,f.get(c)]))},form.querySelector('button'));
        if(type==='social-report'){if(Number(form.dataset.world)!==world()){toast(window.ConquerLocale?.t('social_chat.action_failed')||t('report_player'));return true;}execute({action:'report.submit',player_id:Number(form.dataset.id),reason:f.get('reason'),details:f.get('details')},form.querySelector('button')).then(success=>{if(success&&form.isConnected){form.closest('dialog')?.close();toast(t('report_sent'));}});}
        return true;
    }
    document.addEventListener('visibilitychange',()=>{if(document.hidden)clearTimeout(timer);else if(active())load();});
    for(const event of ['input','change'])document.addEventListener(event,e=>{const form=e.target.closest('form[data-form^="social-"]');if(form&&host()?.contains(form)){form.dataset.socialDirty='true';remember();}});
    return {render,onClick,onSubmit};
};
