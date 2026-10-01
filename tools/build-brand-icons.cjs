'use strict';
// Export the repository's SVG emblem; no external artwork or browser is needed.
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
const iconDir = path.join(root, 'assets', 'icons');
const source = fs.readFileSync(path.join(iconDir, 'conquer.svg'), 'utf8');
async function png(svg, size, opaque = false) {
  let image = sharp(Buffer.from(svg), { density: 384 }).resize(size, size);
  if (opaque) image = image.flatten({ background: '#5c4270' });
  return image.png().toBuffer();
}
(async () => {
  const sizes = [16, 32, 48, 192, 512];
  const images = new Map();
  for (const size of sizes) images.set(size, await png(source, size));
  for (const size of [16, 32, 192, 512]) fs.writeFileSync(path.join(iconDir, `conquer-${size}.png`), images.get(size));
  fs.writeFileSync(path.join(root, 'apple-touch-icon.png'), await png(source, 180, true));
  fs.writeFileSync(path.join(iconDir, 'conquer-maskable-512.png'), await png(source.replace('rx="92"', 'rx="0"'), 512, true));
  // ICO supports PNG frames; keep actual 16/32/48-pixel renditions for small tabs.
  const frames = [16, 32, 48];
  const header = Buffer.alloc(6 + frames.length * 16);
  header.writeUInt16LE(1, 2);
  header.writeUInt16LE(frames.length, 4);
  let offset = header.length;
  frames.forEach((size, index) => {
    const pos = 6 + index * 16;
    header[pos] = size; header[pos + 1] = size;
    header.writeUInt16LE(1, pos + 4); header.writeUInt16LE(32, pos + 6);
    header.writeUInt32LE(images.get(size).length, pos + 8);
    header.writeUInt32LE(offset, pos + 12);
    offset += images.get(size).length;
  });
  fs.writeFileSync(path.join(root, 'favicon.ico'), Buffer.concat([header, ...frames.map(size => images.get(size))]));
  console.log('Exported favicon 16/32/48, Apple 180, app 192/512, and maskable 512.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
