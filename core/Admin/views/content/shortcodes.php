<?php
/**
 * Shortcodes Reference - Content Only View
 * 
 * Available variables:
 * - $shortcodes: Array of registered shortcode names
 * - $snippets: Array of available snippets
 */

$shortcodeInfo = [
    'year' => ['syntax' => '[year]', 'desc' => 'Current year'],
    'date' => ['syntax' => '[date format="Y-m-d"]', 'desc' => 'Current date'],
    'site_name' => ['syntax' => '[site_name]', 'desc' => 'Site name'],
    'site_url' => ['syntax' => '[site_url]', 'desc' => 'Site URL'],
    'email' => ['syntax' => '[email]you@example.com[/email]', 'desc' => 'Obfuscated email'],
    'snippet' => ['syntax' => '[snippet name="..."]', 'desc' => 'Include snippet'],
    'include' => ['syntax' => '[include file="..."]', 'desc' => 'Include file'],
];
?>

<div class="grid grid-2">
    <!-- Shortcodes -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">bolt</span>
                Available Shortcodes
            </span>
            <span class="badge badge-accent"><?= count($shortcodes) ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Usage</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shortcodes as $tag): 
                        $info = $shortcodeInfo[$tag] ?? ['syntax' => "[{$tag}]", 'desc' => 'Custom shortcode'];
                    ?>
                    <tr>
                        <td><span class="badge badge-muted"><?= htmlspecialchars($tag) ?></span></td>
                        <td><code class="text-xs"><?= htmlspecialchars($info['syntax']) ?></code></td>
                        <td><span class="text-sm text-secondary"><?= htmlspecialchars($info['desc']) ?></span></td>
                        <td>
                            <button class="btn btn-xs btn-secondary copy-btn" data-copy="<?= htmlspecialchars($info['syntax']) ?>">
                                <span class="material-symbols-rounded">content_copy</span>
                                Copy
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($shortcodes)): ?>
                    <tr><td colspan="3" class="text-tertiary text-center">No shortcodes registered</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Snippets -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">widgets</span>
                Available Snippets
            </span>
            <span class="badge badge-accent"><?= count($snippets) ?></span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Usage</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($snippets)): ?>
                    <?php foreach ($snippets as $name => $snippet): 
                        $usage = '[snippet name="' . $name . '"]';
                    ?>
                    <tr>
                        <td>
                            <span class="badge badge-muted"><?= htmlspecialchars($name) ?></span>
                        </td>
                        <td><code class="text-xs"><?= htmlspecialchars($usage) ?></code></td>
                        <td>
                            <button class="btn btn-xs btn-secondary copy-btn" data-copy="<?= htmlspecialchars($usage) ?>">
                                <span class="material-symbols-rounded">content_copy</span>
                                Copy
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="3" class="text-tertiary text-center">No snippets in <code>snippets/</code></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Usage Guide -->
<div class="card mt-4">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">help</span>
            Usage Guide
        </span>
    </div>
    <div class="card-body">
        <p class="text-secondary text-sm mb-3">
            Shortcodes are processed after Markdown rendering. Use them in your content files:
        </p>
        <div class="list-item"><span class="list-label">Basic</span><code>[shortcode]</code></div>
        <div class="list-item"><span class="list-label">With attributes</span><code>[shortcode attr="value"]</code></div>
        <div class="list-item"><span class="list-label">With content</span><code>[shortcode]content[/shortcode]</code></div>
        <div class="list-item"><span class="list-label">Snippet variables</span><code>$params, $content, $app, $ava</code></div>
    </div>
</div>

