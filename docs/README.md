# Addy's (very adaptable) CMS

[![Release](https://img.shields.io/github/v/release/adamgreenough/ava)](https://github.com/adamgreenough/ava/releases)
[![Issues](https://img.shields.io/github/issues/adamgreenough/ava)](https://github.com/adamgreenough/ava/issues)
[![Code size](https://img.shields.io/github/languages/code-size/adamgreenough/ava)](https://github.com/adamgreenough/ava)
[![Discord](https://img.shields.io/discord/1028357262189801563)](https://discord.gg/Z7bF9YeK)

A friendly, flat-file CMS for bespoke personal websites, blogs and more.

## Philosophy

Ava is designed for people who love the web. It sits in the sweet spot between a static site generator and a full-blown CMS:

- **📂 Your Files, Your Rules.** Content is just Markdown. Configuration is readable PHP. Everything lives in your Git repo, so you own your data forever.
- **✍️ Bring Your Own Editor.** No clunky WYSIWYG editors here. Write in VS Code, Obsidian, or Notepad. If you can write HTML and CSS, you can build a theme.
- **🚀 No Database Required.** Ava indexes your content into fast PHP arrays. You get the speed of a static site with the dynamic power of PHP.
- **⚡ Edit Live.** Change a file, hit refresh, and see it instantly. No build steps, no waiting for deploys.
- **🎨 Bespoke by Design.** Don't fight a platform. Create any content type you want—blogs, portfolios, recipe collections, changelogs—without plugins or hacks.
- **🤖 AI Friendly.** The clean, file-based structure and integrated docs makes it trivial for AI assistants to read your content, understand your config, and help you build features.

## Core Features

| Feature | What it does for you |
|---------|-------------|
| **Content Types** | Define exactly what you're publishing (Pages, Posts, Projects, etc.). |
| **Taxonomies** | Organize content your way with custom categories, tags, or collections. |
| **Smart Routing** | URLs are generated automatically based on your content structure. |
| **Themes** | Write standard HTML and CSS. Use PHP only where you need dynamic data. |
| **Plugins** | Add functionality like sitemaps and feeds without bloat. |
| **Speed** | Built-in caching makes your site load instantly, even on cheap hosting. |

## Performance

Ava is built to scale. Here's what it can handle:

| Metric | 10,000 Posts |
|--------|--------------|
| Cache rebuild | ~2.4 seconds |
| CLI status check | ~175ms |
| Archive page query | ~70ms |
| Cache load | ~45ms |
| Memory usage | ~50MB |
| Cache size | ~4MB |

The caching system uses optimized binary serialization (igbinary when available) for fast loading even with massive content libraries. There's no database—just lightning-fast PHP arrays loaded from disk.


## Requirements

<img src="https://addy.zip/ava/i-love-php.webp" alt="I love PHP" style="float: right; width: 180px; margin: 0 0 1rem 1.5rem;" />

Ava requires **PHP 8.3** or later. Most modern hosts include this, but check before you start.

**Required Extensions:**

- `mbstring` — UTF-8 text handling
- `json` — Config and API responses
- `ctype` — String validation

These are bundled with most PHP installations. If you're missing one, your host's control panel or `apt install php-mbstring` will sort it out.

**Optional Extensions:**

- `igbinary` — Faster cache serialization (15× faster, 90% smaller)
- `opcache` — Opcode caching for production
- `gd` or `imagick` — Image processing if you add it later

If `igbinary` isn't available, Ava automatically falls back to PHP's built-in `serialize`. You get the same functionality, just slightly slower cache loads. The system auto-detects which format was used when reading cache files, so you can add or remove igbinary at any time.

## Quick Start

```bash
# Clone the repo
git clone https://github.com/adamgreenough/ava.git mysite
cd mysite

# Install dependencies
composer install

# Check status (shows PHP version and extensions)
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

1. **Write** — Create Markdown files in your `content/` folder.
2. **Index** — Ava automatically scans your files and builds a fast index.
3. **Render** — Your theme turns that content into beautiful HTML.

The system handles all the boring stuff: routing, sorting, pagination, and search. You just focus on the content and the design.

## Editing Content: Pick Your Style

Ava is flexible about *how* you edit. Start with local-first. It’s the fastest feedback loop: type → save → refresh.

If you want some beginner-friendly background on the tools involved:

- Learn the basics of running commands in [CLI](cli.md)
- Learn what Markdown is (and what editors are great) in [Content](content.md)

## Is Ava for You?

Ava is perfect if:
- You know some HTML and CSS (or want to learn!).
- You prefer writing in a real text editor over a web form.
- You want a fast, personal site that you fully own and control.
- You don't want to manage a database or complex server setup.

It won't be a good fit if you need a drag-and-drop page builder or a massive ecosystem of third-party themes.

## Next Steps

- [Configuration](configuration.md) — Site settings and content types
- [Content](content.md) — Writing pages and posts
- [Themes](themes.md) — Creating templates
- [Admin](admin.md) — Optional dashboard
- [CLI](cli.md) — Command-line tools
- [Showcase](showcase.md) — Community sites, themes, and plugins

## License

Ava CMS is free and open-source software licensed under the [MIT License](https://github.com/adamgreenough/ava/blob/main/LICENSE).

In plain English, that means you can:

- Use Ava for personal or commercial projects.
- Modify it to fit your site (and keep your changes private if you want).
- Share it, fork it, and redistribute it.

The main thing the license asks is that you keep the MIT license text and copyright notice with the software.

Also worth knowing: the MIT license comes with a standard “no warranty” clause. Ava is provided as-is, so you’re responsible for how you deploy and run it. There's no guarantees that it's fit-for-purpose or impenetrably secure. Standard open-source stuff.

## Contributing

Ava is still fairly early and moving quickly, so I’m not looking for pull requests or additional contributors just yet.

That said, I’d genuinely love your feedback:

- If you run into a bug, get stuck, or have a “this could be nicer” moment, please [open an issue](https://github.com/adamgreenough/ava/issues).
- Feature requests, ideas, and “what if Ava could…” suggestions are very welcome.

If you prefer a more conversational place to ask questions and share ideas, join the Discord:

https://discord.gg/Z7bF9YeK

Even small notes help a lot at this stage.

