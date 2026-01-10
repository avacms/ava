<?php
/**
 * Taxonomy Term Delete Confirmation View
 * 
 * Available variables:
 * - $taxonomy: Taxonomy slug
 * - $term: Term slug to delete
 * - $termData: Term data (name, items, etc.)
 * - $itemCount: Number of content items using this term
 * - $csrf: CSRF token
 * - $site: Site configuration
 * - $admin_url: Admin base URL
 */

$termName = $termData['name'] ?? $term;
?>

<div class="content-layout">
    <div class="card content-main">
        <div class="card-header">
            <span class="card-title">
                <span class="material-symbols-rounded">warning</span>
                Confirm Deletion
            </span>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <span class="material-symbols-rounded">warning</span>
                <div>
                    <strong>You are about to delete the term "<?= htmlspecialchars($termName) ?>"</strong>
                    <?php if ($itemCount > 0): ?>
                    <p class="mt-2">This term is currently used by <strong><?= $itemCount ?></strong> content item<?= $itemCount !== 1 ? 's' : '' ?>. 
                    The term will be removed from the taxonomy registry, but existing content files will still reference it.</p>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/taxonomy/<?= htmlspecialchars($taxonomy) ?>/<?= htmlspecialchars($term) ?>/delete">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                <?php if ($itemCount > 0): ?>
                <input type="hidden" name="force" value="1">
                <?php endif; ?>

                <div class="delete-preview">
                    <div class="list-item">
                        <span class="list-label">Term</span>
                        <span class="list-value"><?= htmlspecialchars($termName) ?></span>
                    </div>
                    <div class="list-item">
                        <span class="list-label">Slug</span>
                        <code class="text-xs"><?= htmlspecialchars($term) ?></code>
                    </div>
                    <div class="list-item">
                        <span class="list-label">Content Using</span>
                        <span class="badge <?= $itemCount > 0 ? 'badge-warning' : 'badge-muted' ?>"><?= $itemCount ?></span>
                    </div>
                </div>

                <div class="form-actions mt-5">
                    <button type="submit" class="btn btn-danger">
                        <span class="material-symbols-rounded">delete</span>
                        Delete Term
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
                    What happens?
                </span>
            </div>
            <div class="card-body">
                <p class="text-sm text-secondary">
                    The term will be removed from:<br>
                    <code class="text-xs">content/_taxonomies/<?= htmlspecialchars($taxonomy) ?>.yml</code>
                </p>
                <p class="text-sm text-secondary mt-3">
                    Content files referencing this term will <strong>not</strong> be modified. You may want to update them manually.
                </p>
            </div>
        </div>
    </div>
</div>
