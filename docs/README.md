# Addy's (very adaptable) CMS

The friendly, flat-file CMS for bespoke websites.

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

1. **Write** — Create Markdown files in your `content/` folder.
2. **Index** — Ava automatically scans your files and builds a fast index.
3. **Render** — Your theme turns that content into beautiful HTML.

The system handles all the boring stuff: routing, sorting, pagination, and search. You just focus on the content and the design.

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

## License

Ava CMS is free and open-source software licensed under the [MIT License](https://github.com/addy/ava-cms/blob/main/LICENSE). You are free to use it for personal and commercial projects.

