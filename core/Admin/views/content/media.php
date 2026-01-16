<?php
/**
 * Media Uploader - Content Only View
 * 
 * Available variables:
 * - $error: Error message (if any)
 * - $success: Success message (if any)
 * - $uploadedFiles: Array of recently uploaded files
 * - $existingFolders: Array of existing folders in media directory
 * - $uploadLimits: Upload limits and capabilities
 * - $currentFolder: Currently browsing folder
 * - $currentFiles: Files in current folder (paginated)
 * - $organizeByDate: Whether date-based organization is enabled by default
 * - $dateFolder: Current date folder (e.g., "2024/01")
 * - $mediaPath: Full path to media directory
 * - $isWritable: Whether media directory is writable
 * - $csrf: CSRF token
 * - $sortBy: Current sort field (date, name, size)
 * - $sortDir: Current sort direction (asc, desc)
 * - $page: Current page number
 * - $totalPages: Total number of pages
 * - $totalFiles: Total number of files
 * - $perPage: Files per page
 * - $mediaAlias: Path alias for shortlinks (e.g., "@media:")
 */

$formatBytes = function($bytes, $precision = 1) {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, $precision) . ' ' . $units[$i];
};

$formatDate = function($timestamp) {
    return date('M j, Y g:i A', $timestamp);
};

$extensionsString = implode(', ', $uploadLimits['allowed_extensions'] ?? ['jpg', 'png', 'gif', 'webp']);

// Build sort URL helper
$buildUrl = function($params = []) use ($admin_url, $currentFolder, $sortBy, $sortDir, $page) {
    $base = $admin_url . '/media';
    $query = array_merge([
        'folder' => $currentFolder,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'page' => $page,
    ], $params);
    
    // Remove empty values
    $query = array_filter($query, fn($v) => $v !== '' && $v !== null);
    
    return $query ? $base . '?' . http_build_query($query) : $base;
};

// Build breadcrumb parts
$breadcrumbParts = [];
if ($currentFolder !== '') {
    $parts = explode('/', $currentFolder);
    $path = '';
    foreach ($parts as $part) {
        $path .= ($path !== '' ? '/' : '') . $part;
        $breadcrumbParts[] = ['name' => $part, 'path' => $path];
    }
}
?>

<?php if (!$isWritable): ?>
<div class="alert alert-danger">
    <span class="material-symbols-rounded">error</span>
    <div>
        <strong>Media directory is not writable.</strong><br>
        Please ensure the directory exists and is writable by PHP.
    </div>
</div>
<?php endif; ?>

<?php if (!$uploadLimits['has_imagick'] && !$uploadLimits['has_gd']): ?>
<div class="alert alert-danger">
    <span class="material-symbols-rounded">warning</span>
    <div>
        <strong>No image processing extension available.</strong><br>
        Install the <code>imagick</code> or <code>gd</code> PHP extension to enable uploads.
    </div>
</div>
<?php endif; ?>

<!-- Upload Form - Full Width -->
<div class="card">
    <div class="card-header">
        <span class="card-title"><span class="material-symbols-rounded">cloud_upload</span> Upload Images</span>
    </div>
    <div class="card-body">
        <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/media" enctype="multipart/form-data" id="upload-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            
            <div class="form-row form-row-2">
                <div class="form-group">
                    <label class="form-label" for="media">Select Images</label>
                    <input type="file" name="media[]" id="media" multiple accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif" class="form-control" required>
                    <p class="form-hint"><?= htmlspecialchars(strtoupper($extensionsString)) ?> — Max <?= htmlspecialchars($uploadLimits['max_file_size_formatted']) ?></p>
                </div>

                <div class="form-group">
                    <label class="form-label" for="folder_select">Destination</label>
                    <div class="upload-dest-row">
                        <select name="folder_mode" id="folder_select" class="form-control">
                            <?php if ($organizeByDate): ?>
                            <option value="date" selected>📅 <?= htmlspecialchars($dateFolder) ?></option>
                            <?php endif; ?>
                            <option value="root">📁 /media/</option>
                            <?php foreach ($existingFolders as $folder): ?>
                            <option value="existing" data-folder="<?= htmlspecialchars($folder) ?>">📁 /<?= htmlspecialchars($folder) ?>/</option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="existing_folder" id="existing_folder" value="">
                        <button type="submit" class="btn btn-primary" <?= (!$isWritable || (!$uploadLimits['has_imagick'] && !$uploadLimits['has_gd'])) ? 'disabled' : '' ?>>
                            <span class="material-symbols-rounded">upload</span>
                            Upload
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Recently Uploaded -->
<?php if (!empty($uploadedFiles)): ?>
<div class="card mt-4">
    <div class="card-header">
        <span class="card-title"><span class="material-symbols-rounded">check_circle</span> Just Uploaded</span>
        <span class="badge badge-success"><?= count($uploadedFiles) ?></span>
    </div>
    <div class="table-wrap">
        <table class="table media-table">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($uploadedFiles as $file): ?>
                <tr>
                    <td>
                        <span class="file-link">
                            <span class="material-symbols-rounded file-icon">image</span>
                            <span class="file-name"><?= htmlspecialchars($file['filename']) ?></span>
                        </span>
                    </td>
                    <td class="file-actions">
                        <button type="button" class="btn btn-secondary btn-xs copy-btn" data-url="<?= htmlspecialchars($file['url']) ?>">
                            <span class="material-symbols-rounded">link</span> URL
                        </button>
                        <button type="button" class="btn btn-secondary btn-xs copy-btn" data-url="<?= htmlspecialchars($mediaAlias . $file['relative_path']) ?>">
                            <span class="material-symbols-rounded">code</span> Shortlink
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Browse Media -->
<div class="card mt-4">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">folder_open</span>
            Browse Media
        </span>
        
        <div class="card-header-actions">
            <label class="sort-label">
                <span class="material-symbols-rounded">sort</span>
                <select id="sort-select">
                    <option value="date-desc" <?= $sortBy === 'date' && $sortDir === 'desc' ? 'selected' : '' ?>>Newest</option>
                    <option value="date-asc" <?= $sortBy === 'date' && $sortDir === 'asc' ? 'selected' : '' ?>>Oldest</option>
                    <option value="name-asc" <?= $sortBy === 'name' && $sortDir === 'asc' ? 'selected' : '' ?>>A → Z</option>
                    <option value="name-desc" <?= $sortBy === 'name' && $sortDir === 'desc' ? 'selected' : '' ?>>Z → A</option>
                    <option value="size-desc" <?= $sortBy === 'size' && $sortDir === 'desc' ? 'selected' : '' ?>>Largest</option>
                    <option value="size-asc" <?= $sortBy === 'size' && $sortDir === 'asc' ? 'selected' : '' ?>>Smallest</option>
                </select>
            </label>
        </div>
    </div>
    
    <!-- Breadcrumb Navigation -->
    <div class="media-breadcrumb">
        <a href="<?= htmlspecialchars($admin_url) ?>/media" class="breadcrumb-link <?= $currentFolder === '' ? 'active' : '' ?>">
            <span class="material-symbols-rounded">home</span>
            media
        </a>
        <?php foreach ($breadcrumbParts as $crumb): ?>
        <span class="breadcrumb-sep">/</span>
        <a href="<?= htmlspecialchars($admin_url) ?>/media?folder=<?= urlencode($crumb['path']) ?>" class="breadcrumb-link <?= $crumb['path'] === $currentFolder ? 'active' : '' ?>">
            <?= htmlspecialchars($crumb['name']) ?>
        </a>
        <?php endforeach; ?>
    </div>
    
    <?php if ($currentFolder === '' && !empty($existingFolders)): ?>
    <div class="folder-bar">
        <?php foreach ($existingFolders as $folder): ?>
        <a href="<?= htmlspecialchars($admin_url) ?>/media?folder=<?= urlencode($folder) ?>" class="folder-chip">
            <span class="material-symbols-rounded">folder</span>
            <?= htmlspecialchars($folder) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($currentFiles)): ?>
    <div class="empty-state">
        <span class="material-symbols-rounded">folder_off</span>
        <p>No files in this folder</p>
    </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table media-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($currentFiles as $file): ?>
                <tr>
                    <td>
                        <a href="<?= htmlspecialchars($file['url']) ?>" target="_blank" rel="noopener" class="file-link">
                            <span class="material-symbols-rounded file-icon">image</span>
                            <span class="file-name"><?= htmlspecialchars($file['name']) ?></span>
                        </a>
                    </td>
                    <td class="text-tertiary text-sm">
                        <?= $formatBytes($file['size']) ?>
                        <?php if ($file['width'] && $file['height']): ?>
                        <span class="text-xs">· <?= $file['width'] ?>×<?= $file['height'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-tertiary text-sm"><?= $formatDate($file['modified']) ?></td>
                    <td class="file-actions">
                        <button type="button" class="btn btn-secondary btn-xs copy-btn" data-url="<?= htmlspecialchars($file['url']) ?>">
                            <span class="material-symbols-rounded">link</span> URL
                        </button>
                        <button type="button" class="btn btn-secondary btn-xs copy-btn" data-url="<?= htmlspecialchars($mediaAlias . $file['path']) ?>">
                            <span class="material-symbols-rounded">code</span> Shortlink
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
    <div class="pagination-bar">
        <span class="pagination-info text-tertiary text-sm">
            Page <?= $page ?> of <?= $totalPages ?> (<?= $totalFiles ?> files)
        </span>
        <div class="pagination-controls">
            <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $page - 1])) ?>" class="btn btn-secondary btn-sm">
                <span class="material-symbols-rounded">chevron_left</span>
            </a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => 1])) ?>" class="btn btn-secondary btn-sm">1</a>
            <?php if ($startPage > 2): ?>
            <span class="pagination-ellipsis">…</span>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $p])) ?>" class="btn <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $p ?></a>
            <?php endfor; ?>
            
            <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
            <span class="pagination-ellipsis">…</span>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $totalPages])) ?>" class="btn btn-secondary btn-sm"><?= $totalPages ?></a>
            <?php endif; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $page + 1])) ?>" class="btn btn-secondary btn-sm">
                <span class="material-symbols-rounded">chevron_right</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Folder selection handling
document.getElementById('folder_select').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const hiddenInput = document.getElementById('existing_folder');
    
    if (option.dataset.folder) {
        this.value = 'existing';
        hiddenInput.value = option.dataset.folder;
    } else {
        hiddenInput.value = '';
    }
});

// Sort handling
document.getElementById('sort-select').addEventListener('change', function() {
    const [sort, dir] = this.value.split('-');
    const url = new URL(window.location);
    url.searchParams.set('sort', sort);
    url.searchParams.set('dir', dir);
    url.searchParams.delete('page');
    window.location = url;
});

// Copy buttons
document.querySelectorAll('.copy-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const url = this.dataset.url;
        const icon = this.querySelector('.material-symbols-rounded');
        const originalIcon = icon.textContent;
        
        try {
            await navigator.clipboard.writeText(url);
            icon.textContent = 'check';
            this.classList.add('copied');
            setTimeout(() => {
                icon.textContent = originalIcon;
                this.classList.remove('copied');
            }, 1500);
        } catch (err) {
            // Fallback
            const input = document.createElement('input');
            input.value = url;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
            icon.textContent = 'check';
            this.classList.add('copied');
            setTimeout(() => {
                icon.textContent = originalIcon;
                this.classList.remove('copied');
            }, 1500);
        }
    });
});
</script>

