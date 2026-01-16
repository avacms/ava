<?php
/**
 * Content Editor - Unified Field-Based Editor
 * 
 * A beautiful, intuitive content editor with:
 * - Enhanced Markdown editing with toolbar and syntax highlighting
 * - Organized field groups for custom fields
 * - Sticky sidebar for quick settings
 * - Live SEO preview
 * - Collapsible sections for advanced settings
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
 * - $app: Application instance
 */

$taxonomiesForType = $typeConfig['taxonomies'] ?? [];
$usesDate = in_array($typeConfig['sorting'] ?? '', ['date_desc', 'date_asc'], true);
$usesOrder = ($typeConfig['sorting'] ?? '') === 'manual';
$typeLabel = rtrim($typeConfig['label'] ?? ucfirst($type), 's');

// Get current values from item
$currentTitle = $item->title();
$currentSlug = $item->slug();
$currentStatus = $item->status();
$currentDate = $item->date()?->format('Y-m-d') ?? date('Y-m-d');
$currentUpdated = $item->updated()?->format('Y-m-d') ?? '';
$currentId = $item->id() ?? '';
$currentBody = $item->rawContent();
$currentExcerpt = $item->excerpt() ?? '';
$currentFilename = pathinfo(basename($item->filePath()), PATHINFO_FILENAME);
$currentOrder = $item->order();
$currentTemplate = $item->template() ?? '';

// Per-item assets
$cssAssets = $item->css();
$jsAssets = $item->js();

// Generate preview URL
$baseUrl = rtrim($site['url'] ?? '', '/');
$urlType = $typeConfig['url']['type'] ?? 'pattern';
if ($urlType === 'hierarchical') {
    $previewUrl = $baseUrl . '/' . ltrim($currentSlug, '/');
} else {
    $pattern = $typeConfig['url']['pattern'] ?? '/{slug}';
    $previewUrl = $baseUrl . str_replace('{slug}', $currentSlug, $pattern);
}
$previewUrlDisplay = str_replace($baseUrl, '', $previewUrl);
$previewUrl .= '?preview=1';

// Check for success message
$saved = isset($_GET['saved']) || isset($successMessage);
$successMsg = $successMessage ?? 'Changes saved successfully.';

// Context for field renderer
$context = [
    'csrf' => $csrf,
    'admin_url' => $admin_url,
    'taxonomyConfig' => $taxonomyConfig,
    'availableTerms' => $availableTerms,
];

// Group custom fields
$customFields = $typeConfig['fields'] ?? [];
$fieldGroups = [];
foreach ($customFields as $fieldName => $fieldConfig) {
    $group = $fieldConfig['group'] ?? 'custom';
    if (!isset($fieldGroups[$group])) {
        $fieldGroups[$group] = [];
    }
    $fieldGroups[$group][$fieldName] = $fieldConfig;
}

// Available templates
$templates = [];
$themePath = $app->path('app/themes/' . $app->config('theme', 'default') . '/templates');
if (is_dir($themePath)) {
    foreach (glob($themePath . '/*.php') as $tplFile) {
        $templates[] = basename($tplFile);
    }
}

// Group label display names
$groupLabels = [
    'content' => 'Content',
    'display' => 'Display Options',
    'media' => 'Media',
    'advanced' => 'Advanced',
    'custom' => 'Custom Fields',
];
?>

<div class="ce-container" id="content-editor">
    <!-- Header -->
    <header class="ce-header">
        <div class="ce-header-left">
            <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>" class="ce-back" title="Back to <?= htmlspecialchars($typeConfig['label'] ?? $type) ?>">
                <span class="material-symbols-rounded">arrow_back</span>
            </a>
            <div class="ce-breadcrumb">
                <span class="ce-breadcrumb-type"><?= htmlspecialchars($typeLabel) ?></span>
                <span class="ce-breadcrumb-sep">/</span>
                <span class="ce-breadcrumb-title" id="header-title"><?= htmlspecialchars($currentTitle ?: 'Untitled') ?></span>
            </div>
        </div>
        <div class="ce-header-right">
            <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/edit?file=<?= htmlspecialchars($fileParam) ?>&mode=raw" 
               class="ce-header-btn" title="Edit raw YAML + Markdown file">
                <span class="material-symbols-rounded">code</span>
                <span class="ce-btn-label">Raw File</span>
            </a>
            <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" class="ce-header-btn">
                <span class="material-symbols-rounded">visibility</span>
                <span class="ce-btn-label">Preview</span>
            </a>
            <button type="submit" form="content-editor-form" class="btn btn-primary">
                <span class="material-symbols-rounded">save</span>
                Save
            </button>
        </div>
    </header>

    <!-- Alerts -->
    <?php if ($saved): ?>
    <div class="ce-alert ce-alert-success">
        <span class="material-symbols-rounded">check_circle</span>
        <span><?= htmlspecialchars($successMsg) ?></span>
        <button type="button" class="ce-alert-close" onclick="this.parentElement.remove()">
            <span class="material-symbols-rounded">close</span>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="ce-alert ce-alert-error">
        <span class="material-symbols-rounded">error</span>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <?php if (!empty($securityWarnings)): ?>
    <div class="ce-alert ce-alert-warning">
        <span class="material-symbols-rounded">warning</span>
        <span><strong>Security warning:</strong> <?= htmlspecialchars($securityWarnings[0]) ?></span>
    </div>
    <?php endif; ?>

    <!-- Main Form -->
    <form method="POST" action="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/edit?file=<?= htmlspecialchars($fileParam) ?>" 
          id="content-editor-form" class="ce-layout">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="_file_mtime" value="<?= htmlspecialchars((string) ($fileMtime ?? 0)) ?>">
        <input type="hidden" name="_editor_mode" value="visual">

        <!-- Main Content Area -->
        <main class="ce-main">
            <!-- Title -->
            <div class="ce-title-wrapper">
                <input type="text" name="fields[title]" id="field-title" class="ce-title-input" 
                       value="<?= htmlspecialchars($currentTitle) ?>" 
                       placeholder="Enter title..."
                       required autocomplete="off">
            </div>

            <!-- Markdown Editor -->
            <div class="ce-editor" id="markdown-editor">
                <div class="ce-editor-toolbar">
                    <div class="ce-toolbar-group">
                        <button type="button" class="ce-tool" data-action="bold" title="Bold (Ctrl+B)">
                            <span class="material-symbols-rounded">format_bold</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="italic" title="Italic (Ctrl+I)">
                            <span class="material-symbols-rounded">format_italic</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="strikethrough" title="Strikethrough">
                            <span class="material-symbols-rounded">strikethrough_s</span>
                        </button>
                    </div>
                    <span class="ce-toolbar-sep"></span>
                    <div class="ce-toolbar-group">
                        <button type="button" class="ce-tool" data-action="h1" title="Heading 1">
                            H1
                        </button>
                        <button type="button" class="ce-tool" data-action="h2" title="Heading 2">
                            H2
                        </button>
                        <button type="button" class="ce-tool" data-action="h3" title="Heading 3">
                            H3
                        </button>
                    </div>
                    <span class="ce-toolbar-sep"></span>
                    <div class="ce-toolbar-group">
                        <button type="button" class="ce-tool" data-action="link" title="Link (Ctrl+K)">
                            <span class="material-symbols-rounded">link</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="image" title="Image">
                            <span class="material-symbols-rounded">image</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="code" title="Inline Code">
                            <span class="material-symbols-rounded">code</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="codeblock" title="Code Block">
                            <span class="material-symbols-rounded">data_object</span>
                        </button>
                    </div>
                    <span class="ce-toolbar-sep"></span>
                    <div class="ce-toolbar-group">
                        <button type="button" class="ce-tool" data-action="ul" title="Bullet List">
                            <span class="material-symbols-rounded">format_list_bulleted</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="ol" title="Numbered List">
                            <span class="material-symbols-rounded">format_list_numbered</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="quote" title="Blockquote">
                            <span class="material-symbols-rounded">format_quote</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="hr" title="Horizontal Rule">
                            <span class="material-symbols-rounded">horizontal_rule</span>
                        </button>
                    </div>
                    <div class="ce-toolbar-spacer"></div>
                    <div class="ce-toolbar-group">
                        <button type="button" class="ce-tool" data-action="wrap" title="Line wrap: Full width">
                            <span class="material-symbols-rounded">wrap_text</span>
                        </button>
                        <button type="button" class="ce-tool" data-action="fullscreen" title="Fullscreen (Esc to exit)">
                            <span class="material-symbols-rounded">fullscreen</span>
                        </button>
                    </div>
                </div>
                <div class="ce-editor-wrapper">
                    <div id="ce-editor" class="codemirror-container" data-codemirror="markdown"></div>
                    <textarea id="field-body" name="fields[body]" class="editor-hidden-input"><?= htmlspecialchars($currentBody) ?></textarea>
                </div>
            </div>

            <!-- Excerpt -->
            <div class="ce-section">
                <label class="ce-field-label" for="field-excerpt">
                    Excerpt
                    <span class="ce-field-optional">optional</span>
                </label>
                <textarea id="field-excerpt" name="fields[excerpt]" class="ce-textarea" rows="2" 
                          placeholder="A brief summary for listings and search results..."><?= htmlspecialchars($currentExcerpt) ?></textarea>
            </div>

            <!-- Custom Field Groups -->
            <?php if (!empty($fieldGroups)): ?>
            <div class="ce-custom-fields">
                <?php foreach ($fieldGroups as $groupKey => $fields): ?>
                <details class="ce-fieldgroup" data-group="<?= htmlspecialchars($groupKey) ?>">
                    <summary class="ce-fieldgroup-header">
                        <span class="material-symbols-rounded ce-fieldgroup-icon">chevron_right</span>
                        <span class="ce-fieldgroup-title"><?= htmlspecialchars($groupLabels[$groupKey] ?? ucfirst($groupKey)) ?></span>
                        <span class="ce-fieldgroup-count"><?= count($fields) ?> field<?= count($fields) !== 1 ? 's' : '' ?></span>
                    </summary>
                    <div class="ce-fieldgroup-content">
                        <?php foreach ($fields as $fieldName => $fieldConfig): 
                            $fieldType = $fieldConfig['type'] ?? 'text';
                            $fieldValue = $item->get($fieldName);
                            $fieldLabel = $fieldConfig['label'] ?? ucfirst(str_replace(['_', '-'], ' ', $fieldName));
                            $fieldDesc = $fieldConfig['description'] ?? null;
                            $fieldRequired = $fieldConfig['required'] ?? false;
                            $fieldId = 'field-custom-' . $fieldName;
                        ?>
                        <div class="ce-field">
                            <label class="ce-field-label" for="<?= htmlspecialchars($fieldId) ?>">
                                <?= htmlspecialchars($fieldLabel) ?>
                                <?php if ($fieldRequired): ?>
                                <span class="ce-field-required">*</span>
                                <?php else: ?>
                                <span class="ce-field-optional">optional</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($fieldType === 'text'): ?>
                            <input type="text" id="<?= htmlspecialchars($fieldId) ?>" 
                                   name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                   class="ce-input" 
                                   value="<?= htmlspecialchars((string)($fieldValue ?? '')) ?>"
                                   <?= $fieldRequired ? 'required' : '' ?>
                                   <?= isset($fieldConfig['maxlength']) ? 'maxlength="' . (int)$fieldConfig['maxlength'] . '"' : '' ?>
                                   <?= isset($fieldConfig['placeholder']) ? 'placeholder="' . htmlspecialchars($fieldConfig['placeholder']) . '"' : '' ?>>
                            
                            <?php elseif ($fieldType === 'textarea'): ?>
                            <textarea id="<?= htmlspecialchars($fieldId) ?>" 
                                      name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                      class="ce-textarea"
                                      rows="<?= (int)($fieldConfig['rows'] ?? 3) ?>"
                                      <?= $fieldRequired ? 'required' : '' ?>><?= htmlspecialchars((string)($fieldValue ?? '')) ?></textarea>
                            
                            <?php elseif ($fieldType === 'number'): ?>
                            <input type="number" id="<?= htmlspecialchars($fieldId) ?>" 
                                   name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                   class="ce-input ce-input-short"
                                   value="<?= htmlspecialchars((string)($fieldValue ?? '')) ?>"
                                   <?= isset($fieldConfig['min']) ? 'min="' . (int)$fieldConfig['min'] . '"' : '' ?>
                                   <?= isset($fieldConfig['max']) ? 'max="' . (int)$fieldConfig['max'] . '"' : '' ?>
                                   <?= isset($fieldConfig['step']) ? 'step="' . htmlspecialchars($fieldConfig['step']) . '"' : '' ?>>
                            
                            <?php elseif ($fieldType === 'checkbox'): ?>
                            <label class="ce-checkbox">
                                <input type="checkbox" name="fields[<?= htmlspecialchars($fieldName) ?>]" value="1"
                                       <?= $fieldValue ? 'checked' : '' ?>>
                                <span class="ce-checkbox-mark"></span>
                                <span class="ce-checkbox-label"><?= htmlspecialchars($fieldConfig['checkboxLabel'] ?? 'Yes') ?></span>
                            </label>
                            
                            <?php elseif ($fieldType === 'select'): ?>
                            <select id="<?= htmlspecialchars($fieldId) ?>" 
                                    name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                    class="ce-select">
                                <?php foreach (($fieldConfig['options'] ?? []) as $optValue => $optLabel): ?>
                                <option value="<?= htmlspecialchars((string)$optValue) ?>" 
                                        <?= $fieldValue == $optValue ? 'selected' : '' ?>><?= htmlspecialchars($optLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                            
                            <?php elseif ($fieldType === 'date'): ?>
                            <input type="date" id="<?= htmlspecialchars($fieldId) ?>" 
                                   name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                   class="ce-input ce-input-short"
                                   value="<?= htmlspecialchars((string)($fieldValue ?? '')) ?>">
                            
                            <?php elseif ($fieldType === 'image'): ?>
                            <div class="ce-image-field">
                                <input type="text" id="<?= htmlspecialchars($fieldId) ?>" 
                                       name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                       class="ce-input"
                                       value="<?= htmlspecialchars((string)($fieldValue ?? '')) ?>"
                                       placeholder="@media:image.jpg">
                                <button type="button" class="ce-input-btn" onclick="openMediaPicker('<?= htmlspecialchars($fieldName) ?>')">
                                    <span class="material-symbols-rounded">folder_open</span>
                                </button>
                            </div>
                            
                            <?php elseif ($fieldType === 'color'): ?>
                            <div class="ce-color-field">
                                <input type="color" id="<?= htmlspecialchars($fieldId) ?>-picker" 
                                       value="<?= htmlspecialchars((string)($fieldValue ?? '#000000')) ?>"
                                       onchange="document.getElementById('<?= htmlspecialchars($fieldId) ?>').value = this.value">
                                <input type="text" id="<?= htmlspecialchars($fieldId) ?>" 
                                       name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                       class="ce-input"
                                       value="<?= htmlspecialchars((string)($fieldValue ?? '')) ?>"
                                       placeholder="#000000">
                            </div>
                            
                            <?php elseif ($fieldType === 'array'): 
                                // Support both 'keyValue' (docs) and 'associative' (implementation) for backwards compatibility
                                $isKeyValue = !empty($fieldConfig['keyValue']) || !empty($fieldConfig['associative']);
                                $arrayValues = is_array($fieldValue) ? $fieldValue : [];
                            ?>
                            <div class="ce-array-field <?= $isKeyValue ? 'ce-array-kv' : '' ?>" 
                                 id="array-<?= htmlspecialchars($fieldName) ?>" 
                                 data-keyvalue="<?= $isKeyValue ? '1' : '0' ?>">
                                <?php if ($isKeyValue): ?>
                                    <?php $idx = 0; foreach ($arrayValues as $arrKey => $arrVal): ?>
                                    <div class="ce-array-item ce-array-item-kv">
                                        <input type="text" name="fields[<?= htmlspecialchars($fieldName) ?>][<?= $idx ?>][key]" 
                                               class="ce-input ce-kv-key" value="<?= htmlspecialchars((string)$arrKey) ?>" placeholder="Key">
                                        <input type="text" name="fields[<?= htmlspecialchars($fieldName) ?>][<?= $idx ?>][value]" 
                                               class="ce-input ce-kv-value" value="<?= htmlspecialchars((string)$arrVal) ?>" placeholder="Value">
                                        <button type="button" class="ce-array-remove" onclick="this.parentElement.remove()">
                                            <span class="material-symbols-rounded">close</span>
                                        </button>
                                    </div>
                                    <?php $idx++; endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($arrayValues as $arrVal): ?>
                                    <div class="ce-array-item">
                                        <input type="text" name="fields[<?= htmlspecialchars($fieldName) ?>][]" 
                                               class="ce-input" value="<?= htmlspecialchars((string)$arrVal) ?>">
                                        <button type="button" class="ce-array-remove" onclick="this.parentElement.remove()">
                                            <span class="material-symbols-rounded">close</span>
                                        </button>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <button type="button" class="ce-link-btn" 
                                    onclick="addArrayItem('array-<?= htmlspecialchars($fieldName) ?>', '<?= htmlspecialchars($fieldName) ?>', '', <?= $isKeyValue ? 'true' : 'false' ?>)">
                                <span class="material-symbols-rounded">add</span>
                                Add <?= $isKeyValue ? 'pair' : 'item' ?>
                            </button>
                            
                            <?php else: ?>
                            <input type="text" id="<?= htmlspecialchars($fieldId) ?>" 
                                   name="fields[<?= htmlspecialchars($fieldName) ?>]" 
                                   class="ce-input"
                                   value="<?= htmlspecialchars((string)($fieldValue ?? '')) ?>">
                            <?php endif; ?>
                            
                            <?php if ($fieldDesc): ?>
                            <p class="ce-field-hint"><?= htmlspecialchars($fieldDesc) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- SEO Section -->
            <details class="ce-section-collapsible" data-group="seo">
                <summary class="ce-section-header">
                    <span class="material-symbols-rounded">search</span>
                    SEO & Social
                    <span class="material-symbols-rounded ce-section-arrow">expand_more</span>
                </summary>
                <div class="ce-section-content">
                    <div class="ce-field">
                        <label class="ce-field-label" for="field-meta-title">
                            Meta Title
                            <span class="ce-char-count" data-for="field-meta-title" data-max="60"></span>
                        </label>
                        <input type="text" id="field-meta-title" name="fields[meta_title]" class="ce-input"
                               value="<?= htmlspecialchars($item->metaTitle() ?? '') ?>"
                               placeholder="Override title for search engines">
                    </div>
                    
                    <div class="ce-field">
                        <label class="ce-field-label" for="field-meta-desc">
                            Meta Description
                            <span class="ce-char-count" data-for="field-meta-desc" data-max="160"></span>
                        </label>
                        <textarea id="field-meta-desc" name="fields[meta_description]" class="ce-textarea" rows="2"
                                  placeholder="Brief description for search results"><?= htmlspecialchars($item->metaDescription() ?? '') ?></textarea>
                    </div>
                    
                    <div class="ce-field-row">
                        <div class="ce-field">
                            <label class="ce-field-label" for="field-og-image">Social Image</label>
                            <div class="ce-image-field">
                                <input type="text" id="field-og-image" name="fields[og_image]" class="ce-input"
                                       value="<?= htmlspecialchars($item->ogImage() ?? '') ?>"
                                       placeholder="@media:social.jpg">
                                <button type="button" class="ce-input-btn" onclick="openMediaPicker('og_image')">
                                    <span class="material-symbols-rounded">folder_open</span>
                                </button>
                            </div>
                            <p class="ce-field-hint">Image for social media sharing (1200×630 recommended)</p>
                        </div>
                        
                        <div class="ce-field">
                            <label class="ce-field-label" for="field-canonical">Canonical URL</label>
                            <input type="url" id="field-canonical" name="fields[canonical]" class="ce-input"
                                   value="<?= htmlspecialchars($item->canonical() ?? '') ?>"
                                   placeholder="https://example.com/original">
                            <p class="ce-field-hint">Use when content exists at multiple URLs</p>
                        </div>
                    </div>
                    
                    <div class="ce-field">
                        <label class="ce-checkbox">
                            <input type="checkbox" name="fields[noindex]" value="1" <?= $item->noindex() ? 'checked' : '' ?>>
                            <span class="ce-checkbox-mark"></span>
                            <span class="ce-checkbox-label">Hide from search engines (noindex)</span>
                        </label>
                    </div>
                    
                    <!-- SEO Preview -->
                    <div class="ce-seo-preview">
                        <div class="ce-seo-preview-label">Search Preview</div>
                        <div class="ce-seo-preview-box">
                            <div class="ce-seo-title" id="seo-preview-title"><?= htmlspecialchars($item->metaTitle() ?: $currentTitle) ?></div>
                            <div class="ce-seo-url"><?= htmlspecialchars($baseUrl . $previewUrlDisplay) ?></div>
                            <div class="ce-seo-desc" id="seo-preview-desc"><?= htmlspecialchars($item->metaDescription() ?: $currentExcerpt) ?></div>
                        </div>
                    </div>
                </div>
            </details>

            <!-- Advanced Section -->
            <details class="ce-section-collapsible" data-group="advanced">
                <summary class="ce-section-header">
                    <span class="material-symbols-rounded">tune</span>
                    Advanced Settings
                    <span class="material-symbols-rounded ce-section-arrow">expand_more</span>
                </summary>
                <div class="ce-section-content">
                    <div class="ce-field-row">
                        <div class="ce-field">
                            <label class="ce-field-label" for="field-slug">URL Slug</label>
                            <div class="ce-slug-field">
                                <input type="text" id="field-slug" name="fields[slug]" class="ce-input"
                                       value="<?= htmlspecialchars($currentSlug) ?>" required
                                       pattern="[a-z0-9-/]+" title="Lowercase letters, numbers, hyphens, and slashes">
                                <button type="button" class="ce-link-btn" onclick="generateSlugFromTitle()">
                                    <span class="material-symbols-rounded">auto_fix</span>
                                    Auto
                                </button>
                            </div>
                        </div>
                        
                        <div class="ce-field">
                            <label class="ce-field-label" for="field-filename">Filename</label>
                            <div class="ce-input-suffix-wrapper">
                                <input type="text" id="field-filename" name="filename" class="ce-input"
                                       value="<?= htmlspecialchars($currentFilename) ?>"
                                       pattern="[a-z0-9-]+" title="Lowercase letters, numbers, and hyphens">
                                <span class="ce-input-suffix">.md</span>
                            </div>
                        </div>
                    </div>

                    <div class="ce-field">
                        <label class="ce-field-label" for="field-id">Content ID</label>
                        <div class="ce-id-field">
                            <input type="text" id="field-id" name="fields[id]" class="ce-input ce-mono"
                                   value="<?= htmlspecialchars($currentId) ?>" placeholder="Optional ULID">
                            <button type="button" class="ce-input-btn" onclick="generateId()" title="Generate ID">
                                <span class="material-symbols-rounded">casino</span>
                            </button>
                        </div>
                        <p class="ce-field-hint">Unique identifier for stable references and ID-based URLs</p>
                    </div>

                    <div class="ce-field">
                        <label class="ce-field-label">Redirect From</label>
                        <div class="ce-array-field" id="redirect-from-array">
                            <?php foreach ($item->redirectFrom() as $redirect): ?>
                            <div class="ce-array-item">
                                <input type="text" name="fields[redirect_from][]" class="ce-input"
                                       value="<?= htmlspecialchars($redirect) ?>" placeholder="/old-url">
                                <button type="button" class="ce-array-remove" onclick="this.parentElement.remove()">
                                    <span class="material-symbols-rounded">close</span>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="ce-link-btn" onclick="addArrayItem('redirect-from-array', 'redirect_from', '/old-url')">
                            <span class="material-symbols-rounded">add</span>
                            Add redirect
                        </button>
                        <p class="ce-field-hint">Old URLs that should 301 redirect to this page</p>
                    </div>

                    <div class="ce-field-row">
                        <div class="ce-field">
                            <label class="ce-field-label">Per-Page CSS</label>
                            <div class="ce-array-field" id="css-assets-array">
                                <?php foreach ($cssAssets as $css): ?>
                                <div class="ce-array-item">
                                    <input type="text" name="fields[assets_css][]" class="ce-input"
                                           value="<?= htmlspecialchars($css) ?>" placeholder="@media:css/custom.css">
                                    <button type="button" class="ce-array-remove" onclick="this.parentElement.remove()">
                                        <span class="material-symbols-rounded">close</span>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="ce-link-btn" onclick="addArrayItem('css-assets-array', 'assets_css', '@media:css/')">
                                <span class="material-symbols-rounded">add</span>
                                Add CSS
                            </button>
                        </div>
                        
                        <div class="ce-field">
                            <label class="ce-field-label">Per-Page JavaScript</label>
                            <div class="ce-array-field" id="js-assets-array">
                                <?php foreach ($jsAssets as $js): ?>
                                <div class="ce-array-item">
                                    <input type="text" name="fields[assets_js][]" class="ce-input"
                                           value="<?= htmlspecialchars($js) ?>" placeholder="@media:js/script.js">
                                    <button type="button" class="ce-array-remove" onclick="this.parentElement.remove()">
                                        <span class="material-symbols-rounded">close</span>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="ce-link-btn" onclick="addArrayItem('js-assets-array', 'assets_js', '@media:js/')">
                                <span class="material-symbols-rounded">add</span>
                                Add JS
                            </button>
                        </div>
                    </div>

                    <div class="ce-field">
                        <label class="ce-checkbox">
                            <input type="checkbox" name="fields[cache]" value="1" <?= ($item->get('cache') ?? true) ? 'checked' : '' ?>>
                            <span class="ce-checkbox-mark"></span>
                            <span class="ce-checkbox-label">Enable page caching</span>
                        </label>
                    </div>
                    
                    <div class="ce-danger-zone">
                        <a href="<?= htmlspecialchars($admin_url) ?>/content/<?= htmlspecialchars($type) ?>/<?= htmlspecialchars($item->slug()) ?>/delete" 
                           class="btn btn-danger-outline btn-sm">
                            <span class="material-symbols-rounded">delete</span>
                            Delete this <?= htmlspecialchars(strtolower($typeLabel)) ?>
                        </a>
                    </div>
                </div>
            </details>
        </main>

        <!-- Sidebar -->
        <aside class="ce-sidebar">
            <!-- Status -->
            <div class="ce-sidebar-section">
                <div class="ce-sidebar-label">Status</div>
                <div class="ce-status-group">
                    <label class="ce-status-option <?= $currentStatus === 'draft' ? 'active' : '' ?>">
                        <input type="radio" name="fields[status]" value="draft" <?= $currentStatus === 'draft' ? 'checked' : '' ?>>
                        <span class="ce-status-dot ce-status-draft"></span>
                        <span>Draft</span>
                    </label>
                    <label class="ce-status-option <?= $currentStatus === 'published' ? 'active' : '' ?>">
                        <input type="radio" name="fields[status]" value="published" <?= $currentStatus === 'published' ? 'checked' : '' ?>>
                        <span class="ce-status-dot ce-status-published"></span>
                        <span>Published</span>
                    </label>
                    <label class="ce-status-option <?= $currentStatus === 'unlisted' ? 'active' : '' ?>">
                        <input type="radio" name="fields[status]" value="unlisted" <?= $currentStatus === 'unlisted' ? 'checked' : '' ?>>
                        <span class="ce-status-dot ce-status-unlisted"></span>
                        <span>Unlisted</span>
                    </label>
                </div>
            </div>

            <!-- Date -->
            <?php if ($usesDate): ?>
            <div class="ce-sidebar-section">
                <label class="ce-sidebar-label" for="field-date">Publish Date</label>
                <input type="date" id="field-date" name="fields[date]" class="ce-input"
                       value="<?= htmlspecialchars($currentDate) ?>">
            </div>
            <?php endif; ?>

            <!-- Updated Date -->
            <div class="ce-sidebar-section">
                <label class="ce-sidebar-label" for="field-updated">Last Updated</label>
                <input type="date" id="field-updated" name="fields[updated]" class="ce-input"
                       value="<?= htmlspecialchars($currentUpdated) ?>" placeholder="Auto">
            </div>

            <!-- Order (for manual sorting) -->
            <?php if ($usesOrder): ?>
            <div class="ce-sidebar-section">
                <label class="ce-sidebar-label" for="field-order">Order</label>
                <input type="number" id="field-order" name="fields[order]" class="ce-input"
                       value="<?= htmlspecialchars((string)$currentOrder) ?>" min="0" step="1">
                <p class="ce-field-hint">Lower numbers appear first</p>
            </div>
            <?php endif; ?>

            <!-- Taxonomies -->
            <?php if (!empty($taxonomiesForType)): ?>
            <?php foreach ($taxonomiesForType as $taxName): 
                $taxConfig = $taxonomyConfig[$taxName] ?? [];
                $taxLabel = $taxConfig['label'] ?? ucfirst($taxName);
                $terms = $availableTerms[$taxName] ?? [];
                $itemTerms = $item->terms($taxName);
            ?>
            <div class="ce-sidebar-section">
                <div class="ce-sidebar-label"><?= htmlspecialchars($taxLabel) ?></div>
                <?php if (!empty($terms)): ?>
                <div class="ce-tag-selector" data-taxonomy="<?= htmlspecialchars($taxName) ?>">
                    <div class="ce-tag-search">
                        <input type="text" class="ce-input ce-tag-search-input" placeholder="Search...">
                    </div>
                    <div class="ce-tag-list">
                        <?php foreach ($terms as $termSlug => $termData): 
                            $isChecked = in_array($termSlug, $itemTerms, true);
                        ?>
                        <label class="ce-tag-item <?= $isChecked ? 'checked' : '' ?>" data-slug="<?= htmlspecialchars($termSlug) ?>">
                            <input type="checkbox" name="fields[<?= htmlspecialchars($taxName) ?>][]"
                                   value="<?= htmlspecialchars($termSlug) ?>"
                                   <?= $isChecked ? 'checked' : '' ?>>
                            <span class="ce-tag-name"><?= htmlspecialchars($termData['name'] ?? $termSlug) ?></span>
                            <?php if (isset($termData['count']) && $termData['count'] > 0): ?>
                            <span class="ce-tag-count"><?= (int)$termData['count'] ?></span>
                            <?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <input type="text" class="ce-input" name="fields[<?= htmlspecialchars($taxName) ?>]"
                       placeholder="term1, term2, term3"
                       value="<?= htmlspecialchars(implode(', ', $itemTerms)) ?>">
                <p class="ce-field-hint">Comma-separated terms</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Template -->
            <?php if (!empty($templates)): ?>
            <div class="ce-sidebar-section">
                <label class="ce-sidebar-label" for="field-template">Template</label>
                <select id="field-template" name="fields[template]" class="ce-select">
                    <option value="">— Default —</option>
                    <?php foreach ($templates as $tpl): ?>
                    <option value="<?= htmlspecialchars($tpl) ?>" <?= $currentTemplate === $tpl ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tpl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Permalink Preview -->
            <div class="ce-sidebar-section">
                <div class="ce-sidebar-label">Permalink</div>
                <div class="ce-permalink">
                    <a href="<?= htmlspecialchars($previewUrl) ?>" target="_blank" id="permalink-preview">
                        <?= htmlspecialchars($previewUrlDisplay) ?>
                    </a>
                </div>
            </div>
        </aside>
    </form>
</div>

<!-- Media Picker Modal -->
<div class="ce-modal ce-modal-lg" id="media-modal" style="display: none;">
    <div class="ce-modal-backdrop" onclick="closeMediaPicker()"></div>
    <div class="ce-modal-content">
        <div class="ce-modal-header">
            <h3>
                <span class="material-symbols-rounded">folder_open</span>
                Select Media
            </h3>
            <button type="button" class="ce-modal-close" onclick="closeMediaPicker()">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="ce-modal-toolbar">
            <div class="ce-media-search">
                <span class="material-symbols-rounded">search</span>
                <input type="text" id="media-search" class="form-control form-control-sm" placeholder="Search files..." oninput="filterMedia()">
            </div>
            <select id="media-folder" class="form-control form-control-sm" onchange="filterMedia()">
                <option value="">All Folders</option>
            </select>
        </div>
        <div class="ce-modal-body">
            <div class="media-grid" id="media-grid">
                <div class="empty-state">
                    <span class="material-symbols-rounded spin">sync</span>
                    <p>Loading...</p>
                </div>
            </div>
            <div id="media-load-more" class="media-load-more" style="display: none;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="loadMoreMedia()">
                    <span class="material-symbols-rounded">expand_more</span>
                    Load more
                </button>
                <span id="media-count" class="text-sm text-tertiary"></span>
            </div>
        </div>
        <div class="ce-modal-footer">
            <span id="selected-media-info" class="text-sm text-secondary"></span>
            <button type="button" class="btn btn-secondary" onclick="closeMediaPicker()">Cancel</button>
            <button type="button" class="btn btn-primary" id="media-insert-btn" onclick="insertSelectedMedia()" disabled>Insert</button>
        </div>
    </div>
</div>

<script>
// =============================================================================
// Content Editor JavaScript
// =============================================================================

const ADMIN_URL = <?= json_encode($admin_url) ?>;
const CONTENT_TYPE = <?= json_encode($type) ?>;

// =============================================================================
// Markdown Editor (CodeMirror)
// =============================================================================
const editorDivContainer = document.getElementById('ce-editor');
const hiddenInput = document.getElementById('field-body');
const editorContainer = document.getElementById('markdown-editor');
const editorWrapper = editorContainer?.querySelector('.ce-editor-wrapper');
let cmEditor = null;

// Editor height persistence
const EDITOR_HEIGHT_KEY = 'ava-editor-height';
if (editorWrapper) {
    const savedHeight = localStorage.getItem(EDITOR_HEIGHT_KEY);
    if (savedHeight) {
        editorWrapper.style.height = savedHeight;
    }
    
    const resizeObserver = new ResizeObserver(entries => {
        for (const entry of entries) {
            const height = entry.contentRect.height;
            if (height > 0) {
                localStorage.setItem(EDITOR_HEIGHT_KEY, height + 'px');
            }
        }
    });
    resizeObserver.observe(editorWrapper);
}

// Initialize CodeMirror
async function initMarkdownEditor() {
    if (typeof window.AvaCodeMirror !== 'undefined') {
        cmEditor = await window.AvaCodeMirror.createEditor(editorDivContainer, {
            content: hiddenInput.value,
            language: 'markdown',
            onChange: function(value) {
                hiddenInput.value = value;
            }
        });
        
        // Apply saved wrap mode
        const savedMode = window.AvaCodeMirror.getSavedWrapMode();
        window.AvaCodeMirror.setLineWrap(editorDivContainer, savedMode);
        updateWrapButton(savedMode);
    } else {
        setTimeout(initMarkdownEditor, 50);
    }
}

// Get current content from editor
function getEditorContent() {
    if (cmEditor) {
        return window.AvaCodeMirror.getValue(cmEditor);
    }
    return hiddenInput.value;
}

// Insert text at cursor
function insertAtCursor(text) {
    if (!cmEditor) {
        console.warn('CodeMirror editor not initialized yet');
        return;
    }
    if (window.AvaCodeMirror && window.AvaCodeMirror.insertText) {
        window.AvaCodeMirror.insertText(cmEditor, text);
    } else {
        console.error('AvaCodeMirror.insertText not available');
    }
}

// Get selected text
function getSelectedText() {
    if (!cmEditor) {
        return '';
    }
    if (window.AvaCodeMirror && window.AvaCodeMirror.getSelection) {
        return window.AvaCodeMirror.getSelection(cmEditor);
    }
    return '';
}

// Toolbar actions
document.querySelectorAll('.ce-tool[data-action]').forEach(btn => {
    btn.addEventListener('click', () => {
        const action = btn.dataset.action;
        handleToolbarAction(action);
    });
});

function handleToolbarAction(action) {
    if (cmEditor && cmEditor.focus) {
        cmEditor.focus();
    }
    const selected = getSelectedText();
    let insert = '';
    
    switch (action) {
        case 'bold':
            insert = `**${selected || 'bold text'}**`;
            break;
        case 'italic':
            insert = `*${selected || 'italic text'}*`;
            break;
        case 'strikethrough':
            insert = `~~${selected || 'strikethrough'}~~`;
            break;
        case 'h1':
            insert = `# ${selected || 'Heading 1'}`;
            break;
        case 'h2':
            insert = `## ${selected || 'Heading 2'}`;
            break;
        case 'h3':
            insert = `### ${selected || 'Heading 3'}`;
            break;
        case 'link':
            insert = `[${selected || 'link text'}](url)`;
            break;
        case 'image':
            openMediaPicker('body');
            return;
        case 'code':
            insert = `\`${selected || 'code'}\``;
            break;
        case 'codeblock':
            insert = `\n\`\`\`\n${selected || 'code'}\n\`\`\`\n`;
            break;
        case 'ul':
            insert = selected ? selected.split('\n').map(l => `- ${l}`).join('\n') : '- ';
            break;
        case 'ol':
            insert = selected ? selected.split('\n').map((l, i) => `${i + 1}. ${l}`).join('\n') : '1. ';
            break;
        case 'quote':
            insert = selected ? selected.split('\n').map(l => `> ${l}`).join('\n') : '> ';
            break;
        case 'hr':
            insert = '\n---\n';
            break;
        case 'fullscreen':
            toggleFullscreen();
            return;
        case 'wrap':
            toggleWrapMode();
            return;
    }
    
    insertAtCursor(insert);
}

// Line wrap mode toggle
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

function toggleWrapMode() {
    const container = document.getElementById('ce-editor');
    if (!container || !window.AvaCodeMirror) return;
    
    const newMode = window.AvaCodeMirror.cycleLineWrap(container);
    updateWrapButton(newMode);
}

function updateWrapButton(mode) {
    const btn = document.querySelector('[data-action="wrap"]');
    if (!btn) return;
    btn.title = 'Line wrap: ' + (wrapModeLabels[mode] || 'Full width');
    const icon = btn.querySelector('.material-symbols-rounded');
    if (icon) icon.textContent = wrapModeIcons[mode] || 'wrap_text';
}

// Initialize wrap mode from saved preference
if (window.AvaCodeMirror) {
    const savedMode = window.AvaCodeMirror.getSavedWrapMode();
    const container = document.getElementById('ce-editor');
    if (container) {
        window.AvaCodeMirror.setLineWrap(container, savedMode);
        updateWrapButton(savedMode);
    }
}

// Fullscreen mode
let isFullscreen = false;
function toggleFullscreen() {
    isFullscreen = !isFullscreen;
    editorContainer.classList.toggle('ce-fullscreen', isFullscreen);
    const icon = document.querySelector('[data-action="fullscreen"] .material-symbols-rounded');
    if (icon) icon.textContent = isFullscreen ? 'fullscreen_exit' : 'fullscreen';
    if (isFullscreen && cmEditor && cmEditor.focus) {
        cmEditor.focus();
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && isFullscreen) {
        toggleFullscreen();
    }
});

// Keyboard shortcuts for toolbar
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && document.activeElement.closest('#markdown-editor')) {
        switch (e.key.toLowerCase()) {
            case 'b':
                e.preventDefault();
                handleToolbarAction('bold');
                break;
            case 'i':
                e.preventDefault();
                handleToolbarAction('italic');
                break;
            case 'k':
                e.preventDefault();
                handleToolbarAction('link');
                break;
        }
    }
});

// Save shortcut
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        hiddenInput.value = getEditorContent();
        document.getElementById('content-editor-form').submit();
    }
});

// Initialize editor
initMarkdownEditor();

// =============================================================================
// Title sync
// =============================================================================
const titleInput = document.getElementById('field-title');
const headerTitle = document.getElementById('header-title');

titleInput.addEventListener('input', () => {
    headerTitle.textContent = titleInput.value || 'Untitled';
});

// =============================================================================
// Status buttons
// =============================================================================
document.querySelectorAll('.ce-status-option input').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.ce-status-option').forEach(opt => opt.classList.remove('active'));
        this.closest('.ce-status-option').classList.add('active');
    });
});

// =============================================================================
// Taxonomy tag selector
// =============================================================================
document.querySelectorAll('.ce-tag-selector').forEach(selector => {
    const searchInput = selector.querySelector('.ce-tag-search-input');
    const items = selector.querySelectorAll('.ce-tag-item');
    
    searchInput?.addEventListener('input', () => {
        const query = searchInput.value.toLowerCase();
        items.forEach(item => {
            const name = item.querySelector('.ce-tag-name').textContent.toLowerCase();
            const slug = item.dataset.slug.toLowerCase();
            item.style.display = (name.includes(query) || slug.includes(query)) ? '' : 'none';
        });
    });
    
    items.forEach(item => {
        const checkbox = item.querySelector('input[type="checkbox"]');
        checkbox.addEventListener('change', () => {
            item.classList.toggle('checked', checkbox.checked);
        });
    });
});

// =============================================================================
// Character counters
// =============================================================================
document.querySelectorAll('.ce-char-count').forEach(counter => {
    const fieldId = counter.dataset.for;
    const max = parseInt(counter.dataset.max);
    const field = document.getElementById(fieldId);
    
    if (field) {
        const update = () => {
            const len = field.value.length;
            counter.textContent = `${len}/${max}`;
            counter.classList.toggle('over', len > max);
        };
        field.addEventListener('input', update);
        update();
    }
});

// =============================================================================
// SEO Preview (truncates at Google limits)
// =============================================================================
const seoTitlePreview = document.getElementById('seo-preview-title');
const seoDescPreview = document.getElementById('seo-preview-desc');
const metaTitleField = document.getElementById('field-meta-title');
const metaDescField = document.getElementById('field-meta-desc');
const excerptField = document.getElementById('field-excerpt');

function truncateText(text, maxLength) {
    if (!text || text.length <= maxLength) return text;
    return text.substring(0, maxLength).trim() + '...';
}

function updateSeoPreview() {
    if (seoTitlePreview) {
        const title = metaTitleField?.value || titleInput?.value || 'Page Title';
        seoTitlePreview.textContent = truncateText(title, 60);
    }
    if (seoDescPreview) {
        const desc = metaDescField?.value || excerptField?.value || 'Page description will appear here...';
        seoDescPreview.textContent = truncateText(desc, 160);
    }
}

[titleInput, metaTitleField, metaDescField, excerptField].forEach(el => {
    el?.addEventListener('input', updateSeoPreview);
});

// =============================================================================
// Collapsible groups - localStorage persistence
// =============================================================================
const STORAGE_KEY = `ava-editor-groups-${CONTENT_TYPE}`;

function loadGroupStates() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            const states = JSON.parse(saved);
            document.querySelectorAll('[data-group]').forEach(el => {
                const group = el.dataset.group;
                if (states[group] !== undefined) {
                    el.open = states[group];
                }
            });
        }
    } catch (e) {
        // Ignore localStorage errors
    }
}

function saveGroupStates() {
    try {
        const states = {};
        document.querySelectorAll('[data-group]').forEach(el => {
            states[el.dataset.group] = el.open;
        });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(states));
    } catch (e) {
        // Ignore localStorage errors
    }
}

// Load states on page load
loadGroupStates();

// Save state when toggled
document.querySelectorAll('[data-group]').forEach(el => {
    el.addEventListener('toggle', saveGroupStates);
});

// =============================================================================
// Slug generation
// =============================================================================
function generateSlugFromTitle() {
    const title = titleInput.value;
    const slug = title.toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/[\s_]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
    document.getElementById('field-slug').value = slug;
}

function generateId() {
    const t = Date.now().toString(32).toUpperCase().padStart(10, '0');
    const chars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    let r = '';
    for (let i = 0; i < 16; i++) {
        r += chars[Math.floor(Math.random() * 32)];
    }
    document.getElementById('field-id').value = t + r;
}

// =============================================================================
// Array fields
// =============================================================================
function addArrayItem(containerId, fieldName, placeholder = '', isKeyValue = false) {
    const container = document.getElementById(containerId);
    const div = document.createElement('div');
    
    // Find next index for keyValue arrays
    const existingItems = container.querySelectorAll('.ce-array-item').length;
    
    if (isKeyValue) {
        div.className = 'ce-array-item ce-array-item-kv';
        div.innerHTML = `
            <input type="text" name="fields[${fieldName}][${existingItems}][key]" class="ce-input ce-kv-key" placeholder="Key">
            <input type="text" name="fields[${fieldName}][${existingItems}][value]" class="ce-input ce-kv-value" placeholder="Value">
            <button type="button" class="ce-array-remove" onclick="this.parentElement.remove()">
                <span class="material-symbols-rounded">close</span>
            </button>
        `;
    } else {
        div.className = 'ce-array-item';
        div.innerHTML = `
            <input type="text" name="fields[${fieldName}][]" class="ce-input" placeholder="${placeholder}">
            <button type="button" class="ce-array-remove" onclick="this.parentElement.remove()">
                <span class="material-symbols-rounded">close</span>
            </button>
        `;
    }
    
    container.appendChild(div);
    div.querySelector('input').focus();
}

// =============================================================================
// Media Picker
// =============================================================================
let mediaTarget = null;
let selectedMedia = null;
let allMedia = [];
let mediaFolders = [];
let mediaDisplayed = 0;
const MEDIA_PAGE_SIZE = 25;

function openMediaPicker(target) {
    mediaTarget = target;
    selectedMedia = null;
    mediaDisplayed = 0;
    document.getElementById('media-search').value = '';
    document.getElementById('media-folder').value = '';
    document.getElementById('selected-media-info').textContent = '';
    document.getElementById('media-insert-btn').disabled = true;
    document.getElementById('media-modal').style.display = 'flex';
    loadMedia();
}

function closeMediaPicker() {
    document.getElementById('media-modal').style.display = 'none';
    mediaTarget = null;
    selectedMedia = null;
}

async function loadMedia() {
    const grid = document.getElementById('media-grid');
    grid.innerHTML = '<div class="empty-state"><span class="material-symbols-rounded spin">sync</span><p>Loading...</p></div>';
    document.getElementById('media-load-more').style.display = 'none';
    
    try {
        const url = ADMIN_URL + '/api/media';
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        
        if (!response.ok) throw new Error('Failed to load');
        
        const data = await response.json();
        allMedia = data.files || [];
        mediaFolders = data.folders || [];
        
        // Populate folder dropdown
        const folderSelect = document.getElementById('media-folder');
        folderSelect.innerHTML = '<option value="">All Folders</option>';
        mediaFolders.forEach(f => {
            folderSelect.innerHTML += `<option value="${f}">${f}</option>`;
        });
        
        // Also add date folders from file paths
        const dateFolders = new Set();
        allMedia.forEach(file => {
            const match = file.path.match(/^(\d{4}\/\d{2})\//);
            if (match) dateFolders.add(match[1]);
        });
        dateFolders.forEach(f => {
            if (!mediaFolders.includes(f)) {
                folderSelect.innerHTML += `<option value="${f}">${f}</option>`;
            }
        });
        
        mediaDisplayed = 0;
        filterMedia();
    } catch (err) {
        grid.innerHTML = '<div class="empty-state"><span class="material-symbols-rounded">error</span><p>Failed to load media</p></div>';
    }
}

function getFilteredMedia() {
    const query = document.getElementById('media-search').value.toLowerCase().trim();
    const folder = document.getElementById('media-folder').value;
    
    return allMedia.filter(file => {
        // Filter by search query
        if (query && !file.name.toLowerCase().includes(query)) {
            return false;
        }
        // Filter by folder
        if (folder && !file.path.startsWith(folder + '/') && !file.path.startsWith(folder)) {
            return false;
        }
        return true;
    });
}

function filterMedia() {
    mediaDisplayed = 0;
    const filtered = getFilteredMedia();
    
    // Always paginate - show first page, user can load more
    const toShow = filtered.slice(0, MEDIA_PAGE_SIZE);
    mediaDisplayed = toShow.length;
    
    renderMediaGrid(toShow);
    updateLoadMoreButton(filtered.length);
}

function loadMoreMedia() {
    const filtered = getFilteredMedia();
    const nextBatch = filtered.slice(mediaDisplayed, mediaDisplayed + MEDIA_PAGE_SIZE);
    mediaDisplayed += nextBatch.length;
    
    appendMediaItems(nextBatch);
    updateLoadMoreButton(filtered.length);
}

function updateLoadMoreButton(totalCount) {
    const loadMoreDiv = document.getElementById('media-load-more');
    const countSpan = document.getElementById('media-count');
    
    if (mediaDisplayed < totalCount) {
        loadMoreDiv.style.display = 'flex';
        countSpan.textContent = `Showing ${mediaDisplayed} of ${totalCount}`;
    } else if (totalCount > 0) {
        loadMoreDiv.style.display = 'flex';
        countSpan.textContent = `${totalCount} file${totalCount !== 1 ? 's' : ''}`;
        loadMoreDiv.querySelector('button').style.display = 'none';
    } else {
        loadMoreDiv.style.display = 'none';
    }
    
    // Show/hide the load more button
    const btn = loadMoreDiv.querySelector('button');
    if (btn) btn.style.display = mediaDisplayed < totalCount ? '' : 'none';
}

function renderMediaGrid(files) {
    const grid = document.getElementById('media-grid');
    
    if (files.length === 0) {
        grid.innerHTML = '<div class="empty-state"><span class="material-symbols-rounded">folder_off</span><p>No files found</p></div>';
        return;
    }
    
    grid.innerHTML = files.map(file => createMediaItemHtml(file)).join('');
}

function appendMediaItems(files) {
    const grid = document.getElementById('media-grid');
    files.forEach(file => {
        grid.insertAdjacentHTML('beforeend', createMediaItemHtml(file));
    });
}

function createMediaItemHtml(file) {
    const isImage = /\.(jpg|jpeg|png|gif|webp|svg|avif)$/i.test(file.name);
    const isSelected = selectedMedia?.path === file.path;
    return `
        <div class="media-item media-item-selectable ${isSelected ? 'selected' : ''}" 
             data-path="${file.path}" data-url="${file.url}" onclick="selectMedia(this)">
            <div class="media-item-preview">
                ${isImage ? `<img src="${file.url}" alt="${file.name}" loading="lazy">` : '<span class="material-symbols-rounded">description</span>'}
            </div>
            <div class="media-item-info">
                <span class="media-item-name" title="${file.name}">${file.name}</span>
            </div>
        </div>
    `;
}

function selectMedia(el) {
    document.querySelectorAll('.media-item.selected').forEach(i => i.classList.remove('selected'));
    el.classList.add('selected');
    
    selectedMedia = {
        path: el.dataset.path,
        url: el.dataset.url,
        name: el.querySelector('.media-item-name').textContent
    };
    
    document.getElementById('selected-media-info').textContent = selectedMedia.name;
    document.getElementById('media-insert-btn').disabled = false;
}

function insertSelectedMedia() {
    if (!selectedMedia) return;
    
    const path = '@media:' + selectedMedia.path.replace(/^\/?(media\/)?/, '');
    
    if (mediaTarget === 'body') {
        const isImage = /\.(jpg|jpeg|png|gif|webp|svg|avif)$/i.test(selectedMedia.name);
        const markdown = isImage ? `![${selectedMedia.name}](${path})` : `[${selectedMedia.name}](${path})`;
        
        // Use CodeMirror to insert
        insertAtCursor(markdown);
    } else if (mediaTarget === 'og_image') {
        document.getElementById('field-og-image').value = path;
    } else {
        const field = document.querySelector(`[name="fields[${mediaTarget}]"]`);
        if (field) field.value = path;
    }
    
    closeMediaPicker();
}

// Close modal on backdrop click
document.querySelectorAll('.ce-modal-backdrop').forEach(el => {
    el.addEventListener('click', closeMediaPicker);
});

// Close modal on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('media-modal');
        if (modal.style.display === 'flex') closeMediaPicker();
    }
});

// =============================================================================
// Form validation
// =============================================================================
document.getElementById('content-editor-form').addEventListener('submit', e => {
    const title = titleInput.value.trim();
    const slug = document.getElementById('field-slug').value.trim();
    
    if (!title) {
        e.preventDefault();
        alert('Title is required.');
        titleInput.focus();
        return;
    }
    
    if (!slug) {
        e.preventDefault();
        alert('URL slug is required.');
        document.getElementById('field-slug').focus();
        return;
    }
});
</script>
