'use strict';
// The authored seven-button dock shape from game.js; only navigation actions are inert.
// Isolated HUD tests do not start game.js or load a player state.
module.exports=function fixtureNavigation(){
 const t=window.ConquerLocale.t,text=window.ConquerLocale.text;
 const entries=[['quests',t('nav.quests_short'),'quests'],['inventory',t('nav.inventory_short'),'inventory'],['reports',text('Post'),'reports'],['chat',text('Chat'),'chat'],['shop',t('nav.market'),'market'],['alliance',text('Allianz'),'alliance'],['world',text('Welt'),'world-map']];
 document.querySelector('#navigation').innerHTML=entries.map(([id,label,art])=>`<button class="game-dock-item ${id==='world'?'hud-scene-switch':''}" data-action="tab" data-id="${id}"><img class="dock-icon dock-menu-art" src="/assets/art/menu-icons/${art}.png" alt=""><span class="dock-label">${label}</span></button>`).join('');
};
