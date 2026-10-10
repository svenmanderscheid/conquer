// One shared illustration per benefit; ownership and battle state remain overlays.
window.ConquerTerritoryArt = (() => {
  'use strict';
  const communes = Object.freeze({
    food: 'commune-food', lumber: 'commune-lumber', stone: 'commune-stone',
    gold: 'commune-gold', abbey: 'commune-abbey', research: 'commune-abbey',
    rune: 'commune-rune', rune_watch: 'commune-rune'
  });
  function key(target) {
    if (target?.kind === 'crown') return 'congress-forum';
    if (target?.kind === 'canton') return 'canton-shrine';
    return Object.hasOwn(communes, target?.benefit_type) ? communes[target.benefit_type] : 'commune-food';
  }
  return Object.freeze({key, image: (base, target) => `${base}/assets/art/territory-v4/${key(target)}.webp`});
})();
