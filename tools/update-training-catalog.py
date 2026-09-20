"""Reference values transcribed from the supplied 2026-09-13 screenshots (T11 excluded)."""
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
path = root / 'data/troops.json'
catalog = json.loads(path.read_text(encoding='utf-8'))
existing = {t['code']: t for t in catalog['troops']}
# power, attack, defense, health, lethality; carry is shared across types.
stats = {
    1: [(4,1,6,6,1),(5,2,7,7,2),(9,3,8,13,3),(13,4,12,14,4),(18,9,13,15,8),(28,10,14,16,9),(40,11,17,17,10),(54,12,18,18,11),(72,13,19,19,12),(94,14,20,20,13)],
    2: [(4,7,1,1,6),(5,8,2,2,7),(9,13,3,3,14),(14,14,4,4,15),(19,15,10,9,16),(30,16,10,10,17),(43,19,12,11,18),(57,20,12,12,19),(77,21,14,13,20),(99,22,15,14,21)],
    3: [(4,6,2,2,5),(5,7,3,3,6),(9,8,4,4,12),(13,12,5,5,13),(18,13,10,9,14),(28,14,11,10,15),(40,17,12,11,16),(54,18,13,12,17),(72,19,14,13,18),(94,20,15,14,19)],
}
loads = [108,124,142,164,188,217,249,287,330,379]
# World-map pace is a deliberate class advantage.  It applies to the real
# monster dispatcher as well as ordinary marches, so cavalry-only monster
# attacks arrive first; a mixed army still travels at its slowest troop.
march_speeds = {
    1: [65,73,81,89,97,105,113,121,129,137],
    2: [75,83,91,99,107,115,123,131,139,147],
    3: [95,108,121,134,147,155,163,171,179,187],
}
# Conquer balance, 2026-09-20: seconds per unit; 2,000 T4 take 7h 46m 40s
# before bonuses. Keep all three schools on the same gentle tier curve.
training_times = [3,5,9,14,20,27,35,44,54,65]
# Resource weights in tenths of the fixed T1 cost (never compound an imported
# catalogue). T4 costs four times T1 instead of the former roughly 36 times.
training_cost_weights = [10,16,25,40,60,85,115,150,190,240]
training_base_costs = {
    1: dict(food=50,lumber=30,stone=0,gold=0),
    2: dict(food=40,lumber=20,stone=0,gold=10),
    3: dict(food=60,lumber=0,stone=0,gold=20),
}
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
        else:
            base=existing[50000001+kind*100000+500]
            troop={**base,'code':code,'tier':tier,'name':names[kind][tier-1],
                   'heal_time':[1,1,1,2,2,3,4,5,6,8][tier-1]}
        for res, amount in training_base_costs[kind].items():
            troop['need_'+res]=amount*training_cost_weights[tier-1]//10
        troop.update(zip(['power','attack','defense','hp','lethality'],values))
        troop.update(name_de=names[kind][tier-1],speed=11,march_speed=march_speeds[kind][tier-1],carry=loads[tier-1],time=training_times[tier-1])
        troop.pop('unlock_academy',None)
        troop.pop('unlock_research',None)
        troop['unlock_building']=[1,4,7,11,13,16,19,22,26,30][tier-1]
        troop['unlock_castle']=[1,4,7,11,13,16,19,22,26,30][tier-1]
        troops.append(troop)
catalog.update(version=max(7,catalog.get('version',0)),max_tier=10,reference='User screenshots, 2026-09-13. T11 intentionally excluded.',troops=troops)
path.write_text(json.dumps(catalog,ensure_ascii=False,indent=2)+'\n',encoding='utf-8')
