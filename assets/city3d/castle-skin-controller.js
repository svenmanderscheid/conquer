import * as T from './vendor/three.module.js';
import {CASTLE_SKIN_IDS,addCastleEffects} from './castle-effects.js?v=collection6';
import {buildNewLegendaryA} from './castle-legendary-new-a.js';
import {buildNewLegendaryB} from './castle-legendary-new-b.js';
import {buildOriginalMythicA} from './castle-original-a.js';
import {buildOriginalMythicB} from './castle-original-b.js';
import {buildDragonCastle} from './castle-dragon.js?v=dragonsteel7';
import {addEpicCastleDetails} from './castle-epic-details.js?v=dragonsteel1';

// Keep selection, placement and construction on the stable outer building.
// Only its cosmetic model is swapped; retain at most three alternate models.
export function attachCastleSkins(root){
  const original=new T.Group();original.name='castle-skin-default';
  original.userData.skin='default';
  for(const child of [...root.children])original.add(child);
  addEpicCastleDetails(original,'default');
  root.add(original);
  const models=new Map([['default',original]]);
  let active=original;
  root.userData.castleSkin='default';
  root.userData.setCastleSkin=skin=>{
    if(!CASTLE_SKIN_IDS.includes(skin))skin='default';
    if(root.userData.castleSkin===skin)return false;
    let model=models.get(skin);
    if(!model){
      model=buildNewLegendaryA(skin)||buildNewLegendaryB(skin)||buildOriginalMythicA(skin)||buildOriginalMythicB(skin)||buildDragonCastle(skin);
      if(!model.userData.skipEpicDetails)addEpicCastleDetails(model,skin);
      addCastleEffects(model,skin);root.add(model);
    }
    models.delete(skin);models.set(skin,model);
    active.visible=false;model.visible=true;active=model;
    root.userData.castleSkin=skin;
    if(models.size>4){
      const [id,unused]=[...models].find(([id])=>id!=='default'&&id!==skin);
      unused.removeFromParent();models.delete(id);
      const geometries=new Set(),materials=new Set();
      unused.traverse(object=>{if(object.geometry)geometries.add(object.geometry);if(object.material&&!object.userData.storybookInk)for(const material of Array.isArray(object.material)?object.material:[object.material])materials.add(material);object.dispose?.();});
      geometries.forEach(geometry=>geometry.dispose());materials.forEach(material=>material.dispose());
    }
    return true;
  };
  root.userData.animateCastle=time=>active.userData.animate?.(time);
  root.userData.castleEffectState=()=>active.userData.effectState?.()??null;
  root.userData.castleModel=()=>active;
}
