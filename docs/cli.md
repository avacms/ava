# CLI Tools

Ava comes with a handy command-line tool to help you manage your site. It's great for checking your site's health, clearing the cache, or creating new content.

## How to use it

Open your terminal in your project folder and run:

```bash
./ava <command>
```

## Common Commands

| Command | What it does |
|---------|--------------|
| `status` | Shows a quick overview of your site (content counts, cache status). |
| `rebuild` | Forces the cache to rebuild. Useful if something looks stuck. |
| `lint` | Checks all your files for errors. |
| `make` | Creates a new file for you. |
| `user:add` | Creates a new admin user. |
| `update:check` | Checks if there's a new version of Ava. |

## Checking Status

Run `./ava status` to see if everything is healthy.

```
=== Ava CMS Status ===

Site: My Site
URL:  https://example.com

Cache:
  Status: ✓ Fresh
  Mode:   auto
```

## Creating Content

You can create files manually, or let Ava do it for you:

```bash
./ava make post "My New Post"
```

This creates a new file in `content/posts/` with the correct date and frontmatter already filled in.

Tip: `make` is just a helper. You can always create files manually if you prefer.

## Validating Content (lint)

Before you commit content changes, it’s a good habit to run:

```bash
./ava lint
```

It checks for common problems like:

| Check | What it means |
|------|---------------|
| YAML syntax | Frontmatter must parse correctly |
| Required fields | `title`, `slug`, `status` are present |
| Status values | Must be `draft`, `published`, or `private` |
| Slug format | Lowercase, alphanumeric, hyphens only |
| Duplicate slugs | Within the same content type |
| Duplicate IDs | Across all content |

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
