"""Derive broad, gently curved game rivers from selected official main rivers.

Run after build-lux-hydrology.py. No network or extra dependencies required.
Small meanders are simplified, then corners become sampled quadratic curves.
The same sampled path and two-field width drive drawing and collision checks.
"""
import json
import math
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DIR = ROOT / 'assets/world-lux-preview'
TOLERANCE, WIDTH = 3, 2
KEEP = {'Mosel', 'Sauer', 'Our', 'Alzette', 'Attert', 'Eisch', 'Wiltz', 'Clerve', 'Woltz'}
source = json.loads((DIR / 'hydrology.json').read_text(encoding='utf-8'))


def nearest(p, a, b):
    dx, dy = b[0]-a[0], b[1]-a[1]
    t = max(0, min(1, ((p[0]-a[0])*dx+(p[1]-a[1])*dy)/(dx*dx+dy*dy))) if dx or dy else 0
    return [a[0]+t*dx, a[1]+t*dy]


def distance2(p, a, b):
    q = nearest(p, a, b)
    return sum((v-w)**2 for v, w in zip(p, q))


def simplify(points, tolerance=TOLERANCE):
    keep, todo = {0, len(points)-1}, [(0, len(points)-1)]
    while todo:
        a, b = todo.pop()
        if b-a < 2:
            continue
        i = max(range(a+1, b), key=lambda j: distance2(points[j], points[a], points[b]))
        if distance2(points[i], points[a], points[b]) > tolerance**2:
            keep.add(i)
            todo.extend([(a, i), (i, b)])
    return [points[i] for i in sorted(keep)]


def rounded(points):
    """Trim each corner and join its tangents with a bounded quadratic arc.

    Curves stay inside each three-point hull (no spline overshoot or added
    loops). Sampling at <=0.25 fields keeps drawing and placement identical.
    """
    out = [points[0]]
    for previous, p, following in zip(points, points[1:], points[2:]):
        incoming, outgoing = math.dist(previous, p), math.dist(p, following)
        if not incoming or not outgoing:
            continue
        cut = min(incoming*.45, outgoing*.45, 10)
        a = [p[i]+(previous[i]-p[i])*cut/incoming for i in [0, 1]]
        b = [p[i]+(following[i]-p[i])*cut/outgoing for i in [0, 1]]
        out.append(a)
        steps = max(8, math.ceil(2*cut/.25))
        for n in range(1, steps+1):
            t = n/steps
            out.append([(1-t)**2*a[i]+2*(1-t)*t*p[i]+t*t*b[i] for i in [0, 1]])
    out.append(points[-1])
    clean = []
    for p in out:
        p = [round(v, 4) for v in p]
        if not clean or p != clean[-1]:
            clean.append(p)
    # Sub-pixel flattening error at every supported zoom, without thousands of
    # redundant segments in each collision bucket on mobile devices.
    return simplify(clean, .01)


selected = [r for r in source['rivers'] if r['sourceName'] in KEEP]
anchors = {tuple(p) for r in selected for p in [r['points'][0], r['points'][-1]]}
rivers = []
for river in selected:
    raw = river['points']
    stops = sorted({0, len(raw)-1} | {i for i, p in enumerate(raw) if tuple(p) in anchors})
    points = []
    for a, b in zip(stops, stops[1:]):
        part = rounded(simplify(raw[a:b+1]))
        points.extend(part[1:] if points else part)
    rivers.append({**river, 'points': points, 'width': WIDTH,
                   'bounds': [[min(p[i] for p in points) for i in [0, 1]],
                              [max(p[i] for p in points) for i in [0, 1]]]})

visits = []
for visit in source['visits']:
    if visit['id'].startswith('river-'):
        name = visit['id'][6:]
        candidates = [nearest(visit['point'], a, b) for r in rivers
                      if r['sourceName'].replace(' ', '-') == name
                      for a, b in zip(r['points'], r['points'][1:])]
        if not candidates:
            continue
        p = min(candidates, key=lambda p: sum((v-w)**2 for v, w in zip(p, visit['point'])))
        visits.append({**visit, 'point': p})
    else:
        visits.append(visit)

data = {**source, 'rivers': rivers, 'visits': visits,
        'design': 'Fictional gently curved game rivers based on official courses; lake shorelines retained',
        'selection': {**source['selection'], 'riverNames': sorted(KEEP)},
        'curves': {'simplificationTolerance': TOLERANCE, 'maximumCornerCut': 10, 'sampleStep': .25, 'flatteningTolerance': .01},
        'widths': 'All game rivers are two fields wide, with no extra blocked riverbanks'}
out = DIR / 'game-hydrology.json'
out.write_text(json.dumps(data, ensure_ascii=False, separators=(',', ':')), encoding='utf-8')
print(json.dumps({'riverLinks': len(rivers), 'points': sum(len(r['points']) for r in rivers),
                  'lakes': len(data['lakes']), 'bytes': out.stat().st_size}))
