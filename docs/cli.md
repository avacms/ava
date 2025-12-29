# CLI Reference

<pre><samp><span class="t-magenta">   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀</span></samp></pre>

Ava includes a friendly command-line interface for managing your site. Run commands from your project root:

```bash
./ava <command> [options]
```


The CLI uses colors and visual formatting for a delightful experience. Most output includes helpful tips and next steps.

<div class="beginner-box">

## Beginner’s Guide to the Terminal

“CLI” just means *typing commands* instead of clicking buttons. It’s a superpower for servers and automation, but you only need a tiny slice of it to be productive with Ava.

### What is “the project root”?
It’s the folder that contains your Ava project — where you can see `composer.json`, `content/`, `themes/`, and the `ava` script.

**Tip:** If you type `./ava status` and it works, you’re in the right folder.

### A tiny CLI cheat-sheet (you’ll use these a lot)

| Command | What it does |
| :--- | :--- |
| `pwd` | Show your current folder (Linux/macOS). |
| `ls` | List files in the current folder (Linux/macOS). |
| `cd folder-name` | Move into a folder. |
| `cd ..` | Go up one folder. |
| `php -v` | Show your PHP version. |

**Windows note:** In PowerShell, the equivalents are `Get-Location` (like `pwd`) and `dir` (like `ls`). `cd` works everywhere.

### Running Commands on a Server (SSH)

You’ll often do Ava work locally, then deploy. But if you want to manage content/config directly on a server, the usual flow is **SSH**:

```bash
ssh user@your-domain-or-server-ip
cd /path/to/your/site
./ava status
```

**SSH clients people like**
- **Built-in (recommended):** macOS Terminal, Linux Terminal, Windows Terminal / PowerShell
- **GUI options:** Termius, PuTTY

### Uploading files (SFTP)
If you’re used to FTP, think of **SFTP** as the safer modern version. Popular clients include FileZilla, WinSCP, Cyberduck, and Transmit.

</div>

## Quick Reference

| Command | Description |
|---------|-------------|
| `status` | Show site overview and health |
| `rebuild` | Force cache rebuild |
| `lint` | Validate content files |
| `make <type> "Title"` | Create new content |
| `prefix <add\|remove> [type]` | Toggle date prefixes on filenames |
| `user:add` | Create admin user |
| `user:password` | Update user password |
| `user:remove` | Remove admin user |
| `user:list` | List all users |
| `update:check` | Check for updates |
| `update:apply` | Apply available update |
| `pages:stats` | Page cache statistics |
| `pages:clear` | Clear page cache |
| `stress:generate` | Generate test content |
| `stress:clean` | Remove test content |

---

## Getting Help

Run `./ava` or `./ava --help` to see all available commands:

```bash
./ava --help
```

<pre><samp><span class="t-magenta">   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀</span>

  <span class="t-dim">───</span> <span class="t-bold">Usage</span> <span class="t-dim">─────────────────────────────────────────────</span>

  ./ava &lt;command&gt; [options]

  <span class="t-dim">───</span> <span class="t-bold">Site Management</span> <span class="t-dim">───────────────────────────────────</span>

    <span class="t-cyan">status</span>                        Show site health and overview
    <span class="t-cyan">rebuild</span>                       Force rebuild all caches
    <span class="t-cyan">lint</span>                          Validate all content files

  <span class="t-dim">───</span> <span class="t-bold">Content</span> <span class="t-dim">───────────────────────────────────────────</span>

    <span class="t-cyan">make &lt;type&gt; "Title"</span>           Create new content
    ...</samp></pre>

---

## Site Management

### status

Shows a quick overview of your site's health:

```bash
./ava status
```

<pre><samp><span class="t-magenta">   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀</span>

  <span class="t-dim">───</span> <span class="t-bold">Site</span> <span class="t-dim">──────────────────────────────────────────────</span>

  <span class="t-dim">Name:</span>       <span class="t-white">My Site</span>
  <span class="t-dim">URL:</span>        <span class="t-cyan">https://example.com</span>

  <span class="t-dim">───</span> <span class="t-bold">Environment</span> <span class="t-dim">───────────────────────────────────────</span>

  <span class="t-dim">PHP:</span>        <span class="t-white">8.3.29</span>
  <span class="t-dim">Extensions:</span> <span class="t-white">igbinary, opcache</span>

  <span class="t-dim">───</span> <span class="t-bold">Content Cache</span> <span class="t-dim">─────────────────────────────────────</span>

  <span class="t-dim">Status:</span>     <span class="t-green">● Fresh</span>
  <span class="t-dim">Mode:</span>       <span class="t-white">auto</span>
  <span class="t-dim">Built:</span>      <span class="t-white">2024-12-28 14:30:00</span>

  <span class="t-dim">───</span> <span class="t-bold">Content</span> <span class="t-dim">───────────────────────────────────────────</span>

  <span class="t-cyan">◆ Page:</span> <span class="t-white">5 published</span>
  <span class="t-cyan">◆ Post:</span> <span class="t-white">38 published</span> <span class="t-yellow">(4 drafts)</span>

  <span class="t-dim">───</span> <span class="t-bold">Taxonomies</span> <span class="t-dim">────────────────────────────────────────</span>

  <span class="t-cyan">◆ Category:</span> <span class="t-white">8 terms</span>
  <span class="t-cyan">◆ Tag:</span> <span class="t-white">23 terms</span>

  <span class="t-dim">───</span> <span class="t-bold">Page Cache</span> <span class="t-dim">────────────────────────────────────────</span>

  <span class="t-dim">Status:</span>     <span class="t-green">● Enabled</span>
  <span class="t-dim">TTL:</span>        <span class="t-white">Forever</span>
  <span class="t-dim">Cached:</span>     <span class="t-white">42 pages</span>
  <span class="t-dim">Size:</span>       <span class="t-white">1.2 MB</span></samp></pre>

### rebuild

Force the cache to rebuild:

```bash
./ava rebuild
```

<pre><samp>  <span class="t-green">✓</span> Rebuilding content cache <span class="t-dim">(23ms)</span>

  <span class="t-green">✓ Cache rebuilt successfully!</span></samp></pre>

Use this after deploying new content in production, or if something looks stuck.

### lint

Validate all content files for common problems:

```bash
./ava lint
```

<pre><samp>  🔍 Validating content files...

  <span class="t-green">╭────────────────────────────────╮
  │  All content files are valid!  │
  │  No issues found.              │
  ╰────────────────────────────────╯</span></samp></pre>

If there are issues, you'll see them listed with links to documentation:

<pre><samp>  🔍 Validating content files...

  <span class="t-red">✗ Found 2 issue(s):</span>

    <span class="t-red">•</span> <span class="t-white">posts/my-post.md:</span> Invalid status "archived" <span class="t-dim">— see https://ava.addy.zone/#/content?id=status</span>
    <span class="t-red">•</span> <span class="t-white">pages/about.md:</span> Missing required field "slug" <span class="t-dim">— see https://ava.addy.zone/#/content?id=frontmatter-guide</span>

  <span class="t-yellow">💡 Tip:</span> Fix the issues above and run lint again</samp></pre>

Checks for:

| Check | What it means |
|-------|---------------|
| YAML syntax | Frontmatter must parse correctly |
| Required fields | `title`, `slug`, `status` are present |
| Status values | Must be `draft`, `published`, or `private` |
| Slug format | Lowercase, alphanumeric, hyphens only |
| Duplicate slugs | Within the same content type |
| Duplicate IDs | Across all content |

---

## Content Creation

### make

Create new content with proper scaffolding:

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

### prefix

Toggle date prefixes on content filenames:

```bash
./ava prefix <add|remove> [type]
```

Examples:

```bash
./ava prefix add post
```

<pre><samp>  Adding date prefixes...

    <span class="t-dim">→</span> <span class="t-white">hello-world.md</span> <span class="t-dim">→</span> <span class="t-cyan">2024-12-28-hello-world.md</span>
    <span class="t-dim">→</span> <span class="t-white">another-post.md</span> <span class="t-dim">→</span> <span class="t-cyan">2024-11-15-another-post.md</span>

  <span class="t-green">✓</span> Renamed 2 file(s)

  <span class="t-blue">→</span> <span class="t-cyan">./ava rebuild</span> <span class="t-dim">— Update the cache</span></samp></pre>

This reads the `date` field from frontmatter.

---

## User Management

Manage admin dashboard users. Users are stored in `app/config/users.php`.

### user:add

Create a new admin user:

```bash
./ava user:add admin@example.com secretpass "Admin User"
```

<pre><samp>  <span class="t-green">╭─────────────────────────────────╮
  │  User created successfully!     │
  ╰─────────────────────────────────╯</span>

  <span class="t-dim">Email:</span>      <span class="t-cyan">admin@example.com</span>
  <span class="t-dim">Name:</span>       <span class="t-white">Admin User</span>

  <span class="t-blue">→</span> <span class="t-cyan">/admin</span> <span class="t-dim">— Login at your admin dashboard</span></samp></pre>

### user:password

Update an existing user's password:

```bash
./ava user:password admin@example.com newpassword
```

<pre><samp>  <span class="t-green">✓</span> Password updated for: <span class="t-cyan">admin@example.com</span></samp></pre>

### user:remove

Remove a user:

```bash
./ava user:remove admin@example.com
```

<pre><samp>  <span class="t-green">✓</span> User removed: <span class="t-cyan">admin@example.com</span></samp></pre>

### user:list

List all configured users:

```bash
./ava user:list
```

<pre><samp>  <span class="t-dim">───</span> <span class="t-bold">Users</span> <span class="t-dim">─────────────────────────────────────────────</span>

    <span class="t-cyan">◆ admin@example.com</span>
      <span class="t-dim">Name:</span> <span class="t-white">Admin User</span>
      <span class="t-dim">Created:</span> <span class="t-white">2024-12-28</span>

    <span class="t-cyan">◆ editor@example.com</span>
      <span class="t-dim">Name:</span> <span class="t-white">Editor</span>
      <span class="t-dim">Created:</span> <span class="t-white">2024-12-15</span></samp></pre>

---

## Updates

### update:check

Check for available Ava updates:

```bash
./ava update:check
```

<pre><samp>  🔍 Checking for updates...

  <span class="t-dim">Current:</span>    <span class="t-white">25.12.0</span>
  <span class="t-dim">Latest:</span>     <span class="t-green">25.12.1</span>

  <span class="t-green">╭───────────────────────╮
  │  Update available!    │
  ╰───────────────────────╯</span>

  <span class="t-dim">Release:</span>    <span class="t-white">v25.12.1</span>
  <span class="t-dim">Published:</span>  <span class="t-white">2024-12-28</span>

  <span class="t-dim">───</span> <span class="t-bold">Changelog</span> <span class="t-dim">──────────────────────────────────────────</span>

  <span class="t-dim">-</span> Fixed page cache invalidation
  <span class="t-dim">-</span> Improved CLI output formatting
  <span class="t-dim">-</span> Added progress bars for bulk operations

  <span class="t-blue">→</span> <span class="t-cyan">./ava update:apply</span> <span class="t-dim">— Download and apply the update</span></samp></pre>

Results are cached for 1 hour. Force a fresh check:

```bash
./ava update:check --force
```

### update:apply

Download and apply the latest update:

```bash
./ava update:apply
```

<pre><samp>  <span class="t-dim">───</span> <span class="t-bold">Update Available</span> <span class="t-dim">───────────────────────────────────</span>

  <span class="t-dim">From:</span>       <span class="t-white">25.12.0</span>
  <span class="t-dim">To:</span>         <span class="t-green">25.12.1</span>

  <span class="t-bold">Will be updated:</span>
    <span class="t-cyan">▸</span> Core files <span class="t-dim">(core/, bin/, bootstrap.php)</span>
    <span class="t-cyan">▸</span> Default theme <span class="t-dim">(themes/default/)</span>
    <span class="t-cyan">▸</span> Bundled plugins <span class="t-dim">(sitemap, feed, redirects)</span>
    <span class="t-cyan">▸</span> Documentation <span class="t-dim">(docs/)</span>

  <span class="t-bold">Will NOT be modified:</span>
    <span class="t-green">•</span> Your content <span class="t-dim">(content/)</span>
    <span class="t-green">•</span> Your configuration <span class="t-dim">(app/)</span>
    <span class="t-green">•</span> Custom themes and plugins
    <span class="t-green">•</span> Storage and cache files

  Continue? <span class="t-dim">[y/N]:</span> <span class="t-green">y</span>

  <span class="t-green">✓</span> Downloading update <span class="t-dim">(342ms)</span>

  <span class="t-green">✓ Update applied successfully!</span>

  <span class="t-green">✓</span> Rebuilding cache <span class="t-dim">(18ms)</span>
  <span class="t-green">✓ Done!</span></samp></pre>

Skip confirmation with `-y`:

```bash
./ava update:apply -y
```

See [Updates](updates.md) for details on what gets updated and preserved.

---

## Page Cache

Commands for managing the on-demand HTML page cache.

### pages:stats

View page cache statistics:

```bash
./ava pages:stats
```

<pre><samp>  <span class="t-dim">───</span> <span class="t-bold">Page Cache</span> <span class="t-dim">─────────────────────────────────────────</span>

  <span class="t-dim">Status:</span>     <span class="t-green">● Enabled</span>
  <span class="t-dim">TTL:</span>        <span class="t-white">Forever (until cleared)</span>

  <span class="t-dim">Cached:</span>     <span class="t-white">42 pages</span>
  <span class="t-dim">Size:</span>       <span class="t-white">1.2 MB</span>
  <span class="t-dim">Oldest:</span>     <span class="t-white">2024-12-28 10:00:00</span>
  <span class="t-dim">Newest:</span>     <span class="t-white">2024-12-28 14:30:00</span></samp></pre>

### pages:clear

Clear cached pages:

```bash
# Clear all cached pages (with confirmation)
./ava pages:clear
```

<pre><samp>  Found <span class="t-white">42</span> cached page(s).

  Clear all cached pages? <span class="t-dim">[y/N]:</span> <span class="t-green">y</span>

  <span class="t-green">✓</span> Cleared <span class="t-white">42</span> cached page(s)</samp></pre>

```bash
# Clear pages matching a URL pattern
./ava pages:clear /blog/*
```

<pre><samp>  <span class="t-green">✓</span> Cleared <span class="t-white">15</span> page(s) matching: <span class="t-cyan">/blog/*</span></samp></pre>

The page cache is also automatically cleared when:
- You run `./ava rebuild`
- Content changes (in `cache.mode = 'auto'`)

See [Configuration](configuration.md#page-cache) for setup options.

---

## Stress Testing

Commands for testing performance with large amounts of content.

### stress:generate

Generate dummy content for stress testing:

```bash
./ava stress:generate post 100
```

<pre><samp>  🧪 Generating <span class="t-white">100</span> dummy post(s)...

  <span class="t-green">[████████████████████████████████]</span> <span class="t-white">100%</span> Creating posts...

  <span class="t-green">✓</span> Generated <span class="t-white">100</span> files in <span class="t-dim">245ms</span>

  <span class="t-green">✓</span> Rebuilding cache <span class="t-dim">(89ms)</span>

  <span class="t-blue">→</span> <span class="t-cyan">./ava stress:clean post</span> <span class="t-dim">— Remove generated content when done</span></samp></pre>

Generated content includes:
- Random lorem ipsum titles and content
- Random dates (within last 2 years for dated types)
- Random taxonomy terms from configured taxonomies
- 80% published, 20% draft status
- Files prefixed with `_dummy-` for easy identification

### stress:clean

Remove all generated test content:

```bash
./ava stress:clean post
```

<pre><samp>  Found <span class="t-white">100</span> dummy content file(s).

  Delete all? <span class="t-dim">[y/N]:</span> <span class="t-green">y</span>

  <span class="t-green">[████████████████████████████████]</span> <span class="t-white">100%</span> Deleting files...

  <span class="t-green">✓</span> Deleted <span class="t-white">100</span> file(s)

  <span class="t-green">✓</span> Rebuilding cache <span class="t-dim">(12ms)</span>
  <span class="t-green">✓ Done!</span></samp></pre>

---

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Error (invalid arguments, validation failures, etc.) |

---

## Common Workflows

### Development

```bash
# Start dev server
php -S localhost:8000 -t public

# Cache rebuilds automatically when files change
# (when cache.mode is 'auto')
```

### Production Deploy

```bash
# In production, set cache.mode to 'never'
# Then rebuild after deploy:
./ava rebuild
```

### Content Validation

```bash
# Before committing content changes:
./ava lint

# If errors found, fix and re-run
```

### Performance Testing

```bash
# Generate test content
./ava stress:generate post 1000

# Check status (should be fast!)
./ava status

# Clean up when done
./ava stress:clean post
```
