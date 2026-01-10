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

<form method="POST" action="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/<?= htmlspecialchars($item->slug()) ?>/edit" class="editor-form" id="editor-form">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">

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
            <textarea id="file_content" name="file_content" class="editor-textarea" spellcheck="false"><?= htmlspecialchars($currentFileContent) ?></textarea>
            <pre id="editor-highlight" class="editor-highlight" aria-hidden="true"></pre>
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
            <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/<?= htmlspecialchars($item->slug()) ?>/delete" 
               class="btn btn-danger-outline">
                <span class="material-symbols-rounded">delete</span>
                Delete
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
            <p class="text-sm text-secondary mb-4">Update the fields below. Only these fields will be updated - any custom fields you've added will be preserved.</p>
            
            <!-- Core Fields -->
            <fieldset class="fm-fieldset">
                <legend>Core</legend>
                <div class="form-group">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" id="fm-title" class="form-control" value="<?= htmlspecialchars($item->title()) ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Slug <span class="text-danger">*</span></label>
                        <input type="text" id="fm-slug" class="form-control" placeholder="auto-generated" value="<?= htmlspecialchars($item->slug()) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status <span class="text-danger">*</span></label>
                        <select id="fm-status" class="form-control">
                            <option value="draft" <?= $item->status() === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= $item->status() === 'published' ? 'selected' : '' ?>>Published</option>
                            <option value="unlisted" <?= $item->status() === 'unlisted' ? 'selected' : '' ?>>Unlisted</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group" id="fm-date-group" style="<?= $usesDate ? '' : 'display:none;' ?>">
                        <label class="form-label">Date</label>
                        <input type="date" id="fm-date" class="form-control" value="<?= htmlspecialchars($item->date() ? $item->date()->format('Y-m-d') : date('Y-m-d')) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">ID</label>
                        <div class="input-group">
                            <input type="text" id="fm-id" class="form-control font-mono text-xs" value="<?= htmlspecialchars($item->id() ?? '') ?>" readonly>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="generateNewId()" title="Generate new ID">
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
                        $itemTerms = $item->terms($taxName);
                    ?>
                    <div class="form-group" data-taxonomy="<?= htmlspecialchars($taxName) ?>">
                        <label class="form-label"><?= htmlspecialchars($taxLabel) ?></label>
                        <?php if (!empty($terms)): ?>
                        <select name="fm-tax-<?= htmlspecialchars($taxName) ?>[]" class="form-control fm-tax-select" multiple data-taxonomy="<?= htmlspecialchars($taxName) ?>">
                            <?php foreach ($terms as $termSlug => $termData): ?>
                            <option value="<?= htmlspecialchars($termSlug) ?>" <?= in_array($termSlug, $itemTerms, true) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($termData['name'] ?? $termSlug) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-hint">Hold Ctrl/Cmd to select multiple.</p>
                        <?php else: ?>
                        <input type="text" class="form-control fm-tax-text" data-taxonomy="<?= htmlspecialchars($taxName) ?>" 
                               placeholder="term1, term2, term3" value="<?= htmlspecialchars(implode(', ', $itemTerms)) ?>">
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
                    <input type="text" id="fm-meta-title" class="form-control" placeholder="Override page title for search engines" 
                           value="<?= htmlspecialchars($item->metaTitle() ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Meta Description</label>
                    <textarea id="fm-meta-desc" class="form-control" rows="2" placeholder="Brief description for search results"><?= htmlspecialchars($item->metaDescription() ?? '') ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">OG Image URL</label>
                        <input type="text" id="fm-og-image" class="form-control" placeholder="/media/image.jpg" 
                               value="<?= htmlspecialchars($item->ogImage() ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Search Indexing</label>
                        <label class="checkbox-label">
                            <input type="checkbox" id="fm-noindex" <?= $item->noindex() ? 'checked' : '' ?>>
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
                Update Frontmatter
            </button>
        </div>
    </div>
</div>

<script>
const editor = document.getElementById('file_content');
const highlight = document.getElementById('editor-highlight');
const form = document.getElementById('editor-form');
const CONFIG = <?= json_encode($jsConfig) ?>;

// Syntax highlighting
function updateHighlight() {
    highlight.innerHTML = highlightMarkdown(editor.value);
    syncScroll();
}

function syncScroll() {
    highlight.scrollTop = editor.scrollTop;
    highlight.scrollLeft = editor.scrollLeft;
}

function highlightMarkdown(text) {
    let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
    // Frontmatter - highlight the delimiters and keys
    html = html.replace(/^(---\n)([\s\S]*?)(\n---)/m, function(m, open, content, close) {
        const yaml = content.replace(/^(\s*)([a-zA-Z_][a-zA-Z0-9_]*)(:)/gm, 
            '$1<span class="hl-key">$2</span><span class="hl-colon">$3</span>');
        return '<span class="hl-delim">' + open + '</span><span class="hl-fm">' + yaml + '</span><span class="hl-delim">' + close + '</span>';
    });
    
    // Code blocks (triple backticks) - must be before inline code
    html = html.replace(/^(```\w*)\n([\s\S]*?)\n(```)/gm, '<span class="hl-cb">$1\n$2\n$3</span>');
    
    // Headers (must be at line start)
    html = html.replace(/^(#{1,6})\s(.+)$/gm, '<span class="hl-h"><span class="hl-hm">$1</span> $2</span>');
    // Bold - must have non-space content
    html = html.replace(/(\*\*)([^\s*][^*]*[^\s*]|[^\s*])(\*\*)/g, '<span class="hl-b">$1$2$3</span>');
    html = html.replace(/(__)([^\s_][^_]*[^\s_]|[^\s_])(__)/g, '<span class="hl-b">$1$2$3</span>');
    // Inline code - simple single backticks only (not already in code block)
    html = html.replace(/`([^`\n]+)`/g, '<span class="hl-c">`$1`</span>');
    // Links
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<span class="hl-l">[$1]($2)</span>');
    // Blockquotes
    html = html.replace(/^(&gt;.*)$/gm, '<span class="hl-q">$1</span>');
    // List markers
    html = html.replace(/^(\s*)([-*+])\s/gm, '$1<span class="hl-li">$2</span> ');
    html = html.replace(/^(\s*)(\d+\.)\s/gm, '$1<span class="hl-li">$2</span> ');
    
    return html;
}

editor.addEventListener('input', updateHighlight);
editor.addEventListener('scroll', syncScroll);
updateHighlight();

// Tab key support
editor.addEventListener('keydown', function(e) {
    if (e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + '  ' + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 2;
        updateHighlight();
    }
});

// Parse YAML frontmatter (simple parser for known structures)
function parseYamlFrontmatter(yaml) {
    const result = {};
    const lines = yaml.split('\n');
    let currentKey = null;
    let currentArray = null;
    
    for (let line of lines) {
        // Array item
        if (line.match(/^\s+-\s+(.+)$/)) {
            const val = line.replace(/^\s+-\s+/, '').replace(/^["']|["']$/g, '').trim();
            if (currentArray && currentKey) {
                result[currentKey].push(val);
            }
            continue;
        }
        
        // Key-value pair
        const match = line.match(/^([a-zA-Z_][a-zA-Z0-9_]*):\s*(.*)$/);
        if (match) {
            currentKey = match[1];
            const val = match[2].replace(/^["']|["']$/g, '').trim();
            if (val === '' || val === '[]') {
                // Could be array on next lines
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

// Frontmatter Generator
function openFrontmatterGenerator() {
    const content = editor.value;
    const fmMatch = content.match(/^---\n([\s\S]*?)\n---/);
    
    if (fmMatch) {
        const parsed = parseYamlFrontmatter(fmMatch[1]);
        
        // Core fields
        if (parsed.title) document.getElementById('fm-title').value = parsed.title;
        if (parsed.slug) document.getElementById('fm-slug').value = parsed.slug;
        if (parsed.status) document.getElementById('fm-status').value = parsed.status;
        if (parsed.date) document.getElementById('fm-date').value = parsed.date;
        if (parsed.id) document.getElementById('fm-id').value = parsed.id;
        
        // SEO fields
        if (parsed.meta_title) document.getElementById('fm-meta-title').value = parsed.meta_title;
        if (parsed.meta_description) document.getElementById('fm-meta-desc').value = parsed.meta_description;
        if (parsed.og_image) document.getElementById('fm-og-image').value = parsed.og_image;
        document.getElementById('fm-noindex').checked = parsed.noindex === 'true' || parsed.noindex === true;
        
        // Taxonomies - work with select or text input
        CONFIG.taxonomies.forEach(taxName => {
            const selectEl = document.querySelector(`select.fm-tax-select[data-taxonomy="${taxName}"]`);
            const textInput = document.querySelector(`.fm-tax-text[data-taxonomy="${taxName}"]`);
            const values = Array.isArray(parsed[taxName]) ? parsed[taxName] : (parsed[taxName] ? [parsed[taxName]] : []);
            
            if (selectEl) {
                // Multi-select: set selected options
                Array.from(selectEl.options).forEach(opt => {
                    opt.selected = values.includes(opt.value);
                });
            } else if (textInput) {
                textInput.value = values.join(', ');
            }
        });
    }
    
    document.getElementById('fm-modal').style.display = 'flex';
    document.getElementById('fm-title').focus();
}

function closeFrontmatterGenerator() {
    document.getElementById('fm-modal').style.display = 'none';
}

function generateNewId() {
    const t = Date.now().toString(32).toUpperCase().padStart(10, '0');
    const r = Array.from({length: 16}, () => '0123456789ABCDEFGHJKMNPQRSTVWXYZ'[Math.floor(Math.random() * 32)]).join('');
    document.getElementById('fm-id').value = t + r;
}

function insertFrontmatter() {
    const title = document.getElementById('fm-title').value.trim();
    if (!title) { alert('Title is required'); return; }
    
    const slug = document.getElementById('fm-slug').value.trim() || generateSlug(title);
    const status = document.getElementById('fm-status').value;
    const date = document.getElementById('fm-date').value;
    let id = document.getElementById('fm-id').value;
    if (!id) { generateNewId(); id = document.getElementById('fm-id').value; }
    
    // SEO
    const metaTitle = document.getElementById('fm-meta-title').value.trim();
    const metaDesc = document.getElementById('fm-meta-desc').value.trim();
    const ogImage = document.getElementById('fm-og-image').value.trim();
    const noindex = document.getElementById('fm-noindex').checked;
    
    // Parse existing frontmatter to preserve custom fields
    const content = editor.value;
    const fmMatch = content.match(/^---\n([\s\S]*?)\n---/);
    const existingFm = fmMatch ? parseYamlFrontmatter(fmMatch[1]) : {};
    
    // Fields we manage (will be updated)
    const managedFields = ['id', 'title', 'slug', 'status', 'date', 'meta_title', 'meta_description', 'og_image', 'noindex', ...CONFIG.taxonomies];
    
    // Build new frontmatter, preserving custom fields
    const newFm = {};
    
    // Add custom fields first (anything not in managedFields)
    for (const key in existingFm) {
        if (!managedFields.includes(key)) {
            newFm[key] = existingFm[key];
        }
    }
    
    // Add managed fields in order
    newFm.id = id;
    newFm.title = title;
    newFm.slug = slug;
    newFm.status = status;
    if (CONFIG.usesDate && date) newFm.date = date;
    
    // Taxonomies - read from select or text input
    CONFIG.taxonomies.forEach(taxName => {
        const selectEl = document.querySelector(`select.fm-tax-select[data-taxonomy="${taxName}"]`);
        const textInput = document.querySelector(`.fm-tax-text[data-taxonomy="${taxName}"]`);
        
        let values = [];
        if (selectEl) {
            values = Array.from(selectEl.selectedOptions).map(opt => opt.value);
        } else if (textInput) {
            values = textInput.value.split(',').map(v => v.trim()).filter(v => v);
        }
        
        if (values.length === 1) {
            newFm[taxName] = values[0];
        } else if (values.length > 1) {
            newFm[taxName] = values;
        }
    });
    
    // SEO fields
    if (metaTitle) newFm.meta_title = metaTitle;
    if (metaDesc) newFm.meta_description = metaDesc;
    if (ogImage) newFm.og_image = ogImage;
    if (noindex) newFm.noindex = true;
    
    // YAML value formatter - only quote if contains special chars
    function yamlValue(val) {
        if (typeof val === 'boolean') return val;
        if (typeof val === 'number') return val;
        const str = String(val);
        // Quote if contains: colon followed by space, #, leading/trailing spaces, or starts with special chars
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
    editor.value = `---\n${yaml}---\n\n${body.replace(/^\n+/, '')}`;
    updateHighlight();
    closeFrontmatterGenerator();
}

function generateSlug(title) {
    return title.toLowerCase().replace(/[^a-z0-9\s-]/g, '').replace(/[\s_]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
}

// Generate filename from frontmatter date and slug
function generateFilenameFromContent() {
    const content = editor.value;
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
}

// Validate filename on input (only allow valid characters)
document.getElementById('filename').addEventListener('input', function(e) {
    // Remove any invalid characters (only allow lowercase a-z, 0-9, and hyphens)
    let val = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/--+/g, '-');
    if (val !== this.value) {
        this.value = val;
    }
});

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeFrontmatterGenerator(); });
</script>
