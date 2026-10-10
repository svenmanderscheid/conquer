'use strict';
const fs = require('node:fs');
const path = require('node:path');
const mobile = path.resolve(__dirname, '..');

function createConfig(gameUrl) {
  const url = new URL(gameUrl);
  if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash) {
    throw new Error('gameUrl must be an HTTPS game entry without credentials, query or fragment.');
  }
  if (!url.pathname.endsWith('/')) url.pathname += '/';
  return {
    appId: 'com.unionofkingdoms.app',
    appName: 'Union of Kingdoms',
    webDir: 'www',
    backgroundColor: '#fff7e7',
    loggingBehavior: 'debug',
    // Remote PHP pages preserve the current same-origin login for device tests.
    // This setting is deliberately not a production distribution architecture.
    server: { url: url.href, cleartext: false, errorPath: 'index.html' },
    android: { allowMixedContent: false },
    // The official App plugin routes Android Back through WebView history.
    includePlugins: ['@capacitor/app', '@capacitor/push-notifications'],
    plugins: {
      App: { disableBackButtonHandler: false },
      PushNotifications: { presentationOptions: ['alert', 'sound'] }
    }
  };
}

function configure() {
  const local = path.join(mobile, 'mobile.local.json');
  const settings = JSON.parse(fs.readFileSync(fs.existsSync(local) ? local : path.join(mobile, 'mobile.config.example.json'), 'utf8'));
  const config = createConfig(process.env.UOK_GAME_URL || settings.gameUrl);
  fs.writeFileSync(path.join(mobile, 'capacitor.config.json'), JSON.stringify(config, null, 2) + '\n');
  console.log(`Android prototype configured for ${config.server.url}`);
  return config;
}

if (require.main === module) {
  try { configure(); } catch (error) { console.error(error.message); process.exitCode = 1; }
}
module.exports = { createConfig, configure };
