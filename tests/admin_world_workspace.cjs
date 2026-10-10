'use strict';

// Invoked by admin_backoffice.cjs against reward_admin.php's disposable database.
const assert = require('assert/strict');
const fs = require('fs');
const path = require('path');

module.exports = async ({page, base, errors}) => {
  assert.match(base, /^http:\/\/127\.0\.0\.1:\d+$/, 'Use the isolated loopback fixture');
  const out = path.join(__dirname, '../output/playwright/admin-world-workspace');
  fs.mkdirSync(out, {recursive: true});
  const keys = ['overview', 'settings', 'spawns', 'territories', 'events', 'gifts', 'activity', 'management'];
  const route = base + '/admin/world?world_id=1';
  const workspace = page.locator('[data-world-workspace]');
  const form = page.locator('form[action$="/world-save"]');
  const field = name => form.locator(`[name="${name}"]`);
  const link = key => workspace.locator(`[data-world-link="${key}"]`);
  const invalidControlErrors = [];
  const posts = [];
  const onConsole = message => {
    if (/invalid form control.*not focusable/i.test(message.text())) invalidControlErrors.push(message.text());
  };
  const onRequest = request => {
    if (request.method() === 'POST') posts.push(new URL(request.url()).pathname);
  };
  page.on('console', onConsole);
  page.on('request', onRequest);

  async function active(key) {
    await page.waitForFunction(expected => {
      const panels = [...document.querySelectorAll('[data-world-panel]')];
      const visible = panels.filter(panel => panel.getClientRects().length > 0);
      return visible.length > 0 && visible.every(panel => panel.dataset.worldPanel === expected);
    }, key);
    const visibleKeys = await workspace.locator('[data-world-panel]:visible').evaluateAll(
      panels => [...new Set(panels.map(panel => panel.dataset.worldPanel))]
    );
    assert.deepEqual(visibleKeys, [key], 'Only the selected world section is visible');
    const current = await link(key).getAttribute('aria-current');
    assert(current && current !== 'false', 'Current world section is exposed to assistive technology');
    assert.equal(await form.locator('button[type=submit]').isVisible(),
      ['settings', 'spawns', 'territories'].includes(key), 'The shared save action follows the settings sections');
  }

  async function select(key) {
    await link(key).click();
    await active(key);
    assert.equal(new URL(page.url()).hash, '#world-' + key);
  }

  async function fit(label) {
    await page.evaluate(async () => {
      await document.fonts.ready;
      await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    });
    const metrics = await page.evaluate(() => ({
      overflow: document.documentElement.scrollWidth - innerWidth,
      scrollbars: getComputedStyle(document.documentElement).scrollbarWidth,
      bodyScrollbars: getComputedStyle(document.body).scrollbarWidth,
      translated: !/admin\.world(?:\.|_workspace\.)/.test(document.querySelector('[data-world-workspace]').innerText)
    }));
    assert(metrics.overflow <= 1, `${label}: horizontal overflow ${metrics.overflow}px`);
    assert.equal(metrics.scrollbars, 'none', label + ': root scrollbars stay hidden');
    assert.equal(metrics.bodyScrollbars, 'none', label + ': body scrollbars stay hidden');
    assert(metrics.translated, label + ': translation keys do not leak into the interface');
    assert.equal(await page.locator('.notice.error').count(), 0, label + ': page renders successfully');
    assert(await workspace.locator('[data-world-link]').evaluateAll(links => links.every(element => {
      const box = element.getBoundingClientRect();
      return box.width >= 44 && box.height >= 44;
    })), label + ': section navigation has touch targets');
  }

  try {
    await page.context().addCookies([{name: 'conquer_locale', value: 'en', url: base}]);
    await page.setViewportSize({width: 1440, height: 1000});
    const response = await page.goto(route);
    assert.equal(response.status(), 200);
    await workspace.waitFor();
    assert.deepEqual(await workspace.locator('[data-world-link]').evaluateAll(links =>
      links.map(element => element.dataset.worldLink)), keys, 'Eight clearly separated world sections');
    for (const key of keys) assert.equal(await link(key).getAttribute('href'), '#world-' + key);
    await active('overview');
    assert.equal(await form.count(), 1, 'Settings, spawns and alliance radii retain one save form');

    const original = await form.evaluate(element => [...new FormData(element).entries()]);
    const originalNames = original.map(([name]) => name).sort();
    for (const name of ['name', 'speed_factor', 'settings[interval_minutes]', 'settings[resource_density_pct]',
      'settings[monster_density_pct]', 'settings[village_density_pct]', 'settings[alliance_center_radius]',
      'settings[alliance_outpost_radius]', 'settings[resource_weights][food]', 'settings[monster_weights][Orc]']) {
      assert(originalNames.includes(name), 'Complete world settings still include ' + name);
    }

    const changes = {
      name: 'Fixture Workspace Edited',
      speed_factor: '1.3',
      'settings[interval_minutes]': '37',
      'settings[resource_density_pct]': '1.125',
      'settings[alliance_center_radius]': '13'
    };
    await select('settings');
    await field('name').fill(changes.name);
    await field('speed_factor').fill(changes.speed_factor);
    await select('spawns');
    await field('settings[interval_minutes]').fill(changes['settings[interval_minutes]']);
    for (const kind of ['resource', 'monster', 'village']) {
      const grouped = field(`settings[${kind}_density_pct]`).locator('xpath=ancestor::details[1]');
      assert.equal(await grouped.count(), 1, kind + ' spawn tuning is in an expandable section');
    }
    const resourceDetails = field('settings[resource_density_pct]').locator('xpath=ancestor::details[1]');
    if (!await resourceDetails.evaluate(element => element.open)) await resourceDetails.locator('summary').first().click();
    await field('settings[resource_density_pct]').fill(changes['settings[resource_density_pct]']);
    await select('territories');
    await field('settings[alliance_center_radius]').fill(changes['settings[alliance_center_radius]']);
    await field('reason').fill('Isolated world workspace regression check');
    await select('events');
    const extraEventForm = page.locator('form[action$="/extra-event-save"]');
    await extraEventForm.locator('[name=name_en]').fill('Cancelled fixture event');
    await extraEventForm.locator('[name=reason]').fill('Must not submit while preserving the world draft');
    await Promise.all([
      page.waitForEvent('dialog').then(async dialog => {
        const type = dialog.type();
        await dialog.dismiss();
        assert.equal(type, 'confirm', 'Another form warns before submitting away from unsaved world settings');
      }),
      extraEventForm.locator('button[type=submit]').click()
    ]);
    assert.equal(await extraEventForm.locator('button[type=submit]').isDisabled(), false,
      'Cancelling another form keeps its save action usable');
    assert.equal(posts.length, 0, 'Cancelling another form sends no request');
    await select('settings');
    for (const [name, value] of Object.entries(changes)) assert.equal(await field(name).inputValue(), value,
      'Section navigation retains unsaved ' + name);
    const pending = await form.evaluate(element => [...new FormData(element).entries()]);
    assert.deepEqual(pending.map(([name]) => name).sort(), originalNames,
      'Inactive sections retain every form field in the save payload');
    const pendingValues = Object.fromEntries(pending);
    for (const [name, value] of original) {
      if (!(name in changes) && name !== 'reason') assert.equal(pendingValues[name], value,
        'Section edits preserve untouched ' + name);
    }

    // Native validation must be able to reveal and focus fields hidden by navigation or details.
    await field('name').fill('');
    await select('spawns');
    await form.locator('button[type=submit]').click();
    await active('settings');
    assert(await field('name').evaluate(element => document.activeElement === element),
      'A required field in another section is revealed and focused');
    assert.equal(posts.length, 0, 'Invalid hidden name cannot issue a save');
    await field('name').fill(changes.name);
    await select('spawns');
    if (!await resourceDetails.evaluate(element => element.open)) await resourceDetails.locator('summary').first().click();
    await field('settings[resource_density_pct]').fill('101');
    await resourceDetails.locator('summary').first().click();
    assert.equal(await resourceDetails.evaluate(element => element.open), false);
    await select('settings');
    await form.locator('button[type=submit]').click();
    await active('spawns');
    assert(await resourceDetails.evaluate(element => element.open), 'Validation expands the hidden invalid spawn group');
    assert(await field('settings[resource_density_pct]').evaluate(element => document.activeElement === element),
      'Validation focuses the invalid spawn input');
    assert.equal(posts.length, 0, 'Invalid collapsed spawn tuning cannot issue a save');
    await field('settings[resource_density_pct]').fill(changes['settings[resource_density_pct]']);

    // A real save proves hidden panels participate in the server's existing world-save contract.
    await select('territories');
    await Promise.all([page.waitForNavigation(), form.locator('button[type=submit]').click()]);
    assert.equal(await page.locator('.notice.error').count(), 0, 'The combined settings save succeeds');
    assert.equal(await page.locator('.notice.success').count(), 1);
    assert.deepEqual(posts, ['/admin/action/world-save'], 'Exactly one explicit world settings write');
    await page.reload();
    for (const [name, value] of Object.entries(changes)) assert.equal(await field(name).inputValue(), value,
      'Saved values across sections survive reload: ' + name);
    const saved = Object.fromEntries(await form.evaluate(element => [...new FormData(element).entries()]));
    for (const [name, value] of original) {
      if (!(name in changes) && !['reason', 'operation_id', 'csrf_token'].includes(name)) assert.equal(saved[name], value,
        'Saving preserves untouched ' + name);
    }

    // Local links use browser history; legacy deep links remain useful from other admin pages.
    await page.goto(route + '#world-overview');
    await active('overview');
    await select('spawns');
    await select('events');
    await page.goBack();
    await active('spawns');
    await page.goForward();
    await active('events');
    for (const [hash, key] of [['#world-activity', 'activity'], ['#world-delete', 'management'],
      ['#extra-event-settings', 'events']]) {
      await page.goto(route + hash);
      await active(key);
      await page.reload();
      await active(key);
      if (hash !== '#world-activity') assert(await page.locator(hash).isVisible(), hash + ' target is visible');
    }

    const formats = [['en', 1440, 1000], ['de', 390, 844], ['fr', 320, 700], ['en', 844, 390], ['de', 568, 320]];
    for (const [locale, width, height] of formats) {
      await page.context().addCookies([{name: 'conquer_locale', value: locale, url: base}]);
      await page.setViewportSize({width, height});
      await page.goto(route);
      for (const key of keys) {
        await select(key);
        if (key === 'spawns') {
          const details = workspace.locator('[data-world-panel="spawns"] details').first();
          if (!await details.evaluate(element => element.open)) await details.locator('summary').first().click();
        }
        await fit(`${locale} ${width}x${height} ${key}`);
        await workspace.locator(`[data-world-panel="${key}"]`).first().evaluate(element =>
          element.scrollIntoView({block: 'start', behavior: 'instant'}));
        await page.screenshot({path: path.join(out, `${key}-${locale}-${width}x${height}.png`)});
      }
    }
    assert.deepEqual(posts, ['/admin/action/world-save'], 'Section navigation and reloads never trigger writes');

    // Build the other map profile through the normal admin form in this disposable fixture.
    await page.goto(base + '/admin/world-create');
    const create = page.locator('form[action$="/world-create"]');
    await create.locator('[name=name]').fill('Fixture Luxembourg Workspace');
    await create.locator('[name=slug]').fill('workspace-lux');
    await create.locator('[name=map_profile]').selectOption('luxembourg');
    await create.locator('[name=status]').selectOption('paused');
    await create.locator('[name=reason]').fill('Isolated Luxembourg workspace fixture');
    await Promise.all([page.waitForNavigation(), create.locator('button[type=submit]').click()]);
    assert.equal(await page.locator('.notice.error').count(), 0, 'Disposable Luxembourg world is created');
    const luxWorld = Number(new URL(page.url()).searchParams.get('world_id'));
    assert(luxWorld > 1, 'The synthetic Luxembourg world is separate from the original fixture');
    const luxRoute = base + '/admin/world?world_id=' + luxWorld;
    await select('territories');
    assert.equal(await workspace.locator('[data-world-panel="territories"]:visible').count(), 2,
      'Alliance radii and Luxembourg conquest rules appear together');
    const territoryForm = page.locator('form[action$="/world-territory-rules"]');
    assert(await territoryForm.isVisible(), 'Luxembourg conquest rules remain editable');
    assert.equal(await territoryForm.locator('form').count(), 0, 'Territory rules are not nested inside another form');
    assert.equal(await field('settings[alliance_center_radius]').evaluate(element =>
      element.form.action.endsWith('/world-save')), true, 'Alliance radii keep their original save action');
    assert.equal(await territoryForm.locator('[name="rules[income_per_hour]"]').evaluate(element =>
      element.form.action.endsWith('/world-territory-rules')), true, 'Conquest rules keep their independent save action');
    const luxSettings = await form.evaluate(element => [...new FormData(element).entries()]
      .filter(([name]) => !['csrf_token', 'operation_id', 'reason'].includes(name)));
    const version = Number(await territoryForm.locator('[name=version]').inputValue());
    await territoryForm.locator('[name="rules[income_per_hour]"]').fill('321');
    await territoryForm.locator('[name=reason]').fill('Isolated conquest rules workspace save');
    await Promise.all([page.waitForNavigation(), territoryForm.locator('button[type=submit]').click()]);
    assert.equal(await page.locator('.notice.error').count(), 0, 'Independent conquest rules save succeeds');
    await page.reload();
    await active('territories');
    assert.equal(await territoryForm.locator('[name="rules[income_per_hour]"]').inputValue(), '321');
    assert.equal(Number(await territoryForm.locator('[name=version]').inputValue()), version + 1,
      'Independent conquest rules save creates one revision');
    assert.deepEqual(await form.evaluate(element => [...new FormData(element).entries()]
      .filter(([name]) => !['csrf_token', 'operation_id', 'reason'].includes(name))), luxSettings,
    'Saving conquest rules preserves all general, spawn and alliance settings');
    for (const [locale, width, height] of [['en', 1440, 1000], ['de', 390, 844], ['fr', 844, 390]]) {
      await page.context().addCookies([{name: 'conquer_locale', value: locale, url: base}]);
      await page.setViewportSize({width, height});
      await page.goto(luxRoute + '#world-territories');
      await active('territories');
      await fit(`Luxembourg ${locale} ${width}x${height}`);
      await territoryForm.evaluate(element => element.scrollIntoView({block: 'start', behavior: 'instant'}));
      await page.screenshot({path: path.join(out, `luxembourg-territories-${locale}-${width}x${height}.png`)});
      await select('events');
      assert.equal(await page.locator('form[action$="/world-events"]').count(), 0,
        'Luxembourg does not receive the legacy conquest event form');
      await select('settings');
      assert.equal(await territoryForm.isVisible(), false, 'Conquest rules hide when another section is selected');
    }
    assert.deepEqual(posts, ['/admin/action/world-save', '/admin/action/world-create', '/admin/action/world-territory-rules'],
      'Only the three explicit isolated fixture actions write data');

    await page.context().addCookies([{name: 'conquer_locale', value: 'de', url: base}]);
    for (const [name, width, height] of [['desktop', 1440, 1000], ['mobile', 390, 844]]) {
      await page.setViewportSize({width, height});
      await page.goto(luxRoute + '#world-overview');
      await active('overview');
      await fit('Final German overview ' + name);
      await page.evaluate(() => window.scrollTo({top: 0, left: 0, behavior: 'instant'}));
      await page.screenshot({path: path.join(out, `preview-${name}.png`), fullPage: true});
    }

    const browser = page.context().browser();
    const plainContext = await browser.newContext({
      storageState: await page.context().storageState(), javaScriptEnabled: false, viewport: {width: 390, height: 844}
    });
    try {
      const plainPage = await plainContext.newPage();
      await plainPage.goto(luxRoute + '#world-territories');
      assert.equal(await plainPage.locator('[data-world-link]').count(), 8);
      assert.equal(await plainPage.locator('[data-world-panel]:visible').count(), 9,
        'Every section remains available when JavaScript is unavailable');
      assert.equal(await plainPage.locator('form[action$="/world-save"] [name=name]').inputValue(), 'Fixture Luxembourg Workspace');
      assert.equal(await plainPage.locator('form[action$="/world-territory-rules"] [name="rules[income_per_hour]"]').inputValue(), '321');
      assert(await plainPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
        'The progressive fallback fits a narrow viewport');
      await plainPage.locator('[data-world-link="gifts"]').click();
      assert.equal(new URL(plainPage.url()).hash, '#world-gifts', 'Fallback section links remain native anchors');
    } finally {
      await plainContext.close();
    }

    const moderatorContext = await browser.newContext({viewport: {width: 390, height: 844}});
    try {
      const moderatorPage = await moderatorContext.newPage();
      moderatorPage.on('pageerror', error => errors.push(error.message));
      await moderatorContext.addCookies([{name: 'conquer_locale', value: 'en', url: base}]);
      await moderatorPage.goto(base + '/admin/login');
      await moderatorPage.locator('[name=identifier], [name=username]').fill('RewardModerator');
      await moderatorPage.locator('[name=password]').fill('Fixture-Reward-123!');
      await Promise.all([moderatorPage.waitForURL(base + '/admin'), moderatorPage.locator('button[type=submit]').click()]);
      await moderatorPage.goto(luxRoute + '#world-settings');
      assert(await moderatorPage.locator('form[action$="/world-save"] [name=name]').isDisabled(),
        'Moderator access stays read-only in general settings');
      await moderatorPage.locator('[data-world-link="territories"]').click();
      assert(await moderatorPage.locator('form[action$="/world-territory-rules"]').isVisible(),
        'Moderators can navigate to and inspect Luxembourg rules');
      assert(await moderatorPage.locator('form[action$="/world-territory-rules"] button[type=submit]').isDisabled(),
        'Moderator access stays read-only in the independent rules form');
      await moderatorPage.locator('[data-world-link="events"]').click();
      assert(await moderatorPage.locator('#extra-event-settings').isVisible(), 'Read-only event navigation remains usable');
    } finally {
      await moderatorContext.close();
    }
    assert.deepEqual(invalidControlErrors, [], 'No unfocusable hidden validation controls');
    assert.deepEqual(errors, [], 'No browser errors');
    console.log('WORLD WORKSPACE PASSED: eight sections, retained edits, complete isolated save, hidden-field validation, history, deep links, both map profiles, independent territory save, no-JS fallback, moderator access, three languages and five viewport sizes. ' + out);
  } finally {
    page.off('console', onConsole);
    page.off('request', onRequest);
  }
};
