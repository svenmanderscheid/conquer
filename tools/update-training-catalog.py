"""Build active T1–T5 from the original troop.json, keeping internal IDs stable."""
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
source = json.loads((root / 'data/balance-source/troop.json').read_text(encoding='utf-8-sig'))
training_times = [3, 5, 9, 16, 27]  # 2,000 T5 = 15 hours before bonuses.
academy_levels = [1, 10, 16, 23, 30]
resources = {10100001: 'food', 10100002: 'lumber', 10100003: 'stone', 10100004: 'gold'}
names = {
    1: ['Schwertkämpfer', 'Krieger', 'Ritter', 'Wächter', 'Kreuzritter'],
    2: ['Bogenschützen', 'Langbogenschützen', 'Waldläufer', 'Armbrustschützen', 'Scharfschützen'],
    3: ['Reiter', 'Berittene Krieger', 'Schwere Kavallerie', 'Eiserne Kavallerie', 'Dragoner'],
}
troops = []
for row in source:
    kind, tier = int(row['type']), int(row['code']) % 100
    assert kind in names and 1 <= tier <= 5
    troop = {key: row[key] for key in ['name', 'type', 'hp', 'attack', 'defense', 'speed', 'carry', 'power', 'heal_time']}
    troop.update(code=50000001 + kind * 100000 + tier * 100, source_code=row['code'], tier=tier,
                 name=row['name'].title(), name_de=names[kind][tier - 1], march_speed=row['speed'],
                 time=training_times[tier - 1], source_time=row['time'],
                 unlock_building=1, unlock_castle=academy_levels[tier - 1], unlock_academy=academy_levels[tier - 1],
                 unlock_research=None if tier == 1 else row['name'].replace(' ', '_'))
    for resource in resources.values():
        troop['need_' + resource] = 0
    for slot in range(1, 5):
        troop['need_' + resources[row[f'need_code_{slot}']]] += row[f'need_value_{slot}']
    troops.append(troop)
assert len(troops) == 15 and len({t['code'] for t in troops}) == 15
catalog = dict(version=8, max_tier=5, reference='data/balance-source/troop.json; original values, adjusted training times (2026-09-29).', troops=troops)
(root / 'data/troops.json').write_text(json.dumps(catalog, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')
print('Built 15 original T1–T5 troops. 2,000 T5: 15 hours before bonuses.')
