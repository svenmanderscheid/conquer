# Image accuracy and coherence review

The review inspected 1,016 unique active, fallback and derived image files across the public website and game. The review inventory and before/after evidence are retained locally under `output/image-audit-2026-10-05/` and `output/image-fixes-2026-10-05/`.

## Corrections

- The complete 66-file troop family is versioned as `fantasy-troops-v3`. Rider antlers attach to the mount, not the rider; drawn bows connect the string to the arrow nock. T4 crossbowmen and T5 snipers carry actual crossbows. Individual equipment, colours and progression remain distinct.
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
