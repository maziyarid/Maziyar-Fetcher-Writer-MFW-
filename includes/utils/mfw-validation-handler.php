<?php
/**
 * Validation Handler Class
 * 
 * Manages data validation and sanitization.
 * Provides comprehensive validation rules and custom validation methods.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Validation_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:28:48';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Custom validation rules
     */
    private $custom_rules = [];

    /**
     * Error messages
     */
    private $messages = [];

    /**
     * Initialize validation handler
     */
    public function __construct() {
        // Set default error messages
        $this->messages = [
            'required' => __('This field is required.', 'mfw'),
            'email' => __('Please enter a valid email address.', 'mfw'),
            'url' => __('Please enter a valid URL.', 'mfw'),
            'numeric' => __('Please enter a valid number.', 'mfw'),
            'integer' => __('Please enter a valid integer.', 'mfw'),
            'float' => __('Please enter a valid decimal number.', 'mfw'),
            'min' => __('Please enter a value greater than or equal to %s.', 'mfw'),
            'max' => __('Please enter a value less than or equal to %s.', 'mfw'),
            'between' => __('Please enter a value between %s and %s.', 'mfw'),
            'length' => __('Please enter exactly %s characters.', 'mfw'),
            'min_length' => __('Please enter at least %s characters.', 'mfw'),
            'max_length' => __('Please enter no more than %s characters.', 'mfw'),
            'matches' => __('This field must match %s.', 'mfw'),
            'alpha' => __('Please enter only letters.', 'mfw'),
            'alpha_numeric' => __('Please enter only letters and numbers.', 'mfw'),
            'alpha_dash' => __('Please enter only letters, numbers, dashes and underscores.', 'mfw'),
            'regex' => __('Please enter a valid value.', 'mfw'),
            'date' => __('Please enter a valid date.', 'mfw'),
            'date_format' => __('Please enter a date in the format %s.', 'mfw'),
            'boolean' => __('Please enter a boolean value.', 'mfw'),
            'in' => __('Please select a valid option.', 'mfw'),
            'not_in' => __('Please select a different option.', 'mfw'),
            'unique' => __('This value already exists.', 'mfw'),
            'exists' => __('This value does not exist.', 'mfw'),
            'ip' => __('Please enter a valid IP address.', 'mfw'),
            'credit_card' => __('Please enter a valid credit card number.', 'mfw'),
            'phone' => __('Please enter a valid phone number.', 'mfw')
        ];
    }

    /**
     * Validate data against rules
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param array $messages Custom error messages
     * @return bool|array True if valid, array of errors if invalid
     */
    public function validate($data, $rules, $messages = []) {
        $errors = [];

        foreach ($rules as $field => $field_rules) {
            // Skip if field doesn't exist and isn't required
            if (!isset($data[$field]) && !$this->has_rule($field_rules, 'required')) {
                continue;
            }

            $value = $data[$field] ?? null;
            $rules_array = $this->parse_rules($field_rules);

            foreach ($rules_array as $rule) {
                $rule_name = $rule['rule'];
                $rule_params = $rule['parameters'];

                // Check if value is valid
                if (!$this->validate_field($value, $rule_name, $rule_params)) {
                    $errors[$field] = $this->get_error_message(
                        $field,
                        $rule_name,
                        $rule_params,
                        $messages
                    );
                    break;
                }
            }
        }

        return empty($errors) ? true : $errors;
    }

    /**
     * Add custom validation rule
     *
     * @param string $name Rule name
     * @param callable $callback Validation callback
     * @param string $message Error message
     * @return void
     */
    public function add_rule($name, $callback, $message) {
        $this->custom_rules[$name] = [
            'callback' => $callback,
            'message' => $message
        ];
    }

    /**
     * Sanitize data based on rules
     *
     * @param array $data Data to sanitize
     * @param array $rules Sanitization rules
     * @return array Sanitized data
     */
    public function sanitize($data, $rules) {
        $sanitized = [];

        foreach ($data as $field => $value) {
            if (!isset($rules[$field])) {
                $sanitized[$field] = $value;
                continue;
            }

            $sanitized[$field] = $this->sanitize_field($value, $rules[$field]);
        }

        return $sanitized;
    }

    /**
     * Validate single field
     *
     * @param mixed $value Field value
     * @param string $rule Rule name
     * @param array $parameters Rule parameters
     * @return bool Validation result
     */
    private function validate_field($value, $rule, $parameters) {
        // Check custom rules first
        if (isset($this->custom_rules[$rule])) {
            return call_user_func($this->custom_rules[$rule]['callback'], $value, $parameters);
        }

        switch ($rule) {
            case 'required':
                return !empty($value);

            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'numeric':
                return is_numeric($value);

            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;

            case 'float':
                return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;

            case 'min':
                return is_numeric($value) && $value >= $parameters[0];

            case 'max':
                return is_numeric($value) && $value <= $parameters[0];

            case 'between':
                return is_numeric($value) && $value >= $parameters[0] && $value <= $parameters[1];

            case 'length':
                return strlen($value) == $parameters[0];

            case 'min_length':
                return strlen($value) >= $parameters[0];

            case 'max_length':
                return strlen($value) <= $parameters[0];

            case 'matches':
                return $value === $parameters[0];

            case 'alpha':
                return ctype_alpha($value);

            case 'alpha_numeric':
                return ctype_alnum($value);

            case 'alpha_dash':
                return preg_match('/^[\w-]*$/', $value) === 1;

            case 'regex':
                return preg_match($parameters[0], $value) === 1;

            case 'date':
                return strtotime($value) !== false;

            case 'date_format':
                $date = DateTime::createFromFormat($parameters[0], $value);
                return $date && $date->format($parameters[0]) === $value;

            case 'boolean':
                return is_bool($value) || in_array($value, [0, 1, '0', '1', true, false], true);

            case 'in':
                return in_array($value, $parameters, true);

            case 'not_in':
                return !in_array($value, $parameters, true);

            case 'ip':
                return filter_var($value, FILTER_VALIDATE_IP) !== false;

            case 'credit_card':
                return $this->validate_credit_card($value);

            case 'phone':
                return $this->validate_phone($value);

            default:
                return false;
        }
    }

    /**
     * Sanitize single field
     *
     * @param mixed $value Field value
     * @param string|array $rules Sanitization rules
     * @return mixed Sanitized value
     */
    private function sanitize_field($value, $rules) {
        $rules = (array)$rules;

        foreach ($rules as $rule) {
            switch ($rule) {
                case 'string':
                    $value = sanitize_text_field($value);
                    break;

                case 'email':
                    $value = sanitize_email($value);
                    break;

                case 'url':
                    $value = sanitize_url($value);
                    break;

                case 'int':
                    $value = intval($value);
                    break;

                case 'float':
                    $value = floatval($value);
                    break;

                case 'boolean':
                    $value = (bool)$value;
                    break;

                case 'html':
                    $value = wp_kses_post($value);
                    break;

                case 'strip_tags':
                    $value = strip_tags($value);
                    break;

                case 'trim':
                    $value = trim($value);
                    break;
            }
        }

        return $value;
    }

    /**
     * Parse validation rules
     *
     * @param string|array $rules Rules to parse
     * @return array Parsed rules
     */
    private function parse_rules($rules) {
        if (is_array($rules)) {
            return array_map(function($rule) {
                return ['rule' => $rule, 'parameters' => []];
            }, $rules);
        }

        $parsed = [];
        $rules_array = explode('|', $rules);

        foreach ($rules_array as $rule) {
            if (strpos($rule, ':') === false) {
                $parsed[] = ['rule' => $rule, 'parameters' => []];
                continue;
            }

            list($name, $params) = explode(':', $rule, 2);
            $parsed[] = [
                'rule' => $name,
                'parameters' => explode(',', $params)
            ];
        }

        return $parsed;
    }

    /**
     * Get error message
     *
     * @param string $field Field name
     * @param string $rule Rule name
     * @param array $parameters Rule parameters
     * @param array $custom_messages Custom messages
     * @return string Error message
     */
    private function get_error_message($field, $rule, $parameters, $custom_messages) {
        // Check for custom field-specific message
        $field_key = $field . '.' . $rule;
        if (isset($custom_messages[$field_key])) {
            return $this->format_message($custom_messages[$field_key], $parameters);
        }

        // Check for custom rule message
        if (isset($custom_messages[$rule])) {
            return $this->format_message($custom_messages[$rule], $parameters);
        }

        // Check for custom rule from add_rule()
        if (isset($this->custom_rules[$rule])) {
            return $this->format_message($this->custom_rules[$rule]['message'], $parameters);
        }

        // Use default message
        return $this->format_message($this->messages[$rule] ?? __('Invalid value.', 'mfw'), $parameters);
    }

    /**
     * Format error message
     *
     * @param string $message Message template
     * @param array $parameters Message parameters
     * @return string Formatted message
     */
    private function format_message($message, $parameters) {
        if (empty($parameters)) {
            return $message;
        }

        return vsprintf($message, $parameters);
    }

    /**
     * Check if rules contain specific rule
     *
     * @param string|array $rules Rules to check
     * @param string $rule Rule to find
     * @return bool Whether rule exists
     */
    private function has_rule($rules, $rule) {
        if (is_array($rules)) {
            return in_array($rule, $rules);
        }

        return strpos($rules . '|', $rule . '|') !== false;
    }

    /**
     * Validate credit card number
     *
     * @param string $number Credit card number
     * @return bool Validation result
     */
    private function validate_credit_card($number) {
        // Remove non-digits
        $number = preg_replace('/[^0-9]/', '', $number);

        // Check length
        if (strlen($number) < 13 || strlen($number) > 19) {
            return false;
        }

        // Luhn algorithm
        $sum = 0;
        $length = strlen($number);
        for ($i = 0; $i < $length; $i++) {
            $digit = (int)$number[$length - 1 - $i];
            if ($i % 2 == 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 == 0;
    }

    /**
     * Validate phone number
     *
     * @param string $number Phone number
     * @return bool Validation result
     */
    private function validate_phone($number) {
        // Basic phone validation (can be customized based on requirements)
        return preg_match('/^[+]?[0-9]{10,14}$/', $number) === 1;
    }
}