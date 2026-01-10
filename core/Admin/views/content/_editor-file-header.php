<?php
/**
 * Shared editor header (file icon + filename + actions)
 *
 * Required variables:
 * - $fileIcon: string (Material symbol name)
 * - $currentFilename: string
 */
?>
<div class="editor-header">
    <div class="editor-file-info">
        <span class="material-symbols-rounded"><?= htmlspecialchars($fileIcon) ?></span>
        <div class="filename-editor">
            <input type="text" name="filename" id="filename" class="filename-input" 
                   value="<?= htmlspecialchars($currentFilename) ?>" 
                   placeholder="filename" 
                   pattern="[a-z0-9]+(-[a-z0-9]+)*"
                   title="Lowercase alphanumeric with hyphens only"
                   autocomplete="off"
                   spellcheck="false">
            <span class="filename-ext">.md</span>
            <button type="button" class="btn-icon-inline" onclick="generateFilenameFromContent()" title="Generate filename from date and slug">
                <span class="material-symbols-rounded">auto_fix_high</span>
            </button>
        </div>
        <button type="button" class="btn-frontmatter" onclick="openFrontmatterGenerator()" title="Generate frontmatter fields">
            <span class="material-symbols-rounded">edit_note</span>
            <span>Frontmatter</span>
        </button>
    </div>
</div>
