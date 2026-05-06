# Hostinger Setup Guide — Conquer

Deployment configuration for `conquer.svenmanderscheid.lu`.

## Current Hostinger setup (as configured)

- **Subdomain:** `conquer.svenmanderscheid.lu`
- **Repo location:** `/home/u171686647/domains/svenmanderscheid.lu/public_html/conquer`
- **GitHub repo:** `git@github.com:svenmanderscheid/conquer.git`
- **Auto-deploy:** Hostinger pulls from GitHub when you push (via Webhook)

## CRITICAL: Document Root Configuration

This is the most important step. **Your repo has the structure:**

```
conquer/                  ← Hostinger pulls everything here
├── public/               ← THIS is what should be served on the web
│   ├── index.php
│   ├── .htaccess
│   └── assets/
├── src/                  ← MUST NOT be web-accessible
├── data/                 ← MUST NOT be web-accessible
├── config/               ← MUST NOT be web-accessible (contains secrets)
└── ...
```

You have **two options** to handle this. **Option A is strongly preferred.**

---

### Option A — Change document root to `/conquer/public/` (PREFERRED)

This is the cleanest, most secure setup. The web server only sees `/public/` and the rest of the repo is invisible to the internet.

**Steps:**

1. Log into Hostinger Control Panel
2. Go to **Domains → Subdomains**
3. Find `conquer.svenmanderscheid.lu`
4. Click **Manage** or **Edit**
5. Change **Document Root** from:
   ```
   public_html/conquer
   ```
   to:
   ```
   public_html/conquer/public
   ```
6. Save and wait 1-2 minutes for the change to take effect
7. **Delete the root `.htaccess`** from your repo (it's only needed for Option B):
   ```bash
   git rm .htaccess
   git commit -m "Remove root .htaccess — using Option A document root"
   git push
   ```

**Verify:** Visit `https://conquer.svenmanderscheid.lu/` — you should see the "Conquer — Coming Soon" page. Then verify security:

- `https://conquer.svenmanderscheid.lu/data/charms.json` → should give **403 Forbidden** or **404 Not Found**
- `https://conquer.svenmanderscheid.lu/src/Autoloader.php` → should give **403 Forbidden** or **404 Not Found**

---

### Option B — Keep document root at `/conquer/`, use root .htaccess fallback

If you can't or don't want to change the document root, the repo includes a root `.htaccess` file that rewrites all requests into `/public/`.

**This works**, but is less secure because:
- A misconfigured `.htaccess` could leak files
- Apache must process the rewrite rule for every request (tiny perf cost)

**Steps:**

1. Verify the root `.htaccess` exists in the repo (it should, from the initial commit)
2. Verify per-folder `.htaccess` files exist in `src/`, `data/`, `config/`, `migrations/`, `cron/`, `tests/`, `docs/` — each containing `Require all denied`
3. Visit `https://conquer.svenmanderscheid.lu/` — you should see "Conquer — Coming Soon"
4. **Test security manually:**
   - `https://conquer.svenmanderscheid.lu/data/charms.json` → must be 403
   - `https://conquer.svenmanderscheid.lu/src/README.md` → must be 403
   - `https://conquer.svenmanderscheid.lu/config/database.example.php` → must be 403
   - `https://conquer.svenmanderscheid.lu/.git/config` → must be 403
   - `https://conquer.svenmanderscheid.lu/SPEC.md` → must be 403

**If any of these return file content, the security is broken — switch to Option A.**

---

## Database Setup

Before the app can do anything beyond the placeholder page, you need a MySQL database.

### Steps in Hostinger panel

1. Go to **Databases → MySQL Databases**
2. Click **Create New Database**
3. Database name: `u171686647_conquer` (Hostinger prepends your username)
4. Username: `u171686647_conquer_app`
5. Password: generate a strong random one (save it!)
6. Click **Create**

### Configure the app

SSH into your Hostinger account:

```bash
ssh u171686647@your-hostinger-host

cd domains/svenmanderscheid.lu/public_html/conquer

# Copy config templates
cp config/database.example.php config/database.php
cp config/app.example.php config/app.php

# Edit database config
nano config/database.php
```

In `config/database.php`, set:

```php
'host'     => 'localhost',
'database' => 'u171686647_conquer',
'username' => 'u171686647_conquer_app',
'password' => 'YOUR_GENERATED_PASSWORD',
```

In `config/app.php`, set:

```php
'env' => 'production',
'debug' => false,
'base_url' => 'https://conquer.svenmanderscheid.lu',
```

These files are gitignored, so they stay on the server only. Safe.

---

## SSL Certificate (HTTPS)

1. In Hostinger panel: **Security → SSL**
2. Find `conquer.svenmanderscheid.lu`
3. Install free **Let's Encrypt** certificate (usually one-click)
4. Wait 5-10 min for activation
5. Once verified working, **enable HTTPS forced redirect** in your `.htaccess`:

   In either `/conquer/.htaccess` (Option B) or `/conquer/public/.htaccess` (Option A), uncomment:
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

   Commit and push so it deploys.

---

## GitHub Auto-Deploy Workflow

Since the subdomain is connected to GitHub:

1. You make changes locally
2. Push to `main` (or whatever branch Hostinger watches)
3. Hostinger's webhook fires
4. Hostinger pulls the new code into `/conquer/`
5. Site is updated — usually within 30 seconds

**Recommended branching:**

- `main` ← what Hostinger pulls — production-ready only
- `dev` ← integration branch for active work
- `feature/*` ← per-feature branches

Workflow:
```bash
# Daily work
git checkout dev
git checkout -b feature/auth-system
# ... code, commit ...
git push origin feature/auth-system
# Open PR to dev on GitHub, merge

# Release to production
git checkout main
git merge dev
git push origin main
# Hostinger auto-deploys
```

---

## Troubleshooting

### "Site shows the file structure / directory listing"

The `Options -Indexes` directive in `.htaccess` should prevent this. If you see it:
- Check that your `.htaccess` actually got pushed
- On Hostinger, check that mod_rewrite is enabled (it should be by default)

### "Site shows raw PHP code instead of executing"

PHP is not configured for that domain. In Hostinger panel:
- **Hosting → Manage → Advanced → PHP Configuration**
- Verify PHP version is 8.2+
- Verify it's set as the handler for the domain

### "Can't connect to MySQL"

- Check `config/database.php` host — on Hostinger it's usually `localhost`, sometimes `127.0.0.1`
- Verify the username includes the `u171686647_` prefix
- Check the password doesn't have special chars that need escaping

### "Webhook didn't fire after push"

- In Hostinger panel: **Advanced → Git → Manage**
- Click **Auto-Deploy → View Webhook URL**
- Go to GitHub repo → Settings → Webhooks
- Verify the webhook URL is correctly set
- Check **Recent Deliveries** in GitHub — failed deliveries show why

### "Changes appear in repo but not on site"

- Hostinger may have hit a deploy error. Check:
  - **Advanced → Git → Manage Repository → Deploy Logs**
- Sometimes a manual **Deploy Now** button click is needed

---

## Performance and Caching Notes

- The repo includes asset caching headers in `public/.htaccess` (30 days for images/fonts)
- For game data files (`/data/*.json`), caching is set to 0 — these are loaded server-side via PHP, not directly served
- Once you're at meaningful traffic levels, consider Cloudflare in front of Hostinger for free CDN + DDoS protection

---

## Summary Checklist

Before considering deployment "done":

- [ ] Subdomain `conquer.svenmanderscheid.lu` resolves
- [ ] Document root configured (Option A preferred — `public/` subfolder)
- [ ] GitHub auto-deploy webhook is firing
- [ ] First push results in updated site
- [ ] `https://conquer.svenmanderscheid.lu/` shows "Coming Soon" placeholder
- [ ] Security check: `/data/`, `/src/`, `/config/`, `/.git/` all return 403
- [ ] MySQL database created and configured in `config/database.php`
- [ ] SSL certificate installed and active
- [ ] HTTPS redirect enabled (after SSL verified)
- [ ] PHP 8.2+ confirmed via `<?= PHP_VERSION ?>` on placeholder page
