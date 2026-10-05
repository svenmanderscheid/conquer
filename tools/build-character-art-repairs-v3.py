"""Verify/rebuild the approved 2026-10-05 troop and march deliveries.

Raw generated source PNGs are retained locally, not shipped to browsers or Git.
Requires Pillow; image generation itself is performed with built-in imagegen.
This script only extracts complete poses, resizes, composes, and encodes them.

  python tools/build-character-art-repairs-v3.py
  python tools/build-character-art-repairs-v3.py --rebuild --source-dir /path/to/masters
  python tools/build-character-art-repairs-v3.py --rebuild --output-root output/art-rebuild
"""
from __future__ import annotations
import argparse
import hashlib
import json
import shutil
from pathlib import Path
from PIL import Image, ImageChops, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
TROOPS = ROOT / 'assets/art/characters/fantasy-troops-v3/manifest.json'
MARCHES = ROOT / 'assets/art/marches/image-repairs-v3.manifest.json'

def read(path):
    return json.loads(path.read_text(encoding='utf-8'))

def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def alpha_bounds(image):
    return image.getchannel('A').point(lambda value: 255 if value > 8 else 0).getbbox()

def save(image, path, **kwargs):
    path.parent.mkdir(parents=True, exist_ok=True)
    pending = path.with_suffix(path.suffix + '.tmp')
    image.save(pending, format='PNG' if path.suffix == '.png' else 'WEBP', **kwargs)
    pending.replace(path)

def build_troops(manifest, sources, target):
    originals = ROOT / 'assets/art/characters/fantasy-troops-v2'
    for item in manifest['delivery']:
        path = target / item['path']
        path.parent.mkdir(parents=True, exist_ok=True)
        if path.name.startswith('guardian'):
            shutil.copy2(originals / path.name, path)
            continue
        ident, kind = path.stem.rsplit('-', 1)
        source = Image.open(sources / f'{ident}.png').convert('RGBA')
        crop = source.crop(alpha_bounds(source))
        original = Image.open(originals / path.name).convert('RGBA')
        width, height = original.size
        box = alpha_bounds(original)
        avail_width = width - 2 * min(box[0], width - box[2])
        avail_height = box[3] - box[1]
        scale = min(avail_width / crop.width, avail_height / crop.height)
        size = (round(crop.width * scale), round(crop.height * scale))
        sprite = crop.resize(size, Image.Resampling.LANCZOS)
        canvas = Image.new('RGBA', original.size)
        canvas.alpha_composite(sprite, ((width-size[0])//2, box[1]+(avail_height-size[1])//2))
        save(canvas, path, quality=90 if kind == 'ui' else 86, method=6)

def isolated_cell(sheet, index, config):
    columns, rows = config['grid']
    left = round(index % columns * sheet.width / columns) - config['overscan_px']
    top = round(index // columns * sheet.height / rows) - config['overscan_px']
    size = config['window_size']
    cell = sheet.crop((left, top, left + size, top + size))
    alpha = cell.getchannel('A')
    binary = alpha.point(lambda value: 255 if value > 0 else 0)
    center = (254, 254)
    if binary.getpixel(center):
        seed = center
    else:
        pixels = binary.load()
        box = binary.getbbox()
        seed = min(((x, y) for y in range(box[1], box[3]) for x in range(box[0], box[2]) if pixels[x,y]), key=lambda p: (p[0]-254)**2 + (p[1]-254)**2)
    component = binary.copy()
    ImageDraw.floodfill(component, seed, 128, thresh=0)
    keep = component.point(lambda value: 255 if value == 128 else 0)
    cell.putalpha(ImageChops.multiply(alpha, keep))
    return cell

def build_flight(item, sources, target):
    sheet = Image.open(sources / item['source_master_filename']).convert('RGBA')
    config = item['extraction']
    frames = []
    for registration in item['registration']:
        cell = isolated_cell(sheet, registration['pose'], config)
        scale = config['scale']
        cell = cell.resize((round(cell.width*scale), round(cell.height*scale)), Image.Resampling.LANCZOS)
        frame = Image.new('RGBA', tuple(item['dimensions']))
        frame.alpha_composite(cell, (round(config['origin'][0]-registration['dx']*scale), round(config['origin'][1]-registration['dy']*scale)))
        frames.append(frame)
    save(frames[0], target/item['static'], optimize=True)
    sequence = [frames[i] for i in item['order']]
    save(sequence[0], target/item['animated'], save_all=True, append_images=sequence[1:], duration=item['duration_ms'], loop=0, quality=86, method=6, allow_mixed=True, minimize_size=True)

def build_tempest(item, sources, target):
    sheet = Image.open(sources / item['source_master_filename']).convert('RGBA')
    config = item['extraction']; size = config['cell_size']; anchors = item['anchors']
    min_x = min(-p[0] for p in anchors); min_y = min(-p[1] for p in anchors)
    max_x = max(size-p[0] for p in anchors); max_y = max(size-p[1] for p in anchors)
    scale = min(config['available_size']/(max_x-min_x), config['available_size']/(max_y-min_y))
    origin = (config['outer_padding']-min_x*scale, config['outer_padding']-min_y*scale)
    frames = []
    for index, (cx, top) in zip(item['selected_poses'], anchors):
        x = index % 2 * size; y = index // 2 * size
        cell = sheet.crop((x, y, x+size, y+size)).resize((round(size*scale), round(size*scale)), Image.Resampling.LANCZOS)
        frame = Image.new('RGBA', tuple(item['static_dimensions']))
        frame.alpha_composite(cell, (round(origin[0]-cx*scale), round(origin[1]-top*scale)))
        frames.append(frame)
    save(frames[0], target/item['static'], quality=90, method=6)
    sequence = [frames[i].resize(tuple(item['animated_dimensions']), Image.Resampling.LANCZOS) for i in item['loop_order']]
    save(sequence[0], target/item['animated'], save_all=True, append_images=sequence[1:], loop=0, duration=item['duration_ms'], quality=86, method=6, allow_mixed=True, minimize_size=True)

def build_default(item, target):
    canvas = Image.new('RGBA', tuple(item['size']))
    for placement in item['placements']:
        relative = Path('assets/art/characters/fantasy-troops-v3') / f"{placement['id']}-ui.webp"
        path = target / relative
        if not path.exists():
            path = ROOT / relative
        sprite = Image.open(path).convert('RGBA')
        sprite = sprite.crop(alpha_bounds(sprite))
        height = placement['height']
        sprite = sprite.resize((round(sprite.width*height/sprite.height), height), Image.Resampling.LANCZOS)
        canvas.alpha_composite(sprite, (placement['x'], placement['y']))
    save(canvas, target/item['path'], quality=90, method=6)

def verify(delivery, target):
    failures = []
    for item in delivery:
        path = target/item['path']
        if not path.is_file() or digest(path) != item['sha256']:
            failures.append(item['path'])
    if failures:
        raise SystemExit('Delivery hash mismatch: ' + ', '.join(failures))
    print(f'PASS: {len(delivery)} exact delivery hashes; {sum((target/i["path"]).stat().st_size for i in delivery):,} bytes')

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--rebuild', action='store_true', help='Rebuild before verifying; default is read-only verification.')
    parser.add_argument('--source-dir', type=Path, default=ROOT/'output/image-fixes-2026-10-05/characters/masters')
    parser.add_argument('--output-root', type=Path, default=ROOT, help='Directory containing assets/ for delivery/rebuild.')
    args = parser.parse_args()
    troop = read(TROOPS); march = read(MARCHES)
    if args.rebuild:
        for source in [*troop['sources'], *march['flights'], march['tempest']]:
            path = args.source_dir/source['source_master_filename']
            if not path.is_file() or digest(path) != source['source_master_sha256']:
                raise SystemExit(f'Missing or changed approved source: {path}')
        build_troops(troop, args.source_dir, args.output_root)
        for item in march['flights']:
            build_flight(item, args.source_dir, args.output_root)
        build_tempest(march['tempest'], args.source_dir, args.output_root)
        build_default(march['default_march'], args.output_root)
    verify([*troop['delivery'], *march['delivery']], args.output_root)

if __name__ == '__main__':
    main()
