'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');
const { spawnSync } = require('node:child_process');
const { requireFirebaseConfig } = require('./push-config.cjs');
const mobile = path.resolve(__dirname, '..');
const android = path.join(mobile, 'android');
const destination = path.resolve(mobile, '../artifacts/android');

function run(command, args, cwd) {
  const result = spawnSync(command, args, { cwd, stdio:'inherit', windowsHide:true });
  if (result.error || result.status !== 0) throw new Error(result.error?.message || command + ' failed with exit code ' + result.status);
}

try {
  requireFirebaseConfig(android, 'release');
  if (!fs.existsSync(path.join(android, 'key.properties'))) {
    throw new Error('Upload signing is missing. For a new app run pnpm signing:init; for an existing app restore its original upload key.');
  }
  // Reject a local HTTP test override before creating a distribution artifact.
  const config = JSON.parse(fs.readFileSync(path.join(mobile, 'capacitor.config.json'), 'utf8'));
  const url = new URL(config.server?.url);
  if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash ||
      config.server.cleartext !== false || config.android?.allowMixedContent !== false ||
      config.appId !== 'com.unionofkingdoms.app' || config.android?.webContentsDebuggingEnabled === true ||
      !['debug', 'none'].includes(config.loggingBehavior)) {
    throw new Error('Release requires the HTTPS game entry, the expected app ID, and disabled release debugging/logging.');
  }
  run(process.execPath, ['scripts/build-web.cjs'], mobile);
  run(process.execPath, ['node_modules/@capacitor/cli/bin/capacitor', 'sync', 'android'], mobile);
  // gradlew.bat is a fixed repository path, never assembled from user input.
  const gradleArgs = [...(process.argv.includes('--online') ? [] : ['--offline']),
    '--console=plain', 'bundleRelease', 'assembleRelease', 'lintRelease'];
  if (process.platform === 'win32') run('cmd.exe', ['/d', '/c', 'gradlew.bat', ...gradleArgs], android);
  else run(path.join(android, 'gradlew'), gradleArgs, android);
  const metadata = JSON.parse(fs.readFileSync(path.join(android, 'app/build/outputs/apk/release/output-metadata.json'), 'utf8'));
  if (metadata.applicationId !== config.appId || metadata.elements.length !== 1) throw new Error('Unexpected release APK metadata.');
  const { versionCode, versionName, outputFile } = metadata.elements[0];
  fs.mkdirSync(destination, { recursive:true });
  const artifacts = [];
  for (const [extension, source] of [
    ['aab', path.join(android, 'app/build/outputs/bundle/release/app-release.aab')],
    ['apk', path.join(android, 'app/build/outputs/apk/release', outputFile)]
  ]) {
    const filename = 'Union-of-Kingdoms-' + versionName + '-release.' + extension;
    const target = path.join(destination, filename);
    fs.copyFileSync(source, target);
    artifacts.push({ file:filename, bytes:fs.statSync(target).size,
      sha256:createHash('sha256').update(fs.readFileSync(target)).digest('hex') });
  }
  const report = { applicationId:metadata.applicationId, versionCode, versionName, gameUrl:url.href,
    architecture:'Online Capacitor client; game pages and state are served over HTTPS.',
    artifacts, deviceVerification:'Pending; building does not verify device behavior or Play approval.' };
  fs.writeFileSync(path.join(destination, 'Union-of-Kingdoms-' + versionName + '-release.json'), JSON.stringify(report, null, 2) + '\n');
  console.log(JSON.stringify(report, null, 2));
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
