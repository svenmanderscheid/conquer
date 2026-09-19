"""Build mobile thumbnails and report portraits from the approved troop artwork."""

from pathlib import Path
from collections import deque

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
CHARACTERS = ROOT / "assets" / "art" / "characters"
REVIEW = ROOT / "artifacts" / "troop-portraits"
REVIEW.mkdir(parents=True, exist_ok=True)


def contain(source: Image.Image, size: int, padding: int) -> Image.Image:
    image = source.convert("RGBA")
    alpha_bounds = image.getchannel("A").getbbox()
    if alpha_bounds:
        image = image.crop(alpha_bounds)
    image.thumbnail((size - padding * 2, size - padding * 2), Image.Resampling.LANCZOS)
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    canvas.alpha_composite(image, ((size - image.width) // 2, (size - image.height) // 2))
    return canvas


def clear_light_edge_background(source: Image.Image) -> Image.Image:
    """Remove only the near-white area connected to the image edge."""
    image = source.convert("RGBA")
    pixels = image.load()
    width, height = image.size
    queue = deque()
    seen = set()
    for x in range(width):
        queue.extend(((x, 0), (x, height - 1)))
    for y in range(height):
        queue.extend(((0, y), (width - 1, y)))
    while queue:
        x, y = queue.popleft()
        if (x, y) in seen:
            continue
        seen.add((x, y))
        red, green, blue, alpha = pixels[x, y]
        light_neutral = min(red, green, blue) >= 178 and max(red, green, blue) - min(red, green, blue) <= 48
        if alpha == 0 or light_neutral:
            pixels[x, y] = (red, green, blue, 0)
            if x:
                queue.append((x - 1, y))
            if x + 1 < width:
                queue.append((x + 1, y))
            if y:
                queue.append((x, y - 1))
            if y + 1 < height:
                queue.append((x, y + 1))
    return image


for troop in ("archer", "cavalry"):
    for tier in range(1, 11):
        ui_path = CHARACTERS / f"{troop}-t{tier}-ui-v1.png"
        with Image.open(ui_path) as source:
            prepared = clear_light_edge_background(source) if tier == 10 else source
            ui = contain(prepared, 768, 8)
            ui.save(ui_path, optimize=True)
            contain(ui, 192, 4).save(
                CHARACTERS / f"{troop}-t{tier}-thumb-v1.webp",
                format="WEBP",
                quality=88,
                method=6,
            )
            report_path = CHARACTERS / f"{troop}-t{tier}-report-v1.png"
            if tier != 10 or not report_path.exists():
                contain(ui, 512, 8).save(report_path, optimize=True)

    sheet = Image.new("RGB", (960, 384), "#efe3cc")
    for index in range(10):
        with Image.open(CHARACTERS / f"{troop}-t{index + 1}-thumb-v1.webp") as thumb:
            sheet.paste(thumb.convert("RGBA"), ((index % 5) * 192, (index // 5) * 192), thumb.convert("RGBA"))
    sheet.save(REVIEW / f"{troop}-tiers-v1.jpg", quality=92, optimize=True)

print("Built 20 UI portraits, 20 thumbnails and 18 report portraits.")
