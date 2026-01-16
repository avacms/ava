<?php
/**
 * Shared editor header (file icon + filename + actions)
 *
 * Required variables:
 * - $fileIcon: string (Material symbol name)
 * - $currentFilename: string
 * 
 * Optional variables:
 * - $showEditorActions: bool (show wrap/fullscreen buttons)
 */
$showEditorActions = $showEditorActions ?? true;
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
        </div>
    </div>
    <?php if ($showEditorActions): ?>
    <div class="editor-header-actions">
        <button type="button" class="editor-action-btn" id="wrap-toggle" title="Line wrap: Full width">
            <span class="material-symbols-rounded">wrap_text</span>
        </button>
        <button type="button" class="editor-action-btn" id="fullscreen-toggle" title="Fullscreen (Esc to exit)">
            <span class="material-symbols-rounded">fullscreen</span>
        </button>
    </div>
    <?php endif; ?>
</div>
