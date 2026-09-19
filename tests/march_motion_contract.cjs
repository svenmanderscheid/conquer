'use strict';
const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert/strict');
const root=path.resolve(__dirname,'..'),sandbox={window:{}};vm.createContext(sandbox);
vm.runInContext(fs.readFileSync(path.join(root,'assets/js/march-skins.js'),'utf8'),sandbox);
const catalog=sandbox.window.ConquerMarchSkins,articulated=[
 'ironkeep','rosehall','sandspire','tidewatch','winterhold','jadecourt','emberforge','ravenloft',
 'clockwork','sapphire','astral','leviathan','yggdrasil','tempest','eclipse'
];
assert.equal(catalog.hasMotion('default'),false);
assert.equal(catalog.motionImage('/conquer','default'),catalog.image('/conquer','default'));
for(const id of articulated){
 assert.equal(catalog.hasMotion(id),true,`${id}: catalog must advertise articulated motion`);
 assert.equal(catalog.hasFlightLayout(id),false,`${id}: ordinary creature must keep the normal map footprint`);
 assert.equal(catalog.motionImage('/conquer',id),`/conquer/assets/art/marches/animated-march-${id}.webp?v=1`,`${id}: motion path contract changed`);
 assert.equal(catalog.image('/conquer',id),`/conquer/assets/art/marches/march-${id}.webp?v=3`,`${id}: static fallback contract changed`);
}
for(const id of ['phoenix','dragon']){
 assert.equal(catalog.hasMotion(id),true,`${id}: legacy articulated asset lost motion support`);
 assert.equal(catalog.hasFlightLayout(id),true,`${id}: legacy flight layout changed`);
 assert.equal(catalog.motionImage('',id),`/assets/art/marches/flight-${id}.webp?v=2`);
 assert.equal(catalog.image('',id),`/assets/art/marches/flight-${id}.png?v=2`);
}
assert.equal(catalog.hasMotion('unknown'),false,'unknown snapshots must not inherit premium motion');
assert.equal(catalog.hasFlightLayout('unknown'),false,'unknown snapshots must not inherit flight layout');
console.log(`PASS ${articulated.length} articulated WebP paths, static fallbacks and legacy phoenix/dragon media contracts.`);
