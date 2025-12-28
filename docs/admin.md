# Admin Dashboard

Ava includes an optional read-only admin dashboard. It's a web-based interface for monitoring your site — not a full CMS editor. Think of it as a visual wrapper around the CLI.

## Philosophy

The admin dashboard is intentionally limited:

- **Read-only** — View content, but don't edit it. Your files are the source of truth.
- **Monitoring** — See cache status, content counts, system info at a glance.
- **Safe tooling** — Run lint checks, trigger cache rebuilds, preview drafts.
- **Lightweight** — No database, no sessions stored on disk. Uses secure cookies.

This keeps things simple and prevents the dashboard from becoming a bottleneck or security risk.

## Enabling the Admin

In `app/config/ava.php`:

```php
'admin' => [
    'enabled' => true,
    'path' => '/admin',   // URL path (change if you prefer /dashboard, etc.)
],
```

## Creating an Admin User

Run the CLI command to create your first user:

```bash
./ava user:create
```

You'll be prompted for:
- Email address
- Password (min 8 characters)

This creates `app/config/users.php` (gitignored by default) containing a bcrypt-hashed password.

To add more users or reset passwords, edit `users.php` directly or run the command again.

## Accessing the Dashboard

Visit `/admin` (or your configured path) in your browser. You'll see a login form.

After logging in, you'll see the main dashboard with:

| Section | Description |
|---------|-------------|
| **Site Info** | Site name and URL |
| **Cache Status** | Current mode, last build time, whether cache is fresh |
| **Content Stats** | Count of each content type, published vs drafts |
| **Taxonomy Stats** | Term counts per taxonomy |
| **Recent Content** | Latest published items |
| **System Info** | PHP version, extensions, server details |

## Dashboard Features

### Cache Management

The dashboard shows your current cache status:

- **Fresh** — Cache is up to date
- **Stale** — Content has changed, cache needs rebuilding
- **Mode** — Current cache mode (auto, always, never)

Click "Rebuild Cache" to regenerate all cache files. This is equivalent to running `./ava rebuild` from the CLI.

### Content Lint

Run validation checks on all content files directly from the dashboard. This checks:

- Valid YAML frontmatter syntax
- Required fields (title, slug, status)
- Valid status values (draft, published, private)
- Slug format (lowercase, alphanumeric, hyphens)
- Duplicate slugs within content types
- Duplicate IDs

Results are displayed inline, showing any errors or warnings.

### Content Browser

Browse all content by type. Each item shows:

- Title and slug
- Status (draft, published, private)
- Date (for dated types)
- Quick links to view on site

This is read-only — to edit content, use your text editor and Git.

## Security

The admin dashboard includes several security measures:

| Feature | Description |
|---------|-------------|
| **Bcrypt passwords** | Passwords are hashed with bcrypt, never stored in plain text |
| **CSRF protection** | All forms include CSRF tokens to prevent cross-site attacks |
| **Secure cookies** | Session cookies are HTTP-only and secure (on HTTPS) |
| **Rate limiting** | Failed login attempts are tracked (basic protection) |
| **No file writes** | Dashboard cannot modify content files |

### Recommendations

- Use a strong, unique password
- Consider changing the admin path from `/admin` to something less guessable
- Use HTTPS in production
- Keep `users.php` out of version control (it's gitignored by default)

## Customizing the Path

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

The admin dashboard has its own built-in UI that respects your system's light/dark mode preference. It uses CSS custom properties, so it adapts automatically.

Currently, the admin theme is not customizable — it's designed to be consistent and stay out of your way.
