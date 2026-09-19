'use strict';

// Read-only profile layout regression. No server, account, or database is used.
// Run: node tests/profile_layout.cjs
const fs = require('fs');
const path = require('path');
const os = require('os');
const assert = require('assert');

const root = path.resolve(__dirname, '..');
const view = fs.readFileSync(path.join(root, 'views/game.php'), 'utf8');
const sheets = [...view.matchAll(/assets\/css\/([^?"']+)\?/g)].map(match => match[1]);
const styles = sheets.map(name => fs.readFileSync(path.join(root, 'assets/css', name), 'utf8')).join('\n');
const output = fs.mkdtempSync(path.join(os.tmpdir(), 'conquer-profile-layout-'));
const viewports = [[320, 568], [390, 844], [568, 320], [1024, 683], [1280, 800]];

function playwright() {
    try { return require('playwright'); }
    catch (error) {
        if (process.env.PLAYWRIGHT_MODULE) return require(process.env.PLAYWRIGHT_MODULE);
        throw error;
    }
}

const slot = (number, filled = false) => filled
    ? `<button class="lok-gear-slot grade-gold"><small>Stufe 1</small><span aria-hidden="true">♜</span><strong>Holzfälleraxt</strong></button>`
    : `<button class="lok-gear-slot is-empty"><span aria-hidden="true">◇</span><small>Platz ${number}</small></button>`;

const html = `<!doctype html><html lang="de"><head><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body class="mobile-game"><dialog id="panel-dialog" data-panel="profile">
  <div class="page-heading"><span class="panel-emblem">♛</span><h1 id="page-title">Profil</h1><button class="panel-close">×</button></div>
  <section id="panel-content" class="panel-content">
    <div class="subtabs">${['Königreich','Fortschritt','Erfolge','Konto'].map((name, index) => `<button class="subtab ${index ? '' : 'active'}">${name}</button>`).join('')}</div>
    <section class="lok-profile is-own">
      <div class="lok-profile-main">
        <div class="lok-profile-stage"><div class="lok-profile-gear">${[1,2,3,4,5,6].map((n, i) => slot(n, i === 0)).join('')}</div>
          <figure class="lok-profile-hero"><span class="lok-profile-crown">♛</span><div style="width:100%;height:65%;background:var(--ui-card-light);border:3px solid var(--ui-frame);border-radius:45% 45% 20px 20px"></div><figcaption><strong>Ekki1992</strong><small>Burg Stufe 3</small></figcaption></figure>
        </div>
        <div class="lok-profile-data">
          <div class="lok-profile-identity"><div class="lok-profile-avatar"></div><div><small>[SOL] SOLOLEVELING</small><strong>Ekki1992</strong><span>ID: 10</span></div><button class="profile-edit-inline">✎</button></div>
          <div class="lok-profile-facts">${[['Macht','50.435'],['Besiegt','8'],['Allianz','[SOL]'],['Welt','#1']].map(([a,b]) => `<div><span>${a}</span><strong>${b}</strong></div>`).join('')}</div>
          <div class="lok-profile-wallet"><div><span>Edelsteine</span><strong>380</strong></div><div><span>Prestige</span><strong>60</strong></div></div>
          <div class="lok-profile-progress"><div><span>Hunter-Stufe 1</span><strong>0 / 250 XP</strong></div><div class="progress-track lord-progress"><span style="width:0%"></span></div><div><span>Aktionspunkte</span><strong>190 / 200 AP</strong></div><div class="progress-track ap-progress"><span style="width:95%"></span></div></div>
        </div>
      </div>
      <nav class="lok-profile-nav">${['Skins','Truppen','Rangliste','Optionen'].map(name => `<button class="button profile-nav">${name}</button>`).join('')}</nav>
    </section>
  </section>
</dialog></body></html>`;

(async () => {
    const {chromium} = playwright();
    const browser = await chromium.launch({headless:true, ...(process.env.BROWSER_EXECUTABLE_PATH ? {executablePath:process.env.BROWSER_EXECUTABLE_PATH} : {})});
    const failures = [];
    try {
        const page = await browser.newPage();
        await page.setContent(html);
        await page.addStyleTag({content:styles});
        await page.locator('#panel-dialog').evaluate(dialog => dialog.showModal());
        for (const [width, height] of viewports) {
            await page.setViewportSize({width, height});
            const result = await page.evaluate(() => {
                const dialog = document.querySelector('#panel-dialog');
                const content = document.querySelector('#panel-content');
                const profile = document.querySelector('.lok-profile');
                const frame = dialog.getBoundingClientRect();
                const contentFrame = content.getBoundingClientRect();
                const visibleNodes = [...profile.querySelectorAll('button,.lok-profile-main,.lok-profile-stage,.lok-profile-data,.lok-profile-progress,.lok-profile-nav')];
                const controls = visibleNodes.filter(node => {
                    const box = node.getBoundingClientRect();
                    return box.width && box.height && (box.left < contentFrame.left - 2 || box.right > contentFrame.right + 2 || box.top < contentFrame.top - 2 || box.bottom > contentFrame.bottom + 2);
                }).map(node => node.textContent.trim().replace(/\s+/g, ' ').slice(0, 40));
                return {
                    viewport:[innerWidth, innerHeight],
                    outside:frame.left < -2 || frame.top < -2 || frame.right > innerWidth + 2 || frame.bottom > innerHeight + 2,
                    scrolling:[dialog, content, profile, ...profile.querySelectorAll('.lok-profile-main,.lok-profile-data,.lok-profile-progress,.lok-profile-nav')].filter(node => node.scrollHeight > node.clientHeight + 2 || node.scrollWidth > node.clientWidth + 2).map(node => ({node:node.id || node.className, client:[node.clientWidth,node.clientHeight], scroll:[node.scrollWidth,node.scrollHeight]})),
                    controls,
                };
            });
            if (result.outside || result.scrolling.length || result.controls.length) failures.push(result);
            await page.screenshot({path:path.join(output, `${width}x${height}.png`)});
        }
        console.log(JSON.stringify({failures, output}, null, 2));
        assert.equal(failures.length, 0, `Profile layout overflow; see ${output}`);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
