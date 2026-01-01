# Writing Content

Content in Ava is just text. You write in [Markdown](https://www.markdownguide.org/basic-syntax/), which is a simple way to format text, and save it as a file. There's no database to manage—your files are your content. 

Ava can handle any combination of Markdown and standard HTML, even within the same file. You can also embed safe and reusable PHP snippets using [Shortcodes](shortcodes.md) for absolute flexibility.

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
- **In the browser:** StackEdit, your web host's file manager, or GitHub's editor if you're using Git

There's no "correct" editor. If you like writing in a notes app and uploading later, that works. If you like editing on the server over SSH, that works too.

### Frontmatter vs Markdown (two different things)

Each content file has:

- **Frontmatter (YAML)** between `---` lines: structured metadata
- **Body (Markdown)**: the actual writing

**Note:** YAML is sensitive to indentation. If something breaks, it’s often a missing space or an unclosed quote in frontmatter. Running [`./ava lint`](cli.md?id=lint) is the fastest way to get a clear error message.

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

You can keep drafts forever. Set `status: draft` while writing, then switch to `published` when you’re happy.

## Creating Content

### Via CLI (Recommended)

Use the [`make`](cli.md?id=make) command:

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

?> **Beginner's need not worry**: the CLI isn’t “advanced mode”, it’s just a helper that saves you from remembering boilerplate and file naming. It's a great way to dip your toes in to command-line tools without getting overwhelmed.

### Manually

Create a `.md` file in the appropriate directory:

```bash
# content/posts/my-new-post.md
```

Add frontmatter and content, then save. If cache mode is `auto`, the site updates immediately.

## Organising Your Files

Content lives in the `content/` folder. You can organise it however you like, but typically it looks like this:

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

For pages, folder structure usually maps nicely to URLs. For example `content/pages/services/web.md` becomes `/services/web`.

## Frontmatter Reference

Frontmatter is metadata about your content, written in [YAML](https://yaml.org/) between two `---` lines at the top of your file. Think of it as the "settings" for each page.

### Full Example

Here's a complete example showing all the common fields:

```yaml
---
id: 01JGMK0000POST0000000000001
title: My Blog Post
slug: my-blog-post
status: published
date: 2024-12-28
excerpt: A short summary for listings and search results.
template: custom-post.php
category:
  - tutorials
  - php
tag:
  - beginner
meta_title: SEO-Optimised Title
meta_description: Description for search engines.
og_image: "@media:2024/social-card.jpg"
noindex: false
cache: true
redirect_from:
  - /old-url
  - /another-old-url
assets:
  css:
    - "@media:css/custom-post.css"
  js:
    - "@media:js/interactive.js"
---

Your Markdown content goes here...
```

### Core Fields

These fields are used by Ava to manage and display your content.

| Field | Required | Description |
|-------|----------|-------------|
| `id` | Auto | Unique identifier (auto-generated when using `./ava make`). Don't edit this. |
| `title` | Yes | The title of your page or post. |
| `slug` | Yes | URL-friendly name. If omitted, Ava generates one from the title. |
| `status` | Yes | Visibility: `draft`, `published`, or `unlisted`. |
| `date` | For posts | Publication date in `YYYY-MM-DD` format. |
| `excerpt` | No | Short summary for listings, search results, and feeds. |
| `template` | No | Use a specific template (e.g., `landing.php`). Overrides the default. |

### Taxonomy Fields

Assign content to categories, tags, or any taxonomy defined in [taxonomies.php](configuration.md?id=taxonomies-taxonomiesphp).

```yaml
category:
  - tutorials
  - php
tag:
  - getting-started
  - beginner
```

You can use a single value or a list. Terms are created automatically if they don't exist.

### SEO Fields

Control how your content appears in search engines and social media.

| Field | Description |
|-------|-------------|
| `meta_title` | Custom title for search engines. Defaults to `title`. |
| `meta_description` | Description shown in search results. |
| `noindex` | Set to `true` to hide from search engines. |
| `og_image` | Image URL for social media sharing. |

### Behaviour Fields

Fine-tune how Ava handles this specific piece of content.

| Field | Description |
|-------|-------------|
| `cache` | Set to `false` to disable page caching for this URL. |
| `redirect_from` | Array of old URLs that should 301 redirect here. |

### Per-Item Assets

Load CSS or JS only on specific pages:

```yaml
assets:
  css:
    - "@media:css/custom-post.css"
  js:
    - "@media:js/interactive.js"
```

### Custom Fields

You can add **any custom fields** you like! They're accessible in your templates via `$item->get('field_name')`.

```yaml
---
title: Team Member
slug: jane-doe
status: published
# Custom fields:
role: Lead Developer
website: "https://janedoe.com"
featured: true
---
```

In your template:
```php
<h1><?= $ava->e($item->title()) ?></h1>
<p>Role: <?= $ava->e($item->get('role')) ?></p>
<?php if ($item->get('featured')): ?>
    <span class="badge">Featured</span>
<?php endif; ?>
```

You can also define expected fields for a content type in [content_types.php](configuration.md?id=content-types-content_typesphp) using the `fields` option—useful for documentation and validation.

## Redirects

When you move or rename content, set up redirects in the new file:

```yaml
redirect_from:
  - /old-url
  - /another-old-url
```

Requests to the old URLs will 301 redirect to the new location.

## Per-Item Assets (Art-Directed Posts)

For art-directed blogging, you can load custom CSS or JS on specific pages. Put your files in `public/media/` and reference them:

```yaml
assets:
  css:
    - "@media:css/my-styled-post.css"
  js:
    - "@media:js/interactive-chart.js"
```

Your theme must include `<?= $ava->itemAssets($item) ?>` in the `<head>` for these to load (the default theme already does this).

## Where to Put Images and Media

<div class="beginner-box">
<strong>Start here</strong>

- Put user-facing images and files in <code>public/media/</code> (use the <code>@media:</code> alias).
- Theme assets are handled by your theme’s asset pipeline/helpers; don’t drop theme CSS/JS here.
- Keep content (Markdown) in <code>content/</code>; reference media via aliases so paths stay clean.

Project snapshot:

```text
public/
  media/         # your images, PDFs, downloads
  index.php
content/         # your Markdown pages/posts
```

Example Markdown:

```markdown
![Team photo](@media:team/group.jpg)

[Download PDF](@media:docs/guide.pdf)
```
</div>

## Path Aliases

Use aliases instead of hard-coded URLs. These are configured in [`ava.php`](configuration.md#path-aliases) and expanded at render time:

| Alias | Default Expansion | Use For |
|-------|-------------------|--------|
| `@media:` | `/media/` | Images, downloads, per-post CSS/JS |

You can add custom aliases in your config (e.g., `@cdn:` for a CDN URL).

Use in your Markdown:

```markdown
![Hero image](@media:images/hero.jpg)

[Download PDF](@media:docs/guide.pdf)
```

This makes it easy to change asset locations later without updating every content file.

## Shortcodes

Embed dynamic content using shortcodes:

```markdown
Current year: [year]

Site name: [site_name]

Include snippet: [snippet name="cta" heading="Join Us"]
```

See [Shortcodes](shortcodes.md) for the full reference.

## Content Status

| Status | Visibility |
|--------|------------|
| `draft` | Hidden from site. Viewable with preview token. |
| `published` | Visible to everyone. Appears in listings and archives. |
| `unlisted` | Not in listings/archives. Accessible via direct URL (no token needed). |

## Previewing Your Site

Ava is flexible—you can edit directly on a live server, work locally and deploy, or any combination that suits your workflow.

<div class="beginner-box">

## Choosing Your Workflow

There's no single "right" way to work with Ava. Here are some common approaches:

### Option 1: Edit Directly on Your Server

The simplest approach—just edit files on your web server.

**How:** Use SFTP (FileZilla, Cyberduck, WinSCP), your host's file manager, or SSH.

**Pros:**
- No extra setup—changes are live immediately
- Great for quick fixes and content updates
- Works from any computer

**Cons:**
- No undo if you break something (make backups!)
- Slower if you're making lots of changes

### Option 2: Work Locally, Then Upload

Edit on your own computer, preview locally, then upload when ready.

**How:** Run PHP's built-in server (see below), edit in your favourite editor, upload via SFTP when happy.

**Pros:**
- Fast feedback loop—save, refresh, repeat
- Can work offline
- Safer—test changes before they go live

**Cons:**
- Need PHP installed on your computer
- Extra step to upload changes

### Option 3: Use Git + Remote Repository

Track all changes with version control and sync via GitHub, GitLab, or similar.

**How:** Commit changes locally, push to a remote repository, pull or deploy on your server.

**Pros:**
- Full history of every change (easy to undo mistakes)
- Great for collaboration
- Can automate deployments

**Cons:**
- Steeper learning curve if you're new to Git
- More setup involved

### Mix and Match

Many people combine approaches: quick content fixes directly on the server, bigger design changes locally with Git. Do what works for you!

</div>

### Local Preview

If you're working on your own machine, PHP's built-in server is the quickest way to preview:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000` in your browser.

!> This is a development server, not for public use. See the [Hosting Guide](hosting.md) for production options.

### Live Editing

Editing files directly on your server works great too! Ava's auto-rebuild mode (the default) means changes appear immediately—just save and refresh.

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
