'use strict';
// Export the approved painted master; no browser or external service is needed.
const fs = require('node:fs');
const path = require('node:path');
const sharp = require('sharp');
const root = path.resolve(__dirname, '..');
const iconDir = path.join(root, 'assets', 'icons');
const source = fs.readFileSync(path.join(iconDir, 'union-of-kingdoms-painted-master.png'));
const faviconSource = fs.readFileSync(path.join(iconDir, 'union-of-kingdoms-kingdom-favicon-master.png'));
// Clean-white brand surface; preserve the approved painted motif when exporting.
const background = '#ffffff';
async function png(input, size, opaque = false) {
  let image = sharp(input).resize(size, size);
  if (opaque) image = image.flatten({ background });
  return image.png({ compressionLevel: 9 }).toBuffer();
}
(async () => {
  const master = await sharp(source).metadata();
  if (!master.width || master.width !== master.height) throw new Error('The approved icon master must be square.');
  const faviconMaster = await sharp(faviconSource).metadata();
  if (!faviconMaster.hasAlpha || faviconMaster.width !== faviconMaster.height) throw new Error('The favicon master must be square with transparency.');
  const sizes = [16, 32, 48, 192, 512];
  const images = new Map();
  for (const size of sizes) images.set(size, await png(size <= 48 ? faviconSource : source, size));
  for (const size of [16, 32, 192, 512]) fs.writeFileSync(path.join(iconDir, `conquer-${size}.png`), images.get(size));
  fs.writeFileSync(path.join(root, 'apple-touch-icon.png'), await png(source, 180, true));
  // The Kingdom master has white padding; this inset keeps its painted silhouette
  // inside the central 80%-diameter safe circle of maskable launcher icons.
  const insetSize = Math.floor(master.width * 0.84);
  const inset = await sharp(source).resize(insetSize, insetSize).png().toBuffer();
  const maskableMaster = await sharp({ create: { width: master.width, height: master.height, channels: 3, background } })
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
  console.log('Exported Kingdom app 192/512, Apple 180, Android launcher, maskable 512, and simplified transparent favicon 16/32/48.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
