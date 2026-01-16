<?php

declare(strict_types=1);

namespace Ava\Fields\Types;

use Ava\Fields\AbstractFieldType;
use Ava\Fields\ValidationResult;

/**
 * Array Field Type
 *
 * Dynamic list of items with optional key-value pairs.
 */
final class ArrayField extends AbstractFieldType
{
    public function name(): string
    {
        return 'array';
    }

    public function label(): string
    {
        return 'Array';
    }

    public function schema(): array
    {
        return array_merge($this->baseSchema(), [
            'associative' => [
                'type' => 'bool',
                'label' => 'Key-Value Pairs',
                'description' => 'Store as key:value pairs instead of a simple list',
                'default' => false,
            ],
            'allowEmptyValues' => [
                'type' => 'bool',
                'label' => 'Allow Empty Values',
                'description' => 'For associative arrays, allow empty values',
                'default' => true,
            ],
            'minItems' => [
                'type' => 'int',
                'label' => 'Minimum Items',
                'description' => 'Minimum number of items required',
            ],
            'maxItems' => [
                'type' => 'int',
                'label' => 'Maximum Items',
                'description' => 'Maximum number of items allowed',
            ],
            'keyPlaceholder' => [
                'type' => 'string',
                'label' => 'Key Placeholder',
                'description' => 'Placeholder text for key input',
                'default' => 'Key',
            ],
            'valuePlaceholder' => [
                'type' => 'string',
                'label' => 'Value Placeholder',
                'description' => 'Placeholder text for value input',
                'default' => 'Value',
            ],
        ]);
    }

    public function validate(mixed $value, array $config): ValidationResult
    {
        if (!is_array($value)) {
            return ValidationResult::error('Value must be an array.');
        }

        // Support both 'associative' and 'keyValue' config options
        $associative = $config['associative'] ?? $config['keyValue'] ?? false;
        $allowEmptyValues = $config['allowEmptyValues'] ?? true;
        $count = count($value);

        if (isset($config['minItems']) && $count < $config['minItems']) {
            return ValidationResult::error("At least {$config['minItems']} item(s) required.");
        }

        if (isset($config['maxItems']) && $count > $config['maxItems']) {
            return ValidationResult::error("No more than {$config['maxItems']} item(s) allowed.");
        }

        if ($associative && !$allowEmptyValues) {
            foreach ($value as $key => $val) {
                if (trim((string) $val) === '') {
                    return ValidationResult::error("Empty values not allowed for key: {$key}");
                }
            }
        }

        return ValidationResult::success();
    }

    public function toStorage(mixed $value, array $config): mixed
    {
        // Handle string input (comma-separated)
        if (is_string($value)) {
            if (trim($value) === '') {
                return [];
            }
            $value = array_map('trim', explode(',', $value));
        }
        
        if (!is_array($value)) {
            return [];
        }

        // Support both 'associative' and 'keyValue' config options
        $associative = $config['associative'] ?? $config['keyValue'] ?? false;

        if ($associative) {
            // Filter out entries with empty keys
            $filtered = [];
            foreach ($value as $key => $val) {
                if (is_array($val) && isset($val['key']) && isset($val['value'])) {
                    // From form submission: [['key' => 'foo', 'value' => 'bar'], ...]
                    $k = trim((string) $val['key']);
                    if ($k !== '') {
                        $filtered[$k] = $val['value'];
                    }
                } else {
                    // Already in key => value format
                    if (is_string($key) && trim($key) !== '') {
                        $filtered[$key] = $val;
                    }
                }
            }
            return $filtered;
        }

        // Simple list: filter empty values and reindex
        return array_values(array_filter($value, fn($v) => !is_array($v) && trim((string) $v) !== ''));
    }

    public function fromStorage(mixed $value, array $config): mixed
    {
        if (!is_array($value)) {
            return [];
        }

        // Support both 'associative' and 'keyValue' config options
        $associative = $config['associative'] ?? $config['keyValue'] ?? false;

        if ($associative) {
            // Convert to [['key' => k, 'value' => v], ...] for editing
            $result = [];
            foreach ($value as $key => $val) {
                $result[] = ['key' => $key, 'value' => $val];
            }
            return $result;
        }

        return array_values($value);
    }

    public function defaultValue(array $config): mixed
    {
        return $config['default'] ?? [];
    }

    public function render(string $name, mixed $value, array $config, array $context = []): string
    {
        $id = 'field-' . $this->e($name);
        $associative = $config['associative'] ?? false;
        $maxItems = $config['maxItems'] ?? null;
        $keyPlaceholder = $config['keyPlaceholder'] ?? 'Key';
        $valuePlaceholder = $config['valuePlaceholder'] ?? 'Value';
        $items = $this->fromStorage($value ?? [], $config);
        
        $input = '<div class="array-input-group" id="' . $this->e($id) . '-container" ';
        $input .= 'data-field="' . $this->e($name) . '" ';
        $input .= 'data-associative="' . ($associative ? 'true' : 'false') . '"';
        if ($maxItems) {
            $input .= ' data-max-items="' . $maxItems . '"';
        }
        $input .= '>';
        
        // Items list
        $input .= '<div class="array-items" id="' . $this->e($id) . '-items">';
        
        if ($associative) {
            foreach ($items as $index => $item) {
                $input .= $this->renderAssociativeItem($name, $index, $item['key'] ?? '', $item['value'] ?? '', $keyPlaceholder, $valuePlaceholder);
            }
        } else {
            foreach ($items as $index => $item) {
                $input .= $this->renderSimpleItem($name, $index, $item, $valuePlaceholder);
            }
        }
        
        $input .= '</div>';
        
        // Add button
        $input .= '<button type="button" class="btn btn-secondary btn-sm array-add-btn" ';
        $input .= 'data-target="' . $this->e($id) . '"';
        if ($maxItems && count($items) >= $maxItems) {
            $input .= ' disabled';
        }
        $input .= '>';
        $input .= '<span class="material-symbols-rounded">add</span> Add Item';
        $input .= '</button>';
        
        $input .= '</div>';

        return $this->wrapField($name, $input, $config);
    }

    private function renderSimpleItem(string $name, int $index, string $value, string $placeholder): string
    {
        $html = '<div class="array-item" data-index="' . $index . '">';
        $html .= '<input type="text" name="fields[' . $this->e($name) . '][]" value="' . $this->e($value) . '" ';
        $html .= 'class="form-control" placeholder="' . $this->e($placeholder) . '">';
        $html .= '<button type="button" class="array-item-remove btn-icon" title="Remove">';
        $html .= '<span class="material-symbols-rounded">close</span>';
        $html .= '</button>';
        $html .= '</div>';
        return $html;
    }

    private function renderAssociativeItem(string $name, int $index, string $key, string $value, string $keyPlaceholder, string $valuePlaceholder): string
    {
        $html = '<div class="array-item array-item-assoc" data-index="' . $index . '">';
        $html .= '<input type="text" name="fields[' . $this->e($name) . '][' . $index . '][key]" value="' . $this->e($key) . '" ';
        $html .= 'class="form-control array-item-key" placeholder="' . $this->e($keyPlaceholder) . '">';
        $html .= '<span class="array-separator">:</span>';
        $html .= '<input type="text" name="fields[' . $this->e($name) . '][' . $index . '][value]" value="' . $this->e($value) . '" ';
        $html .= 'class="form-control array-item-value" placeholder="' . $this->e($valuePlaceholder) . '">';
        $html .= '<button type="button" class="array-item-remove btn-icon" title="Remove">';
        $html .= '<span class="material-symbols-rounded">close</span>';
        $html .= '</button>';
        $html .= '</div>';
        return $html;
    }

    public function javascript(): string
    {
        return <<<'JS'
// Array field - add/remove items
document.querySelectorAll('.array-add-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const targetId = this.dataset.target;
        const container = document.getElementById(targetId + '-container');
        const itemsContainer = document.getElementById(targetId + '-items');
        const fieldName = container.dataset.field;
        const associative = container.dataset.associative === 'true';
        const maxItems = parseInt(container.dataset.maxItems) || Infinity;
        
        const currentCount = itemsContainer.querySelectorAll('.array-item').length;
        if (currentCount >= maxItems) {
            return;
        }
        
        const index = currentCount;
        const item = document.createElement('div');
        item.className = 'array-item' + (associative ? ' array-item-assoc' : '');
        item.dataset.index = index;
        
        if (associative) {
            item.innerHTML = '<input type="text" name="fields[' + fieldName + '][' + index + '][key]" class="form-control array-item-key" placeholder="Key">' +
                '<span class="array-separator">:</span>' +
                '<input type="text" name="fields[' + fieldName + '][' + index + '][value]" class="form-control array-item-value" placeholder="Value">' +
                '<button type="button" class="array-item-remove btn-icon" title="Remove"><span class="material-symbols-rounded">close</span></button>';
        } else {
            item.innerHTML = '<input type="text" name="fields[' + fieldName + '][]" class="form-control" placeholder="Value">' +
                '<button type="button" class="array-item-remove btn-icon" title="Remove"><span class="material-symbols-rounded">close</span></button>';
        }
        
        itemsContainer.appendChild(item);
        initArrayItemRemove(item.querySelector('.array-item-remove'));
        item.querySelector('input').focus();
        
        // Disable add button if max reached
        if (itemsContainer.querySelectorAll('.array-item').length >= maxItems) {
            btn.disabled = true;
        }
    });
});

function initArrayItemRemove(btn) {
    btn.addEventListener('click', function() {
        const item = this.closest('.array-item');
        const container = item.closest('.array-input-group');
        item.remove();
        // Re-enable add button
        const addBtn = container.querySelector('.array-add-btn');
        const maxItems = parseInt(container.dataset.maxItems) || Infinity;
        if (container.querySelectorAll('.array-item').length < maxItems) {
            addBtn.disabled = false;
        }
    });
}

// Init existing items
document.querySelectorAll('.array-item-remove').forEach(initArrayItemRemove);
JS;
    }
}
