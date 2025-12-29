# Addy's (very adaptable) CMS

A developer-first, flat-file PHP CMS for long-lived personal sites.

## Philosophy

Ava is built on a few core principles:

- **Files are the source of truth.** Content is Markdown. Configuration is PHP arrays. Everything lives in your Git repo.
- **No database.** Content is indexed into PHP arrays and cached. Fast reads, zero database overhead.
- **Edit live.** This isn't a static site generator. Change a file, see it immediately. The cache auto-rebuilds when content changes.
- **Freeform structure.** Define any content types you want. Pages, posts, recipes, bookmarks, whatever. You're not locked into a blog-or-pages paradigm.
- **Developer-friendly.** Minimal magic. The code is readable. Templates are plain PHP. Extend via hooks, shortcodes, and plugins.

## Core Concepts

| Concept | Description |
|---------|-------------|
| **Content Types** | Define what kinds of content your site has (pages, posts, etc.). Each type has its own directory, URL pattern, and template. |
| **Taxonomies** | Grouping systems like categories and tags. Fully customizable — create any taxonomy you need. |
| **Shortcodes** | Embed dynamic content in Markdown. Built-in shortcodes plus easy custom ones. |
| **Themes** | Plain PHP templates with a helper object. No template language to learn. |
| **Plugins** | Extend functionality via hooks. Plugins can add routes, shortcodes, content types, and more. |
| **Cache** | Content is compiled into PHP arrays for fast loading. Rebuilds automatically or on-demand. |

## Quick Start

```bash
# Clone the repo
git clone https://github.com/adamgreenough/ava.git mysite
cd mysite

# Install dependencies
composer install

# Check status
./ava status

# Build the cache
./ava rebuild

# Start development server
php -S localhost:8000 -t public
```

Visit [http://localhost:8000](http://localhost:8000) to see your site.

## Project Structure

```
mysite/
├── app/
│   ├── config/          # Configuration files
│   │   ├── ava.php      # Main config (site, paths, cache)
│   │   ├── content_types.php
│   │   └── taxonomies.php
│   ├── hooks.php        # Custom hooks
│   └── shortcodes.php   # Custom shortcodes
├── content/
│   ├── pages/           # Page content (hierarchical URLs)
│   ├── posts/           # Blog posts (/blog/{slug})
│   └── _taxonomies/     # Term registries
├── themes/
│   └── default/         # Theme templates
│       ├── templates/
│       └── assets/
├── plugins/             # Optional plugins
├── snippets/            # Safe PHP snippets for [snippet] shortcode
├── public/              # Web root
│   └── index.php        # Entry point
├── storage/cache/       # Generated cache (gitignored)
└── ava                  # CLI tool
```

## How It Works

1. **Content** — You write Markdown files with YAML frontmatter in `content/`.
2. **Cache** — Ava indexes all content into PHP arrays stored in `storage/cache/`.
3. **Request** — When a request comes in, the router finds the matching content.
4. **Render** — The theme template receives the content and renders HTML.

The cache makes reads instant. In `auto` mode (recommended), the cache rebuilds automatically when you change files.

## What Ava Is Not

- **Not a static site generator.** Pages are rendered on request. You can run dynamic PHP.
- **Not a traditional CMS.** There's no WYSIWYG editor. You edit files directly.
- **Not WordPress.** No plugins ecosystem, no themes marketplace. It's a framework for developers.

## Next Steps

- [Configuration](configuration.md) — Site settings and content types
- [Content](content.md) — Writing pages and posts
- [Themes](themes.md) — Creating templates
- [Admin](admin.md) — Optional dashboard
- [CLI](cli.md) — Command-line tools
