(() => {
    'use strict';
    const screen = document.getElementById('app-start');
    if (!screen) return;
    const track = document.getElementById('app-start-progress');
    const status = document.getElementById('app-start-status');
    const retry = document.getElementById('app-start-retry');
    const percent = document.getElementById('app-start-percent');
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
        status.textContent = text('startup.ready');
        setTimeout(() => { screen.classList.add('is-complete'); setTimeout(() => screen.remove(), 350); }, 350);
    }
    function fail() {
        if (finished) return;
        status.textContent = text('startup.error');
        retry.hidden = false;
    }
    const timeout = setTimeout(fail, 30000);
    retry.addEventListener('click', () => location.reload());
    window.ConquerStartup = { ready() { if (finished) return; gameReady = true; update(90); finish(); }, fail };
    document.addEventListener('DOMContentLoaded', () => {
        update(20);
        const images = [...document.images].filter(img => img.loading !== 'lazy' && img.getAttribute('src'));
        let settled = 0;
        const done = () => { settled++; update(20 + Math.round(50 * settled / Math.max(1, images.length))); };
        images.forEach(img => { if (img.complete) done(); else { img.addEventListener('load', done, {once:true}); img.addEventListener('error', done, {once:true}); } });
    }, {once:true});
    window.addEventListener('load', () => { pageReady = true; update(80); finish(); }, {once:true});
})();
