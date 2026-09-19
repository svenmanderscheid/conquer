"""Extract the user-supplied inventory artwork and wire it into the item catalog.

The screenshots are reference material supplied for this project.  This importer
keeps only the illustrated item motif: labels, quantities, tile frames and the
dark screenshot background are discarded.  Run it with the three screenshots
in Resources, Speedups, Misc order.
"""

from __future__ import annotations

import sys
from pathlib import Path

import cv2
import numpy as np
from PIL import Image, ImageDraw, ImageFont


ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT / "assets" / "art" / "items" / "reference"
REVIEW = ROOT / "artifacts" / "item-icons"


# Tight crops around the actual illustration, never around the complete item tile.
CROPS = {
    "resource-gems": (0, (251, 230, 347, 302)),
    "resource-gems-pile": (0, (394, 224, 497, 303)),
    "vip-points": (0, (690, 221, 784, 307)),
    "resource-food": (0, (973, 222, 1071, 307)),
    "resource-lumber": (0, (833, 373, 938, 456)),
    "resource-stone": (0, (393, 512, 501, 605)),
    "resource-gold": (0, (535, 658, 650, 747)),
    "action-points": (0, (251, 802, 351, 893)),
    "speedup-generic": (1, (254, 226, 352, 307)),
    "speedup-building": (1, (545, 370, 647, 458)),
    "speedup-research": (1, (544, 512, 648, 606)),
    "speedup-training": (1, (253, 798, 358, 893)),
    "speedup-healing": (1, (543, 798, 650, 894)),
    "misc-helmet": (2, (43, 143, 105, 204)),
    "dragon-egg-green": (2, (148, 137, 201, 205)),
    "dragon-egg-red": (2, (247, 137, 300, 205)),
    "dragon-egg-gold": (2, (346, 137, 399, 205)),
    "teleport-castle": (2, (441, 142, 509, 205)),
    "misc-crest": (2, (540, 141, 609, 205)),
    "misc-token": (2, (637, 141, 698, 204)),
    "fragment-legendary": (2, (45, 239, 105, 298)),
    "fragment-epic": (2, (144, 239, 204, 298)),
    "fragment-rare": (2, (243, 239, 303, 298)),
    "fragment-normal": (2, (343, 239, 402, 298)),
    "misc-scroll": (2, (441, 239, 505, 299)),
    "misc-red-flask": (2, (540, 239, 604, 299)),
}


UNASSIGNED = [
    ("U1", "misc-helmet"),
    ("U2", "misc-crest"),
    ("U3", "misc-token"),
    ("U4", "misc-scroll"),
    ("U5", "misc-red-flask"),
]


# Local regions occupied by the live quantity overlay in the source UI.  Only
# bright numeral pixels inside these regions are inpainted; the item stays intact.
COUNT_RECTS = {
    "resource-gems": (66, 47, 96, 72), "resource-gems-pile": (73, 52, 103, 79),
    "vip-points": (54, 55, 94, 86), "resource-food": (73, 54, 98, 85),
    "resource-lumber": (14, 45, 105, 83), "resource-stone": (61, 58, 108, 93),
    "resource-gold": (22, 51, 115, 89), "action-points": (52, 49, 100, 91),
    "speedup-generic": (31, 48, 98, 81), "speedup-building": (48, 49, 102, 88),
    "speedup-research": (44, 54, 104, 94), "speedup-training": (54, 53, 105, 95),
    "speedup-healing": (24, 54, 107, 96),
}


def foreground(crop: Image.Image, name: str) -> Image.Image:
    """Remove the dark blue tile field while retaining the source pixels."""
    rgba = np.array(crop.convert("RGBA"), copy=True)
    bgr = cv2.cvtColor(rgba[:, :, :3], cv2.COLOR_RGB2BGR)
    height, width = bgr.shape[:2]
    # Remove the game's live white quantity overlay before isolating the art.
    if name in COUNT_RECTS:
        x1, y1, x2, y2 = COUNT_RECTS[name]
        hsv = cv2.cvtColor(bgr, cv2.COLOR_BGR2HSV)
        numeral = np.zeros((height, width), np.uint8)
        region = (hsv[y1:y2, x1:x2, 1] < 88) & (hsv[y1:y2, x1:x2, 2] > 142)
        numeral[y1:y2, x1:x2][region] = 255
        numeral = cv2.dilate(numeral, np.ones((5, 5), np.uint8), iterations=1)
        if np.any(numeral):
            bgr = cv2.inpaint(bgr, numeral, 4, cv2.INPAINT_TELEA)
            rgba[:, :, :3] = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)

    border = max(2, min(width, height) // 24)
    rect = (border, border, width - border * 2, height - border * 2)
    if name == "teleport-castle":
        mask = np.full((height, width), cv2.GC_PR_BGD, np.uint8)
        mask[:border, :] = mask[-border:, :] = cv2.GC_BGD
        mask[:, :border] = mask[:, -border:] = cv2.GC_BGD
        hsv = cv2.cvtColor(bgr, cv2.COLOR_BGR2HSV)
        castle = ((hsv[:, :, 1] < 95) & (hsv[:, :, 2] > 82)) | ((hsv[:, :, 0] > 82) & (hsv[:, :, 0] < 108) & (hsv[:, :, 1] > 105))
        castle[:border * 2, :] = castle[-border * 2:, :] = False
        castle[:, :border * 2] = castle[:, -border * 2:] = False
        mask[castle] = cv2.GC_FGD
        cv2.grabCut(bgr, mask, None, np.zeros((1, 65), np.float64), np.zeros((1, 65), np.float64), 7, cv2.GC_INIT_WITH_MASK)
    else:
        mask = np.zeros((height, width), np.uint8)
        cv2.grabCut(bgr, mask, rect, np.zeros((1, 65), np.float64), np.zeros((1, 65), np.float64), 7, cv2.GC_INIT_WITH_RECT)
    alpha = np.where((mask == cv2.GC_FGD) | (mask == cv2.GC_PR_FGD), 255, 0).astype(np.uint8)
    alpha = cv2.morphologyEx(alpha, cv2.MORPH_CLOSE, np.ones((3, 3), np.uint8))
    alpha = cv2.GaussianBlur(alpha, (3, 3), 0.55)
    rgba[:, :, 3] = alpha
    image = Image.fromarray(rgba)
    bounds = image.getbbox()
    if not bounds:
        raise RuntimeError(f"Foreground extraction produced an empty icon: {name}")
    image = image.crop(bounds)
    canvas = Image.new("RGBA", (128, 128))
    image.thumbnail((116, 112), Image.Resampling.LANCZOS)
    canvas.alpha_composite(image, ((128 - image.width) // 2, (128 - image.height) // 2))
    return canvas


def contact_sheet() -> None:
    width, row_height = 410, 156
    image = Image.new("RGB", (width, row_height * len(UNASSIGNED) + 32), "#E9DFCF")
    draw = ImageDraw.Draw(image)
    font_path = ROOT / "assets" / "fonts" / "Almendra-Regular.ttf"
    font = ImageFont.truetype(str(font_path), 28) if font_path.exists() else ImageFont.load_default()
    small = ImageFont.truetype(str(font_path), 20) if font_path.exists() else ImageFont.load_default()
    for index, (label, name) in enumerate(UNASSIGNED):
        y = 16 + index * row_height
        draw.rounded_rectangle((16, y, width - 16, y + row_height - 12), radius=18, fill="#FBF6EC", outline="#756080", width=3)
        icon = Image.open(OUTPUT / f"{name}.png").convert("RGBA")
        image.paste(icon, (32, y + 7), icon)
        draw.text((180, y + 34), label, font=font, fill="#443549")
        draw.text((180, y + 78), "Bitte benennen", font=small, fill="#5F5261")
    image.save(REVIEW / "nicht-zugeordnete-icons.png", optimize=True)


def all_icons_sheet() -> None:
    columns, cell = 6, 180
    rows = (len(CROPS) + columns - 1) // columns
    image = Image.new("RGB", (columns * cell, rows * cell), "#E9DFCF")
    draw = ImageDraw.Draw(image)
    font_path = ROOT / "assets" / "fonts" / "Almendra-Regular.ttf"
    font = ImageFont.truetype(str(font_path), 16) if font_path.exists() else ImageFont.load_default()
    for index, name in enumerate(CROPS):
        x, y = index % columns * cell, index // columns * cell
        icon = Image.open(OUTPUT / f"{name}.png").convert("RGBA")
        image.paste(icon, (x + 26, y + 8), icon)
        draw.text((x + 8, y + 141), name, font=font, fill="#443549")
    image.save(REVIEW / "alle-importierten-icons.png", optimize=True)


def main() -> None:
    if len(sys.argv) != 4:
        raise SystemExit("Usage: import-reference-item-icons.py RESOURCES.png SPEEDUPS.png MISC.png")
    sources = [Image.open(path).convert("RGB") for path in sys.argv[1:]]
    OUTPUT.mkdir(parents=True, exist_ok=True)
    REVIEW.mkdir(parents=True, exist_ok=True)
    for name, (source_index, box) in CROPS.items():
        foreground(sources[source_index].crop(box), name).save(OUTPUT / f"{name}.png", optimize=True)
    contact_sheet()
    all_icons_sheet()
    print(f"Imported {len(CROPS)} icons and wrote {REVIEW / 'nicht-zugeordnete-icons.png'}")


if __name__ == "__main__":
    main()
