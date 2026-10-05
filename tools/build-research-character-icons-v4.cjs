'use strict';

const fs = require('fs');
const path = require('path');
const sharp = require('C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/sharp');
const root = path.resolve(__dirname, '..');
const referenceDir = path.join(root, 'assets/art/research/references');
const motifDir = path.join(root, 'assets/art/research/nodes-v3/motifs');
// Use a new public directory whenever the approved artwork changes so an
// installed PWA cannot keep serving older files under identical URLs.
const outputDir = path.join(root, 'assets/art/research/characters-v10');
const troopDir = path.join(root, 'assets/art/characters/fantasy-troops-v3');
const baseDir = path.join(outputDir, 'bases');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'assets/art/research/nodes-v3/manifest.json'), 'utf8'));
const SIZE = 256;
const TRANSPARENT = {r: 0, g: 0, b: 0, alpha: 0};
const unitSheet = path.join(referenceDir, 'research-unit-progressions.png');
const approvedSheet = path.join(referenceDir, 'research-character-style-approved.png');
const paintedSceneSources = {
    food: 'economy-food-approved.png', wood: 'economy-wood-approved.png', stone: 'economy-stone-approved.png',
    crystal: 'economy-crystal-approved.png', army: 'general-army-approved.png', hospital: 'general-hospital-approved.png',
    research: 'general-research-approved.png', construction: 'general-construction-approved.png', protection: 'general-protection-approved.png'
};
const unlocks = [
    ['warrior', 'knight', 'guardian', 'crusader'],
    ['longbow_man', 'ranger', 'crossbow_man', 'sniper'],
    ['horseman', 'heavy_cavalry', 'iron_cavalry', 'dragoon']
];

fs.mkdirSync(baseDir, {recursive: true});
// Counter artwork represents the unit receiving the bonus, not its opponent.
const familyOf = value => { const code = value.split('_against_')[0]; return /infantry|infantrys|warrior|knight|guardian|crusader/.test(code) ? 'infantry'
    : /ranged|archer|archers|longbow|ranger|crossbow|sniper/.test(code) ? 'ranged'
        : /cavalry|cavalrys|horseman|dragoon/.test(code) ? 'cavalry' : 'general'; };
const effectOf = code => /(?:^|_)hp(?:_|$)/.test(code) ? 'hp'
    : /(?:^|_)def(?:_|$)/.test(code) ? 'def'
        : /(?:^|_)atk(?:_|$)/.test(code) ? 'atk'
            : /(?:^|_)spd(?:_|$)/.test(code) || code === 'troop_speed_when_participating_a_rally' ? 'spd' : null;

async function cropGrid(source, columns, rows, column, row, target = 244, trimBackground = TRANSPARENT) {
    const meta = await sharp(source).metadata();
    const left = Math.round(meta.width * column / columns);
    const top = Math.round(meta.height * row / rows);
    const right = Math.round(meta.width * (column + 1) / columns);
    const bottom = Math.round(meta.height * (row + 1) / rows);
    const cell = await sharp(source).extract({left, top, width: right - left, height: bottom - top}).png().toBuffer();
    return sharp(cell).trim({background: trimBackground})
        .resize(target, target, {fit: 'contain', background: TRANSPARENT}).png().toBuffer();
}

function badgeSvg(code) {
    const ink = '#3d241d', paper = '#fff8e9', plum = '#74405f', blue = '#3977b8', red = '#d94b45', green = '#587a43';
    const badges = [];
    if (code.startsWith('advanced_')) badges.push(`<g transform="translate(8 8)"><rect width="58" height="39" rx="18" fill="${plum}" stroke="${ink}" stroke-width="5"/><text x="29" y="28" text-anchor="middle" font-family="Georgia,serif" font-weight="700" font-size="25" fill="${paper}">II</text></g>`);
    if (code.includes('_against_')) badges.push(`<g transform="translate(8 194)"><circle cx="27" cy="27" r="25" fill="${red}" stroke="${ink}" stroke-width="5"/><circle cx="27" cy="27" r="13" fill="none" stroke="${paper}" stroke-width="5"/><path d="M27 5v11M27 38v11M5 27h11M38 27h11" stroke="${paper}" stroke-width="5" stroke-linecap="round"/></g>`);
    if (code.startsWith('castle_defending_')) badges.push(`<g transform="translate(7 190)" fill="${blue}" stroke="${ink}" stroke-width="5" stroke-linejoin="round"><path d="M4 57V20h13V8l9 9 9-9v12h14V8l9 9 9-9v49Z"/><path d="M26 57V39h20v18" fill="${paper}"/></g>`);
    if (code.includes('when_composed_of')) badges.push(`<g transform="translate(8 197)" fill="${green}" stroke="${ink}" stroke-width="4"><circle cx="13" cy="25" r="11"/><circle cx="34" cy="16" r="13"/><circle cx="57" cy="25" r="11"/></g>`);
    if (code.includes('participating_a_rally') || code === 'rally_attack_amount') badges.push(`<g transform="translate(8 187)" stroke="${ink}" stroke-width="5" stroke-linejoin="round"><path d="M12 68V7"/><path d="M14 10h49L51 27l12 17H14Z" fill="${plum}"/></g>`);
    return badges.length ? Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="${SIZE}" height="${SIZE}" viewBox="0 0 ${SIZE} ${SIZE}">${badges.join('')}</svg>`) : null;
}

// Opaque native medallions replace the complete old heart/rays area. Never
// place a translucent new statistic over a baked-in, contradictory HP badge.
function effectBadgeSvg(effect) {
    const ink='#3d241d', paper='#fff8e9';
    const symbols={
        hp:'<path d="M50 75S18 57 18 35c0-18 23-23 32-7 9-16 32-11 32 7 0 22-32 40-32 40Z" fill="#f05a6a"/>',
        def:'<path d="m50 13 29 10v23c0 19-12 32-29 40-17-8-29-21-29-40V23Z" fill="#4d91d1"/><path d="M50 22v51M29 44h42" fill="none" stroke="#fff8e9"/>',
        atk:'<path d="m23 70 11-11 35-42 13-4-3 14-38 39-11 11Z" fill="#d5e2e7"/><path d="m24 57 18 18m-25 7 16-16" fill="none" stroke="#ba8c37" stroke-width="7"/>',
        spd:'<path d="m40 19 27 4-4 34c-4 18-17 28-47 26l-2-17c15 0 23-7 25-19Z" fill="#71533e"/><path d="M22 72c21 2 34-7 37-17" fill="none" stroke="#fff8e9"/>',
        plus:'<path d="M50 21v56M22 49h56" fill="none" stroke="#5b9b45" stroke-width="13"/>',
        up:'<path d="m19 49 31-31 31 31H63v32H37V49Z" fill="#5b9b45"/>',
        cost:'<ellipse cx="36" cy="66" rx="24" ry="12" fill="#e9af39"/><path d="M12 49v17c0 16 48 16 48 0V49" fill="#e9af39"/><ellipse cx="36" cy="49" rx="24" ry="12" fill="#f8d36a"/><path d="M75 18v39M61 45l14 16 14-16" fill="none" stroke="#588746" stroke-width="8"/>'
    };
    if(!Object.hasOwn(symbols,effect))throw new Error('Unknown research effect '+effect);
    return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><rect x="141" y="148" width="106" height="99" rx="27" fill="${paper}" stroke="${ink}" stroke-width="4"/><rect x="145" y="152" width="98" height="91" rx="23" fill="none" stroke="#c89839" stroke-width="3"/><g transform="translate(147 150) scale(.92)" stroke="${ink}" stroke-width="4" stroke-linejoin="round" stroke-linecap="round">${symbols[effect]}</g></svg>`);
}

function sceneBadgeKind(code) {
    if (/protect/.test(code)) return 'def';
    if (/(capacity|storage|march_size|march_limit|training_amount|rally_attack_amount)/.test(code)) return 'plus';
    if (/(gathering_speed|research_speed|construction_speed|healing_time_reduced|troop_speed)/.test(code)) return 'spd';
    if (/production/.test(code)) return 'up';
    return effectOf(code);
}

function sceneBadgeSvg(kind) { return effectBadgeSvg(kind); }

function paintedSceneFor(code) {
    if (/food/.test(code)) return 'food';
    if (/wood/.test(code)) return 'wood';
    if (/stone/.test(code)) return 'stone';
    if (/gold/.test(code)) return 'gold';
    if (/crystal/.test(code)) return 'crystal';
    if (/hospital|healing/.test(code)) return 'hospital';
    if (/research_speed/.test(code)) return 'research';
    if (/construction_speed/.test(code)) return 'construction';
    if (/resource_(protect|capacity|production)|production_resource_protect/.test(code)) return 'protection';
    if (/troops_|march_|rally_attack_amount|troop_speed_when_participating_a_rally/.test(code)) return 'army';
    return null;
}

function portraitFrameSvg() {
    return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="248" height="248"><defs><linearGradient id="paper" x2="0" y2="1"><stop stop-color="#fbf6ec"/><stop offset="1" stop-color="#dfcda9"/></linearGradient></defs><rect x="3" y="3" width="242" height="242" rx="27" fill="#c89839" stroke="#3d241d" stroke-width="5"/><rect x="11" y="11" width="226" height="226" rx="21" fill="url(#paper)" stroke="#ffdf77" stroke-width="5"/></svg>`);
}
const troopFiles={infantry:'guardian',ranged:'fire-archer',cavalry:'shadow-rider'};
async function troopCutout(family,width,height) {
    return sharp(path.join(troopDir,`${troopFiles[family]}-t4-ui.webp`)).trim({background:TRANSPARENT}).resize(width,height,{fit:'contain',background:TRANSPARENT}).png().toBuffer();
}
// Pack one connected painted subject, removing only neighbouring sheet fragments.
async function isolateSubject(buffer) {
    const {data,info}=await sharp(buffer).ensureAlpha().raw().toBuffer({resolveWithObject:true});
    const w=info.width,h=info.height,seen=new Uint8Array(w*h);let largest=[];
    for(let start=0;start<w*h;start++) {
        if(seen[start]||data[start*4+3]<16)continue;
        const pixels=[start];seen[start]=1;
        for(let q=0;q<pixels.length;q++) {
            const at=pixels[q],x=at%w,y=Math.floor(at/w);
            for(const next of [x>0?at-1:-1,x<w-1?at+1:-1,y>0?at-w:-1,y<h-1?at+w:-1]) {
                if(next>=0&&!seen[next]&&data[next*4+3]>=16){seen[next]=1;pixels.push(next);}
            }
        }
        if(pixels.length>largest.length)largest=pixels;
    }
    const keep=new Uint8Array(w*h);largest.forEach(at=>keep[at]=1);
    // Preserve antialiasing immediately next to the selected component.
    for(let at=0;at<w*h;at++)if(!keep[at]) {
        const x=at%w,y=Math.floor(at/w);
        if(![x>0?at-1:-1,x<w-1?at+1:-1,y>0?at-w:-1,y<h-1?at+w:-1].some(next=>next>=0&&keep[next]))data[at*4+3]=0;
    }
    return sharp(data,{raw:info}).trim({background:TRANSPARENT}).png().toBuffer();
}
// Export the source tile's exterior white sheet background as alpha. The
// flood fill cannot reach painted interior highlights across the dark frame.
async function transparentSheetExterior(buffer) {
    const {data,info}=await sharp(buffer).ensureAlpha().raw().toBuffer({resolveWithObject:true});
    const w=info.width,h=info.height,seen=new Uint8Array(w*h),queue=[];
    function visit(at) {
        if(seen[at])return;seen[at]=1;
        const i=at*4,r=data[i],g=data[i+1],b=data[i+2];
        if(data[i+3]===0||(Math.min(r,g,b)>=225&&Math.max(r,g,b)-Math.min(r,g,b)<=20))queue.push(at);
    }
    for(let x=0;x<w;x++){visit(x);visit((h-1)*w+x);}
    for(let y=0;y<h;y++){visit(y*w);visit(y*w+w-1);}
    for(let q=0;q<queue.length;q++) {
        const at=queue[q],x=at%w,y=Math.floor(at/w);data[at*4+3]=0;
        if(x>0)visit(at-1);if(x<w-1)visit(at+1);if(y>0)visit(at-w);if(y<h-1)visit(at+w);
    }
    return sharp(data,{raw:info}).png().toBuffer();
}
async function buildApprovedBases() {
    // Match the actual, corrected game characters instead of retaining the old
    // horned cavalry or the original HP hearts baked into the concept sheet.
    for (const family of Object.keys(troopFiles)) {
        const character=await troopCutout(family,220,220);
        await sharp(portraitFrameSvg()).composite([{input:character,left:14,top:14}]).png({compressionLevel:9}).toFile(path.join(baseDir,`${family}.png`));
    }
    const army=[
        {input:await troopCutout('ranged',114,180),left:16,top:30},
        {input:await troopCutout('cavalry',130,180),left:101,top:28},
        {input:await troopCutout('infantry',144,190),left:51,top:46}
    ];
    await sharp(portraitFrameSvg()).composite(army).png({compressionLevel:9}).toFile(path.join(baseDir,'scene-army.png'));
    const mask = Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="248" height="248"><rect width="248" height="248" rx="28" fill="white"/></svg>`);
    const gold = await cropGrid(approvedSheet, 5, 1, 3, 0, 248, {r:255,g:255,b:255,alpha:1});
    await sharp(await transparentSheetExterior(gold)).composite([{input:mask,blend:'dest-in'}]).png({compressionLevel:9}).toFile(path.join(baseDir,'scene-gold.png'));
    for (const [key,filename] of Object.entries(paintedSceneSources)) {
        if(key==='army')continue;
        await sharp(path.join(referenceDir,filename)).resize(248,248,{fit:'cover'}).composite([{input:mask,blend:'dest-in'}]).png({compressionLevel:9}).toFile(path.join(baseDir,`scene-${key}.png`));
    }
    // Measured full silhouettes in the approved sheet. Some arrow tips cross
    // nominal cell borders, so isolate the main connected subject after crop.
    const boxes=[
        [[46,94,335,387],[392,42,691,383],[751,67,1067,395],[1112,35,1412,393]],
        [[28,438,359,722],[372,436,720,731],[761,428,1049,714],[1072,421,1420,734]],
        [[51,738,353,1046],[409,744,718,1050],[761,728,1084,1048],[1118,727,1434,1051]]
    ];
    const meta=await sharp(unitSheet).metadata();
    if(meta.width!==1448||meta.height!==1086)throw new Error('Review unit source crop bounds after source changes');
    for(let row=0;row<unlocks.length;row++)for(let column=0;column<unlocks[row].length;column++) {
        const [x0,y0,x1,y1]=boxes[row][column];
        const cell=await sharp(unitSheet).extract({left:x0-2,top:y0-2,width:x1-x0+5,height:y1-y0+5}).png().toBuffer();
        const subject=await isolateSubject(cell);
        await sharp(subject).resize(214,214,{fit:'contain',background:TRANSPARENT}).png({compressionLevel:9}).toFile(path.join(baseDir,`${unlocks[row][column]}.png`));
    }

}

async function renderApprovedStat(code, family, effect) {
    const base = await sharp(path.join(baseDir, `${family}.png`)).png().toBuffer();
    const composites = [{input: base, gravity: 'center'}, {input: effectBadgeSvg(effect), left: 0, top: 0}];
    const badge = badgeSvg(code);
    if (badge) composites.push({input: badge, left: 0, top: 0});
    return sharp({create: {width: SIZE, height: SIZE, channels: 4, background: TRANSPARENT}}).composite(composites).png({compressionLevel: 9}).toBuffer();
}

async function renderFamilySpecial(code, family) {
    const base = await sharp(path.join(baseDir, `${family}.png`)).png().toBuffer();
    const composites = [{input: base, gravity: 'center'}];
    const kind = /training_speed/.test(code) ? 'spd' : /training_amount|storage/.test(code) ? 'plus' : /training_cost/.test(code) ? 'cost' : null;
    if (kind) composites.push({input: sceneBadgeSvg(kind), left: 0, top: 0});
    const badge = badgeSvg(code);
    if (badge) composites.push({input: badge, left: 0, top: 0});
    return sharp({create: {width: SIZE, height: SIZE, channels: 4, background: TRANSPARENT}}).composite(composites).png({compressionLevel: 9}).toBuffer();
}

async function renderUnlock(code) {
    const tier=unlocks.find(row=>row.includes(code)).indexOf(code)+2;
    const tierColours={2:'#8b5e3c',3:'#4f854b',4:'#367bc4',5:'#9656b7'};
    const rank=Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><circle cx="218" cy="218" r="29" fill="${tierColours[tier]}" stroke="#3d241d" stroke-width="4"/><text x="218" y="231" text-anchor="middle" font-family="Georgia,serif" font-size="38" font-weight="bold" fill="#fff8e9">${tier}</text></svg>`);
    const base=await sharp(path.join(baseDir,`${code}.png`)).png().toBuffer();
    return sharp({create:{width:SIZE,height:SIZE,channels:4,background:TRANSPARENT}}).composite([{input:portraitFrameSvg(),left:4,top:4},{input:base,left:21,top:21},{input:rank,left:0,top:0}]).png({compressionLevel:9}).toBuffer();
}

async function renderSpecial(code) {
    const scene = paintedSceneFor(code);
    if (scene) {
        const base = await sharp(path.join(baseDir, `scene-${scene}.png`)).png().toBuffer();
        const composites = [{input: base, gravity: 'center'}];
        const kind = sceneBadgeKind(code);
        if (kind) composites.push({input: sceneBadgeSvg(kind), left: 0, top: 0});
        const badge = badgeSvg(code);
        if (badge) composites.push({input: badge, left: 0, top: 0});
        return sharp({create: {width: SIZE, height: SIZE, channels: 4, background: TRANSPARENT}}).composite(composites).png({compressionLevel: 9}).toBuffer();
    }
    const motifPath = path.join(motifDir, `${code}.svg`);
    if (!fs.existsSync(motifPath)) throw new Error(`Missing motif for ${code}`);
    const raw = await sharp(motifPath, {density: 288}).png().toBuffer();
    const motif = await sharp(raw).trim({background: TRANSPARENT})
        .resize(242, 242, {fit: 'contain', background: TRANSPARENT}).png().toBuffer();
    return sharp({create: {width: SIZE, height: SIZE, channels: 4, background: TRANSPARENT}})
        .composite([{input: motif, gravity: 'center'}]).png({compressionLevel: 9}).toBuffer();
}

(async () => {
    await buildApprovedBases();
    const unlockSet = new Set(unlocks.flat());
    for (const code of manifest.codes) {
        const family = familyOf(code), effect = effectOf(code);
        const buffer = unlockSet.has(code) ? await renderUnlock(code)
            : family !== 'general' && effect ? await renderApprovedStat(code, family, effect)
                : family !== 'general' ? await renderFamilySpecial(code, family)
                : await renderSpecial(code);
        await sharp(buffer).png({compressionLevel: 9}).toFile(path.join(outputDir, `${code}.png`));
    }
    fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({version: 10, count: manifest.codes.length, codes: manifest.codes}, null, 2) + '\n');
    console.log(`Wrote ${manifest.codes.length} approved effect-first research icons to ${path.relative(root, outputDir)}`);
})().catch(error => { console.error(error); process.exit(1); });
