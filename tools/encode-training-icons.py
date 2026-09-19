"""Encode the native Three.js portrait renders for the tier selector (requires Pillow)."""
from pathlib import Path
from PIL import Image

root = Path(__file__).resolve().parents[1]
source = root / "artifacts/training/icons"
destination = root / "assets/art/training"
destination.mkdir(parents=True, exist_ok=True)
for unit_type in range(1, 4):
    for tier in range(1, 11):
        name = f"{unit_type}-{tier}"
        with Image.open(source / f"{name}.png") as portrait:
            portrait.save(destination / f"{name}.webp", quality=85, method=6)
print(f"30 portraits: {sum(p.stat().st_size for p in destination.glob('*.webp')):,} bytes")
