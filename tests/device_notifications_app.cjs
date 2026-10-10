'use strict';
// Real /city app with a disposable database; only device push capabilities and
// push endpoints are simulated. No browser subscription or push delivery occurs.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const {spawn} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const out = path.join(root, 'output/playwright/device-notifications');
const sizes = [[1280, 800], [390, 844], [320, 568], [844, 390], [568, 320]];
const endpoint = 'https://fcm.googleapis.com/fcm/send/uok-isolated-ui-fixture';
const publicKey = Buffer.concat([Buffer.from([4]), Buffer.alloc(64, 1)]).toString('base64url');

function installPushMock({locale, endpoint}) {
    if (locale !== 'en') {
        localStorage.setItem('conquer.locale', locale);
        document.cookie = 'conquer_locale=' + locale + '; Path=/; SameSite=Lax';
    }
    const mock = window.testDevicePush = {
        permission: sessionStorage.getItem('test.push.permission') || 'default',
        subscribed: sessionStorage.getItem('test.push.subscribed') === 'yes',
        prompts: 0, subscriptions: 0, unsubscriptions: 0, shows: 0,
    };
    const subscription = {
        endpoint, expirationTime: null,
        toJSON: () => ({endpoint, expirationTime: null, keys: {p256dh: 'fixture-p256dh', auth: 'fixture-auth'}}),
        async unsubscribe() {
            mock.unsubscriptions++;
            mock.subscribed = false;
            sessionStorage.removeItem('test.push.subscribed');
            return true;
        },
    };
    function MockNotification() { throw new Error('The test must never display a real notification'); }
    Object.defineProperty(MockNotification, 'permission', {get: () => mock.permission});
    MockNotification.requestPermission = async () => {
        mock.prompts++;
        mock.permission = 'granted';
        sessionStorage.setItem('test.push.permission', 'granted');
        return 'granted';
    };
    Object.defineProperty(window, 'Notification', {configurable: true, writable: true, value: MockNotification});
    Object.defineProperty(window, 'PushManager', {configurable: true, writable: true, value: function PushManager() {}});
    const registration = {
        scope: location.origin + '/', active: {state: 'activated'},
        pushManager: {
            async getSubscription() { return mock.subscribed ? subscription : null; },
            async subscribe(options) {
                if (options.userVisibleOnly !== true || !options.applicationServerKey) throw new Error('Missing Web Push options');
                mock.subscriptions++;
                mock.subscribed = true;
                sessionStorage.setItem('test.push.subscribed', 'yes');
                return subscription;
            },
        },
        async showNotification() { mock.shows++; },
        async getNotifications() { return []; },
        async update() {},
    };
    const worker = new EventTarget();
    worker.ready = Promise.resolve(registration);
    worker.register = async () => registration;
    worker.getRegistration = async () => registration;
    worker.getRegistrations = async () => [registration];
    Object.defineProperty(navigator, 'serviceWorker', {configurable: true, value: worker});
}

async function reachable(locator, size, label) {
    await locator.scrollIntoViewIfNeeded();
    const geometry = await locator.evaluate(el => {
        const r = el.getBoundingClientRect();
        const top = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
        return {
            touch: r.height >= 44, visible: r.top >= -1 && r.bottom <= innerHeight + 1,
            fits: r.left >= -1 && r.right <= innerWidth + 1,
            uncovered: top === el || el.contains(top),
        };
    });
    assert(Object.values(geometry).every(Boolean), JSON.stringify({label, size, ...geometry}));
}

(async () => {
    fs.mkdirSync(out, {recursive: true});
    const port = await new Promise(resolve => {
        const server = net.createServer();
        server.listen(0, '127.0.0.1', () => {const port = server.address().port; server.close(() => resolve(port));});
    });
    const fixture = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
        [path.join(root, 'tools/preview-feature-fixture.php'), '--appearance', '--port=' + port],
        {cwd: root, stdio: ['pipe', 'pipe', 'pipe'], windowsHide: true});
    let browser, log = '';
    const errors = [], layouts = [], writes = [], requests = [];
    try {
        await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(Error(log || 'Preview timeout')), 60000);
            fixture.stdout.on('data', data => {log += data; if (log.includes('Synthetic preview ready')) {clearTimeout(timeout); resolve();}});
            fixture.stderr.on('data', data => {log += data;});
            fixture.on('error', reject);
            fixture.on('exit', () => {clearTimeout(timeout); reject(Error(log));});
        });
        browser = await chromium.launch({headless: true, executablePath: process.env.BROWSER_EXECUTABLE_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe'});
        for (const locale of ['en', 'de', 'fr']) {
            let enabled = false, available = true;
            const context = await browser.newContext({viewport: {width: 1280, height: 800}, hasTouch: true, locale: 'de-DE'});
            await context.addInitScript(installPushMock, {locale, endpoint});
            const page = await context.newPage();
            page.setDefaultTimeout(20000);
            page.on('pageerror', error => errors.push({locale, message: error.message}));
            await page.route('**/api/push/**', async route => {
                const request = route.request(), url = new URL(request.url()), action = url.pathname.split('/').at(-1);
                const payload = request.method() === 'POST' ? request.postDataJSON() : null;
                requests.push({locale, action, method: request.method(), payload, endpoint: url.searchParams.get('endpoint')});
                if (payload) {
                    assert(request.headers()['x-csrf-token'], action + ' uses the authenticated CSRF token');
                    assert.equal(payload.expected_world_id, 1, action + ' stays in the current world');
                }
                if (action === 'subscribe') enabled = true;
                if (action === 'unsubscribe') enabled = false;
                const status = {available, public_key: publicKey, enabled, preferences: {}, player_id: 1};
                await route.fulfill({status: 200, contentType: 'application/json', json: {ok: true, data: action === 'test' ? {queued: true} : status}});
            });
            const base = 'http://127.0.0.1:' + port;
            await page.goto(base + '/?zugang=login');
            await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');
            await page.locator('[name=password]').fill('PreviewFixture!2026');
            await Promise.all([page.waitForURL('**/city'), page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);
            page.on('request', request => {
                const pathname = new URL(request.url()).pathname;
                if (request.method() === 'POST' && pathname.startsWith('/api/') && !pathname.startsWith('/api/push/') && pathname !== '/api/telemetry') writes.push(pathname);
            });
            await page.goto(base + '/city#city');
            await page.locator('#navigation').waitFor();
            if (await page.locator('.kingdom-intro').isVisible()) await page.locator('.kingdom-intro [data-action=close-dialog]').tap();
            await page.locator('#hud-menu').tap();
            await page.locator('[data-action=dialog-tab][data-id=settings]').tap();
            const panel = page.locator('.device-notification-settings');
            const button = name => panel.locator(`[data-device-notification="${name}"]`);
            const waitState = state => page.waitForFunction(state => document.querySelector('[data-device-notification-status]')?.dataset.state === state, state);
            await panel.waitFor();
            await waitState('disabled');
            assert.equal(await page.evaluate(() => ConquerLocale.locale), locale, 'English is the default even with a German browser');
            assert.equal(await page.evaluate(() => testDevicePush.prompts), 0, 'Opening settings does not request OS permission');
            assert.equal(await page.evaluate(() => testDevicePush.subscriptions), 0, 'Opening settings does not subscribe');
            for (const [width, height] of sizes) {
                await page.setViewportSize({width, height});
                for (const name of ['enable', 'refresh']) await reachable(button(name), `${width}x${height}`, `${locale}:${name}`);
                assert.equal(await panel.evaluate(el => el.scrollWidth > el.clientWidth + 1), false, 'The notification panel does not overflow horizontally');
                await panel.scrollIntoViewIfNeeded();
                await page.screenshot({path: path.join(out, `${locale}-disabled-${width}x${height}.png`)});
                layouts.push({locale, state: 'disabled', width, height});
            }
            await page.setViewportSize({width: 390, height: 844});
            await button('enable').tap();
            await waitState('enabled');
            assert.equal(await page.evaluate(() => testDevicePush.prompts), 1, 'Only explicit Enable requests permission');
            assert.equal(await page.evaluate(() => testDevicePush.subscriptions), 1);
            // A changing game snapshot must not replace permission controls or
            // shift the test button while this account's settings are open.
            let changedSnapshots = 0;
            await page.route('**/api/game/state*', async route => {
                const response = await route.fetch(), json = await response.json();
                json.data.map_center = {...json.data.map_center, notification_scroll_check: ++changedSnapshots};
                await route.fulfill({response, json});
            });
            await button('test').scrollIntoViewIfNeeded();
            const beforePoll = await button('test').boundingBox();
            await button('test').evaluate(el => {window.testStableNotificationButton = el;});
            await Promise.all([
                page.waitForResponse(response => response.url().includes('/api/game/state') && response.ok()),
                page.evaluate(() => dispatchEvent(new Event('online'))),
            ]);
            await page.waitForTimeout(500);
            assert(changedSnapshots > 0, 'The server snapshot changed during the settings check');
            assert(await button('test').evaluate(el => el === window.testStableNotificationButton), 'Polling preserves the actual notification button');
            assert.deepEqual(await button('test').boundingBox(), beforePoll, 'Polling preserves the notification touch position');
            for (const [width, height] of sizes) {
                await page.setViewportSize({width, height});
                for (const name of ['test', 'disable', 'refresh']) await reachable(button(name), `${width}x${height}`, `${locale}:${name}`);
                await panel.scrollIntoViewIfNeeded();
                await page.screenshot({path: path.join(out, `${locale}-enabled-${width}x${height}.png`)});
                layouts.push({locale, state: 'enabled', width, height});
            }
            await page.setViewportSize({width: 390, height: 844});
            const testResponse = page.waitForResponse(response => response.url().endsWith('/api/push/test'));
            await button('test').tap();
            assert.equal((await testResponse).status(), 200);
            await button('refresh').tap();
            await waitState('enabled');
            assert.equal(await page.evaluate(() => testDevicePush.subscriptions), 1, 'Status refresh does not resubscribe');
            await page.reload();
            await page.locator('#hud-menu').waitFor();
            if (!await panel.isVisible()) {
                await page.locator('#hud-menu').tap();
                await page.locator('[data-action=dialog-tab][data-id=settings]').tap();
            }
            await panel.waitFor();
            await waitState('enabled');
            assert.equal(await page.evaluate(() => testDevicePush.prompts), 0, 'Reload restores state without prompting');
            assert.equal(await page.evaluate(() => testDevicePush.subscriptions), 0, 'Reload reuses the existing subscription');
            await button('disable').tap();
            await waitState('disabled');
            assert.equal(await page.evaluate(() => testDevicePush.unsubscriptions), 1, 'Disable releases the device subscription');
            available = false;
            await button('refresh').tap();
            await waitState('unconfigured');
            assert.equal(await button('enable').isEnabled(), false);
            await panel.scrollIntoViewIfNeeded();
            await page.screenshot({path: path.join(out, `${locale}-unconfigured-390x844.png`)});
            available = true;
            await page.evaluate(() => {testDevicePush.permission = 'denied';});
            await button('refresh').tap();
            await waitState('denied');
            assert.equal(await button('enable').isEnabled(), false);
            await panel.scrollIntoViewIfNeeded();
            await page.screenshot({path: path.join(out, `${locale}-denied-390x844.png`)});
            await page.evaluate(() => {delete window.Notification; delete window.PushManager;});
            await button('refresh').tap();
            await waitState('unsupported');
            assert.equal(await button('enable').isEnabled(), false);
            await panel.scrollIntoViewIfNeeded();
            await page.screenshot({path: path.join(out, `${locale}-unsupported-390x844.png`)});
            assert.equal(await page.evaluate(() => testDevicePush.shows), 0, 'No real browser notification is displayed');
            await context.close();
        }
        assert.deepEqual(errors, [], 'No browser page errors');
        assert.deepEqual(writes, [], 'Notification controls never send gameplay commands');
        for (const locale of ['en', 'de', 'fr']) {
            for (const action of ['subscribe', 'unsubscribe', 'test']) assert.equal(requests.filter(r => r.locale === locale && r.action === action).length, 1, locale + ':' + action + ' occurs once');
        }
        fs.writeFileSync(path.join(out, 'report.json'), JSON.stringify({layouts, states: ['disabled', 'enabled', 'unconfigured', 'denied', 'unsupported'], pageErrors: errors, gameplayWrites: writes, mockedPushRequests: requests.map(({payload, ...request}) => request)}, null, 2) + '\n');
        console.log('PASS device notification settings: 3 languages, 30 desktop/portrait/landscape layouts, permission lifecycle, enabled/disabled/denied/unconfigured/unsupported, CSRF, one test delivery, no gameplay writes. ' + out);
    } finally {
        if (browser) await browser.close();
        if (fixture.exitCode === null) {
            const stopped = new Promise(resolve => fixture.once('exit', resolve));
            fixture.stdin.write('\n');
            await Promise.race([stopped, new Promise((_, reject) => {const timer = setTimeout(() => reject(Error('Fixture cleanup timeout')), 15000); timer.unref();})]);
        }
    }
})().catch(error => {console.error(error); process.exitCode = 1;});
