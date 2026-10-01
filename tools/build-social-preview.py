from pathlib import Path
import re, html, shutil

root = Path(__file__).resolve().parents[1]
out = root / 'outputs/social-2026-09'
(out / 'css').mkdir(exist_ok=True)
(out / 'fonts').mkdir(exist_ok=True)
shutil.copy2(root / 'assets/css/fantasy-fonts.css', out / 'css/fantasy-fonts.css')
for font in (root / 'assets/fonts').iterdir():
    if font.is_file(): shutil.copy2(font, out / 'fonts' / font.name)
tokens = re.search(r':root\s*\{[^}]+\}', (root / 'assets/css/village-theme.css').read_text(encoding='utf-8')).group(0)
css = tokens + '''
*{box-sizing:border-box}body{margin:0;background:var(--ui-paper);color:var(--ui-ink);font:19px/1.55 var(--ui-font,Almendra),serif}
header{padding:32px max(22px,calc((100vw - 1200px)/2));background:var(--ui-primary-dark);color:var(--ui-card-light)}
h1{font-size:clamp(34px,5vw,58px);line-height:1.1;margin:12px 0}h2{line-height:1.2;color:var(--ui-primary-dark)}
header p{max-width:800px}nav{display:flex;gap:14px;flex-wrap:wrap}a{color:var(--ui-primary-dark)}nav a{color:var(--ui-card-light)}
a,button{min-height:44px;display:inline-flex;align-items:center;gap:8px}main{max-width:1244px;margin:auto;padding:28px 22px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}.card{min-width:0;border:1px solid var(--ui-line);border-radius:18px;background:var(--ui-card-light);overflow:hidden}
.poster{display:block;width:100%;height:auto}.copy{padding:24px}textarea{display:block;width:100%;min-height:280px;resize:vertical;border:1px solid var(--ui-line);background:var(--ui-card);color:var(--ui-ink);border-radius:9px;padding:14px;font:17px/1.5 var(--ui-font,Almendra),serif}
button{margin:14px 16px 0 0;border:0;border-radius:9px;background:var(--ui-green);color:var(--ui-card-light);padding:10px 18px;font:inherit;cursor:pointer}
.logo{display:block;max-width:600px;width:100%;height:auto;margin:auto}.logo-section{margin-bottom:32px;padding:24px;border:1px solid var(--ui-line);border-radius:18px;background:var(--ui-card)}
.reading{max-width:850px}.reading h2{margin-top:42px}.reading h3{margin-top:28px}a:focus-visible,button:focus-visible,textarea:focus-visible{outline:3px solid var(--ui-primary-dark);outline-offset:3px}
@media(max-width:700px){.grid{grid-template-columns:1fr}.copy{padding:18px}main{padding:20px 14px}}@media print{nav,button,textarea{display:none}.reading{max-width:none}}
'''
(out / 'css/gallery.css').write_text(css, encoding='utf-8')
head='<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="css/fantasy-fonts.css"><link rel="stylesheet" href="css/gallery.css">'
source=(out/'CAPTIONS-EN.md').read_text(encoding='utf-8')
posts=re.findall(r'^## (\d{2}) · ([^\n]+)\n(.*?)(?=^## |\Z)',source,re.M|re.S)
cards=[]
for number,title,body in posts:
    picture=next(out.glob(number+'-*.png')).name
    cards.append(f'<article class="card"><img class="poster" src="{picture}" loading="lazy" alt="{html.escape(title)} — promotional artwork"><div class="copy"><h2>{number} · {html.escape(title)}</h2><label for="post-{number}">Caption and hashtags</label><textarea id="post-{number}" readonly>{html.escape(body.strip())}</textarea><button data-copy="post-{number}">Copy caption</button><a href="{picture}" download>Download image</a><p role="status" id="status-{number}"></p></div></article>')
page=head+'<title>Union of Kingdoms — English social campaign</title><header><p>A new Era begins</p><h1>Your English campaign kit</h1><p>10 images, ready-to-copy captions and hashtags, and a new logo. Promotional artwork · Register for closed alpha.</p><nav><a href="game-vision.html">Read the game vision</a><a href="CAPTIONS-EN.md" download>Download all captions</a><a href="PROMPTS.json" download>Image prompts</a></nav></header><main><section class="logo-section"><h2>The logo</h2><img class="logo" src="union-of-kingdoms-logo-en.png" alt="Union of Kingdoms — A new Era begins"><a href="union-of-kingdoms-logo-en.png" download>Download PNG logo</a></section><div class="grid">'+''.join(cards)+'</div></main><script src="gallery.js"></script></html>'
(out/'index.html').write_text(page,encoding='utf-8')
(out/'gallery.js').write_text('''document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{const field=document.getElementById(button.dataset.copy);field.focus();field.select();let copied=false;try{await navigator.clipboard.writeText(field.value);copied=true}catch{}const status=document.getElementById('status-'+button.dataset.copy.slice(-2));status.textContent=copied?'Caption copied.':'Caption selected. Use your device’s Copy action.'}));''',encoding='utf-8')
def markdown_body(text):
    blocks=[]
    for block in text.strip().split('\n\n'):
        if block.startswith('#'):
            level=len(block)-len(block.lstrip('#'));blocks.append(f'<h{level}>{html.escape(block[level:].strip())}</h{level}>')
        elif re.match(r'^\d+\. ',block):
            blocks.append('<ol>'+''.join('<li>'+html.escape(re.sub(r'^\d+\. ','',line))+'</li>' for line in block.splitlines())+'</ol>')
        else: blocks.append('<p>'+html.escape(block).replace('\n',' ')+'</p>')
    return ''.join(blocks)
vision=(out/'GAME-VISION-EN.md').read_text(encoding='utf-8')
(out/'game-vision.html').write_text(head+'<title>Union of Kingdoms — Goal and development</title><header><nav><a href="index.html">Back to campaign</a><a href="GAME-VISION-EN.md" download>Download the text</a></nav></header><main class="reading">'+markdown_body(vision)+'</main></html>',encoding='utf-8')
print(f'Built gallery with {len(cards)} posts and a standalone game overview.')
