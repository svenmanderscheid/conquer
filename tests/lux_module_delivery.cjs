'use strict';
// Exercise real Apache delivery: an HTTP fixture can hide a broken .mjs MIME type.
const assert = require('node:assert/strict');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = (process.env.LUX_DELIVERY_URL || 'http://localhost/conquer').replace(/\/$/, '');
const url = new URL(base);
assert(['localhost', '127.0.0.1'].includes(url.hostname), 'Read-only localhost asset check required');

(async () => {
    const browser = await chromium.launch({headless: true, channel: 'chrome'});
    try {
        for (const viewport of [{width: 1280, height: 800}, {width: 390, height: 844}, {width: 844, height: 390}]) {
            const page = await browser.newPage({viewport, serviceWorkers: 'block'});
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            // A public JSON document establishes the origin without logging in or changing game state.
            await page.goto(base + '/assets/world-lux-preview/game-geography.json');
            const response = await page.request.get(base + '/assets/world-lux-preview/hydrology.mjs?v=2');
            assert.equal(response.status(), 200);
            assert.match(response.headers()['content-type'], /^(?:application|text)\/javascript\b/);
            await page.addScriptTag({url: base + '/assets/js/world-lux.js'});
            const result = await page.evaluate(async base => {
                const map = await ConquerLuxWorld.load(base, {
                    version: 1, width: 768, height: 1100,
                    geography_url: 'assets/world-lux-preview/game-geography.json',
                    hydrology_url: 'assets/world-lux-preview/game-hydrology.json'
                });
                return {communes: map.communes.length, cantons: map.cantons.length, water: typeof map.waterAt};
            }, url.pathname.replace(/\/$/, ''));
            assert.deepEqual(result, {communes: 100, cantons: 12, water: 'function'});
            assert.deepEqual(errors, []);
            await page.close();
            console.log(`PASS ${viewport.width}x${viewport.height}: Apache JavaScript MIME, dynamic hydrology import and Luxembourg geometry`);
        }
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
