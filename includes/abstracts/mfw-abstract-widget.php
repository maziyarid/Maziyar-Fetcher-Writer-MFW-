<?php
/**
 * Widget Abstract Class
 * 
 * Provides base functionality for all widgets.
 * Handles widget registration, rendering, and configuration.
 *
 * @package MFW
 * @subpackage Abstracts
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Abstract_Widget extends WP_Widget {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:23:32';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Widget configuration
     */
    protected $config = [
        'id_base' => '',
        'name' => '',
        'description' => '',
        'classname' => '',
        'customize_selective_refresh' => true
    ];

    /**
     * Default instance settings
     */
    protected $defaults = [];

    /**
     * Form fields configuration
     */
    protected $fields = [];

    /**
     * Initialize widget
     */
    public function __construct() {
        // Set widget configuration
        $this->setup();

        // Register widget
        parent::__construct(
            $this->config['id_base'],
            $this->config['name'],
            [
                'description' => $this->config['description'],
                'classname' => $this->config['classname'],
                'customize_selective_refresh' => $this->config['customize_selective_refresh']
            ]
        );

        // Add widget actions and filters
        $this->add_hooks();
    }

    /**
     * Setup widget configuration
     * Must be implemented by child classes
     */
    abstract protected function setup();

    /**
     * Widget frontend display
     * Must be implemented by child classes
     *
     * @param array $args Display arguments
     * @param array $instance Settings for the current widget instance
     */
    abstract public function widget($args, $instance);

    /**
     * Add widget specific hooks
     */
    protected function add_hooks() {
        // Add widget scripts
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_scripts']);

        // Add widget styles
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_styles']);

        // Add AJAX handlers
        add_action('wp_ajax_mfw_widget_' . $this->config['id_base'], [$this, 'ajax_handler']);
        add_action('wp_ajax_nopriv_mfw_widget_' . $this->config['id_base'], [$this, 'ajax_handler']);
    }

    /**
     * Widget backend form
     *
     * @param array $instance Current settings
     * @return string Form HTML
     */
    public function form($instance) {
        $instance = wp_parse_args($instance, $this->defaults);
        $output = '';

        foreach ($this->fields as $key => $field) {
            $field = wp_parse_args($field, [
                'type' => 'text',
                'label' => '',
                'description' => '',
                'class' => '',
                'default' => '',
                'options' => []
            ]);

            $value = isset($instance[$key]) ? $instance[$key] : $field['default'];
            $id = $this->get_field_id($key);
            $name = $this->get_field_name($key);

            $output .= '<p>';
            $output .= '<label for="' . esc_attr($id) . '">' . esc_html($field['label']) . '</label>';

            switch ($field['type']) {
                case 'text':
                case 'number':
                case 'url':
                case 'email':
                    $output .= '<input type="' . esc_attr($field['type']) . '" ';
                    $output .= 'class="widefat ' . esc_attr($field['class']) . '" ';
                    $output .= 'id="' . esc_attr($id) . '" ';
                    $output .= 'name="' . esc_attr($name) . '" ';
                    $output .= 'value="' . esc_attr($value) . '">';
                    break;

                case 'textarea':
                    $output .= '<textarea class="widefat ' . esc_attr($field['class']) . '" ';
                    $output .= 'id="' . esc_attr($id) . '" ';
                    $output .= 'name="' . esc_attr($name) . '" ';
                    $output .= 'rows="5">' . esc_textarea($value) . '</textarea>';
                    break;

                case 'select':
                    $output .= '<select class="widefat ' . esc_attr($field['class']) . '" ';
                    $output .= 'id="' . esc_attr($id) . '" ';
                    $output .= 'name="' . esc_attr($name) . '">';
                    foreach ($field['options'] as $option_value => $option_label) {
                        $output .= '<option value="' . esc_attr($option_value) . '" ';
                        $output .= selected($value, $option_value, false) . '>';
                        $output .= esc_html($option_label) . '</option>';
                    }
                    $output .= '</select>';
                    break;

                case 'checkbox':
                    $output .= '<input type="checkbox" ';
                    $output .= 'class="' . esc_attr($field['class']) . '" ';
                    $output .= 'id="' . esc_attr($id) . '" ';
                    $output .= 'name="' . esc_attr($name) . '" ';
                    $output .= 'value="1" ';
                    $output .= checked($value, 1, false) . '>';
                    break;

                case 'radio':
                    foreach ($field['options'] as $option_value => $option_label) {
                        $output .= '<label>';
                        $output .= '<input type="radio" ';
                        $output .= 'class="' . esc_attr($field['class']) . '" ';
                        $output .= 'name="' . esc_attr($name) . '" ';
                        $output .= 'value="' . esc_attr($option_value) . '" ';
                        $output .= checked($value, $option_value, false) . '> ';
                        $output .= esc_html($option_label) . '</label><br>';
                    }
                    break;
            }

            if ($field['description']) {
                $output .= '<small class="description">' . esc_html($field['description']) . '</small>';
            }

            $output .= '</p>';
        }

        echo $output;
    }

    /**
     * Widget settings update
     *
     * @param array $new_instance New settings
     * @param array $old_instance Old settings
     * @return array Updated settings
     */
    public function update($new_instance, $old_instance) {
        $instance = $old_instance;

        foreach ($this->fields as $key => $field) {
            if (isset($new_instance[$key])) {
                switch ($field['type']) {
                    case 'text':
                        $instance[$key] = sanitize_text_field($new_instance[$key]);
                        break;

                    case 'number':
                        $instance[$key] = absint($new_instance[$key]);
                        break;

                    case 'url':
                        $instance[$key] = esc_url_raw($new_instance[$key]);
                        break;

                    case 'email':
                        $instance[$key] = sanitize_email($new_instance[$key]);
                        break;

                    case 'textarea':
                        $instance[$key] = wp_kses_post($new_instance[$key]);
                        break;

                    case 'checkbox':
                        $instance[$key] = !empty($new_instance[$key]) ? 1 : 0;
                        break;

                    default:
                        $instance[$key] = $new_instance[$key];
                }
            } else {
                $instance[$key] = $field['default'];
            }
        }

        // Log widget update
        $this->log_widget_update($instance);

        return $instance;
    }

    /**
     * Enqueue admin scripts
     */
    public function admin_scripts() {
        // Override in child class if needed
    }

    /**
     * Enqueue frontend scripts
     */
    public function frontend_scripts() {
        // Override in child class if needed
    }

    /**
     * Enqueue admin styles
     */
    public function admin_styles() {
        // Override in child class if needed
    }

    /**
     * Enqueue frontend styles
     */
    public function frontend_styles() {
        // Override in child class if needed
    }

    /**
     * Handle AJAX requests
     */
    public function ajax_handler() {
        // Override in child class if needed
        wp_die();
    }

    /**
     * Log widget update
     *
     * @param array $instance Widget settings
     */
    protected function log_widget_update($instance) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_widget_log',
                [
                    'widget_id' => $this->id,
                    'widget_type' => $this->config['id_base'],
                    'settings' => json_encode($instance),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log widget update: %s', $e->getMessage()),
                get_class($this),
                'error'
            );
        }
    }
}