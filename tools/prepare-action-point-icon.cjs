'use strict';

// The legacy AP fallback was a gold-coin crop. Reuse the approved AP potion;
// no new artwork or retouching is required.
const path=require('path');
const sharp=require('C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/sharp');
const root=path.resolve(__dirname,'..');
sharp(path.join(root,'assets/art/items/painted-v2/10104001.webp'))
    .resize(128,128,{fit:'contain',background:{r:0,g:0,b:0,alpha:0}})
    .png({compressionLevel:9})
    .toFile(path.join(root,'assets/art/items/reference/action-points-v2.png'))
    .catch(error=>{console.error(error);process.exitCode=1;});
