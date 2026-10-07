# Open Alpha — 5 October 2026

The owner confirmed opening Union of Kingdoms without invitation keys and updating all campaign posts.

`AlphaAccess::isOpen()` uses `open_alpha`, defaulting to true. Password registration skips invitation consumption in open mode, retaining account validation, CSRF, rate limits, email verification, transactions and authenticated sessions. Setting `open_alpha` to false restores the existing key requirement; the service for creating and consuming keys remains available. Social providers resume or link accounts; new players use the registration form.

The public website links directly to `https://play.unionofkingdoms.com/?mode=register`, identifies Open Alpha and explains that features, balance and alpha progress may change. The game registration has no key field in open mode. English is the default; new text is supplied in English, German, French and Luxembourgish.

The live site was updated through its authenticated hosting file manager. The previous `src`, `views` and `data` directories are saved outside `public_html` under `open-alpha-backup-20261005/`. Live code diverged from the local checkout: the live registration uses its existing automatic first-open-world selection, while the local form already has a world selector. The live registration received only the Open Alpha gate and messaging changes, preserving its existing city creation. The website views matched the local baseline. Live catalogues received additive keys, preserving newer existing messages. No database migration, player reset, world change, social post, Git commit or push was performed.

The local registration test uses a disposable database and mail sink, covering keyless account creation, invalid CSRF, duplicate email, paused-world rollback, unused legacy keys, world selection and restoring closed mode. Existing closed-mode tests also pass. The HTTP/browser checks and actual city-app fixture are documented in `MARKETING_2026-09.md`. Live GET checks confirmed keyless UI and matching public calls to action; no live account was created as a test.

Campaign output: `outputs/social-2026-09/index.html`, ten full-resolution PNGs, English Instagram/Facebook captions, image descriptions, game overview, image checksums and actual edit prompts. Both social-kit ZIP names contain this revision. Screenshots and caption/layout validation reports are in `output/open-alpha-2026-10-05/`.
