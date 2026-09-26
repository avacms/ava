# Ava CMS Storage

Generated runtime files. Everything here is rebuilt when deleted; keep it out of
version control.

- `cache/` — content index and cached pages
- `logs/` — error and indexer logs
- `tmp/` — temporary files (updates, tests)

## Content index

Each rebuild writes a complete generation to `cache/index/<generation>/`, then
points `cache/state.json` at it, so requests never see a half-built index.
Replaced generations are deleted after a two-minute grace period.

| File | Purpose |
|------|---------|
| `routes.bin` | Route tables (every request) |
| `slug_lookup.bin` | Single-item lookups (type/key → file) |
| `recent_cache.bin` | Newest 200 items of each type |
| `tax_index.bin` | Taxonomy terms with counts |
| `content_index.bin` | Metadata for every item (array backend) |
| `bodies/*.bin` | Raw bodies in ~1 MB shards, read by search (array backend) |
| `content_index.sqlite` | Metadata, bodies and routes in one database (SQLite backend) |
| `html/*.bin` | Pre-rendered Markdown, one file per page |
| `synonyms.bin`, `stopwords.bin` | Search configuration |

`cache/.cache_key` signs the binary files. The web server and whoever runs
`./ava rebuild` must both be able to read it.

## Page cache

`cache/pages/*.json` holds cached pages, feeds and sitemaps, one file per scheme,
host, path and `?paged` value (listings only). Only anonymous GET and HEAD
requests for the host in `site.base_url` (plus `webpage_cache.hosts`) share
entries; the PHP session cookie and Authorization header bypass it, other cookies
and client Cache-Control headers don't. Responses that set cookies, Vary or their
own Cache-Control are never stored, and neither are pages rendered for
`utm_*`-tagged URLs, although those are served the plain URL's entry.

Each entry records the index state it was rendered from and is ignored after a
rebuild or theme change. Cached pages carry an ETag, so repeat visits get 304s.
