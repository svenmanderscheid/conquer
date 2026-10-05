"""Export the approved 5x4 menu concept sheet into transparent game assets."""

from collections import deque
from pathlib import Path
import argparse
import shutil

from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "assets" / "art" / "menu-icons" / "menu-icon-concepts.png"
OUTPUT = SOURCE.parent.parent / "menu-icons-v2"
CORRECTED = ["army", "inventory", "quests", "settings", "menu", "arena", "profile"]
NAMES = [
    "profile", "quests", "army", "research", "inventory",
    "treasures", "mastery", "market", "community", "defense",
    "events", "expeditions", "rankings", "arena", "worlds",
    "reports", "settings", "account", "help", "menu",
]
# The painted sheet has an outer border and uneven row spacing. Dividing the
# full bitmap into equal quarters clips the first-row bottoms and last-row tops.
# These are the actual panel boundaries in the approved 1402 x 1122 source.
SOURCE_SIZE = (1402, 1122)
COLUMN_EDGES = (27, 286, 560, 835, 1110, 1378)
ROW_EDGES = (32, 309, 561, 814, 1086)


def is_sheet_background(pixel: tuple[int, int, int, int]) -> bool:
    red, green, blue, _ = pixel
    return red >= 205 and green >= 188 and blue >= 150 and max(pixel[:3]) - min(pixel[:3]) <= 92


def clear_connected_background(image: Image.Image) -> Image.Image:
    rgba = image.convert("RGBA")
    pixels = rgba.load()
    width, height = rgba.size
    queue: deque[tuple[int, int]] = deque()
    visited = set()
    for x in range(width):
        queue.extend(((x, 0), (x, height - 1)))
    for y in range(height):
        queue.extend(((0, y), (width - 1, y)))
    while queue:
        x, y = queue.popleft()
        if (x, y) in visited or not is_sheet_background(pixels[x, y]):
            continue
        visited.add((x, y))
        red, green, blue, _ = pixels[x, y]
        pixels[x, y] = (red, green, blue, 0)
        if x:
            queue.append((x - 1, y))
        if x + 1 < width:
            queue.append((x + 1, y))
        if y:
            queue.append((x, y - 1))
        if y + 1 < height:
            queue.append((x, y + 1))
    return rgba


def keep_main_subject(image: Image.Image) -> Image.Image:
    """Discard disconnected sheet borders while keeping the icon's main silhouette."""
    alpha = image.getchannel("A")
    width, height = image.size
    visited: set[tuple[int, int]] = set()
    components: list[list[tuple[int, int]]] = []
    for y in range(height):
        for x in range(width):
            if (x, y) in visited or alpha.getpixel((x, y)) < 16:
                continue
            component: list[tuple[int, int]] = []
            queue = deque([(x, y)])
            visited.add((x, y))
            while queue:
                current_x, current_y = queue.popleft()
                component.append((current_x, current_y))
                for next_x, next_y in ((current_x - 1, current_y), (current_x + 1, current_y), (current_x, current_y - 1), (current_x, current_y + 1)):
                    if not (0 <= next_x < width and 0 <= next_y < height):
                        continue
                    if (next_x, next_y) in visited or alpha.getpixel((next_x, next_y)) < 16:
                        continue
                    visited.add((next_x, next_y))
                    queue.append((next_x, next_y))
            components.append(component)
    if not components:
        return image
    keep = set(max(components, key=len))
    pixels = image.load()
    for y in range(height):
        for x in range(width):
            if (x, y) not in keep:
                red, green, blue, _ = pixels[x, y]
                pixels[x, y] = (red, green, blue, 0)
    return image


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--only", nargs="*", choices=NAMES, default=CORRECTED,
                        help="Re-export only these existing menu symbols.")
    args = parser.parse_args()
    sheet = Image.open(SOURCE).convert("RGBA")
    OUTPUT.mkdir(parents=True, exist_ok=True)
    # Version the complete public set while preserving unaffected illustrations.
    for existing in SOURCE.parent.glob("*.png"):
        if existing != SOURCE:
            shutil.copy2(existing, OUTPUT / existing.name)
    if sheet.size != SOURCE_SIZE:
        raise RuntimeError("Approved sheet size changed; review panel boundaries before export")
    for index, name in enumerate(NAMES):
        if args.only and name not in args.only:
            continue
        column, row = index % 5, index // 5
        box = (
            COLUMN_EDGES[column] + 2,
            ROW_EDGES[row] + 2,
            COLUMN_EDGES[column + 1] - 2,
            ROW_EDGES[row + 1] - 2,
        )
        icon = keep_main_subject(clear_connected_background(sheet.crop(box)))
        alpha_box = icon.getchannel("A").getbbox()
        if not alpha_box:
            raise RuntimeError(f"No visible pixels found for {name}")
        icon = icon.crop(alpha_box)
        icon.thumbnail((224, 224), Image.Resampling.LANCZOS)
        canvas = Image.new("RGBA", (256, 256))
        canvas.alpha_composite(icon, ((256 - icon.width) // 2, (256 - icon.height) // 2))
        canvas.save(OUTPUT / f"{name}.png", optimize=True)


if __name__ == "__main__":
    main()
