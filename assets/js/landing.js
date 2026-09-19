(() => {
  'use strict';
  const root = document.documentElement;
  const header = document.querySelector('[data-site-header]');
  const toggle = document.querySelector('.lp-menu-toggle');
  const nav = document.querySelector('#site-navigation');
  const setMenu = open => {
    if (!toggle || !nav) return;
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
    root.classList.toggle('menu-open', open);
  };
  toggle?.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
  nav?.addEventListener('click', event => { if (event.target.closest('a')) setMenu(false); });
  addEventListener('scroll', () => header?.classList.toggle('is-scrolled', scrollY > 24), { passive: true });

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
    if (alphaInput) alphaInput.required = register;
    if (password) {
      password.minLength = register ? 10 : 1;
      password.autocomplete = register ? 'new-password' : 'current-password';
      password.placeholder = register ? 'Mindestens 10 Zeichen' : 'Dein Passwort';
    }
    if (submit) submit.firstChild.textContent = register ? ' Königreich gründen ' : ' Weiterspielen ';
  };
  document.querySelectorAll('[data-mode]').forEach(button => button.addEventListener('click', () => setMode(button.dataset.mode)));
  setMode(authMode?.value === 'login' ? 'login' : 'register');
  alphaInput?.addEventListener('input', () => {
    const value = alphaInput.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 24);
    alphaInput.value = value.match(/.{1,4}/g)?.join('-') || '';
  });

  const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const reel = document.querySelector('[data-game-reel]');
  if (reel) {
    const scenes = [...reel.querySelectorAll('[data-reel-scene]')];
    const sceneButtons = [...reel.querySelectorAll('[data-reel-to]')];
    const reelToggle = reel.querySelector('[data-reel-toggle]');
    const toggleIcon = reelToggle?.querySelector('[aria-hidden="true"]');
    const toggleLabel = reel.querySelector('[data-reel-toggle-label]');
    const counter = reel.querySelector('[data-reel-counter]');
    let activeScene = 0;
    let paused = reduced;
    let reelTimer = null;
    const restartProgress = () => {
      reel.classList.remove('is-running');
      void reel.offsetWidth;
      if (!paused) reel.classList.add('is-running');
    };
    const showScene = index => {
      activeScene = (index + scenes.length) % scenes.length;
      scenes.forEach((scene, sceneIndex) => {
        const active = sceneIndex === activeScene;
        scene.classList.toggle('is-active', active);
        scene.setAttribute('aria-hidden', String(!active));
      });
      sceneButtons.forEach((button, buttonIndex) => {
        const active = buttonIndex === activeScene;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
      });
      if (counter) counter.textContent = `${String(activeScene + 1).padStart(2, '0')} / ${String(scenes.length).padStart(2, '0')}`;
      restartProgress();
    };
    const schedule = () => {
      if (reelTimer) clearInterval(reelTimer);
      reelTimer = paused ? null : setInterval(() => showScene(activeScene + 1), 5500);
    };
    const setPaused = value => {
      paused = value;
      reel.classList.toggle('is-paused', paused);
      reelToggle?.setAttribute('aria-pressed', String(paused));
      if (toggleIcon) toggleIcon.textContent = paused ? '▶' : 'Ⅱ';
      if (toggleLabel) toggleLabel.textContent = paused ? 'Animation abspielen' : 'Animation pausieren';
      restartProgress();
      schedule();
    };
    sceneButtons.forEach(button => button.addEventListener('click', () => {
      showScene(Number(button.dataset.reelTo));
      schedule();
    }));
    reelToggle?.addEventListener('click', () => setPaused(!paused));
    document.addEventListener('visibilitychange', () => {
      if (document.hidden && reelTimer) { clearInterval(reelTimer); reelTimer = null; }
      else schedule();
    });
    showScene(0);
    setPaused(paused);
  }

  if (!reduced && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); }
    }), { threshold: .14, rootMargin: '0px 0px -40px' });
    document.querySelectorAll('.reveal').forEach(element => observer.observe(element));
  } else document.querySelectorAll('.reveal').forEach(element => element.classList.add('is-visible'));

  const error = document.querySelector('.form-error');
  if (error) { document.querySelector('#zugang')?.scrollIntoView(); error.focus(); }
})();
