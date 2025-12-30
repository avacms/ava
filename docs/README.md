# Addy's (very adaptable) CMS

[![Release](https://img.shields.io/github/v/release/adamgreenough/ava)](https://github.com/adamgreenough/ava/releases)
[![Issues](https://img.shields.io/github/issues/adamgreenough/ava)](https://github.com/adamgreenough/ava/issues)
[![Code size](https://img.shields.io/github/languages/code-size/adamgreenough/ava)](https://github.com/adamgreenough/ava)
[![Discord](https://img.shields.io/discord/1028357262189801563)](https://discord.gg/Z7bF9YeK)
[![GitHub Repo stars](https://img.shields.io/github/stars/adamgreenough/ava)](https://github.com/adamgreenough/ava)


A friendly, flexible, flat-file PHP-based CMS for bespoke personal websites, blogs and more.

## Philosophy

Ava is designed for people who love the web. It sits in the sweet spot between a static site generator and a full-blown CMS:

- **📂 Your Files, Your Rules.** Content is just Markdown. Configuration is readable PHP. Your files are the source of truth—back them up however you like, and you own your data forever.
- **✍️ Bring Your Own Editor.** No clunky WYSIWYG editors here. Write in VS Code, Obsidian, or Notepad. If you can write HTML and CSS, you can build a theme.
- **🚀 No Database Required.** Ava indexes your content into fast PHP arrays. You get the speed of a static site with the dynamic power of PHP.
- **⚡ Edit Live.** Change a file, hit refresh, and see it instantly. No build steps required.
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
| **Speed** | Built-in page caching makes your site load instantly, even on cheap hosting. |

## Performance

Ava is built for speed. Most sites have under 1,000 posts—here's what you can expect:

| Posts | Archive Page | Single Post | Cached Page |
|-------|--------------|-------------|-------------|
| 100 | 3ms | 5ms | <1ms |
| 1,000 | 3ms | 8ms | <1ms |

Archive pages stay fast regardless of content size thanks to tiered caching. **Cached pages serve in under 1 millisecond**—faster than most static site generators can serve pre-built files.

Ava gives you the serving speed of static sites with the instant publishing of a dynamic CMS. Edit a file, refresh, see it live—no waiting for builds.

[Full benchmarks, memory usage, and igbinary comparison →](caching.md#performance)


## Requirements

<img src="https://addy.zip/ava/i-love-php.webp" alt="I love PHP" style="float: right; width: 180px; margin: 0 0 1rem 1.5rem;" />

Ava requires **PHP 8.3** or later and **SSH access** for some simple commands. Most good hosts include this, but check before you start.

**Required Extensions:**

- `mbstring` — UTF-8 text handling
- `json` — Config and API responses
- `ctype` — String validation

These are bundled with most PHP installations. If you're missing one, your host's control panel or `apt install php-mbstring` will sort it out.

**Optional Extensions:**

- `igbinary` — Faster content index (15× faster, 90% smaller)
- `opcache` — Opcode caching for production
- `gd` or `imagick` — Image processing if you add it later

If `igbinary` isn't available, Ava falls back to PHP's built-in `serialize`. The system auto-detects which format was used when reading index files.

## Quick Start

Getting started with Ava is incredibly simple and the default set-up can be put live in just a minute. Here are a few options:

### Download and Upload

The simplest approach—no special tools required:

[![Release](https://img.shields.io/github/v/release/adamgreenough/ava)](https://github.com/adamgreenough/ava/releases)

1. Download the latest release from [GitHub Releases](https://github.com/adamgreenough/ava/releases)
2. Extract the ZIP file
3. Upload to your web host (via SFTP, your host's file manager, or however you prefer)
4. Run `composer install` to install dependencies
5. Configure your site by editing `app/config/ava.php`
6. Run `./ava rebuild` to build the content index
7. Visit your site!

### Clone with Git

If you're comfortable with Git and want version control from the start:

```bash
# 1. Clone the repo
git clone https://github.com/adamgreenough/ava.git mysite
cd mysite

# 2. Install dependencies
composer install

# 3. Configure your site by editing app/config/ava.php

# 4. Check status (shows PHP version and extensions)
./ava status

# 5. Build the content index
./ava rebuild
```

### Local Development (Optional)

If you want to preview your site on your own computer before going live:

```bash
php -S localhost:8000 -t public
```

Then visit [http://localhost:8000](http://localhost:8000) in your browser.

<div class="beginner-box">

### Ready for Production?

See the [Hosting Guide](hosting.md) for shared hosting, VPS options, and deployment tips.

</div>

### Default Site

By default, Ava comes with a simple example site. You can replace the content in the `content/` folder and your theme in the `themes/default/` folder to start building your site.

<img src="images/default.webp" alt="Default theme preview" style="border: 1px solid #e5e5e5;" />

The default theme provides a clean, minimal starting point for your site. Customize it with your own styles, scripts and templates to match your vibe.

## Project Structure

```
mysite/
├── app/
│   ├── config/          # Configuration files
│   │   ├── ava.php      # Main config (site, paths, caching)
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
├── storage/cache/       # Content index and page cache (gitignored)
└── ava                  # CLI tool
```

## How It Works

1. **Write** — Create Markdown files in your `content/` folder.
2. **Index** — Ava automatically scans your files and builds a fast index.
3. **Render** — Your theme turns that content into beautiful HTML.

The system handles all the boring stuff: routing, sorting, pagination, and search. You just focus on the content and the design.

## Editing Content: Pick Your Style

Ava is flexible about *how* you work. There's no "correct" way to edit—pick whatever fits your workflow:

- **Edit directly on your server** — SFTP, SSH, or your host's file manager. Changes appear instantly.
- **Work locally** — Edit on your computer and upload when ready. Great for bigger changes.
- **Use Git** — Version control with GitHub, GitLab, etc. Perfect for collaboration and history.
- **Mix and match** — Quick fixes on the server, bigger projects locally. Whatever works for you.

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
- [Hosting](hosting.md) — Getting your site live
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

