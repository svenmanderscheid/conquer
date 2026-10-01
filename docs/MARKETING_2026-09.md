# English campaign and public website — 24 September 2026

## Website refresh — 25 September 2026

The public page now includes a three-image campaign gallery (city, army and monsters), with links to the matching game sections. The original artwork stays uncropped, is loaded lazily as WebP and is labelled as promotional illustration. The city image reuses the social preview. The troop section offers touch-friendly T1–T10 buttons for all three current troop types; without JavaScript, the existing T10 portraits remain visible.

Validated the public-host preview in five desktop, portrait and landscape sizes using `tmp/check-marketing.cjs`: no overflow, missing images or browser errors. All 30 troop portrait variants loaded successfully when selected. Gallery navigation and the monster anchor were checked. Screenshots are in `artifacts/marketing-en/`. Changes are local; no deployment was performed.

## Confirmed slogan

The owner corrected the official slogan to **A new Era begins**, with that exact spelling and capitalization. It replaces the assistant-proposed slogan on the logo, campaign artwork, website and campaign kit.

## Confirmed owner direction

The owner confirmed lasting kingdom development, PvP with kingdom conquest as its objective, and alliance PvE against increasingly powerful monsters for valuable rewards. A regular seasonal reset is not the intended core model. Alpha resets remain possible. The owner subsequently requested all campaign deliverables in English, with captions and hashtags, and an additional logo.

## Delivered locally

- Ten English promotional images and a new English logo, generated with built-in Imagegen.
- `outputs/social-2026-09/index.html`: standalone gallery with copyable captions and downloads.
- `outputs/social-2026-09/GAME-VISION-EN.md` and `game-vision.html`: detailed player journey, development direction and unresolved design questions.
- `outputs/social-2026-09/CAPTIONS-EN.md`: ten captions with hashtags.
- `outputs/social-2026-09/PROMPTS.json`: final English prompt set and generator provenance.
- Public landing page in English, with current troop art, relics, rally monsters, progression and development status. Game-localisation preferences remain unchanged. The English waitlist form explicitly records English as the invitation language.

The actual game login is selected for localhost and play.unionofkingdoms.com. The public page is selected for unionofkingdoms.com. For local visual checks, `tmp/preview-marketing.php` routes an isolated local PHP server through the actual public-host entry point and refuses write routes. It is not a production route or a login bypass.

## Claim boundaries

Source of truth for troop tiers and unlocks: `data/troops.json`, `docs/TRAINING.md`, and the active `assets/js/training-panel.js`. Current illustrations come from `assets/art/characters/fantasy-troops-v2/`.

Relic art and current public descriptions were checked against `assets/js/treasure-panel.js`, `src/Game/Treasure/TreasureService.php`, `docs/TREASURES.md` and the active storybook images. Old catalogue counts and the draft relic naming table were not used as marketing claims. The universal-fragment exchange and individual-effect progression are present in the current service.

Regional enemies: `docs/REGIONAL_RALLY_BOSSES.md`. These implemented rally monsters must not be confused with the narrative bosses in `BOSS_STORY_CONCEPT.md`.

Existing PvP is distinguished from the long-term kingdom-conquest goal. No final conquest victory conditions, reward guarantees, seasonal schedule, store availability or release dates were invented. Platform direction follows `MOBILE_APP_STRATEGY.md`.

## Validation

`artifacts/marketing-en/checks.json`: actual public route, English language, five sizes (1440×1000, 390×844, 320×568, 844×390, 568×320), no horizontal overflow, missing images or JavaScript errors; expandable FAQ, required fields and JavaScript-disabled waitlist. No real form submitted.

PHP and JavaScript syntax checks passed. The existing isolated `tests/alpha_waitlist.php` suite passed, covering normalisation, chosen language, consent, duplicate requests, invalid inputs and separation from game access.

The images were visually inspected and their files verified. Web artwork was encoded as WebP without changing composition. Full PNG campaign originals are retained. No social posts were sent and no deployment, commit or push was performed.

## Closed-alpha call to action

The ten promotional footers, captions and public registration buttons now use "Register for closed alpha" in place of the development label. Web campaign assets use refreshed versioned filenames. Alpha access still uses the waiting list; access conditions and possible alpha resets remain explained. The original logo and official slogan are unchanged. Footer edit prompts are included in the campaign kit.

## Logo and monster review

Reviewed all ten campaign logos against the supplied master. Corrected the old wordmark on post 02 and missing slogan on post 03. Post 08 now follows the current Grumwald master in assets/art/monsters/storybook-v2-masters/grumwald.png. The three revised website images use v3 filenames to bypass cached artwork. The campaign kit includes the review and actual built-in Imagegen edit prompts.

## Campaign website address

The campaign address is www.unionofkingdoms.com. All ten poster footers and caption links use the www version. Website poster copies use new versioned asset paths. This does not change game login hosts or routing.
