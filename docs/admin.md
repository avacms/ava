# Admin Dashboard

Ava includes a simple, optional dashboard to help you keep an eye on your site. It's not a full editor, your files are the source of truth, but it's great for discovering more about your setup, learning how to configure and customise Ava as well as checking your site's health.

<a href="images/admin-dashboard.webp" target="_blank" rel="noopener">
    <img src="images/admin-dashboard.webp" alt="Ava admin dashboard" />
</a>

## What's it for?

Think of the dashboard as a friendly window into your site's engine room:

- **🩺 Health Check** — See if your content index is fresh and your system is happy.
- **📊 Content Overview** — Quickly see how many posts and pages you have.
- **🧹 Linting** — Check your content files for errors (like missing titles or broken links).
- **⚡ Index Rebuild** — Rebuild your site's content index with a single click.

## Enabling the Dashboard

It's disabled by default. To turn it on, edit `app/config/ava.php`:

```php
'admin' => [
    'enabled' => true,
    'path' => '/admin',   // You can change this to /dashboard or anything else!
    'theme' => 'cyan',    // Accent color: cyan, pink, purple, green, blue, amber
],
```

## Creating Your First User

Since there's no database, users are stored in a config file. Use the CLI to create one:

```bash
./ava user:add admin@example.com yourpassword "Your Name"
```

This creates a secure `app/config/users.php` file. If you're using version control, this file is gitignored by default to keep your credentials safe.

[Read more about security below](admin?id=security).

## Features

### Content Linter

This is the most useful feature. It scans all your Markdown files and warns you about:
- Missing required fields (like title or date)
- Invalid status settings
- Duplicate URLs

It's like a spellchecker for your site's structure.

<a href="images/admin-lint.webp" target="_blank" rel="noopener">
    <img src="images/admin-lint.webp" alt="Content linter screen" />
</a>

### Content Index Status

If you're making changes and they aren't showing up, the Content Index panel tells you why. You can see if the index is "Fresh" or "Stale" and rebuild it instantly.

### Theme Inspector

See exactly which theme is active and list all the available templates and assets. It's helpful for debugging if a page isn't looking right.

<a href="images/admin-themes.webp" target="_blank" rel="noopener">
    <img src="images/admin-themes.webp" alt="Theme inspector screen" />
</a>

### Admin Logs

View a log of admin actions — logins, logouts, index rebuilds, and warnings. Logs are stored in `storage/logs/admin.log` and show:

<a href="images/admin-log.webp" target="_blank" rel="noopener">
    <img src="images/admin-log.webp" alt="Admin logs screen" />
</a>

- Timestamp
- Log level (INFO, WARNING)
- Action description
- IP address

### System Info

Detailed system information including:

- Server load and memory usage
- PHP version and extensions
- Cache status and file counts
- Directory permissions
- Hook registrations

<a href="images/admin-info.webp" target="_blank" rel="noopener">
    <img src="images/admin-info.webp" alt="System info screen" />
</a>

### Content Browser

Browse all content by type. Each item shows:

- Title and slug
- Status (draft, published, unlisted)
- Date (for dated types)
- Quick links to view on site

You can create, edit, and delete content files directly in the admin. Changes are written back to your Markdown files (files remain the source of truth).

<a href="images/admin-content.webp" target="_blank" rel="noopener">
    <img src="images/admin-content.webp" alt="Content browser screen" />
</a>

### Taxonomies

Browse and manage taxonomy terms (like categories) and see which content is using them.

<a href="images/admin-taxonomy.webp" target="_blank" rel="noopener">
    <img src="images/admin-taxonomy.webp" alt="Taxonomies screen" />
</a>

### Shortcodes

See which shortcodes are available and how they render.

<a href="images/admin-shortcodes.webp" target="_blank" rel="noopener">
    <img src="images/admin-shortcodes.webp" alt="Shortcodes screen" />
</a>

### Media Uploader

Upload images securely to your media folder. The uploader includes comprehensive security features:

- **MIME type validation** — Files are verified using multiple methods (finfo, getimagesize, magic bytes)
- **Image reprocessing** — Images are reprocessed through ImageMagick or GD to strip any hidden payloads
- **SVG sanitization** — SVG files are cleaned to remove scripts and event handlers
- **Filename sanitization** — Filenames are normalized to prevent path traversal and other attacks

#### Configuration

Configure media uploads in `app/config/ava.php` within the admin section:

```php
'admin' => [
    'enabled' => true,
    'path'    => '/admin',
    'theme'   => 'cyan',

    'media' => [
        'enabled'          => true,
        'path'             => 'public/media',       // Upload destination
        'organize_by_date' => true,                 // Create year/month folders
        'max_file_size'    => 10 * 1024 * 1024,     // 10 MB limit
        'allowed_types'    => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/avif',
        ],
    ],
],
```

| Option | Description |
|--------|-------------|
| `enabled` | Set to `false` to disable the media uploader |
| `path` | Directory for uploads, relative to project root |
| `organize_by_date` | When `true`, uploads go into `year/month/` subfolders |
| `max_file_size` | Maximum file size in bytes (also limited by PHP settings) |
| `allowed_types` | Array of permitted MIME types |

#### Using Uploaded Media

After uploading, you can reference images in your content using the `@media:` alias:

```markdown
![Photo](@media:2024/01/my-photo.jpg)
```

This is equivalent to `/media/2024/01/my-photo.jpg`. The alias is defined in `paths.aliases` and can be customized.

?> **Tip:** The uploader provides both a full URL and a shortlink (`@media:...`) for each uploaded file. Use the shortlink in your Markdown for cleaner, portable content.

## Security

The admin dashboard is designed with security as a priority. Here's exactly how your credentials and sessions are protected:

### Password Storage

When you create a user with `./ava user:add`, your password goes through these steps:

1. **Hashing with bcrypt** - Uses PHP's `password_hash()` with bcrypt and a cost factor of 12 (current security recommendation)
2. **Only the hash is stored** - Your actual password never touches the disk; only the irreversible hash is saved to `app/config/users.php`
3. **Future-proof** - Uses `PASSWORD_BCRYPT` explicitly, ensuring consistent behaviour across PHP versions

**What this means for you:** Even if someone gains access to your `users.php` file, they cannot recover your actual password. Bcrypt is specifically designed to be slow (intentionally) to prevent brute-force attacks.

Example of what's stored (the actual password is NOT recoverable from this, unless you use brute-force guessing):
```php
'password' => '$2a$12$erDlkVmb.CvQbJeQoAkwoej1FANMw2QTzf3h2/VI5acJYHcpPagJa'
```

<div class="beginner-box">

## What is bcrypt and why is it safe?

**Understanding the hash:**
- `$2a$` = Bcrypt algorithm identifier
- `12$` = Cost factor (2^12 = 4,096 iterations)
- `erDlkVmb.CvQbJeQoAkwoe` = The **salt** (22 characters, randomly generated)
- `j1FANMw2QTzf3h2/VI5acJYHcpPagJa` = The actual hash of (password + salt)

**Why bcrypt is safe from rainbow tables (unlike MD5):**

Rainbow tables are pre-computed databases of password hashes. With MD5, if your password is "password123", the hash is always `482c811da5d5b4bc6d497ffa98491e38`. An attacker can look this up instantly in a rainbow table.

Bcrypt prevents this with **automatic salting**:

1. **Each hash gets a unique random salt** - Even if two users have the same password, their hashes look completely different because the salt is different
2. **Salt is stored in the hash itself** - The 22 characters after `$12$` are the salt, stored right in the hash so PHP can verify passwords later
3. **Salt makes rainbow tables useless** - A rainbow table would need a separate entry for every possible password × every possible salt combination (astronomically large)
4. **Slow by design** - Cost factor of 12 means 4,096 iterations, making brute-force attacks take much longer (about 4× slower than cost 10)

**In simple terms:** MD5 is like a photocopy—same input always gives the same output. Bcrypt is like mixing your password with random data unique to you, then running it through a slow blender 4,096 times. Even with two identical passwords, the results are completely different and can't be reversed.
</div>

### HTTPS and Transport Security

Bcrypt protects passwords stored in `users.php`, but it cannot protect passwords traveling from your browser to the server. Without HTTPS, your password is sent in **plain text** over the network where it can be intercepted by WiFi sniffing, compromised routers, or ISP monitoring.

!> **HTTPS is required for production.** The admin dashboard automatically blocks HTTP access from non-localhost addresses and returns a 403 error directing you to use HTTPS. This prevents passwords and session cookies from being transmitted unencrypted.

**How it works:**
- HTTPS encrypts all traffic using TLS before it leaves your browser
- Network observers see only encrypted data—your password cannot be read even if packets are intercepted
- The server decrypts the data and then hashes your password with bcrypt for storage

**Localhost exception:**

The admin allows HTTP on localhost (127.0.0.1 and ::1) because traffic stays on your machine and isn't exposed to network-level attacks. However, local malware or compromised system software could still intercept localhost traffic. For highly sensitive environments, consider using HTTPS even locally.

?> **Simple analogy:** Bcrypt is a safe protecting stored passwords. HTTPS is an armored truck protecting passwords in transit. You need both for complete security.

### Login & Session Security

**Brute-force protection:** Login attempts are rate-limited by IP address. After 5 failed attempts, the IP is locked out for 15 minutes. This prevents automated password guessing while allowing legitimate users to recover from typos.

**Timing attack prevention:** When you try to log in with an email that doesn't exist, Ava still performs a password verification against a dummy hash. This ensures response times don't reveal which email addresses are valid.

**Session security:**
- **Session fixation protection** — Session ID is regenerated on both login and logout
- **HTTP-only cookies** — JavaScript cannot access your session cookie (prevents XSS attacks)
- **SameSite protection** — Cookies include `SameSite=Lax` to prevent CSRF attacks
- **Secure flag** — When served over HTTPS, cookies are marked as secure-only

### CSRF Protection

Every form in the admin dashboard includes a CSRF token:

- **Generated securely** - Uses PHP's `random_bytes(32)` for cryptographically secure randomness
- **Timing-safe verification** - Token comparison uses `hash_equals()` to prevent timing attacks
- **Token regeneration** - Fresh token generated after form submissions

### Core Dashboard Capabilities

The admin dashboard provides a small set of core management tools:

**Core features:**
- ✅ Create, edit, and delete content (Markdown + YAML frontmatter)
- ✅ Manage taxonomy terms
- ✅ Rebuild the content index
- ✅ Lint content files for errors
- ✅ View logs and diagnostics

**What admin does not do:**
- ❌ WYSIWYG / visual editing
- ❌ Upload media files
- ❌ Modify core configuration files (other than admin/user-related data)

### Best Practices

The admin dashboard provides access to sensitive information and, with plugins enabled, may allow modifying redirects or other site settings. Follow these recommendations:

| Practice | Why It Matters |
|----------|----------------|
| **Use a strong password** | 16+ characters with mixed case, numbers, and symbols. Consider using a password manager. |
| **Always use HTTPS in production** | Without HTTPS, session cookies and passwords can be intercepted. Most hosts offer free SSL via Let's Encrypt. |
| **Keep `users.php` out of Git** | It's gitignored by default, but double-check. Your password hash shouldn't be in version control. |
| **Review plugin permissions** | If you enable plugins that extend admin functionality (like redirects), understand what they can modify. |
| **Monitor admin logs** | Check `storage/logs/admin.log` periodically for suspicious login attempts. |
| **Change the admin path** | Setting `'path' => '/_secret-admin'` doesn't add real security, but reduces log spam from bots. |

## Customising the Path

If you want the admin at a different URL:

```php
'admin' => [
    'enabled' => true,
    'path' => '/dashboard',  // Now at /dashboard
],
```

All admin routes (login, logout, etc.) will be prefixed with this path.

## Disabling the Admin

To disable the dashboard entirely:

```php
'admin' => [
    'enabled' => false,
],
```

This removes all admin routes. The dashboard code still exists but is never loaded.

## Preview Drafts

The admin allows previewing draft content without making it public.

When viewing the content browser, draft items have a "Preview" link. This uses a secure token to temporarily view unpublished content.

You can also preview drafts by adding query parameters:

```
https://yoursite.com/some-draft-post?preview=1&token=YOUR_PREVIEW_TOKEN
```

Set your preview token in `ava.php`:

```php
'security' => [
    'preview_token' => 'your-secret-token-here',
],
```

## Theming

The admin dashboard has its own built-in UI and respects your system's light/dark mode preference by default.

If you prefer to force a theme, you can use the theme toggle inside the admin — it saves your choice (so it sticks between visits) but will fall back to your system preference if you haven’t set anything yet.
