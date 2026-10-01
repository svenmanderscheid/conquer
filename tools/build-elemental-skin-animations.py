"""Build lightweight transparent loops for the four elemental skin pairs."""
from __future__ import annotations

import math
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
MAP = ROOT / "assets" / "art" / "map"
MARCH = ROOT / "assets" / "art" / "marches"
ELEMENTS = {
    "forest": ("#9acb55", "#5b873a"),
    "fire": ("#ffb13b", "#e64e25"),
    "water": ("#8ff4f2", "#27aeca"),
    "wind": ("#eefbff", "#8bcdf2"),
}


def rgba(hex_color: str, alpha: int) -> tuple[int, int, int, int]:
    value = hex_color.lstrip("#")
    return tuple(int(value[i:i + 2], 16) for i in (0, 2, 4)) + (alpha,)


def save_loop(frames: list[Image.Image], destination: Path, duration: int, quality: int = 82) -> None:
    frames[0].save(destination, save_all=True, append_images=frames[1:], duration=duration,
                   loop=0, quality=quality, method=5, minimize_size=True)


def castle_frames(skin: str, count: int = 20, size: int = 576) -> list[Image.Image]:
    base = Image.open(MAP / f"castle-{skin}.png").convert("RGBA").resize((size, size), Image.Resampling.LANCZOS)
    frames: list[Image.Image] = []
    for index in range(count):
        phase = index / count * math.tau
        frame = base.copy()
        fx = Image.new("RGBA", frame.size)
        draw = ImageDraw.Draw(fx)
        if skin == "forest":
            for n in range(11):
                progress = (index / count + n / 11) % 1
                x = int(size * (.18 + (n * .071) % .66) + math.sin(phase + n) * 9)
                y = int(size * (.20 + progress * .64))
                color = rgba(ELEMENTS[skin][n % 2], int(175 * math.sin(progress * math.pi)))
                draw.ellipse((x - 4, y - 2, x + 4, y + 2), fill=color)
        elif skin == "fire":
            glow = int(55 + 35 * (math.sin(phase) + 1) / 2)
            draw.ellipse((size*.31, size*.03, size*.43, size*.18), fill=(255, 116, 25, glow))
            draw.ellipse((size*.72, size*.15, size*.84, size*.29), fill=(255, 116, 25, glow))
            for n in range(12):
                progress = (index / count + n / 12) % 1
                x = int(size * (.22 + (n * .113) % .62) + math.sin(phase * 1.4 + n) * 7)
                y = int(size * (.44 - progress * .36))
                radius = 2 + n % 3
                draw.ellipse((x-radius, y-radius, x+radius, y+radius), fill=rgba(ELEMENTS[skin][n % 2], int(210*(1-progress))))
        elif skin == "water":
            for n in range(10):
                progress = (index / count + n / 10) % 1
                x = int(size * (.14 + (n * .173) % .72) + math.sin(phase + n) * 5)
                y = int(size * (.68 - progress * .43))
                radius = 2 + n % 4
                draw.ellipse((x-radius, y-radius, x+radius, y+radius), outline=rgba(ELEMENTS[skin][n % 2], int(190*(1-progress))), width=2)
            shimmer = int(35 + 28 * (math.sin(phase) + 1) / 2)
            for x in (.18, .48, .72, .88):
                draw.rounded_rectangle((size*x-5, size*.33, size*x+5, size*.72), 5, fill=(110, 250, 255, shimmer))
        else:
            # A soft circular crop lets the defining wheel turn while the castle stays fixed.
            center = (int(size*.50), int(size*.365)); radius = int(size*.145)
            crop = base.crop((center[0]-radius, center[1]-radius, center[0]+radius, center[1]+radius))
            mask = Image.new("L", crop.size); ImageDraw.Draw(mask).ellipse((5, 5, crop.width-5, crop.height-5), fill=225)
            mask = mask.filter(ImageFilter.GaussianBlur(5))
            spun = crop.rotate(-index * 360 / count, Image.Resampling.BICUBIC, expand=False)
            frame.paste(spun, (center[0]-radius, center[1]-radius), mask)
            for n in range(6):
                progress = (index / count + n / 6) % 1
                y = int(size*(.25 + n*.075) + math.sin(phase+n)*5)
                x = int(size*(.08 + progress*.82))
                draw.arc((x-24, y-7, x+24, y+7), 190, 350, fill=rgba(ELEMENTS[skin][n%2], int(170*math.sin(progress*math.pi))), width=3)
        frame.alpha_composite(fx.filter(ImageFilter.GaussianBlur(.25)))
        frames.append(frame)
    return frames


def articulated_ground_pose(source: Image.Image, skin: str, phase: float) -> Image.Image:
    """Move four soft leg zones in alternating phases while the torso stays stable.

    The source paintings are intentionally kept as one coherent illustration. Soft
    masks avoid cut-out seams, but their opposing offsets make hoof lift and plant
    readable at the small world-map size.
    """
    width,height=source.size
    centers={"forest":(.235,.405,.585,.765),"fire":(.205,.405,.595,.785)}[skin]
    result=source.copy()
    stride={"forest":13.5,"fire":10.0}[skin]
    for index,center in enumerate(centers):
        leg_phase=phase+(math.pi if index%2 else 0)
        horizontal=math.sin(leg_phase)*stride
        lift=max(0,-math.cos(leg_phase))*({"forest":8.0,"fire":5.0}[skin])
        # AFFINE maps output coordinates back into the source image.
        moved=source.transform(source.size,Image.Transform.AFFINE,(1,0,-horizontal,0,1,lift),Image.Resampling.BICUBIC)
        mask=Image.new("L",source.size)
        draw=ImageDraw.Draw(mask)
        half={"forest":.105,"fire":.115}[skin]
        top={"forest":.635,"fire":.675}[skin]
        draw.ellipse(((center-half)*width,top*height,(center+half)*width,1.035*height),fill=255)
        # Preserve the hips; the visible stride should begin below the torso.
        fade=Image.new("L",(1,height))
        fade_pixels=fade.load()
        for y in range(height):
            fade_pixels[0,y]=max(0,min(255,int((y-top*height)/(height*.105)*255)))
        mask=Image.composite(mask,Image.new("L",source.size),fade.resize(source.size))
        mask=mask.filter(ImageFilter.GaussianBlur(width*.012))
        result=Image.composite(moved,result,mask)
    return result


def seamless_gait_pose(source: Image.Image, skin: str, phase: float) -> Image.Image:
    """Create a stable, seamless pose with locomotion appropriate to the skin."""
    width,height=source.size
    if skin in {"forest","fire"}:
        pose=articulated_ground_pose(source,skin,phase)
        # The torso only follows the footfall slightly; the legs carry the gait.
        return pose.rotate(math.sin(phase)*({"forest":.65,"fire":.4}[skin]),Image.Resampling.BICUBIC,center=(width*.53,height*.60))
    stride={"forest":.050,"fire":.038,"water":.056,"wind":.062}[skin]*math.sin(phase)
    # Pillow's affine coefficients map output back to source. Anchoring the
    # shear near the shoulders keeps heads and equipment perfectly stable.
    pivot=height*.54
    warped=source.transform(source.size,Image.Transform.AFFINE,(1,-stride,stride*pivot,0,1,0),Image.Resampling.BICUBIC)
    mask=Image.new("L",source.size)
    gradient=Image.new("L",(1,height))
    pixels=gradient.load()
    for y in range(height):
        pixels[0,y]=max(0,min(255,int((y-height*.53)/(height*.17)*255)))
    mask=gradient.resize(source.size)
    pose=Image.composite(warped,source,mask)
    # A small forward/backward rock turns the already raised lead paw/hoof into
    # a readable plant-and-lift cycle at normal map size.
    return pose.rotate(math.sin(phase)*({"wind":2.2,"water":1.5}.get(skin,1.0)),Image.Resampling.BICUBIC,center=(width*.53,height*.60))


def march_frames(skin: str, count: int = 24, size: int = 384) -> list[Image.Image]:
    source = Image.open(MARCH / f"march-{skin}.webp").convert("RGBA").resize((512, 512), Image.Resampling.LANCZOS)
    frames: list[Image.Image] = []
    for index in range(count):
        phase = index / count * math.tau
        bob = {"forest": 5, "fire": 3, "water": 4, "wind": 8}[skin]
        y = int(math.sin(phase) * bob)
        rigged = seamless_gait_pose(source,skin,phase)
        squash = 1 + math.sin(phase*2) * ({"fire": .012, "forest": .009}.get(skin, .006))
        creature = rigged.resize((int(size/squash), int(size*squash)), Image.Resampling.LANCZOS)
        frame = Image.new("RGBA", (size, size))
        frame.alpha_composite(creature, ((size-creature.width)//2, (size-creature.height)//2+y))
        fx = Image.new("RGBA", frame.size); draw = ImageDraw.Draw(fx)
        for n in range(8):
            progress = (index / count + n / 8) % 1
            alpha = int(185 * (1-progress))
            if skin == "forest":
                x, yy = int(size*(.30-progress*.22)), int(size*(.73-progress*.18)+math.sin(phase+n)*6)
                draw.ellipse((x-5, yy-2, x+5, yy+2), fill=rgba(ELEMENTS[skin][n%2], alpha))
            elif skin == "fire":
                x, yy = int(size*(.27-progress*.18)), int(size*(.72-progress*.27))
                draw.ellipse((x-3, yy-5, x+3, yy+5), fill=rgba(ELEMENTS[skin][n%2], alpha))
            elif skin == "water":
                x, yy = int(size*(.25-progress*.18)), int(size*(.70-progress*.12))
                draw.ellipse((x-5, yy-3, x+5, yy+3), outline=rgba(ELEMENTS[skin][n%2], alpha), width=2)
            else:
                x, yy = int(size*(.30-progress*.22)), int(size*(.66+math.sin(phase+n)*.025))
                draw.line((x-14, yy, x+12, yy), fill=rgba(ELEMENTS[skin][n%2], alpha), width=3)
        frame.alpha_composite(fx)
        frames.append(frame)
    return frames


selected=set(sys.argv[1:])
unknown=selected-set(ELEMENTS)
if unknown:
    raise SystemExit(f"Unknown elemental skin(s): {', '.join(sorted(unknown))}")

for element in ELEMENTS:
    if selected and element not in selected:
        continue
    castle = castle_frames(element)
    save_loop(castle, MAP / f"castle-{element}.webp", 70, 80)
    march = march_frames(element)
    save_loop(march, MARCH / f"animated-march-{element}.webp", 50, 82)
    print(element, (MAP / f"castle-{element}.webp").stat().st_size, (MARCH / f"animated-march-{element}.webp").stat().st_size)
