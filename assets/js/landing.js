(() => {
  'use strict';
  const authMode = document.querySelector('#auth-mode');
  const password = document.querySelector('[name="password"]');
  const alphaField = document.querySelector('.alpha-key-field');
  const alphaInput = document.querySelector('[name="alpha_key"]');
  const submit = document.querySelector('#auth-submit');
  const setMode = mode => {
    if (!['waitlist', 'register', 'login'].includes(mode)) mode = 'waitlist';
    const register = mode === 'register';
    document.querySelectorAll('[data-access-panel]').forEach(panel => {
      panel.hidden = panel.dataset.accessPanel !== (mode === 'waitlist' ? 'waitlist' : 'auth');
    });
    document.querySelector('[data-auth-card]').dataset.accessMode = mode;
    if (authMode) authMode.value = register ? 'register' : 'login';
    document.querySelectorAll('.auth-switch [data-auth-target], .lp-key-link [data-auth-target]').forEach(link => {
      const active = link.dataset.authTarget === mode;
      link.classList.toggle('active', active);
      if (active) link.setAttribute('aria-current', 'true');
      else link.removeAttribute('aria-current');
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
  document.querySelectorAll('[data-auth-target]').forEach(link => {
    link.addEventListener('click', event => {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      setMode(link.dataset.authTarget);
      history.pushState(null, '', link.href);
      // Move keyboard focus to the form without opening the mobile keyboard.
      document.querySelector('#zugang')?.focus({ preventScroll:true });
      document.querySelector('#zugang')?.scrollIntoView({ block:'nearest' });
    });
  });
  window.addEventListener('popstate', () => setMode(new URL(location.href).searchParams.get('zugang') || 'waitlist'));
  setMode(document.querySelector('[data-auth-card]')?.dataset.accessMode || 'waitlist');
  alphaInput?.addEventListener('input', () => {
    const value = alphaInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 24);
    alphaInput.value = value.match(/.{1,4}/g)?.join('-') || '';
  });
  const error = document.querySelector('.form-error, .lp-waitlist-success');
  if (error) {
    document.querySelector('#zugang')?.scrollIntoView();
    error.focus({ preventScroll:true });
  }

  const heroSlides = [...document.querySelectorAll('.lp-hero-slide')];
  const heroButtons = [...document.querySelectorAll('[data-hero-slide]')];
  const reduceMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
  let heroIndex = 0;
  let heroTimer = null;
  const showHero = index => {
    if (!heroSlides.length) return;
    heroIndex = (index + heroSlides.length) % heroSlides.length;
    heroSlides.forEach((slide, i) => slide.classList.toggle('is-active', i === heroIndex));
    heroButtons.forEach((button, i) => {
      const active = i === heroIndex;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', String(active));
    });
  };
  const restartHero = () => {
    if (reduceMotion || heroSlides.length < 2) return;
    clearInterval(heroTimer);
    heroTimer = setInterval(() => showHero(heroIndex + 1), 7000);
  };
  heroButtons.forEach(button => button.addEventListener('click', () => {
    showHero(Number(button.dataset.heroSlide));
    restartHero();
  }));
  showHero(0);
  restartHero();
})();
