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
for code, amount in [('warrior', 74402), ('knight', 316322), ('guardian', 616214), ('crusader', 1040235)]:
    assert nodes[code]['levels'][0]['resources'] == dict.fromkeys(RESOURCES, amount)

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
require('crusader', 1)
total = {resource: sum(row['resources'][resource] for code, level in needed.items() for row in nodes[code]['levels'][:level]) for resource in RESOURCES}
assert sum(needed.values()) == 104
assert 53_000_000 < sum(total.values()) < 55_000_000
assert 14_000_000 < total['gold'] < 15_000_000
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
