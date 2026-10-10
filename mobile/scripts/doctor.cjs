'use strict';
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { inspectFirebaseConfig } = require('./push-config.cjs');
const mobile = path.resolve(__dirname, '..');
let missing = 0;
function check(label, ready, hint = '') {
  console.log(`${ready ? 'OK' : 'MISSING'} ${label}${!ready && hint ? ': ' + hint : ''}`);
  if (!ready) missing++;
}
check('Node.js 22+', Number(process.versions.node.split('.')[0]) >= 22);
check('Capacitor dependencies', fs.existsSync(path.join(mobile, 'node_modules/@capacitor/cli')), 'Run pnpm install --frozen-lockfile in mobile.');
check('Native Push Notifications plugin', fs.existsSync(path.join(mobile, 'node_modules/@capacitor/push-notifications')), 'Run pnpm install --frozen-lockfile in mobile.');
for (const variant of ['debug', 'release']) {
  const firebase = inspectFirebaseConfig(path.join(mobile, 'android'), variant);
  check(`Firebase ${variant} (${firebase.packageName})`, firebase.ready, firebase.reason);
}
check('Game configuration', fs.existsSync(path.join(mobile, 'capacitor.config.json')), 'Run pnpm configure.');
check('Bundled connection screen', fs.existsSync(path.join(mobile, 'www/index.html')), 'Run pnpm web.');
check('Android project', fs.existsSync(path.join(mobile, 'android/gradlew.bat')));
const studioRoots = [
  process.env.CAPACITOR_ANDROID_STUDIO_PATH ? path.resolve(process.env.CAPACITOR_ANDROID_STUDIO_PATH, '../..') : null,
  path.join(process.env.ProgramFiles || 'C:/Program Files', 'Android/Android Studio'),
  path.join(process.env.LOCALAPPDATA || '', 'Programs/Android/android-studio')
].filter(Boolean);
check('Android Studio', studioRoots.some(root => fs.existsSync(path.join(root, 'bin', process.platform === 'win32' ? 'studio64.exe' : 'studio.sh'))), 'Install Android Studio or set CAPACITOR_ANDROID_STUDIO_PATH.');
const userJavaRoot = path.join(process.env.LOCALAPPDATA || '', 'Programs/Java');
const userBuildJdks = fs.existsSync(userJavaRoot) ? fs.readdirSync(userJavaRoot).filter(name => name.startsWith('jdk-21.')).map(name => path.join(userJavaRoot, name)) : [];
const javaHomes = [process.env.JAVA_HOME, ...userBuildJdks, ...studioRoots.map(root => path.join(root, 'jbr'))].filter(Boolean);
const javaName = process.platform === 'win32' ? 'java.exe' : 'java';
const java = javaHomes.map(home => path.join(home, 'bin', javaName)).find(file => fs.existsSync(file)) || javaName;
const javaResult = spawnSync(java, ['-version'], { encoding: 'utf8', windowsHide: true });
const javaVersion = (javaResult.stderr || javaResult.stdout || '').match(/version "(\d+)/);
const javaMajor = Number(javaVersion?.[1] || 0);
check('Build Java 21–24 (Gradle 8.14.3)', javaResult.status === 0 && javaMajor >= 21 && javaMajor <= 24, 'Set JAVA_HOME to JDK 21. New Android Studio versions may bundle a newer, incompatible JDK.');
let configuredSdk = '';
const localProperties = path.join(mobile, 'android/local.properties');
if (fs.existsSync(localProperties)) configuredSdk = (fs.readFileSync(localProperties, 'utf8').match(/^sdk\.dir=(.+)$/m)?.[1] || '').trim().replace(/\\([\\:])/g, '$1');
const sdk = configuredSdk || process.env.ANDROID_HOME || process.env.ANDROID_SDK_ROOT || path.join(process.env.LOCALAPPDATA || '', 'Android/Sdk');
check('Android SDK platform 36', fs.existsSync(path.join(sdk, 'platforms/android-36/android.jar')), 'Install SDK platform 36 in Android Studio.');
check('Android Build-Tools 36', fs.existsSync(path.join(sdk, 'build-tools/36.0.0', process.platform === 'win32' ? 'aapt2.exe' : 'aapt2')), 'Install Android SDK Build-Tools 36.0.0.');
check('Android platform tools', fs.existsSync(path.join(sdk, 'platform-tools', process.platform === 'win32' ? 'adb.exe' : 'adb')), 'Install Android SDK Platform-Tools.');
console.log('This checks prerequisites only; it does not build an APK or certify device behavior.');
process.exitCode = missing ? 1 : 0;
