/* Mobile presentation and navigation; all game actions stay in their feature modules. */
window.ConquerMobilePages = function (ctx) {
    'use strict';
    const {navigate, getRoute, getPlayfield, closeChat} = ctx;
    const media = matchMedia('(max-width:700px), (max-width:1100px) and (max-height:520px) and (orientation:landscape)');
    const panel = document.querySelector('#panel-dialog'), dialog = document.querySelector('#game-dialog');
    const key = 'conquerMobilePage';
    const fullDetails = '.territory-shell,.item-sources,.research-detail,.march-command,.rally-detail,.rally-list,.skin-collection,.march-skin-detail,.theme-bundle-review,.vip-panel,.mail-detail,.mail-compose,.community-letter,.battle-report,.combat-report,.monster-report,.scout-report,.menu-groups,.shop-hub,.lok-profile,.bug-report-form,form[data-form="profile"]';
    let activeOverlay = null, traversing = false, backPending = false;
    const dismissals = new Map();
    const read = () => history.state?.[key];
    const write = (entry, push = false) => history[push ? 'pushState' : 'replaceState']({...history.state, [key]:entry}, '', '#'+entry.route);
    function goBack() {if(!backPending){backPending=true;history.back();}}
    function seed() {
        if (!read()) write({route:getRoute(), depth:0, overlay:null});
    }
    function route(tab, fromHistory = false) {
        if (!media.matches && !read()) return false;
        seed();
        if (!fromHistory) {
            const previous = read();
            if (previous.route !== tab) write({route:tab, depth:previous.depth+(previous.overlay ? 0 : 1), overlay:null}, !previous.overlay);
            else if (previous.overlay) write({...previous, overlay:null});
        } else if (read().route !== tab) write({...read(), route:tab, overlay:null});
        activeOverlay = null;
        return true;
    }
    function opened(kind, dismiss) {
        if (!media.matches || traversing) return;
        if (dismiss) dismissals.set(kind, dismiss);
        seed();
        const previous = read();
        if (previous.overlay !== kind) write({route:getRoute(), depth:previous.depth+1, overlay:kind}, true);
        activeOverlay = kind;
    }
    function closed(kind) {
        if (activeOverlay === kind) activeOverlay = null;
        if (!traversing && read()?.overlay === kind) goBack();
    }
    function back() {
        if (activeOverlay && dismissals.has(activeOverlay)) {dismissals.get(activeOverlay)();return;}
        if (read()?.depth > 0) goBack();
        else navigate(getPlayfield());
    }
    function backButton(parent, action) {
        if (!parent || parent.querySelector(':scope > .mobile-page-back')) return;
        const button = document.createElement('button');
        button.type = 'button';button.className = 'mobile-page-back';
        button.setAttribute('aria-label','Zurück');button.title = 'Zurück';button.textContent = '‹';
        button.addEventListener('click', action);parent.prepend(button);
    }
    backButton(panel.querySelector('.page-heading'), back);
    function syncDialog() {
        const full = Boolean(dialog.querySelector(fullDetails));
        dialog.classList.toggle('mobile-detail-page', full);
        const heading = dialog.querySelector('.popup-heading,.march-command-heading');
        backButton(heading, () => {
            // Feature back actions retain their selection, drafts and current server data.
            const target = dialog.querySelector('[data-action="mailbox-back"], [data-action="march-rally-back"], [data-action="march-skins"], [data-action="theme-bundles"], [data-action="rally-list"]');
            if (target && !target.disabled) target.click(); else dialog.close();
        });
    }
    function viewport() {
        const view = window.visualViewport;
        // Pinching must remain usable; only track the keyboard's unzoomed viewport.
        const usable = view && Math.abs(view.scale-1)<.05;
        document.documentElement.style.setProperty('--mobile-page-height', `${usable ? view.height : innerHeight}px`);
        document.documentElement.style.setProperty('--mobile-page-top', `${usable ? view.offsetTop : 0}px`);
    }
    window.addEventListener('popstate', () => {
        backPending = false;
        const entry = read();
        if (!entry && !activeOverlay) return;
        traversing = true;
        if (activeOverlay && activeOverlay !== entry?.overlay) dismissals.get(activeOverlay)?.();
        // A desktop dialog may be open while an older mobile route remains in
        // history. Only dismiss an overlay this controller actually registered;
        // a nested calculator's Back must not close an unrelated parent dialog.
        if (dialog.open && activeOverlay === 'dialog' && entry?.overlay !== 'dialog') dialog.close();
        if (activeOverlay === 'chat' && entry?.overlay !== 'chat') closeChat();
        activeOverlay = entry?.overlay || null;
        if (entry && entry.route !== getRoute()) navigate(entry.route, {fromHistory:true});
        // Forward navigation never replays an action or restores stale form HTML.
        const shown = entry?.overlay === 'relic' ? panel.querySelector('.treasury-detail') : entry?.overlay === 'relic-exchange' ? panel.querySelector('.treasury-exchange') : entry?.overlay === 'dialog' ? dialog.open : document.body.classList.contains('world-chat-open');
        if (entry?.overlay && !shown) {
            write({...entry, overlay:null});activeOverlay = null;
        }
        traversing = false;
    });
    window.addEventListener('resize', viewport);
    window.visualViewport?.addEventListener('resize', viewport);
    window.visualViewport?.addEventListener('scroll', viewport);
    media.addEventListener('change', () => {viewport();syncDialog();});
    // A reload reconstructs feature state from the server, never an old modal entry.
    if (read()?.overlay) write({...read(), overlay:null});
    viewport();
    return {route, opened, closed, syncDialog, back, isMobile:()=>media.matches};
};
