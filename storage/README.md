# Ava CMS Storage

This directory contains generated runtime files:

- `cache/` — Content index files and cached HTML pages
- `logs/` — Error and debug logs
- `tmp/` — Temporary files

## Important

- This entire directory is safe to delete (will be regenerated)
- Add to `.gitignore` in production
- Content index is auto-rebuilt based on `content_index.mode` setting

## Content Index Files

| File | Purpose |
|------|---------|
| `content_index.bin` | All content indexed by type, slug, ID (full index) |
| `slug_lookup.bin` | Fast single-item lookups (type/slug → file path) |
| `recent_cache.bin` | Top 200 items per type for fast archive queries |
| `tax_index.bin` | Taxonomy terms with counts |
| `routes.bin` | Compiled route map |
| `fingerprint.json` | Change detection data |
| `pages/*.html` | Cached HTML pages (page cache)
