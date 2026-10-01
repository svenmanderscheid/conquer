"""Derive research timers from immutable sources; never compound earlier reductions."""
import json
from decimal import Decimal, ROUND_CEILING


def apply_times(research, source, policy):
    """Change only time. Existing queue timestamps are intentionally outside this catalogue import."""
    rule = policy['normal']
    unlocks = policy['troop_unlock_seconds']
    for tree, catalog in research.items():
        original = json.loads((source / (tree + '.json')).read_text(encoding='utf-8-sig'))
        for node in catalog['nodes']:
            rows = {int(row['level']): row for row in original[node.get('source_code', node['code'])]}
            for entry in node['levels']:
                raw = rows[entry['level']]
                if node['code'] in unlocks:
                    assert len(node['levels']) == 1, node['code']
                    entry['time'] = int(unlocks[node['code']])
                    continue
                academy = max((int(req['level']) for req in raw['requirements'] if req['type'] == 'academy'), default=1)
                steps = max(0, rule['max_academy_level'] - academy)
                limit = Decimal(rule['max_seconds']) / Decimal(rule['factor_per_academy_level']) ** steps
                seconds = min(Decimal(raw['time']), limit) if rule['preserve_shorter_source_times'] else limit
                entry['time'] = max(1, int(seconds.to_integral_value(rounding=ROUND_CEILING)))
