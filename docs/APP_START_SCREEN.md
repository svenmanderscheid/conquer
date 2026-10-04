# Union of Kingdoms app start

The shared sign-in and game pages include `views/partials/app-start.php` before their interface. The start screen reuses the approved village WebP variants and game logo; its responsive styles live in the final shared `village-theme.css`.

`assets/js/app-start.js` starts at 0%, advances with document and non-lazy image loading, and reaches 100% only after the page load and, in the game, the first successful state refresh and rendering. Percentages represent startup milestones, not transferred bytes. The completed screen fades away. A failed initial state request or a 30-second wait offers an explicit reload; background polling can still complete startup. Reduced motion and safe screen edges are respected. English, German, French and Luxembourgish text uses the shared language catalogs.

Local verification on 4 October 2026: sign-in start screen, card bounds and successful dismissal at 1280×800, 390×844 and 568×320; no JavaScript page errors. Captures: `artifacts/app-start/`. PHP/JavaScript syntax and all four JSON catalogs passed. Authenticated startup and a real Android device remain to be checked. The Android prototype loads the remote game URL and receives this shared screen after server deployment; its native launch screen is unchanged.
