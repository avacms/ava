---
title: Home
status: published
order: 1
excerpt: Congratulations! Your site is up and running. This is your homepage, rendered from a simple Markdown file.
---

# Welcome to Ava 👋

Congratulations! Your site is up and running. This is your homepage, rendered from a simple Markdown file at `content/pages/index.md`.

---

## What is Ava?

Ava is a **flat-file CMS** built for developers and writers who love working with files. No databases, no build steps—just Markdown files, PHP templates, and blazing-fast caching. 

### Why you'll love it

- **📝 Your Editor, Your Way** — Write in any editor. Content is just Markdown.
- **⚡ Instant Publishing** — Edit, save, refresh. No build queues.
- **🎨 Full HTML Control** — Templates are PHP. No framework to fight.
- **🔍 Built-in Search** — Dynamic search works out of the box.
- **📦 Truly Portable** — Back up with `cp -r`. Version with Git.

---

## Quick Start Guide

### 1. Create Your First Page

Add a new file at `content/pages/contact.md`:

```markdown
---
title: Contact
status: published
---

# Get in Touch

Drop us a line at hello@example.com
```

Save it and visit `/contact`. That's all it takes!

### 2. Write a Blog Post

Create `content/posts/my-first-post.md`:

```markdown
---
title: My First Post
date: 2025-01-01
status: published
---

# Hello World

This is my first blog post with Ava.
```

Your post will appear at `/blog/my-first-post`.

> **Tip:** Use the CLI for faster content creation: `./ava make post "My First Post"`

### 3. Customize Your Theme

Templates live in `themes/default/templates/`. They're just PHP with a powerful helper:

```php
<article>
    <h1><?= $ava->e($content->title()) ?></h1>
    <?= $ava->body($content) ?>
</article>
```

Check out the theme files—they're fully commented to help you learn!

---

## Learn More

- 📚 **[Documentation](https://ava.addy.zone/docs)** — Complete guides and reference
- 🗳️ **[GitHub](https://github.com/avacms/ava)** — Source code and issues
- 💬 **[Discussions](https://github.com/orgs/avacms/discussions)** — Community and support

---

**Ready to build something great?** Start by editing this page, then explore the [blog](/blog) and [about](/about) pages. Make Ava yours! 🚀
