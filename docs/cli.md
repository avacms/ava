# CLI Reference

The Ava CLI helps you manage your site from the command line. It's your primary tool for cache management, content validation, and scaffolding.

## Usage

```bash
./ava <command> [arguments]

# Or explicitly with PHP
php bin/ava <command> [arguments]
```

## Commands Overview

| Command | Description |
|---------|-------------|
| `status` | Show site status, cache info, content counts |
| `rebuild` | Rebuild all cache files |
| `lint` | Validate all content files |
| `make <type> "Title"` | Create new content |
| `prefix <add\|remove> [type]` | Toggle date prefix on filenames |
| `user:add` | Create admin user |
| `user:password` | Update user password |
| `user:remove` | Remove a user |
| `user:list` | List all users |
| `update:check` | Check for available updates |
| `update:apply` | Download and apply updates |

---

## status

Show a summary of your site's current state.

```bash
./ava status
```

Example output:

```
=== Ava CMS Status ===

Site: My Site
URL:  https://example.com

Cache:
  Status: ✓ Fresh
  Mode:   auto
  Built:  2024-12-28 10:30:15

Content:
  pages: 3 total (3 published, 0 drafts)
  posts: 5 total (4 published, 1 drafts)

Taxonomies:
  categories: 4 terms
  tags: 8 terms
```

Use this to verify your site is configured correctly and cache is up to date.

---

## rebuild

Force a complete cache rebuild.

```bash
./ava rebuild
```

This regenerates all cached files:

| File | Contents |
|------|----------|
| `storage/cache/content_index.php` | All content metadata |
| `storage/cache/tax_index.php` | Taxonomy terms and assignments |
| `storage/cache/routes.php` | Compiled route map |
| `storage/cache/fingerprint.json` | Content file hashes |

Use after:
- Deploying content changes (if cache mode is `never`)
- Modifying content type or taxonomy configuration
- Troubleshooting stale content issues

---

## lint

Validate all content files for errors.

```bash
./ava lint
```

Checks for:

| Check | Description |
|-------|-------------|
| YAML syntax | Valid frontmatter parsing |
| Required fields | `title`, `slug`, `status` present |
| Status values | Must be `draft`, `published`, or `private` |
| Slug format | Lowercase, alphanumeric, hyphens only |
| Duplicate slugs | Within the same content type |
| Duplicate IDs | Across all content |

Run this before committing content changes to catch errors early.

---

## make

Create new content with proper scaffolding.

```bash
./ava make <type> "Title"
```

Examples:

```bash
# Create a page
./ava make page "About Us"
# → content/pages/about-us.md

# Create a blog post
./ava make post "Hello World"
# → content/posts/hello-world.md

# Create custom type content
./ava make recipe "Chocolate Cake"
# → content/recipes/chocolate-cake.md
```

The generated file includes:

```yaml
---
id: 01JGMK...           # Auto-generated ULID
title: About Us
slug: about-us          # Slugified from title
status: draft           # Always starts as draft
date: 2024-12-28        # Only for dated types
---

# About Us

Your content here.
```

Run without arguments to see available types:

```bash
./ava make
# Available types:
#   page - Pages
#   post - Posts
```

---

## User Management

Manage admin dashboard users.

### user:add

Create a new admin user:

```bash
./ava user:add <email> <password> [name]
```

Example:
```bash
./ava user:add admin@example.com secretpass "Admin User"
```

### user:password

Update an existing user's password:

```bash
./ava user:password <email> <new-password>
```

### user:remove

Remove a user:

```bash
./ava user:remove <email>
```

### user:list

List all configured users:

```bash
./ava user:list
```

---

## prefix

Toggle date prefixes on content filenames.

```bash
./ava prefix <add|remove> [type]
```

Examples:
```bash
# Add date prefix to all posts
./ava prefix add post
# → hello-world.md becomes 2024-12-28-hello-world.md

# Remove date prefix from posts
./ava prefix remove post
```

This reads the `date` field from frontmatter. Run `ava rebuild` after to update the cache.

---

## update:check

Check for available Ava updates.

```bash
./ava update:check
```

Example output:

```
Checking for updates...

Current version: 25.12.1
Latest version:  25.12.3

✓ Update available!

Release: December Bug Fixes
Published: 2025-12-30

Changelog:
----------
- 🐛 Fixed routing issue with trailing slashes
- 🔧 Improved cache rebuild performance

Run `php bin/ava update:apply` to update.
```

Results are cached for 1 hour. Force a fresh check:

```bash
./ava update:check --force
```

---

## update:apply

Download and apply the latest update.

```bash
./ava update:apply
```

The updater will:
1. Show what will be updated
2. Ask for confirmation
3. Download the release
4. Apply updates to core files
5. Rebuild the cache

Skip confirmation with `-y`:

```bash
./ava update:apply -y
```

See [Updates](updates.md) for full documentation on what gets updated and preserved.

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (invalid args, validation failures, etc.) |

## Common Workflows

### Development

```bash
# Start dev server
php -S localhost:8000 -t public

# Watch for changes (cache.mode should be 'auto')
# Cache rebuilds automatically when files change
```

### Production Deploy

```bash
# Set cache.mode to 'never' in config
# Then rebuild after deploy:
php bin/ava rebuild
```

### Content Validation

```bash
# Before committing content changes:
php bin/ava lint

# If errors found, fix and re-run
```
