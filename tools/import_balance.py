"""Import the supplied balance tables without executing enum.py.

Usage: python tools/import_balance.py [source-directory]
The archived source is the default, so imports are repeatable on every host.
Existing inventory IDs and saved research IDs retain their meaning.
"""
import ast
import hashlib
import json
import pathlib
import re
import shutil
import sys

ROOT = pathlib.Path(__file__).resolve().parents[1]
ARCHIVE = ROOT / 'data/balance-source'
ARGS = [arg for arg in sys.argv[1:] if arg != '--research-only']
SOURCE = pathlib.Path(ARGS[0]) if ARGS else ARCHIVE
BUILDINGS = 'academy barrack castle farm gold_mine hall_of_alliance hospital lumber_camp quarry storage trading_post treasure_house wall watch_tower'.split()
FILES = [name + '.json' for name in BUILDINGS + ['battle', 'production', 'advanced', 'field_monster', 'field_object']] + ['enum.py']

# The supplied tables are the authoritative source for costs, power and
# prerequisites.  Their last ten building durations belong to a different
# progression economy (for example Castle 30 takes more than 160 days), so
# Conquer intentionally applies its own late-game time curve after import.
# Levels 1–20 keep the supplied times unchanged.  The final major upgrades
# become a real end-game goal without making the first twenty levels slower.
DAY = 86_400
BUILD_TIME_CURVES = {
    # Castle and Academy both end at exactly thirty days at level 30.
    'major': [4*DAY, 5*DAY, 6*DAY, 8*DAY, 10*DAY, 13*DAY, 17*DAY, 21*DAY, 25*DAY, 30*DAY],
    # Alliance, defensive and treasury structures are deliberately close to
    # the major curve because they gate collective and storage progression.
    'infrastructure': [4*DAY, 5*DAY, 6*DAY, 8*DAY, 10*DAY, 12*DAY, 15*DAY, 18*DAY, 22*DAY, 26*DAY],
    # Capacity and scouting must remain meaningful past city level 20.
    'capacity': [2*DAY, 3*DAY, 4*DAY, 5*DAY, 7*DAY, 9*DAY, 12*DAY, 16*DAY, 21*DAY, 26*DAY],
    # Troop schools grow noticeably after T7/T8, while Castle 30 remains the
    # primary end-game gate for T10.
    'military': [18*3600, DAY, 32*3600, 42*3600, 54*3600, 3*DAY, 4*DAY, 5*DAY, 7*DAY, 10*DAY],
    'resource': [16*3600, 22*3600, 30*3600, 42*3600, 54*3600, 3*DAY, 4*DAY, 5*DAY, 7*DAY, 10*DAY],
}
BUILD_TIME_GROUPS = {
    'major': {'castle', 'academy'},
    'infrastructure': {'hall_of_alliance', 'hospital', 'trading_post', 'treasure_house', 'wall'},
    'capacity': {'storage', 'watch_tower'},
    'military': {'barrack'},
    'resource': {'farm', 'gold_mine', 'lumber_camp', 'quarry'},
}


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf-8')


def integer(value):
    n = int(value)
    assert float(value) == n and n >= 0, value
    return n


def apply_conquer_build_times(buildings):
    """Replace only levels 21–30 with the documented Conquer time curves."""
    groups = {code: group for group, codes in BUILD_TIME_GROUPS.items() for code in codes}
    assert set(groups) == set(buildings), (set(groups), set(buildings))
    for code, levels in buildings.items():
        curve = BUILD_TIME_CURVES[groups[code]]
        for level, seconds in enumerate(curve, start=21):
            levels[str(level)]['time'] = seconds


def main():
    # Parse literal declarations only. Imports, calls and bot instructions are never run.
    symbols = {}
    for entry in ast.parse((SOURCE / 'enum.py').read_text(encoding='utf-8-sig')).body:
        if isinstance(entry, ast.Assign) and len(entry.targets) == 1 and isinstance(entry.targets[0], ast.Name):
            try:
                symbols[entry.targets[0].id] = ast.literal_eval(entry.value)
            except (ValueError, TypeError):
                pass
    item_names = {v: k.removeprefix('ITEM_CODE_') for k, v in symbols.items() if k.startswith('ITEM_CODE_') and isinstance(v, int)}
    inventory = read(ROOT / 'data/items.json')
    items = inventory['items']
    mappings = {}
    unresolved = []

    def resolve(code):
        code = integer(code)
        if str(code) in mappings:
            return mappings[str(code)]['item_code']
        name = item_names.get(code)
        match = None
        if name:
            pack = re.fullmatch(r'(FOOD|LUMBER|STONE|GOLD|CRYSTAL)_(\d+)(K|M)?', name)
            speed = re.fullmatch(r'(SPEEDUP(?:_BUILDING|_RESEARCH|_TRAIN)?|RECOVER)_(\d+)(M|H|D)', name)
            ap = re.fullmatch(r'ACTION_POINTS_(\d+)', name)
            if pack:
                resource = {'CRYSTAL': 'gems'}.get(pack[1], pack[1].lower())
                amount = int(pack[2]) * {'K': 1000, 'M': 1000000, None: 1}[pack[3]]
                match = next((i for i in items if i['category'] == 'resource_pack' and i['resource'] == resource and i['amount'] == amount), None)
            elif speed:
                category = {'SPEEDUP': 'generic', 'SPEEDUP_BUILDING': 'building', 'SPEEDUP_RESEARCH': 'research', 'SPEEDUP_TRAIN': 'training', 'RECOVER': 'healing'}[speed[1]]
                seconds = int(speed[2]) * {'M': 60, 'H': 3600, 'D': 86400}[speed[3]]
                match = next((i for i in items if i['category'] == 'speedup' and i['subcategory'] == category and i['duration_seconds'] == seconds), None)
            elif ap:
                amount = int(ap[1])
                match = next((i for i in items if i['category'] == 'ap_refill' and i['ap_amount'] == amount), None)
                if match is None:
                    match = dict(next(i for i in items if i['category'] == 'ap_refill'), code=110000000 + code, ap_amount=amount,
                                 name=f'Action Points {amount}', name_de=f'Energieflasche · {amount} AP', description_de=f'Stellt bis zu {amount} Aktionspunkte wieder her.', loot_sources=[])
                    items.append(match)
            elif name.startswith('RESOURCE_BOX_LV'):
                level = int(name.removeprefix('RESOURCE_BOX_LV'))
                match = next((i for i in items if i['category'] == 'resource_box' and i['box_level'] == level), None)
            elif name == 'VIP_100':
                match = next(i for i in items if i['category'] == 'vip_point' and i['vip_points'] == 100)
            elif name in ('SILVER_CHEST', 'GOLD_CHEST'):
                match = next(i for i in items if i['category'] == 'chest' and i['chest_type'] == name.split('_')[0].lower())
        if match is None:
            target = 110000000 + code  # A disjoint namespace; never reinterpret saved inventory codes.
            match = next((i for i in items if i['code'] == target), None)
            label = ('Beschleunigerkiste Stufe '+name.removeprefix('SPEEDUP_BOX_LV')) if name and name.startswith('SPEEDUP_BOX_LV') else name.replace('_', ' ').title() if name else f'Unzugeordneter Gegenstand {code}'
            if not match:
                match = {'code': target, 'source_item_code': code, 'name': label, 'name_de': label,
                         'category': 'material', 'subcategory': 'reference', 'rarity': 'normal',
                         'description_de': 'Dieser Gegenstand bleibt im Inventar erhalten. Seine Wirkung ist in den gelieferten Daten nicht definiert.',
                         'is_usable': False, 'icon': 'pouch.svg', 'icon_framed': False, 'loot_sources': []}
                items.append(match)
            match['name'] = match['name_de'] = label
            unresolved.append({'source_code': code, 'symbol': name, 'item_code': target, 'reason': 'missing_effect' if name else 'missing_definition'})
        mappings[str(code)] = {'symbol': name, 'item_code': match['code'], 'name': match.get('name_de', match['name'])}
        return match['code']

    # Material identifiers are symbolic in the building tables; enum.py supplies no numeric codes.
    materials = {'golden_pillar': (119000001, 'Goldene Säule'), 'alliance_badge': (119000002, 'Allianzabzeichen')}
    buildings = {}
    for name in BUILDINGS:
        rows = read(SOURCE / (name + '.json'))
        assert list(map(int, rows)) == list(range(1, 31)), name
        levels = {}
        for level, row in rows.items():
            resources = dict.fromkeys(['food', 'lumber', 'stone', 'gold'], 0)
            costs = {}
            for cost in row['resources']:
                key, value = cost['type'], integer(cost['value'])
                if key in resources:
                    resources[key] += value
                else:
                    target, label = materials[key]
                    costs[str(target)] = value
                    if not any(i['code'] == target for i in items):
                        items.append({'code': target, 'name': label, 'name_de': label, 'category': 'material', 'subcategory': 'building',
                                      'source_resource': key, 'rarity': 'normal', 'is_usable': False, 'icon': 'pouch.svg', 'icon_framed': False,
                                      'description_de': 'Wird beim Gebäudeausbau automatisch verbraucht.', 'loot_sources': []})
            levels[level] = {'resources': resources, 'items': costs, 'time': integer(row['time']), 'power': integer(row['power']),
                             'requirements': {r['type']: integer(r['level']) for r in row['requirements']}, 'valid': row['valid']}
        buildings[name] = levels

    apply_conquer_build_times(buildings)

    def drops(row, prefix, chance, count):
        result = []
        for slot in range(1, count + 1):
            code = integer(row.get(f'{prefix}_{slot}', 0))
            quantity = integer(row.get(f'count_{slot}', 0))
            probability = float(row.get(f'{chance}_{slot}', 0))
            assert 0 <= probability <= 1
            if code and quantity:
                result.append({'source_item_code': code, 'item_code': resolve(code), 'count': quantity, 'probability': probability})
        return result

    monsters = []
    for row in read(SOURCE / 'field_monster.json'):
        entry = {'source_code': integer(row['code']), 'name': row['name'], 'level': integer(row['level']), 'amount': integer(row['amount']),
                 'action_point_cost': integer(row['action_point']), 'stats': {k: integer(row[k]) for k in ['hp', 'attack', 'defense']},
                 'power_per_unit': integer(row['power']), 'xp': integer(row['xp']), 'rare': row['rare'], 'asset': row['asset'],
                 'buff_grade_min': row['buff_grade_min'], 'buff_grade_max': row['buff_grade_max'], 'klay': row['klay'],
                 'drops': drops(row, 'item', 'prob', 11), 'alliance_gift': None}
        if row['alliance_gift_1']:
            entry['alliance_gift'] = {'source_item_code': row['alliance_gift_1'], 'item_code': resolve(row['alliance_gift_1']), 'count': row['alliance_gift_count_1']}
        monsters.append(entry)
    fields = []
    for row in read(SOURCE / 'field_object.json'):
        fields.append({k: row[k] for k in ['code', 'name', 'level', 'production', 'gathering', 'asset']} | {'drops': drops(row, 'drop', 'rate', 3)})

    # Research already has semantic metadata and intentionally retired troop unlocks.
    # Reimport numeric source values and requirements while preserving saved IDs.
    research = {}
    retired = {n['code']: n for n in read(ROOT / 'data/retired-troop-research.json')['nodes']}
    for tree in ['production', 'battle', 'advanced']:
        raw = read(SOURCE / (tree + '.json'))
        catalog = read(ROOT / 'data/research' / (tree + '.json'))
        aliases = {n.get('source_code', n['code']): n['code'] for n in catalog['nodes']}
        assert set(raw) == set(aliases) | (set(retired) if tree == 'battle' else set())
        for node in catalog['nodes']:
            source_code = node.get('source_code', node['code'])
            levels = []
            for row in raw[source_code]:
                requirements = []
                for req in row['requirements']:
                    code = req['type']
                    if code == 'academy':
                        requirements.append({'type': 'academy', 'level': integer(req['level'])})
                    elif code in retired:
                        requirements.extend(r for r in retired[code]['levels'][0]['requirements'] if r['type'] == 'research')
                    else:
                        requirements.append({'type': 'research', 'level': integer(req['level']), 'code': aliases.get(code, code)})
                resources = dict.fromkeys(['food', 'lumber', 'stone', 'gold'], 0)
                for r in row['resources']:
                    key = 'lumber' if r['type'] == 'wood' else r['type']
                    assert key in resources, key
                    resources[key] += integer(r['value'])
                levels.append({'level': integer(row['level']), 'ability_value': float(row['stats']['ability_value']),
                               'time': integer(row['time']), 'power': integer(row['power']), 'resources': resources,
                               'requirements': list({json.dumps(r, sort_keys=True): r for r in requirements}.values())})
            node['levels'], node['max_level'] = levels, len(levels)
        research[tree] = catalog
    nodes = {n['code']: n for tree in research.values() for n in tree['nodes']}
    assert len(nodes) == sum(len(t['nodes']) for t in research.values())
    for node in nodes.values():
        for level in node['levels']:
            for req in level['requirements']:
                assert req['type'] == 'academy' or (req['code'] in nodes and req['level'] <= nodes[req['code']]['max_level']), req

    if '--research-only' in sys.argv:
        for tree, catalog in research.items():
            write(ROOT / 'data/research' / (tree + '.json'), catalog)
        print(f'Imported {len(nodes)} research nodes, preserving saved IDs and retired troop unlocks.')
        return

    # Fill out levels that were absent from the previous normal-world catalogue.
    catalogue = read(ROOT / 'data/monsters.json')
    family_codes = {'Orc': 20200101, 'Skeleton': 20200102, 'Golem': 20200103, 'Treasure Goblin': 20200104,
                    'Deathkar': 20200201, 'Green Dragon': 20200202, 'Red Dragon': 20200203, 'Gold Dragon': 20200204, 'Magdar': 20200205}
    source_monsters = {(row['source_code'], row['level']): row for row in monsters}
    for existing in catalogue['monsters']:
        row = source_monsters.get((family_codes.get(existing['name']), existing['level']))
        if row:
            existing.update({k: v for k, v in row.items() if k != 'name'})
            existing['source_amount'] = row['amount']
            existing['resource_reward'] = dict.fromkeys(['food','lumber','stone','gold'],0)
            existing['gems_drop'] = {'chance': 0, 'amount': 0}
    present = {(r['name'], r['level']) for r in catalogue['monsters']}
    for row in monsters:
        if row['source_code'] == 20200104 and ('Treasure Goblin', row['level']) not in present:
            code = 20200400 + row['level']
            assert not any(r['code'] == code for r in catalogue['monsters'])
            catalogue['monsters'].append(row | {'code': code, 'name': 'Treasure Goblin', 'type': 'solo',
                                                'source_amount': row['amount'],
                                                'resource_reward': dict.fromkeys(['food','lumber','stone','gold'],0), 'gems_drop': {'chance': 0, 'amount': 0}})

    # Imported NPC counts come from a different combat economy. Conquer sizes
    # encounters against its actual troop stats and solo/rally capacity curve.
    troop_rows = read(ROOT / 'data/troops.json')['troops']
    tiers = {}
    for troop in troop_rows:
        tier = integer(troop['tier'])
        tiers.setdefault(tier, {'attacks': [], 'castle': 1})
        tiers[tier]['attacks'].append(integer(troop['attack']))
        tiers[tier]['castle'] = max(tiers[tier]['castle'], integer(troop['unlock_castle']))

    def march_capacity(castle):
        return 5000 + int(45000 * (castle - 1) // 29)

    def rally_capacity(hall):
        points = [(1, 20000), (5, 50000), (10, 100000), (20, 250000), (30, 500000)]
        previous, base = points[0]
        for at, cap in points:
            if hall >= at:
                previous, base = at, cap
                continue
            return base + int((hall - previous) * (cap - base) // (at - previous))
        return base

    solo_difficulty = {'Treasure Goblin': .65, 'Orc': .75, 'Skeleton': .82, 'Golem': .90}
    regional = {'Deathkar', 'Frostgrimm', 'Sandmaul', 'Glutramm', 'Grumwald'}
    endgame = {'Green Dragon': (.85, [8, 9, 10]), 'Red Dragon': (.95, [8, 9, 10]),
               'Gold Dragon': (1.05, [8, 9, 10]), 'Magdar': (1.15, [8, 9, 10])}
    for monster in catalogue['monsters']:
        name, level = monster['name'], integer(monster['level'])
        if name == 'Ork-Späher' or (name == 'Orc' and level == 0):
            continue
        tier, difficulty, capacity = level, None, None
        if name in solo_difficulty:
            difficulty = solo_difficulty[name]
            capacity = march_capacity(tiers[tier]['castle'])
        elif name in regional:
            difficulty = .90
            capacity = rally_capacity(tiers[tier]['castle'])
        elif name in endgame:
            difficulty, tier_map = endgame[name]
            tier = tier_map[level - 1]
            capacity = rally_capacity(tiers[tier]['castle'])
        else:
            continue
        monster.setdefault('source_amount', monster['amount'])
        average_attack = sum(tiers[tier]['attacks']) / len(tiers[tier]['attacks'])
        absorption = monster['stats']['hp'] + monster['stats']['defense']
        monster['amount'] = max(10, round(capacity * average_attack * difficulty / absorption / 10) * 10)
    catalogue['description'] = 'Conquer monster identities and regional bosses. Normal-world balance is generated from field_monster.json with source_item_map.json; reward overrides retain precedence.'
    catalogue['balance_notes'] = {'source': 'data/balance-source/field_monster.json', 'item_mapping': 'data/source_item_map.json',
                                'regional_bosses': 'Keep their Conquer definitions.', 'charms': 'Keep the existing guaranteed Conquer charm mechanics; source grade bounds are retained as metadata.',
                                'combat_amounts': 'Playable amount is derived from matching troop tiers and real solo/rally capacity; source_amount preserves imported NPC counts.',
                                'solo_target': 'Treasure Goblin 65%, Orc 75%, Skeleton 82%, Golem 90% of an unbuffed full mixed march.',
                                'regional_rally_target': 'Regional bosses use 90% of an unbuffed full mixed rally at the matching progression tier.',
                                'endgame_target': 'Green/Red/Gold/Magdar use 85%/95%/105%/115% of T8-T10 endgame rallies.'}
    ARCHIVE.mkdir(parents=True, exist_ok=True)
    if SOURCE.resolve() != ARCHIVE.resolve():
        for name in FILES:
            shutil.copyfile(SOURCE / name, ARCHIVE / name)
    (ARCHIVE / '.htaccess').write_text('Require all denied\n', encoding='utf-8')
    write(ROOT / 'data/buildings.json', {'version': 2, 'buildings': buildings, 'aliases': {'archery_range': 'barrack', 'stable': 'barrack'}})
    write(ROOT / 'data/reference_monsters.json', {'version': 1, 'monsters': monsters})
    write(ROOT / 'data/field_objects.json', {'version': 1, 'objects': fields})
    write(ROOT / 'data/source_item_map.json', {'version': 1, 'items': mappings, 'unresolved': sorted(unresolved, key=lambda r: r['source_code']), 'building_materials': {k: v[0] for k, v in materials.items()}})
    write(ROOT / 'data/items.json', inventory)
    write(ROOT / 'data/monsters.json', catalogue)
    for tree, catalog in research.items():
        write(ROOT / 'data/research' / (tree + '.json'), catalog)
    write(ARCHIVE / 'manifest.json', {'files': {name: hashlib.sha256((ARCHIVE / name).read_bytes()).hexdigest() for name in FILES},
                                     'building_levels': sum(map(len, buildings.values())), 'research_nodes': len(nodes),
                                     'research_levels': sum(len(n['levels']) for n in nodes.values()), 'monsters': len(monsters), 'field_objects': len(fields),
                                     'mapped_items': len(mappings), 'unresolved_items': len(unresolved)})
    print(f'Imported {len(buildings)} buildings, {len(nodes)} research nodes, {len(monsters)} monsters, {len(fields)} objects, {len(mappings)} item mappings ({len(unresolved)} missing definitions/effects).')


if __name__ == '__main__':
    main()
