/* UUID v4 support for LAN HTTP, where randomUUID is absent but getRandomValues is available. */
(() => {
    'use strict';
    const webCrypto = globalThis.crypto;
    if (!webCrypto || typeof webCrypto.randomUUID === 'function' || typeof webCrypto.getRandomValues !== 'function') return;
    Object.defineProperty(webCrypto, 'randomUUID', {
        configurable: true,
        writable: true,
        value: function randomUUID() {
            const bytes = webCrypto.getRandomValues(new Uint8Array(16));
            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;
            const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
            return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
        }
    });
})();
