"""Create fluid 24-frame march loops from the eight reviewed key poses.

The build uses bidirectional optical flow between adjacent poses. It keeps the
authored anatomy and timing while adding two short in-between frames. Runtime
still receives an ordinary animated WebP and needs no animation dependency.
"""
from __future__ import annotations

import argparse
import json
from pathlib import Path

import cv2
import numpy as np
from PIL import Image


ROOT = Path(__file__).resolve().parents[1]
FRAME_ROOT = ROOT / "artifacts" / "march-rig-frames"
SPEC = json.loads((ROOT / "docs" / "march-creature-motion-spec.json").read_text(encoding="utf-8"))


def rgba(path: Path) -> np.ndarray:
    return np.asarray(Image.open(path).convert("RGBA"), dtype=np.uint8)


def gray(frame: np.ndarray) -> np.ndarray:
    alpha = frame[..., 3:4].astype(np.float32) / 255.0
    composed = frame[..., :3].astype(np.float32) * alpha + 246.0 * (1.0 - alpha)
    return cv2.cvtColor(composed.astype(np.uint8), cv2.COLOR_RGB2GRAY)


def flow(source: np.ndarray, target: np.ndarray) -> np.ndarray:
    return cv2.calcOpticalFlowFarneback(
        gray(source), gray(target), None,
        pyr_scale=0.5, levels=4, winsize=27, iterations=5,
        poly_n=7, poly_sigma=1.5, flags=cv2.OPTFLOW_FARNEBACK_GAUSSIAN,
    )


def warp(frame: np.ndarray, displacement: np.ndarray, amount: float) -> np.ndarray:
    height, width = frame.shape[:2]
    grid_x, grid_y = np.meshgrid(np.arange(width, dtype=np.float32), np.arange(height, dtype=np.float32))
    return cv2.remap(
        frame,
        grid_x - displacement[..., 0] * amount,
        grid_y - displacement[..., 1] * amount,
        interpolation=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_CONSTANT,
        borderValue=(0, 0, 0, 0),
    )


def premultiplied_mix(a: np.ndarray, b: np.ndarray, amount: float) -> np.ndarray:
    af = a.astype(np.float32) / 255.0
    bf = b.astype(np.float32) / 255.0
    aa = af[..., 3:4]
    ba = bf[..., 3:4]
    alpha = aa * (1.0 - amount) + ba * amount
    color = af[..., :3] * aa * (1.0 - amount) + bf[..., :3] * ba * amount
    color = np.divide(color, np.maximum(alpha, 1e-5), out=np.zeros_like(color), where=alpha > 1e-5)
    return np.clip(np.concatenate((color, alpha), axis=2) * 255.0, 0, 255).astype(np.uint8)


def inbetweens(a: np.ndarray, b: np.ndarray, multiplier: int) -> list[np.ndarray]:
    forward = flow(a, b)
    backward = flow(b, a)
    frames = [a]
    for step in range(1, multiplier):
        amount = step / multiplier
        from_a = warp(a, forward, amount)
        from_b = warp(b, backward, 1.0 - amount)
        frames.append(premultiplied_mix(from_a, from_b, amount))
    return frames


def build_skin(skin: dict, output: Path, multiplier: int) -> dict:
    skin_id = skin["id"]
    keys = [rgba(FRAME_ROOT / skin_id / f"{index:02}.png") for index in range(8)]
    sequence: list[np.ndarray] = []
    for index, current in enumerate(keys):
        sequence.extend(inbetweens(current, keys[(index + 1) % len(keys)], multiplier))
    images = [Image.fromarray(frame, "RGBA") for frame in sequence]
    frame_ms = max(20, round(int(skin["loop_ms"]) / len(images)))
    target = output / f"animated-march-{skin_id}.webp"
    images[0].save(
        target,
        save_all=True,
        append_images=images[1:],
        duration=frame_ms,
        loop=0,
        quality=82,
        method=4,
        alpha_quality=100,
    )
    return {
        "id": skin_id,
        "keyframes": 8,
        "frames": len(images),
        "frame_ms": frame_ms,
        "duration_ms": frame_ms * len(images),
        "bytes": target.stat().st_size,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("ids", nargs="*")
    parser.add_argument("--multiplier", type=int, default=3, choices=(2, 3, 4))
    parser.add_argument("--production", action="store_true")
    args = parser.parse_args()
    output = ROOT / ("assets/art/marches" if args.production else "artifacts/march-motion-smooth")
    output.mkdir(parents=True, exist_ok=True)
    selected = set(args.ids)
    report = [
        build_skin(skin, output, args.multiplier)
        for skin in SPEC["skins"]
        if not selected or skin["id"] in selected
    ]
    suffix = "-" + "-".join(sorted(selected)) if selected else ""
    report_path = output / f"build-report{suffix}.json"
    report_path.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"status": "PASS", "output": str(output), "assets": report}, indent=2))


if __name__ == "__main__":
    main()
