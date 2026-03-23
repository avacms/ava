<?php

declare(strict_types=1);

namespace Ava\Fields;

use Ava\Application;

/**
 * Field Validator
 *
 * Validates frontmatter against configured field definitions.
 * Integrates with the content linter.
 */
final class FieldValidator
{
    private FieldRegistry $registry;
    private Application $app;

    /** @var array<string, array<string, Field>> Cached fields by content type */
    private array $fieldsCache = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->registry = new FieldRegistry();
    }

    /**
     * Get the field registry.
     */
    public function registry(): FieldRegistry
    {
        return $this->registry;
    }

    /**
     * Validate frontmatter against field definitions for a content type.
     *
     * @param array $frontmatter The frontmatter to validate
     * @param string $contentType The content type (e.g., 'post', 'page')
     * @return ValidationResult
     */
    public function validate(array $frontmatter, string $contentType): ValidationResult
    {
        $fieldDefinitions = $this->getFieldDefinitions($contentType);
        
        if (empty($fieldDefinitions)) {
            // No fields defined, nothing to validate
            return ValidationResult::success();
        }

        $results = [];

        foreach ($fieldDefinitions as $name => $config) {
            $field = $this->registry->createField($name, $config);
            
            if ($field === null) {
                $results[] = ValidationResult::warning("Unknown field type '{$config['type']}' for field '{$name}'");
                continue;
            }

            $value = $frontmatter[$name] ?? null;
            $result = $field->validate($value);

            if (!$result->isValid()) {
                // Prefix errors with field name
                foreach ($result->errors() as $error) {
                    $results[] = ValidationResult::error("{$field->label()}: {$error}");
                }
            }
            
            foreach ($result->warnings() as $warning) {
                $results[] = ValidationResult::warning("{$field->label()}: {$warning}");
            }
        }

        if (empty($results)) {
            return ValidationResult::success();
        }

        return ValidationResult::merge(...$results);
    }

    /**
     * Validate a single field value.
     *
     * @param string $fieldName The field name
     * @param mixed $value The value to validate
     * @param string $contentType The content type
     * @return ValidationResult
     */
    public function validateField(string $fieldName, mixed $value, string $contentType): ValidationResult
    {
        $fieldDefinitions = $this->getFieldDefinitions($contentType);
        
        if (!isset($fieldDefinitions[$fieldName])) {
            return ValidationResult::success(); // No definition, no validation
        }

        $field = $this->registry->createField($fieldName, $fieldDefinitions[$fieldName]);
        
        if ($field === null) {
            return ValidationResult::warning("Unknown field type");
        }

        return $field->validate($value);
    }

    /**
     * Get all validation errors for a frontmatter array.
     * Returns a flat array of error strings suitable for linting.
     *
     * @param array $frontmatter The frontmatter to validate
     * @param string $contentType The content type
     * @return array<string>
     */
    public function getErrors(array $frontmatter, string $contentType): array
    {
        $result = $this->validate($frontmatter, $contentType);
        return $result->errors();
    }

    /**
     * Get field definitions for a content type.
     *
     * @return array<string, array>
     */
    public function getFieldDefinitions(string $contentType): array
    {
        // Use Application's cached content types (avoids repeated file reads)
        $contentTypes = $this->app->contentTypes();
        $typeConfig = $contentTypes[$contentType] ?? [];
        
        return $typeConfig['fields'] ?? [];
    }

    /**
     * Get fields for a content type as Field objects.
     *
     * @return array<string, Field>
     */
    public function getFields(string $contentType): array
    {
        if (isset($this->fieldsCache[$contentType])) {
            return $this->fieldsCache[$contentType];
        }

        $definitions = $this->getFieldDefinitions($contentType);
        $fields = [];

        foreach ($definitions as $name => $config) {
            $field = $this->registry->createField($name, $config);
            if ($field !== null) {
                $fields[$name] = $field;
            }
        }

        $this->fieldsCache[$contentType] = $fields;
        return $fields;
    }

    /**
     * Get all configured fields with their current values from frontmatter.
     *
     * @param array $frontmatter The current frontmatter
     * @param string $contentType The content type
     * @return array<string, array{field: Field, value: mixed}>
     */
    public function getFieldsWithValues(array $frontmatter, string $contentType): array
    {
        $fields = $this->getFields($contentType);
        $result = [];

        foreach ($fields as $name => $field) {
            $value = $frontmatter[$name] ?? null;
            $result[$name] = [
                'field' => $field,
                'value' => $field->fromStorage($value),
            ];
        }

        return $result;
    }

    /**
     * Apply default values to frontmatter for fields that don't have values.
     *
     * @param array $frontmatter The current frontmatter
     * @param string $contentType The content type
     * @return array The frontmatter with defaults applied
     */
    public function applyDefaults(array $frontmatter, string $contentType): array
    {
        $fields = $this->getFields($contentType);

        foreach ($fields as $name => $field) {
            if (!array_key_exists($name, $frontmatter) || $frontmatter[$name] === null) {
                $default = $field->defaultValue();
                if ($default !== null) {
                    $frontmatter[$name] = $default;
                }
            }
        }

        return $frontmatter;
    }

    /**
     * Transform field values for storage.
     *
     * @param array $values The raw form values (from $_POST['fields'])
     * @param string $contentType The content type
     * @return array The values ready for YAML storage
     */
    public function prepareForStorage(array $values, string $contentType): array
    {
        $fields = $this->getFields($contentType);
        $result = [];

        foreach ($values as $name => $value) {
            if (isset($fields[$name])) {
                $result[$name] = $fields[$name]->toStorage($value);
            } else {
                // Pass through unknown fields as-is
                $result[$name] = $value;
            }
        }

        return $result;
    }
}
