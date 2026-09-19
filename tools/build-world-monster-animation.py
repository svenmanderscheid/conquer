"""Build subtle, looping world-map animations from the approved monster concept sheet.

The source remains untouched.  Each character gets a stable 256 px marker canvas,
a neutral PNG pose for reduced-motion mode, and a short animated WebP loop.
"""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw, ImageFilter


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "assets" / "art" / "concepts" / "world-monsters-v2.png"
OUTPUT = ROOT / "assets" / "art" / "map"
WORK_SIZE = 512
FINAL_SIZE = 256
FRAME_COUNT = 40
FRAME_MS = 50

# Transparent gaps in the generated sheet make these cuts deterministic.
CROPS = {
    "orc": (37, 8, 610, 708),
    "skeleton": (626, 8, 1035, 690),
    "golem": (1048, 8, 1656, 724),
    "goblin": (1687, 50, 2138, 724),
}


def normalize(source: Image.Image, box: tuple[int, int, int, int]) -> Image.Image:
    sprite = source.crop(box)
    alpha_box = sprite.getchannel("A").getbbox()
    if alpha_box:
        sprite = sprite.crop(alpha_box)
    max_width, max_height = 474, 474
    scale = min(max_width / sprite.width, max_height / sprite.height)
    size = (round(sprite.width * scale), round(sprite.height * scale))
    sprite = sprite.resize(size, Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (WORK_SIZE, WORK_SIZE))
    x = (WORK_SIZE - sprite.width) // 2
    y = 496 - sprite.height
    canvas.alpha_composite(sprite, (x, y))
    return canvas


def anchored_transform(
    image: Image.Image,
    *,
    scale_x: float = 1.0,
    scale_y: float = 1.0,
    shift_x: float = 0.0,
    lift: float = 0.0,
) -> Image.Image:
    """Scale around the feet, keeping the marker footprint visually fixed."""
    width = max(1, round(WORK_SIZE * scale_x))
    height = max(1, round(WORK_SIZE * scale_y))
    resized = image.resize((width, height), Image.Resampling.BICUBIC)
    result = Image.new("RGBA", image.size)
    x = round((WORK_SIZE - width) / 2 + shift_x)
    y = round(WORK_SIZE - height - lift)
    result.alpha_composite(resized, (x, y))
    return result


def articulated_part(
    image: Image.Image,
    polygon: list[tuple[int, int]],
    *,
    angle: float,
    pivot: tuple[int, int],
    shift: tuple[float, float] = (0, 0),
) -> Image.Image:
    """Move one softly masked prop while leaving the rest of the body in place."""
    mask = Image.new("L", image.size)
    ImageDraw.Draw(mask).polygon(polygon, fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(1.4))
    part = Image.new("RGBA", image.size)
    part.paste(image, mask=mask)

    remaining = image.copy()
    remaining.putalpha(ImageChops.subtract(image.getchannel("A"), mask))
    moved = part.rotate(
        angle,
        resample=Image.Resampling.BICUBIC,
        center=pivot,
        fillcolor=(0, 0, 0, 0),
    )
    if shift != (0, 0):
        shifted = Image.new("RGBA", image.size)
        shifted.alpha_composite(moved, (round(shift[0]), round(shift[1])))
        moved = shifted
    return Image.alpha_composite(remaining, moved)


def rune_glow(image: Image.Image, strength: float) -> Image.Image:
    """Pulse only the cyan magical paint already present on the golem."""
    pixels = image.load()
    mask = Image.new("L", image.size)
    mask_pixels = mask.load()
    for y in range(image.height):
        for x in range(image.width):
            r, g, b, a = pixels[x, y]
            if a and b > 105 and g > 85 and b > r * 1.12 and g > r * 0.92:
                mask_pixels[x, y] = min(255, round(a * strength))
    glow_mask = mask.filter(ImageFilter.GaussianBlur(7))
    glow = Image.new("RGBA", image.size, (80, 224, 241, 0))
    glow.putalpha(glow_mask)
    return Image.alpha_composite(image, glow)


def render(base: Image.Image, kind: str, frame: int) -> Image.Image:
    phase = math.tau * frame / FRAME_COUNT
    breath = math.sin(phase)

    if kind == "orc":
        image = anchored_transform(
            base,
            scale_x=1 - 0.004 * breath,
            scale_y=1 + 0.010 * breath,
            shift_x=2.2 * math.sin(phase - 0.35),
        )
        image = articulated_part(
            image,
            [(8, 65), (190, 65), (205, 350), (10, 390)],
            angle=2.0 * math.sin(phase - 0.7),
            pivot=(174, 280),
            shift=(0, 1.3 * math.sin(phase - 0.7)),
        )
    elif kind == "skeleton":
        rattle = math.sin(phase * 2) * 1.2
        image = anchored_transform(
            base,
            scale_x=1 - 0.003 * breath,
            scale_y=1 + 0.007 * breath,
            shift_x=2.0 * math.sin(phase + 0.4) + rattle,
        )
        image = articulated_part(
            image,
            [(20, 85), (190, 70), (220, 370), (15, 405)],
            angle=2.6 * math.sin(phase * 2 + 0.5),
            pivot=(205, 285),
        )
        image = articulated_part(
            image,
            [(290, 190), (505, 170), (510, 445), (280, 450)],
            angle=-1.8 * math.sin(phase - 0.4),
            pivot=(305, 300),
        )
    elif kind == "golem":
        heavy = math.sin(phase)
        image = anchored_transform(
            base,
            scale_x=1 - 0.006 * heavy,
            scale_y=1 + 0.012 * heavy,
            shift_x=1.2 * math.sin(phase - 0.2),
        )
        image = rune_glow(image, 0.16 + 0.16 * (0.5 + 0.5 * math.sin(phase - 0.8)))
    else:  # goblin: quicker posture changes, with the heavy sack following late.
        nervous = 1.7 * math.sin(phase * 2) + 1.0 * math.sin(phase)
        image = anchored_transform(
            base,
            scale_x=1 - 0.004 * breath,
            scale_y=1 + 0.009 * breath,
            shift_x=nervous,
            lift=max(0, 1.7 * math.sin(phase * 2)),
        )
        image = articulated_part(
            image,
            [(285, 115), (510, 95), (510, 430), (300, 445), (250, 285)],
            angle=-2.2 * math.sin(phase - 0.75),
            pivot=(285, 245),
            shift=(0.8 * math.sin(phase - 0.75), 1.8 * math.sin(phase - 0.75)),
        )

    return image.resize((FINAL_SIZE, FINAL_SIZE), Image.Resampling.LANCZOS)


def main() -> None:
    source = Image.open(SOURCE).convert("RGBA")
    OUTPUT.mkdir(parents=True, exist_ok=True)
    for kind, box in CROPS.items():
        base = normalize(source, box)
        frames = [render(base, kind, frame) for frame in range(FRAME_COUNT)]
        still = render(base, kind, 0)
        still.save(OUTPUT / f"life-{kind}-v2.png", optimize=True)
        frames[0].save(
            OUTPUT / f"life-{kind}-v2.webp",
            save_all=True,
            append_images=frames[1:],
            duration=FRAME_MS,
            loop=0,
            quality=84,
            method=4,
            minimize_size=True,
        )
        print(kind, "ok")


if __name__ == "__main__":
    main()
