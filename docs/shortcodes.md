# Shortcodes

Shortcodes are little magic tags you can put in your Markdown to do things that Markdown can't do.

## Why use them?

Sometimes you need more than just text and images. Shortcodes let you:
- **✨ Insert dynamic data** (like the current year).
- **🧩 Add complex HTML** (like buttons or cards) without writing HTML in your post.
- **♻️ Reuse content** across multiple pages.

## How they look

They look like tags in square brackets:

```markdown
Hello, it is currently [year].

[button url="/contact"]Get in Touch[/button]
```

## Built-in Shortcodes

Ava comes with a few handy ones out of the box:

| Shortcode | What it does |
|-----------|--------------|
| `[year]` | Prints the current year (great for footers!). |
| `[site_name]` | Prints your site's name. |
| `[email]me@example.com[/email]` | Creates a spam-proof email link. |
| `[button url="/"]Click Me[/button]` | Creates a styled button. |

## Custom Snippets

The most powerful feature is the `[snippet]` shortcode. It lets you write a small PHP file and use it anywhere in your content.

### 1. Create the snippet
Make a file at `snippets/alert.php`:

```php
<div class="alert" style="background: #eee; padding: 1rem;">
    <strong>Note:</strong> <?= $content ?>
</div>
```

### 2. Use it in Markdown
```markdown
[snippet name="alert"]
This is a very important note!
[/snippet]
```

Want to learn more about customizing your site's look? Check out the [Themes guide](themes.md).

This keeps your content clean and your design consistent.


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
