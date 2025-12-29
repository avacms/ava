---
id: 01JGMK0000POST0000000000001
title: Welcome to Your New Blog! 🎉
slug: hello-world
status: published
date: 2024-12-28
excerpt: Your first post on Ava. Learn how to create content, customize themes, and make this site your own.
category:
  - getting-started
tag:
  - welcome
  - tutorial
  - theming
---

Congratulations! You've just set up Ava, and this is your first blog post. You're looking at content that lives in a simple Markdown file at `content/posts/hello-world.md`.

Note: The "blog" is completely optional — Ava doesn't force you into a single structure. "Posts" are a flexible content type you can configure for anything. Here are some ideas you could use posts for:

- Recipes
- Link lists / linkblog
- Changelogs / release notes
- Quick notes or microblogging
- Documentation entries or how-tos
- Portfolio items or case studies
- Newsletters or announcements
- Podcast show notes
- Product updates

Ava is freeform: create custom content types and templates to match your needs. Now, let's explore what you can do with it!

## Creating More Posts

Want to write another post? It's as simple as creating a new `.md` file in the `content/posts/` directory. Here's the basic structure:

```markdown
---
title: My Awesome Post
slug: my-awesome-post
date: 2024-12-28
status: published
excerpt: A short description of what this post is about
category:
  - personal
tag:
  - life
  - thoughts
---

# Your content starts here!

Write anything you want in **Markdown**.
```

That's it! Save the file, refresh your browser, and your post appears.

### Frontmatter Fields You Can Use

The YAML section at the top (between the `---` lines) is called "frontmatter." Here are the fields you can use:

- `title` — The post title (shows up in `<h1>` and `<title>`)
- `slug` — The URL-friendly version (e.g., `my-post` becomes `/posts/my-post`)
- `date` — Publication date (format: `YYYY-MM-DD`)
- `status` — Either `published` or `draft`
- `excerpt` — Short description for archives and meta tags
- `category` — Array of categories (e.g., `- tech`, `- personal`)
- `tag` — Array of tags (e.g., `- tutorial`, `- guide`)
- `template` — Override the default template (optional)

You can also add **custom fields** for anything you need!

## Theming Your Site

This is where the magic happens. All your templates live in `themes/default/templates/`. Let's look at what's available:

### The Main Templates

- **`page.php`** — Used for pages like "About" or "Contact"
- **`post.php`** — Used for individual blog posts (like this one!)
- **`archive.php`** — Shows lists of posts (your blog homepage)
- **`_header.php`** — Site header (logo, navigation, meta tags)
- **`_footer.php`** — Site footer (copyright, links, scripts)

### The `$ava` Object

Inside your templates, you have access to the `$ava` object, which gives you everything you need about the current page:

```php
// Basic content
<?= $ava->title() ?>          // Post title
<?= $ava->content() ?>        // Rendered HTML from Markdown
<?= $ava->excerpt() ?>        // Short description

// Metadata
<?= $ava->date('F j, Y') ?>   // Formatted date
<?= $ava->url() ?>            // Full URL to this page
<?= $ava->id() ?>             // Unique content ID

// Taxonomies
<?= $ava->category() ?>       // First category
<?php foreach ($ava->categories() as $cat): ?>
  <?= $cat ?>                 // Loop through all categories
<?php endforeach; ?>

// Custom fields
<?= $ava->meta('author') ?>   // Get custom frontmatter field
```

### Querying Content

Want to show a list of posts? Use the `$ava->query()` helper:

```php
// Get 5 most recent posts
$posts = $ava->query('post')
    ->where('status', 'published')
    ->orderBy('date', 'desc')
    ->limit(5)
    ->get();

foreach ($posts as $post):
?>
  <article>
    <h2><a href="<?= $post->url() ?>"><?= $post->title() ?></a></h2>
    <p><?= $post->excerpt() ?></p>
  </article>
<?php endforeach; ?>
```

**Full Query API:**
- `->where($field, $value)` — Filter by field
- `->orderBy($field, 'asc|desc')` — Sort results
- `->limit($n)` — Limit number of results
- `->offset($n)` — Skip first N results
- `->get()` — Execute query and return results
- `->first()` — Get just the first result
- `->count()` — Count matching items

Check out the [API documentation](https://ava.addy.zone/api.html) for more!

## Using Shortcodes

Shortcodes are little snippets you can use in your Markdown to add dynamic content. Try these:

**Current year:** [year]

**Site name:** [site_name]

**Site URL:** [site_url]

### Creating Your Own Shortcodes

Want to make your own? Open `app/shortcodes.php` and add them:

```php
$shortcodes->add('cta', function($args) {
    $text = $args['text'] ?? 'Click me';
    $url = $args['url'] ?? '#';
    return "<a href='$url' class='cta-button'>$text</a>";
});
```

Then use it in your content:

```markdown
[cta text="Read More" url="/about"]
```

## Helpful CLI Commands

Ava includes a command-line tool to make your life easier. Open your terminal and try these:

```bash
# Check your site status
php bin/ava status

# Clear all caches
php bin/ava cache:clear

# See page cache statistics
php bin/ava pages:stats

# Clear just the page cache
php bin/ava pages:clear

# Validate your content (check for errors)
php bin/ava lint

# List all content
php bin/ava content:list

# Find content by query
php bin/ava content:find "my search term"
```

These commands are your friends! Use them often.

## Customizing Styles

Want to change how things look? Edit the CSS in `themes/default/assets/style.css`. It's plain CSS—no preprocessors, no build steps.

```css
/* Make links a different color */
a {
  color: #0066cc;
  text-decoration: none;
}

a:hover {
  text-decoration: underline;
}
```

Reload your browser and see the changes instantly.

## Adding Custom Fields

Need extra metadata for your posts? Just add it to the frontmatter:

```markdown
---
title: My Post
author: Jane Doe
reading_time: 5 minutes
featured_image: /media/hero.jpg
---
```

Then access it in your template:

```php
<p>By <?= $ava->meta('author') ?></p>
<p>Reading time: <?= $ava->meta('reading_time') ?></p>
<img src="<?= $ava->meta('featured_image') ?>" alt="">
```

## What's Next?

Here are some ideas to explore:

1. **Customize the homepage** — Edit `content/pages/index.md`
2. **Style your theme** — Edit `themes/default/assets/style.css`
3. **Add navigation** — Update `themes/default/templates/_header.php`
4. **Create custom content types** — Check out `app/config/content_types.php`
5. **Add taxonomies** — Define new ones in `app/config/taxonomies.php`
6. **Install plugins** — Explore `plugins/sitemap` and `plugins/feed`
7. **Hook into events** — Use `app/hooks.php` to customize behavior

## Need Help?

- **Documentation**: [ava.addy.zone](https://ava.addy.zone) has comprehensive guides
- **API Reference**: [ava.addy.zone/api.html](https://ava.addy.zone/api.html) for all template functions
- **Source Code**: [github.com/addyosmani/ava](https://github.com/addyosmani/ava) if you want to see how it works

---

**Now it's your turn!** Delete this post, create your own content, and make this site yours. Ava is here to help you build exactly what you want—no more, no less.

Happy creating! 🚀
