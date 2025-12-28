# Releasing Ava

This guide is for maintainers creating new Ava releases.

## Version Format

Ava uses [CalVer](https://calver.org/) with the format **`YY.0M.MICRO`**:

```
YY.0M.MICRO
│  │  └── Release number within the month (1, 2, 3...)
│  └───── Zero-padded month (01-12)
└──────── Two-digit year (25, 26...)
```

### Examples

- `25.12.1` — First release of December 2025
- `25.12.2` — Second release of December 2025
- `26.01.1` — First release of January 2026

### Determining the Next Version

1. Check the current month/year
2. Look at the latest release tag
3. If same month: increment MICRO
4. If new month: reset MICRO to 1

```bash
# Current: 25.12.3, releasing in December 2025
# Next: 25.12.4

# Current: 25.12.3, releasing in January 2026  
# Next: 26.01.1
```

## Release Checklist

### 1. Update Version Constant

Edit `bootstrap.php` and update `AVA_VERSION`:

```php
define('AVA_VERSION', '25.12.1');
```

### 2. Update Bundled Plugins List (if needed)

If adding new bundled plugins, update `core/Updater.php`:

```php
private array $bundledPlugins = [
    'sitemap',
    'feed', 
    'redirects',
    'new-plugin',  // Add new bundled plugins here
];
```

### 3. Test Locally

```bash
php bin/ava lint           # Validate content
php bin/ava rebuild        # Rebuild cache
php bin/ava status         # Check everything works
```

### 4. Commit and Tag

```bash
git add -A
git commit -m "🔖 Release 25.12.1"
git tag -a v25.12.1 -m "Release 25.12.1"
git push origin main --tags
```

### 5. Create GitHub Release

1. Go to **Releases** → **Draft a new release**
2. Choose the tag you just pushed (e.g., `v25.12.1`)
3. Set release title to the version (e.g., `25.12.1`)
4. Write the changelog in the description
5. Click **Publish release**

## Changelog Format

Use this format for release notes:

```markdown
## What's New

- ✨ Feature: Brief description
- ✨ Feature: Another feature

## Improvements

- 🔧 Improvement description
- 🔧 Another improvement

## Bug Fixes

- 🐛 Fixed issue description

## Breaking Changes

- ⚠️ Description of breaking change and migration steps

## New Bundled Plugins

- `plugin-name` — Brief description (not activated by default)
```

### Emoji Reference

| Emoji | Use for |
|-------|---------|
| ✨ | New features |
| 🔧 | Improvements/enhancements |
| 🐛 | Bug fixes |
| ⚠️ | Breaking changes |
| 📚 | Documentation |
| 🚀 | Performance |
| 🔒 | Security |

## What's Included in Releases

GitHub's zipball automatically includes everything in the repo. The updater only applies specific directories (see `core/Updater.php`).

**Updated by updater:**
- `core/`
- `docs/`
- `bin/`
- `themes/default/`
- `plugins/{bundled}/`
- `bootstrap.php`
- `composer.json`
- `public/index.php`
- `public/assets/admin.css`

**Never included/updated:**
- `content/` (should be in `.gitignore` anyway for demo content)
- `app/config/users.php` (gitignored)
- `storage/` (gitignored except structure)
- `vendor/` (gitignored)
- `.env` (gitignored)

## Testing the Update Flow

Before releasing, test the update mechanism:

1. Create a test installation
2. Set it to an older version in `bootstrap.php`
3. Create a test release on GitHub
4. Run `php bin/ava update:check`
5. Run `php bin/ava update:apply`
6. Verify files were updated correctly
7. Verify user files were preserved

## Hotfix Releases

For urgent fixes within the same month:

1. Just increment MICRO: `25.12.1` → `25.12.2`
2. Follow the normal release process
3. Note in changelog that it's a hotfix

## Pre-release / Beta

Not officially supported, but you could use:
- `25.12.1-beta.1`
- `25.12.1-rc.1`

The version comparison should still work, but these would be considered "less than" the final release.

## Repository Settings

Ensure the GitHub repository has:

- **Releases** enabled
- **Public** visibility (for API access without auth)
- Tags following the `v{VERSION}` format

## Troubleshooting

### Users can't fetch updates

- Ensure releases are published (not draft)
- Ensure repository is public
- Check GitHub API status

### Version comparison issues

The updater uses PHP's `version_compare()`. CalVer format works correctly:
- `25.12.1` < `25.12.2` ✓
- `25.12.9` < `25.12.10` ✓
- `25.12.99` < `26.01.1` ✓
