---
title: 'Hello World'
slug: hello-world
status: published
date: '2026-01-01'
updated: '2026-01-01'
category: general
tag:
  - welcome
  - tutorial
excerpt: 'Welcome to your new Ava site! This sample post shows you how content works and what you can do with Markdown.'
---

Welcome to your new Ava site! 🎉

This is a sample blog post that demonstrates how content works in Ava. Feel free to edit or delete it once you're ready to start creating your own content.

## How Posts Work

Every post is a Markdown file stored in `content/posts/`. The filename doesn't matter for URLs—Ava uses the `slug` from the frontmatter to create clean, predictable URLs.

The **frontmatter** at the top of each file (between the `---` markers) contains metadata:

| Field | Purpose |
|-------|---------|
| `title` | The post title |
| `slug` | URL path (this post is at `/blog/hello-world`) |
| `date` | Publication date for sorting |
| `status` | `published` or `draft` |
| `excerpt` | Summary for listings and SEO |
| `category` | Categories for organization |
| `tag` | Tags for cross-referencing |

## Writing in Markdown

Ava supports standard Markdown syntax. Here are some examples:

### Text Formatting

You can write **bold text**, *italicized text*, and `inline code`. You can also create [links to other pages](/) or [external sites](https://ava.addy.zone).

### Code Blocks

Syntax highlighting works automatically for code blocks:

```php
// Query recent posts in your templates
$posts = $ava->query()
    ->type('post')
    ->published()
    ->orderBy('date', 'desc')
    ->perPage(5)
    ->get();

foreach ($posts as $post) {
    echo $post->title();
}
```

### Blockquotes

> "Simplicity is the ultimate sophistication."  
> — Leonardo da Vinci

### Lists

Organize content with lists:

- Posts live in `content/posts/`
- Pages live in `content/pages/`
- Custom content types are configured in `app/config/content_types.php`

Or numbered lists:

1. Write your content in Markdown
2. Save the file
3. Refresh your browser

## Built-in Shortcodes

Ava includes shortcodes for dynamic content:

- **Current year:** [year]
- **Site name:** [site_name]

You can create your own shortcodes too—check the [documentation](https://ava.addy.zone/docs/shortcodes).

## What's Next?

1. **Edit this post** — Change the title, add your own content
2. **Create a new post** — Run `./ava make post "Your Post Title"`
3. **Explore the theme** — Check out `themes/default/` to see how templates work
4. **Read the docs** — Visit [ava.addy.zone/docs](https://ava.addy.zone/docs) for guides and reference

Happy publishing! 📝
