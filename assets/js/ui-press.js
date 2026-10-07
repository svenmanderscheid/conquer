/* Visual feedback only: native controls and game handlers own activation. */
(() => {
  'use strict';
  const body = document.body;
  if (!body?.matches('.mobile-game,.welcome,.play-login-page,.report-page') || body.matches('.admin-modern')) return;

  const excluded = '.painted-village-building,.atlas-marker,.atlas-minimap,.rt-node,.talent-node,.talent-star-select,.portrait,.portrait-button,.profile-avatar,.avatar-choice,.member-identity,#account-button,[data-action*="portrait"],[draggable="true"],[data-ui-press="off"],#uok-layout-layer';
  let pointer = null, keyboard = null;

  function control(target) {
    const button = target instanceof Element ? target.closest('button,a.button') : null;
    return button && !button.closest(excluded) && !button.closest('[inert]') &&
      !button.matches(':disabled,[aria-disabled="true"]') ? button : null;
  }
  function prepare(button) {
    button.style.setProperty('--ui-press-edge', getComputedStyle(button).borderBottomColor);
    button.classList.add('ui-press-ready');
  }
  function paint(button) {
    if (button) button.classList.toggle('ui-is-pressed',
      !!((pointer?.button === button && pointer.inside) || keyboard?.button === button));
  }
  function clearPointer() {
    const button = pointer?.button;
    pointer = null;
    paint(button);
  }
  function clearKeyboard() {
    const button = keyboard?.button;
    keyboard = null;
    paint(button);
  }
  function clear() { clearPointer(); clearKeyboard(); }
  const inside = (event, rect) => event.clientX >= rect.left && event.clientX <= rect.right &&
    event.clientY >= rect.top && event.clientY <= rect.bottom;
  const listen = (type, handler) => document.addEventListener(type, handler, { capture: true, passive: true });

  listen('pointerover', event => {
    const button = control(event.target);
    if (button && !button.contains(event.relatedTarget)) prepare(button);
  });
  listen('focusin', event => { const button = control(event.target); if (button) prepare(button); });
  listen('pointerdown', event => {
    if (!event.isPrimary || event.button !== 0) return;
    clearPointer();
    const button = control(event.target);
    if (!button) return;
    prepare(button);
    // Keep the original hit area stable while CSS visually lowers the face.
    pointer = { button, id: event.pointerId, rect: button.getBoundingClientRect(), inside: true };
    paint(button);
  });
  listen('pointermove', event => {
    if (!pointer || pointer.id !== event.pointerId) return;
    if (!(event.buttons & 1) || !pointer.button.isConnected || !control(pointer.button)) { clearPointer(); return; }
    pointer.inside = inside(event, pointer.rect);
    paint(pointer.button);
  });
  listen('pointerout', event => {
    if (!pointer || pointer.id !== event.pointerId || pointer.button.contains(event.relatedTarget)) return;
    if (!event.relatedTarget) { clearPointer(); return; }
    pointer.inside = false;
    paint(pointer.button);
  });
  for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
    listen(type, event => { if (pointer?.id === event.pointerId) clearPointer(); });
  }
  listen('keydown', event => {
    if (event.repeat || event.altKey || event.ctrlKey || event.metaKey || ![' ', 'Enter'].includes(event.key)) return;
    const button = control(event.target);
    // Space scrolls a native link; only Enter activates it.
    if (!button || (button.matches('a') && event.key !== 'Enter')) return;
    clearKeyboard();
    prepare(button);
    keyboard = { button, key: event.key };
    paint(button);
  });
  listen('keyup', event => { if (keyboard?.key === event.key) clearKeyboard(); });
  listen('focusout', event => { if (keyboard && !keyboard.button.contains(event.relatedTarget)) clearKeyboard(); });
  listen('scroll', clear);
  listen('dragstart', clear);
  document.addEventListener('visibilitychange', () => { if (document.hidden) clear(); });
  window.addEventListener('blur', clear);
  window.addEventListener('pagehide', clear);
})();
