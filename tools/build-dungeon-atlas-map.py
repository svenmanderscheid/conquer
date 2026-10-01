"""Build local SVG geometry for the visual dungeon concept (no runtime map service).

Source: ACT Luxembourg / SIG-GR 2026, CC BY 4.0.
https://data.public.lu/en/datasets/cantons-in-luxembourg-2026/
Usage: python tools/build-dungeon-atlas-map.py path/to/cantons-lux-2026.geojson
"""
import json
import math
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[1]
source = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
factor = math.cos(math.radians(49.8))
points = [(x * factor, y) for f in source["features"] for ring in f["geometry"]["coordinates"] for x, y in ring]
min_x, max_x = min(x for x, y in points), max(x for x, y in points)
min_y, max_y = min(y for x, y in points), max(y for x, y in points)
scale = 700 / (max_y - min_y)
offset = (650 - (max_x - min_x) * scale) / 2
features = []
for feature in source["features"]:
    rings = []
    for ring in feature["geometry"]["coordinates"]:
        projected = [(round(offset + (x * factor - min_x) * scale, 1), round(45 + (max_y - y) * scale, 1)) for x, y in ring]
        rings.append([point for i, point in enumerate(projected) if i == 0 or point != projected[i - 1]])
    outer = rings[0]
    cross = [(a[0] * b[1] - b[0] * a[1]) for a, b in zip(outer, outer[1:])]
    area = sum(cross)
    center = [round(sum((a[i] + b[i]) * c for a, b, c in zip(outer, outer[1:], cross)) / (3 * area), 1) for i in range(2)]
    path = " ".join("M" + "L".join(f"{x},{y}" for x, y in ring) + "Z" for ring in rings)
    features.append({"id": feature["properties"]["code"], "name": feature["properties"]["name"], "center": center, "path": path})
    print(feature["properties"]["name"], center)
output = {"source": "ACT Luxembourg / SIG-GR 2026", "license": "CC BY 4.0", "sourceUrl": "https://data.public.lu/en/datasets/cantons-in-luxembourg-2026/", "viewBox": [0, 0, 650, 800], "features": features}
(root / "assets/dungeon-preview/cantons.json").write_text(json.dumps(output, ensure_ascii=False, separators=(",", ":")), encoding="utf-8")
