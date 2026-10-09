(() => {
  'use strict';
  const endpoint = document.body.dataset.linkEndpoint;
  if (!endpoint || navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true) return;
  const sources = ['direct','instagram','discord','facebook','google','youtube','tiktok','x','other'];
  const channel = new URL(location.href).searchParams.get('utm_source')?.toLowerCase();
  const host = (() => { try { return new URL(document.referrer).hostname.toLowerCase(); } catch { return ''; } })();
  const domains = { instagram:['instagram.com'],discord:['discord.com','discord.gg'],facebook:['facebook.com','fb.com'],
    google:['google.com','google.lu','google.de','google.fr','google.co.uk'],youtube:['youtube.com','youtu.be'],tiktok:['tiktok.com'],x:['x.com','twitter.com','t.co'] };
  const source = sources.includes(channel) ? channel : Object.keys(domains).find(key => domains[key].some(domain => host === domain || host.endsWith('.' + domain))) || (host ? 'other' : 'direct');
  const track = event => {
    if (!event.isTrusted || (event.type === 'auxclick' && event.button !== 1)) return;
    const link = event.target.closest?.('a[data-track-link]');
    if (!link) return;
    // Navigation is immediate. Keepalive survives page departure; never retries a click.
    try {
      const request = fetch(endpoint, { method:'POST',credentials:'omit',mode:'same-origin',keepalive:true,
        headers:{'Content-Type':'application/json'},body:JSON.stringify({link:link.dataset.trackLink,source}) });
      request.catch(() => {});
    } catch { /* Optional counters cannot interfere with navigation. */ }
  };
  document.addEventListener('click', track, true);
  document.addEventListener('auxclick', track, true);
})();
