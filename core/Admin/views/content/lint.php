<?php
/**
 * Lint Content - Content Only View
 * 
 * Available variables:
 * - $errors: Array of validation errors
 * - $valid: Boolean indicating if all content is valid
 */
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">verified</span>
            Content Validation
        </span>
        <?php if ($valid): ?>
        <span class="badge badge-success">All Valid</span>
        <?php else: ?>
        <span class="badge badge-danger"><?= count($errors) ?> Error<?= count($errors) !== 1 ? 's' : '' ?></span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($valid): ?>
        <div class="lint-success">
            <span class="material-symbols-rounded">verified</span>
            <div>
                <strong>All content files are valid</strong>
                <p>No YAML or Markdown errors found across all content files.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="lint-error-summary">
            Found <?= count($errors) ?> error<?= count($errors) !== 1 ? 's' : '' ?> in content files:
        </div>
        <div class="lint-errors">
            <?php foreach ($errors as $error): ?>
            <div class="lint-error-item">
                <span class="material-symbols-rounded">error</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <span class="card-title">
            <span class="material-symbols-rounded">help</span>
            About Linting
        </span>
    </div>
    <div class="card-body">
        <p class="text-secondary text-sm mb-3">
            The linter checks all content files for:
        </p>
        <div class="list-item"><span class="list-label">YAML Syntax</span><span class="text-secondary text-sm">Valid frontmatter structure</span></div>
        <div class="list-item"><span class="list-label">Required Fields</span><span class="text-secondary text-sm">Title, date, status presence</span></div>
        <div class="list-item"><span class="list-label">Date Format</span><span class="text-secondary text-sm">ISO 8601 date format</span></div>
        <div class="list-item"><span class="list-label">Taxonomy Terms</span><span class="text-secondary text-sm">Valid taxonomy references</span></div>
    </div>
</div>
