(() => {
    'use strict';
    const screen = document.getElementById('app-start');
    if (!screen) return;
    const track = document.getElementById('app-start-progress');
    const status = document.getElementById('app-start-status');
    const retry = document.getElementById('app-start-retry');
    const percent = document.getElementById('app-start-percent');
    const reduced = () => document.body.classList.contains('reduced-motion') || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    let progress = 0, pageReady = false, gameReady = screen.dataset.game !== 'true', finished = false;
    const text = key => window.ConquerLocale?.t(key) || status.textContent;
    function update(value) {
        progress = Math.max(progress, value);
        track.setAttribute('aria-valuenow', String(progress));
        track.firstElementChild.style.width = progress + '%';
        percent.textContent = progress + '%';
    }
    function finish() {
        if (finished || !pageReady || !gameReady) return;
        finished = true;
        clearTimeout(timeout);
        update(100);
        retry.hidden = true;
        screen.setAttribute('aria-busy', 'false');
        status.textContent = text('startup.ready');
        screen.classList.add('is-complete');
        const dismiss = () => {
            screen.remove();
            window.dispatchEvent(new Event('conquer:startup-complete'));
        };
        // This is only the visual fade, never a minimum loading time.
        if (reduced()) dismiss(); else setTimeout(dismiss, 300);
    }
    function fail() {
        if (finished) return;
        status.textContent = text('startup.error');
        retry.hidden = false;
    }
    const timeout = setTimeout(fail, 30000);
    retry.addEventListener('click', () => location.reload());
    window.ConquerStartup = { ready() { if (finished) return; gameReady = true; update(90); finish(); }, fail };
    const documentReady = () => {
        update(20);
        status.textContent = text('startup.artwork');
        const images = [...document.images].filter(img => img.loading !== 'lazy' && img.getAttribute('src'));
        let settled = 0;
        const done = () => { settled++; update(20 + Math.round(50 * settled / Math.max(1, images.length))); };
        images.forEach(img => { if (img.complete) done(); else { img.addEventListener('load', done, {once:true}); img.addEventListener('error', done, {once:true}); } });
    };
    const loaded = () => {
        pageReady = true;
        update(80);
        if (!gameReady) status.textContent = text('startup.sync');
        finish();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', documentReady, {once:true});
    else documentReady();
    if (document.readyState === 'complete') loaded();
    else window.addEventListener('load', loaded, {once:true});
})();
