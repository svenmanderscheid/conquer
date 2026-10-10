/* Admin-only email output. Addresses are never stored in browser storage. */
(async () => {
    'use strict';
    if (window.ConquerLocale?.ready) await window.ConquerLocale.ready;
    const button = document.querySelector('[data-waitlist-copy]');
    const output = document.getElementById('waitlist-emails');
    const list = document.querySelector('[data-waitlist-copy-list]');
    const status = document.querySelector('[data-waitlist-copy-status]');
    if (!button || !output || !list || !status) return;
    const report = key => {
        const params = { count: Number(button.dataset.emailCount) };
        status.dataset.i18n = 'admin.waitlist.' + key;
        status.dataset.i18nParams = JSON.stringify(params);
        status.textContent = window.ConquerLocale.t(status.dataset.i18n, params);
        status.hidden = false;
    };
    button.addEventListener('click', async () => {
        if (!output.value || button.disabled) return;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(output.value);
            list.hidden = true;
            report('copied');
        } catch (_) {
            list.hidden = false;
            output.focus();
            output.select();
            output.setSelectionRange(0, output.value.length);
            report('copy_manual');
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });
})();
