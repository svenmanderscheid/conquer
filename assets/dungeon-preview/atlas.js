// Read-only fantasy concept. Historical inspirations and legends are identified separately; all bosses, rewards and progression are fictional.
const dungeons = [
  {
    "id": "05",
    "name": "Clervaux",
    "title": "Die Abtei des letzten Liedes",
    "art": "frost_hollow",
    "theme": "Chorgesang & Winterzauber",
    "boss": "Der Stille Kantor",
    "drop": "Klangkristall",
    "icon": "crystal-flask",
    "material": "Material für schützende Klangrelikte",
    "description": "Über dem Tal von Clervaux bewahrt ein Orden die sieben Klänge des Nordens. Seit die letzte Glocke verstummte, liegt seine Abtei unter gläsernem Frost. Folgt den eingefrorenen Melodien durch den Kreuzgang und entreißt dem Stillen Kantor den letzten Ton, bevor auch die Stimmen des Tales erlöschen.",
    "history": "Die Benediktinerabtei von Clervaux wurde 1910 gegründet und ist für ihren gregorianischen Chorgesang bekannt. Die vereiste Abtei und ihr Orden sind unsere Fantasy-Erfindung.",
    "sources": [
      {
        "label": "Abtei Clervaux · Geschichte",
        "url": "https://www.abbaye-clervaux.lu/en/history"
      },
      {
        "label": "Visit Clervaux · Abtei und Chorgesang",
        "url": "https://www.visit-clervaux.lu/en/patrimoine/clervaux-abbey-and-its-churches"
      }
    ],
    "biome": "frost",
    "state": "locked",
    "requirement": "Meistere zuerst den Dungeon von Wiltz.",
    "point": [
      253,
      148
    ]
  },
  {
    "id": "08",
    "name": "Wiltz",
    "title": "Die Gerberei der Dornen",
    "art": "thorn_maze",
    "theme": "Alte Zünfte & Waldzauber",
    "boss": "Der Rindenmeister",
    "drop": "Runenleder",
    "icon": "dragon-scale-mail",
    "material": "Material für leichte Schutzrüstungen",
    "description": "In den alten Gerberhöfen von Wiltz wurde einst Leder gefertigt, das selbst Dornen nicht durchdringen konnten. Nun hat der Rindenmeister die Werkstätten mit Wurzeln versiegelt; verzauberte Häute wandern ohne Träger durch die Hallen. Befreit die gefangenen Handwerker und gewinnt das vergessene Zeichen ihrer Zunft zurück.",
    "history": "Wiltz besaß eine über Jahrhunderte bedeutende Gerbertradition. Die Geschichte des Lederhandwerks wird auch im Museum im Schloss vermittelt. Lebende Häute und der Rindenmeister sind erfunden.",
    "sources": [
      {
        "label": "Gemeinde Wiltz · Geschichte des Lederhandwerks",
        "url": "https://www.wiltz.lu/de/die-gemeinde/allgemeine-informationen/geschichte"
      }
    ],
    "biome": "forest",
    "state": "locked",
    "requirement": "Meistere zuerst den Dungeon von Redange.",
    "point": [
      172,
      277
    ]
  },
  {
    "id": "09",
    "name": "Vianden",
    "title": "Der Schwur der Sturmfeste",
    "art": "storm_spire",
    "theme": "Grafenburgen & gebrochene Eide",
    "boss": "Der Graf ohne Namen",
    "drop": "Sturmwappen",
    "icon": "stormbreaker",
    "material": "Material für Waffen des Sturms",
    "description": "Hoch über der Our hält die Sturmfeste von Vianden einem Gewitter stand, das seit Generationen nicht weiterzieht. In ihrer Halle schlagen leere Rüstungen die Banner vergessener Grafen. Der Graf ohne Namen bewacht einen gebrochenen Bund: Nur Gefährten, die einander durch den Sturm tragen, können sein Wappen erringen.",
    "history": "Die Burg Vianden war die Residenz der Grafen von Vianden und wurde über mehrere Jahrhunderte ausgebaut. Der namenlose Graf, sein Bund und das ewige Gewitter gehören zur Spielwelt.",
    "sources": [
      {
        "label": "Burg Vianden · Bau- und Herrschaftsgeschichte",
        "url": "https://castle-vianden.lu/gb/geschichte/"
      }
    ],
    "biome": "frost",
    "state": "locked",
    "requirement": "Meistere zuerst den Dungeon von Diekirch.",
    "point": [
      345,
      263
    ]
  },
  {
    "id": "06",
    "name": "Diekirch",
    "title": "Die Nachtwacht von Bourscheid",
    "art": "shadow_crypt",
    "theme": "Burgherren & steinerne Wachen",
    "boss": "Der Eidwächter",
    "drop": "Sauerwacht-Siegel",
    "icon": "kite-shield",
    "material": "Material für schützende Relikte",
    "description": "Auf dem Felsen von Bourscheid entzündet sich jede Nacht eine Wacht aus blauen Flammen. Die steinerne Burggarde fordert weiter den Tribut längst vergangener Herren und hält die Vorräte der umliegenden Dörfer gefangen. Durchbrecht den alten Befehl und erinnert den Eidwächter daran, wen seine Mauern eigentlich schützen sollten.",
    "history": "Die Burg Bourscheid liegt im Kanton Diekirch. Zur historischen Herrschaft gehörten umliegende Dörfer, deren Bewohner unter anderem Abgaben und Versorgungsdienste leisten mussten. Die steinerne Nachtwacht ist erfunden.",
    "sources": [
      {
        "label": "Gemeinde Bourscheid · Burg und Herrschaft",
        "url": "https://bourscheid.lu/la-commune/presentation/bourscheid-histoire-courte/"
      }
    ],
    "biome": "forest",
    "state": "locked",
    "requirement": "Meistere zuerst den Dungeon von Mersch.",
    "point": [
      304,
      365
    ]
  },
  {
    "id": "07",
    "name": "Redange",
    "title": "Die Steinchronik von Useldingen",
    "art": "thorn_maze",
    "theme": "Burgtrümmer & erwachte Erinnerungen",
    "boss": "Der Vergessene Burgherr",
    "drop": "Erinnerungsstein",
    "icon": "fragment-of-golem",
    "material": "Material für standhafte Rüstungen",
    "description": "Nachts kehren die verstreuten Steine der Burg Useldingen an ihren alten Platz zurück. Jeder trägt eine Erinnerung an fremde Banner, verlorene Erben und zerrissene Bündnisse. Doch der Vergessene Burgherr setzt daraus immer denselben Krieg zusammen. Ordnet die Steinchronik neu, bevor die wandernden Mauern das Tal verschließen.",
    "history": "Die Herrschaft Useldingen entstand um 1100. Die Burg erlitt schwere Schäden, verfiel später und wurde als Steinbruch genutzt. Die wandernden Steine und ihre Chronik sind eine freie Fantasy-Deutung.",
    "sources": [
      {
        "label": "Visit Luxembourg · Geschichte der Burg Useldingen",
        "url": "https://www.visitluxembourg.com/de/attraktion/schloss-useldange"
      }
    ],
    "biome": "forest",
    "state": "open",
    "requirement": "In diesem Beispiel bereits freigeschaltet.",
    "point": [
      156,
      423
    ]
  },
  {
    "id": "04",
    "name": "Mersch",
    "title": "Die Hallen der drei Flüsse",
    "art": "sunken_temple",
    "theme": "Alte Mosaike & Flussmagie",
    "boss": "Der Dreistromhüter",
    "drop": "Dreistrom-Perle",
    "icon": "jewel-of-harmony",
    "material": "Material für Heilungsrelikte",
    "description": "Wo Alzette, Eisch und Mamer zusammenfinden, ruhen unter Mersch die Mosaikhallen eines vergessenen Volkes. Drei Wasseradern speisen ihr leuchtendes Becken, doch ein zerbrochener Pakt hat sie gegeneinander aufgebracht. Besänftigt die Flüsse und stellt euch dem Dreistromhüter, der Heilwasser in eine reißende Flut verwandelt.",
    "history": "Mersch liegt am Zusammenfluss von Alzette, Eisch und Mamer. Die örtliche Römervilla besaß Mosaike, Warmluftheizungen und ein großes Wasserbecken. Die unterirdischen Hallen und der Flusspakt sind erfunden.",
    "sources": [
      {
        "label": "Gemeinde Mersch · Römervilla und Ortsgeschichte (PDF)",
        "url": "https://www.mersch.lu/media/4e79fa1e-9bf2-4682-b453-c27bd9407946/depliant-1-rundgang-de.pdf"
      }
    ],
    "biome": "forest",
    "state": "open",
    "requirement": "In diesem Beispiel bereits freigeschaltet.",
    "point": [
      302,
      466
    ]
  },
  {
    "id": "10",
    "name": "Echternach",
    "title": "Das lebende Skriptorium",
    "art": "thorn_maze",
    "theme": "Goldene Handschriften & Tintenmagie",
    "boss": "Der Tintenwächter",
    "drop": "Goldene Initiale",
    "icon": "scroll-of-drain",
    "material": "Material für Zauberbücher und Wissensrelikte",
    "description": "Unter der Abtei von Echternach liegt eine Schreibstube, in der goldene Buchstaben Licht schenken. Seit ein schwarzer Tropfen die Chronik berührte, steigen die Tiere ihrer Randzeichnungen aus dem Pergament und verirren sich in den Felswäldern. Findet die verlorenen Seiten und bezwingt den Tintenwächter, bevor er die Namen des Landes auslöscht.",
    "history": "Im Skriptorium der Abtei Echternach wurden kostbare Handschriften geschrieben und mit Buchmalerei geschmückt. Das Abteimuseum zeigt Nachbildungen berühmter Werke. Lebende Schriftzeichen und der Tintenwächter sind erfunden.",
    "sources": [
      {
        "label": "Abteimuseum Echternach · Handschriften und Skriptorium",
        "url": "https://abteimuseum.org/abbey-museum/"
      }
    ],
    "biome": "forest",
    "state": "open",
    "requirement": "In diesem Beispiel bereits freigeschaltet.",
    "point": [
      479,
      411
    ]
  },
  {
    "id": "01",
    "name": "Capellen",
    "title": "Die Spiegelburg von Koerich",
    "art": "shadow_crypt",
    "theme": "Zwei Burgen & ein verschwundenes Erbe",
    "boss": "Der Doppelvogt",
    "drop": "Zwillingssiegel",
    "icon": "mirror-of-truth",
    "material": "Material für Schutz- und Spiegelamulette",
    "description": "Im Wassergraben des Gréiweschlass von Koerich erscheint bei Mondlicht eine zweite Burg: das verschwundene Fockeschlass. Ein Doppelvogt herrscht zugleich in Stein und Spiegelbild und lässt Reisende zwischen beiden Höfen verschwinden. Vereint die getrennten Burgsiegel, um den Heimweg zu öffnen und das geraubte Erbe zurückzuholen.",
    "history": "Koerich besaß zwei Burgen: Das Gréiweschlass ist als Ruine erhalten, das Fockeschlass verschwunden. Spiegelwelt, Doppelvogt und Zwillingssiegel sind unsere Erfindungen, keine überlieferte Sage.",
    "sources": [
      {
        "label": "Gemeinde Koerich · Die beiden Burgen",
        "url": "https://www.koerich.lu/fr/commune/tourisme/histoire"
      }
    ],
    "biome": "sand",
    "state": "done",
    "requirement": "In diesem Beispiel bereits gemeistert.",
    "point": [
      195,
      569
    ]
  },
  {
    "id": "03",
    "name": "Luxembourg",
    "title": "Melusinas gebrochener Eid",
    "art": "shadow_crypt",
    "theme": "Bockfelsen, Kasematten & Wassersagen",
    "boss": "Der Hüter des falschen Schwurs",
    "drop": "Melusinas Träne",
    "icon": "gem-of-vital",
    "material": "Material für Heil- und Schutzamulette",
    "description": "Unter dem Bockfelsen trägt die Alzette Melusinas Lied durch die Kasematten. Ein fremder Hüter hat aus dem gebrochenen Vertrauen ihrer Sage einen Bann geschmiedet: Jede verschlossene Tür trennt Gefährten voneinander. Folgt der Wasserstimme und löst den falschen Schwur, damit Melusina den Weg zur verlorenen Krone weisen kann.",
    "history": "Die Melusina-Sage verbindet die Nixe mit Graf Siegfried und dem Bockfelsen. Die Kasematten gehören zur realen Festungsgeschichte, entstanden aber viel später als die erste Burg. Unsere Handlung verbindet Sage und Bauwerk frei.",
    "sources": [
      {
        "label": "Luxembourg City Tourist Office · Melusina-Sage",
        "url": "https://www.luxembourg-city.com/en/about-luxembourg-city/meng-stad-my-city/details/melusina-and-siegfried"
      },
      {
        "label": "Luxembourg City Tourist Office · Bock-Kasematten",
        "url": "https://www.luxembourg-city.com/en/tours-activities/underground/bock-casemates"
      }
    ],
    "biome": "sand",
    "state": "open",
    "requirement": "In diesem Beispiel bereits freigeschaltet.",
    "point": [
      344,
      584
    ]
  },
  {
    "id": "11",
    "name": "Grevenmacher",
    "title": "Das Tor der freien Stadt",
    "art": "storm_spire",
    "theme": "Freiheitsbriefe & verzauberte Stadttore",
    "boss": "Der Nebelzöllner",
    "drop": "Freibrief-Siegel",
    "icon": "ancient-bracelet",
    "material": "Material für Handels- und Versorgungsrelikte",
    "description": "An den alten Toren von Grevenmacher erhebt der Nebelzöllner einen unmöglichen Preis: Jeder Händler muss seinen Namen zurücklassen. Die Worte des Freiheitsbriefs sind aus der Stadthalle gestohlen worden und schweben als Runen über der Mosel. Holt sie zurück, bevor die freie Stadt sich an keinen ihrer Bewohner mehr erinnert.",
    "history": "Grevenmacher erhielt 1252 von Graf Heinrich V. einen Freiheitsbrief. Stadtrechte und Befestigungen prägen seine Geschichte. Der Nebelzöllner und die gestohlenen Namen sind eine Fantasy-Erfindung.",
    "sources": [
      {
        "label": "Stadt Grevenmacher · Freiheitsbrief und Stadtgeschichte",
        "url": "https://grevenmacher.lu/maacher-geschicht-op-eng-popular-maneier-virgestallt/"
      }
    ],
    "biome": "sand",
    "state": "locked",
    "requirement": "Meistere zuerst den Dungeon von Echternach.",
    "point": [
      476,
      527
    ]
  },
  {
    "id": "02",
    "name": "Esch-sur-Alzette",
    "title": "Die Herzschmiede des Minett",
    "art": "ember_vault",
    "theme": "Rote Erde & uralte Schmiedekunst",
    "boss": "Der Schlackenschmied",
    "drop": "Glutminett",
    "icon": "flame-flower",
    "material": "Material für feurige Waffen",
    "description": "Tief in der roten Erde des Minett haben Bergleute ein Erz geweckt, das wie ein Herz schlägt. Seitdem speisen die alten Stollen eine Schmiede, deren Feuer eiserne Wächter gebiert. Öffnet die verschütteten Wege für die eingeschlossenen Arbeiter und bezwingt den Schlackenschmied, bevor sein glutrotes Heer die Hügel verlässt.",
    "history": "Eisenerzabbau und Stahlindustrie haben den Süden Luxemburgs geprägt; der Name Minett verweist auf das örtliche Eisenerz. Das schlagende Erzherz und die eisernen Wächter sind erfunden.",
    "sources": [
      {
        "label": "Visit Luxembourg · Bergbau und Stahl im Minett",
        "url": "https://www.visitluxembourg.com/get-to-know-luxembourg/on-the-trail-of-sweat-and-steel"
      }
    ],
    "biome": "ash",
    "state": "done",
    "requirement": "In diesem Beispiel bereits gemeistert.",
    "point": [
      247,
      695
    ]
  },
  {
    "id": "12",
    "name": "Remich",
    "title": "Die Masken von Ricciacum",
    "art": "sunken_temple",
    "theme": "Antike Bühnen & verzauberte Masken",
    "boss": "Der Maskenkönig",
    "drop": "Ricciacum-Maske",
    "icon": "heroic-spirit",
    "material": "Material für stärkende Bann- und Mutrelikte",
    "description": "Im alten Ricciacum bei Dalheim beginnt jede Nacht ein Schauspiel vor leeren Rängen. Wer auf der vergessenen Handelsstraße vorbeizieht, wird vom Maskenkönig in eine Rolle gezwungen und vergisst sein früheres Leben. Steigt zwischen den steinernen Sitzreihen hinab und zerbrecht die letzte Maske, bevor der Vorhang für immer fällt.",
    "history": "Dalheim liegt im Kanton Remich. Die gallorömische Siedlung Ricciacum besaß ein Theater und lag an einer wichtigen römischen Straße. Das nächtliche Schauspiel und der Maskenkönig sind erfunden.",
    "sources": [
      {
        "label": "Visit Moselle · Ricciacum und das Theater von Dalheim",
        "url": "https://www.visitmoselle.lu/place/gallo-roman-site-dalheim"
      },
      {
        "label": "Gemeinde Dalheim · Lage im Kanton Remich",
        "url": "https://dalheim.lu/decouvrir-la-commune/dgemeng-vun-uewen/"
      }
    ],
    "biome": "sand",
    "state": "locked",
    "requirement": "Meistere zuerst den Dungeon von Grevenmacher.",
    "point": [
      452,
      680
    ]
  }
];
const palette = { forest:'#b9c985', frost:'#dce9e5', sand:'#e6cc96', ash:'#ad9990' };
const $ = (selector) => document.querySelector(selector);
const stage = $('#map-stage');
const detail = $('#dungeon-detail');
const dialog = $('#dungeon-dialog');
const escape = value => String(value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const art = dungeon => `../art/dungeons/${dungeon.art}.webp`;
const selectionFromHash = () => dungeons.find(d => `#kanton-${d.id}` === location.hash)?.id || null;
const initialSelection = selectionFromHash();
let selected = initialSelection || '10';
let showProgress = false;
let map;
let returnFocus = null;
let closing = false;

function svgElement(name, attributes) {
  const element = document.createElementNS('http://www.w3.org/2000/svg', name);
  for (const [key, value] of Object.entries(attributes)) element.setAttribute(key, String(value));
  return element;
}

function drawMap(geography) {
  stage.replaceChildren();
  map = svgElement('svg', { viewBox:'0 0 650 800', 'aria-hidden':'true' });
  const allPaths = geography.features.map(f => f.path).join(' ');
  map.innerHTML = `<defs><filter id="land-shadow" x="-10%" y="-10%" width="120%" height="125%"><feDropShadow dx="0" dy="7" stdDeviation="0" flood-color="#786342" flood-opacity=".3"/></filter><filter id="selected-glow" x="-20%" y="-20%" width="140%" height="140%"><feDropShadow dx="0" dy="0" stdDeviation="5" flood-color="#c5a361" flood-opacity=".7"/></filter><clipPath id="land-clip"><path d="${allPaths}"/></clipPath></defs>`;
  map.append(svgElement('path', {d:allPaths, fill:'#e6cc96', stroke:'#c7b692', 'stroke-width':9, 'stroke-linejoin':'round', filter:'url(#land-shadow)'}));
  const regions = svgElement('g', {class:'canton-regions'});
  for (const feature of geography.features) {
    const dungeon = dungeons.find(d => d.id === feature.id);
    if (!dungeon) continue;
    regions.append(svgElement('path', {d:feature.path, fill:palette[dungeon.biome], class:'canton-shape', 'data-canton':dungeon.id}));
  }
  map.append(regions);
  const scenery = svgElement('g', {'clip-path':'url(#land-clip)', 'pointer-events':'none', opacity:'.63'});
  map.append(scenery);
  stage.append(map);
  // Reuse approved scenery, with deterministic sparse placement clear of all labels.
  let seed = 27;
  const random = () => { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296; };
  const paths = [...regions.children];
  for (let i = 0; i < 320; i++) {
    const x = 60 + random() * 525, y = 60 + random() * 680;
    if (dungeons.some(d => Math.abs(d.point[0] - x) < 67 && Math.abs(d.point[1] - y) < 49)) continue;
    const region = paths.find(path => path.isPointInFill(new DOMPoint(x, y)));
    if (!region) continue;
    const dungeon = dungeons.find(d => d.id === region.dataset.canton);
    const type = dungeon.biome === 'frost' ? 'pine' : random() > .55 ? 'oak' : 'pine';
    if (dungeon.biome === 'ash') continue;
    const size = 35 + random() * 20;
    scenery.append(svgElement('image', {href:`../art/fantasy-village-v1/world-tree-${type}-v7.png`, x:x-size/2, y:y-size/2, width:size, height:size}));
  }
  for (const dungeon of dungeons) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'map-marker';
    button.dataset.select = dungeon.id;
    button.style.left = `${dungeon.point[0] / 650 * 100}%`;
    button.style.top = `${dungeon.point[1] / 800 * 100}%`;
    button.innerHTML = `<img class="marker-art" src="${art(dungeon)}" alt="" width="44" height="44"><span class="marker-label">${escape(dungeon.name)}</span><span class="marker-state" hidden aria-hidden="true"></span>`;
    stage.append(button);
  }
}

function drawList() {
  $('#list-view').innerHTML = dungeons.map(d => `<button type="button" class="canton-card" data-select="${d.id}"><img src="${art(d)}" alt="" width="58" height="58" loading="lazy"><span><strong>${escape(d.name)}</strong><small>${escape(d.title)}</small></span></button>`).join('');
}

function updateSelection({ announce = false } = {}) {
  const dungeon = dungeons.find(d => d.id === selected);
  document.querySelectorAll('[data-select]').forEach(button => {
    const data = dungeons.find(d => d.id === button.dataset.select);
    const status = showProgress ? ({done:'Gemeistert', open:'Verfügbar', locked:'Noch gesperrt'}[data.state]) : 'Dungeon ansehen';
    button.setAttribute('aria-pressed', String(data.id === selected));
    button.setAttribute('aria-haspopup', 'dialog');
    button.setAttribute('aria-controls', 'dungeon-dialog');
    button.setAttribute('aria-label', `${data.name}: ${data.title}. ${status}`);
    button.classList.toggle('is-locked', showProgress && data.state === 'locked');
    button.classList.toggle('is-done', showProgress && data.state === 'done');
    const badge = button.querySelector('.marker-state');
    if (badge) { badge.hidden = !showProgress; badge.innerHTML = data.state === 'locked' ? '<svg viewBox="0 0 16 16" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="2" fill="currentColor"/><path d="M5 7V5a3 3 0 016 0v2" fill="none" stroke="currentColor" stroke-width="2"/></svg>' : {done:'✓', open:'◇'}[data.state]; }
  });
  document.querySelectorAll('.canton-shape').forEach(path => {
    const data = dungeons.find(d => d.id === path.dataset.canton);
    path.classList.toggle('is-selected', data.id === selected);
    path.setAttribute('fill', showProgress && data.state === 'locked' ? '#ddd2c0' : palette[data.biome]);
  });
  // Keep the selected border above adjoining regions without moving keyboard targets.
  const selectedPath = map?.querySelector(`[data-canton="${selected}"]`);
  if (selectedPath) selectedPath.parentNode.append(selectedPath);
  document.querySelectorAll('.progress-legend').forEach(element => { element.hidden = !showProgress; });
  const access = showProgress
    ? `<strong>${{done:'✓ Bereits gemeistert',open:'◇ Bereit für eine Expedition',locked:'Noch verschlossen'}[dungeon.state]}</strong>${escape(dungeon.requirement)}`
    : '<strong>Ein Kanton. Ein eigener Dungeon.</strong>Seine besondere Beute macht jede Rückkehr lohnenswert.';
  $('#dungeon-modal-title').textContent = dungeon.title;
  $('#dungeon-modal-canton').textContent = `Kanton ${dungeon.name} · ${dungeon.id} / 12`;
  detail.innerHTML = `<section class="detail-visual" aria-label="Dungeon und Endboss"><div class="detail-art"><img src="${art(dungeon)}" alt="Illustration zum Dungeon ${escape(dungeon.title)}" width="450" height="300"><span class="detail-theme">${escape(dungeon.theme)}</span></div>
    <p class="detail-description">${escape(dungeon.description)}</p>
    <div class="detail-stats"><div><small>Endboss</small><strong>${escape(dungeon.boss)}</strong></div><div><small>Expedition</small><strong>Gemeinsam mit 2–4 Spielern</strong></div></div></section><section class="detail-rewards" aria-label="Beute und Freischaltung">
    <div class="loot-heading"><h3>Der Schatz dieses Kantons</h3><span>Beute-Idee</span></div>
    <div class="signature-drop"><div class="drop-icon"><img src="../art/items/treasures/${dungeon.icon}.png" alt="" width="49" height="49"></div><div><small>Kantonsspezifischer Drop</small><strong>${escape(dungeon.drop)}</strong><p>${escape(dungeon.material)}</p></div></div>
    <div class="secondary-drops"><img src="../art/items/backpack/gold-bundle.svg" alt=""><img src="../art/items/treasures/heroic-spirit.png" alt=""><span>Dazu: Gold und eine Chance auf Reliktfragmente</span></div>
    <div class="detail-access">${access}</div>
    <details class="detail-history"><summary>Luxemburg hinter der Legende</summary><p>${escape(dungeon.history)}</p><ul aria-label="Quellen zum historischen Vorbild">${dungeon.sources.map(source => `<li><a href="${escape(source.url)}" target="_blank" rel="noopener noreferrer">${escape(source.label)} <span class="visually-hidden">(öffnet in neuem Tab)</span><span aria-hidden="true">↗</span></a></li>`).join('')}</ul></details></section>`;
  detail.setAttribute('aria-busy', 'false');
  if (announce) $('#selection-announcement').textContent = `${dungeon.name}: ${dungeon.title}. Vorgeschlagener Drop: ${dungeon.drop}.`;
}

function openDialog(opener = null) {
  if (dialog.open) return;
  returnFocus = opener?.matches('button') ? opener : document.querySelector(`${$('#map-view').hidden ? '.canton-card' : '.map-marker'}[data-select="${selected}"]`);
  document.body.classList.add('dungeon-popup-open');
  dialog.showModal();
}

function hideDialog() {
  if (!dialog.open) return;
  dialog.close();
  document.body.classList.remove('dungeon-popup-open');
  if (returnFocus?.isConnected && returnFocus.getClientRects().length) returnFocus.focus({preventScroll:true});
}

function select(id, { remember = true, opener = null } = {}) {
  if (closing || !dungeons.some(d => d.id === id)) return;
  if (remember) {
    // One history entry per open popup, so Back closes it even after Next.
    const method = dialog.open ? 'replaceState' : 'pushState';
    window.history[method]({...window.history.state, atlasDungeonPopup:true}, '', `#kanton-${id}`);
  }
  selected = id;
  updateSelection({announce:true});
  detail.scrollTop = 0;
  openDialog(opener);
}

function dismissDialog() {
  if (!dialog.open || closing) return;
  hideDialog();
  if (window.history.state?.atlasDungeonPopup) {
    closing = true;
    window.history.back();
  } else {
    window.history.replaceState(window.history.state, '', location.pathname + location.search);
  }
}

function syncLocation() {
  closing = false;
  const id = selectionFromHash();
  if (id) {
    if (!dialog.open || id !== selected) select(id, {remember:false});
  } else hideDialog();
}

document.addEventListener('click', event => {
  const target = event.target.closest('[data-select], [data-canton]');
  if (target) select(target.dataset.select || target.dataset.canton, {opener:target});
  const view = event.target.closest('[data-view]');
  if (view) {
    const list = view.dataset.view === 'list';
    $('#map-view').hidden = list;
    $('#list-view').hidden = !list;
    document.querySelectorAll('[data-view]').forEach(button => button.setAttribute('aria-pressed', String(button === view)));
  }
  if (event.target.closest('[data-next]')) select(dungeons[(dungeons.findIndex(d => d.id === selected) + 1) % dungeons.length].id);
  if (event.target.closest('[data-close-dialog]')) dismissDialog();
});
$('#progress-toggle').addEventListener('change', event => { showProgress = event.target.checked; updateSelection(); });
dialog.addEventListener('cancel', event => { event.preventDefault(); dismissDialog(); });
const outsideDialog = event => {
  const rect = dialog.getBoundingClientRect();
  return event.target === dialog && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom);
};
let backdropPress = false;
dialog.addEventListener('pointerdown', event => { backdropPress = outsideDialog(event); });
dialog.addEventListener('click', event => { if (backdropPress && outsideDialog(event)) dismissDialog(); backdropPress = false; });
window.addEventListener('popstate', syncLocation);
window.addEventListener('hashchange', syncLocation);

drawList();
updateSelection();
if (initialSelection) {
  // A shared URL also gets a local map entry behind its popup.
  if (!window.history.state?.atlasDungeonPopup) {
    const url = location.href;
    window.history.replaceState(window.history.state, '', location.pathname + location.search);
    window.history.pushState({...window.history.state, atlasDungeonPopup:true}, '', url);
  }
  openDialog();
}
try {
  const response = await fetch(new URL('cantons.json', import.meta.url));
  if (!response.ok) throw new Error('Map data unavailable');
  const geography = await response.json();
  if (geography.features.length !== 12 || geography.features.some(f => !dungeons.some(d => d.id === f.id))) throw new Error('Unexpected canton data');
  drawMap(geography);
  updateSelection();
  if (dialog.open && !returnFocus) returnFocus = document.querySelector(`.map-marker[data-select="${selected}"]`);
} catch (error) {
  const message = document.createElement('p');
  message.className = 'map-loading';
  message.textContent = 'Die Karte konnte nicht geladen werden. Alle Dungeons findest du in der Kantonsliste.';
  stage.replaceChildren(message);
  console.error('Dungeon atlas:', error);
}
