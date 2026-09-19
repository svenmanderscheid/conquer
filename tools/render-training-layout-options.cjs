'use strict';

const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

const base = process.env.TRAINING_FIXTURE_URL || 'http://127.0.0.1:19321';
const output = path.resolve(__dirname, '../artifacts/training-layout-options');
fs.mkdirSync(output, { recursive: true });

const shared = `
  body.mobile-game #panel-dialog[data-panel='army']>.page-heading {
    width:100%!important;
    align-self:stretch!important;
  }
  .training-school .training-max {
    background:var(--ui-gold-soft)!important;
    border-color:var(--ui-gold)!important;
    color:var(--ui-ink)!important;
    box-shadow:0 3px 0 var(--ui-gold)!important;
  }
  .training-school .training-quantity>button {
    background:color-mix(in srgb,var(--ui-primary) 14%,var(--ui-card))!important;
    border-color:var(--ui-primary-light)!important;
    color:var(--ui-primary-dark)!important;
  }
  .training-school .training-schools button {
    position:relative;
    overflow:hidden;
    background:var(--ui-card)!important;
    border-color:var(--ui-line)!important;
  }
  .training-school .training-schools button::after {
    content:'';
    position:absolute;
    inset:auto 10px 5px 10px;
    height:3px;
    border-radius:3px;
    background:var(--ui-green);
  }
  .training-school .training-schools button[aria-pressed='true'] {
    background:var(--ui-primary)!important;
    border-color:var(--ui-primary-dark)!important;
    color:var(--ui-card-light)!important;
    box-shadow:0 3px 0 var(--ui-primary-dark)!important;
  }
  .training-school .training-schools button[aria-pressed='true'] :is(strong,small) { color:var(--ui-card-light)!important; }
  .training-school .training-schools button[aria-pressed='true']::after { background:var(--ui-gold); }
  .training-school .training-schools button>span {
    display:grid;
    place-items:center;
    width:38px;
    height:38px;
    border-radius:12px;
    background:var(--ui-inset);
  }
  .training-school .training-schools button[aria-pressed='true']>span { background:var(--ui-primary-light); }
  .training-school .training-view-tabs button[aria-pressed='true'] {
    background:var(--ui-primary)!important;
    border-color:var(--ui-primary-dark)!important;
    color:var(--ui-card-light)!important;
  }
`;

const optionA = shared + `
  body.mobile-game #panel-dialog[data-panel='army']:not([data-hospital='true']) {
    --game-window-height:min(690px,calc(100dvh - 20px - env(safe-area-inset-top) - env(safe-area-inset-bottom)));
  }
  .training-workspace { grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr); gap:10px 18px; }
  .training-workspace>.training-tier-picker { width:min(100%,820px); }
  .training-controls { justify-content:stretch; }
  .training-detail-scroll {
    flex:1;
    display:flex;
    flex-direction:column;
    justify-content:center;
    padding:16px;
    border:1px solid var(--ui-line);
    border-radius:18px;
    background:var(--ui-card);
    box-shadow:0 3px 0 var(--ui-line);
  }
  .training-detail-scroll::before {
    content:'Ausbildungsauftrag';
    margin:0 0 10px;
    color:var(--ui-primary-dark);
    font-size:17px;
    font-weight:bold;
  }
  .training-cost-grid { background:var(--ui-gold-soft); }
  .training-showcase { border:1px solid var(--ui-line); }
`;

const optionB = shared + `
  body.mobile-game #panel-dialog[data-panel='army']:not([data-hospital='true']) {
    --game-window-height:min(700px,calc(100dvh - 20px - env(safe-area-inset-top) - env(safe-area-inset-bottom)));
  }
  .training-workspace {
    grid-template-columns:minmax(0,.88fr) minmax(0,1.12fr);
    grid-template-rows:auto minmax(0,1fr);
    gap:12px 20px;
  }
  .training-workspace>.training-showcase { grid-column:1; grid-row:1/3; }
  .training-workspace>.training-tier-picker { grid-column:2; grid-row:1; width:100%; }
  .training-workspace>.training-controls { grid-column:2; grid-row:2; }
  .training-showcase {
    border:1px solid var(--ui-gold);
    box-shadow:0 3px 0 var(--ui-line);
  }
  .training-controls {
    align-self:stretch;
    justify-content:center;
    padding:16px;
    border:1px solid var(--ui-line);
    border-radius:18px;
    background:var(--ui-card);
    box-shadow:0 3px 0 var(--ui-line);
  }
  .training-detail-scroll::before {
    content:'Ausbildungsauftrag';
    display:block;
    margin:0 0 10px;
    color:var(--ui-primary-dark);
    font-size:17px;
    font-weight:bold;
  }
  .training-cost-grid { background:var(--ui-inset); }
  .training-submit-row { grid-template-columns:78px 1fr; }
`;

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  try {
    const page = await browser.newPage({ viewport: { width: 1280, height: 800 }, hasTouch: true });
    await page.goto(base);
    await page.locator('[data-mode=login]').click();
    await page.locator('[name=username]').fill('PreviewPlayer');
    await page.locator('[name=password]').fill('PreviewFixture!2026');
    await Promise.all([page.waitForURL('**/city'), page.locator('#auth-submit').click()]);

    for (const [name, css] of [['variante-a-kompakt', optionA], ['variante-b-zweispaltig', optionB]]) {
      await page.goto(base + '/city#army');
      await page.reload();
      await page.locator('.training-school.has-model').waitFor();
      await page.addStyleTag({ content: css });
      await page.waitForTimeout(200);
      await page.screenshot({ path: path.join(output, name + '.png') });
    }
  } finally {
    await browser.close();
  }
})().catch(error => {
  console.error(error);
  process.exit(1);
});
