/* One-time invitation output. No keys are written to browser storage. */
(() => {
    'use strict';
    const output = document.getElementById('alpha-issued-keys');
    const button = document.querySelector('[data-copy-alpha]');
    const status = document.querySelector('[data-alpha-copy-status]');
    button?.addEventListener('click', async () => {
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(output.value);
            status.textContent = 'Kopiert. Du kannst die Keys jetzt weitergeben.';
        } catch (_) {
            output.focus();
            output.select();
            output.setSelectionRange(0, output.value.length);
            status.textContent = 'Keys sind markiert. Bitte über das Kopiermenü deines Geräts kopieren.';
        }
    });
})();
