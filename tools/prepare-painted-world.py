"""Pack approved imagegen pose sheets; no artwork is drawn by this tool.

Usage: python tools/prepare-painted-world.py <source-manifest.json>
Registration uses the fixed lower foundation. Only the recorded signature
regions change; the rest of the building is copied exactly from the first pose.
"""
from pathlib import Path
import hashlib
import json
import sys
import numpy as np
from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / 'assets/art/map/painted-v2'
SIZE = 512
ORDER = [0, 1, 2, 3, 2, 1]
INSETS = {'castle-leviathan': 24, 'castle-yggdrasil': 24, 'castle-tempest': 24}
REGIONS = {
    'congress': [(0.39, 0, 0.63, 0.21)],
    'shrine-forest': [(0.05, 0, 0.94, 0.46)],
    'shrine-ice': [(0.35, 0.26, 0.65, 0.64)],
    'shrine-lava': [(0.29, 0.05, 0.76, 0.56)],
    'shrine-sand': [(0.30, 0, 0.68, 0.35)],
    'castle-ironkeep': [(0.31, 0, 0.68, 0.35)],
    'castle-rosehall': [(0.54, 0, 0.88, 0.22)],
    'castle-sandspire': [(0.42, 0, 0.73, 0.34)],
    'castle-tidewatch': [(0.43, 0.19, 0.66, 0.30)],
    'castle-winterhold': [(0.48, 0, 0.67, 0.26)],
    'castle-jadecourt': [(0.40, 0.23, 0.58, 0.38)],
    'castle-emberforge': [(0.35, 0, 0.65, 0.45)],
    'castle-ravenloft': [(0.37, 0, 0.71, 0.26)],
    'castle-clockwork': [(0.55, 0.35, 0.79, 0.60)],
    'castle-sapphire': [(0.33, 0.01, 0.78, 0.33)],
    'castle-phoenix': [(0.22, 0, 0.99, 0.49)],
    'castle-astral': [(0.34, 0.09, 0.78, 0.36)],
    'castle-leviathan': [(0.30, 0, 1, 0.46)],
    'castle-yggdrasil': [(0.07, 0, 1, 0.42)],
    'castle-tempest': [(0.15, 0, 0.91, 0.47)],
    'castle-eclipse': [(0.33, 0.01, 0.82, 0.41)],
    'castle-dragon': [(0.05, 0.43, 0.20, 0.62), (0.23, 0.51, 0.35, 0.74), (0.50, 0.55, 0.64, 0.83), (0.76, 0.51, 0.99, 0.84)],
}


def foundation(image):
    alpha = np.asarray(image.getchannel('A'))
    ys, xs = np.where((alpha > 192) & (np.indices(alpha.shape)[0] >= SIZE * .62))
    if len(xs) < 50:
        raise ValueError('No fixed foundation detected')
    return (float(xs.min()), float(ys.min()), float(xs.max()), float(ys.max()))


def registered_frames(source, inset=0):
    if source.width % 2 or source.height % 2:
        raise ValueError('Pose sheet requires equal 2x2 cells')
    cw, ch = source.width // 2, source.height // 2
    frames = [source.crop((i % 2 * cw, i // 2 * ch, (i % 2 + 1) * cw, (i // 2 + 1) * ch)).resize((SIZE, SIZE), Image.Resampling.LANCZOS) for i in range(4)]
    ref = foundation(frames[0])
    framing = 1 - 2 * inset / SIZE
    first = frames[0].transform((SIZE, SIZE), Image.Transform.AFFINE, (1 / framing, 0, -inset / framing, 0, 1 / framing, -inset / framing), Image.Resampling.BICUBIC) if inset else frames[0]
    registered, transforms = [first], [[framing, inset, inset]]
    for frame in frames[1:]:
        box = foundation(frame)
        scale = (ref[2] - ref[0]) / (box[2] - box[0])
        if not .90 < scale < 1.10:
            raise ValueError('Pose scale drift requires manual source review')
        dx = (ref[0] + ref[2] - scale * (box[0] + box[2])) / 2
        dy = ref[3] - scale * box[3]
        scale, dx, dy = scale * framing, dx * framing + inset, dy * framing + inset
        image = frame.transform((SIZE, SIZE), Image.Transform.AFFINE, (1 / scale, 0, -dx / scale, 0, 1 / scale, -dy / scale), Image.Resampling.BICUBIC)
        registered.append(image)
        transforms.append([round(scale, 6), round(dx, 3), round(dy, 3)])
    return registered, transforms


def pack(entry):
    name = entry['id']
    source_path = Path(entry['source'])
    if not source_path.is_absolute():
        source_path = ROOT / source_path
    if not source_path.exists():
        return None
    source = Image.open(source_path).convert('RGBA')
    if source.getchannel('A').getextrema() != (0, 255):
        raise ValueError(name + ': missing full transparency')
    source_hash = hashlib.sha256(source_path.read_bytes()).hexdigest()
    if name == 'alliance-center':
        source.thumbnail((SIZE, SIZE), Image.Resampling.LANCZOS)
        temporary = OUTPUT / (name + '.pending.webp')
        source.save(temporary, quality=86, method=6, exact=True)
        temporary.replace(OUTPUT / (name + '.webp'))
        return {'id': name, 'sourceSha256': source_hash, 'sourceFile': source_path.name, 'prompt': entry['prompt'], 'stillOnly': True}
    inset = INSETS.get(name, 0)
    frames, transforms = registered_frames(source, inset)
    mask = Image.new('L', (SIZE, SIZE))
    draw = ImageDraw.Draw(mask)
    for region in REGIONS[name]:
        draw.rectangle(tuple(round(v * (SIZE - 2 * inset) + inset) for v in region), fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(.7))
    poses = [frames[0]] + [Image.composite(frame, frames[0], mask) for frame in frames[1:]]
    # Pixel-identical architecture outside the generated signature layer.
    outside = np.asarray(mask) == 0
    for pose in poses[1:]:
        if np.any(np.asarray(pose)[outside] != np.asarray(poses[0])[outside]):
            raise ValueError(name + ': stationary masonry changed')
    poses[0].save(OUTPUT / (name + '.png'), optimize=True)
    loop = [poses[i] for i in ORDER]
    temporary = OUTPUT / (name + '.pending.webp')
    loop[0].save(temporary, save_all=True, append_images=loop[1:], duration=450, loop=0, quality=84, method=6, exact=True, minimize_size=True)
    temporary.replace(OUTPUT / (name + '.webp'))
    diffs = [float(np.mean(np.any(np.asarray(pose) != np.asarray(poses[0]), axis=2))) for pose in poses[1:]]
    if max(diffs) < .0002:
        raise ValueError(name + ': no visible signature motion')
    return {'id': name, 'sourceSha256': source_hash, 'sourceFile': source_path.name, 'prompt': entry['prompt'], 'frameSize': SIZE, 'poseOrder': ORDER, 'frameDurationMs': 450, 'framingInset': inset, 'registration': transforms, 'motionRegions': REGIONS[name], 'changedPixelFractions': diffs, 'stationaryPixelsUnchanged': True}


def main():
    entries = json.loads(Path(sys.argv[1]).read_text(encoding='utf-8-sig'))
    OUTPUT.mkdir(parents=True, exist_ok=True)
    selected = set(sys.argv[2].split(',')) if len(sys.argv) > 2 else None
    previous = json.loads((OUTPUT / 'manifest.json').read_text(encoding='utf-8'))['assets'] if selected and (OUTPUT / 'manifest.json').exists() else []
    records = [record for record in previous if record['id'] not in selected] if selected else []
    for entry in entries:
        if selected and entry['id'] not in selected:
            continue
        record = pack(entry)
        if record:
            records.append(record)
            print('Packed', record['id'], flush=True)
    for record in records:
        record['outputs'] = [{'file': path.name, 'bytes': path.stat().st_size, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest()} for path in OUTPUT.glob(record['id'] + '.*') if path.suffix in ['.png', '.webp']]
    (OUTPUT / 'manifest.json').write_text(json.dumps({'date': '2026-10-05', 'generator': 'Built-in image_gen; deterministic crop/registration/pose-layer packing only', 'assets': records}, indent=2) + '\n', encoding='utf-8')


if __name__ == '__main__':
    main()
