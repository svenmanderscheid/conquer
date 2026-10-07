# Image accuracy and coherence review

The review inspected 1,016 unique active, fallback and derived image files across the public website and game. The review inventory and before/after evidence are retained locally under `output/image-audit-2026-10-05/` and `output/image-fixes-2026-10-05/`.

## Corrections

- The complete 66-file troop family is versioned as `fantasy-troops-v3`. Rider antlers attach to the mount, not the rider; drawn bows connect the string to the arrow nock. T4 and T5 were initially changed to crossbows; the user corrected this interpretation on 7 October, as recorded below. Individual equipment, colours and progression remain distinct.
- Twenty-five menu exports use `menu-icons-v2`; seven clipped silhouettes were recovered from their approved source sheet. Research uses 129 corrected node images in `characters-v10`, with correct beneficiaries, twelve distinct unlock illustrations, unambiguous effects and clean transparency. The AP fallback depicts a potion.
- Six campaign motifs and three hero banners use coherent current character and relic designs. Both closed and open alpha poster variants are supplied. The army poster says five tiers. Favicon, PWA, Apple and Android launcher derivatives share the corrected master.
- Sixteen building portraits now match the painted city. The sawmill construction cycle omits the inconsistent beam/trestle pose. Alliance centre, four shrines and seventeen legacy castle skins have coherent painted silhouettes and registered themed motion. The older Congress runtime has a painted fallback; the independently updated forum keeps its own mapping.
- The default march uses corrected troops. Dragon, phoenix and tempest have matching transparent stills and loops. Reduced motion uses stable stills. Concurrent changes to the standard formation animation remain intact.

## Verification

Actual app checks covered desktop, narrow portrait and short landscape: 51 initial views and six final crossbow views, without broken images, browser errors or overflow. Research passed five viewport sizes, construction passed all sixteen buildings, and the focused world review covered all seventeen remastered skins, all twenty-two castle mappings, shrines, alliance centre and Congress fallback. Dragon fallback and reduced-motion checks passed. The 73 character/march images were rebuilt independently with identical hashes. Website checks loaded all 26 page images in three formats without errors. These are browser checks, not a physical-device acceptance test.

The existing broad shrine test still contains an obsolete search-close selector; the broad painted-world suite also encounters an unrelated Magdar animation assertion. Focused replacement-path, image decoding, motion/still and actual renderer checks pass.

## Sources and release handling

Raster corrections used the built-in image generation tool. Exact prompts and source/output hashes are in the manifests beside the new troop, march and world assets and in `assets/marketing/image-repairs-20261005.json`. Menu/research exports reuse approved source artwork. Raw accepted masters remain in the local review folders. Preparation tools validate reproducible outputs.

New asset paths and explicit icon versions avoid stale browser caches. The release is isolated from concurrent gameplay edits. The live package applies only recorded image-path and cache substitutions to separately captured server sources, with previous files archived outside the public web root; it does not replace divergent live gameplay code wholesale.

## Deployment regression, 7 October 2026

A fresh live request and independent browser checks found the public troop preview serving `fantasy-troops-v2` again. All corrected v3 files were still available with the approved hashes. The live HTML response was `private, no-store` with a dynamic CDN response, so the problem was an older template on the server, not the player's browser cache. The server's separate Git history did not include the image corrections; the landing template had been replaced after the successful 5 October verification.

Deployment must preserve image fixes as part of the server's tracked release state, including the new asset directories. When applying a targeted release to a divergent live checkout, capture its current files and apply only reviewed substitutions; verify expected before and after hashes and retain a private backup. Do not replace the current server code with an older whole-project snapshot.

Run `tests/landing_artwork.cjs` after website releases, with `UOK_ARTWORK_URL=https://unionofkingdoms.com/` for the live check. It verifies the default T5 portraits and interactive T1–T5 previews against approved v3 paths and image contents at desktop, phone and landscape sizes. A GitHub push alone does not establish that the live site serves the same release.

The targeted restoration changed 43 live files and recorded all 384 approved runtime paths in server commit `a484e8a`. The three language catalogues changed only the archer equipment description. All before/after hashes and a private file backup were retained; no database or configuration changes were included. The live regression test then passed every viewport with and without JavaScript, including SHA-256 checks of all fifteen displayed troop portraits. Evidence is in `output/image-regression-2026-10-07/` and `artifacts/landing-artwork/`.

## Archer correction, 7 October 2026

The Archer must carry a bow and arrow at every tier. T4 and T5 now reuse the already corrected bow masters from the original built-in image generation, with no new generation. All active Archer portraits use the fresh `fire-archer-bow` prefix within `fantasy-troops-v3`, including training, reports, hospital, march and rally lists, the map and public website. Other Archer tiers are byte-identical copies of their approved portraits. The original crossbow assets remain historical and are no longer referenced by these consumers. The marketing description in English, German and French no longer claims progression to crossbows. The visible T4 name is Veteran Archers (Veteranenbogenschützen / Archers vétérans), matching the bow. Existing gameplay identifiers, combat values and training costs remain unchanged.

Sources, original prompts and export checksums are recorded in `assets/art/characters/fantasy-troops-v3/archer-bow-manifest.json`. Evidence for this correction is stored in `output/archer-bow-2026-10-07/`.

Research reuses the same bow portraits in 35 affected node icons (ranged effects, army scenes and the two higher-tier unlocks) under `assets/art/research/characters-bow-v1/`. Rebuild these with `node tools/build-research-character-icons-v4.cjs --archer-bows-only`. All other research artwork retains its existing addresses.

Verification of the bow correction: the public landing fixture passed all fifteen T1-T5 troop portraits at desktop, phone and landscape sizes, with and without JavaScript. The actual `/city#city` app and archery building action passed at 1280x800, 390x844 and 844x390; T4/T5 portraits and their locked research requirements loaded the new bow assets with no browser errors, missing images or inaccessible controls. Research catalogue checks passed. The three changed descriptions resolve in English, German and French. The broad locale parity test still reports 240 pre-existing missing French keys; this change preserves catalogue keys and changes only Archer editorial descriptions and display-name values. Physical-device testing was not performed for this asset-only correction.
