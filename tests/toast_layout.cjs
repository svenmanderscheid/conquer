const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '..');
const styles = [
  'assets/css/game.css',
  'assets/css/game-theme.css',
  'assets/css/mobile-shell.css',
  'assets/css/game-popups.css',
  'assets/css/popup-skin.css',
  'assets/css/village-theme.css',
].map(file => fs.readFileSync(path.join(root, file), 'utf8')).join('\n');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
  try {
    const page = await browser.newPage({ hasTouch: true });
    await page.setContent(`<!doctype html><html lang="de"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><style>${styles}</style><body class="mobile-game"><main></main><nav id="navigation" class="game-dock"></nav><dialog id="panel-dialog"><div class="page-heading"><h1>Truppenausbildung</h1></div><section id="panel-content"></section></dialog><dialog id="game-dialog"><div id="dialog-content"></div></dialog><div id="toast" role="status">655 Truppen werden ausgebildet.</div></body></html>`);

    for (const [width, height] of [[390, 844], [320, 568], [568, 320]]) {
      await page.setViewportSize({ width, height });
      for (const host of ['body', 'panel-dialog', 'game-dialog']) {
        const metrics = await page.evaluate(hostId => {
          const toast = document.querySelector('#toast');
          document.querySelectorAll('dialog[open]').forEach(dialog => dialog.close());
          const host = hostId === 'body' ? document.body : document.querySelector(`#${hostId}`);
          host.append(toast);
          if (host instanceof HTMLDialogElement) host.showModal();
          toast.classList.add('visible');
          const rect = toast.getBoundingClientRect();
          return { top: rect.top, bottom: rect.bottom, left: rect.left, right: rect.right };
        }, host);
        assert(metrics.top >= 0, `${host} toast starts inside ${width}x${height}`);
        assert(metrics.bottom < height / 2, `${host} toast stays in the upper half of ${width}x${height}`);
        assert(metrics.left >= 10 && metrics.right <= width - 10, `${host} toast fits horizontally in ${width}x${height}: ${JSON.stringify(metrics)}`);
      }
    }
    console.log('PASS toast stays compact at the top in the playfield and both dialog layers.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
