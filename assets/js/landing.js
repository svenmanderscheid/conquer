(() => {
  'use strict';
  const authMode = document.querySelector('#auth-mode');
  const password = document.querySelector('[name="password"]');
  const alphaField = document.querySelector('.alpha-key-field');
  const alphaInput = document.querySelector('[name="alpha_key"]');
  const submit = document.querySelector('#auth-submit');
  const setMode = mode => {
    const register = mode === 'register';
    if (authMode) authMode.value = register ? 'register' : 'login';
    document.querySelectorAll('[data-mode]').forEach(button => {
      const active = button.dataset.mode === mode;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    if (alphaField) alphaField.hidden = !register;
    if (alphaInput) {
      alphaInput.required = register;
      alphaInput.disabled = !register;
    }
    if (password) {
      password.minLength = register ? 10 : 1;
      password.autocomplete = register ? 'new-password' : 'current-password';
      password.placeholder = register ? 'Mindestens 10 Zeichen' : 'Dein Passwort';
    }
    if (submit) submit.firstChild.textContent = register ? ' Königreich gründen ' : ' Weiterspielen ';
    window.ConquerLocale?.apply(document.querySelector('[data-auth-card]'));
  };
  document.querySelectorAll('[data-mode]').forEach(button => {
    button.addEventListener('click', () => setMode(button.dataset.mode));
  });
  document.querySelectorAll('[data-auth-target]').forEach(link => {
    link.addEventListener('click', () => {
      setMode(link.dataset.authTarget);
      // Move keyboard focus to the form without opening the mobile keyboard.
      document.querySelector('#zugang')?.focus({ preventScroll:true });
    });
  });
  setMode(authMode?.value === 'login' ? 'login' : 'register');
  alphaInput?.addEventListener('input', () => {
    const value = alphaInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 24);
    alphaInput.value = value.match(/.{1,4}/g)?.join('-') || '';
  });
  const error = document.querySelector('.form-error');
  if (error) {
    document.querySelector('#zugang')?.scrollIntoView();
    error.focus({ preventScroll:true });
  }
})();
