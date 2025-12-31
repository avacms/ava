# CLI Reference

<pre><samp><span class="t-magenta">   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀</span></samp></pre>

Ava includes a friendly command-line interface for managing your site. Run commands from your project root:

```bash
./ava <command> [options]
```

The CLI has been thoughtfully designed for a simple and delightful experience. Most output includes helpful tips and next steps.

<div class="beginner-box">

## Beginner’s Guide to the Terminal

“CLI” just means *typing commands* instead of clicking buttons. It’s a superpower for servers and automation, but you only need a tiny slice of it to be productive with Ava.

### What is “the project root”?
It’s the folder that contains your Ava project — where you can see `composer.json`, `content/`, `themes/`, and the `ava` script.

?> **Tip:** If you type `./ava status` and it works, you’re in the right folder.

### A tiny CLI cheat-sheet (you’ll use these a lot)

| Command | What it does |
| :--- | :--- |
| `pwd` | Show your current folder (Linux/macOS). Short for "print working directory". |
| `ls` | List files in the current folder (Linux/macOS). Short for "list". |
| `cd folder-name` | Move into a folder. Short for "change directory". |
| `cd ..` | Go up one folder. |
| `php -v` | Show your PHP version. |

?> **Windows note:** In PowerShell, the equivalents are `Get-Location` (like `pwd`) and `dir` (like `ls`). `cd` works everywhere.

### Running Commands on a Server (SSH)

One of Ava's strengths is flexibility—you can work however suits you best. Edit files directly on your server, work locally and deploy, or mix both approaches. There's no "correct" way.

If you need to run CLI commands on a remote server, use **SSH**:

```bash
ssh user@your-domain-or-server-ip
cd /path/to/your/site
./ava status
```

**SSH clients people like:**
- **Built-in:** macOS Terminal, Linux Terminal, Windows Terminal / PowerShell
- **Code Editors/IDEs:** [Visual Studio Code (with Remote - SSH)](https://code.visualstudio.com/docs/remote/ssh)
- **GUI options:** Termius, PuTTY

For a deeper dive into SSH, hosting options, and getting Ava live on the internet, see the [Hosting Guide](hosting.md).

### Uploading files (SFTP)
If you’re used to FTP, think of **SFTP** as the safer modern version. Popular clients include FileZilla, WinSCP, Cyberduck, and Transmit.

</div>

## Quick Reference

| Command | Description |
|---------|-------------|
| `status` | Show site overview and health |
| `rebuild` | Rebuild the [content index](performance.md#content-indexing) |
| `lint` | Validate [content files](content.md) |
| `benchmark` | Test content index [performance](performance.md#benchmark-comparison) |
| `make <type> "Title"` | Create new content |
| `prefix <add\|remove> [type]` | Toggle date prefixes on filenames |
| `user:add` | Create admin user |
| `user:password` | Update user password |
| `user:remove` | Remove admin user |
| `user:list` (or `user`) | List all users |
| `update:check` (or `update`) | Check for updates |
| `update:apply` | Apply available update |
| `pages:stats` (or `pages`) | Page cache statistics |
| `pages:clear` | Clear page cache |
| `logs:stats` (or `logs`) | Log file statistics |
| `logs:tail` | Show last lines of a log |
| `logs:clear` | Clear log files |
| `stress:generate` | Generate test content |
| `stress:clean` | Remove test content |
| `test [filter] [-q]` | Run the [test suite](testing.md) |

---

## Getting Help

Run `./ava` or `./ava --help` to see all available commands:

```bash
./ava --help
```

<div class="beginner-box">
<strong>Why do commands start with <code>./</code>?</strong><br>
<br>
<code>./ava</code> means “run the ava script in this folder.” The <code>./</code> tells your computer to look for the command right here, not somewhere else on your system. This is common for project tools in PHP, Node, Python, and more. If you just type <code>ava</code>, it only works if you’ve installed it globally (which is not recommended for project scripts).
</div>

**Shortcuts:** Several commands have convenient aliases:
- `./ava pages` → `pages:stats`
- `./ava logs` → `logs:stats`
- `./ava user` → `user:list`
- `./ava update` → `update:check`

<pre><samp><span class="t-magenta">   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀</span>

  <span class="t-dim">───</span> <span class="t-bold">Usage</span> <span class="t-dim">─────────────────────────────────────────────</span>

  ./ava &lt;command&gt; [options]

  <span class="t-dim">───</span> <span class="t-bold">Site Management</span> <span class="t-dim">───────────────────────────────────</span>

    <span class="t-cyan">status</span>                        Show site health and overview
    <span class="t-cyan">rebuild</span>                       Rebuild the content index
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
  <span class="t-dim">Extensions:</span> <span class="t-white">igbinary, pdo_sqlite, opcache</span>

  <span class="t-dim">───</span> <span class="t-bold">Content Index</span> <span class="t-dim">─────────────────────────────────────</span>

  <span class="t-dim">Status:</span>     <span class="t-green">● Fresh</span>
  <span class="t-dim">Mode:</span>       <span class="t-white">auto</span>
  <span class="t-dim">Backend:</span>    <span class="t-white">sqlite</span>
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

Rebuild the content index:

```bash
./ava rebuild
```

<pre><samp>  <span class="t-green">✓</span> Rebuilding content index <span class="t-dim">(23ms)</span>

  <span class="t-green">✓ Content index rebuilt!</span></samp></pre>

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

  <span class="t-blue">→</span> <span class="t-cyan">./ava rebuild</span> <span class="t-dim">— Update the content index</span></samp></pre>

This reads the `date` field from frontmatter.

---

## User Management

Manage admin dashboard users. Users are stored in [`app/config/users.php`](configuration.md).

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

**Password Security:** Your password is hashed using [bcrypt](https://en.wikipedia.org/wiki/Bcrypt) before being stored in `app/config/users.php`. This means:
- Your actual password is never saved—only an irreversible hash
- Even if someone accesses the users file, they can't recover your password
- Each password has a unique salt, so identical passwords have different hashes

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

### update

Check for available updates (alias for `update:check`):

```bash
./ava update
```

Results are cached for 1 hour. Force a fresh check:

```bash
./ava update --force
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

  <span class="t-yellow">⚠️  Have you backed up your site and have a secure copy saved off-site?</span>
  <span class="t-dim">[y/N]:</span> <span class="t-green">y</span>

  Continue with update? <span class="t-dim">[y/N]:</span> <span class="t-green">y</span>

  <span class="t-green">✓</span> Downloading update <span class="t-dim">(342ms)</span>

  <span class="t-green">✓ Update applied successfully!</span>

  <span class="t-green">✓</span> Rebuilding content index <span class="t-dim">(18ms)</span>
  <span class="t-green">✓ Done!</span></samp></pre>

Skip confirmation with `-y`:

```bash
./ava update:apply -y
```

See [Updates](updates.md) for details on what gets updated and preserved.

---

## Page Cache

Commands for managing the on-demand HTML page cache. This cache stores rendered web pages for all URLs on your site—not just the "Page" content type—including posts, archives, taxonomy pages, and custom content types.

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
- Content changes (in `content_index.mode = 'auto'`)

See [Performance](performance.md#page-cache-details) for details.

---

## Logs

Commands for managing log files in `storage/logs/`. Ava automatically rotates log files when they exceed the configured size limit to prevent disk space issues.

### logs:stats

View log file statistics:

```bash
./ava logs:stats
```

<pre><samp>  <span class="t-dim">───</span> <span class="t-bold">Logs</span> <span class="t-dim">───────────────────────────────────────────────</span>

  <span class="t-dim">indexer.log:</span>  <span class="t-white">245.3 KB</span> <span class="t-dim">(2 files) · 1,847 lines</span>
  <span class="t-dim">admin.log:</span>    <span class="t-white">12.1 KB</span> <span class="t-dim">· 89 lines</span>

  <span class="t-dim">Total:</span>        <span class="t-white">257.4 KB (3 files)</span>

  <span class="t-dim">Max Size:</span>     <span class="t-white">10 MB per log</span>
  <span class="t-dim">Max Files:</span>    <span class="t-white">3 rotated copies</span></samp></pre>

### logs:tail

Show the last lines of a log file:

```bash
# Show last 20 lines of indexer.log (default)
./ava logs:tail

# Show last 20 lines of a specific log
./ava logs:tail indexer.log

# Show last 50 lines
./ava logs:tail indexer -n 50
```

<pre><samp>  <span class="t-dim">───</span> <span class="t-bold">indexer.log (last 20 lines)</span> <span class="t-dim">─────────────────────</span>

  <span class="t-dim">[2024-12-28T14:30:00+00:00]</span> Indexer errors:
    - Missing required field "slug" in posts/draft-post.md
    - Invalid date format in posts/old-post.md

  <span class="t-dim">[2024-12-28T15:45:00+00:00]</span> Indexer errors:
    - Duplicate ID found: posts/copy-of-post.md</samp></pre>

### logs:clear

Clear log files:

```bash
# Clear all logs (with confirmation)
./ava logs:clear
```

<pre><samp>  Found <span class="t-white">3</span> log file(s) <span class="t-dim">(257.4 KB)</span>.

  Clear all log files? <span class="t-dim">[y/N]:</span> <span class="t-green">y</span>

  <span class="t-green">✓</span> Cleared <span class="t-white">3</span> log file(s) <span class="t-dim">(257.4 KB)</span></samp></pre>

```bash
# Clear a specific log (and its rotated copies)
./ava logs:clear indexer.log
```

<pre><samp>  <span class="t-green">✓</span> Cleared <span class="t-white">2</span> log file(s) <span class="t-dim">(245.3 KB)</span></samp></pre>

### Log Rotation

Ava automatically rotates log files to prevent them from growing too large. Configure rotation in `app/config/ava.php`:

```php
'logs' => [
    'max_size' => 10 * 1024 * 1024,  // 10 MB (default)
    'max_files' => 3,                 // Keep 3 rotated copies
],
```

When a log exceeds `max_size`, it's rotated:
- `indexer.log` → `indexer.log.1`
- `indexer.log.1` → `indexer.log.2` (etc.)
- Oldest files beyond `max_files` are deleted

See [Configuration - Logs](configuration.md?id=logs) for details.

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

  <span class="t-green">✓</span> Rebuilding content index <span class="t-dim">(89ms)</span>

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

  <span class="t-green">✓</span> Rebuilding content index <span class="t-dim">(12ms)</span>
  <span class="t-green">✓ Done!</span></samp></pre>

---

## Testing

### test

Run the automated test suite:

```bash
./ava test
```

<pre><samp>  <span class="t-bold">Ava CMS Test Suite</span>
  <span class="t-dim">──────────────────────────────────────────────────</span>

  <span class="t-cyan">StrTest</span>

    <span class="t-green">✓</span> slug converts to lowercase
    <span class="t-green">✓</span> slug replaces spaces with separator
    <span class="t-green">✓</span> starts with returns true for match
    <span class="t-dim">...</span>

  <span class="t-dim">──────────────────────────────────────────────────</span>
  <span class="t-bold">Tests:</span> <span class="t-green">333 passed</span> <span class="t-dim">(70ms)</span></samp></pre>

Filter tests by class name:

```bash
./ava test Str           # Run StrTest only
./ava test Parser        # Run ParserTest only
./ava test Request       # Run RequestTest only
```

Run tests with minimal output (useful for CI/CD):

```bash
./ava test --quiet       # Long form
./ava test -q            # Short form
```

<pre><samp>  <span class="t-bold">Ava CMS Test Suite</span>
  <span class="t-dim">──────────────────────────────────────────────────</span>
  <span class="t-bold">Tests:</span> <span class="t-green">355 passed</span> <span class="t-dim">(60ms)</span></samp></pre>

See [Testing](testing.md) for details on writing tests and available assertions.

---

## Benchmarking

### benchmark

Test the performance of your content index:

```bash
./ava benchmark
```

<pre><samp><span class="t-magenta">   ▄▄▄  ▄▄ ▄▄  ▄▄▄     ▄▄▄▄ ▄▄   ▄▄  ▄▄▄▄
  ██▀██ ██▄██ ██▀██   ██▀▀▀ ██▀▄▀██ ███▄▄
  ██▀██  ▀█▀  ██▀██   ▀████ ██   ██ ▄▄██▀</span>
  <span class="t-dim">v25.12.2</span>

  <span class="t-dim">───</span> <span class="t-bold">Performance Benchmark</span> <span class="t-dim">───────────────────────────────</span>

  <span class="t-dim">Content:</span>    <span class="t-cyan">1,003</span> items
              page: 2
              post: 1001

  <span class="t-dim">Backend:</span>    <span class="t-cyan">array + igbinary</span>
  <span class="t-dim">igbinary:</span>   <span class="t-green">enabled</span>
  <span class="t-dim">Iterations:</span> 5

  Testing array + igbinary...

  <span class="t-dim">───</span> <span class="t-bold">Results</span> <span class="t-dim">──────────────────────────────────────────────</span>

  <span class="t-bold">Test                array + igbinary</span>
  <span class="t-dim">──────────────────────────────────────</span>
  Count               2.2ms
  Get by slug         3.5ms
  Recent (page 1)     0.14ms
  Archive (page 50)   7.4ms
  Sort by date        9.7ms
  Sort by title       10.5ms
  Search              7.3ms
  <span class="t-dim">──────────────────────────────────────</span>
  Memory              124 KB
  Cache size          592.2 KB

  <span class="t-yellow">💡 Tip:</span> Run with <span class="t-cyan">--compare</span> to test all backends.
  <span class="t-blue">📚 Docs:</span> https://ava.addy.zone/#/performance</samp></pre>

**Options:**

| Option | Description |
|--------|-------------|
| `--compare` | Compare all available backends side-by-side |
| `--iterations=N` | Number of test iterations (default: 5) |

**What it tests:**
- **Count** — Counting all posts
- **Get by slug** — Fetching a single post by URL
- **Recent (page 1)** — Homepage/recent posts (uses fast cache)
- **Archive (page 50)** — Deep pagination (loads full index)
- **Sort by date** — Sorting all posts by date
- **Sort by title** — Sorting all posts by title
- **Search** — Full-text search across content

**Typical workflow:**

```bash
# Generate test content at your target scale
./ava stress:generate post 10000

# Run benchmark on current backend
./ava benchmark

# Compare all backends (rebuilds for each)
./ava benchmark --compare

# Clean up when done
./ava stress:clean post
```

See [Performance](performance.md) for detailed benchmark results and backend recommendations.

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

# Content index rebuilds automatically when files change
# (when content_index.mode is 'auto')
```

### Production Deploy

```bash
# In production, set content_index.mode to 'never'
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
./ava benchmark

# Clean up when done
./ava stress:clean post
```
