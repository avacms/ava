<?php
/**
 * Taxonomy Term Create View
 * 
 * Available variables:
 * - $taxonomy: Taxonomy slug
 * - $config: Taxonomy configuration
 * - $error: Error message (if any)
 * - $csrf: CSRF token
 * - $site: Site configuration
 * - $admin_url: Admin base URL
 */
?>

<div class="content-layout">
    <div class="card content-main">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">add</span>
                New Term
            </span>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/taxonomy/<?= htmlspecialchars($taxonomy) ?>/create">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                
                <div class="form-group">
                    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" required
                           placeholder="e.g., Getting Started"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                    <p class="form-hint">The display name for this term.</p>
                </div>

                <div class="form-group">
                    <label for="slug" class="form-label">Slug</label>
                    <input type="text" id="slug" name="slug" class="form-control" 
                           pattern="[a-z0-9-]+"
                           placeholder="e.g., getting-started (auto-generated if empty)"
                           value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
                    <p class="form-hint">URL-safe identifier. Lowercase letters, numbers, and hyphens only.</p>
                </div>

                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3"
                              placeholder="Optional description for this term..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span class="material-symbols-rounded">add</span>
                        Create Term
                    </button>
                    <a href="<?= htmlspecialchars($admin_url) ?>/taxonomy/<?= htmlspecialchars($taxonomy) ?>" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="config-sidebar">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <span class="material-symbols-rounded">info</span>
                    About Terms
                </span>
            </div>
            <div class="card-body">
                <p class="text-sm text-secondary">
                    Terms are stored in YAML files at:<br>
                    <code class="text-xs">content/_taxonomies/<?= htmlspecialchars($taxonomy) ?>.yml</code>
                </p>
                <p class="text-sm text-secondary mt-3">
                    After creating a term, you can assign content to it by adding the term to your content's frontmatter.
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-generate slug from name
document.getElementById('name').addEventListener('input', function() {
    const slugField = document.getElementById('slug');
    if (!slugField.value) {
        slugField.placeholder = this.value
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/[\s_]+/g, '-')
            .replace(/-+/g, '-')
            .trim() || 'auto-generated';
    }
});
</script>
