"""Cost progression, immutable source data and repeatable imports in an isolated copy."""
import copy
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
FILES = ['buildings.json'] + [f'research/{tree}.json' for tree in ('production', 'battle', 'advanced')]
RESOURCES = ('food', 'lumber', 'stone', 'gold')
sys.path.insert(0, str(ROOT / 'tools'))
from economy_balance import apply_costs


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def prices(row):
    return {key: sum(int(r['value']) for r in row['resources'] if ('lumber' if r['type'] == 'wood' else r['type']) == key) for key in RESOURCES}


def check_sequence(rows, original):
    previous = dict.fromkeys(RESOURCES, 0)
    for row, raw in zip(rows, original, strict=True):
        for resource, value in row['resources'].items():
            source = prices(raw)[resource]
            assert type(value) is int and 0 <= value <= source
            assert (value == 0) == (source == 0)
            assert value >= previous[resource], 'Higher upgrades must not become cheaper'
        previous = row['resources']


buildings = read(ROOT / 'data/buildings.json')['buildings']
for code, rows in buildings.items():
    check_sequence(list(rows.values()), list(read(ROOT / f'data/balance-source/{code}.json').values()))
nodes = {}
for tree in ('production', 'battle', 'advanced'):
    original = read(ROOT / f'data/balance-source/{tree}.json')
    for node in read(ROOT / f'data/research/{tree}.json')['nodes']:
        check_sequence(node['levels'], original[node.get('source_code', node['code'])])
        nodes[node['code']] = node

# Fixed design checkpoints catch accidental factors, gold ratios and tier jumps.
assert buildings['castle']['30']['resources'] == dict(food=4304834, lumber=4304834, stone=4304834, gold=1549817)
assert buildings['academy']['30']['resources'] == dict(food=0, lumber=4607860, stone=4607860, gold=4608029)
assert buildings['farm']['30']['resources'] == dict(food=0, lumber=1285797, stone=1285797, gold=385612)
for code, amount in [('warrior', 52082), ('longbow_man', 52082), ('horseman', 52082), ('knight', 316322), ('guardian', 616214), ('crusader', 1040235)]:
    assert nodes[code]['levels'][0]['resources'] == dict.fromkeys(RESOURCES, amount)

# Early combat improvements and the transition to normal T3-era prices.
for code, level, expected in [
    ('infantry_hp', 1, (245, 123, 368, 258)),
    ('infantry_def', 1, (1764, 882, 2646, 1853)),
    ('infantry_atk', 1, (5145, 2573, 7718, 5403)),
    ('infantry_training_amount', 1, (21484, 10742, 32226, 32226)),  # Academy 11
    ('infantry_training_amount', 3, (38249, 19125, 57373, 57373)),  # Academy 12
    ('infantry_training_amount', 5, (67593, 33797, 101389, 101389)),  # Academy 13
    ('march_size', 1, (66633, 66633, 66633, 66633)),  # Academy 14
    ('march_size', 3, (115298, 115298, 115298, 115298)),  # Academy 15
    ('march_size', 5, (198756, 198756, 198756, 198756)),  # Academy 16: unchanged
]:
    assert nodes[code]['levels'][level - 1]['resources'] == dict(zip(RESOURCES, expected)), (code, level)

# Reconstruct the previous policy to prove that only early military prices move.
catalogs = {tree: read(ROOT / f'data/research/{tree}.json') for tree in ('production', 'battle', 'advanced')}
previous = copy.deepcopy(catalogs)
policy = read(ROOT / 'data/economy_balance.json')
policy['research']['early_military']['resource_factor'] = '1.00'
policy['research']['early_military']['gold']['resource_factor'] = '1.00'
apply_costs({}, previous, ROOT / 'data/balance-source', policy)
changed = 0
for tree, catalog in catalogs.items():
    for node, old_node in zip(catalog['nodes'], previous[tree]['nodes'], strict=True):
        for row, old_row in zip(node['levels'], old_node['levels'], strict=True):
            academy = max((req['level'] for req in row['requirements'] if req['type'] == 'academy'), default=1)
            if tree != 'battle' or academy >= 16:
                assert row == old_row, 'Other trees and later military levels retain existing prices'
            else:
                assert all(row['resources'][r] <= old_row['resources'][r] for r in RESOURCES)
                assert sum(row['resources'].values()) < sum(old_row['resources'].values())
                changed += 1
assert changed == 118

# The additional gold relief ends before T2, without reducing other resources again.
before_gold = copy.deepcopy(catalogs)
gold_policy = read(ROOT / 'data/economy_balance.json')
gold_policy['research']['early_military']['gold']['resource_factor'] = '1.00'
apply_costs({}, before_gold, ROOT / 'data/balance-source', gold_policy)
for tree, catalog in catalogs.items():
    for node, old_node in zip(catalog['nodes'], before_gold[tree]['nodes'], strict=True):
        for row, old_row in zip(node['levels'], old_node['levels'], strict=True):
            academy = max((req['level'] for req in row['requirements'] if req['type'] == 'academy'), default=1)
            for resource in RESOURCES:
                if tree == 'battle' and academy < 10 and resource == 'gold':
                    assert 0 < row['resources'][resource] < old_row['resources'][resource]
                else:
                    assert row['resources'][resource] == old_row['resources'][resource]
for code, level, gold in [('infantry_atk', 3, 8869), ('infantry_spd', 1, 10936),
                          ('infantry_spd', 3, 16900), ('infantry_spd', 5, 27416),
                          ('troops_storage', 3, 27343)]:
    assert nodes[code]['levels'][level - 1]['resources']['gold'] == gold

# Include every sequential prerequisite, counting shared branches only once.
needed = {}
def require(code, level):
    previous = needed.get(code, 0)
    if previous >= level:
        return
    needed[code] = level
    for row in nodes[code]['levels'][previous:level]:
        for req in row['requirements']:
            if req['type'] == 'research':
                require(req['code'], req['level'])
require('warrior', 1)
assert sum(needed.values()) == 28
assert sum(sum(row['resources'].values()) for code, level in needed.items() for row in nodes[code]['levels'][:level]) == 926461
needed.clear()
require('crusader', 1)
total = {resource: sum(row['resources'][resource] for code, level in needed.items() for row in nodes[code]['levels'][:level]) for resource in RESOURCES}
assert sum(needed.values()) == 104
assert sum(total.values()) == 52611876
assert total['gold'] == 13986111
print('PASS monotonic costs for 420 building and 963 research levels; T5 path includes all 104 prerequisites/upgrades:', total)

# Run generators outside the working tree; a price update must not reset other
# active rules or compound a previous discount. Full imports must retain prices.
with tempfile.TemporaryDirectory(prefix='conquer-economy-') as directory:
    target = Path(directory)
    shutil.copytree(ROOT / 'data', target / 'data')
    (target / 'tools').mkdir()
    for script in ('import_balance.py', 'economy_balance.py', 'research_time_balance.py'):
        shutil.copyfile(ROOT / 'tools' / script, target / 'tools' / script)
    before = {p.relative_to(target / 'data'): p.read_bytes() for p in (target / 'data').rglob('*') if p.is_file()}
    active = {file: read(target / 'data' / file) for file in FILES}
    for flag in ('--costs-only', '--costs-only'):
        subprocess.run([sys.executable, str(target / 'tools/import_balance.py'), flag], check=True, capture_output=True)
        after = {p.relative_to(target / 'data'): p.read_bytes() for p in (target / 'data').rglob('*') if p.is_file()}
        assert after == before, 'Repeat cost updates must be byte-identical and touch no other data'
    # Deliberately change a live price and unrelated metadata. Only the price is repaired.
    altered = copy.deepcopy(active['buildings.json'])
    altered['buildings']['castle']['30']['resources']['gold'] = 1
    altered['buildings']['castle']['30']['time'] += 123
    (target / 'data/buildings.json').write_text(json.dumps(altered), encoding='utf-8')
    subprocess.run([sys.executable, str(target / 'tools/import_balance.py'), '--costs-only'], check=True, capture_output=True)
    altered['buildings']['castle']['30']['resources']['gold'] = 1549817
    assert read(target / 'data/buildings.json') == altered
    for args in (['--research-only'], []):
        subprocess.run([sys.executable, str(target / 'tools/import_balance.py'), *args], check=True, capture_output=True)
        for file in FILES[1:]:
            assert read(target / 'data' / file) == active[file]
    assert read(target / 'data/buildings.json') == active['buildings.json']
    source_files = read(ROOT / 'data/balance-source/manifest.json')['files']
    for name in source_files:
        relative = Path('balance-source') / name
        assert (target / 'data' / relative).read_bytes() == before[relative], f'Original source stays immutable: {name}'
print('PASS costs-only idempotence, unrelated metadata preservation and parity with research/full imports; archive unchanged')
