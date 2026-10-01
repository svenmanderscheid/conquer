"""Size/encode existing approved artwork for the isolated map preview."""
from pathlib import Path
from PIL import Image
root=Path(__file__).resolve().parents[1]
out=root/'assets/world-lux-preview/art'
for name,source,size in [
    ('tile-0','fantasy-village-v1/world-tile-0.png',768),
    ('tile-1','fantasy-village-v1/world-tile-1.png',768),
    ('water','map/castle-water.webp',320),
    ('fire','map/castle-fire.webp',320),
    ('shrine','map/shrine-forest.webp',320),
]:
    with Image.open(root/'assets/art'/source) as im:
        im=im.convert('RGBA');im.thumbnail((size,size),Image.Resampling.LANCZOS)
        im.save(out/(name+'.webp'),quality=85,method=6)
print('Five approved sprites resized and encoded; originals unchanged.')
