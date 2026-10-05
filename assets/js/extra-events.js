/* One scheduled-event window; event rules and destinations remain on the server. */
window.ConquerExtraEvents = function({getState,base,openDialog,navigate,now,date}) {
    const locale=window.ConquerLocale,t=(key,args)=>locale.t(key,args);
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const targets=['events','dungeons','market','community','land','world','alliance'],icons=['events','quests','alliance','world-map','market'];
    const dialog=document.getElementById('game-dialog'),button=document.getElementById('hud-extra-event');
    let selected=null,signature='';
    const events=()=>{
        const state=getState(),rows=state?.extra_events??(state?.extra_event_button?[state.extra_event_button]:[]);
        return rows.filter(event=>targets.includes(event.target)&&icons.includes(event.icon)&&date(event.ends_at)>now());
    };
    function paint(rows,focus=false){
        if(!rows.some(event=>String(event.id)===selected))selected=rows.length?String(rows[0].id):null;
        const event=rows.find(event=>String(event.id)===selected);
        const tabs=rows.map(event=>`<button type="button" role="tab" id="extra-event-tab-${Number(event.id)}" aria-controls="extra-event-panel" aria-selected="${String(event.id)===selected}" tabindex="${String(event.id)===selected?'0':'-1'}" data-action="extra-event-tab" data-id="${Number(event.id)}"><img src="${base}/assets/art/menu-icons-v2/${event.icon}.png" alt=""><span data-user-content>${esc(event.name)}</span></button>`).join('');
        const content=event?`<article id="extra-event-panel" role="tabpanel" aria-labelledby="extra-event-tab-${Number(event.id)}" tabindex="0"><div class="extra-event-details"><header class="extra-event-hero"><img src="${base}/assets/art/menu-icons-v2/${event.icon}.png" alt=""><h3 data-user-content>${esc(event.name)}</h3></header><p class="extra-event-description" data-user-content>${esc(event.description)}</p><p class="extra-event-time">${esc(t('extra_event.hub.ends'))} <strong data-end="${esc(event.ends_at)}"></strong></p></div><footer class="extra-event-footer"><button type="button" class="button" data-action="extra-event-go" data-id="${Number(event.id)}">${esc(t('extra_event.hub.go'))}</button></footer></article>`:`<p class="extra-events-empty">${esc(t('extra_event.hub.empty'))}</p>`;
        const html=`<nav class="extra-event-tabs" role="tablist" aria-label="${esc(t('extra_event.hub.tabs'))}">${tabs}</nav>${content}`;
        let host=dialog.querySelector('.extra-events');
        if(!host||!dialog.open){openDialog(`<h2>${esc(t('extra_event.hub.title'))}</h2><section class="extra-events">${html}</section>`);host=dialog.querySelector('.extra-events');}
        else host.innerHTML=html;
        if(event){host.querySelector('[data-end]').textContent=locale.formatDuration(Math.max(0,(date(event.ends_at)-now())/1000));}
        if(focus){const tab=host.querySelector('[aria-selected="true"]');tab?.focus({preventScroll:true});tab?.scrollIntoView({block:'nearest',inline:'nearest'});}
        signature=JSON.stringify(rows);
    }
    function update(){
        const rows=events();if(button){button.hidden=!rows.length;button.dataset.action='extra-events-open';button.removeAttribute('data-id');button.setAttribute('aria-label',t('extra_event.hub.open',{count:rows.length}));button.title=t('extra_event.hub.title');button.querySelector('img').src=base+'/assets/art/menu-icons-v2/events.png';button.querySelector('.hud-edge-label').textContent=t('extra_event.hub.title');let count=button.querySelector('.extra-events-count');if(!count){count=document.createElement('b');count.className='extra-events-count';count.setAttribute('aria-hidden','true');button.append(count);}count.hidden=rows.length<2;count.textContent=String(rows.length);}
        if(dialog.open&&dialog.querySelector('.extra-events')&&JSON.stringify(rows)!==signature)paint(rows);
    }
    function onAction(action,id){
        if(action==='extra-events-open'){paint(events());return true;}
        if(action==='extra-event-tab'){selected=String(id);paint(events(),true);return true;}
        if(action==='extra-event-go'){const event=events().find(event=>String(event.id)===String(id));if(event){dialog.close();navigate(event.target);}else paint(events());return true;}
        return false;
    }
    dialog.addEventListener('keydown',event=>{
        if(!event.target.matches('.extra-events [role="tab"]')||!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
        const tabs=[...dialog.querySelectorAll('.extra-events [role="tab"]')],index=tabs.indexOf(event.target);
        const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(index+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;
        event.preventDefault();onAction('extra-event-tab',tabs[next].dataset.id);
    });
    return {update,onAction};
};
