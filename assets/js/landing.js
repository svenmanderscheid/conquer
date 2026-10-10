(() => {
  'use strict';
  const syncLocale=()=>{
    const field=document.querySelector('#waitlist-form input[name="locale"]');
    if(field&&window.ConquerLocale)field.value=window.ConquerLocale.locale;
  };
  document.addEventListener('conquer:locale',syncLocale);
  document.querySelector('#waitlist-form')?.addEventListener('submit',syncLocale);
  syncLocale();
  const tierPicker = document.querySelector('.lp-tier-picker');
  const troopImages = [...document.querySelectorAll('.lp-troop-grid img')];
  const troopNames = ['Infantry guardian', 'Fire archer', 'Cavalry shadow rider'];
  if (tierPicker && troopImages.length) {
    // Keep the full T5 previews available when JavaScript is disabled.
    tierPicker.hidden = false;
    tierPicker.addEventListener('click', event => {
      const button = event.target.closest('[data-preview-tier]');
      if (!button) return;
      const tier = Number(button.dataset.previewTier);
      if (!Number.isInteger(tier) || tier < 1 || tier > 5) return;
      troopImages.forEach((img, index) => {
        img.src = img.src.replace(/-t\d+-ui\.webp/, `-t${tier}-ui.webp`);
        img.alt = `${troopNames[index]}, tier ${tier}`;
      });
      tierPicker.querySelectorAll('button').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
      document.querySelector('[data-tier-status]').textContent = `Troop artwork · Tier ${tier} of 5`;
    });
  }
  document.querySelectorAll('[data-auth-target]').forEach(link => {
    link.addEventListener('click', event => {
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      history.pushState(null, '', link.href);
      // Move keyboard focus to the form without opening the mobile keyboard.
      document.querySelector('#zugang')?.focus({ preventScroll:true });
      document.querySelector('#zugang')?.scrollIntoView({ block:'nearest' });
    });
  });
  const error = document.querySelector('.form-error, .lp-waitlist-success');
  if (error) {
    document.querySelector('#zugang')?.scrollIntoView();
    error.focus({ preventScroll:true });
  }

  const heroSlides = [...document.querySelectorAll('.lp-hero-slide')];
  const heroButtons = [...document.querySelectorAll('[data-hero-slide]')];
  let heroIndex = 0;
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
  heroButtons.forEach(button => button.addEventListener('click', () => {
    showHero(Number(button.dataset.heroSlide));
  }));
  showHero(0);
})();
