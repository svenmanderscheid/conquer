'use strict';
// Export the approved painted master; no browser or external service is needed.
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
const iconDir = path.join(root, 'assets', 'icons');
const source = fs.readFileSync(path.join(iconDir, 'union-of-kingdoms-painted-master.png'));
// Apricotlicht brand violet; preserve the approved painted motif when exporting.
const background = '#8538bc';
async function png(size, opaque = false) {
  let image = sharp(source).resize(size, size);
  if (opaque) image = image.flatten({ background });
  return image.png({ compressionLevel: 9 }).toBuffer();
}
(async () => {
  const master = await sharp(source).metadata();
  if (!master.width || master.width !== master.height) throw new Error('The approved icon master must be square.');
  const sizes = [16, 32, 48, 192, 512];
  const images = new Map();
  for (const size of sizes) images.set(size, await png(size));
  for (const size of [16, 32, 192, 512]) fs.writeFileSync(path.join(iconDir, `conquer-${size}.png`), images.get(size));
  fs.writeFileSync(path.join(root, 'apple-touch-icon.png'), await png(180, true));
  // Keep the full battle scene inside the safe circle of launcher masks.
  const inset = await sharp(source).resize(700, 700).png().toBuffer();
  const maskableMaster = await sharp({ create: { width: 1254, height: 1254, channels: 3, background } })
    .composite([{ input: inset, gravity: 'centre' }]).png({ compressionLevel: 9 }).toBuffer();
  fs.writeFileSync(path.join(iconDir, 'union-of-kingdoms-painted-maskable-master.png'), maskableMaster);
  const maskable = await sharp(maskableMaster)
    .resize(512, 512).flatten({ background })
    .png({ compressionLevel: 9 }).toBuffer();
  fs.writeFileSync(path.join(iconDir, 'conquer-maskable-512.png'), maskable);
  fs.writeFileSync(path.join(root, 'mobile/android/app/src/main/res/drawable-nodpi/uok_launcher.png'), images.get(512));
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
