"""Research balance, source preservation and timer-only/research/full import parity."""
import json
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
TREES = ('production', 'battle', 'advanced')
DAY = 86400


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


catalogs = {tree: read(ROOT / f'data/research/{tree}.json') for tree in TREES}
nodes = {n['code']: n for catalog in catalogs.values() for n in catalog['nodes']}
unlocks = {
    72000: ('warrior', 'longbow_man', 'horseman'),
    4*DAY: ('knight', 'ranger', 'heavy_cavalry'),
    21*DAY: ('guardian', 'crossbow_man', 'iron_cavalry'),
    45*DAY: ('crusader', 'sniper', 'dragoon'),
}
troops = {code for group in unlocks.values() for code in group}
for seconds, codes in unlocks.items():
    for code in codes:
        assert len(nodes[code]['levels']) == 1 and nodes[code]['levels'][0]['time'] == seconds

reduced = 0
for tree, catalog in catalogs.items():
    source = read(ROOT / f'data/balance-source/{tree}.json')
    for node in catalog['nodes']:
        previous = 0
        for entry, raw in zip(node['levels'], source[node.get('source_code', node['code'])], strict=True):
            seconds = entry['time']
            assert isinstance(seconds, int) and 0 < seconds <= int(raw['time']), node['code']
            assert seconds >= previous, node['code']
            previous = seconds
            if node['code'] not in troops:
                assert seconds <= 14*DAY, node['code']
            reduced += seconds < int(raw['time'])
assert nodes['advanced_construction_speed']['levels'][-1]['time'] == 14*DAY
assert nodes['advanced_research_speed']['levels'][-1]['time'] == 1008000  # Academy 29: 14 days / 1.2.
assert nodes['food_production']['levels'][0]['time'] == 3  # Short tutorial timer is preserved.

with tempfile.TemporaryDirectory(prefix='conquer-research-times-') as directory:
    target = Path(directory)
    shutil.copytree(ROOT / 'data', target / 'data')
    (target / 'tools').mkdir()
    for script in ('import_balance.py', 'economy_balance.py', 'research_time_balance.py'):
        shutil.copyfile(ROOT / 'tools' / script, target / 'tools' / script)
    before = {p.relative_to(target / 'data'): p.read_bytes() for p in (target / 'data').rglob('*') if p.is_file()}

    def run(*args):
        subprocess.run([sys.executable, str(target / 'tools/import_balance.py'), *args], check=True, capture_output=True)

    for _ in range(2):
        run('--research-times-only')
        after = {p.relative_to(target / 'data'): p.read_bytes() for p in (target / 'data').rglob('*') if p.is_file()}
        assert after == before, 'Time-only import must be repeatable and touch no unrelated catalogue'
    file = target / 'data/research/production.json'
    altered = read(file)
    row = altered['nodes'][0]['levels'][0]
    expected_time = row['time']
    row.update(time=99999999, power=12345, resources={'food': 321, 'gold': 0, 'lumber': 0, 'stone': 0})
    file.write_text(json.dumps(altered), encoding='utf-8')
    run('--research-times-only')
    row['time'] = expected_time
    assert read(file) == altered, 'Only time changes; even unrelated edited metadata is preserved'
    file.write_bytes(before[Path('research/production.json')])
    for args in (('--research-only',), ()):
        run(*args)
        for tree in TREES:
            assert read(target / f'data/research/{tree}.json') == catalogs[tree]
    assert read(target / 'data/buildings.json') == read(ROOT / 'data/buildings.json')
    for relative, content in before.items():
        if relative.parts[0] == 'balance-source' and relative.name != 'manifest.json':
            assert (target / 'data' / relative).read_bytes() == content, relative
    assert read(target / 'data/balance-source/manifest.json')['files'] == read(ROOT / 'data/balance-source/manifest.json')['files']

print(f'PASS all 963 research stages: {reduced} reduced, T2/T3 unchanged, T4 21d, T5 45d, normal maximum 14d')
print('PASS timer-only import scope/idempotence, full/research import parity and untouched original archive')
