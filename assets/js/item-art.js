/* Approved painted item families, live value labels and speedup duration tiers. */
window.ConquerItemArt = (() => {
    'use strict';
    const blueChestArt = 'daily-chest-blue-v1.png';
    const aliases = {
        'gems.svg':'gems', 'energy.svg':'energy', 'speedup.svg':'speedup',
        'backpack/speedup.svg':'speedup', 'backpack/speedup-building.svg':'construction',
        'backpack/speedup-research.svg':'research', 'shield.svg':'shield',
        'chest-gold.svg':'chest', 'fragment-epic.svg':'fragment',
        'research.svg':'research', 'hammer.svg':'construction'
    };
    for (const resource of ['food','lumber','stone','gold','gems']) {
        for (const size of ['bundle','crate','cart']) aliases[`backpack/${resource}-${size}.svg`] = resource;
    }
    const speedupArt = {
        generic:'painted-v2/10103001.webp', building:'painted-v2/10103011.webp',
        research:'painted-v2/10103021.webp', training:'painted-v2/10103031.webp',
        healing:'painted-v2/10103041.webp'
    };
    const speedupCodes = new Map();
    const speedupAliases = {'speedup.svg':speedupArt.generic};
    for (const [type,codes] of Object.entries({
        generic:[10103001,10103002,10103003,10103004,10103005,10103006,10203001,10203002,10203003,10203004,10203005,10203006],
        building:[10103011,10103012,10103013,10203007,10203008,10203009,10203010,10203011,10203012,10203013],
        research:[10103021,10103022,10103023,10203014,10203015,10203016,10203017,10203018,10203019,10203020],
        training:[10103031,10103032,10203021,10203022,10203023,10203024,10203025,10203026,10203027,10203028],
        healing:[10103041,10203029,10203030,10203031,10203032,10203033,10203034,10203035,10203036,10203037]
    })) {
        for (const code of codes) speedupCodes.set(code,type);
        speedupAliases[`backpack/speedup${type==='generic'?'':'-'+type}.svg`] = speedupArt[type];
        for (const suffix of ['', '-v2']) speedupAliases[`reference/speedup-${type}${suffix}.png`] = speedupArt[type];
    }
    // Only presentation identities live here. Amounts, bonuses and use rules come from the catalog.
    const families = {
        'resource:food':[10101001,10101002,10101003,10201001,10201002,10201003,10201004,10201005,10201006,10201007],
        'resource:lumber':[10101011,10101012,10201008,10201009,10201010,10201011,10201012,10201013,10201014,10201015],
        'resource:stone':[10101021,10101022,10201016,10201017,10201018,10201019,10201020,10201021,10201022,10201023],
        'resource:gold':[10101031,10101032,10201024,10201025,10201026,10201027,10201028,10201029,10201030,10201031],
        'resource:gems':[10101041,10101042,10101043,10201032,10201033,10201034,10201035,10201036],
        'ap_refill':[10104001,10104002,10204001,10300001],
        'vip_point':[10106001,10106002,10106003,10206001,10206002,10206003,10206004],
        'resource_box':[10205001,10205002,10205003,10205004,10205005],
        'boost:resource_production':[10102001,10102002],
        'boost:gathering_speed':[10102011,10202009],
        'boost:training_speed':[10102041,10202012],
        'boost:anti_spy':[10102051,10202025],
        'boost:city_shield':[10102061,10202026],
        'boost:food_production':[10202001,10202002],
        'boost:lumber_production':[10202003,10202004],
        'boost:stone_production':[10202005,10202006],
        'boost:gold_production':[10202007,10202008],
        'boost:troops_atk':[10202013,10202014],
        'boost:troops_def':[10202015,10202016],
        'boost:troops_hp':[10202017,10202018],
        'boost:march_size':[10202019,10202020,10300004],
        'boost:vs_monster_attack':[10202021,10202022],
        'boost:march_speed':[10202023,10202024],
        'fragments:normal':[10207001,10207011], 'fragments:rare':[10207002],
        'fragments:epic':[10207003,10207013], 'fragments:legendary':[10207004,10300002]
    };
    const familyCodes = new Map(), familyArt = {};
    for (const [family,codes] of Object.entries(families)) {
        familyArt[family] = `painted-v2/${codes[0]}.webp`;
        for (const code of codes) familyCodes.set(code,family);
    }
    function family(item) {
        if (item.category === 'resource_pack') return `resource:${item.resource}`;
        if (item.category === 'boost') return `boost:${item.boost_type}`;
        if (['ap_refill','vip_point','resource_box'].includes(item.category)) return item.category;
        if (item.category === 'fragment_pack' && item.subcategory !== 'dragon_egg' && !Number(item.treasure_code)) return `fragments:${item.fragment_grade}`;
        return familyCodes.get(Number(item.item_code || item.code));
    }
    function file(path) {
        if (path === 'chest-silver.svg' || path === 'painted-v2/10105001.webp') return blueChestArt;
        if (speedupAliases[path]) return speedupAliases[path];
        const alias = aliases[path], painted = familyArt[`resource:${alias}`] || (alias === 'energy' ? familyArt.ap_refill : null);
        if (painted) return painted;
        if (path === 'prestige.svg') return familyArt.vip_point;
        return alias ? `painted-v1/${alias}.webp` : path;
    }
    const uniqueCodes = new Set([10101001,10101002,10101003,10101011,10101012,10101021,10101022,10101031,10101032,10101041,10101042,10101043,10102001,10102002,10102011,10102041,10102051,10102061,10103001,10103002,10103003,10103004,10103005,10103006,10103011,10103012,10103013,10103021,10103022,10103023,10103031,10103032,10103041,10104001,10104002,10105001,10105002,10105003,10106001,10106002,10106003,10201001,10201002,10201003,10201004,10201005,10201006,10201007,10201008,10201009,10201010,10201011,10201012,10201013,10201014,10201015,10201016,10201017,10201018,10201019,10201020,10201021,10201022,10201023,10201024,10201025,10201026,10201027,10201028,10201029,10201030,10201031,10201032,10201033,10201034,10201035,10201036,10202001,10202002,10202003,10202004,10202005,10202006,10202007,10202008,10202009,10202012,10202013,10202014,10202015,10202016,10202017,10202018,10202019,10202020,10202021,10202022,10202023,10202024,10202025,10202026,10203001,10203002,10203003,10203004,10203005,10203006,10203007,10203008,10203009,10203010,10203011,10203012,10203013,10203014,10203015,10203016,10203017,10203018,10203019,10203020,10203021,10203022,10203023,10203024,10203025,10203026,10203027,10203028,10203029,10203030,10203031,10203032,10203033,10203034,10203035,10203036,10203037,10204001,10205001,10205002,10205003,10205004,10205005,10206001,10206002,10206003,10206004,10207001,10207002,10207003,10207004,10207005,10207021,10207022,10207023,10208001,10208002,10208003,10300001,10300002,10300003,10300004,10300005,119000001,119000002,120101007,120104022,120104023,120104026,120104027,120104103,120104104,120104107,120104136,120105012,120105013,120105014,120601001,120601002,120601003,120602002,120602003,120602004,120603023,120603024,120603025,120603026,120604001,120604002]);
    function forItem(item) {
        const code = Number(item.item_code || item.code);
        if (code === 10105001 || item.category === 'chest' && item.chest_type === 'silver') return blueChestArt;
        const speedup = item.category === 'speedup' ? item.subcategory || 'generic' : speedupCodes.get(code);
        if (Object.hasOwn(speedupArt,speedup)) return speedupArt[speedup];
        const shared = familyArt[family(item)];
        if (shared) return shared;
        if (uniqueCodes.has(code)) return `painted-v2/${code}.webp`;
        if (item.category === 'resource_pack' && ['food','lumber','stone','gold','gems'].includes(item.resource)) return `painted-v1/${item.resource}.webp`;
        if (item.category === 'ap_refill') return 'painted-v1/energy.webp';
        if (item.category === 'boost' && item.boost_type === 'city_shield') return 'painted-v1/shield.webp';
        if (item.category === 'chest' && item.chest_type === 'gold') return 'painted-v1/chest.webp';
        if (item.category === 'fragment_pack' && item.fragment_grade === 'epic' && item.subcategory !== 'dragon_egg' && !Number(item.treasure_code)) return 'painted-v1/fragment.webp';
        return null;
    }
    function speedupTier(item) {
        const seconds = Number(item.duration_seconds);
        if ((item.category !== 'speedup' && !speedupCodes.has(Number(item.item_code || item.code))) || !Number.isFinite(seconds) || seconds <= 0) return null;
        return seconds < 3600 ? 'grey' : seconds < 86400 ? 'blue' : seconds < 604800 ? 'violet' : 'orange';
    }
    function speedupLabel(item) {
        const seconds = Number(item.duration_seconds);
        if (!Number.isFinite(seconds) || seconds <= 0) return '';
        const number = value => value.toLocaleString(window.ConquerLocale?.locale || 'en', {maximumFractionDigits:2});
        return seconds >= 86400 ? number(seconds / 86400)+'d' : seconds >= 3600 ? number(seconds / 3600)+'h' : seconds >= 60 ? number(seconds / 60)+'m' : number(seconds)+'s';
    }
    function shortNumber(value) {
        const n = Number(value) || 0, number = value => value.toLocaleString(window.ConquerLocale?.locale || 'en', {maximumFractionDigits:1});
        return Math.abs(n) >= 1e9 ? number(n / 1e9)+'B' : Math.abs(n) >= 1e6 ? number(n / 1e6)+'M' : Math.abs(n) >= 1e3 ? number(n / 1e3)+'k' : number(n);
    }
    function labels(item) {
        const time = speedupLabel(item);
        if (item.category === 'speedup') return time ? [time] : [];
        if (item.category === 'resource_pack') return Number(item.amount) > 0 ? [shortNumber(item.amount)] : [];
        if (item.category === 'boost') return [Number(item.bonus_pct) > 0 ? '+'+shortNumber(item.bonus_pct)+'%' : '',time].filter(Boolean);
        if (item.category === 'ap_refill') return Number(item.ap_amount) > 0 ? [shortNumber(item.ap_amount)+' AP'] : [];
        if (item.category === 'vip_point') return Number(item.vip_points) > 0 ? [shortNumber(item.vip_points)+' VIP'] : [];
        if (item.category === 'resource_box') return Number(item.box_level) > 0 ? [window.ConquerLocale?.t('template.level',{level:item.box_level}) || 'Level '+item.box_level] : [];
        if (item.category === 'fragment_pack') return Number(item.fragment_amount) > 0 ? [shortNumber(item.fragment_amount)+' Fr.'] : [];
        return time ? [time] : [];
    }
    function stockLabel(quantity) {
        const n = Math.max(0,Number(quantity) || 0);
        return n >= 10000 ? shortNumber(n) : n.toLocaleString(window.ConquerLocale?.locale || 'en');
    }
    function resourceUrl(base,resource) {
        const path = familyArt[`resource:${resource}`];
        return path ? url(base,path) : null;
    }
    function artUrl(base,path) {
        if (path === 'items/chest-silver.svg' || path === 'items/painted-v2/10105001.webp') return url(base,blueChestArt);
        const resource = /^ui-resources\/(food|lumber|stone|gold|gems)\.png$/.exec(path)?.[1] || (path === 'items/gems.svg' ? 'gems' : null);
        return resource ? resourceUrl(base,resource) : `${base}/assets/art/${path}`;
    }
    function url(base,path) { return `${base}/assets/art/items/${file(path)}?v=${encodeURIComponent(window.CONQUER_ITEM_ART_VERSION || 'painted-v1')}`; }
    return {file,url,resourceUrl,artUrl,forItem,speedupTier,speedupLabel,labels,stockLabel};
})();
