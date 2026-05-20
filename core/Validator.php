<?php
namespace Core;

/**
 * WHISKER — Input Validator
 *
 * Usage:
 *   $v = new Validator($request->all(), [
 *       'name'  => 'required|min:2|max:100',
 *       'email' => 'required|email',
 *       'price' => 'required|numeric|min:0',
 *   ]);
 *   if ($v->fails()) { $errors = $v->errors(); }
 */
class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];

    public function __construct(array $data, array $rules)
    {
        $this->data  = $data;
        $this->rules = $rules;
        $this->validate();
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $fieldErrors) {
            return $fieldErrors[0] ?? null;
        }
        return null;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $label = ucfirst(str_replace('_', ' ', $field));

            foreach ($rules as $rule) {
                $parts = explode(':', $rule, 2);
                $ruleName = $parts[0];
                $param    = $parts[1] ?? null;

                $error = $this->checkRule($ruleName, $value, $param, $label);
                if ($error) {
                    $this->errors[$field][] = $error;
                }
            }
        }
    }

    private function checkRule(string $rule, $value, ?string $param, string $label): ?string
    {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || $value === []) {
                    return "{$label} is required.";
                }
                break;

            case 'email':
                // L13: use explicit empty-check rather than truthy-check —
                // `$value && ...` skips validation for legitimate values like
                // "0" (PHP-falsy but not empty). Only skip when truly empty.
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "{$label} must be a valid email.";
                }
                break;

            case 'numeric':
                if ($value !== null && $value !== '' && !is_numeric($value)) {
                    return "{$label} must be a number.";
                }
                break;

            case 'integer':
                if ($value !== null && $value !== '' && !ctype_digit((string)$value)) {
                    return "{$label} must be a whole number.";
                }
                break;

            case 'min':
                if (is_numeric($value) && $value < (float)$param) {
                    return "{$label} must be at least {$param}.";
                }
                if (is_string($value) && strlen($value) < (int)$param) {
                    return "{$label} must be at least {$param} characters.";
                }
                break;

            case 'max':
                if (is_numeric($value) && $value > (float)$param) {
                    return "{$label} must be no more than {$param}.";
                }
                if (is_string($value) && strlen($value) > (int)$param) {
                    return "{$label} must be no more than {$param} characters.";
                }
                break;

            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    return "{$label} must be a valid URL.";
                }
                break;

            case 'slug':
                if ($value !== null && $value !== '' && !preg_match('/^[a-z0-9\-]+$/', $value)) {
                    return "{$label} must contain only lowercase letters, numbers, and hyphens.";
                }
                break;

            case 'confirmed':
                $confirmField = $param ?? ($label . '_confirmation');
                $confirmValue = $this->data[strtolower(str_replace(' ', '_', $confirmField))] ?? null;
                if ($value !== $confirmValue) {
                    return "{$label} confirmation does not match.";
                }
                break;

            case 'in':
                $allowed = explode(',', $param);
                if ($value !== null && $value !== '' && !in_array($value, $allowed)) {
                    return "{$label} must be one of: {$param}.";
                }
                break;
        }

        return null;
    }
}
