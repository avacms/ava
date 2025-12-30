---
id: 01JGMK0000ABOUT00000000001
title: About Ava
slug: about
status: published
---

# About Ava CMS

Ava (Addy's Very Adaptable CMS) is a **flat-file content management system** designed for people who love working with files, version control, and clean code. It's built for personal sites, blogs, portfolios, and small content sites where simplicity matters more than enterprise features.

## Our Philosophy

We believe the best tools are the ones you don't notice. Ava follows a few core principles:

### Git is the Source of Truth

Your content lives in Markdown files. History, versioning, and collaboration are handled by Git—the tool you already know and trust. No custom versioning system. No database exports. Just `git commit` and `git push`.

### The Filesystem is Trusted

Files are files. Folders are folders. There's no abstraction layer pretending the filesystem is something else. When you save `hello.md` in `content/posts/`, it becomes `/posts/hello` on your site. Simple.

### The CMS Gets Out of Your Way

No WYSIWYG editor fighting with your HTML. No media library when your OS has a perfectly good file browser. No "page builder" adding inline styles you have to override. Ava assumes you know what you're doing and trusts you to do it.

### Performance is a Feature

Ava uses a **two-layer caching system** that makes most requests complete in under a millisecond:

1. **Page cache** — Full HTML pages cached as files (GET requests only)
2. **Content cache** — Parsed Markdown and metadata cached as PHP arrays

The result? Your site is **fast** without any configuration, CDN, or optimization plugins.

## Who is Ava For?

Ava is perfect if you:

- Want to write content in **Markdown** with your favorite editor
- Prefer **Git** over admin panels for managing content
- Love **PHP templates** and want full control over your HTML
- Need a site that's **fast by default** without complex caching plugins
- Want something **simple** you can understand in an afternoon
- Value **clarity** over magic and conventions over configuration

## Who is Ava NOT For?

Ava might not be right if you:

- Need a visual page builder or drag-and-drop editor
- Want to give non-technical users a WordPress-like admin
- Need complex user management or membership features
- Require e-commerce or form builder plugins
- Want a massive plugin ecosystem

(Though honestly, you can build most of those things yourself if you want to!)

## Technical Stack

Ava is built with:

- **PHP 8.4+** — Modern PHP features, typed properties, enums
- **League CommonMark** — Standards-compliant Markdown parsing
- **Symfony YAML** — Configuration and frontmatter
- **igbinary** (optional) — Fast binary serialization for caching

No frameworks. No Composer bloat. The entire core is under 3,000 lines of readable code.

## Architecture Highlights

### Content Model

Everything in Ava is a "content item" with:
- **ID** — ULID-based unique identifier
- **Frontmatter** — YAML metadata (title, status, date, custom fields)
- **Body** — Markdown content
- **Type** — Post, page, or custom type
- **Taxonomies** — Categories, tags, or custom taxonomies

### Routing

Routes are defined in `app/config/ava.php`:

```php
'routes' => [
    '/' => ['type' => 'page', 'slug' => 'index'],
    '/posts' => ['type' => 'post', 'template' => 'archive.php'],
    '/posts/:slug' => ['type' => 'post'],
],
```

Clean. Explicit. No magic.

### Templating

Templates are plain PHP files in `themes/default/templates/`. The `$ava` object gives you everything you need:

```php
<?php include '_header.php'; ?>

<article>
  <h1><?= $ava->title() ?></h1>
  <time><?= $ava->date('F j, Y') ?></time>
  <?= $ava->content() ?>
</article>

<?php include '_footer.php'; ?>
```

No template language to learn. Just PHP.

## Security

Ava's page cache is designed with security in mind:

- **GET requests only** — POST/PUT/DELETE never cached
- **Query string exclusion** — Prevents cache poisoning (except safe UTM params)
- **Admin bypass** — Logged-in users never see cached pages
- **Safe defaults** — Cache disabled by default, opt-in only

Full details in the [caching documentation](https://ava.addy.zone/#/caching).

## Get Involved

Ava is open source and evolving! Here's how to learn more:

- **Documentation**: [ava.addy.zone](https://ava.addy.zone)
- **Source Code**: [github.com/adamgreenough/ava](https://github.com/adamgreenough/ava)
- **Issues & Ideas**: [GitHub Issues](https://github.com/adamgreenough/ava/issues)

We're building this for ourselves and people like us—developers who want simple, fast, file-based publishing without the weight of traditional CMSs.

---

**Now go build something awesome!** Start by editing this page (`content/pages/about.md`) or check out the [homepage](/) for next steps. 🚀
