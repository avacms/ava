# Content Authoring

Content in Ava is Markdown files with YAML frontmatter. There's no database — your files are the source of truth.

## The Basics

Every piece of content is a `.md` file containing:

1. **Frontmatter** — YAML metadata between `---` fences
2. **Body** — Markdown content

```markdown
---
title: My Post Title
slug: my-post-title
status: published
date: 2024-12-28
---

# My Post Title

Your content goes here. Use standard Markdown syntax.
```

## File Location

Content lives in `content/`, organized by type:

```
content/
├── pages/           # Pages (hierarchical URLs)
│   ├── index.md     # Home page (/)
│   ├── about.md     # /about
│   └── services/
│       └── web.md   # /services/web
├── posts/           # Posts (/blog/{slug})
│   └── hello.md
└── _taxonomies/     # Term registries (optional)
    ├── category.yml
    └── tag.yml
```

The directory structure depends on your content type configuration. Pages typically use hierarchical URLs (folder = URL path), while posts use pattern-based URLs.

## Frontmatter Reference

### Required Fields

Every content file needs these:

| Field | Description | Example |
|-------|-------------|---------|
| `title` | Display title | `"My Post Title"` |
| `slug` | URL-safe identifier | `"my-post-title"` |
| `status` | Visibility status | `draft`, `published`, or `private` |

If you omit `slug`, Ava generates one from the filename.

### Common Fields

| Field | Description | Example |
|-------|-------------|---------|
| `id` | Unique identifier (ULID) | `"01JGMK..."` |
| `date` | Publication date | `2024-12-28` |
| `updated` | Last modified date | `2024-12-28` |
| `excerpt` | Summary for listings | `"A brief intro..."` |
| `template` | Override default template | `"custom-post"` |
| `draft` | Quick draft toggle | `true` |

### SEO Fields

| Field | Description |
|-------|-------------|
| `meta_title` | Custom `<title>` tag (falls back to `title`) |
| `meta_description` | Meta description for search engines |
| `noindex` | Set `true` to add `noindex` meta tag |
| `canonical` | Canonical URL for duplicate content |
| `og_image` | Open Graph image path |

### Taxonomy Assignment

Assign content to taxonomy terms:

```yaml
category:
  - tutorials
  - php
tag:
  - getting-started
  - cms
```

For hierarchical taxonomies:

```yaml
topic:
  - guides/basics
  - guides/advanced
```

## Redirects

When you move or rename content, set up redirects in the new file:

```yaml
redirect_from:
  - /old-url
  - /another-old-url
```

Requests to the old URLs will 301 redirect to the new location.

## Per-Item Assets

Load CSS or JS only on specific pages:

```yaml
assets:
  css:
    - "@uploads:2024/custom-post.css"
  js:
    - "@assets:interactive-chart.js"
```

## Path Aliases

Use aliases instead of hard-coded URLs. These are configured in `ava.php` and expanded at render time:

| Alias | Default Expansion |
|-------|-------------------|
| `@media:` | `/media/` |
| `@uploads:` | `/media/uploads/` |
| `@assets:` | `/assets/` |

Use in your Markdown:

```markdown
![Hero image](@uploads:2024/hero.jpg)

[Download PDF](@media:docs/guide.pdf)
```

This makes it easy to change asset locations later without updating every content file.

## Shortcodes

Embed dynamic content using shortcodes:

```markdown
Current year: [year]

Site name: [site_name]

[button url="/contact"]Contact Us[/button]

[snippet name="cta" heading="Join Us"]
```

See [Shortcodes](shortcodes.md) for the full reference.

## Content Status

| Status | Visibility |
|--------|------------|
| `draft` | Hidden from site. Viewable with preview token. |
| `published` | Visible to everyone. |
| `private` | Hidden from listings. Accessible via direct URL with preview token. |

## Creating Content

### Via CLI (Recommended)

```bash
# Create a page
./ava make page "About Us"

# Create a post
./ava make post "Hello World"
```

This creates a properly formatted file with:
- Generated ULID
- Slugified filename
- Date (for dated types)
- Draft status

### Manually

Create a `.md` file in the appropriate directory:

```bash
# content/posts/my-new-post.md
```

Add frontmatter and content, then save. If cache mode is `auto`, the site updates immediately.

## Validation

Run the linter to check all content:

```bash
./ava lint
```

This catches:
- Invalid YAML syntax
- Missing required fields
- Invalid status values
- Malformed slugs
- Duplicate slugs or IDs
