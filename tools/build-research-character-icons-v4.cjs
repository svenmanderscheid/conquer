'use strict';

const fs = require('fs');
const path = require('path');
const sharp = require('C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/sharp');
const root = path.resolve(__dirname, '..');
const referenceDir = path.join(root, 'assets/art/research/references');
const motifDir = path.join(root, 'assets/art/research/nodes-v3/motifs');
// Use a new public directory whenever the approved artwork changes so an
// installed PWA cannot keep serving older files under identical URLs.
const outputDir = path.join(root, 'assets/art/research/characters-v9');
const baseDir = path.join(outputDir, 'bases');
const manifest = JSON.parse(fs.readFileSync(path.join(root, 'assets/art/research/nodes-v3/manifest.json'), 'utf8'));
const SIZE = 256;
const TRANSPARENT = {r: 0, g: 0, b: 0, alpha: 0};
const effectSheets = {
    infantry: path.join(referenceDir, 'research-effects-infantry.png'),
    ranged: path.join(referenceDir, 'research-effects-ranged.png'),
    cavalry: path.join(referenceDir, 'research-effects-cavalry.png')
};
const unitSheet = path.join(referenceDir, 'research-unit-progressions.png');
const approvedSheet = path.join(referenceDir, 'research-character-style-approved.png');
const paintedSceneSources = {
    food: 'economy-food-approved.png', wood: 'economy-wood-approved.png', stone: 'economy-stone-approved.png',
    crystal: 'economy-crystal-approved.png', army: 'general-army-approved.png', hospital: 'general-hospital-approved.png',
    research: 'general-research-approved.png', construction: 'general-construction-approved.png', protection: 'general-protection-approved.png'
};
const effects = ['hp', 'def', 'atk', 'spd'];
const unlocks = [
    ['warrior', 'knight', 'guardian', 'crusader'],
    ['longbow_man', 'ranger', 'crossbow_man', 'sniper'],
    ['horseman', 'heavy_cavalry', 'iron_cavalry', 'dragoon']
];

fs.mkdirSync(baseDir, {recursive: true});
const familyOf = code => /infantry|infantrys|warrior|knight|guardian|crusader/.test(code) ? 'infantry'
    : /ranged|archer|archers|longbow|ranger|crossbow|sniper/.test(code) ? 'ranged'
        : /cavalry|cavalrys|horseman|dragoon/.test(code) ? 'cavalry' : 'general';
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

function effectBadgeSvg(effect) {
    const ink = '#3d241d', paper = '#fff8e9';
    const art = effect === 'hp'
        ? `<path d="M205 229c-25-16-37-29-37-44 0-12 9-21 20-21 8 0 14 4 17 11 4-7 10-11 18-11 12 0 20 9 20 21 0 15-12 28-38 44Z" fill="#f05a6a" stroke="${ink}" stroke-width="5"/>`
        : effect === 'def'
            ? `<path d="M205 160 237 171v22c0 20-12 34-32 44-20-10-32-24-32-44v-22Z" fill="#4d91d1" stroke="${ink}" stroke-width="5"/><path d="M205 168v59M181 190h48" stroke="${paper}" stroke-width="4" opacity=".9"/>`
            : effect === 'atk'
                ? `<path d="m174 230 14-14 34-47 11-4-4 12-34 47-14 14Z" fill="#f6f2e5" stroke="${ink}" stroke-width="5" stroke-linejoin="round"/><path d="m180 218 14 14m-23 8 16-16" stroke="#c79738" stroke-width="6" stroke-linecap="round"/>`
                : `<path d="M171 227c16-3 28-13 32-28l5-28 22 4-4 31c-4 21-19 34-48 35Z" fill="#655f58" stroke="${ink}" stroke-width="5"/><path d="M180 223c15 2 29-3 41-13" stroke="${paper}" stroke-width="4"/>`;
    const rays = `<path d="M205 151v8m0 76v8m-54-54h9m89 0h-9m-68-27 6 7m54-7-6 7m-48 54-6 7m54-7 6 7" stroke="white" stroke-width="6" stroke-linecap="round" opacity=".95"/>`;
    return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 256 256"><g transform="translate(41 39) scale(.8)">${rays}<circle cx="205" cy="195" r="38" fill="white" opacity=".9"/>${art}</g></svg>`);
}

function sceneBadgeKind(code) {
    if (/protect/.test(code)) return 'def';
    if (/(capacity|storage|march_size|march_limit|training_amount|rally_attack_amount)/.test(code)) return 'plus';
    if (/(gathering_speed|research_speed|construction_speed|healing_time_reduced|troop_speed)/.test(code)) return 'spd';
    if (/production/.test(code)) return 'up';
    return effectOf(code);
}

function sceneBadgeSvg(kind) {
    if (effects.includes(kind)) return effectBadgeSvg(kind);
    const ink = '#3d241d', paper = '#fff8e9';
    const art = kind === 'plus'
        ? `<path d="M194 169v50M169 194h50" stroke="#5b9b45" stroke-width="13" stroke-linecap="round"/>`
        : `<path d="m171 204 23-29 23 29h-13v20h-20v-20Z" fill="#5b9b45" stroke="${ink}" stroke-width="5" stroke-linejoin="round"/>`;
    return Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="256" height="256"><g transform="translate(39 39) scale(.8)"><circle cx="194" cy="194" r="34" fill="${paper}" stroke="#c89839" stroke-width="4"/>${art}</g></svg>`);
}

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

async function buildApprovedBases() {
    const familyColumns = {infantry: 0, ranged: 1, cavalry: 2};
    for (const [family, column] of Object.entries(familyColumns)) {
        const buffer = await cropGrid(approvedSheet, 5, 1, column, 0, 248, {r: 255, g: 255, b: 255, alpha: 1});
        const mask = Buffer.from(`<svg xmlns="http://www.w3.org/2000/svg" width="248" height="248"><rect width="248" height="248" rx="28" fill="white"/></svg>`);
        await sharp(buffer).composite([{input: mask, blend: 'dest-in'}]).png({compressionLevel: 9}).toFile(path.join(baseDir, `${family}.png`));
    }
    const gold = await cropGrid(approvedSheet, 5, 1, 3, 0, 248, {r: 255, g: 255, b: 255, alpha: 1});
    await sharp(gold).png({compressionLevel: 9}).toFile(path.join(baseDir, 'scene-gold.png'));
    for (const [key, filename] of Object.entries(paintedSceneSources)) {
        await sharp(path.join(referenceDir, filename)).resize(248, 248, {fit: 'cover'}).png({compressionLevel: 9}).toFile(path.join(baseDir, `scene-${key}.png`));
    }
    for (let row = 0; row < unlocks.length; row++) {
        for (let column = 0; column < unlocks[row].length; column++) {
            const buffer = await cropGrid(unitSheet, 4, 3, column, row, 232);
            await sharp(buffer).png({compressionLevel: 9}).toFile(path.join(baseDir, `${unlocks[row][column]}.png`));
        }
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
    const kind = /training_speed/.test(code) ? 'spd' : /training_amount|storage/.test(code) ? 'plus' : /training_cost/.test(code) ? 'def' : null;
    if (kind) composites.push({input: sceneBadgeSvg(kind), left: 0, top: 0});
    const badge = badgeSvg(code);
    if (badge) composites.push({input: badge, left: 0, top: 0});
    return sharp({create: {width: SIZE, height: SIZE, channels: 4, background: TRANSPARENT}}).composite(composites).png({compressionLevel: 9}).toBuffer();
}

async function renderUnlock(code) {
    const base = await sharp(path.join(baseDir, `${familyOf(code)}.png`)).png().toBuffer();
    return sharp({create: {width: SIZE, height: SIZE, channels: 4, background: TRANSPARENT}}).composite([{input: base, gravity: 'center'}]).png({compressionLevel: 9}).toBuffer();
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
    fs.writeFileSync(path.join(outputDir, 'manifest.json'), JSON.stringify({version: 9, count: manifest.codes.length, codes: manifest.codes}, null, 2) + '\n');
    console.log(`Wrote ${manifest.codes.length} approved effect-first research icons to ${path.relative(root, outputDir)}`);
})().catch(error => { console.error(error); process.exit(1); });
