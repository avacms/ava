<?php
/**
 * Content Edit View - Unified File Editor
 * 
 * Available variables:
 * - $type: Content type slug
 * - $item: Content item being edited
 * - $typeConfig: Content type configuration
 * - $taxonomyConfig: All taxonomy configurations
 * - $availableTerms: Terms available for each taxonomy
 * - $error: Error message (if any)
 * - $securityWarnings: Security warning messages
 * - $csrf: CSRF token
 * - $site: Site configuration
 * - $admin_url: Admin base URL
 */

$taxonomiesForType = $typeConfig['taxonomies'] ?? [];
$usesDate = in_array($typeConfig['sorting'] ?? '', ['date_desc', 'date_asc'], true);

// Get current filename (without .md extension)
$currentFilename = isset($_POST['filename']) ? $_POST['filename'] : pathinfo(basename($item->filePath()), PATHINFO_FILENAME);

// Get the actual file content from disk to preserve exact formatting
if (isset($_POST['file_content'])) {
    $currentFileContent = $_POST['file_content'];
} else {
    // Read the original file to preserve exact YAML formatting
    $filePath = $item->filePath();
    if (file_exists($filePath)) {
        $currentFileContent = file_get_contents($filePath);
    } else {
        // Fallback: reconstruct from item data (shouldn't normally happen)
        $frontmatter = [];
        $frontmatter['id'] = $item->id() ?? '';
        $frontmatter['title'] = $item->title();
        $frontmatter['slug'] = $item->slug();
        $frontmatter['status'] = $item->status();
        if ($item->date()) {
            $frontmatter['date'] = $item->date()->format('Y-m-d');
        }
        $yaml = \Symfony\Component\Yaml\Yaml::dump($frontmatter, 2, 2);
        $currentFileContent = "---\n" . trim($yaml) . "\n---\n\n" . $item->rawContent();
    }
}

$typeLabel = rtrim($typeConfig['label'] ?? ucfirst($type), 's');

// JSON encode data for JavaScript
$jsConfig = [
    'usesDate' => $usesDate,
    'taxonomies' => $taxonomiesForType,
    'availableTerms' => $availableTerms,
    'taxonomyConfig' => $taxonomyConfig,
];

// Check for success message
$saved = isset($_GET['saved']) || isset($successMessage);
$successMsg = $successMessage ?? 'Changes saved successfully.';
?>

<form method="POST" action="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/edit?file=<?= htmlspecialchars($fileParam) ?>" class="editor-form" id="editor-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="_file_mtime" value="<?= htmlspecialchars((string) ($fileMtime ?? 0)) ?>">

    <?php if ($saved): ?>
    <div class="alert alert-success mb-4">
        <span class="material-symbols-rounded">check_circle</span>
        <div><?= htmlspecialchars($successMsg) ?></div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger mb-4">
        <span class="material-symbols-rounded">error</span>
        <div><?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($securityWarnings)): ?>
    <div class="alert alert-warning mb-4">
        <span class="material-symbols-rounded">warning</span>
        <div>
            <strong>Content blocked:</strong> <?= htmlspecialchars($securityWarnings[0]) ?>
            <?php if (count($securityWarnings) > 1): ?>
            <span class="text-xs">(+<?= count($securityWarnings) - 1 ?> more)</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="editor-container">
        <?php $fileIcon = 'description'; ?>
        <?php include __DIR__ . '/_editor-file-header.php'; ?>
        
        <div class="editor-wrapper">
            <div id="editor" class="codemirror-container" data-codemirror="yaml-frontmatter"></div>
            <textarea id="file_content" name="file_content" class="editor-hidden-input"><?= htmlspecialchars($currentFileContent) ?></textarea>
        </div>
    </div>

    <div class="editor-footer">
        <div class="editor-footer-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-rounded">save</span>
                Save <?= htmlspecialchars($typeLabel) ?>
            </button>
            <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>" class="btn btn-secondary">
                Cancel
            </a>
            <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/edit?file=<?= htmlspecialchars($fileParam) ?>" 
               class="btn btn-secondary" title="Switch to visual field editor">
                <span class="material-symbols-rounded">view_compact</span>
                Field Editor
            </a>
            <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/<?= htmlspecialchars($item->slug()) ?>/delete" 
               class="btn btn-danger-outline">
                <span class="material-symbols-rounded">delete</span>
                Delete
            </a>
        </div>
    </div>
</form>

<script>
(function() {
    const editorContainer = document.getElementById('editor');
    const editorCard = document.querySelector('.editor-container');
    const hiddenInput = document.getElementById('file_content');
    const form = document.getElementById('editor-form');
    const wrapBtn = document.getElementById('wrap-toggle');
    const fullscreenBtn = document.getElementById('fullscreen-toggle');
    let cmEditor = null;
    
    // Line wrap mode labels and icons
    const wrapModeLabels = {
        'full': 'Full width',
        'narrow': 'Narrow column',
        'none': 'No wrapping'
    };
    const wrapModeIcons = {
        'full': 'wrap_text',
        'narrow': 'view_column',
        'none': 'more_horiz'
    };
    
    function updateWrapButton(mode) {
        if (!wrapBtn) return;
        wrapBtn.title = 'Line wrap: ' + (wrapModeLabels[mode] || 'Full width');
        const icon = wrapBtn.querySelector('.material-symbols-rounded');
        if (icon) icon.textContent = wrapModeIcons[mode] || 'wrap_text';
    }
    
    // Initialize CodeMirror when ready
    async function initEditor() {
        if (typeof window.AvaCodeMirror !== 'undefined') {
            const content = hiddenInput.value;
            cmEditor = await window.AvaCodeMirror.createEditor(editorContainer, {
                content: content,
                language: 'yaml-frontmatter',
                onChange: function(value) {
                    hiddenInput.value = value;
                }
            });
            
            // Apply saved wrap mode
            const savedMode = window.AvaCodeMirror.getSavedWrapMode();
            window.AvaCodeMirror.setLineWrap(editorContainer, savedMode);
            updateWrapButton(savedMode);
        } else {
            // Retry if AvaCodeMirror not yet loaded
            setTimeout(initEditor, 50);
        }
    }
    
    // Wrap toggle
    if (wrapBtn) {
        wrapBtn.addEventListener('click', function() {
            if (!window.AvaCodeMirror) return;
            const newMode = window.AvaCodeMirror.cycleLineWrap(editorContainer);
            updateWrapButton(newMode);
        });
    }
    
    // Fullscreen toggle
    let isFullscreen = false;
    function toggleFullscreen() {
        isFullscreen = !isFullscreen;
        editorCard.classList.toggle('editor-fullscreen', isFullscreen);
        const icon = fullscreenBtn?.querySelector('.material-symbols-rounded');
        if (icon) icon.textContent = isFullscreen ? 'fullscreen_exit' : 'fullscreen';
        if (isFullscreen && cmEditor && cmEditor.focus) {
            // Focus editor after a tick
            setTimeout(() => cmEditor.focus(), 0);
        }
    }
    
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', toggleFullscreen);
    }
    
    // Sync to hidden input before submit
    form.addEventListener('submit', function() {
        if (cmEditor) {
            hiddenInput.value = window.AvaCodeMirror.getValue(cmEditor);
        }
    });
    
    // Ctrl+S to save, Escape to exit fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isFullscreen) {
            toggleFullscreen();
            return;
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            if (cmEditor) {
                hiddenInput.value = window.AvaCodeMirror.getValue(cmEditor);
            }
            form.submit();
        }
    });
    
    // Validate filename on input
    document.getElementById('filename').addEventListener('input', function(e) {
        let val = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/--+/g, '-');
        if (val !== this.value) {
            this.value = val;
        }
    });
    
    initEditor();
})();
</script>

