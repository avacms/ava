<?php

declare(strict_types=1);

namespace Ava\Fields;

use Ava\Application;

/**
 * Field Renderer
 *
 * Renders field forms for the admin editor.
 */
final class FieldRenderer
{
    private FieldValidator $validator;
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->validator = new FieldValidator($app);
    }

    /**
     * Render all configured fields for a content type.
     *
     * @param array $frontmatter Current frontmatter values
     * @param string $contentType The content type
     * @param array $context Additional context (csrf, admin_url, etc.)
     * @return string HTML for all fields
     */
    public function renderFields(array $frontmatter, string $contentType, array $context = []): string
    {
        $fields = $this->validator->getFieldsWithValues($frontmatter, $contentType);
        
        if (empty($fields)) {
            return '';
        }

        // Prepare context with additional data
        $context = $this->prepareContext($context, $contentType);
        
        $html = '<div class="field-panel" data-content-type="' . htmlspecialchars($contentType) . '">';
        
        // Group fields by section if defined
        $groupedFields = $this->groupFieldsBySection($fields);
        
        foreach ($groupedFields as $section => $sectionFields) {
            if ($section !== 'default' && !empty($section)) {
                $html .= '<fieldset class="field-section">';
                $html .= '<legend>' . htmlspecialchars($section) . '</legend>';
            }
            
            foreach ($sectionFields as $name => $data) {
                $html .= $data['field']->render($data['value'], $context);
            }
            
            if ($section !== 'default' && !empty($section)) {
                $html .= '</fieldset>';
            }
        }
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Render built-in fields (status, taxonomies, SEO, etc.).
     *
     * @param \Ava\Content\Item|null $item The content item (null for new content)
     * @param array $typeConfig Content type configuration
     * @param array $context Additional context
     * @return string HTML for built-in fields
     */
    public function renderBuiltInFields(?\Ava\Content\Item $item, array $typeConfig, array $context = []): string
    {
        $html = '';
        $registry = $this->validator->registry();
        
        // === Status Field ===
        $statusField = $registry->get('status');
        $statusValue = $item?->status() ?? 'draft';
        $html .= '<div class="field-section-inline">';
        $html .= $statusField->render('status', $statusValue, ['label' => 'Status'], $context);
        $html .= '</div>';

        // === Date Field (if uses dates) ===
        $usesDate = in_array($typeConfig['sorting'] ?? '', ['date_desc', 'date_asc'], true);
        if ($usesDate) {
            $dateField = $registry->get('date');
            $dateValue = $item?->date()?->format('Y-m-d') ?? date('Y-m-d');
            $html .= $dateField->render('date', $dateValue, ['label' => 'Date'], $context);
        }

        // === Taxonomies ===
        $taxonomies = $typeConfig['taxonomies'] ?? [];
        if (!empty($taxonomies)) {
            $taxField = $registry->get('taxonomy');
            foreach ($taxonomies as $taxName) {
                $taxConfig = $context['taxonomyConfig'][$taxName] ?? [];
                $taxLabel = $taxConfig['label'] ?? ucfirst($taxName);
                $taxValue = $item?->terms($taxName) ?? [];
                
                $html .= $taxField->render($taxName, $taxValue, [
                    'label' => $taxLabel,
                    'taxonomy' => $taxName,
                    'multiple' => true,
                ], $context);
            }
        }

        return $html;
    }

    /**
     * Render SEO fields section.
     *
     * @param \Ava\Content\Item|null $item The content item
     * @param array $context Additional context
     * @return string HTML for SEO fields
     */
    public function renderSeoFields(?\Ava\Content\Item $item, array $context = []): string
    {
        $registry = $this->validator->registry();
        $textField = $registry->get('text');
        $textareaField = $registry->get('textarea');
        $checkboxField = $registry->get('checkbox');
        $imageField = $registry->get('image');

        $html = '<fieldset class="field-section">';
        $html .= '<legend>SEO</legend>';

        // Meta Title
        $html .= $textField->render('meta_title', $item?->metaTitle() ?? '', [
            'label' => 'Meta Title',
            'description' => 'Override the page title for search engines',
            'placeholder' => 'Leave empty to use page title',
            'maxLength' => 60,
        ], $context);

        // Meta Description
        $html .= $textareaField->render('meta_description', $item?->metaDescription() ?? '', [
            'label' => 'Meta Description',
            'description' => 'Brief description for search results',
            'placeholder' => 'Write a compelling description...',
            'maxLength' => 160,
            'rows' => 2,
        ], $context);

        // OG Image
        $html .= $imageField->render('og_image', $item?->ogImage() ?? '', [
            'label' => 'Social Image',
            'description' => 'Image for social media sharing (1200×630 recommended)',
        ], $context);

        // Noindex
        $html .= $checkboxField->render('noindex', $item?->noindex() ?? false, [
            'label' => 'Search Visibility',
            'checkboxLabel' => 'Hide from search engines (noindex)',
        ], $context);

        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Render advanced/behavior fields section.
     *
     * @param \Ava\Content\Item|null $item The content item
     * @param array $typeConfig Content type configuration
     * @param array $context Additional context
     * @return string HTML for advanced fields
     */
    public function renderAdvancedFields(?\Ava\Content\Item $item, array $typeConfig, array $context = []): string
    {
        $registry = $this->validator->registry();
        $textField = $registry->get('text');
        $checkboxField = $registry->get('checkbox');
        $arrayField = $registry->get('array');
        $templateField = $registry->get('template');

        $html = '<fieldset class="field-section">';
        $html .= '<legend>Advanced</legend>';

        // Template
        $html .= $templateField->render('template', $item?->template() ?? '', [
            'label' => 'Template',
            'description' => 'Override the default template',
            'defaultTemplate' => $typeConfig['templates']['single'] ?? null,
        ], $context);

        // Cacheable
        $html .= $checkboxField->render('cache', $item?->get('cache') ?? true, [
            'label' => 'Caching',
            'checkboxLabel' => 'Enable page caching',
        ], $context);

        // Redirect From
        $html .= $arrayField->render('redirect_from', $item?->redirectFrom() ?? [], [
            'label' => 'Redirect From',
            'description' => 'URLs that should redirect to this page (301)',
            'valuePlaceholder' => '/old-url',
        ], $context);

        // Per-item Assets
        $html .= '<div class="field-subgroup">';
        $html .= '<label class="field-label">Per-Page Assets</label>';
        
        $html .= $arrayField->render('assets_css', $item?->css() ?? [], [
            'label' => 'CSS Files',
            'valuePlaceholder' => '@media:css/custom.css',
        ], $context);
        
        $html .= $arrayField->render('assets_js', $item?->js() ?? [], [
            'label' => 'JS Files',
            'valuePlaceholder' => '@media:js/script.js',
        ], $context);
        
        $html .= '</div>';

        $html .= '</fieldset>';

        return $html;
    }

    /**
     * Get all JavaScript needed for fields.
     *
     * @param string $contentType The content type
     * @return string Combined JavaScript
     */
    public function getFieldsJavaScript(string $contentType): string
    {
        $fields = $this->validator->getFields($contentType);
        $scripts = [];
        $addedTypes = [];

        // Collect unique type scripts
        foreach ($fields as $field) {
            $typeName = $field->typeName();
            if (!isset($addedTypes[$typeName])) {
                $js = $field->javascript();
                if ($js !== '') {
                    $scripts[] = $js;
                }
                $addedTypes[$typeName] = true;
            }
        }

        // Add scripts for built-in field types
        $builtInTypes = ['status', 'taxonomy', 'text', 'textarea', 'checkbox', 'image', 'array', 'gallery', 'color'];
        $registry = $this->validator->registry();
        
        foreach ($builtInTypes as $typeName) {
            if (!isset($addedTypes[$typeName])) {
                $type = $registry->get($typeName);
                if ($type) {
                    $js = $type->javascript();
                    if ($js !== '') {
                        $scripts[] = $js;
                    }
                }
                $addedTypes[$typeName] = true;
            }
        }

        // Add common field validation script
        $scripts[] = $this->getCommonValidationScript();

        return implode("\n\n", $scripts);
    }

    /**
     * Prepare context with additional data for rendering.
     */
    private function prepareContext(array $context, string $contentType): array
    {
        // Add available terms for taxonomy fields
        if (!isset($context['availableTerms'])) {
            $context['availableTerms'] = [];
            $contentTypes = $this->app->config('content_types', []);
            $typeConfig = $contentTypes[$contentType] ?? [];
            $taxonomies = $typeConfig['taxonomies'] ?? [];
            
            foreach ($taxonomies as $taxName) {
                $context['availableTerms'][$taxName] = $this->app->repository()->terms($taxName);
            }
        }

        // Add available templates
        if (!isset($context['templates'])) {
            $themePath = $this->app->path('app/themes/' . $this->app->config('theme', 'default') . '/templates');
            $context['templates'] = [];
            if (is_dir($themePath)) {
                foreach (glob($themePath . '/*.php') as $file) {
                    $context['templates'][] = basename($file);
                }
            }
        }

        // Add content items for content reference fields (only if needed)
        if (!isset($context['contentItems'])) {
            $context['contentItems'] = [];
            
            // Only load content items if there are content-type fields configured
            $contentTypes = $this->app->config('content_types', []);
            $typeConfig = $contentTypes[$contentType] ?? [];
            $hasContentField = false;
            foreach ($typeConfig['fields'] ?? [] as $fieldDef) {
                if (($fieldDef['type'] ?? '') === 'content') {
                    $hasContentField = true;
                    break;
                }
            }
            
            if ($hasContentField) {
                $repository = $this->app->repository();
                foreach ($repository->types() as $type) {
                    $items = $repository->allMeta($type);
                    $context['contentItems'][$type] = array_map(fn($item) => [
                        'slug' => $item->slug(),
                        'title' => $item->title(),
                        'id' => $item->id(),
                    ], $items);
                }
            }
        }

        return $context;
    }

    /**
     * Group fields by their 'section' config option.
     */
    private function groupFieldsBySection(array $fields): array
    {
        $grouped = ['default' => []];

        foreach ($fields as $name => $data) {
            $section = $data['field']->option('section', 'default');
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][$name] = $data;
        }

        return $grouped;
    }

    /**
     * Get common validation JavaScript.
     */
    private function getCommonValidationScript(): string
    {
        return <<<'JS'
// Common field validation
const FieldValidation = {
    init: function() {
        document.querySelectorAll('.field-input').forEach(function(input) {
            input.addEventListener('blur', function() {
                FieldValidation.validateField(this);
            });
            input.addEventListener('input', function() {
                // Clear error on input
                const group = this.closest('.field-group');
                if (group) {
                    const error = group.querySelector('.field-error');
                    if (error) {
                        error.hidden = true;
                        error.textContent = '';
                    }
                }
            });
        });
    },
    
    validateField: function(input) {
        const group = input.closest('.field-group');
        if (!group) return true;
        
        const error = group.querySelector('.field-error');
        const required = input.hasAttribute('required');
        const value = input.value.trim();
        
        // Required check
        if (required && value === '') {
            this.showError(error, 'This field is required.');
            return false;
        }
        
        // Length checks
        const minLength = parseInt(input.getAttribute('minlength'));
        const maxLength = parseInt(input.getAttribute('maxlength'));
        
        if (minLength && value.length < minLength) {
            this.showError(error, 'Must be at least ' + minLength + ' characters.');
            return false;
        }
        
        if (maxLength && value.length > maxLength) {
            this.showError(error, 'Must be no more than ' + maxLength + ' characters.');
            return false;
        }
        
        // Pattern check
        const pattern = input.getAttribute('pattern');
        if (pattern && value && !new RegExp(pattern).test(value)) {
            this.showError(error, 'Value does not match required format.');
            return false;
        }
        
        // Number checks
        if (input.type === 'number' && value !== '') {
            const min = parseFloat(input.getAttribute('min'));
            const max = parseFloat(input.getAttribute('max'));
            const numValue = parseFloat(value);
            
            if (!isNaN(min) && numValue < min) {
                this.showError(error, 'Must be at least ' + min + '.');
                return false;
            }
            
            if (!isNaN(max) && numValue > max) {
                this.showError(error, 'Must be no more than ' + max + '.');
                return false;
            }
        }
        
        this.hideError(error);
        return true;
    },
    
    showError: function(el, message) {
        if (el) {
            el.textContent = message;
            el.hidden = false;
        }
    },
    
    hideError: function(el) {
        if (el) {
            el.hidden = true;
            el.textContent = '';
        }
    },
    
    validateAll: function() {
        let valid = true;
        document.querySelectorAll('.field-input').forEach(function(input) {
            if (!FieldValidation.validateField(input)) {
                valid = false;
            }
        });
        return valid;
    }
};

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', FieldValidation.init.bind(FieldValidation));
} else {
    FieldValidation.init();
}

// Validate on form submit
document.querySelectorAll('form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        if (!FieldValidation.validateAll()) {
            e.preventDefault();
            const firstError = document.querySelector('.field-error:not([hidden])');
            if (firstError) {
                firstError.closest('.field-group').scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
});
JS;
    }
}
