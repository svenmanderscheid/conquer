# Conquer

> ⚠️ **"Conquer" is an internal working codename.** The final marketing name is undecided and will be chosen later (likely after EU trademark lawyer review). All references to "Conquer" should be understood as placeholders.

A browser-based 4X strategy MMO inspired by League of Kingdoms (sunsetting May 2026). Built as the spiritual successor — same deep strategic core, modernized stack, no pay-to-win, no NFTs, no blockchain bullshit.

## Status

🚧 **Pre-development** — Sprint 0 in progress (asset foundation + project skeleton).

## What this is

- **Genre**: 4X strategy MMO (eXplore, eXpand, eXploit, eXterminate)
- **Platform**: Browser (web + PWA), mobile-friendly, no install required
- **Map**: 1024×1024 tile world, top-down 2D, 8 sectors with ~625 player slots each (5,000 max per world)
- **Core loop**: Build city → train troops → research tech → kill monsters → join alliance → fight Conquest Events for shrine control
- **Progression target**: F2P player reaches Castle L30 + T5 troops in ~12 months at default world speed
- **No pay-to-win**: GEMS purchase saves time, never unlocks content

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 (OOP, PDO, no framework) |
| Database | MySQL 8 |
| Frontend | Alpine.js 3 + Vanilla JS + Canvas 2D |
| Hosting | Hostinger Shared Business |
| Real-time | HTTP polling (15s active, 60s idle) |
| Build | None — direct file editing, deploy via Git pull |

The "no framework" choice is deliberate. The owner wants to read every line of code. Laravel/Symfony are excellent tools, but they hide too much for a learning project.

## Repository layout

```
.
├── docs/                   # Specification documents
│   ├── SPEC.md            # Master specification (4,170+ lines)
│   ├── CLAUDE.md          # Pointer file for Claude Code sessions
│   └── Conquer_Asset_Specifications.pdf
├── data/                   # Single source of truth for game data
│   ├── monsters.json      # 58 monster entries with drops
│   ├── charms.json        # 24 charms × drop distribution
│   ├── world_spawn.json   # Hourly cron spawn rates per sector
│   └── buildings/         # (coming Sprint 1) — per-building configs
├── src/                    # PHP source code (web-blocked via .htaccess)
├── index.php               # Front controller (entry point for all requests)
├── assets/                 # Sprites, icons, fonts (web-accessible)
├── config/                 # Environment configs (gitignored secrets, web-blocked)
├── migrations/             # SQL migration files (web-blocked)
├── cron/                   # Background job scripts (web-blocked)
└── tests/                  # PHPUnit / custom test scripts (web-blocked)
```

## Getting started

### Prerequisites

- PHP 8.2+ with PDO MySQL extension
- MySQL 8.0+
- Web server (Apache, nginx, or Caddy)
- Git

### Local setup

```bash
git clone git@github.com:svenmanderscheid/conquer.git
cd conquer

# Copy config template (then edit with your local DB credentials)
cp config/database.example.php config/database.php

# Run migrations (Sprint 1 deliverable — not yet implemented)
php migrations/run.php

# Start built-in PHP server (flat layout — no -t needed, current dir is webroot)
php -S localhost:8080

# Visit http://localhost:8080
```

### Hostinger deployment

```bash
# On Hostinger SSH:
cd ~/domains/conquer.svenmanderscheid.lu/public_html
git pull origin main
# That's it. No build step.
```

## Development

### Working with Claude Code

This project is designed to be co-developed with Claude (Anthropic's AI assistant). The `docs/CLAUDE.md` file tells Claude what to read first. Typical workflow:

1. Open Claude Code in the repo root
2. Claude reads `docs/CLAUDE.md` automatically
3. Ask Claude to implement an issue from the GitHub backlog
4. Claude reads `docs/SPEC.md` for the relevant section
5. Claude writes the code, runs tests, commits

### Running tests

```bash
# (placeholder — testing setup is Sprint 2)
php tests/run.php
```

## Roadmap

See `docs/SPEC.md` §26 for the full 24-sprint roadmap. High-level phases:

- **Phase 1 — Sprints 1-9 (~9 months)**: Single-world MVP with full mechanics
- **Phase 2 — Sprints 10-15 (~6 months)**: Polish, illustrated city view, treasure UI, mobile UX
- **Phase 3 — Sprints 16-19 (~4 months)**: Monetization, Stripe integration, GEMS shop
- **Phase 4 — Sprints 20-24 (~5 months)**: Public launch, marketing, multi-world support

Total time-to-public-launch estimate: **~24 months** of part-time hobby development.

## Contributing

This is a solo hobby project with Claude as a coding collaborator. Not currently accepting external contributions, but feel free to open issues with bug reports or feature suggestions if you stumble across the repo.

## License

Source code: **All rights reserved** until further notice. Final license decision (likely AGPL or proprietary) will be made before public launch.

Game data files (`data/*.json`) are derivative of original League of Kingdoms data structures and used here for reference / inspiration. Original LoK data IDs are preserved for traceability. All gameplay rules, balance values, and design decisions are original.

## Author

Sven Manderscheid (Ekki) — Luxembourg  
Day job: Teacher / pedagogical advisor at CNFPC Esch-sur-Alzette  
Hobby: Building things that probably shouldn't be solo projects but I'm doing it anyway

---

**Built with Claude as drafting collaborator.**
