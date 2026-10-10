'use strict';
const assert = require('node:assert/strict');
const { test } = require('node:test');
const { createConfig } = require('../mobile/scripts/configure.cjs');
const { inspectFirebaseConfig, requireFirebaseConfig } = require('../mobile/scripts/push-config.cjs');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { buildWeb } = require('../mobile/scripts/build-web.cjs');

test('prototype preserves the game origin and subdirectory without broad navigation permissions', () => {
  const config = createConfig('https://test.example/conquer');
  assert.equal(config.backgroundColor, '#fff7e7', 'The native launch surface follows warm-cream branding');
  assert.equal(config.server.url, 'https://test.example/conquer/');
  assert.equal(config.server.cleartext, false);
  assert.equal(config.android.allowMixedContent, false);
  assert.equal(config.server.allowNavigation, undefined);
  assert.deepEqual(config.includePlugins, ['@capacitor/app', '@capacitor/push-notifications']);
  assert.equal(config.plugins.App.disableBackButtonHandler, false);
  assert.deepEqual(config.plugins.PushNotifications.presentationOptions, ['alert', 'sound']);
  assert.equal(config.appName, 'Union of Kingdoms');
});

test('Firebase setup checks the exact Android variant and rejects missing, mismatched or incomplete configuration', () => {
  const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'uok-mobile-push-'));
  const app = path.join(temporaryRoot, 'app');
  fs.mkdirSync(path.join(app, 'src/debug'), {recursive: true});
  fs.mkdirSync(path.join(app, 'src/release'), {recursive: true});
  const client = packageName => ({client_info: {mobilesdk_app_id: '1:123456:android:abcdef', android_client_info: {package_name: packageName}}, api_key: [{current_key: 'synthetic-test-api-key'}]});
  const config = packages => ({project_info: {project_number: '123456', project_id: 'synthetic-push-fixture'}, client: packages.map(client)});
  const write = (target, value) => fs.writeFileSync(target, JSON.stringify(value));
  const common = path.join(app, 'google-services.json');
  const debug = path.join(app, 'src/debug/google-services.json');
  const release = path.join(app, 'src/release/google-services.json');
  try {
    assert.equal(inspectFirebaseConfig(temporaryRoot, 'release').ready, false);
    assert.throws(() => requireFirebaseConfig(temporaryRoot, 'release'), /com\.unionofkingdoms\.app/);
    assert.throws(() => inspectFirebaseConfig(temporaryRoot, '../release'), /Unknown Android build variant/);
    fs.writeFileSync(common, '{broken-json');
    assert.match(inspectFirebaseConfig(temporaryRoot, 'release').reason, /not valid JSON/);
    write(common, config(['com.unionofkingdoms.app']));
    assert.equal(requireFirebaseConfig(temporaryRoot, 'release').path, common);
    assert.equal(inspectFirebaseConfig(temporaryRoot, 'debug').ready, false, 'A release client cannot configure the debug package');
    write(debug, config(['com.unionofkingdoms.app.prototype']));
    assert.equal(requireFirebaseConfig(temporaryRoot, 'debug').path, debug);
    write(release, config(['com.other.app']));
    assert.equal(inspectFirebaseConfig(temporaryRoot, 'release').ready, false, 'A broken variant override cannot fall back silently');
    write(release, {...config(['com.unionofkingdoms.app']), project_info: {}});
    assert.match(inspectFirebaseConfig(temporaryRoot, 'release').reason, /incomplete/);
    write(release, config(['com.unionofkingdoms.app']));
    assert.equal(requireFirebaseConfig(temporaryRoot, 'release').path, release);
  } finally {
    const resolved = path.resolve(temporaryRoot);
    assert.equal(path.dirname(resolved), path.resolve(os.tmpdir()));
    assert(path.basename(resolved).startsWith('uok-mobile-push-'));
    fs.rmSync(resolved, {recursive: true, force: true});
  }
});

test('configuration rejects insecure or credential-bearing game URLs', () => {
  for (const url of ['http://test.example/', 'file:///game', 'javascript:alert(1)', 'https://u:p@test.example/', 'https://test.example/?token=secret', 'https://test.example/#token', 'not a URL', undefined]) {
    assert.throws(() => createConfig(url));
  }
});


test('connection bundle embeds approved local fonts and replaces historical licenses safely', () => {
  const temporaryRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'uok-mobile-fonts-'));
  const outputDir = path.join(temporaryRoot, 'www');
  const configPath = path.join(temporaryRoot, 'capacitor.config.json');
  const fontDir = path.join(outputDir, 'assets/fonts');
  const legacy = ['OFL-Almendra.txt', 'OFL-Lora.txt', 'almendra-400-latin-ext.woff2', 'almendra-400-latin.woff2', 'almendra-700-latin-ext.woff2', 'almendra-700-latin.woff2', 'lora-latin-ext.woff2', 'lora-latin.woff2'];
  try {
    fs.mkdirSync(fontDir, { recursive:true });
    for (const file of legacy) fs.writeFileSync(path.join(fontDir, file), 'previous generated asset');
    fs.writeFileSync(configPath, JSON.stringify(createConfig('https://test.example/conquer')));
    const bundle = buildWeb({ configPath, outputDir });
    assert.deepEqual(bundle.files, ['index.html', 'assets/fonts/OFL-Bree-Serif.txt', 'assets/fonts/OFL-Nunito.txt']);
    for (const file of legacy) assert.equal(fs.existsSync(path.join(fontDir, file)), false, file + ' was removed from generated output');
    for (const file of bundle.files.slice(1)) assert.deepEqual(fs.readFileSync(path.join(outputDir, file)), fs.readFileSync(path.resolve(__dirname, '..', file)));
    const html = fs.readFileSync(path.join(outputDir, 'index.html'), 'utf8');
    const fonts = html.match(/<style data-source="assets\/css\/fantasy-fonts\.css">([\s\S]*?)<\/style>/)?.[1];
    assert(fonts, 'The local font stylesheet is embedded');
    for (const family of ['Bree Serif', 'Nunito', 'Conquer UI']) assert(fonts.includes('font-family: "' + family + '"'));
    assert.match(fonts, /font-weight:\s*400 800/);
    assert.equal((fonts.match(/data:font\/woff2;base64,/g) || []).length, 6, 'Both Latin subsets are embedded for Bree, Nunito and its compatibility alias');
    assert.doesNotMatch(fonts, /almendra|lora|https?:\/\/|url\(["']?\.\.\//i, 'Fonts have no old-family or network dependency');
    fs.writeFileSync(path.join(outputDir, 'unexpected.php'), '<?php');
    assert.throws(() => buildWeb({ configPath, outputDir }), /Unexpected files/);
    assert.equal(fs.readFileSync(path.join(outputDir, 'unexpected.php'), 'utf8'), '<?php', 'Unknown files are preserved on rejection');
  } finally {
    const resolved = path.resolve(temporaryRoot);
    assert.equal(path.dirname(resolved), path.resolve(os.tmpdir()));
    assert(path.basename(resolved).startsWith('uok-mobile-fonts-'));
    fs.rmSync(resolved, { recursive:true, force:true });
  }
});
