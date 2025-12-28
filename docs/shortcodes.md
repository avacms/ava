# Shortcodes

Shortcodes let you embed dynamic content in Markdown files. They're processed after Markdown parsing, so you can mix them freely with your content.

## Why Shortcodes?

Markdown is great for content, but sometimes you need:

- Dynamic data (current year, site name)
- Reusable components (CTAs, buttons, cards)
- PHP logic without exposing raw PHP in content files

Shortcodes give you this while keeping content files safe and readable.

## Syntax

Three forms of shortcodes:

```markdown
# Self-closing (no content)
[year]

# With attributes
[snippet name="cta" heading="Join Us"]

# Paired (wrapping content)
[button url="/contact"]Contact Us[/button]
```

Attributes can be quoted or unquoted. Quotes are required if values contain spaces.

## Built-in Shortcodes

| Shortcode | Output | Example |
|-----------|--------|---------|
| `[year]` | Current year | `2024` |
| `[date format="..."]` | Formatted date | `[date format="F j, Y"]` → `December 28, 2024` |
| `[site_name]` | Site name from config | `My Site` |
| `[site_url]` | Base URL from config | `https://example.com` |
| `[email]addr@example.com[/email]` | Obfuscated mailto link | Spam-resistant email |
| `[button url="..." class="..."]Text[/button]` | Styled anchor tag | `<a href="..." class="...">Text</a>` |
| `[snippet name="..."]` | Execute PHP snippet | See below |
| `[include file="..."]` | Include a template partial | `[include file="callout"]` |

## Snippets

Snippets are the most powerful shortcode. They execute PHP files from the `snippets/` directory, letting you build reusable components.

### Using a Snippet

```markdown
[snippet name="cta" heading="Get Started" button_url="/signup"]
```

### Creating a Snippet

Create `snippets/cta.php`:

```php
<?php
// snippets/cta.php
// Renders a call-to-action box

// Get attributes with defaults
$heading = $params['heading'] ?? 'Get Started';
$buttonUrl = $params['button_url'] ?? '/';
$buttonText = $params['button_text'] ?? 'Learn More';

?>
<div class="cta-box">
    <h3><?= htmlspecialchars($heading) ?></h3>
    <a href="<?= htmlspecialchars($buttonUrl) ?>" class="btn">
        <?= htmlspecialchars($buttonText) ?>
    </a>
</div>
```

### Available Variables in Snippets

| Variable | Type | Description |
|----------|------|-------------|
| `$params` | array | All shortcode attributes |
| `$content` | string | Inner content (for paired shortcodes) |
| `$app` | Application | Ava Application instance |

### Security Note

Snippets can execute arbitrary PHP, so:

- Only you (the developer) create snippets
- Content authors can only *use* existing snippets
- Snippet files are in a separate directory, not in content

To disable snippets entirely:

```php
// ava.php
'security' => [
    'shortcodes' => [
        'allow_php_snippets' => false,
    ],
],
```

## Custom Shortcodes

Register in `app/shortcodes.php`:

```php
<?php
// app/shortcodes.php

use Ava\Application;

$shortcodes = Application::getInstance()->shortcodes();
$shortcodes->register('greeting', function (array $attrs) {
    $name = $attrs['name'] ?? 'World';
    return "Hello, " . htmlspecialchars($name) . "!";
});

// Using the app
$shortcodes->register('recent_posts', function (array $attrs) {
    $app = \\Ava\\Application::getInstance();
    $count = (int) ($attrs['count'] ?? 5);
    
    $posts = $app->repository()->published('post');
    $posts = array_slice($posts, 0, $count);
    
    $html = '<ul class="recent-posts">';
    foreach ($posts as $post) {
        $url = $app->router()->urlFor('post', $post->slug());
        $html .= '<li><a href="' . $url . '">' . htmlspecialchars($post->title()) . '</a></li>';
    }
    $html .= '</ul>';
    
    return $html;
});
```

## Processing Order

1. Markdown is rendered to HTML
2. Shortcodes are processed in the HTML output

This means shortcodes can output raw HTML without escaping.

## Security Notes

- Snippet names are validated (no path traversal)
- Snippets can be disabled via `security.shortcodes.allow_php_snippets`
- Unknown shortcodes are left as-is (not an error)
