(() => {
  'use strict';
  document.querySelectorAll('[data-link-copy]').forEach(button => {
    button.addEventListener('click', async () => {
      const input = button.closest('.link-copy').querySelector('input');
      const status = document.querySelector('[data-link-status]');
      const t = key => window.ConquerLocale?.t?.('links.' + key) || '';
      try {
        await navigator.clipboard.writeText(input.value);
        status.textContent = t('copied');
      } catch {
        input.focus(); input.select(); input.setSelectionRange(0, input.value.length);
        status.textContent = t('copy_manual');
      }
      // Feedback sits beside the copied link, even on long mobile lists.
      let feedback = button.closest('.link-row').querySelector('.link-copy-feedback');
      if (!feedback) {
        feedback = document.createElement('p'); feedback.className = 'link-copy-feedback'; feedback.setAttribute('role', 'status');
        button.closest('.link-copy').after(feedback);
      }
      feedback.textContent = status.textContent;
    });
  });
})();
