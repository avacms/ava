# Writing Content

Content in Ava is just text. You write in [Markdown](https://www.markdownguide.org/basic-syntax/), which is a simple way to format text, and save it as a file. There's no database to manage—your files are your content. 

Ava can handle any combination of Markdown and standard HTML, even within the same file. You can also embed safe and reusable PHP snippets using [Shortcodes](https://ava-dev.addy.zone/docs/#/shortcodes) for absolute flexibility.

<div class="beginner-box">

## What is Markdown?

Markdown is a lightweight way to format text using plain characters.

- You write readable text.
- You sprinkle in simple symbols for headings, links, lists, and code.
- Mix in your own custom HTML if required for advanced styling.
- Ava (and your theme) turns it into HTML.

### A tiny Markdown cheat-sheet

```markdown
# Heading 1
## Heading 2

**bold** and *italic*

- bullet item
1. numbered item

[a link](https://example.com)

`inline code`

```php
// a code block
echo 'Hello';
```‎ 
```

[View full Markdown reference](https://www.markdownguide.org/basic-syntax/)

### Markdown Editors (use what you like)

You can write Ava content in almost anything:

- **Code editors:** VS Code, Sublime Text, PhpStorm
- **Markdown-focused apps:** Obsidian, Typora, MarkText, iA Writer, Zettlr
- **In the browser:** GitHub’s built-in editor (edit a `.md` file on GitHub), or tools like StackEdit

There’s no “correct” editor. If you like writing in a notes app and then committing to Git, that works. If you like editing on the server over SSH, that works too.

### Frontmatter vs Markdown (two different things)

Each content file has:

- **Frontmatter (YAML)** between `---` lines: structured metadata
- **Body (Markdown)**: the actual writing

**Note:** YAML is sensitive to indentation. If something breaks, it’s often a missing space or an unclosed quote in frontmatter. Running `./ava lint` is the fastest way to get a clear error message.

</div>

## The Basics

Every piece of content is a `.md` file with two parts:

1. **💌 Frontmatter** — Metadata about the content (like title, date, status) at the top. Think of it like the address on an envelope.
2. **📝 Body** — The actual content, written in Markdown.

```markdown
---
title: My First Post
slug: my-first-post
status: published
date: 2024-12-28
---

# Hello World

This is my first post. I can use **bold**, *italics*, and [links](https://example.com).
```

**Tip:** You can keep drafts forever. Set `status: draft` while writing, then switch to `published` when you’re happy.

## Creating Content

### Via CLI (Recommended)


```bash
./ava make <type> "Title"
```

Examples:

```bash
./ava make page "About Us"
./ava make post "Hello World"
```

<pre><samp>  <span class="t-green">╭───────────────────────────╮
  │  Created new post!        │
  ╰───────────────────────────╯</span>

  <span class="t-dim">File:</span>       <span class="t-white">content/posts/hello-world.md</span>
  <span class="t-dim">ID:</span>         <span class="t-white">01JGHK8M3Q4R5S6T7U8V9WXYZ</span>
  <span class="t-dim">Slug:</span>       <span class="t-cyan">hello-world</span>
  <span class="t-dim">Status:</span>     <span class="t-yellow">draft</span>

  <span class="t-yellow">💡 Tip:</span> Edit your content, then set status: published when ready</samp></pre>

Run without arguments to see available types:

```bash
./ava make
```

<pre><samp>  <span class="t-red">✗</span> Usage: ./ava make &lt;type&gt; "Title"

  <span class="t-bold">Available types:</span>

    <span class="t-cyan">▸ page</span> <span class="t-dim">— Pages</span>
    <span class="t-cyan">▸ post</span> <span class="t-dim">— Posts</span>

  <span class="t-bold">Example:</span>
    <span class="t-dim">./ava make post "My New Post"</span></samp></pre>

This creates a properly formatted file with:
- Generated ULID
- Slugified filename
- Date (for dated types)
- Draft status

**Beginner's need not worry**: the CLI isn’t “advanced mode” — it’s just a helper that saves you from remembering boilerplate and file naming.

### Manually

Create a `.md` file in the appropriate directory:

```bash
# content/posts/my-new-post.md
```

Add frontmatter and content, then save. If cache mode is `auto`, the site updates immediately.

## Organizing Your Files

Content lives in the `content/` folder. You can organize it however you like, but typically it looks like this:

```
content/
├── pages/           # Standard pages like About or Contact
│   ├── index.md     # Your homepage
│   ├── about.md     # /about
│   └── services/
│       └── web.md   # /services/web
├── posts/           # Blog posts
│   └── hello.md     # /blog/hello
└── _taxonomies/     # Categories and Tags
    ├── category.yml
    └── tag.yml
```

**Tip:** For pages, folder structure usually maps nicely to URLs. For example `content/pages/services/web.md` becomes `/services/web`.

## Frontmatter Guide

Frontmatter is just a list of settings for your page. It goes between two lines of `---`.

### Essential Fields

| Field | What it does | Example |
|-------|-------------|---------|
| `title` | The name of your page or post. | `"My Post Title"` |
| `slug` | The URL-friendly name. If you leave this out, Ava makes one for you! | `"my-post-title"` |
| `status` | Controls visibility. Use `draft` while writing. | `draft`, `published` |

### Useful Extras

| Field | What it does | Example |
|-------|-------------|---------|
| `date` | When this was published. | `2024-12-28` |
| `excerpt` | A short summary for lists and search results. | `"A brief intro..."` |
| `template` | Use a specific layout for this page. | `"custom-post"` |

### SEO Superpowers

Ava handles the technical SEO stuff for you, but you can override it:

| Field | Description |
|-------|-------------|
| `meta_title` | Custom title for search engines (defaults to your Title). |
| `meta_description` | Description for search results. |
| `noindex` | Set to `true` to hide this page from search engines. |
| `og_image` | Image to show when shared on social media. |

### Organizing with Taxonomies

You can tag and categorize your content easily:

```yaml
category:
  - tutorials
  - php
tag:
  - getting-started
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

## Previewing Your Site Locally

If you’re working on your own machine, the simplest preview is PHP’s built-in dev server:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000` in your browser.

> **Tip:** This is a development server only — for real hosting you’ll use Apache/Nginx (or your host’s PHP setup).

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
