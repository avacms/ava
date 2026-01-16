<?php
/**
 * Content Create View - Unified File Editor
 * 
 * Available variables:
 * - $type: Content type slug
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
$typeLabel = rtrim($typeConfig['label'] ?? ucfirst($type), 's');
$typeSingular = strtolower($typeLabel);
$usesDate = in_array($typeConfig['sorting'] ?? '', ['date_desc', 'date_asc'], true);

// Default file content for new files
if (isset($_POST['file_content'])) {
    $currentFileContent = $_POST['file_content'];
    $currentFilename = $_POST['filename'] ?? '';
} else {
    $today = date('Y-m-d');
    $slug = 'new-' . $typeSingular;
    
    $yaml = "title: New {$typeLabel}\n";
    $yaml .= "slug: {$slug}\n";
    $yaml .= "status: draft\n";
    if ($usesDate) {
        $yaml .= "date: {$today}\n";
    }
    
    $currentFileContent = "---\n{$yaml}---\n\n# New {$typeLabel}\n\nStart writing your content here...\n";
    // Generate default filename (date-prefixed for dated content types)
    $currentFilename = $usesDate ? "{$today}-{$slug}" : $slug;
}

// JSON encode data for JavaScript
$jsConfig = [
    'usesDate' => $usesDate,
    'taxonomies' => $taxonomiesForType,
    'availableTerms' => $availableTerms,
    'taxonomyConfig' => $taxonomyConfig,
    'typeLabel' => $typeLabel,
];
?>

<form method="POST" action="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/create" class="editor-form" id="editor-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

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
        <?php $fileIcon = 'add_circle'; ?>
        <?php include __DIR__ . '/_editor-file-header.php'; ?>
        
        <div class="editor-wrapper">
            <div id="editor" class="codemirror-container" data-codemirror="yaml-frontmatter"></div>
            <textarea id="file_content" name="file_content" class="editor-hidden-input"><?= htmlspecialchars($currentFileContent) ?></textarea>
        </div>
    </div>

    <div class="editor-footer">
        <div class="editor-footer-actions">
            <button type="submit" class="btn btn-primary">
                <span class="material-symbols-rounded">add</span>
                Create <?= htmlspecialchars($typeLabel) ?>
            </button>
            <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>" class="btn btn-secondary">
                Cancel
            </a>
        </div>
    </div>
</form>

<!-- Frontmatter Generator Modal -->
<div id="fm-modal" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="closeFrontmatterGenerator()"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>
                <span class="material-symbols-rounded">auto_fix_high</span>
                Generate Frontmatter
            </h3>
            <button type="button" class="btn-icon" onclick="closeFrontmatterGenerator()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="modal-body">
            <p class="text-sm text-secondary mb-4">Fill in the fields below. Any custom fields you add manually will be preserved.</p>
            
            <!-- Core Fields -->
            <fieldset class="fm-fieldset">
                <legend>Core</legend>
                <div class="form-group">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" id="fm-title" class="form-control" placeholder="Enter title...">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" id="fm-slug" class="form-control" placeholder="auto-generated from title">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select id="fm-status" class="form-control">
                            <option value="draft" selected>Draft</option>
                            <option value="published">Published</option>
                            <option value="unlisted">Unlisted</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" id="fm-date-group" style="<?= $usesDate ? '' : 'display:none;' ?>">
                        <label class="form-label">Date</label>
                        <input type="date" id="fm-date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ID <span class="text-secondary text-xs">(optional)</span></label>
                        <div class="input-group">
                            <input type="text" id="fm-id" class="form-control font-mono text-xs" placeholder="Auto-generated if empty">
                            <button type="button" class="btn btn-secondary btn-sm" onclick="generateNewId()" title="Generate ID">
                                <span class="material-symbols-rounded">refresh</span>
                            </button>
                        </div>
                    </div>
                </div>
            </fieldset>
            
            <!-- Taxonomies -->
            <?php if (!empty($taxonomiesForType)): ?>
            <fieldset class="fm-fieldset">
                <legend>Taxonomies</legend>
                <div id="fm-taxonomies">
                    <?php foreach ($taxonomiesForType as $taxName): 
                        $taxConfig = $taxonomyConfig[$taxName] ?? [];
                        $taxLabel = $taxConfig['label'] ?? ucfirst($taxName);
                        $terms = $availableTerms[$taxName] ?? [];
                    ?>
                    <div class="form-group" data-taxonomy="<?= htmlspecialchars($taxName) ?>">
                        <label class="form-label"><?= htmlspecialchars($taxLabel) ?></label>
                        <?php if (!empty($terms)): ?>
                        <select name="fm-tax-<?= htmlspecialchars($taxName) ?>[]" class="form-control fm-tax-select" multiple data-taxonomy="<?= htmlspecialchars($taxName) ?>">
                            <?php foreach ($terms as $termSlug => $termData): ?>
                            <option value="<?= htmlspecialchars($termSlug) ?>">
                                <?= htmlspecialchars($termData['name'] ?? $termSlug) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Hold Ctrl/Cmd to select multiple.</p>
                        <?php else: ?>
                        <input type="text" class="form-control fm-tax-text" data-taxonomy="<?= htmlspecialchars($taxName) ?>" 
                               placeholder="term1, term2, term3">
                        <p class="form-hint">Comma-separated. No predefined terms available.</p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
            <?php endif; ?>
            
            <!-- SEO Fields -->
            <fieldset class="fm-fieldset">
                <legend>SEO</legend>
                <div class="form-group">
                    <label class="form-label">Meta Title</label>
                    <input type="text" id="fm-meta-title" class="form-control" placeholder="Override page title for search engines">
                </div>
                <div class="form-group">
                    <label class="form-label">Meta Description</label>
                    <textarea id="fm-meta-desc" class="form-control" rows="2" placeholder="Brief description for search results"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">OG Image URL</label>
                        <input type="text" id="fm-og-image" class="form-control" placeholder="/media/image.jpg">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Search Indexing</label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="fm-noindex">
                            Hide from search engines (noindex)
                        </label>
                    </div>
                </div>
            </fieldset>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeFrontmatterGenerator()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="insertFrontmatter()">
                <span class="material-symbols-rounded">check</span>
                Insert Frontmatter
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const editorContainer = document.getElementById('editor');
    const editorCard = document.querySelector('.editor-container');
    const hiddenInput = document.getElementById('file_content');
    const form = document.getElementById('editor-form');
    const wrapBtn = document.getElementById('wrap-toggle');
    const fullscreenBtn = document.getElementById('fullscreen-toggle');
    const CONFIG = <?= json_encode($jsConfig) ?>;
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
    
    // Initialize CodeMirror
    async function initEditor() {
        if (typeof window.AvaCodeMirror !== 'undefined') {
            cmEditor = await window.AvaCodeMirror.createEditor(editorContainer, {
                content: hiddenInput.value,
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
            setTimeout(initEditor, 50);
        }
    }
    
    // Get editor content
    function getEditorContent() {
        if (cmEditor) {
            return window.AvaCodeMirror.getValue(cmEditor);
        }
        return hiddenInput.value;
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
            setTimeout(() => cmEditor.focus(), 0);
        }
    }
    
    if (fullscreenBtn) {
        fullscreenBtn.addEventListener('click', toggleFullscreen);
    }
    
    form.addEventListener('submit', function() {
        hiddenInput.value = getEditorContent();
    });
    
    // Ctrl+S to save, Escape to exit fullscreen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isFullscreen) {
            toggleFullscreen();
            return;
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            hiddenInput.value = getEditorContent();
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
    
    // Expose for frontmatter generator
    window.setEditorContent = function(text) {
        hiddenInput.value = text;
        if (cmEditor && window.AvaCodeMirror.setValue) {
            window.AvaCodeMirror.setValue(cmEditor, text);
        }
    };
    
    window.getEditorContent = function() {
        return getEditorContent();
    };

    initEditor();

    // Parse YAML frontmatter (simple parser for known structures)
    function parseYamlFrontmatter(yaml) {
        const result = {};
        const lines = yaml.split('\n');
        let currentKey = null;
        let currentArray = null;
        
        for (let line of lines) {
            if (line.match(/^\s+-\s+(.+)$/)) {
                const val = line.replace(/^\s+-\s+/, '').replace(/^["']|["']$/g, '').trim();
                if (currentArray && currentKey) {
                    result[currentKey].push(val);
                }
                continue;
            }
            
            const match = line.match(/^([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*)$/);
            if (match) {
                currentKey = match[1];
                const val = match[2].replace(/^["']|["']$/g, '').trim();
                if (val === '' || val === '[]') {
                    result[currentKey] = [];
                    currentArray = true;
                } else {
                    result[currentKey] = val;
                    currentArray = false;
                }
            }
        }
        return result;
    }
    window.parseYamlFrontmatter = parseYamlFrontmatter;

    // Frontmatter Generator functions (exposed globally)
    window.openFrontmatterGenerator = function() {
        const content = getPlainText();
        const fmMatch = content.match(/^---\n([\s\S]*?)\n---/);
        
        if (fmMatch) {
            const parsed = parseYamlFrontmatter(fmMatch[1]);
            document.getElementById('fm-title').value = parsed.title || '';
            document.getElementById('fm-slug').value = parsed.slug || '';
            document.getElementById('fm-status').value = parsed.status || 'draft';
            if (CONFIG.usesDate) {
                document.getElementById('fm-date').value = parsed.date || new Date().toISOString().slice(0, 10);
            }
            document.getElementById('fm-template').value = parsed.template || '';
            document.getElementById('fm-excerpt').value = parsed.excerpt || '';
            document.getElementById('fm-meta-title').value = parsed.meta_title || '';
            document.getElementById('fm-meta-desc').value = parsed.meta_description || '';
            document.getElementById('fm-og-image').value = parsed.og_image || '';
            document.getElementById('fm-noindex').checked = parsed.noindex === true || parsed.noindex === 'true';
            
            // Taxonomies
            if (CONFIG.taxonomies) {
                CONFIG.taxonomies.forEach(tax => {
                    const el = document.getElementById('fm-tax-' + tax);
                    if (el) {
                        const vals = parsed[tax];
                        if (Array.isArray(vals)) {
                            Array.from(el.options).forEach(opt => {
                                opt.selected = vals.includes(opt.value);
                            });
                        }
                    }
                });
            }
        }
        
        document.getElementById('fm-modal').style.display = 'flex';
        document.getElementById('fm-title').focus();
    };
    
    window.closeFrontmatterGenerator = function() {
        document.getElementById('fm-modal').style.display = 'none';
    };
    
    window.insertFrontmatter = function() {
        const content = getPlainText();
        const fmMatch = content.match(/^---\n([\s\S]*?)\n---/);
        
        const title = document.getElementById('fm-title').value.trim();
        const slug = document.getElementById('fm-slug').value.trim() || generateSlug(title);
        const status = document.getElementById('fm-status').value;
        const date = CONFIG.usesDate ? document.getElementById('fm-date').value : null;
        const template = document.getElementById('fm-template').value.trim();
        const excerpt = document.getElementById('fm-excerpt').value.trim();
        const metaTitle = document.getElementById('fm-meta-title').value.trim();
        const metaDesc = document.getElementById('fm-meta-desc').value.trim();
        const ogImage = document.getElementById('fm-og-image').value.trim();
        const noindex = document.getElementById('fm-noindex').checked;
        
        // Collect taxonomy values
        const taxValues = {};
        if (CONFIG.taxonomies) {
            CONFIG.taxonomies.forEach(tax => {
                const el = document.getElementById('fm-tax-' + tax);
                if (el) {
                    const selected = Array.from(el.selectedOptions).map(o => o.value);
                    if (selected.length > 0) {
                        taxValues[tax] = selected;
                    }
                }
            });
        }
        
        // Build frontmatter object
        let newFm = {
            id: crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            }),
            title: title,
            slug: slug,
            status: status,
        };
        
        if (date) newFm.date = date;
        if (template) newFm.template = template;
        if (excerpt) newFm.excerpt = excerpt;
        
        // Add taxonomy values
        for (const [tax, vals] of Object.entries(taxValues)) {
            newFm[tax] = vals;
        }
        
        // SEO fields
        if (metaTitle) newFm.meta_title = metaTitle;
        if (metaDesc) newFm.meta_description = metaDesc;
        if (ogImage) newFm.og_image = ogImage;
        if (noindex) newFm.noindex = true;
        
        // YAML value formatter
        function yamlValue(val) {
            if (typeof val === 'boolean') return val;
            if (typeof val === 'number') return val;
            const str = String(val);
            if (/[:#\[\]{}|>&*!?'"]/.test(str) || str !== str.trim() || str === '') {
                return '"' + str.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"';
            }
            return str;
        }
        
        // Convert to YAML
        let yaml = '';
        for (const [key, val] of Object.entries(newFm)) {
            if (Array.isArray(val)) {
                yaml += `${key}:\n` + val.map(v => `  - ${yamlValue(v)}\n`).join('');
            } else if (typeof val === 'boolean') {
                yaml += `${key}: ${val}\n`;
            } else {
                yaml += `${key}: ${yamlValue(val)}\n`;
            }
        }
        
        const body = fmMatch ? content.slice(fmMatch[0].length + fmMatch.index) : content;
        const defaultBody = body.trim();
        let newBody = body;
        if (defaultBody.startsWith('# New ') || defaultBody === '') {
            newBody = `# ${title}\n\nStart writing your content here...\n`;
        }
        
        setEditorContent(`---\n${yaml}---\n\n${newBody.replace(/^\n+/, '')}`);
        closeFrontmatterGenerator();
    };
    
    window.generateSlug = function(title) {
        return title.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    };
    
    window.generateFilenameFromContent = function() {
        const content = getPlainText();
        const fmMatch = content.match(/^---\n([\s\S]*?)\n---/);
        if (!fmMatch) {
            alert('No frontmatter found. Add frontmatter first.');
            return;
        }
        
        const parsed = parseYamlFrontmatter(fmMatch[1]);
        const slug = parsed.slug || generateSlug(parsed.title || 'untitled');
        const date = parsed.date || '';
        
        let filename = slug;
        if (CONFIG.usesDate && date && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
            filename = date + '-' + slug;
        }
        
        document.getElementById('filename').value = filename;
    };
    
    // Validate filename
    document.getElementById('filename').addEventListener('input', function(e) {
        let val = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/--+/g, '-');
        if (val !== this.value) {
            this.value = val;
        }
    });
    
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFrontmatterGenerator(); });
})();
</script>

