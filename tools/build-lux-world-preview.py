"""Build static projected boundaries and small copies of approved world artwork.

Input: artifacts/realm-map/geodata.json (ACT / SIG-GR 2026, CC BY 4.0).
Only size/encoding changes are applied to artwork. Originals are preserved.
"""
import json
import math
from pathlib import Path
from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "assets/world-lux-preview"
geo = json.loads((ROOT / "artifacts/realm-map/geodata.json").read_text(encoding="utf-8"))
factor = math.cos(math.radians(49.8))

def rings(feature):
    c = feature["geometry"]["coordinates"]
    return [r for polygon in c for r in polygon] if feature["geometry"]["type"] == "MultiPolygon" else c

points = [p for f in geo["cantons"]["features"] for r in rings(f) for p in r]
x0, x1 = min(p[0] for p in points), max(p[0] for p in points)
y0, y1 = min(p[1] for p in points), max(p[1] for p in points)
scale = min(1400 / ((x1-x0)*factor), 1900 / (y1-y0))
ox = (1600-(x1-x0)*factor*scale)/2
oy = (2100-(y1-y0)*scale)/2

def project(point):
    return [round(ox+(point[0]-x0)*factor*scale, 2), round(oy+(y1-point[1])*scale, 2)]

def convert(feature):
    all_points = [project(p) for r in rings(feature) for p in r]
    path = "".join("M" + "L".join(",".join(str(v) for v in project(p)) for p in r) + "Z" for r in rings(feature))
    return {**feature["properties"], "point": project(feature["properties"]["point"]), "path": path,
            "bounds": [[min(p[0] for p in all_points),min(p[1] for p in all_points)],
                       [max(p[0] for p in all_points),max(p[1] for p in all_points)]]}

OUT.mkdir(parents=True, exist_ok=True)
data = {kind:[convert(f) for f in geo[kind]["features"]] for kind in ["cantons", "communes"]}
data["size"] = [1600,2100]
data["source"] = "ACT Luxembourg / SIG-GR 2026 · CC BY 4.0"
(OUT/"geography.json").write_text(json.dumps(data, ensure_ascii=False, separators=(",",":")), encoding="utf-8")

assets = {
    "terrain": ("fantasy-village-v1/world-terrain-v3-fewer-trees.png",1536),
    "oak": ("fantasy-village-v1/world-tree-oak-v7.png",192),
    "pine": ("fantasy-village-v1/world-tree-pine-v7.png",192),
    "castle": ("fantasy-village-v1/world-castle-v2.png",256),
    "crown": ("map/castle-default.png",320),
    "alliance": ("map/alliance-center-v3.webp",256),
    "farm": ("fantasy-village-v1/world-farm-v8.png",160),
    "lumber": ("fantasy-village-v1/world-lumber-v8.png",160),
    "quarry": ("fantasy-village-v1/world-quarry-v8.png",160),
    "gold": ("fantasy-village-v1/world-gold-v2.png",160),
    "crystal": ("fantasy-village-v1/world-crystal-v8.png",160),
    "tower": ("fantasy-village-v1/watch_tower.png",192),
    "dungeon": ("map/shrine-forest.webp",224),
    "mountain": ("map/scenery-mountain.png",192),
    "rocks": ("map/scenery-rocks.png",128),
    "orc": ("monsters/2.5d/bright-v2/orc.png",192),
    "golem": ("monsters/2.5d/bright-v2/golem.png",192),
}
(OUT/"art").mkdir(exist_ok=True)
for name,(source,size) in assets.items():
    with Image.open(ROOT/"assets/art"/source) as image:
        image = image.convert("RGBA")
        image.thumbnail((size,size),Image.Resampling.LANCZOS)
        image.save(OUT/"art"/(name+".webp"),format="WEBP",quality=83,method=6)
print(json.dumps({"cantons":len(data["cantons"]),"communes":len(data["communes"]),"bytes":sum(p.stat().st_size for p in OUT.rglob("*") if p.is_file())}))
