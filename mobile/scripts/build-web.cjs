'use strict';

const fs = require('node:fs');
const path = require('node:path');
const { createHash } = require('node:crypto');

const mobileRoot = path.resolve(__dirname, '..');
const projectRoot = path.resolve(mobileRoot, '..');
const legacyFiles = [
  'assets/css/fantasy-fonts.css',
  'assets/css/localization.css',
  'assets/css/village-theme.css',
  'assets/js/localization.js',
  'assets/icons/conquer-192.png',
  'assets/fonts/almendra-400-latin-ext.woff2',
  'assets/fonts/almendra-400-latin.woff2',
  'assets/fonts/almendra-700-latin-ext.woff2',
  'assets/fonts/almendra-700-latin.woff2',
  'assets/fonts/lora-latin-ext.woff2',
  'assets/fonts/lora-latin.woff2',
  'assets/fonts/OFL-Almendra.txt',
  'assets/fonts/OFL-Lora.txt',
  'shell.css', 'bootstrap.js', 'service-worker.js'
];
const fontFiles = [
  'assets/fonts/bree-serif-v18-400-latin-ext.woff2',
  'assets/fonts/bree-serif-v18-400-latin.woff2',
  'assets/fonts/nunito-v32-latin-ext.woff2',
  'assets/fonts/nunito-v32-latin.woff2'
];
const licenseFiles = ['assets/fonts/OFL-Bree-Serif.txt', 'assets/fonts/OFL-Nunito.txt'];
const localeKeys = [
  'locale.label',
  'mobile.connection.title',
  'mobile.connection.description',
  'mobile.connection.retry'
];

function filesIn(directory, prefix = '') {
  if (!fs.existsSync(directory)) return [];
  if (fs.lstatSync(directory).isSymbolicLink()) throw new Error('The web output must not contain symlinks.');
  return fs.readdirSync(directory, { withFileTypes:true }).flatMap(entry => {
    if (entry.isSymbolicLink()) throw new Error('The web output must not contain symlinks.');
    const name = prefix + entry.name;
    return entry.isDirectory() ? filesIn(path.join(directory, entry.name), name + '/') : [name];
  });
}

function buildWeb({ configPath = path.join(mobileRoot, 'capacitor.config.json'), outputDir = path.join(mobileRoot, 'www') } = {}) {
  const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
  const gameUrl = new URL(config.server?.url);
  if (!['https:', 'http:'].includes(gameUrl.protocol) || gameUrl.username || gameUrl.password) {
    throw new Error('A configured HTTP(S) game URL without credentials is required.');
  }
  if (gameUrl.protocol !== 'https:' && config.server?.cleartext !== true) {
    throw new Error('HTTP game URLs require an explicit cleartext development configuration.');
  }
  const allowed = new Set(['index.html', ...licenseFiles]);
  const existing = filesIn(outputDir);
  const unexpected = existing.filter(file => !allowed.has(file) && !legacyFiles.includes(file));
  if (unexpected.length) throw new Error('Unexpected files in the web bundle; inspect them before building: ' + unexpected.join(', '));

  const catalogs = {};
  for (const language of ['en', 'de', 'fr']) {
    const full = JSON.parse(fs.readFileSync(path.join(projectRoot, 'data/i18n', language + '.json'), 'utf8'));
    catalogs[language] = Object.fromEntries(localeKeys.map(key => {
      if (typeof full[key] !== 'string' || !full[key]) throw new Error('Missing translation: ' + language + ':' + key);
      return [key, full[key]];
    }));
  }
  const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' })[character]);
  // Capacitor's remote-server errorPath only guarantees this single local file.
  // Inline every dependency so the screen works without the localhost asset proxy.
  const read = file => fs.readFileSync(path.join(projectRoot, file), 'utf8').replace(/\r\n?/g, '\n');
  const dataUrl = (file, type) => 'data:' + type + ';base64,' + fs.readFileSync(path.join(projectRoot, file)).toString('base64');
  const fonts = read('assets/css/fantasy-fonts.css').replace(/url\(["']?\.\.\/fonts\/([^"')]+)["']?\)/g, (_, name) => {
    const file = 'assets/fonts/' + name;
    if (!fontFiles.includes(file)) throw new Error('Unrecognised bundled font: ' + name);
    return 'url("' + dataUrl(file, 'font/woff2') + '")';
  });
  // The talent-header illustration never appears in this connection screen.
  // Omit that unrelated 2 MiB background while keeping the shared theme last.
  const theme = read('assets/css/village-theme.css').replace(/url\(['"]\.\.\/art\/village2\.png['"]\)/g, 'none');
  const styles = [
    { source:'assets/css/fantasy-fonts.css', content:fonts },
    { source:'assets/css/localization.css', content:read('assets/css/localization.css') },
    { source:'mobile/web/shell.css', content:read('mobile/web/shell.css') },
    { source:'assets/css/village-theme.css', content:theme }
  ];
  if (styles.some(style => /url\(\s*(?!["']?data:)/.test(style.content.replace(/url\(["']?data:[^)]*\)/g, '')) || /@import|<\/style/i.test(style.content))) {
    throw new Error('Fallback styles must not request external assets or imports.');
  }
  const bootstrap = 'window.CONQUER_BASE="";\nwindow.CONQUER_I18N=' + JSON.stringify({ locale:'en', registerServiceWorker:false, catalogs }).replace(/</g, '\\u003c') + ';\n';
  const scripts = [bootstrap, read('assets/js/localization.js')].map(script => script.replace(/<\/script/gi, '<\\/script'));
  const hash = value => "'sha256-" + createHash('sha256').update(value).digest('base64') + "'";
  const csp = "default-src 'none'; script-src " + scripts.map(hash).join(' ') + '; style-src ' + styles.map(style => hash(style.content)).join(' ') + "; img-src data:; font-src data:; connect-src 'none'; worker-src 'none'; base-uri 'none'; object-src 'none'; form-action 'none'";
  const values = { ...catalogs.en, gameUrl:gameUrl.href, csp, icon:dataUrl('assets/icons/conquer-192.png', 'image/png') };
  const raw = {
    styles:styles.map(style => '<style data-source="' + style.source + '">' + style.content + '</style>').join('\n'),
    scripts:scripts.map(script => '<script>' + script + '</script>').join('\n')
  };
  const html = fs.readFileSync(path.join(mobileRoot, 'web/index.html'), 'utf8').replace(/\{\{([^}]+)\}\}/g, (_, key) => {
    if (Object.hasOwn(raw, key)) return raw[key];
    if (!Object.hasOwn(values, key)) throw new Error('Unknown template value: ' + key);
    return escapeHtml(values[key]);
  });
  fs.mkdirSync(outputDir, { recursive:true });
  for (const file of licenseFiles) {
    const destination = path.join(outputDir, file);
    fs.mkdirSync(path.dirname(destination), { recursive:true });
    fs.copyFileSync(path.join(projectRoot, file), destination);
  }
  fs.writeFileSync(path.join(outputDir, 'index.html'), html);
  // Remove only known generated files from the previous multi-file bundle.
  for (const file of existing) if (!allowed.has(file)) fs.unlinkSync(path.join(outputDir, file));
  return { outputDir:path.resolve(outputDir), files:[...allowed], gameUrl:gameUrl.href };
}

if (require.main === module) {
  try {
    const result = buildWeb();
    console.log('Built ' + result.files.length + ' public fallback files for ' + result.gameUrl);
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}

module.exports = { buildWeb };
