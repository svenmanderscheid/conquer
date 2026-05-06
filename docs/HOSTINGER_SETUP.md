# Hostinger Setup Guide — Conquer (Flat Layout)

Deployment configuration for `conquer.svenmanderscheid.lu`.

## Current setup

- **Subdomain:** `conquer.svenmanderscheid.lu`
- **Repo location:** `/home/u171686647/domains/svenmanderscheid.lu/public_html/conquer`
- **GitHub repo:** `git@github.com:svenmanderscheid/conquer.git`
- **Branch tracked:** `main`
- **Auto-deploy:** Yes (via Hostinger webhook)

## Repo layout — flat

This repository uses a **flat layout** where the entire repo is the document root. This is required because Hostinger Shared Hosting on this plan does not allow setting the document root to a subdirectory.

```
public_html/conquer/                    ← Both Hostinger install path AND web document root
├── index.php                           ← Entry point (web-accessible)
├── .htaccess                           ← Security + URL rewriting
├── assets/                             ← Sprites, icons, fonts (web-accessible)
├── src/                                ← BLOCKED via .htaccess
│   └── .htaccess  (Require all denied)
├── data/                               ← BLOCKED via .htaccess
│   └── .htaccess  (Require all denied)
├── config/                             ← BLOCKED via .htaccess (contains secrets!)
│   └── .htaccess  (Require all denied)
├── migrations/                         ← BLOCKED via .htaccess
├── cron/                               ← BLOCKED via .htaccess
├── tests/                              ← BLOCKED via .htaccess
└── docs/                               ← BLOCKED via .htaccess
```

**How security works in this setup:**

1. The root `.htaccess` blocks any URL matching `^/(src|config|migrations|cron|tests|docs|data|...)/.*$` with a 403
2. Each protected folder has its own `.htaccess` with `Require all denied` (defense in depth — even if root htaccess fails)
3. The root `.htaccess` also blocks all dotfiles (`.git`, `.env`) and dangerous extensions (`.md`, `.sql`, etc.)

## Deployment workflow

### Initial push

After cloning the repo template locally:

```bash
mkdir -p ~/projects/conquer
cd ~/projects/conquer
tar -xzf ~/Downloads/conquer-repo.tar.gz

# Verify commits
git log --oneline

# Optionally re-author commits
git commit --amend --author="Sven Manderscheid <sven@svenmanderscheid.lu>" --no-edit

# Push to GitHub
git remote add origin git@github.com:svenmanderscheid/conquer.git
git push -u origin main
```

Hostinger's webhook fires automatically on push. Within ~30 seconds, the new code is live at `conquer.svenmanderscheid.lu`.

### Daily workflow

```bash
git checkout dev
git checkout -b feature/auth-system

# ... write code locally, test in XAMPP ...

git push origin feature/auth-system
# PR feature/auth-system → dev on GitHub, merge after review

# When dev is stable, release to production:
git checkout main
git merge dev --no-ff
git push origin main
# Hostinger auto-deploys to conquer.svenmanderscheid.lu
```

## After first deploy — verify

Visit `https://conquer.svenmanderscheid.lu/`. You should see:

- Big "Conquer" gradient title
- "working codename — final name TBD" subtitle
- "Sprint 0 — Foundation in progress" status
- PHP version + server time at bottom

### Critical security check

Test these URLs — **all must return 403 Forbidden**:

| URL | Expected |
|---|---|
| `https://conquer.svenmanderscheid.lu/data/charms.json` | 403 |
| `https://conquer.svenmanderscheid.lu/data/monsters.json` | 403 |
| `https://conquer.svenmanderscheid.lu/src/` | 403 |
| `https://conquer.svenmanderscheid.lu/src/README.md` | 403 |
| `https://conquer.svenmanderscheid.lu/config/` | 403 |
| `https://conquer.svenmanderscheid.lu/config/database.example.php` | 403 |
| `https://conquer.svenmanderscheid.lu/.git/config` | 403 |
| `https://conquer.svenmanderscheid.lu/.gitignore` | 403 |
| `https://conquer.svenmanderscheid.lu/SPEC.md` | 403 (if it exists at root) |
| `https://conquer.svenmanderscheid.lu/README.md` | 403 |
| `https://conquer.svenmanderscheid.lu/docs/SPEC.md` | 403 |

**If ANY of these returns content, security is broken.** Check:
1. `.htaccess` exists at the root and is correctly deployed
2. The per-folder `.htaccess` files exist in `src/`, `data/`, `config/`, etc.
3. Apache `mod_rewrite` and `AllowOverride All` are enabled (Hostinger defaults — should be fine)

## Database setup (one-time)

In Hostinger panel:

1. **Databases → MySQL Databases → Create New**
2. Database name: `u171686647_conquer` (Hostinger prepends your account prefix)
3. User: `u171686647_conquer_app`
4. Password: generate strong random — **save it!**

SSH to Hostinger:

```bash
ssh u171686647@your-hostinger-ssh-host
cd domains/svenmanderscheid.lu/public_html/conquer

# Copy config templates (these are gitignored, so they stay only on the server)
cp config/database.example.php config/database.php
cp config/app.example.php config/app.php

# Edit production database credentials
nano config/database.php
```

Set:
```php
'host'     => 'localhost',
'database' => 'u171686647_conquer',
'username' => 'u171686647_conquer_app',
'password' => 'YOUR_GENERATED_PASSWORD',
```

Edit app config:
```bash
nano config/app.php
```

Set:
```php
'env' => 'production',
'debug' => false,
'base_url' => 'https://conquer.svenmanderscheid.lu',
```

These config files are in `.gitignore` so they live only on the server — Hostinger pulls won't overwrite them, GitHub never sees the credentials.

## SSL Certificate (HTTPS)

1. Hostinger panel → **Security → SSL**
2. Find `conquer.svenmanderscheid.lu`
3. Install free **Let's Encrypt** certificate (one-click)
4. Wait 5-10 min for activation
5. Verify HTTPS works in browser
6. Once verified, **enable HTTPS forced redirect** — uncomment in `.htaccess`:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
   Commit and push so it deploys.

## Branching strategy

```
main          ← production (Hostinger watches this branch) — only merge tested code
  ↑
dev           ← integration branch
  ↑
feature/*     ← per-feature branches
```

Daily:
```bash
git checkout dev
git pull
git checkout -b feature/something

# work locally with XAMPP, test, iterate

git push -u origin feature/something
# PR on GitHub: feature/something → dev → merge

# Periodic releases to production:
git checkout main
git merge dev --no-ff
git push origin main
# Hostinger auto-deploys
```

## Troubleshooting

### "Site shows directory listing"

`Options -Indexes` in `.htaccess` should prevent this. If you see it:
- Verify `.htaccess` got pushed (`https://conquer.svenmanderscheid.lu/.htaccess` should return 403, not the file content)
- In Hostinger panel: ensure mod_rewrite is enabled (default on)

### "Site shows raw PHP code"

PHP isn't configured for the domain:
- Hostinger panel → **Hosting → Manage → Advanced → PHP Configuration**
- Verify PHP version is 8.2+
- Verify it's set as the handler

### "Webhook didn't fire after git push"

- Hostinger panel → **Advanced → Git → Manage Repository**
- Click **Auto-Deploy → View Webhook URL**
- GitHub repo → **Settings → Webhooks**
- Verify webhook URL matches
- Click **Recent Deliveries** to see failures and reasons

### "Push works but site doesn't update"

Hostinger had a deploy error. Check:
- **Advanced → Git → Manage Repository → Deploy Logs**
- Sometimes a manual **Deploy Now** is needed

### "Can't connect to MySQL from PHP"

- Check `config/database.php` host — `localhost` usually works on Hostinger
- Username must include `u171686647_` prefix
- If password has special chars (`@`, `&`, etc.), test by escaping them

### "/data/charms.json returns 200 instead of 403"

Critical security failure. Immediate steps:
1. SSH and verify `data/.htaccess` exists with `Require all denied`
2. Test: `curl -I https://conquer.svenmanderscheid.lu/data/charms.json`
3. If still 200, the per-folder htaccess isn't being processed
4. Check that `AllowOverride All` is set (Hostinger default — should work)
5. Worst case: rename `data/charms.json` to `data/charms.json.dat` so it's not served as JSON

## Performance and caching

- Asset caching headers in root `.htaccess` (30 days for images/fonts)
- For game data files (`/data/*.json`), caching is set to 0 — these are loaded server-side via PHP, not directly served
- At meaningful traffic, consider Cloudflare in front of Hostinger for free CDN + DDoS protection

## Pre-Sprint-1 checklist

- [ ] Repo pushed to GitHub
- [ ] First Hostinger deploy worked — placeholder page shows on subdomain
- [ ] All 11 security URLs return 403 (run the table above)
- [ ] MySQL database created
- [ ] `config/database.php` configured on server
- [ ] `config/app.php` configured (env=production, debug=false)
- [ ] SSL installed
- [ ] HTTPS forced redirect enabled
- [ ] PHP 8.2+ confirmed via placeholder page footer

Once all boxes are checked, ready for Sprint 1.
