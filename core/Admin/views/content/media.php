<?php
/**
 * Media Browser - Professional Media Management
 * 
 * Available variables:
 * - $error: Error message (if any)
 * - $success: Success message (if any)
 * - $uploadedFiles: Array of recently uploaded files
 * - $existingFolders: Array of all folders (flat list for dropdown)
 * - $subfolders: Array of subfolders in current directory
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
 * - $viewMode: Current view mode (grid, list)
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

$formatDateShort = function($timestamp) {
    return date('M j, Y', $timestamp);
};

$formatTime = function($timestamp) {
    return date('g:i A', $timestamp);
};

$extensionsString = implode(', ', $uploadLimits['allowed_extensions'] ?? ['jpg', 'png', 'gif', 'webp']);
$acceptTypes = 'image/jpeg,image/png,image/gif,image/webp,image/svg+xml,image/avif';

// Build URL helper
$buildUrl = function($params = []) use ($admin_url, $currentFolder, $sortBy, $sortDir, $page, $viewMode) {
    $base = $admin_url . '/media';
    $query = array_merge([
        'folder' => $currentFolder,
        'sort' => $sortBy,
        'dir' => $sortDir,
        'view' => $viewMode,
        'page' => $page,
    ], $params);
    
    // Remove default/empty values
    if ($query['folder'] === '') unset($query['folder']);
    if ($query['sort'] === 'date' && $query['dir'] === 'desc') {
        unset($query['sort'], $query['dir']);
    }
    if ($query['view'] === 'list') unset($query['view']); // list is default
    if ($query['page'] === 1) unset($query['page']);
    
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

$canUpload = $isWritable && ($uploadLimits['has_imagick'] || $uploadLimits['has_gd']);
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

<!-- Upload Section -->
<div class="upload-section <?= $canUpload ? '' : 'disabled' ?>">
    <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/media<?= $currentFolder !== '' ? '?folder=' . urlencode($currentFolder) : '' ?>" 
          enctype="multipart/form-data" id="upload-form">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="folder_mode" id="folder-mode" value="<?= $organizeByDate ? 'date' : 'root' ?>">
        
        <!-- Upload Toolbar -->
        <div class="upload-toolbar">
            <div class="upload-toolbar-left">
                <label for="existing_folder">Upload to:</label>
                <select name="existing_folder" id="existing_folder" class="form-control form-control-sm">
                    <option value="" <?= !$organizeByDate && $currentFolder === '' ? 'selected' : '' ?>>/media/</option>
                    <?php if ($organizeByDate): ?>
                    <option value="__date__" selected>/<?= htmlspecialchars($dateFolder) ?>/</option>
                    <?php endif; ?>
                    <?php foreach ($existingFolders as $folder): ?>
                    <option value="<?= htmlspecialchars($folder) ?>">/<?= htmlspecialchars($folder) ?>/</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="upload-toolbar-right">
                <span class="upload-formats"><?= htmlspecialchars(strtoupper($extensionsString)) ?></span>
                <span class="upload-limit">Max <?= htmlspecialchars($uploadLimits['max_file_size_formatted']) ?></span>
            </div>
        </div>
        
        <!-- Dropzone -->
        <div class="media-dropzone" id="dropzone">
            <input type="file" name="media[]" id="file-input" multiple accept="<?= $acceptTypes ?>" class="dropzone-input" <?= $canUpload ? '' : 'disabled' ?>>
            
            <div class="dropzone-content">
                <div class="dropzone-icon">
                    <span class="material-symbols-rounded">cloud_upload</span>
                </div>
                <div class="dropzone-text">
                    <span class="dropzone-main">Drag & drop images here</span>
                    <span class="dropzone-sub">or <button type="button" class="dropzone-browse" onclick="document.getElementById('file-input').click()">browse files</button></span>
                </div>
            </div>
            
            <!-- Upload Progress -->
            <div class="dropzone-progress" id="upload-progress" style="display: none;">
                <div class="progress-bar">
                    <div class="progress-fill" id="progress-fill"></div>
                </div>
                <div class="progress-text" id="progress-text">Uploading...</div>
            </div>
            
            <!-- Preview Area -->
            <div class="dropzone-preview" id="preview-area"></div>
        </div>
    </form>
</div>

<!-- Recently Uploaded -->
<?php if (!empty($uploadedFiles)): ?>
<div class="card upload-success-card">
    <div class="card-header">
        <span class="card-title"><span class="material-symbols-rounded">check_circle</span> Just Uploaded</span>
        <span class="badge badge-success"><?= count($uploadedFiles) ?></span>
    </div>
    <div class="upload-success-grid">
        <?php foreach ($uploadedFiles as $file): ?>
        <div class="upload-success-item">
            <img src="<?= htmlspecialchars($file['url']) ?>" alt="" class="upload-thumb" loading="lazy">
            <div class="upload-success-info">
                <span class="upload-success-name"><?= htmlspecialchars($file['filename']) ?></span>
                <div class="upload-success-actions">
                    <button type="button" class="btn btn-secondary btn-xs copy-btn" data-copy="<?= htmlspecialchars($file['url']) ?>" title="Copy URL">
                        <span class="material-symbols-rounded">link</span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-xs copy-btn" data-copy="<?= htmlspecialchars($mediaAlias . $file['relative_path']) ?>" title="Copy Shortlink">
                        <span class="material-symbols-rounded">code</span>
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Media Browser -->
<div class="card media-browser">
    <div class="media-browser-header">
        <div class="media-browser-nav">
            <!-- Breadcrumb -->
            <div class="media-breadcrumb">
                <a href="<?= htmlspecialchars($buildUrl(['folder' => '', 'page' => 1])) ?>" class="breadcrumb-link <?= $currentFolder === '' ? 'active' : '' ?>">
                    <span class="material-symbols-rounded">home</span>
                    media
                </a>
                <?php foreach ($breadcrumbParts as $crumb): ?>
                <span class="breadcrumb-sep">/</span>
                <a href="<?= htmlspecialchars($buildUrl(['folder' => $crumb['path'], 'page' => 1])) ?>" 
                   class="breadcrumb-link <?= $crumb['path'] === $currentFolder ? 'active' : '' ?>">
                    <?= htmlspecialchars($crumb['name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="media-browser-controls">
            <!-- View Toggle -->
            <div class="view-toggle">
                <a href="<?= htmlspecialchars($buildUrl(['view' => 'grid', 'page' => 1])) ?>" 
                   class="view-toggle-btn <?= $viewMode === 'grid' ? 'active' : '' ?>" title="Grid view">
                    <span class="material-symbols-rounded">grid_view</span>
                </a>
                <a href="<?= htmlspecialchars($buildUrl(['view' => 'list', 'page' => 1])) ?>" 
                   class="view-toggle-btn <?= $viewMode === 'list' ? 'active' : '' ?>" title="List view">
                    <span class="material-symbols-rounded">view_list</span>
                </a>
            </div>
            
            <!-- Sort -->
            <div class="sort-control">
                <select id="sort-select" class="form-control form-control-sm">
                    <option value="date-desc" <?= $sortBy === 'date' && $sortDir === 'desc' ? 'selected' : '' ?>>Newest</option>
                    <option value="date-asc" <?= $sortBy === 'date' && $sortDir === 'asc' ? 'selected' : '' ?>>Oldest</option>
                    <option value="name-asc" <?= $sortBy === 'name' && $sortDir === 'asc' ? 'selected' : '' ?>>A → Z</option>
                    <option value="name-desc" <?= $sortBy === 'name' && $sortDir === 'desc' ? 'selected' : '' ?>>Z → A</option>
                    <option value="size-desc" <?= $sortBy === 'size' && $sortDir === 'desc' ? 'selected' : '' ?>>Largest</option>
                    <option value="size-asc" <?= $sortBy === 'size' && $sortDir === 'asc' ? 'selected' : '' ?>>Smallest</option>
                </select>
            </div>
        </div>
    </div>
    
    <!-- Subfolders -->
    <?php if (!empty($subfolders)): ?>
    <div class="media-folders<?= empty($currentFiles) ? ' no-border' : '' ?>">
        <?php foreach ($subfolders as $folder): ?>
        <a href="<?= htmlspecialchars($buildUrl(['folder' => $folder['path'], 'page' => 1])) ?>" class="media-folder">
            <span class="material-symbols-rounded folder-icon">folder</span>
            <span class="folder-name"><?= htmlspecialchars($folder['name']) ?></span>
            <span class="folder-count"><?= $folder['item_count'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <!-- Files -->
    <?php if (empty($currentFiles) && empty($subfolders)): ?>
    <div class="empty-state">
        <span class="material-symbols-rounded">folder_off</span>
        <p>No files in this folder</p>
        <p class="empty-hint">Drag images here or click to upload</p>
    </div>
    <?php elseif (empty($currentFiles)): ?>
    <!-- Only folders, no files -->
    <?php elseif ($viewMode === 'grid'): ?>
    <!-- Grid View -->
    <div class="media-grid" id="media-grid">
        <?php foreach ($currentFiles as $file): ?>
        <div class="media-item" data-path="<?= htmlspecialchars($file['path']) ?>">
            <div class="media-item-preview">
                <img data-src="<?= htmlspecialchars($file['url']) ?>" alt="<?= htmlspecialchars($file['name']) ?>" class="lazy-image">
                <div class="media-item-overlay">
                    <a href="<?= htmlspecialchars($file['url']) ?>" target="_blank" rel="noopener" class="overlay-btn" title="Open">
                        <span class="material-symbols-rounded">open_in_new</span>
                    </a>
                    <button type="button" class="overlay-btn copy-btn" data-copy="<?= htmlspecialchars($file['url']) ?>" title="Copy URL">
                        <span class="material-symbols-rounded">link</span>
                    </button>
                    <button type="button" class="overlay-btn copy-btn" data-copy="<?= htmlspecialchars($mediaAlias . $file['path']) ?>" title="Copy Shortlink">
                        <span class="material-symbols-rounded">code</span>
                    </button>
                    <?php if ($canUpload): ?>
                    <button type="button" class="overlay-btn delete-btn" data-path="<?= htmlspecialchars($file['path']) ?>" data-name="<?= htmlspecialchars($file['name']) ?>" title="Delete">
                        <span class="material-symbols-rounded">delete</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="media-item-info">
                <span class="media-item-name" title="<?= htmlspecialchars($file['name']) ?>"><?= htmlspecialchars($file['name']) ?></span>
                <span class="media-item-meta">
                    <span class="meta-primary"><?= $formatBytes($file['size']) ?><?php if ($file['width'] && $file['height']): ?> · <?= $file['width'] ?>×<?= $file['height'] ?><?php endif; ?></span>
                    <span class="meta-secondary"><?= $formatDateShort($file['modified']) ?> · <?= $formatTime($file['modified']) ?></span>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <!-- List View -->
    <div class="table-wrap">
        <table class="table media-table">
            <thead>
                <tr>
                    <th></th>
                    <th>
                        <a href="<?= htmlspecialchars($buildUrl(['sort' => 'name', 'dir' => ($sortBy === 'name' && $sortDir === 'asc') ? 'desc' : 'asc', 'page' => 1])) ?>" class="sort-header<?= $sortBy === 'name' ? ' active' : '' ?>">
                            Name
                            <?php if ($sortBy === 'name'): ?>
                            <span class="sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= htmlspecialchars($buildUrl(['sort' => 'size', 'dir' => ($sortBy === 'size' && $sortDir === 'desc') ? 'asc' : 'desc', 'page' => 1])) ?>" class="sort-header<?= $sortBy === 'size' ? ' active' : '' ?>">
                            Size
                            <?php if ($sortBy === 'size'): ?>
                            <span class="sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= htmlspecialchars($buildUrl(['sort' => 'date', 'dir' => ($sortBy === 'date' && $sortDir === 'desc') ? 'asc' : 'desc', 'page' => 1])) ?>" class="sort-header<?= $sortBy === 'date' ? ' active' : '' ?>">
                            Modified
                            <?php if ($sortBy === 'date'): ?>
                            <span class="sort-indicator"><?= $sortDir === 'asc' ? '▲' : '▼' ?></span>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($currentFiles as $file): ?>
                <tr>
                    <td class="media-list-icon">
                        <span class="material-symbols-rounded">image</span>
                    </td>
                    <td>
                        <a href="<?= htmlspecialchars($file['url']) ?>" target="_blank" rel="noopener" class="file-link">
                            <?= htmlspecialchars($file['name']) ?>
                        </a>
                    </td>
                    <td class="media-file-size">
                        <span class="file-size"><?= $formatBytes($file['size']) ?></span>
                        <?php if ($file['width'] && $file['height']): ?>
                        <span class="file-dimensions"><?= $file['width'] ?>×<?= $file['height'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="media-file-date">
                        <span class="file-date"><?= $formatDateShort($file['modified']) ?></span>
                        <span class="file-time"><?= $formatTime($file['modified']) ?></span>
                    </td>
                    <td class="file-actions">
                        <button type="button" class="btn btn-secondary btn-xs copy-btn" data-copy="<?= htmlspecialchars($file['url']) ?>" title="Copy URL">
                            <span class="material-symbols-rounded">link</span>
                        </button>
                        <button type="button" class="btn btn-secondary btn-xs copy-btn" data-copy="<?= htmlspecialchars($mediaAlias . $file['path']) ?>" title="Copy Shortlink">
                            <span class="material-symbols-rounded">code</span>
                        </button>
                        <?php if ($canUpload): ?>
                        <button type="button" class="btn btn-secondary btn-xs delete-btn" data-path="<?= htmlspecialchars($file['path']) ?>" data-name="<?= htmlspecialchars($file['name']) ?>" title="Delete">
                            <span class="material-symbols-rounded">delete</span>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Pagination -->
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
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-backdrop" id="delete-modal" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Delete File</h3>
            <button type="button" class="modal-close" onclick="closeDeleteModal()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/media<?= $currentFolder !== '' ? '?folder=' . urlencode($currentFolder) : '' ?>" id="delete-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="delete_file">
            <input type="hidden" name="file_path" id="delete-file-path" value="">
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="delete-file-name"></strong>?</p>
                <p class="text-tertiary text-sm">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');
    const progressArea = document.getElementById('upload-progress');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const previewArea = document.getElementById('preview-area');
    const folderSelect = document.getElementById('existing_folder');
    
    // Lazy load images in grid view
    function lazyLoadImages() {
        const grid = document.getElementById('media-grid');
        if (!grid) return;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        img.classList.remove('lazy-image');
                        observer.unobserve(img);
                    }
                }
            });
        }, { rootMargin: '100px' });
        
        grid.querySelectorAll('.lazy-image').forEach(img => {
            observer.observe(img);
        });
    }
    
    // Initialize lazy loading
    lazyLoadImages();
    
    // Handle folder select with __date__ option
    if (folderSelect) {
        folderSelect.addEventListener('change', function() {
            const folderMode = uploadForm.querySelector('input[name="folder_mode"]');
            if (this.value === '__date__') {
                folderMode.value = 'date';
            } else if (this.value === '') {
                folderMode.value = 'root';
            } else {
                folderMode.value = 'existing';
            }
        });
    }
    
    // Drag and drop handling
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(event => {
        dropzone.addEventListener(event, e => {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    ['dragenter', 'dragover'].forEach(event => {
        dropzone.addEventListener(event, () => {
            dropzone.classList.add('dragover');
        });
    });
    
    ['dragleave', 'drop'].forEach(event => {
        dropzone.addEventListener(event, () => {
            dropzone.classList.remove('dragover');
        });
    });
    
    dropzone.addEventListener('drop', e => {
        if (dropzone.classList.contains('disabled')) return;
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFiles(files);
        }
    });
    
    fileInput.addEventListener('change', () => {
        if (fileInput.files.length > 0) {
            handleFiles(fileInput.files);
        }
    });
    
    function handleFiles(files) {
        // Show previews
        previewArea.innerHTML = '';
        previewArea.style.display = 'flex';
        
        Array.from(files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const preview = document.createElement('div');
                preview.className = 'preview-item';
                
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                preview.appendChild(img);
                
                const name = document.createElement('span');
                name.className = 'preview-name';
                name.textContent = file.name;
                preview.appendChild(name);
                
                previewArea.appendChild(preview);
            }
        });
        
        // Auto-submit after brief delay to show previews
        setTimeout(() => {
            uploadWithProgress();
        }, 500);
    }
    
    function uploadWithProgress() {
        const formData = new FormData(uploadForm);
        
        // Handle __date__ special value
        if (formData.get('existing_folder') === '__date__') {
            formData.delete('existing_folder');
        }
        
        progressArea.style.display = 'block';
        progressFill.style.width = '0%';
        progressText.textContent = 'Uploading...';
        
        const xhr = new XMLHttpRequest();
        
        xhr.upload.addEventListener('progress', e => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = percent + '%';
                progressText.textContent = `Uploading... ${percent}%`;
            }
        });
        
        xhr.addEventListener('load', () => {
            if (xhr.status === 200) {
                progressText.textContent = 'Complete! Refreshing...';
                progressFill.style.width = '100%';
                setTimeout(() => {
                    // Navigate to the upload folder with newest first sorting
                    const targetFolder = folderSelect.value === '__date__' 
                        ? '<?= htmlspecialchars($dateFolder) ?>' 
                        : folderSelect.value;
                    const url = new URL('<?= htmlspecialchars($admin_url) ?>/media', window.location.origin);
                    if (targetFolder) {
                        url.searchParams.set('folder', targetFolder);
                    }
                    url.searchParams.set('sort', 'date');
                    url.searchParams.set('dir', 'desc');
                    window.location = url;
                }, 300);
            } else {
                progressText.textContent = 'Upload failed. Please try again.';
            }
        });
        
        xhr.addEventListener('error', () => {
            progressText.textContent = 'Upload failed. Please try again.';
        });
        
        xhr.open('POST', uploadForm.getAttribute('action'));
        xhr.send(formData);
    }
    
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
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const text = this.dataset.copy;
            const icon = this.querySelector('.material-symbols-rounded');
            const originalIcon = icon.textContent;
            
            try {
                await navigator.clipboard.writeText(text);
                icon.textContent = 'check';
                this.classList.add('copied');
                setTimeout(() => {
                    icon.textContent = originalIcon;
                    this.classList.remove('copied');
                }, 1500);
            } catch (err) {
                // Fallback
                const input = document.createElement('input');
                input.value = text;
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
    
    // Delete Modal
    const deleteModal = document.getElementById('delete-modal');
    
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const path = this.dataset.path;
            const name = this.dataset.name;
            
            document.getElementById('delete-file-path').value = path;
            document.getElementById('delete-file-name').textContent = name;
            deleteModal.style.display = 'flex';
        });
    });
    
    window.closeDeleteModal = function() {
        deleteModal.style.display = 'none';
    };
    
    deleteModal.addEventListener('click', e => {
        if (e.target === deleteModal) closeDeleteModal();
    });
    
    // ESC to close modals
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeFolderModal();
            closeDeleteModal();
        }
    });
})();
</script>

