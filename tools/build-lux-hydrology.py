"""Project official INSPIRE/AGE river lines and lakes into preview fields.

Build dependency: Node and Proj4js 2.22.0. No GIS library is needed in the browser.
Sources are cached under artifacts/world-lux-preview/hydro-source (CC0).
GML EPSG:3035 has northing/easting axis order, unlike the x/y Proj4js input.
"""
import json
import math
import io
import shutil
import subprocess
import tarfile
import xml.etree.ElementTree as ET
from pathlib import Path
from urllib.request import urlopen

ROOT = Path(__file__).resolve().parents[1]
CACHE = ROOT / 'artifacts/world-lux-preview/hydro-source'

OUT = ROOT / 'assets/world-lux-preview/hydrology.json'
MIN_LAKE_AREA_M2 = 50000  # Keep large lakes; small ponds must not split city sites.
SOURCES = {
    'network.gml': 'https://data.public.lu/en/datasets/r/9cee7d08-34e2-4bce-8a31-8e00cf6e7dfd',
    'standing-water-age.gml': 'https://data.public.lu/en/datasets/r/181e52af-445b-4495-94d0-dcf6e2a54280',
}
CACHE.mkdir(parents=True, exist_ok=True)
for name, url in SOURCES.items():
    if not (CACHE / name).exists():
        with urlopen(url, timeout=60) as response:
            (CACHE / name).write_bytes(response.read())

if not (CACHE/'proj4.js').exists():
    with urlopen('https://registry.npmjs.org/proj4/2.22.0',timeout=30) as response:
        package=json.load(response)
    with urlopen(package['dist']['tarball'],timeout=30) as response:
        archive=response.read()
    with tarfile.open(fileobj=io.BytesIO(archive),mode='r:gz') as tar:
        for name in ['package/dist/proj4.js','package/LICENSE.md']:
            member=tar.extractfile(name)
            if member:(CACHE/Path(name).name).write_bytes(member.read())

network_root=ET.parse(CACHE/'network.gml').getroot()
lake_root=ET.parse(CACHE/'standing-water-age.gml').getroot()
position_lists=[p.text for root in [network_root,lake_root] for p in root.findall('.//{http://www.opengis.net/gml/3.2}posList')]
node=shutil.which('node')
assert node, 'Node is required for the build-time coordinate transform'
conversion=subprocess.run([node,str(ROOT/'tools/lux-hydro-project.cjs'),str(CACHE/'proj4.js')],
    input=json.dumps(position_lists),text=True,encoding='utf-8',capture_output=True,check=True)
converted=dict(zip(position_lists,json.loads(conversion.stdout)))

geo = json.loads((ROOT / 'artifacts/realm-map/geodata.json').read_text(encoding='utf-8'))
def geo_rings(feature):
    c = feature['geometry']['coordinates']
    return [r for polygon in c for r in polygon] if feature['geometry']['type'] == 'MultiPolygon' else c
country_points = [p for f in geo['cantons']['features'] for r in geo_rings(f) for p in r]
x0,x1 = min(p[0] for p in country_points),max(p[0] for p in country_points)
y0,y1 = min(p[1] for p in country_points),max(p[1] for p in country_points)
factor = math.cos(math.radians(49.8))
scale = min(1400 / ((x1-x0)*factor),1900 / (y1-y0))
ox = (1600-(x1-x0)*factor*scale)/2
oy = (2100-(y1-y0)*scale)/2
field_scale = min(768/1326.82,1100/1900)

def project(lon,lat):
    return [round((ox+(lon-x0)*factor*scale-136.59)*field_scale,3),
            round((oy+(y1-lat)*scale-100)*field_scale,3)]

def coordinates(text):
    points=converted[text]
    assert all(5.3<a<6.8 and 49.2<b<50.5 for a,b in points), 'CRS/axis order mismatch'
    return [project(a,b) for a,b in points]

def closest(p,a,b):
    dx,dy=b[0]-a[0],b[1]-a[1]
    t=max(0,min(1,((p[0]-a[0])*dx+(p[1]-a[1])*dy)/(dx*dx+dy*dy))) if dx or dy else 0
    return [a[0]+t*dx,a[1]+t*dy]

def distance2(p,a,b):
    q=closest(p,a,b)
    return (p[0]-q[0])**2+(p[1]-q[1])**2

def simplify(points,tolerance=.12):
    # Douglas-Peucker, preserving every link endpoint and confluence exactly.
    if len(points)<3:return points
    keep={0,len(points)-1};stack=[(0,len(points)-1)]
    while stack:
        a,b=stack.pop()
        if b-a<2:continue
        i=max(range(a+1,b),key=lambda n:distance2(points[n],points[a],points[b]))
        if distance2(points[i],points[a],points[b])>tolerance*tolerance:
            keep.add(i);stack.extend([(a,i),(i,b)])
    return [points[i] for i in sorted(keep)]

def bounds(points):
    return [[min(p[0] for p in points),min(p[1] for p in points)],
            [max(p[0] for p in points),max(p[1] for p in points)]]

NS={'g':'http://www.opengis.net/gml/3.2','n':'http://inspire.ec.europa.eu/schemas/gn/4.0',
    'h':'http://inspire.ec.europa.eu/schemas/hy-n/5.0','p':'http://inspire.ec.europa.eu/schemas/hy-p/5.0'}
major={'Mosel':(3.2,'Remich'),'Sauer':(2.0,'Diekirch'),'Our':(1.5,'Vianden'),
       'Alzette':(1.2,'Luxembourg'),'Attert':(.85,'Redange'),'Eisch':(.8,'Habscht'),
       'Mamer':(.7,'Mamer'),'Wiltz':(.85,'Wiltz'),'Clerve':(.75,'Clervaux'),
       'Woltz':(.7,'Troisvierges'),'Syre':(.7,'Betzdorf'),'Ernz Blanche':(.7,"Vallée de l'Ernz"),
       'Ernz Noire':(.7,'Berdorf'),'Chiers':(.75,'Pétange'),'Wark':(.7,'Ettelbruck')}
aliases={'Ernz Blanche':'Weiße Ernz','Ernz Noire':'Schwarze Ernz','Syre':'Syr / Syre','Clerve':'Clerve / Klerf'}
lines=[]
root=network_root
for f in root.findall('.//h:WatercourseLink',NS):
    pos=f.find('.//g:posList',NS)
    if pos is None:continue
    name=f.findtext('.//n:text',default='',namespaces=NS)
    # Export only the selected main courses, so drawing and every placement
    # check use the same uncluttered world. Source GML stays intact in cache.
    if name not in major:continue
    raw=coordinates(pos.text);points=simplify(raw)
    if len(points)<2:continue
    width=major[name][0]
    lines.append({'id':f.attrib['{'+NS['g']+'}id'],'name':aliases.get(name,name),
                  'sourceName':name,'points':points,'bounds':bounds(points),'width':width,'major':True})

# Keep real shorelines only for large lakes/reservoirs of at least 5 hectares.
# Smaller basins are ordinary buildable terrain throughout the play preview.
lakes=[]
for f in lake_root.findall('.//p:StandingWater',NS):
    area=float(f.findtext('p:surfaceArea',default='0',namespaces=NS))
    if area<MIN_LAKE_AREA_M2:continue
    for i,patch in enumerate(f.findall('.//g:PolygonPatch',NS)):
        rings=[simplify(coordinates(p.text),.08) for p in patch.findall('.//g:posList',NS)]
        rings=[r for r in rings if len(r)>=4]
        if not rings:continue
        source_id=f.attrib['{'+NS['g']+'}id']
        lakes.append({'id':source_id+f'-{i}','name':'Obersauer-Stausee' if source_id=='stausee_sauer.1' else '', 'rings':rings,
                      'bounds':bounds([p for r in rings for p in r]),'areaM2':area})

commune_points={f['properties']['name']:project(*f['properties']['point']) for f in geo['communes']['features']}
visits=[]
for name,(_,commune) in major.items():
    target=commune_points[commune]
    choices=[closest(target,a,b) for line in lines if line['sourceName']==name for a,b in zip(line['points'],line['points'][1:])]
    p=min(choices,key=lambda p:(p[0]-target[0])**2+(p[1]-target[1])**2)
    visits.append({'id':'river-'+name.replace(' ','-'),'name':aliases.get(name,name),'point':[round(v,3) for v in p]})

reservoir=next(l for l in lakes if l['name']=='Obersauer-Stausee')
# Find a point in the wide part of the actual reservoir, not the bounding-box
# centre (which can be dry land for such a winding lake).
def inside_lake(x,y):
    inside=False
    for ring in reservoir['rings']:
        for a,b in zip(ring,ring[1:]+ring[:1]):
            if (a[1]>y)!=(b[1]>y) and x<(b[0]-a[0])*(y-a[1])/(b[1]-a[1])+a[0]:inside=not inside
    return inside
lo,hi=reservoir['bounds']
lake_candidates=[[x+.5,y+.5] for x in range(math.floor(lo[0]),math.ceil(hi[0]),2)
                 for y in range(math.floor(lo[1]),math.ceil(hi[1]),2) if inside_lake(x+.5,y+.5)]
centre=max(lake_candidates,key=lambda p:min(distance2(p,a,b) for ring in reservoir['rings'] for a,b in zip(ring,ring[1:]+ring[:1])))
visits.append({'id':'lake-upper-sure','name':'Obersauer-Stausee','point':centre})
data={'source':'Géoportail / AGE hydrographic network and standing waters, INSPIRE, CC0',
      'sources':list(SOURCES.values()),'sourceCRS':'EPSG:3035 (northing/easting)',
      'units':'fields','simplificationTolerance':.12,'widths':'Stylised for game readability, not surveyed widths',
      'selection':{'rivers':'main courses only','riverNames':list(major),'minimumLakeAreaM2':MIN_LAKE_AREA_M2},
      'rivers':lines,'lakes':lakes,'visits':visits}
OUT.write_text(json.dumps(data,ensure_ascii=False,separators=(',',':')),encoding='utf-8')
print(json.dumps({'riverLinks':len(lines),'points':sum(len(r['points']) for r in lines),'lakes':len(lakes),'bytes':OUT.stat().st_size}))
print('Largest lakes:',[(r['id'],r['areaM2'],r['bounds']) for r in sorted(lakes,key=lambda r:-r['areaM2'])[:5]])
