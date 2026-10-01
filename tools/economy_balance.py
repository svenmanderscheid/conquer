"""Derive active resource prices from immutable source tables, never from old discounts."""
import json
from decimal import Decimal, ROUND_CEILING

RESOURCES = ('food', 'lumber', 'stone', 'gold')


def read(path):
    return json.loads(path.read_text(encoding='utf-8-sig'))


def source_resources(row):
    result = dict.fromkeys(RESOURCES, 0)
    for cost in row['resources']:
        resource = 'lumber' if cost['type'] == 'wood' else cost['type']
        if resource in result:
            result[resource] += int(cost['value'])
    return result


def scaled_price(value, factor):
    return int((Decimal(value) * factor).to_integral_value(rounding=ROUND_CEILING))


def apply_costs(buildings, research, source, policy):
    """Change only resources. Keep materials, timers, effects, IDs and prerequisites."""
    rule = policy['buildings']
    anchor = rule['anchor_level']
    for code, levels in buildings.items():
        original = read(source / (code + '.json'))
        base = source_resources(original[str(anchor)])
        for level, entry in levels.items():
            raw = source_resources(original[level])
            growth = Decimal(rule['growth_per_level']) ** max(0, int(level) - anchor)
            prices = {}
            for resource, value in raw.items():
                # Early levels retain the source curve; later levels grow at most
                # 25% per step from the original level-five anchor.
                bounded = min(Decimal(value), Decimal(base[resource]) * growth) if int(level) > anchor else Decimal(value)
                factor = Decimal(rule['resource_factor'])
                if resource == 'gold':
                    factor *= Decimal(rule['gold_factor'])
                prices[resource] = scaled_price(bounded, factor)
            entry['resources'] = prices

    rule = policy['research']
    for tree, catalog in research.items():
        original = read(source / (tree + '.json'))
        for node in catalog['nodes']:
            rows = {int(row['level']): row for row in original[node.get('source_code', node['code'])]}
            for entry in node['levels']:
                row = rows[entry['level']]
                academy = max((int(req['level']) for req in row['requirements'] if req['type'] == 'academy'), default=1)
                factor = Decimal(rule['resource_factor']) * Decimal(rule['factor_per_academy_level']) ** max(0, academy - rule['anchor_academy_level'])
                entry['resources'] = {
                    resource: scaled_price(value, factor * (Decimal(rule['gold_factor']) if resource == 'gold' else 1))
                    for resource, value in source_resources(row).items()
                }
