"""Removed bonus IDs cannot return through catalogue generation or chest data."""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[1]
RETIRED = {10102021, 10102031, 10202010, 10202011}


def verify(root):
    items = json.loads((root / 'data/items.json').read_text(encoding='utf-8'))['items']
    assert not RETIRED.intersection(item['code'] for item in items)
    assert not any(item['category'] == 'boost' and item.get('boost_type') in ('construction_speed', 'research_speed') for item in items)
    for chest in json.loads((root / 'data/chest_drops.json').read_text(encoding='utf-8'))['chests'].values():
        assert chest['drop_table'] and not RETIRED.intersection(row.get('item_code') for row in chest['drop_table'])
    for category, key, value in [('boost', 'boost_type', 'resource_production'), ('boost', 'boost_type', 'training_speed'), ('speedup', 'subcategory', 'building'), ('speedup', 'subcategory', 'research')]:
        assert any(item['category'] == category and item.get(key) == value for item in items), (category, value)
    return {item['code']: item for item in items}


before = verify(ROOT)
with tempfile.TemporaryDirectory(prefix='conquer-retired-items-') as directory:
    target = Path(directory)
    (target / 'data').mkdir()
    (target / 'tools').mkdir()
    for name in ('items.json', 'chest_drops.json'):
        shutil.copyfile(ROOT / 'data' / name, target / 'data' / name)
    shutil.copyfile(ROOT / 'tools/extend-item-catalog.cjs', target / 'tools/extend-item-catalog.cjs')
    subprocess.run(['node', str(target / 'tools/extend-item-catalog.cjs')], check=True, capture_output=True)
    after = verify(target)
    for code, item in before.items():
        assert code in after and after[code]['category'] == item['category']
        for field in ('boost_type', 'duration_seconds', 'amount', 'bonus_pct', 'resource'):
            assert after[code].get(field) == item.get(field), (code, field)
print('PASS retired IDs stay absent after regeneration; other item IDs, effects and time speedups remain intact')
