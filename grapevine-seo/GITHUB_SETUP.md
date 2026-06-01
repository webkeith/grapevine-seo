# 🔧 GitHub Update Checker Setup — Grapevine SEO

This plugin self-updates directly from your GitHub repository using the
[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library (bundled in `lib/`).

---

## One-Time Setup (5 minutes)

### Step 1 — Create your GitHub repo

1. Go to [github.com/new](https://github.com/new)
2. Name it **`grapevine-seo`** (must match the plugin slug)
3. Set visibility: **Public** (recommended) or Private (see Step 4)
4. Push the plugin folder:

```bash
cd /path/to/grapevine-seo
git init
git add .
git commit -m "Initial release v2.0.0"
git remote add origin https://github.com/webkeith/grapevine-seo.git
git push -u origin main
```

---

### Step 2 — Edit the two lines in `grapevine-seo.php`

Open `grapevine-seo.php` and replace:

```php
define( 'GVSEO_GITHUB_REPO',  'https://github.com/webkeith/grapevine-seo' );
define( 'GVSEO_GITHUB_TOKEN', '' );
```

With your real username:

```php
define( 'GVSEO_GITHUB_REPO',  'https://github.com/johnsmith/grapevine-seo' );
define( 'GVSEO_GITHUB_TOKEN', '' );   // leave empty for public repos
```

---

### Step 3 — Tag a release to publish an update

Whenever you make changes and want WordPress sites to update:

1. **Bump the version** in `grapevine-seo.php`:
   ```
   * Version: 2.0.1
   ```
   And the constant:
   ```php
   define( 'GVSEO_VERSION', '2.0.1' );
   ```

2. **Commit and tag**:
   ```bash
   git add .
   git commit -m "Bump to 2.0.1 — fix X, improve Y"
   git tag v2.0.1
   git push origin main --tags
   ```

3. **GitHub Actions builds and publishes** the release automatically
   (the workflow in `.github/workflows/release.yml` runs on every `v*` tag push).

4. Within 12 hours, WordPress sites with the plugin installed will show
   **"Update available"** in Plugins → Installed Plugins.

---

### Step 4 — Private repos (optional)

If your repo is **private**, generate a Personal Access Token:

1. GitHub → Settings → Developer Settings → **Personal Access Tokens → Tokens (classic)**
2. Click **Generate new token (classic)**
3. Scopes: check **`repo`** (full control)
4. Copy the token

Add it to `wp-config.php` on your WordPress site (keeps it out of the plugin code):

```php
// wp-config.php
define( 'GVSEO_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' );
```

Then in `grapevine-seo.php` update the constant to read from config:
```php
define( 'GVSEO_GITHUB_TOKEN', defined('GVSEO_GITHUB_TOKEN') ? GVSEO_GITHUB_TOKEN : '' );
```

---

## How It Works

```
You push tag v2.0.1
       ↓
GitHub Actions builds grapevine-seo.zip
       ↓
GitHub Release published with ZIP attached
       ↓
Plugin Update Checker polls https://api.github.com/repos/…/releases/latest
       ↓
WordPress shows "Update available: 2.0.1"
       ↓
User clicks Update → WP downloads grapevine-seo.zip from GitHub → installs
```

---

## Version Bump Checklist

- [ ] Update `* Version: X.Y.Z` in the plugin header
- [ ] Update `define( 'GVSEO_VERSION', 'X.Y.Z' )` constant
- [ ] `git commit -m "Release X.Y.Z"`
- [ ] `git tag vX.Y.Z`
- [ ] `git push origin main --tags`
- [ ] Check GitHub → Actions tab to confirm the release workflow ran
- [ ] Check Plugins page on a test site after ~12 hours (or trigger manually via wp-admin)

---

## Manual Update Check (for testing)

To force WordPress to check for updates immediately, visit:

```
/wp-admin/update-core.php
```

Or use WP-CLI:
```bash
wp plugin update grapevine-seo
```
