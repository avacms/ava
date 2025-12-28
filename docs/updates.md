# Updates

Ava includes a built-in update system that checks for and applies updates from the official GitHub repository.

## Checking for Updates

### Via CLI (Recommended)

```bash
php bin/ava update:check
```

This checks for available updates and shows the changelog if one is available. Results are cached for 1 hour.

```bash
php bin/ava update:check --force   # Bypass cache
```

### Via Admin Dashboard

The admin dashboard shows your current version in the sidebar. When an update is available, a notification banner appears at the top of the dashboard with the new version number.

## Applying Updates

```bash
php bin/ava update:apply
```

This will:
1. Download the latest release from GitHub
2. Show you what will be updated
3. Ask for confirmation
4. Apply the update
5. Rebuild the cache

Use `-y` to skip the confirmation prompt:

```bash
php bin/ava update:apply -y
```

## What Gets Updated

The update process **replaces** these directories/files:

| Path | Description |
|------|-------------|
| `core/` | Core CMS PHP code |
| `docs/` | Documentation |
| `bin/` | CLI scripts |
| `themes/default/` | Default theme only |
| `plugins/sitemap/` | Bundled sitemap plugin |
| `plugins/feed/` | Bundled RSS feed plugin |
| `plugins/redirects/` | Bundled redirects plugin |
| `bootstrap.php` | Bootstrap and version |
| `composer.json` | Dependencies |
| `public/index.php` | Front controller |
| `public/assets/admin.css` | Admin stylesheet |

## What's Preserved

The update process **never touches** these:

| Path | Description |
|------|-------------|
| `content/` | Your content files |
| `app/` | Your configuration |
| `storage/` | Cache, logs, data files |
| `vendor/` | Composer dependencies |
| `themes/*/` | Custom themes (anything except `default/`) |
| `plugins/*/` | Custom plugins (anything not bundled) |
| `.env` | Environment file |
| `.git/` | Git repository |

## New Bundled Plugins

When an update includes new bundled plugins:

1. They are copied to `plugins/`
2. They are **not activated** automatically
3. A message shows which new plugins are available
4. To activate, add them to your `plugins` array in `app/config/ava.php`

## Versioning

Ava uses [CalVer](https://calver.org/) with the format `YY.0M.MICRO`:

- **YY**: Two-digit year
- **0M**: Zero-padded month  
- **MICRO**: Release number within that month

### Examples

| Version | Meaning |
|---------|---------|
| `25.12.1` | First release of December 2025 |
| `25.12.2` | Second release of December 2025 |
| `26.01.1` | First release of January 2026 |
| `26.01.15` | Fifteenth release of January 2026 |

This scheme:
- Tells you roughly when a release was made
- Avoids semantic versioning debates
- Always increases (newer = higher)
- Allows unlimited releases per month

## Manual Updates

If you prefer not to use the built-in updater:

1. Download the latest release from GitHub
2. Extract and copy the files listed in "What Gets Updated"
3. Run `php bin/ava rebuild` to rebuild the cache
4. Run `composer install` if `composer.json` changed

## Troubleshooting

### "Could not fetch release info from GitHub"

- Check your internet connection
- GitHub API may be rate-limited (60 requests/hour for unauthenticated)
- Try again in a few minutes

### Update fails mid-way

Your content and configuration are safe. The update only modifies core files. You can:

1. Try running the update again
2. Manually download and extract the release
3. Check file permissions on the `core/` directory

### After updating, site shows errors

1. Run `composer install` to update dependencies
2. Run `php bin/ava rebuild` to clear caches
3. Check the changelog for breaking changes
