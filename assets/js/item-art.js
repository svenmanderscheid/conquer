/* Approved painted inventory artwork. Keep specialist and rarity art distinct. */
window.ConquerItemArt = (() => {
    'use strict';
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
    function file(path) { return aliases[path] ? `painted-v1/${aliases[path]}.webp` : path; }
    const uniqueCodes = new Set([10101001,10101002,10101003,10101011,10101012,10101021,10101022,10101031,10101032,10101041,10101042,10101043,10102001,10102002,10102011,10102041,10102051,10102061,10103001,10103002,10103003,10103004,10103005,10103006,10103011,10103012,10103013,10103021,10103022,10103023,10103031,10103032,10103041,10104001,10104002,10105001,10105002,10105003,10106001,10106002,10106003,10201001,10201002,10201003,10201004,10201005,10201006,10201007,10201008,10201009,10201010,10201011,10201012,10201013,10201014,10201015,10201016,10201017,10201018,10201019,10201020,10201021,10201022,10201023,10201024,10201025,10201026,10201027,10201028,10201029,10201030,10201031,10201032,10201033,10201034,10201035,10201036,10202001,10202002,10202003,10202004,10202005,10202006,10202007,10202008,10202009,10202012,10202013,10202014,10202015,10202016,10202017,10202018,10202019,10202020,10202021,10202022,10202023,10202024,10202025,10202026,10203001,10203002,10203003,10203004,10203005,10203006,10203007,10203008,10203009,10203010,10203011,10203012,10203013,10203014,10203015,10203016,10203017,10203018,10203019,10203020,10203021,10203022,10203023,10203024,10203025,10203026,10203027,10203028,10203029,10203030,10203031,10203032,10203033,10203034,10203035,10203036,10203037,10204001,10205001,10205002,10205003,10205004,10205005,10206001,10206002,10206003,10206004,10207001,10207002,10207003,10207004,10207005,10207021,10207022,10207023,10208001,10208002,10208003,10300001,10300002,10300003,10300004,10300005,119000001,119000002,120101007,120104022,120104023,120104026,120104027,120104103,120104104,120104107,120104136,120105012,120105013,120105014,120601001,120601002,120601003,120602002,120602003,120602004,120603023,120603024,120603025,120603026,120604001,120604002]);
    function forItem(item) {
        const code = Number(item.item_code || item.code);
        if (uniqueCodes.has(code)) return `painted-v2/${code}.webp`;
        if (item.category === 'resource_pack' && ['food','lumber','stone','gold','gems'].includes(item.resource)) return `painted-v1/${item.resource}.webp`;
        if (item.category === 'speedup') {
            const name = {generic:'speedup',building:'construction',research:'research'}[item.subcategory];
            if (name) return `painted-v1/${name}.webp`;
        }
        if (item.category === 'ap_refill') return 'painted-v1/energy.webp';
        if (item.category === 'boost' && item.boost_type === 'city_shield') return 'painted-v1/shield.webp';
        if (item.category === 'chest' && item.chest_type === 'gold') return 'painted-v1/chest.webp';
        if (item.category === 'fragment_pack' && item.fragment_grade === 'epic') return 'painted-v1/fragment.webp';
        return null;
    }
    function url(base,path) { return `${base}/assets/art/items/${file(path)}?v=${encodeURIComponent(window.CONQUER_ITEM_ART_VERSION || 'painted-v1')}`; }
    return {file,url,forItem};
})();
