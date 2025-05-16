<?php
/**
 * Form Helper Class
 * 
 * Provides utility methods for HTML form generation and handling.
 * Handles form elements with proper escaping, validation, and CSRF protection.
 *
 * @package MFW
 * @subpackage Helpers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Form_Helper {
    /**
     * Last operation timestamp
     *
     * @var string
     */
    private static $last_operation = '2025-05-14 07:21:53';

    /**
     * Last operator
     *
     * @var string
     */
    private static $last_operator = 'maziyarid';

    /**
     * Current form ID
     *
     * @var string
     */
    private static $current_form = null;

    /**
     * Form data
     *
     * @var array
     */
    private static $data = [];

    /**
     * Form errors
     *
     * @var array
     */
    private static $errors = [];

    /**
     * Open form
     *
     * @param string $action Form action URL
     * @param array $attributes Form attributes
     * @return string Form opening tag
     */
    public static function open($action = '', $attributes = []) {
        $attributes['method'] = $attributes['method'] ?? 'post';
        $attributes['action'] = esc_url($action);
        
        // Generate unique form ID if not provided
        self::$current_form = $attributes['id'] ?? 'form_' . wp_generate_password(6, false);
        $attributes['id'] = self::$current_form;

        // Add CSRF protection
        $html = MFW_Html_Helper::element('form', $attributes);
        $html .= wp_nonce_field('mfw_form_' . self::$current_form, '_mfw_nonce', true, false);

        return $html;
    }

    /**
     * Close form
     *
     * @return string Form closing tag
     */
    public static function close() {
        self::$current_form = null;
        return '</form>';
    }

    /**
     * Create text input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param array $attributes Input attributes
     * @return string Input element
     */
    public static function text($name, $value = '', $attributes = []) {
        return self::input('text', $name, $value, $attributes);
    }

    /**
     * Create password input
     *
     * @param string $name Input name
     * @param array $attributes Input attributes
     * @return string Input element
     */
    public static function password($name, $attributes = []) {
        return self::input('password', $name, '', $attributes);
    }

    /**
     * Create email input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param array $attributes Input attributes
     * @return string Input element
     */
    public static function email($name, $value = '', $attributes = []) {
        return self::input('email', $name, $value, $attributes);
    }

    /**
     * Create number input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @param array $attributes Input attributes
     * @return string Input element
     */
    public static function number($name, $value = '', $attributes = []) {
        return self::input('number', $name, $value, $attributes);
    }

    /**
     * Create hidden input
     *
     * @param string $name Input name
     * @param string $value Input value
     * @return string Input element
     */
    public static function hidden($name, $value = '') {
        return self::input('hidden', $name, $value);
    }

    /**
     * Create textarea
     *
     * @param string $name Textarea name
     * @param string $value Textarea value
     * @param array $attributes Textarea attributes
     * @return string Textarea element
     */
    public static function textarea($name, $value = '', $attributes = []) {
        $attributes['name'] = $name;
        $attributes['id'] = $attributes['id'] ?? $name;
        
        return MFW_Html_Helper::element('textarea', $attributes, esc_textarea($value));
    }

    /**
     * Create select
     *
     * @param string $name Select name
     * @param array $options Select options
     * @param string|array $selected Selected value(s)
     * @param array $attributes Select attributes
     * @return string Select element
     */
    public static function select($name, $options, $selected = '', $attributes = []) {
        $attributes['name'] = $name;
        $attributes['id'] = $attributes['id'] ?? $name;

        if (!isset($attributes['multiple'])) {
            $selected = (array) $selected;
        } else {
            $attributes['name'] .= '[]';
        }

        $html_options = '';
        foreach ($options as $value => $label) {
            $option_attributes = [
                'value' => $value,
                'selected' => in_array($value, (array) $selected)
            ];

            $html_options .= MFW_Html_Helper::element('option', $option_attributes, esc_html($label));
        }

        return MFW_Html_Helper::element('select', $attributes, $html_options);
    }

    /**
     * Create checkbox
     *
     * @param string $name Checkbox name
     * @param string $value Checkbox value
     * @param bool $checked Whether checkbox is checked
     * @param array $attributes Checkbox attributes
     * @return string Checkbox element
     */
    public static function checkbox($name, $value = '1', $checked = false, $attributes = []) {
        $attributes['type'] = 'checkbox';
        $attributes['name'] = $name;
        $attributes['value'] = $value;
        $attributes['id'] = $attributes['id'] ?? $name;

        if ($checked) {
            $attributes['checked'] = 'checked';
        }

        return MFW_Html_Helper::element('input', $attributes);
    }

    /**
     * Create radio
     *
     * @param string $name Radio name
     * @param string $value Radio value
     * @param bool $checked Whether radio is checked
     * @param array $attributes Radio attributes
     * @return string Radio element
     */
    public static function radio($name, $value, $checked = false, $attributes = []) {
        $attributes['type'] = 'radio';
        $attributes['name'] = $name;
        $attributes['value'] = $value;
        $attributes['id'] = $attributes['id'] ?? $name . '_' . $value;

        if ($checked) {
            $attributes['checked'] = 'checked';
        }

        return MFW_Html_Helper::element('input', $attributes);
    }

    /**
     * Create file input
     *
     * @param string $name Input name
     * @param array $attributes Input attributes
     * @return string Input element
     */
    public static function file($name, $attributes = []) {
        $attributes['type'] = 'file';
        $attributes['name'] = $name;
        $attributes['id'] = $attributes['id'] ?? $name;

        return MFW_Html_Helper::element('input', $attributes);
    }

    /**
     * Create submit button
     *
     * @param string $text Button text
     * @param array $attributes Button attributes
     * @return string Button element
     */
    public static function submit($text, $attributes = []) {
        $attributes['type'] = 'submit';
        return MFW_Html_Helper::button($text, $attributes);
    }

    /**
     * Create input element
     *
     * @param string $type Input type
     * @param string $name Input name
     * @param string $value Input value
     * @param array $attributes Input attributes
     * @return string Input element
     */
    protected static function input($type, $name, $value = '', $attributes = []) {
        $attributes['type'] = $type;
        $attributes['name'] = $name;
        $attributes['value'] = $value;
        $attributes['id'] = $attributes['id'] ?? $name;

        return MFW_Html_Helper::element('input', $attributes);
    }

    /**
     * Set form data
     *
     * @param array $data Form data
     * @return void
     */
    public static function set_data($data) {
        self::$data = $data;
    }

    /**
     * Get form data
     *
     * @param string|null $key Data key
     * @param mixed $default Default value
     * @return mixed Form data
     */
    public static function get_data($key = null, $default = null) {
        if (is_null($key)) {
            return self::$data;
        }

        return self::$data[$key] ?? $default;
    }

    /**
     * Set form errors
     *
     * @param array $errors Form errors
     * @return void
     */
    public static function set_errors($errors) {
        self::$errors = $errors;
    }

    /**
     * Get form errors
     *
     * @param string|null $key Error key
     * @return mixed Form errors
     */
    public static function get_errors($key = null) {
        if (is_null($key)) {
            return self::$errors;
        }

        return self::$errors[$key] ?? [];
    }

    /**
     * Check if form has errors
     *
     * @param string|null $key Error key
     * @return bool Whether form has errors
     */
    public static function has_errors($key = null) {
        if (is_null($key)) {
            return !empty(self::$errors);
        }

        return isset(self::$errors[$key]);
    }

    /**
     * Validate form data
     *
     * @param array $rules Validation rules
     * @return bool Whether validation passed
     */
    public static function validate($rules) {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = self::get_data($field);
            $rules = explode('|', $rule);

            foreach ($rules as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    list($rule, $param_str) = explode(':', $rule);
                    $params = explode(',', $param_str);
                }

                $error = self::validate_field($field, $value, $rule, $params);
                if ($error) {
                    $errors[$field][] = $error;
                }
            }
        }

        self::$errors = $errors;
        return empty($errors);
    }

    /**
     * Validate field value
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $rule Validation rule
     * @param array $params Rule parameters
     * @return string|null Validation error message
     */
    protected static function validate_field($field, $value, $rule, $params = []) {
        switch ($rule) {
            case 'required':
                if (empty($value)) {
                    return sprintf(__('%s is required'), $field);
                }
                break;

            case 'email':
                if (!empty($value) && !is_email($value)) {
                    return sprintf(__('%s must be a valid email'), $field);
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    return sprintf(__('%s must be numeric'), $field);
                }
                break;

            case 'min':
                if (!empty($value) && strlen($value) < $params[0]) {
                    return sprintf(__('%s must be at least %d characters'), $field, $params[0]);
                }
                break;

            case 'max':
                if (!empty($value) && strlen($value) > $params[0]) {
                    return sprintf(__('%s must be no more than %d characters'), $field, $params[0]);
                }
                break;
        }

        return null;
    }
}