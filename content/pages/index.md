---
id: 01JGMK0000HOMEPAGE00000001
title: Home
slug: index
status: published
template: page.php
---

# Welcome to Ava! 👋

You did it! Ava is up and running. This is your homepage, and you're already looking at content rendered from a simple Markdown file.

## What is Ava?

Ava (Addy's Very Adaptable CMS) is a **flat-file CMS** built for people who love the web and want to create without the complexity of traditional CMSs. No databases. No build steps. Just files, Markdown, and PHP.

### Why You'll Love It

- **Write in Markdown** — Your content lives in `content/` as plain `.md` files
- **Blazingly Fast** — Two-layer caching (page + content) means sub-millisecond response times
- **Developer-Friendly** — Clean PHP code, no framework magic, easy to understand
- **Git-First** — Version control is built into your workflow, not bolted on
- **Zero Complexity** — No npm, no build tools, no webpack configs to fight with

## Your First Steps

Ready to make this site your own? Here's where to start:

### 1. Create Your First Page

Open your editor and create a new file in `content/pages/`:

```markdown
---
title: My New Page
slug: my-page
status: published
---

# Hello from my new page!

This is **so easy**.
```

Save it as `my-page.md`, then visit `/my-page` in your browser. That's it!

### 2. Start Blogging

Want to write a blog post? Just create a file in `content/posts/`:

```markdown
---
title: My First Blog Post
slug: my-first-post
date: 2024-12-28
status: published
excerpt: A quick intro to my post
category:
  - personal
---

# My awesome blog post

Content goes here!
```

Posts automatically show up in your blog feed at `/posts`.

### 3. Customize Your Theme

This is where it gets fun! All your templates live in `themes/default/templates/`. Open `page.php` or `post.php` and you'll see clean, simple PHP:

```php
<?php include '_header.php'; ?>

<article>
  <h1><?= $ava->title() ?></h1>
  <?= $ava->content() ?>
</article>

<?php include '_footer.php'; ?>
```

The `$ava` object gives you access to everything:
- `$ava->title()` — The page title
- `$ava->content()` — Your rendered Markdown
- `$ava->excerpt()` — Post excerpt
- `$ava->date()` — Publication date
- `$ava->url()` — Current page URL

**Pro tip:** Check out the [theming documentation](https://ava.addy.zone/#/themes) for the full list of template helpers!

### 4. Use the CLI

Ava includes a command-line tool that makes life easier:

```bash
# See your site status
php bin/ava status

# Clear all caches
php bin/ava cache:clear

# Get page cache stats
php bin/ava pages:stats

# Validate your content
php bin/ava lint
```

### 5. Add Some Flair with Shortcodes

Shortcodes let you add dynamic content anywhere in your Markdown:

```markdown
The current year is [year], and this site is called [site_name]!
```

You can create your own shortcodes in `app/shortcodes.php`. Want a "call to action" button? Add this:

```php
$shortcodes->add('cta', function($args) {
    $text = $args['text'] ?? 'Click me';
    $url = $args['url'] ?? '#';
    return "<a href='$url' class='cta-button'>$text</a>";
});
```

Then use it: `[cta text="Get Started" url="/about"]`

## Need Help?

- **Documentation**: [ava.addy.zone](https://ava.addy.zone)
- **Code**: [github.com/adamgreenough/ava](https://github.com/adamgreenough/ava)
- **Philosophy**: Read the [About](https://ava.addy.zone/#/?id=main) page to understand Ava's approach

## What's Next?

This is just the beginning! Here are some ideas:

- Customize the CSS in `themes/default/assets/style.css`
- Add new content types in `app/config/content_types.php`
- Create custom taxonomies (tags, categories, series, etc.)
- Install plugins from `plugins/` or create your own
- Hook into events with `app/hooks.php`

**Most importantly:** Have fun! Ava is designed to get out of your way so you can focus on creating great content and building the site you've always wanted.

Happy building! 🚀
