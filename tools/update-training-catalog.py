"""Reference values transcribed from the supplied 2026-09-13 screenshots (T11 excluded)."""
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
path = root / 'data/troops.json'
catalog = json.loads(path.read_text(encoding='utf-8'))
existing = {t['code']: t for t in catalog['troops']}
# power, attack, defense, health, lethality; speed and carry are shared across types.
stats = {
    1: [(4,1,6,6,1),(5,2,7,7,2),(9,3,8,13,3),(13,4,12,14,4),(18,9,13,15,8),(28,10,14,16,9),(40,11,17,17,10),(54,12,18,18,11),(72,13,19,19,12),(94,14,20,20,13)],
    2: [(4,7,1,1,6),(5,8,2,2,7),(9,13,3,3,14),(14,14,4,4,15),(19,15,10,9,16),(30,16,10,10,17),(43,19,12,11,18),(57,20,12,12,19),(77,21,14,13,20),(99,22,15,14,21)],
    3: [(4,6,2,2,5),(5,7,3,3,6),(9,8,4,4,12),(13,12,5,5,13),(18,13,10,9,14),(28,14,11,10,15),(40,17,12,11,16),(54,18,13,12,17),(72,19,14,13,18),(94,20,15,14,19)],
}
loads = [108,124,142,164,188,217,249,287,330,379]
names = {
    1:['Schwertkämpfer','Krieger','Ritter','Wächter','Kreuzritter','Schildveteran','Schwerer Gardist','Eliteritter','Königsgardist','Kronenwächter'],
    2:['Bogenschützen','Langbogenschützen','Waldläufer','Armbrustschützen','Scharfschützen','Meisterschützen','Waldwächter','Eliteschützen','Königsschützen','Kronenschützen'],
    3:['Reiter','Berittene Krieger','Schwere Kavallerie','Eiserne Kavallerie','Dragoner','Lanzenveteranen','Panzerreiter','Elitekavallerie','Königsreiter','Kronenreiter'],
}
troops=[]
for kind in [1,2,3]:
    for tier, values in enumerate(stats[kind],1):
        code=50000001+kind*100000+tier*100
        if tier<=5:
            troop=dict(existing[code])
            # Keep Conquer's established movement pacing, independently of reference speed.
            troop.setdefault('march_speed',troop['speed'])
        else:
            base=existing[50000001+kind*100000+500]
            troop={**base,'code':code,'tier':tier,'name':names[kind][tier-1],
                   'time':round(base['time']*1.3**(tier-5)),
                   'heal_time':[1,2,3,4,5,7,9,11,13,15][tier-1],
                   'march_speed':base.get('march_speed',base['speed'])+8*(tier-5)}
            for res in ['food','lumber','stone','gold']:
                troop['need_'+res]=round(base['need_'+res]*1.35**(tier-5))
        troop.update(zip(['power','attack','defense','hp','lethality'],values))
        troop.update(name_de=names[kind][tier-1],speed=11,carry=loads[tier-1])
        troop.pop('unlock_academy',None)
        troop.pop('unlock_research',None)
        troop['unlock_building']=[1,4,7,11,13,16,19,22,26,30][tier-1]
        troop['unlock_castle']=[1,7,7,11,13,16,19,22,26,30][tier-1]
        troops.append(troop)
catalog.update(version=3,max_tier=10,reference='User screenshots, 2026-09-13. T11 intentionally excluded.',troops=troops)
path.write_text(json.dumps(catalog,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
