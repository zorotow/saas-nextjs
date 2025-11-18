<?php
/**
 * Simple Validation Class
 * Similar to Zod but for PHP
 */

class Validator {
    private $data;
    private $errors = [];
    private $rules = [];

    public function __construct($data) {
        $this->data = $data;
    }

    /**
     * Create new validator
     */
    public static function make($data) {
        return new self($data);
    }

    /**
     * Add validation rule
     */
    public function rule($field, $rules) {
        $this->rules[$field] = $rules;
        return $this;
    }

    /**
     * Run validation
     */
    public function validate() {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule => $param) {
                if (is_int($rule)) {
                    $rule = $param;
                    $param = true;
                }

                $methodName = 'validate' . ucfirst($rule);
                if (method_exists($this, $methodName)) {
                    $error = $this->$methodName($field, $value, $param);
                    if ($error) {
                        $this->errors[$field] = $error;
                        break;
                    }
                }
            }
        }

        return empty($this->errors);
    }

    /**
     * Get errors
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * Get first error
     */
    public function firstError() {
        return reset($this->errors) ?: null;
    }

    /**
     * Validation rules
     */
    private function validateRequired($field, $value, $param) {
        if ($value === null || $value === '') {
            return ucfirst($field) . ' is required';
        }
        return null;
    }

    private function validateEmail($field, $value, $param) {
        if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address';
        }
        return null;
    }

    private function validateMin($field, $value, $length) {
        if ($value && strlen($value) < $length) {
            return ucfirst($field) . " must be at least {$length} characters";
        }
        return null;
    }

    private function validateMax($field, $value, $length) {
        if ($value && strlen($value) > $length) {
            return ucfirst($field) . " must not exceed {$length} characters";
        }
        return null;
    }

    private function validateNumeric($field, $value, $param) {
        if ($value && !is_numeric($value)) {
            return ucfirst($field) . ' must be a number';
        }
        return null;
    }

    private function validateIn($field, $value, $options) {
        if ($value && !in_array($value, $options)) {
            return ucfirst($field) . ' must be one of: ' . implode(', ', $options);
        }
        return null;
    }

    private function validateMatch($field, $value, $otherField) {
        $otherValue = $this->data[$otherField] ?? null;
        if ($value !== $otherValue) {
            return ucfirst($field) . ' does not match ' . $otherField;
        }
        return null;
    }

    private function validateUnique($field, $value, $table) {
        if (!$value) return null;

        $db = Database::getInstance();
        $existing = $db->fetch(
            "SELECT id FROM {$table} WHERE {$field} = ? LIMIT 1",
            [$value]
        );

        if ($existing) {
            return ucfirst($field) . ' is already taken';
        }
        return null;
    }
}
