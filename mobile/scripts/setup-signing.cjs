'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { randomBytes } = require('node:crypto');
const { spawnSync } = require('node:child_process');
const mobile = path.resolve(__dirname, '..');
const signingDir = path.join(mobile, '.signing');
const keystore = path.join(signingDir, 'upload.jks');
const properties = path.join(mobile, 'android/key.properties');
const certificate = path.join(signingDir, 'upload-certificate.pem');

function run(command, args, env = process.env) {
  const result = spawnSync(command, args, { env, encoding:'utf8', windowsHide:true, timeout:60000 });
  if (result.error || result.status !== 0) {
    throw new Error(result.error?.message || result.stderr || result.stdout || command + ' failed');
  }
}

try {
  if (fs.existsSync(keystore) || fs.existsSync(properties)) {
    throw new Error('Upload signing already exists. Preserve the existing key; this command never replaces it.');
  }
  fs.mkdirSync(signingDir, { recursive:true, mode:0o700 });
  if (process.platform === 'win32') {
    const owner = process.env.USERDOMAIN + '\\' + process.env.USERNAME;
    run('icacls.exe', [signingDir, '/inheritance:r', '/grant:r', owner + ':(OI)(CI)F', '*S-1-5-18:(OI)(CI)F']);
  }
  const password = randomBytes(32).toString('hex');
  const env = { ...process.env, UOK_UPLOAD_PASSWORD:password };
  const keytool = process.env.JAVA_HOME
    ? path.join(process.env.JAVA_HOME, 'bin', process.platform === 'win32' ? 'keytool.exe' : 'keytool')
    : 'keytool';
  run(keytool, ['-genkeypair', '-noprompt', '-keystore', keystore, '-storetype', 'JKS',
    '-alias', 'upload', '-keyalg', 'RSA', '-keysize', '4096', '-validity', '10000',
    '-dname', 'CN=Union of Kingdoms, OU=Android Upload, O=Union of Kingdoms',
    '-storepass:env', 'UOK_UPLOAD_PASSWORD', '-keypass:env', 'UOK_UPLOAD_PASSWORD'], env);
  const content = '# Private upload signing. Back up together with mobile/.signing/upload.jks.\n' +
    'storeFile=../.signing/upload.jks\nstorePassword=' + password + '\nkeyAlias=upload\nkeyPassword=' + password + '\n';
  fs.writeFileSync(properties, content, { flag:'wx', mode:0o600 });
  if (process.platform === 'win32') {
    const owner = process.env.USERDOMAIN + '\\' + process.env.USERNAME;
    run('icacls.exe', [properties, '/inheritance:r', '/grant:r', owner + ':F', '*S-1-5-18:F']);
  }
  run(keytool, ['-exportcert', '-rfc', '-keystore', keystore, '-alias', 'upload',
    '-storepass:env', 'UOK_UPLOAD_PASSWORD', '-file', certificate], env);
  console.log('Created private RSA-4096 upload signing and a public PEM certificate.');
  console.log('Back up mobile/.signing/ and mobile/android/key.properties securely. Passwords were not printed.');
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}
