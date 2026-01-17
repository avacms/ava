<p align="center">
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://ava.addy.zone/media/dark.png">
  <source media="(prefers-color-scheme: light)" srcset="https://ava.addy.zone/media/light.png">
  <img alt="Ava CMS" src="https://ava.addy.zone/media/light.png">
</picture>
</p>

<p align="center">
  <strong>A fast, flexible, file-based CMS built with modern PHP.</strong><br>
</p>

---

![Ava CMS screenshots](https://ava.addy.zone/media/screenshots.webp)

---

# Ava CMS

[![Release](https://img.shields.io/github/v/release/AvaCMS/ava)](https://github.com/AvaCMS/ava/releases)
[![Issues](https://img.shields.io/github/issues/AvaCMS/ava)](https://github.com/AvaCMS/ava/issues)
[![Stars](https://img.shields.io/github/stars/AvaCMS/ava)](https://github.com/AvaCMS/ava/stargazers)
[![Code size](https://img.shields.io/github/languages/code-size/AvaCMS/ava)](https://github.com/AvaCMS/ava)
[![License](https://img.shields.io/github/license/AvaCMS/ava)](https://github.com/AvaCMS/ava/blob/main/LICENSE)
[![Discord](https://img.shields.io/discord/1028357262189801563)](https://discord.gg/fZwW4jBVh5)

Ava CMS is a modern flat-file CMS for people who want a website they can **understand**, **move**, **scale** and **keep**.

Your content lives on disk as plain files, not rows in a database or records in a proprietary system. Create a Markdown file and you have a page. Edit it, refresh your browser, and it’s live.

Your site stays readable, portable, and fully yours. No proprietary formats. No hidden layers. Just files in, website out.

```text
your-site/
├── app/           # Your code
│   ├── config/        # Configuration (site settings, content types, taxonomies, users)
│   ├── plugins/       # Enabled plugins
│   ├── snippets/      # Reusable HTML/PHP content blocks
│   └── themes/        # Your HTML/PHP templates
├── content/       # Your content
│   ├── pages/           # Your Markdown content
│   └── ...              # Other content types (posts, products, etc)
├── core/          # Ava CMS code
├── public/        # Web root, public files
│   └── media/           # Uploaded media (images, videos, etc)
├── storage/       # Cache and logs
├── vendor/        # Minimal Composer dependencies
└── ava            # CLI tool
```

Ava CMS is not a “one-click” CMS, and it doesn’t try to be. It trades heavy admin interfaces and complex deployment pipelines for speed, clarity, and control. If you’re comfortable editing files, writing a little HTML, and checking documentation — or want a CMS that grows with you as you learn — Ava fits naturally into your workflow.

## ✨ Why Ava?

- 📝 **Markdown & HTML** — Write fast in Markdown, drop into HTML when you need total control.
- ⚡ **Instant feedback** — No complicated build steps or deploy queue. Edit a file, refresh, done with automatic indexing.
- 🎨 **Design freedom** — Plain PHP templates with standard HTML/CSS. Ava CMS stays out of your way.
- 🧩 **Flexible content modeling** — Define blogs, portfolios, events, catalogs, or anything else using custom content types and fields.
- 🚀 **Dynamic features without bloat** — Search, filtering, pagination and sorting work out of the box.
- 🛠️ **Power when you want it** — A CLI, plugin system, and hooks keep put advanced features at hand.
- 📈 **Seamless scaling** — Switch engines with a single setting, optional SQLite keeps sites with tens of thousands of posts snappy. 
- 🤖 **LLM-friendly** — Clear files, detailed docs, and a smooth CLI make Ava CMS + AI tools a great pair to help you build themes and plugins.

## 📦 What’s included

- **Content types**, **custom fields** and **taxonomies** for modeling your site your way
- **Optional admin dashboard** for structured content editing and site monitoring
- **Smart routing** based on your content structure or configured patterns
- **Shortcodes** and **snippets** for reusable dynamic blocks inside Markdown
- **Search** across your content with configurable weighting
- **Plugins + hooks** (with bundled plugins like sitemap, redirects, and feeds)
- **CLI tool** for everyday tasks (cache, users, diagnostics, and more)
- **SEO features** like customisable meta tags, sitemaps, and clean URLs
- **Caching** (two-tier content indexing + configurable full-page caching for static-speed delivery)

## 💡 How it works

1. **Write** — Create Markdown files in `content/`.
2. **Index** — Ava CMS automatically scans your files and builds fast indexes.
3. **Render** — Your theme turns that content into HTML.

You choose how you work: edit directly on your server (SFTP/SSH), work locally and upload, use Git, or mix and match. Ava CMS doesn’t lock you into a workflow, it adapts to yours.

## 🏁 Quick Start

### Requirements

- **PHP 8.3+**
- **Composer**

**Optionally**, for better performance and features:

- **`igbinary`** PHP extension for faster indexing and caching
- **`gd`** or **`imagick`** PHP extension for image processing

**Only faster for massive sites** (~10,000+ items) or **very low memory** environments:

- **`pdo_sqlite`** PHP extension ([see benchmarks](https://ava.addy.zone/docs/performance))

That’s it! Ava CMS is designed to run happily whether it's on modest shared hosting, a scalable VPS, powerful cloud infrastructure or just your local machine and works well with most web servers (Apache, Nginx, Caddy, etc).

### 1) Install

**Option A: Download a release**

- Download the latest release: https://github.com/avacms/ava/releases
- Extract it into a folder on your machine or server

**Option B: Clone from GitHub**

```bash
git clone https://github.com/avacms/ava.git my-site
cd my-site
composer install
```

If you downloaded a release zip, run this from the extracted folder:

```bash
composer install
```

### 2) Configure

Edit your site settings in `app/config/ava.php`.

### 3) Run locally

Start the built-in PHP development server if you want to run Ava CMS locally:

```bash
./ava start
# or
php -S localhost:8000 -t public
```

Visit `http://localhost:8000`.

### 4) Create content

Add a new page by creating a Markdown file in `content/pages/`.

**File:** `content/pages/hello-world.md`

```markdown
---
title: Hello World
status: published
---

# Welcome to Ava CMS!

This is my first page. It's just a text file.
```

Visit `http://localhost:8000/hello-world` to see it live.

## 📚 Documentation

Documentation lives at **https://ava.addy.zone/**.

- [Getting Started](https://ava.addy.zone/docs)
- [Configuration](https://ava.addy.zone/docs/configuration)
- [Admin Dashboard](https://ava.addy.zone/docs/admin)
- [Theming](https://ava.addy.zone/docs/theming)
- [CLI](https://ava.addy.zone/docs/cli)
- [Plugin Development](https://ava.addy.zone/docs/creating-plugins)
- [Showcase](https://ava.addy.zone/showcase)

## 🔌 Plugins & Themes

Ava includes a simple hook-based plugin system, and theming is just PHP templates. A few plugins are bundled in this repo (like sitemap, redirects, and a feed plugin) so you can see the pattern and ship common features quickly.

- Community plugins: https://ava.addy.zone/plugins
- Community themes: https://ava.addy.zone/themes

## ⚡ Performance

Ava is designed to be blazing fast, whether you have 100 pages or 100,000:

- **Tiered caching**: avoid repeating expensive work on every request.
- **Page caching** (optional): serve cached HTML to bypass PHP for most visitors.
- **Switchable engines**: use the default binaries for best performance on most sites or seamlessly switch to SQLite for massive sites or low-memory environments.

See https://ava.addy.zone/docs/performance

## 🤝 Contributing & Community

If you’d like to contribute core code, open an issue first so we can agree on approach and scope. You can submit your own plugins, themes and websites directly to the docs showcase. 

Feedback and suggestions are always welcome! If you're trying Ava and face any friction, please open an issue or join the Discord and let us know.

- Bugs, questions, and ideas: https://github.com/avacms/ava/issues
- Chat & support: https://discord.gg/fZwW4jBVh5
- Community themes: https://ava.addy.zone/themes
- Community plugins: https://ava.addy.zone/plugins
- Sites built with Ava: https://ava.addy.zone/showcase

## 📄 License

**Ava CMS is provided as free, open-source software without warranty (GNU General Public License). It is under active development and may contain bugs or security issues. You are responsible for reviewing, testing, and securing any deployment.**

Copyright (c) 2025-2026 Adam Greenough

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program. If not, see [https://www.gnu.org/licenses/](https://www.gnu.org/licenses/).
