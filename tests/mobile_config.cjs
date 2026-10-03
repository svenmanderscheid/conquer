'use strict';
const assert = require('node:assert/strict');
const { test } = require('node:test');
const { createConfig } = require('../mobile/scripts/configure.cjs');

test('prototype preserves the game origin and subdirectory without broad navigation permissions', () => {
  const config = createConfig('https://test.example/conquer');
  assert.equal(config.server.url, 'https://test.example/conquer/');
  assert.equal(config.server.cleartext, false);
  assert.equal(config.android.allowMixedContent, false);
  assert.equal(config.server.allowNavigation, undefined);
  assert.deepEqual(config.includePlugins, ['@capacitor/app']);
  assert.equal(config.plugins.App.disableBackButtonHandler, false);
  assert.equal(config.appName, 'Union of Kingdoms');
});

test('configuration rejects insecure or credential-bearing game URLs', () => {
  for (const url of ['http://test.example/', 'file:///game', 'javascript:alert(1)', 'https://u:p@test.example/', 'https://test.example/?token=secret', 'https://test.example/#token', 'not a URL', undefined]) {
    assert.throws(() => createConfig(url));
  }
});
