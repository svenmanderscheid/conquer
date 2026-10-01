"""Build a game-only partition on a four-field grid; preserve official geography.

No runtime dependencies: the browser receives closed polygons and a compact cell
assignment. All neighbours share the same edges; communes partition their canton.
"""
import json
from collections import defaultdict, deque
from pathlib import Path
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'assets/world-lux-preview'
source = json.loads((OUT / 'geography.json').read_text(encoding='utf-8'))
STEP, WIDTH, HEIGHT = 4, 768, 1100
COLS, ROWS = WIDTH // STEP, HEIGHT // STEP
SCALE = min(WIDTH / 1326.82, HEIGHT / 1900)


def field(p):
    return [(p[0] - 136.59) * SCALE, (p[1] - 100) * SCALE]


def source_rings(f):
    return [[field(list(map(float, p.split(',')))) for p in ring.rstrip('Z').split('L')]
            for ring in f['path'].split('M')[1:]]


yy, xx = np.mgrid[:ROWS, :COLS]
px, py = (xx + .5) * STEP, (yy + .5) * STEP
original = np.zeros((ROWS, COLS), dtype=np.int16)
for label, f in enumerate(source['communes'], 1):
    lo, hi = map(field, f['bounds'])
    mask = (px >= lo[0]) & (px <= hi[0]) & (py >= lo[1]) & (py <= hi[1])
    x, y = px[mask], py[mask]
    inside = np.zeros(x.shape, dtype=bool)
    for ring in source_rings(f):
        for a, b in zip(ring, ring[1:] + ring[:1]):
            if a[1] != b[1]:
                inside ^= ((a[1] > y) != (b[1] > y)) & (x < (b[0]-a[0])*(y-a[1])/(b[1]-a[1])+a[0])
    selected = np.flatnonzero(mask)[inside]
    original.flat[selected] = label

canton_ids = [f['id'] for f in source['cantons']]
parents = np.array([0] + [canton_ids.index(f['canton']) + 1 for f in source['communes']])


def counts(mask, radius=3):
    a = np.pad(mask.astype(np.int32), radius)
    a = np.pad(a, ((1, 0), (1, 0))).cumsum(0).cumsum(1)
    n = radius * 2 + 1
    return a[n:, n:] - a[:-n, n:] - a[n:, :-n] + a[:-n, :-n]


def smooth(grid, labels, allowed=None, iterations=4):
    for _ in range(iterations):
        best = np.zeros(grid.shape, dtype=np.int32)
        result = grid.copy()
        # Retain the current owner on ties to avoid biased boundary drift.
        scores = [(label, counts(grid == label)) for label in labels]
        for label, score in scores:
            best[grid == label] = score[grid == label]
        for label, score in scores:
            wins = score > best
            if allowed is not None:
                wins &= allowed == parents[label]
            result[wins], best[wins] = label, score[wins]
        grid = result
    return grid


cantons = smooth(parents[original], range(13))
communes = original.copy()
communes[parents[communes] != cantons] = 0
communes[cantons == 0] = 0
# Changed strips inherit the closest original commune within their new canton.
for c in range(1, 13):
    empty = (cantons == c) & (communes == 0)
    if not empty.any():
        continue
    ex, ey = px[empty], py[empty]
    dist = np.full(ex.shape, np.inf)
    owner = np.zeros(ex.shape, dtype=np.int16)
    for label in np.flatnonzero(parents == c):
        sx, sy = field(source['communes'][label-1]['point'])
        d = (ex-sx)**2 + (ey-sy)**2
        wins = d < dist
        owner[wins], dist[wins] = label, d[wins]
    communes[empty] = owner
communes = smooth(communes, range(1, 101), allowed=cantons, iterations=3)


def components(grid, label):
    remaining = set(zip(*np.where(grid == label)))
    result = []
    while remaining:
        p = min(remaining)
        remaining.remove(p)
        queue, part = deque([p]), [p]
        while queue:
            y, x = queue.popleft()
            for n in [(y-1, x), (y, x+1), (y+1, x), (y, x-1)]:
                if n in remaining:
                    remaining.remove(n)
                    queue.append(n)
                    part.append(n)
        result.append(part)
    return sorted(result, key=len, reverse=True)


# Remove tiny disconnected remnants, then grow adjoining communes into them.
for label in range(1, 101):
    parts = components(communes, label)
    assert parts, f'Commune {label} disappeared'
    for part in parts[1:]:
        for y, x in part:
            communes[y, x] = 0
pending = set(zip(*np.where((cantons > 0) & (communes == 0))))
while pending:
    changed = []
    for y, x in sorted(pending):
        neighbours = [int(communes[ny, nx]) for ny, nx in [(y-1,x),(y,x+1),(y+1,x),(y,x-1)]
                      if 0 <= ny < ROWS and 0 <= nx < COLS and communes[ny,nx] > 0
                      and parents[communes[ny,nx]] == cantons[y,x]]
        if neighbours:
            communes[y,x] = max(set(neighbours), key=lambda n: (neighbours.count(n), -n))
            changed.append((y,x))
    assert changed, 'Unreachable fragment'
    pending.difference_update(changed)
for label in range(1,101):
    assert len(components(communes,label)) == 1
assert np.array_equal(parents[communes],cantons)
for label in range(1,13):
    assert len(components(cantons,label)) == 1


def outlines(grid, label):
    edges = defaultdict(set)
    for y, x in zip(*np.where(grid == label)):
        for nx, ny, a, b in [(x,y-1,(x,y),(x+1,y)),(x+1,y,(x+1,y),(x+1,y+1)),
                              (x,y+1,(x+1,y+1),(x,y+1)),(x-1,y,(x,y+1),(x,y))]:
            if not (0 <= nx < COLS and 0 <= ny < ROWS) or grid[ny,nx] != label:
                edges[a].add(b)
    rings = []
    direction = {(1,0):0,(0,1):1,(-1,0):2,(0,-1):3}
    while edges:
        start = min(edges)
        p, ring, incoming = start, [], 0
        while True:
            ring.append(p)
            def priority(q):
                d = direction[(q[0]-p[0],q[1]-p[1])]
                return {1:0,0:1,3:2,2:3}[(d-incoming)%4]
            q = min(edges[p],key=priority)
            incoming = direction[(q[0]-p[0],q[1]-p[1])]
            edges[p].remove(q)
            if not edges[p]:
                del edges[p]
            p = q
            if p == start:
                break
        clean = []
        for i,p in enumerate(ring):
            a,b = ring[i-1],ring[(i+1)%len(ring)]
            if (p[0]-a[0])*(b[1]-p[1]) != (p[1]-a[1])*(b[0]-p[0]):
                clean.append([int(p[0])*STEP,int(p[1])*STEP])
        rings.append(clean)
    return rings


def feature(f, grid, label):
    rs = outlines(grid,label)
    points = [p for r in rs for p in r]
    # A representative point is inside its new region, near the familiar place.
    tx,ty = field(f['point'])
    ys,xs = np.where(grid == label)
    index = np.argmin((xs*STEP+STEP/2-tx)**2+(ys*STEP+STEP/2-ty)**2)
    return {**{k:v for k,v in f.items() if k not in ['path','bounds','point']},
            'point':[int(xs[index])*STEP+STEP/2,int(ys[index])*STEP+STEP/2],
            'rings':rs,'bounds':[[min(p[0] for p in points),min(p[1] for p in points)],
                                [max(p[0] for p in points),max(p[1] for p in points)]],
            'fields':int(len(xs))*STEP*STEP}


runs=[]
for row in communes:
    run=[]
    for value in row:
        value=int(value)
        if run and run[-2] == value:
            run[-1]+=1
        else:
            run.extend([value,1])
    runs.append(run)
data={'units':'fields','grid':{'step':STEP,'width':COLS,'height':ROWS,'rows':runs},
      'cantons':[feature(f,cantons,i) for i,f in enumerate(source['cantons'],1)],
      'communes':[feature(f,communes,i) for i,f in enumerate(source['communes'],1)],
      'source':source['source'],'design':'Fictional smoothed game regions; not administrative boundaries'}
(OUT/'game-geography.json').write_text(json.dumps(data,ensure_ascii=False,separators=(',',':')),encoding='utf-8')
print(json.dumps({'cantons':12,'communes':100,'fields':int((communes>0).sum())*16,
                  'changed_grid_cells':int((original!=communes).sum()),'bytes':(OUT/'game-geography.json').stat().st_size}))
