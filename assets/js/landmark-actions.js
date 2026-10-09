/* Map shortcuts use fresh authenticated eligibility before opening an army. */
window.ConquerLandmarkActions=function(ctx){
  'use strict';
  const {api,esc,openDialog,getState,action,marchPanel,territoryPanel}=ctx;
  const tr=(key,params={})=>ctx.t('landmark.'+key,params);
  let sequence=0;
  const dialog=()=>document.querySelector('#game-dialog');
  const when=value=>{
    if(!value)return tr('unannounced');
    const raw=String(value),date=new Date(/Z$|[+-]\d\d:\d\d$/.test(raw)?raw:raw.replace(' ','T')+'Z');
    return Number.isFinite(date.getTime())?date.toLocaleString(window.ConquerLocale?.locale||'en',{weekday:'short',day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit',timeZoneName:'short'}):tr('unannounced');
  };
  function info(target,reason){
    const name=['shrine','congress'].includes(target.kind)?window.ConquerLocale.text(target.name):target.name;
    const attackWindow=target.next_window,event=target.event;
    const timing=attackWindow?`${when(attackWindow.starts_at)} – ${when(attackWindow.ends_at)}`:event?`${when(event.starts_at||event.next_starts_at)} – ${when(event.ends_at)}`:tr('anytime');
    openDialog(`<section class="landmark-info"><h2>${esc(tr('attack_info'))}</h2><h3 translate="no">${esc(name)}</h3><p role="status">${esc(reason)}</p><dl><dt>${esc(tr('window'))}</dt><dd>${esc(timing)}</dd></dl>${target.kind==='commune'&&!target.owner_alliance_id?`<p>${esc(tr('npc_first_attack'))}</p>`:''}<p class="muted">${esc(tr('arrival_rule'))}</p><button type="button" class="button secondary" data-action="close-dialog">${esc(tr('close'))}</button></section>`);
  }
  async function rally(kind,id){
    const requestedWorld=Number(getState().city.world_id),request=++sequence;
    openDialog(`<section class="landmark-info"><h2>${esc(tr('rally'))}</h2><p role="status">${esc(tr('checking'))}</p></section>`);
    const valid=()=>request===sequence&&Number(getState().city.world_id)===requestedWorld&&dialog()?.open&&!!dialog().querySelector('.landmark-info');
    try{
      if(kind==='territory'){
        const state=await api(`territory/state?world_id=${requestedWorld}`);
        if(!valid())return;
        const result=await api(`territory/target?world_id=${requestedWorld}&id=${encodeURIComponent(id)}`),target=result.target||result;
        if(!valid())return;
        const reason=!target.can_attack?target.blocked_reason||tr('unavailable'):!['leader','vice_leader','officer'].includes(state.role)?tr('leadership'):null;
        if(reason){info(target,reason);return;}
        await territoryPanel.openRally(id);return;
      }
      const result=await api(`shrines/${Number(id)}`),target=result.shrine;
      if(!valid())return;
      if(!target)throw new Error(tr('unavailable'));
      if(!target.can_attack){info(target,target.locked_reason||(!target.own_alliance_id?tr('join_alliance'):Number(target.alliance_id)===Number(target.own_alliance_id)?tr('owned'):tr('closed')));return;}
      marchPanel.open(Number(id),kind==='congress'?'congress-rally':'shrine-rally',{target});
    }catch(error){if(valid())info({name:tr('rally')},error.message);}
  }
  function onClick(act,button){
    if(!['landmark-rally','landmark-scout'].includes(act))return false;
    const {kind,id}=button.dataset;
    if(!['territory','shrine','congress'].includes(kind)||!id)return true;
    if(act==='landmark-rally')rally(kind,id);
    else action('march/scout-landmark',{target_kind:kind==='territory'?'territory':'shrine',target_id:String(id)},tr('scout_sent'));
    return true;
  }
  return {onClick};
};
