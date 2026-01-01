---
id: 01JGMK0000ABOUT00000000001
title: About
slug: about
status: published
---

# About Ava CMS

Ava is a **flat-file content management system** built for developers and writers who value simplicity, speed, and control. It's designed for personal sites, blogs, portfolios, and documentation—anywhere you want power without complexity.

---

## Philosophy

### Files Are the Source of Truth

Your content lives in Markdown files on your filesystem. There's no database to maintain, no export to worry about, no vendor lock-in. Back up your content however you like—copy a folder, sync to the cloud, or commit to Git.

### Instant Feedback

Edit a file, save it, refresh your browser—and see your changes immediately. No build step, no deploy queue, no cache invalidation dance. Ava gets out of your way so you can focus on your content.

### Developer-Friendly

Templates are plain PHP. Configuration is PHP arrays. The entire core is under 3,000 lines of readable code. If you want to understand how something works, you can read it in an afternoon.

### Performance by Default

Ava uses two-layer caching to serve pages in under a millisecond:

1. **Page cache** — Full HTML pages stored as static files
2. **Content index** — Parsed metadata in a fast binary format

Your site is fast without CDNs, optimization plugins, or tuning.

---

## Who is Ava For?

Ava is perfect if you:

- Write content in **Markdown** with your favorite editor
- Want full control over your **HTML and CSS**
- Need a site that's **fast by default**
- Value **clarity and simplicity** over feature bloat
- Want to **understand** the tools you use

Ava might not be right if you need a visual page builder, a WordPress-like admin for non-technical users, or complex e-commerce out of the box.

---

## The Tech Stack

| Component | Technology |
|-----------|------------|
| Language | PHP 8.3+ |
| Markdown | League CommonMark |
| Config | Symfony YAML |
| Caching | Binary index + static HTML |

No frameworks. No JavaScript build tools. Just PHP.

---

## Get Involved

- 📚 **[Documentation](https://ava.addy.zone)** — Learn everything about Ava
- 💻 **[GitHub](https://github.com/adamgreenough/ava)** — Source code and issues
- 💬 **[Discord](https://discord.gg/Z7bF9YeK)** — Join the community

---

**Ready to start?** Head back to the [homepage](/) and follow the quick start guide. 🚀
