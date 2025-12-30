# Caching

Ava uses two distinct caching layers to deliver fast page loads without a database.

## Overview

| Layer | What it stores | Purpose |
|-------|----------------|---------|
| **Content Index** | Metadata, routes, taxonomy data | Avoid parsing Markdown on every request |
| **Page Cache** | Rendered HTML pages | Serve pages instantly without rendering |

The **Content Index** is the foundation—a binary snapshot of your content structure. The **Page Cache** stores the final HTML output for instant serving.

---

## Content Index

When you add or edit content, Ava doesn't read Markdown files on every request. Instead, it builds an index of all content metadata and stores it in `storage/cache/`.

### Index Files

| File | Contents |
|------|----------|
| `content_index.bin` | All content items indexed by type, slug, and ID |
| `slug_lookup.bin` | Lightweight type/slug → file path mapping for fast single-item loads |
| `recent_cache.bin` | Top 200 items per type for fast archive queries (~51KB) |
| `tax_index.bin` | Taxonomy terms with item counts |
| `routes.bin` | Compiled URL → content mappings |
| `fingerprint.json` | Hash of content files for change detection |

### Configuration

Set the rebuild mode in `app/config/ava.php`:

\`\`\`php
'content_index' => [
    'mode' => 'auto',
],
\`\`\`

| Mode | Behavior | Best for |
|------|----------|----------|
| `auto` | Rebuilds when content files change | Development, small sites |
| `never` | Only rebuilds via `./ava rebuild` | Production, CI/CD |
| `always` | Rebuilds on every request | Debugging only |

**Recommendation:** Use `auto` during development, switch to `never` for production.

### Binary Serialization

Index files use binary serialization for fast loading:

- **igbinary** (if installed): ~4-5× faster loading, ~10× smaller
- **PHP serialize** (fallback): Works everywhere

Files include a format marker (`IG:` or `SZ:`) for safe environment switching. Run `./ava rebuild` after changing PHP environments.

### Tiered Caching

Ava uses a three-tier caching strategy to optimize for different access patterns:

| Cache | Size (100k posts) | Use Case |
|-------|-------------------|----------|
| `recent_cache.bin` | ~51KB | Archive pages 1-20, RSS, homepage |
| `slug_lookup.bin` | ~8.7MB | Single post/page views |
| `content_index.bin` | ~45MB | Deep pagination, search, filters |

#### Recent Cache (Archives)

For common queries like homepages, archives (pages 1-20), and RSS feeds:

1. During index rebuild, Ava extracts the most recent 200 published items per content type
2. This "recent cache" stores only essential fields (id, slug, title, date, excerpt, taxonomies)
3. Queries that can be satisfied from recent cache load ~51KB instead of ~45MB
4. Result: Archive pages load in **~3ms with ~2MB memory** instead of ~2400ms with ~323MB

**When recent cache is used:**

- Single content type queries
- Published status filter only
- Date descending order (default)
- No taxonomy filters, field filters, or search
- Requested page fits within cached items (200 items ÷ perPage)

#### Slug Lookup (Single Items)

For individual post/page views:

1. The slug lookup maps `type/slug → file path` with minimal overhead (~8.7MB for 100k posts)
2. Single-item lookups load this compact index, then parse just one Markdown file
3. Result: Single posts load in **~130ms with ~82MB memory** instead of ~570ms with ~324MB

**When slug lookup is used:**

- Loading a single item by type and slug (e.g., viewing a blog post)

#### Full Index (Complex Queries)

The full content index is only loaded for:

- Deep pagination (beyond page 20 with 10 per page)
- Filtered queries (taxonomy, field, search)
- Non-default sort order
- Multiple content types

### Manual Rebuild

\`\`\`bash
./ava rebuild
\`\`\`

Rebuilds the content index and clears the page cache. Takes ~2-3 seconds for 10,000 items.

---

## Page Cache

The page cache stores fully-rendered HTML. When a visitor requests a cached page, Ava serves the static HTML directly—no template rendering, no Markdown parsing.

### How It Works

1. **First request**: Page is rendered normally (~30-50ms)
2. **Cache write**: HTML saved to `storage/cache/pages/`
3. **Subsequent requests**: Cached HTML served (~0.1ms)

This is **on-demand** caching—pages are cached after their first visit.

### Configuration

\`\`\`php
'page_cache' => [
    'enabled' => true,
    'ttl' => null,
    'exclude' => [
        '/api/*',
        '/preview/*',
    ],
],
\`\`\`

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `true` | Enable HTML page caching |
| `ttl` | int\|null | `null` | Lifetime in seconds. `null` = until rebuild |
| `exclude` | array | `[]` | URL patterns to never cache |

### What Gets Cached

- ✅ Single content pages (posts, pages, custom types)
- ✅ Archive pages (post lists, paginated archives)
- ✅ Taxonomy pages (categories, tags)
- ❌ Admin pages
- ❌ URLs with query parameters (except UTM)
- ❌ Logged-in admin users
- ❌ POST/PUT/DELETE requests

### Per-Page Override

Override the global setting in frontmatter:

\`\`\`yaml
---
title: My Dynamic Page
cache: false
---
\`\`\`

### Cache Invalidation

The page cache is cleared when:

- `./ava rebuild` is run
- Content changes (with `content_index.mode = 'auto'`)
- "Rebuild Now" clicked in admin
- "Flush Pages" clicked in admin

Clear manually:

\`\`\`bash
./ava pages:clear              # Clear all
./ava pages:clear /blog/*      # Clear matching pattern
\`\`\`

### HTML Comments

Every page includes a footer comment:

**Fresh render:**
\`\`\`html
<!-- Generated by Ava CMS v25.12.1 | Rendered: 2025-12-29 10:00:00 | 32ms -->
\`\`\`

**Cached page:**
\`\`\`html
<!-- Generated by Ava CMS v25.12.1 | Cached: 2025-12-29 10:00:00 -->
\`\`\`

---

## Monitoring

### CLI

\`\`\`bash
./ava status         # Shows content index and page cache status
./ava pages:stats    # Detailed page cache stats
\`\`\`

### Admin Dashboard

The dashboard shows both layers with quick actions:
- **Rebuild Now**: Rebuilds content index (clears page cache)
- **Flush Pages**: Clears page cache only

---

## Performance

Most Ava sites have fewer than 1,000 posts. Here's what you can expect at realistic scales:

### Quick Reference

| Posts | Archive Page | Single Post | Index Rebuild |
|-------|--------------|-------------|---------------|
| 10 | 2ms | 6ms | instant |
| 100 | 3ms | 5ms | ~40ms |
| 1,000 | 3ms | 8ms | ~220ms |

Archive pages stay fast regardless of content size thanks to the recent cache. Single posts load quickly via the slug lookup. **Cached pages serve in under 1ms.**

### Full Benchmark Table

For those pushing limits:

| Posts | Rebuild | Archive | Single Post | Full Index | Slug Lookup | Recent Cache |
|-------|---------|---------|-------------|------------|-------------|--------------|
| 10 | ~15ms | 2ms | 6ms | 5.5KB | 1.1KB | 2.4KB |
| 100 | ~40ms | 3ms | 5ms | 47KB | 8.8KB | 23KB |
| 1,000 | ~220ms | 3ms | 8ms | 454KB | 87KB | 55KB |
| 10,000 | ~2.6s | 3ms | 15ms | 4.4MB | 887KB | 52KB |
| 100,000 | ~27s | 4ms | 180ms | 45MB | 8.7MB | 51KB |

**Key observations:**

- **Archive pages** stay constant (~3ms) because they use the compact recent cache (~51KB)
- **Single posts** scale with slug lookup size, but remain fast
- **Recent cache** stays tiny regardless of total content (only stores top 200 items)

### Memory Usage

| Query Type | 1,000 Posts | 10,000 Posts | 100,000 Posts |
|------------|-------------|--------------|---------------|
| Archive page (recent cache) | 2MB | 2MB | 2MB |
| Single post (slug lookup) | 2MB | 10MB | 80MB |
| Deep pagination (full index) | 4MB | 35MB | 323MB |

### With vs Without igbinary

The `igbinary` extension is recommended for better performance:

| Metric | With igbinary | Without igbinary | Difference |
|--------|---------------|------------------|------------|
| Full index size (10k posts) | 4.4MB | 42MB | **10× larger** |
| Full index load time | 61ms | 422ms | **7× slower** |
| Memory usage | 35MB | 229MB | **6.5× more** |

At 100,000 posts, the full index without igbinary is **415MB**—large enough to cause memory limit errors on typical servers. The tiered caching (recent cache and slug lookup) mitigates this, but `igbinary` is strongly recommended for sites with 10,000+ items.

**Slug lookup and recent cache** are less affected because they're much smaller to begin with.

<details>
<summary>Benchmark environment</summary>

Tested on Ava **v25.12.2** using a Hetzner Cloud VPS (4 shared vCPUs, 8GB RAM, Intel Xeon Skylake). PHP 8.3.29, single-threaded. The `igbinary` extension was enabled for primary benchmarks.
</details>

---

## Security

The page cache is secure by default:

| Protection | How |
|------------|-----|
| Query string XSS | URLs with \`?params\` bypass cache |
| Cache poisoning | Cache key is URL path only |
| Session leakage | Admin users bypass cache |
| POST injection | Only GET requests cached |
| Path traversal | Filenames are MD5 hashed |

---

## Troubleshooting

### Pages not being cached

1. Check if enabled: \`./ava status\`
2. Log out of admin (admin users bypass cache)
3. Check exclude patterns
4. Check for query parameters in URL

### Content changes not appearing

1. If \`mode\` is \`never\`, run \`./ava rebuild\`
2. Delete \`storage/cache/fingerprint.json\` to force rebuild
3. Run \`./ava rebuild\` to reset everything

### Empty content after PHP change

Run \`./ava rebuild\` to regenerate index files in the current format.
