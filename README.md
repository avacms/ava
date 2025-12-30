![Ava CMS Banner](https://addy.zip/ava/ava-banner.webp)

# Ava CMS

[![Release](https://img.shields.io/github/v/release/adamgreenough/ava)](https://github.com/adamgreenough/ava/releases)
[![Issues](https://img.shields.io/github/issues/adamgreenough/ava)](https://github.com/adamgreenough/ava/issues)
[![Stars](https://img.shields.io/github/stars/adamgreenough/ava)](https://github.com/adamgreenough/ava/stargazers)
[![Code size](https://img.shields.io/github/languages/code-size/adamgreenough/ava)](https://github.com/adamgreenough/ava)
[![Discord](https://img.shields.io/discord/1028357262189801563)](https://discord.gg/Z7bF9YeK)

Ava is a friendly, flexible, flat-file, PHP-based CMS for bespoke personal websites, blogs and more. Content is Markdown files (with YAML frontmatter), and Ava builds a fast cache so pages render quickly—no database required. 

Thoroughly [documented](https://ava.addy.zone/#/) with beginners in mind and easy to customize, Ava gives you full control over your content and design without complexity.

**Perfect for:** personal sites, blogs, portfolios, documentation, and any project where you want simplicity without sacrificing power.

## Why Ava?

### 📁 No Database, No Problem
Content is just Markdown files. Back them up however you like—copy to a folder, sync to the cloud, or use Git. Your data is always portable and yours to control.

### ⚡ Truly Instant Updates
Edit a file, refresh your browser, see it live. There's no build step, no deploy queue, no waiting for static regeneration. Changes are immediate.

### 🔍 Dynamic Features Built-In
Unlike static site generators, Ava is a real PHP application. You get search, forms, and dynamic content without third-party services. Add any PHP functionality you need.

### 🎨 Your HTML, Your Way
Templates are plain PHP files, so there's no template language to learn. If you know some HTML, CSS, and a little PHP, you can build any design.

### 🚀 Blazing Fast Performance
Two-layer caching serves pages in under 1ms. Even without caching, 10,000 posts render in ~160ms. You get the speed of static files with the flexibility of dynamic PHP. [See benchmarks →](https://ava.addy.zone/#/README?id=performance)

### 🧩 Flexible Content Modelling
Define any content types, taxonomies, and URL patterns. Blogs, portfolios, recipes, documentation—structure content however you think.

### 🔌 Simple Extensibility
Hooks, shortcodes, and a straightforward plugin system. Extend Ava without fighting it.

### 💻 Works Anywhere PHP Runs
Cheap shared hosting, a VPS, your laptop—if it runs PHP 8.3, it runs Ava. No special server requirements.

## Quick Start

```bash
# 1. Download from GitHub releases (or git clone)
#    https://github.com/adamgreenough/ava/releases

# 2. Install dependencies
composer install

# 3. Build the content index
./ava rebuild

# 4. Preview locally (optional)
php -S localhost:8000 -t public
```

Open http://localhost:8000 and you're running! For production, see the [hosting guide](https://ava.addy.zone/#/hosting).

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

Main docs: https://ava.addy.zone/

| Section | Description |
|---------|-------------|
| [Getting Started](https://ava.addy.zone/#/README) | Installation and first steps |
| [Writing Content](https://ava.addy.zone/#/content) | Markdown, frontmatter, organizing files |
| [Configuration](https://ava.addy.zone/#/configuration) | Site settings, content types, taxonomies |
| [Themes](https://ava.addy.zone/#/themes) | Templates, the `$ava` helper, queries |
| [Hosting](https://ava.addy.zone/#/hosting) | Shared hosting, VPS, and deployment |
| [CLI](https://ava.addy.zone/#/cli) | Command-line reference |

## Requirements

- PHP 8.3+
- Extensions: `mbstring`, `json`, `ctype`
- Optional: `igbinary` (faster index loading), `opcache`, `curl`

## Performance

Fast by default. Most sites have under 1,000 posts—here's what you can expect:

| Posts | Cached Page | Archive Page | Single Post |
|-------|-------------|--------------|-------------|
| 100 | <1ms | 3ms | 5ms |
| 1,000 | <1ms | 3ms | 8ms |

**Cached pages serve in under 1 millisecond**—faster than most static site generators can serve pre-built files. Archive pages stay fast regardless of content size thanks to tiered caching.

Need to scale further? Ava handles 100,000 posts without breaking a sweat.

[Full benchmarks, memory usage, and igbinary comparison →](https://ava.addy.zone/#/caching?id=performance)

## Contributing

Ava is moving quickly, so I'm not accepting undiscussed pull requests right now. The best way to help:

- [Open an issue](https://github.com/adamgreenough/ava/issues) — bugs, ideas, questions all welcome
- [Join the Discord](https://discord.gg/Z7bF9YeK) — chat and support

## License

MIT — free and open source. See [LICENSE](LICENSE).

