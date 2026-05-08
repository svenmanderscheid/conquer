# Conquer Spec v1.7 — Update Document

This document consolidates all changes since v1.6 into a single update package. Hand this to Claude Code with the prompt:

> "Update the Conquer spec to v1.7 based on this consolidated change list. Update SPEC.md, CLAUDE.md, manifest.json, and replace conquer-32.gpl with kenney-tiny-town.gpl. Then bump the version and add a changelog entry."

---

## Summary of Changes

| # | Change | Affects |
|---|---|---|
| 1 | Visual style → Cozy-Stardew | SPEC.md §28, Asset PDF, CLAUDE.md |
| 2 | Palette → Kenney Tiny Town colors | data/palette/, SPEC.md §28 |
| 3 | One sprite per monster (all tiers same) | SPEC.md §14 |
| 4 | No separate battle sprites | SPEC.md §28, Asset PDF |
| 5 | Card-based battle reports | SPEC.md §17 (battles) |
| 6 | Multi-tile sprite size hierarchy | SPEC.md §7, §14, §28 |
| 7 | Inside-city buildings 3×3, Castle 4×4 | SPEC.md §10 (buildings) |
| 8 | World-map resource-tiles 2×2 | SPEC.md §7 (world map) |
| 9 | Shrines 5×5 (160×160) | SPEC.md §7 |
| 10 | Congress 6×6 (192×192) | SPEC.md §7 |

---

## 1. Visual Style — Cozy-Stardew

**Before (v1.6):** "16-bit SNES inspired pixel art, retro gaming aesthetic"

**After (v1.7):** "Cozy-Stardew pixel art style — warm pastel color palette, friendly inviting atmosphere, soft warm tones, rounded shapes, indie game aesthetic. Reference games: Stardew Valley, Cult of the Lamb, Coral Island."

**Rationale:** Cozy aesthetic is more accessible, modern, and appealing to a broader audience than dark medieval fantasy. Solo-dev production-friendly. Consistent with the player base of similar Browser-MMOs.

**Action:**
- Update SPEC.md §28.1 (Visual Style) to describe Cozy-Stardew direction
- Update CLAUDE.md "Visual Style" section
- Update Asset PDF cover page and §1 (Style Reference)
- Add reference games: Stardew Valley, Cult of the Lamb, Coral Island

---

## 2. Palette — Kenney Tiny Town Colors

**Before (v1.6):** Conquer-32 custom palette in `data/palette/conquer-32.gpl`

**After (v1.7):** Kenney Tiny Town palette extracted from Kenney's CC0 sprite pack. The user will extract this from `tilemap_packed.png` in Aseprite (Sprite → Color Mode → Indexed → Save Palette As → kenney-tiny-town.gpl).

**Action:**
- User saves `data/palette/kenney-tiny-town.gpl` (manual, in Aseprite)
- Backup current palette: rename `conquer-32.gpl` → `conquer-32-OLD.gpl`
- Set `kenney-tiny-town.gpl` as the new project standard palette
- All future Conquer-native sprites use Kenney palette
- Kenney placeholder sprites already use Kenney palette (consistent by definition)

---

## 3. One Sprite Per Monster (All Tiers Same)

**Before (v1.6):** "Each monster has 10 tier-level variants (Lv1 through Lv10), each requiring separate sprite production. ~60 monster sprites total."

**After (v1.7):** "Each monster has ONE sprite used across ALL tier levels (Lv1 through Lv10). Tier differentiation is shown via DB stats (HP, Attack, Defense), not visually. Total monster sprites: 8."

**Sprites needed:**
- `orc.png` (64×64)
- `skeleton.png` (64×64)
- `golem.png` (64×64)
- `deathkar.png` (64×64)
- `drake_green.png` (96×96)
- `drake_red.png` (96×96)
- `drake_gold.png` (96×96)
- `hydra.png` (128×128)

**Rationale:** Solo-dev production reality. Genre-standard (Lords Mobile, Rise of Kingdoms do this). Player understands tier from stats panel, not sprite differences.

**Action:**
- Update SPEC.md §14 to reflect one-sprite-per-monster
- Remove tier-level sprite variants from production list
- Update DB schema notes: monsters.tier_level controls stats, not sprite

---

## 4. No Separate Battle Sprites

**Before (v1.6):** "Each entity has both a map-sprite (32×32) AND a battle-sprite (64×64) for the battle report screen."

**After (v1.7):** "Each entity has ONE sprite. The same sprite is used on the map, in tooltips, and in battle reports. Battle reports use card-based UI with stats panels, not animated sprites."

**Rationale:** Strategy-MMO genre standard. Players want fast battle-report comprehension, not animations. Eliminates 50% of monster sprite production work.

**Action:**
- Remove "battle/" subdirectory from `assets/sprites/pixel/monsters/`
- All monster sprites live directly in `assets/sprites/pixel/monsters/`
- Update SPEC.md §17 (Battles) and §28 (Asset Specs)
- Battle-report renderer uses map sprite at 2× or 3× CSS scale

---

## 5. Card-Based Battle Reports

**Before (v1.6):** Battle report layout undefined / placeholder.

**After (v1.7):** "Battle reports use a card-based layout with character portraits and stats. NO animation, NO turn-by-turn replay. Single static screen with all relevant data."

**Layout structure:**
```
+--------------------------------------------------+
| BATTLE REPORT              VICTORY / DEFEAT       |
| Sector 4 | Plains (412, 287)                      |
+--------------------------------------------------+
| [Player Card]    vs    [Enemy Card]               |
| - Sprite                                          |
| - Name                                            |
| - HP/Damage                                       |
| - Troops sent vs survived                         |
+--------------------------------------------------+
| Casualties: 15 Swordsmen, 8 Archers               |
| Loot: 1,250 Gold, 800 Lumber, 1× Charm A          |
+--------------------------------------------------+
| [Send Reinforcements] [View Replay] [Close]       |
+--------------------------------------------------+
```

**Action:**
- Add SPEC.md §17.5 (Battle Report Layout)
- Document card-based design as final
- No "View Replay" feature in v1.0 (text-log only)

---

## 6. Multi-Tile Sprite Size Hierarchy

**Before (v1.6):** All sprites 32×32 (1×1 tile). City spans 2×2 tiles via render scaling.

**After (v1.7):** Six-tier size hierarchy for world-map and inside-city objects.

### World-Map Sprite Sizes

| Object | Tiles | Sprite | Examples |
|---|---|---|---|
| 1×1 | 1 | 32×32 | Terrain (Plains, Forest, Water, Mountain), Decorations, Treasures |
| 2×2 | 4 | 64×64 | Resource-Tiles (Wheat Field, Forest Dense, Stone Quarry, Gold Mine, Gem Lode, Ruins), Standard-Monsters (Orc, Skeleton, Golem, Deathkar) |
| 3×3 | 9 | 96×96 | Drakes (Green, Red, Gold) |
| 4×4 | 16 | 128×128 | Hydra (endboss), Player Cities (world-map representation) |
| 5×5 | 25 | 160×160 | Shrines (5 types: Forest, Mountain, Water, Desert, Volcano) |
| 6×6 | 36 | 192×192 | Congress (1 globally) |

### Inside-City Sprite Sizes

| Object | Tiles | Sprite | Examples |
|---|---|---|---|
| 1×1 | 1 | 32×32 | Watch Tower |
| 2×1 / 1×2 | 2 | 64×32 / 32×64 | Wall segments |
| 3×3 | 9 | 96×96 | Standard buildings (Farm, Lumber Camp, Quarry, Gold Mine, Storage, Treasure House, Barracks, Hospital, Trading Post, Hall of Alliance, +2 more) |
| 4×4 | 16 | 128×128 | Castle (City Center) |

### Rendering Logic

A multi-tile sprite occupies multiple grid positions. The renderer reads the entity's `size_tiles` property (e.g., `2x2`, `3x3`, `4x4`) and renders the sprite spanning those tiles. Hit-testing and collision detection uses the same grid.

**DB schema implication:** Add `size_tiles` field to entities table:
```sql
ALTER TABLE entities ADD COLUMN size_tiles VARCHAR(7) NOT NULL DEFAULT '1x1';
-- Values: '1x1', '2x2', '3x3', '4x4', '5x5', '6x6', '2x1', '1x2'
```

**Action:**
- Update SPEC.md §7 (World Map) with tile-occupation rules
- Update SPEC.md §10 (Buildings) with inside-city building sizes
- Update SPEC.md §14 (Monsters) with monster sizes
- Update SPEC.md §28 (Asset Specs) with full size hierarchy table
- Update Asset PDF with new size hierarchy
- Add note to renderer code (Sprint 3) about multi-tile rendering

---

## 7. Inside-City Buildings 3×3, Castle 4×4

**Detail to point 6.** Inside the city view (when player zooms into their own city):
- 12 standard buildings: 3×3 tiles each (96×96 px sprite)
- 1 Castle (city center): 4×4 tiles (128×128 px sprite)
- Watch Tower: 1×1 tiles (32×32 px sprite)
- Wall segments: 2×1 or 1×2 tiles (64×32 or 32×64 px)

**City-view canvas size:** Approximately 14×14 tiles to fit all buildings + paths comfortably. May expand to 16×16 if walls are added on the perimeter.

**Action:**
- Update SPEC.md §10 with inside-city layout requirements
- Add inside-city-view canvas size note
- Update Asset PDF building production list with 96×96 sizes

---

## 8. World-Map Resource-Tiles 2×2

**Detail to point 6.** Gather-points on the world-map (where players send troops to harvest resources):
- Wheat Field, Forest Dense, Stone Quarry, Gold Mine, Gem Lode, Ruins
- All 2×2 tiles (64×64 px sprite)
- 6 sprite types total
- Density: ~1 resource tile per 80 terrain tiles (down from previous 1:20 ratio because each tile now occupies 4× the space)

**Action:**
- Update SPEC.md §7 with resource-tile density rules
- Update Asset PDF resource-tile production list with 64×64 sizes
- World-spawn JSON (`data/world_spawn.json`) updated for new density

---

## 9. Shrines 5×5 (160×160)

**Detail to point 6.** Shrines are sector-level wonders held by alliances for buffs.

- 5 types: Forest, Mountain, Water, Desert, Volcano (matches biome variation per sector)
- Each shrine: 5×5 tiles (160×160 px sprite)
- Spawn count: 5 shrines per sector × 8 sectors = 40 shrines on the map
- Sprite count: 5 (one per type, reused across sectors)

**Action:**
- Update SPEC.md §7 with shrine specifications
- Update Asset PDF with 160×160 production sizes
- Add to production roadmap

---

## 10. Congress 6×6 (192×192)

**Detail to point 6.** The Congress is the SINGLE globally-located endgame wonder.

- Located at world center (sector 4 or sector 5 boundary)
- Size: 6×6 tiles (192×192 px sprite)
- Sprite count: 1
- Occupier wins server endgame (per existing spec §22)

**Action:**
- Update SPEC.md §22 (Endgame) with Congress sprite size
- Update Asset PDF
- Add to production roadmap (last sprite to produce — endgame priority)

---

## File Changes Summary

| File | Action |
|---|---|
| `docs/SPEC.md` | Update §7, §10, §14, §17, §22, §28. Bump to v1.7. Add changelog. |
| `CLAUDE.md` | Update Visual Style and Asset Production sections. Reference v1.7. |
| `docs/Conquer_Asset_Specifications.pdf` | Regenerate to v2.1 with new sizes and Kenney palette references. |
| `assets/sprites/pixel/manifest.json` | Update with new size classes, remove battle subfolder. |
| `data/palette/conquer-32.gpl` | Rename to `conquer-32-OLD.gpl`. Replace with `kenney-tiny-town.gpl` from Aseprite extraction. |
| `data/world_spawn.json` | Adjust resource-tile density for 2×2 occupation. |

---

## Production Roadmap (Asset Sprint 0)

Total: ~36 sprites across 5 size classes.

### Phase 1 — Monsters (Priority: high)
- 4 standard monsters at 64×64
- 3 drakes at 96×96
- 1 hydra at 128×128
- **Subtotal: 8 sprites**

### Phase 2 — Resource Tiles (Priority: high)
- 6 resource types at 64×64
- **Subtotal: 6 sprites**

### Phase 3 — Inside-City Buildings (Priority: medium, blocks Sprint 2)
- 12 standard buildings at 96×96
- 1 Castle at 128×128
- 1 Watch Tower at 32×32
- 2 Wall variants at 64×32
- **Subtotal: 16 sprites**

### Phase 4 — World-Map Cities (Priority: low)
- 3 city tiers at 128×128
- **Subtotal: 3 sprites**

### Phase 5 — Wonders (Priority: low)
- 5 shrine types at 160×160
- 1 congress at 192×192
- **Subtotal: 6 sprites**

**Grand total: 39 sprites.** Estimated production time at hobby pace (8h/week): 5-7 weeks for full set.

---

## Changelog Entry for SPEC.md

Append to changelog:

```markdown
## v1.7 — 2026-05-08

### Visual Style
- Switched from 16-bit SNES aesthetic to Cozy-Stardew style
- Palette: Kenney Tiny Town replaces Conquer-32 as project standard
- Reference games: Stardew Valley, Cult of the Lamb, Coral Island

### Sprite System
- Established 6-tier multi-tile sprite size hierarchy (1×1 through 6×6)
- One sprite per monster across all tier levels (no Lv1-10 variants)
- Removed separate battle-sprites — same sprite used everywhere
- Battle reports redesigned to card-based layout (no animation)

### World Map
- Resource-tiles upgraded to 2×2 (from 1×1) for visual prominence
- Standard monsters at 2×2, drakes at 3×3, hydra at 4×4
- Shrines at 5×5 (160×160 px), Congress at 6×6 (192×192 px)
- Resource-tile spawn density adjusted for new occupation rules

### Inside-City
- Standard buildings at 3×3 (96×96 px)
- Castle (city center) at 4×4 (128×128 px)
- Watch Tower at 1×1 (32×32 px)
- Wall segments at 2×1 / 1×2 (64×32 / 32×64 px)

### Production
- Total Sprint 0 sprite count: ~39 (down from ~280 in v1.6)
- One-sprite-per-monster reduces production by 60%
- Card-based battle UI eliminates ~30% of UI sprite work
```
