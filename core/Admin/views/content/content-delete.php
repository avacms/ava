<?php
/**
 * Content Delete Confirmation View
 * 
 * Available variables:
 * - $type: Content type slug
 * - $item: Content item to delete
 * - $typeConfig: Content type configuration
 * - $csrf: CSRF token
 * - $site: Site configuration
 * - $admin_url: Admin base URL
 */

$hasError = isset($_GET['error']);
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
            <?php if ($hasError && $_GET['error'] === 'confirm'): ?>
            <div class="alert alert-danger">
                <span class="material-symbols-rounded">error</span>
                Please type the slug exactly to confirm deletion.
            </div>
            <?php endif; ?>

            <div class="alert alert-danger">
                <span class="material-symbols-rounded">delete_forever</span>
                <div>
                    <strong>This action cannot be undone!</strong>
                    <p class="mt-2">You are about to permanently delete "<?= htmlspecialchars($item->title()) ?>".</p>
                    <p>A backup will be saved to <code>storage/backups/</code> but the original file will be removed.</p>
                </div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/<?= htmlspecialchars($item->slug()) ?>/delete">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

                <div class="delete-preview">
                    <div class="list-item">
                        <span class="list-label">Title</span>
                        <span class="list-value"><?= htmlspecialchars($item->title()) ?></span>
                    </div>
                    <div class="list-item">
                        <span class="list-label">Slug</span>
                        <code class="text-sm"><?= htmlspecialchars($item->slug()) ?></code>
                    </div>
                    <div class="list-item">
                        <span class="list-label">Status</span>
                        <span class="badge <?= $item->isPublished() ? 'badge-success' : 'badge-warning' ?>">
                            <?= htmlspecialchars($item->status()) ?>
                        </span>
                    </div>
                    <div class="list-item">
                        <span class="list-label">File</span>
                        <code class="text-xs"><?= htmlspecialchars(basename($item->filePath())) ?></code>
                    </div>
                    <?php if ($item->date()): ?>
                    <div class="list-item">
                        <span class="list-label">Date</span>
                        <span class="text-sm"><?= $item->date()->format('M j, Y') ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="form-group mt-5">
                    <label for="confirm" class="form-label">
                        Type <strong><?= htmlspecialchars($item->slug()) ?></strong> to confirm:
                    </label>
                    <input type="text" id="confirm" name="confirm" class="form-control" 
                           autocomplete="off"
                           placeholder="<?= htmlspecialchars($item->slug()) ?>">
                </div>

                <div class="form-actions mt-5">
                    <button type="submit" class="btn btn-danger" id="delete-btn" disabled>
                        <span class="material-symbols-rounded">delete_forever</span>
                        Delete Permanently
                    </button>
                    <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>" class="btn btn-secondary">
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
                <ul class="text-sm text-secondary list-compact">
                    <li>The content file will be deleted</li>
                    <li>A backup is saved to <code>storage/backups/</code></li>
                    <li>The content index is rebuilt</li>
                    <li>Cached pages are cleared</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const confirmInput = document.getElementById('confirm');
const deleteBtn = document.getElementById('delete-btn');
const expectedSlug = <?= json_encode($item->slug()) ?>;

confirmInput.addEventListener('input', function() {
    deleteBtn.disabled = this.value !== expectedSlug;
});
</script>
