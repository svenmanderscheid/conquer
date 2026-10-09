# Union of Kingdoms link tracker

Open **Administration → Overview → Link tracker** (`/admin/links`). This is a global overview across worlds. Administrators create campaign links, copy their public URL, change the saved destination and pause or reactivate them. Moderators can read statistics and export CSV; campaign changes require a superadmin. Existing URL names remain fixed after creation.

## What is measured

- Campaign links use `/go/SLUG`: each eligible GET increments the link counter and returns a temporary redirect to its saved destination. HEAD requests redirect without counting. Paused, missing and built-in website slugs return 404. Request parameters cannot replace a destination. The route respects deployment subfolders and creates no session.
- Share URLs on the website/game hosts use the canonical public website. Local previews retain their current hostname, port and subfolder instead of inheriting an old production `base_url`.
- The public landing page counts 13 authored website targets: login, hero/browser/access/guide registration, closed-alpha waitlist, Discord, contact, privacy, account deletion, and the city/world/army gallery. Clicks are recorded from trusted normal, keyboard, modified and middle-button events. Anchor navigation and existing link destinations are preserved. A script-generated click is excluded. No game buttons or private gameplay are measured.
- Figures show clicks and visits, **not unique people** or registrations. Repeated visits count again. Website counting requires JavaScript and successful delivery; blockers, connectivity loss and undetectable bots can affect the figures.
- Today and the last 7/30/90 UTC calendar days include the current day. Lifetime counts and the latest counted click remain visible on each link. Per-link statistics, source categories, device categories, a daily chart and a CSV export use the selected period. A missing referrer appears as Direct / unknown.

`utm_source` on the landing page can select one of the known source categories. Otherwise the source is classified from the referring domain. Only the category is sent; the browser never sends the page URL, referrer URL, link destination or arbitrary parameter values to the counter endpoint. Sources are direct/unknown, Instagram, Discord, Facebook, Google, YouTube, TikTok, X/Twitter and other. Devices are desktop, phone, tablet and other/unknown.

## Storage and safeguards

Migration `0140_link_tracker.sql` adds `link_tracker_links` and `link_tracker_daily` and seeds the website targets. The statistics table has one aggregate per link, day, device and source, with a count and latest click time. It contains no IP addresses, visitor IDs, user agents, player IDs or complete request URLs. There are no analytics cookies or analytics browser storage, and no external analytics provider.

Known bot/preview user agents, prefetch/prerender requests, Do Not Track and Global Privacy Control are excluded when detected. Existing abuse protection uses a hashed daily request bucket, with a capacity of 120 and refill over 60 seconds. These temporary buckets use the existing rate-limit expiry and maintenance. The public click endpoint is session-free, requires same-origin POST, caps its body at 1024 bytes and accepts only enabled built-in website targets. It has no game-state mutation. Saved HTTPS/public-path destinations are validated; sensitive routes, unsafe protocols, credentials and tracking loops are rejected. Counter failures do not block an otherwise valid campaign redirect.

Campaign edits use the existing authenticated CSRF, superadmin, operation receipt and audit transaction. Replaying a successful create does not create another link. Revision checking prevents stale edits from overwriting newer changes. Failed input remains a bounded draft. Campaign names and destinations are escaped and excluded from automatic text translation. New authored texts use `links.*` in English, German and French, with English as default/fallback. The public landing-page footer explains these counters independently of any separate privacy-page release. Both published gallery layouts map to the same city/world/army counters.

## Installation and checks

Run `php tools/migrate-link-tracker.php --apply` to install only this additive migration. `link_tracking_enabled` defaults to true and can be set to false in the server configuration; this disables counting while existing campaign redirects continue. Install the migration before publishing the new views and handlers.

`php tests/link_tracker.php --browser` uses a disposable database and synthetic admins. It covers destination validation, rollback, receipts, stale updates, source/device classification, opt-out and bot suppression, calendar/lifetime filters, actual front-controller redirects, subfolders, method/origin/body checks, anonymous/moderator protection, real public clicks across navigation, clipboard/fallback, CSV, localization and seven desktop/portrait/landscape language combinations. Browser evidence is under `output/playwright/link-tracker/`. `tests/mobile_comfort_app.cjs` covers the actual main app in five sizes as an integration check. These are browser checks; native-device verification and live publication are separate.
