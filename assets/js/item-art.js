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
    function forItem(item) {
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
