# Shortcodes

Shortcodes let you add dynamic content to your Markdown without writing raw HTML. They're simple tags in square brackets that get replaced when the page renders.

## How They Work

Shortcodes come in two forms:

```markdown
<!-- Self-closing -->
Copyright © [year] [site_name]

<!-- Paired (with content between) -->
[email]hello@example.com[/email]
```

When rendered, `[year]` becomes `2025`, `[site_name]` becomes your site name, and `[email]` creates a spam-protected mailto link.

## Built-in Shortcodes

| Shortcode | Output |
|-----------|--------|
| `[year]` | Current year |
| `[site_name]` | Site name from config |
| `[site_url]` | Site URL from config |
| `[email]you@example.com[/email]` | Obfuscated mailto link |
| `[snippet name="file"]` | Renders `snippets/file.php` |

## Creating Custom Shortcodes

Register shortcodes in your `theme.php`:

```php
<?php
// themes/yourtheme/theme.php

use Ava\Application;

return function (Application $app): void {
    $shortcodes = $app->shortcodes();

    // Self-closing shortcode
    $shortcodes->register('greeting', function (array $attrs) {
        $name = $attrs['name'] ?? 'friend';
        return "Hello, " . htmlspecialchars($name) . "!";
    });

    // Paired shortcode (receives content)
    $shortcodes->register('highlight', function (array $attrs, ?string $content) {
        $color = $attrs['color'] ?? 'yellow';
        return '<mark style="background:' . htmlspecialchars($color) . '">' . $content . '</mark>';
    });
};
```

Usage:

```markdown
[greeting name="Alice"]

[highlight color="#ffeeba"]This text is highlighted.[/highlight]
```

### Shortcode Callback Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$attrs` | `array` | All attributes passed to the shortcode |
| `$content` | `?string` | Content between opening/closing tags (null for self-closing) |

### Best Practices

- **Escape output** — Always use `htmlspecialchars()` or `$ava->e()` for user-provided values to prevent XSS attacks
- **Return strings** — Shortcodes must return a string (the replacement HTML)
- **Keep it simple** — Complex shortcodes are better as snippets (see below)
- **Name carefully** — Shortcode names are case-insensitive, use underscores for multi-word names

## Snippets: Reusable PHP Components

For more complex components, use snippets. A snippet is a PHP file in your `snippets/` folder that you invoke with the `[snippet]` shortcode.

**When to use snippets vs shortcodes:**

| Use Shortcodes | Use Snippets |
|----------------|--------------|
| Simple text replacements | Complex HTML structures |
| No external files needed | Reusable across sites |
| 1-5 lines of code | Need full PHP file with logic |
| Site-specific utilities | Component libraries |

### Creating a Snippet

```php
<?php // snippets/cta.php ?>
<?php
$heading = $params['heading'] ?? 'Ready to get started?';
$button = $params['button'] ?? 'Learn More';
$url = $params['url'] ?? '/contact';
?>
<div class="cta-box">
    <h3><?= htmlspecialchars($heading) ?></h3>
    <p><?= $content ?></p>
    <a href="<?= htmlspecialchars($url) ?>" class="button">
        <?= htmlspecialchars($button) ?>
    </a>
</div>
```

### Using a Snippet

```markdown
[snippet name="cta" heading="Join Our Newsletter" button="Subscribe" url="/subscribe"]
Get weekly tips delivered to your inbox.
[/snippet]
```

### Variables in Snippets

| Variable | Description |
|----------|-------------|
| `$content` | Text between opening/closing tags |
| `$params` | Array of all attributes (e.g., `$params['heading']`) |
| `$ava` | TemplateHelpers instance (for `$ava->e()`, `$ava->url()`, etc.) |
| `$app` | Application instance |

### Example Snippets

**YouTube Embed:**
```php
<?php // snippets/youtube.php ?>
<?php $id = $params['id'] ?? ''; ?>
<div class="video-embed" style="aspect-ratio: 16/9;">
    <iframe src="https://www.youtube.com/embed/<?= $ava->e($id) ?>" 
            frameborder="0" allowfullscreen style="width:100%;height:100%;"></iframe>
</div>
```

Usage: `[snippet name="youtube" id="dQw4w9WgXcQ"]`

**Notice Box:**
```php
<?php // snippets/notice.php ?>
<?php
$type = $params['type'] ?? 'info';
$icons = ['info' => '💡', 'warning' => '⚠️', 'success' => '✅', 'error' => '❌'];
$icon = $icons[$type] ?? '💡';
?>
<div class="notice notice-<?= $ava->e($type) ?>">
    <span><?= $icon ?></span>
    <div><?= $content ?></div>
</div>
```

Usage:
```markdown
[snippet name="notice" type="warning"]
This feature is experimental.
[/snippet]
```

**Pricing Card:**
```php
<?php // snippets/pricing.php ?>
<?php
$plan = $params['plan'] ?? 'Plan';
$price = $params['price'] ?? '$0';
$features = $params['features'] ?? '';
$url = $params['url'] ?? '#';
?>
<div class="pricing-card">
    <h3><?= $ava->e($plan) ?></h3>
    <div class="price"><?= $ava->e($price) ?><span>/month</span></div>
    <ul>
        <?php foreach (explode(',', $features) as $feature): ?>
            <li><?= $ava->e(trim($feature)) ?></li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= $ava->e($url) ?>" class="button">Get Started</a>
</div>
```

Usage: `[snippet name="pricing" plan="Pro" price="$29" features="Unlimited projects, Priority support, API access"]`

## How Processing Works

1. Markdown is converted to HTML
2. Shortcodes are processed in the HTML output
3. Result is sent to the browser

Since shortcodes run after Markdown processing, they can safely output raw HTML.

## Organising Your Theme

If your `theme.php` grows large, split it up:

```php
<?php
// themes/yourtheme/theme.php

return function (\Ava\Application $app): void {
    (require __DIR__ . '/inc/shortcodes.php')($app);
    (require __DIR__ . '/inc/hooks.php')($app);
};
```

```php
<?php
// themes/yourtheme/inc/shortcodes.php

return function (\Ava\Application $app): void {
    $shortcodes = $app->shortcodes();

    $shortcodes->register('button', function (array $attrs, ?string $content) {
        // ...
    });
};
```

## Security

- **Path safety:** Snippet names can't contain `..` or `/` — no directory traversal
- **Disable snippets:** Set `security.shortcodes.allow_php_snippets` to `false`
- **Unknown shortcodes:** Left as-is in output (no errors)
- **Escaping:** Always use `htmlspecialchars()` or `$ava->e()` for user values
