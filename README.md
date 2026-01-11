![Ava CMS Banner](https://addy.zip/ava/ava-banner.webp#3)

# Ava CMS

[![Release](https://img.shields.io/github/v/release/ava-cms/ava)](https://github.com/ava-cms/ava/releases)
[![Issues](https://img.shields.io/github/issues/ava-cms/ava)](https://github.com/ava-cms/ava/issues)
[![Stars](https://img.shields.io/github/stars/ava-cms/ava)](https://github.com/ava-cms/ava/stargazers)
[![Code size](https://img.shields.io/github/languages/code-size/ava-cms/ava)](https://github.com/ava-cms/ava)
[![Discord](https://img.shields.io/discord/1028357262189801563)](https://discord.gg/fZwW4jBVh5)

Ava is a friendly, flexible, flat-file CMS built in PHP for bespoke personal websites, blogs, and more.

Content lives in plain [Markdown files](https://ava.addy.zone/docs/content) with YAML frontmatter, optional HTML, and extensible PHP shortcodes. Back it up however you like: copy a folder, sync to the cloud, or commit to Git. Your content stays portable, transparent, and fully under your control.

An intuitive [command-line interface](https://ava.addy.zone/docs/cli) lets you view statistics, manage cache, create content boilerplates, automate updates, inspect logs, and benchmark your setup without leaving your terminal.

Ava automatically builds a lightweight [cache](https://ava.addy.zone/docs/performance) so pages render quickly. There's no build step, no deploy queue, and no waiting for static regeneration. Edit a file, refresh the browser, and see your changes immediately.

Thoughtfully [documented](https://ava.addy.zone/docs) and designed to be approachable for beginners while remaining easy to customise, Ava gives you full control over your content and presentation without unnecessary complexity.

**Perfect for:** personal sites, blogs, portfolios, documentation, directories, and any project where you want simplicity without giving up power.

## Screenshots

![Ava CMS Screenshots](https://addy.zip/ava/ava-screenshots.webp#3)

Configurable CLI and admin colour themes available! Default theme is a collection of minimal, well commented templates (page, blog, post, search) for you to build on.

## Why Ava?

### ✍️ Bring Your Own Editor
No clunky WYSIWYG editors here. Write [flexible Markdown or HTML](https://ava.addy.zone/docs/content) (with easily extensible PHP shortcodes) in your favourite editor, IDE, or terminal. Focus on writing, not wrestling with a web interface.

### 📁 No Database, No Problem
No database is required, but [SQLite is seamlessly available](https://ava.addy.zone/docs/performance#backend-options) as a lightweight local file to support large content collections while keeping memory usage low.

### ⚡ Truly Instant Updates
Edit a file, refresh your browser, see it live. There’s no build step, no deploy queue, and no waiting for static regeneration. Changes are immediate.

### 🔍 Dynamic Features Built In
Unlike static site generators, Ava is a real PHP application. You get [searching, sorting, filtering and dynamic content](https://ava.addy.zone/docs/api#search-endpoint) out of the box without relying on third-party services. Add any PHP functionality you need.

### 🎨 Your HTML, Your Way
Templates are plain PHP files, so there's no template language to learn. If you know a little HTML and CSS, you already know how to [build a theme](https://ava.addy.zone/docs/theming).

### 🚀 Blazing Fast Performance
[Two-layer caching](https://ava.addy.zone/docs/performance) keeps PHP page generation extremely fast. Even without page caching, posts compile quickly, and large content updates can be indexed almost immediately for responsive search and sorting.

### 🧩 Flexible Content Modelling
Define any [content types](https://ava.addy.zone/docs/configuration), taxonomies, and URL patterns you like. Blogs, portfolios, recipes, documentation. Structure content however it makes sense to you.

### 🔌 Simple Extensibility
Hooks, shortcodes, and a straightforward [plugin system](https://ava.addy.zone/docs/creating-plugins) let you extend Ava without fighting it or working around hidden abstractions.

### 💻 Works Anywhere PHP Runs
Cheap shared hosting, a VPS, or your laptop — if it can run modern PHP and you can use the shell, it can run Ava. No special [server setup](https://ava.addy.zone/docs/hosting) required.

### 🤖 AI Friendly
A clean file-based structure, clear configuration, thorough documentation, and a straightforward CLI make it easy for [AI assistants](https://ava.addy.zone/docs/ai-reference) to read your content, understand your setup, and help you build themes and extensions.

## Quick Start

```bash
# 1. Download from GitHub releases (or git clone)
#    https://github.com/ava-cms/ava/releases

# 2. Install dependencies
composer install

# 3. Build the content index
./ava rebuild

# 4. Preview locally (optional)
php -S localhost:8000 -t public
```

Open http://localhost:8000 and you're running! For production, see the [hosting guide](https://ava.addy.zone/docs/hosting).

## Project Structure

```
├── content/           # Your Markdown content
│   ├── pages/         # Pages (URLs match folder structure)
│   └── posts/         # Posts (blog-style URLs)
├── themes/            # Your theme templates
│   └── default/
├── plugins/           # Plugins (bundled + your own)
├── app/
│   └── config/        # Your configuration files
├── core/              # Ava engine (don't edit)
└── storage/           # Cache, logs, temp files
```

## Documentation

Main docs: https://ava.addy.zone/docs

| Section | Description |
|---------|-------------|
| [Getting Started](https://ava.addy.zone/docs) | Installation and first steps |
| [Writing Content](https://ava.addy.zone/docs/content) | Markdown, frontmatter, organizing files |
| [Configuration](https://ava.addy.zone/docs/configuration) | Site settings, content types, taxonomies |
| [Themes](https://ava.addy.zone/docs/theming) | Templates, the `$ava` helper, queries |
| [Hosting](https://ava.addy.zone/docs/hosting) | Shared hosting, VPS, and deployment |
| [CLI](https://ava.addy.zone/docs/cli) | Command-line reference |

## Requirements

- PHP 8.3+
- Extensions: `mbstring`, `json`, `ctype`
- Optional: `pdo_sqlite` (large site scaling), `igbinary` (faster index loading), `opcache`, `curl`

## Performance

Ava is designed to be fast by default, whether you have 100 posts or 100,000.

- **Instant Publishing:** No build step. Edit a file, hit refresh, and it's live.
- **Smart Caching:** A tiered caching system ensures your most popular pages load instantly.
- **Scalable Backends:** Start with the default Array backend for raw speed, or switch to SQLite for constant memory usage at scale.
- **Static Speed:** Enable full page caching to serve static HTML files, bypassing the application entirely for most visitors.

[See full benchmarks and scaling guide →](https://ava.addy.zone/docs/performance)

## Contributing

Ava is moving quickly, so I'm not accepting undiscussed pull requests right now. The best way to help:

- [Open an issue](https://github.com/ava-cms/ava/issues) — bugs, ideas, questions all welcome
- [Join the Discord](https://discord.gg/fZwW4jBVh5) — chat and support

## License

MIT — free and open source. See [LICENSE](LICENSE).

