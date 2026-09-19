"""Build eight-pose animated WebPs from retained 4x2 Imagegen sheets."""
from __future__ import annotations
import json
import sys
from pathlib import Path
from PIL import Image, ImageDraw
import numpy as np

ROOT = Path(__file__).resolve().parents[1]
SHEETS = ROOT / "asset-workflow" / "02-animation-sheets"
OUTPUT = ROOT / "assets" / "art" / "marches"
FRAMES = ROOT / "artifacts" / "march-rig-frames"
SPEC = json.loads((ROOT / "docs" / "march-creature-motion-spec.json").read_text(encoding="utf-8"))
GROUND = {"ironkeep", "rosehall", "sandspire", "winterhold", "jadecourt", "emberforge", "clockwork", "sapphire", "yggdrasil"}

def remove_preview_grid(image: Image.Image) -> Image.Image:
    """Flood only the edge-connected neutral checkerboard; enclosed pale art survives."""
    rgb = np.asarray(image.convert("RGB"))
    spread = rgb.max(axis=2).astype(np.int16) - rgb.min(axis=2).astype(np.int16)
    light = rgb.mean(axis=2)
    candidate = ((spread <= 38) & (light >= 105)).astype(np.uint8) * 255
    mask = Image.fromarray(candidate, "L").copy()
    # Imagegen's decorative checker does not guarantee that pixel (0,0) is
    # neutral. Seed every still-unvisited candidate on all four sheet edges.
    border=[]
    border.extend((x,0) for x in range(mask.width));border.extend((x,mask.height-1) for x in range(mask.width))
    border.extend((0,y) for y in range(mask.height));border.extend((mask.width-1,y) for y in range(mask.height))
    pixels=mask.load()
    for point in border:
        if pixels[point] == 255: ImageDraw.floodfill(mask, point, 128, thresh=0)
    connected = np.asarray(mask) == 128
    # Include the one-pixel neutral fringe created by antialiasing against the grid.
    alpha = np.where(connected, 0, 255).astype(np.uint8)
    rgba = np.dstack((rgb, alpha))
    return Image.fromarray(rgba, "RGBA")

def cells(sheet: Image.Image) -> list[tuple[Image.Image, tuple[float,float]]]:
    """Extract the central connected subject from overlapping grid windows."""
    result=[];cell_w=sheet.width/4;cell_h=sheet.height/2
    for row in range(2):
        for column in range(4):
            center_x=(column+.5)*cell_w;center_y=(row+.5)*cell_h
            left=max(0,round(center_x-cell_w*.72));right=min(sheet.width,round(center_x+cell_w*.72))
            top=max(0,round(center_y-cell_h*.72));bottom=min(sheet.height,round(center_y+cell_h*.72))
            window=sheet.crop((left,top,right,bottom));opaque=np.asarray(window.getchannel("A"))>0
            ys,xs=np.nonzero(opaque)
            if not len(xs): raise RuntimeError(f"empty animation cell {column},{row}")
            local_center=np.array([center_x-left,center_y-top]);distance=(xs-local_center[0])**2+(ys-local_center[1])**2
            seed=(int(xs[distance.argmin()]),int(ys[distance.argmin()]))
            component=Image.fromarray(opaque.astype(np.uint8)*255,"L").copy()
            ImageDraw.floodfill(component,seed,128,thresh=0);keep=np.asarray(component)==128
            rgba=np.asarray(window).copy();rgba[:,:,3]=np.where(keep,rgba[:,:,3],0)
            isolated=Image.fromarray(rgba,"RGBA");box=isolated.getchannel("A").getbbox()
            if not box: raise RuntimeError(f"no central subject in cell {column},{row}")
            offset=(left+box[0]-center_x,top+box[1]-center_y)
            result.append((isolated.crop(box),offset))
    return result

def normalize(raw: list[tuple[Image.Image,tuple[float,float]]], grounded: bool, size: int=256) -> list[Image.Image]:
    min_x=min(offset[0] for _,offset in raw);min_y=min(offset[1] for _,offset in raw)
    max_x=max(offset[0]+frame.width for frame,offset in raw);max_y=max(offset[1]+frame.height for frame,offset in raw)
    scale=min((size-24)/(max_x-min_x),(size-24)/(max_y-min_y))
    anchor_x=size/2-(min_x+max_x)/2*scale
    anchor_y=(size-12-max_y*scale) if grounded else size/2-(min_y+max_y)/2*scale
    normalized=[]
    for subject,offset in raw:
        width=max(1,round(subject.width*scale)); height=max(1,round(subject.height*scale))
        subject=subject.resize((width,height),Image.Resampling.LANCZOS)
        canvas=Image.new("RGBA",(size,size),(0,0,0,0))
        x=round(anchor_x+offset[0]*scale);y=round(anchor_y+offset[1]*scale)
        canvas.alpha_composite(subject,(x,y)); normalized.append(canvas)
    return normalized

def build() -> None:
    OUTPUT.mkdir(parents=True,exist_ok=True);FRAMES.mkdir(parents=True,exist_ok=True)
    selected=set(sys.argv[1:]);report=[]
    for skin in SPEC["skins"]:
        skin_id=skin["id"]; source=SHEETS/skin_id/"sheet.png"
        if selected and skin_id not in selected: continue
        if not source.exists(): raise FileNotFoundError(source)
        clean=remove_preview_grid(Image.open(source))
        sequence=normalize(cells(clean),skin_id in GROUND)
        frame_dir=FRAMES/skin_id;frame_dir.mkdir(parents=True,exist_ok=True)
        for index,frame in enumerate(sequence): frame.save(frame_dir/f"{index:02}.png",optimize=True)
        motion=OUTPUT/f"animated-march-{skin_id}.webp"
        sequence[0].save(motion,save_all=True,append_images=sequence[1:],duration=int(skin["frame_ms"]),loop=0,quality=82,method=6,alpha_quality=100)
        still=sequence[0].resize((512,512),Image.Resampling.LANCZOS)
        still.save(OUTPUT/f"march-{skin_id}.webp",format="WEBP",quality=88,method=6)
        report.append({"id":skin_id,"frames":8,"frame_ms":skin["frame_ms"],"motion_bytes":motion.stat().st_size})
    target=ROOT/"artifacts"/"march-rig-build.json";target.write_text(json.dumps(report,indent=2)+"\n",encoding="utf-8")
    print(json.dumps({"status":"PASS","assets":len(report),"bytes":sum(row["motion_bytes"] for row in report),"report":str(target)},indent=2))

if __name__ == "__main__": build()
