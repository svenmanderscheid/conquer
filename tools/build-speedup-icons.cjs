'use strict';
// Original vector artwork: shared rounded arrows and readable activity emblems.
const fs=require('node:fs'),path=require('node:path');
function buildSpeedups(){
 const out=path.join(__dirname,'../assets/art/items/backpack');fs.mkdirSync(out,{recursive:true});
 const arrow='M13 23H35Q38 23 40 26L68 59Q71 63 68 67L40 101Q38 104 35 104H13Q9 104 11 100L39 66Q41 63 39 60L11 27Q8 23 13 23Z';
 const defs=`<defs>
  <linearGradient id="face" x1="0" y1="0" x2=".35" y2="1"><stop stop-color="#c2f4fa"/><stop offset=".45" stop-color="#7ad9f2"/><stop offset="1" stop-color="#37a2d6"/></linearGradient>
  <linearGradient id="depth" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#317dac"/><stop offset="1" stop-color="#24559c"/></linearGradient>
  <linearGradient id="steel" x1="0" y1="0" x2=".8" y2="1"><stop stop-color="#f2f5e9"/><stop offset=".5" stop-color="#c3d6d8"/><stop offset="1" stop-color="#7e9fac"/></linearGradient>
  <g id="arrow" stroke="#493b33" stroke-width="3.2" stroke-linejoin="round" stroke-linecap="round">
   <path d="${arrow}" transform="translate(0 5)" fill="url(#depth)"/>
   <path d="${arrow}" fill="url(#face)"/>
   <path d="M16 27H34L63 61H47Z" fill="#e6fbf5" stroke="none"/>
   <path d="M46 67H64L36 99H16Z" fill="#2994c8" stroke="none"/>
   <path d="m16 29 17 0 25 29" fill="none" stroke="#fffdf0" stroke-width="2.8"/>
   <path d="m17 100 17 0 29-33" fill="none" stroke="#8ce2ef" stroke-width="2.4"/>
   <path d="M46 62h16" stroke="#e1f8f1" stroke-width="2"/>
  </g>
 </defs>`;
 const emblems={
  building:{name:'Bauen',color:'#f4dcaa',art:`
   <path d="m20 39 15-22 6 4-15 22q-3 5-7 1Z" fill="#b8793e"/>
   <path d="m23 39 12-17" stroke="#f8d391" stroke-width="2.6"/>
   <path d="m15 12 8-8q2-2 5 0l20 14q3 2 1 5l-6 8q-2 2-5 0L16 17q-3-2-1-5Z" fill="url(#steel)"/>
   <path d="m19 11 6-4 18 12-6 4Z" fill="#f6faf0" stroke="none"/>
   <path d="m39 24 6-4 1 3-5 6Z" fill="#7096aa" stroke="none"/>`},
  training:{name:'Ausbildung',color:'#d7e7ee',art:`
   <path d="M12 29V22C12 9 19 4 29 4s18 7 18 20v12l-8 8-8-4-9 4-10-9Z" fill="url(#steel)"/>
   <path d="M29 7v16l15 3v-4C43 12 37 7 29 7Z" fill="#8dafbb" stroke="none"/>
   <path d="M16 22 29 26 42 22v11l-10 4-3-6-3 6-10-5Z" fill="#493b33"/>
   <path d="M27 24h5v17l-3 2-2-2Z" fill="#e4af38" stroke-width="1.8"/>
   <path d="M17 18C18 11 22 8 27 8" fill="none" stroke="#fffcef" stroke-width="3"/>
   <path d="m15 36 8 5m13-2 7-5" fill="none" stroke="#7396a3" stroke-width="2"/>`},
  research:{name:'Forschung',color:'#e5d5ed',art:`
   <path d="m5 11 19 3 5 4 6-4 17-3v31l-20 3-3 3-5-3-19-3Z" fill="#7758a1"/>
   <path d="M7 7c9-1 15 2 22 7 8-6 14-8 22-7v29c-10-1-15 2-22 5-7-4-13-5-22-5Z" fill="#fff2d3"/>
   <path d="M29 14v27c7-3 12-6 22-5V7c-9 0-14 3-22 7Z" fill="#ead8b2" stroke="none"/>
   <path d="M29 14v25M12 16l10 3m-10 4 10 3m13-7 10-4m-10 11 10-4" stroke="#ba9764" stroke-width="2" fill="none"/>
   <path d="m37 33 6-3v15l-3-3-3 5Z" fill="#9a66bf" stroke-width="1.8"/>`},
  healing:{name:'Heilung',color:'#deebc9',art:`
   <path d="M23 5h12q2 0 2 2v10h10q2 0 2 2v12q0 2-2 2H37v10q0 2-2 2H23q-2 0-2-2V33H11q-2 0-2-2V19q0-2 2-2h10V7q0-2 2-2Z" fill="#7abd53"/>
   <path d="M26 9h7v12h12v8H33v12h-7V29H13v-8h13Z" fill="#a3dc70" stroke="none"/>
   <path d="M26 10v12H14" fill="none" stroke="#eef9c7" stroke-width="3"/>
   <path d="M45 31H35v11" fill="none" stroke="#549447" stroke-width="2"/>`},
 };
 for(const kind of ['generic',...Object.keys(emblems)]){
  const emblem=emblems[kind];
  const arrows=`<g${emblem?' transform="translate(9 -3) scale(.94)"':''}><use href="#arrow" x="49"/><use href="#arrow"/></g>`;
  const badge=emblem?`<g transform="translate(1 70)" stroke="#493b33" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round">
   <rect x="1" y="4" width="57" height="50" rx="13" fill="#70472f" opacity=".18" stroke="none"/>
   <rect x="1" y="0" width="57" height="50" rx="13" fill="${emblem.color}"/>
   <path d="M8 14q0-8 9-8h22" stroke="#fffaf0" stroke-width="2.5" fill="none"/>
   <g transform="translate(0 1) scale(.98)">${emblem.art}</g>
  </g>`:'';
  fs.writeFileSync(path.join(out,kind==='generic'?'speedup.svg':`speedup-${kind}.svg`),`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><title>Beschleuniger${emblem?' · '+emblem.name:' · Universell'}</title>${defs}${arrows}${badge}</svg>\n`);
 }
 console.log('Wrote 5 illustrated speedup icons.');
}
module.exports=buildSpeedups;
if(require.main===module)buildSpeedups();
