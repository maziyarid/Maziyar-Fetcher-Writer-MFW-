<?php
/**
 * Metabox Handler Class
 * 
 * Manages custom metaboxes for post types, including creation,
 * rendering, and data saving.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Metabox_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 17:53:27';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Registered metaboxes
     */
    private $metaboxes = [];

    /**
     * Field types and their renderers
     */
    private $field_types = [
        'text' => 'render_text_field',
        'textarea' => 'render_textarea_field',
        'select' => 'render_select_field',
        'radio' => 'render_radio_field',
        'checkbox' => 'render_checkbox_field',
        'media' => 'render_media_field',
        'color' => 'render_color_field',
        'date' => 'render_date_field',
        'repeater' => 'render_repeater_field'
    ];

    /**
     * Initialize metabox handler
     */
    public function __construct() {
        // Add metabox hooks
        add_action('add_meta_boxes', [$this, 'add_metaboxes']);
        add_action('save_post', [$this, 'save_metabox_data'], 10, 2);
        
        // Add validation filters
        add_filter('mfw_validate_text_field', [$this, 'validate_text'], 10, 2);
        add_filter('mfw_validate_textarea_field', [$this, 'validate_textarea'], 10, 2);
        add_filter('mfw_validate_select_field', [$this, 'validate_select'], 10, 2);
        add_filter('mfw_validate_radio_field', [$this, 'validate_radio'], 10, 2);
        add_filter('mfw_validate_checkbox_field', [$this, 'validate_checkbox'], 10, 2);
        add_filter('mfw_validate_media_field', [$this, 'validate_media'], 10, 2);
        add_filter('mfw_validate_color_field', [$this, 'validate_color'], 10, 2);
        add_filter('mfw_validate_date_field', [$this, 'validate_date'], 10, 2);
    }

    /**
     * Register metabox
     *
     * @param array $args Metabox arguments
     * @return bool Success status
     */
    public function register_metabox($args) {
        try {
            // Required arguments
            $required = ['id', 'title', 'post_types', 'fields'];
            foreach ($required as $arg) {
                if (!isset($args[$arg])) {
                    throw new Exception(sprintf(
                        __('Missing required argument: %s', 'mfw'),
                        $arg
                    ));
                }
            }

            // Parse arguments
            $args = wp_parse_args($args, [
                'context' => 'advanced',
                'priority' => 'default',
                'callback' => null,
                'validation_rules' => []
            ]);

            // Validate fields
            foreach ($args['fields'] as $field) {
                if (!isset($field['type']) || !isset($this->field_types[$field['type']])) {
                    throw new Exception(sprintf(
                        __('Invalid field type: %s', 'mfw'),
                        $field['type'] ?? 'undefined'
                    ));
                }
            }

            $this->metaboxes[$args['id']] = $args;
            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to register metabox: %s', $e->getMessage()),
                'metabox_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Add registered metaboxes
     */
    public function add_metaboxes() {
        foreach ($this->metaboxes as $id => $metabox) {
            foreach ($metabox['post_types'] as $post_type) {
                add_meta_box(
                    $id,
                    $metabox['title'],
                    $metabox['callback'] ?? [$this, 'render_metabox'],
                    $post_type,
                    $metabox['context'],
                    $metabox['priority'],
                    ['metabox' => $metabox]
                );
            }
        }
    }

    /**
     * Render metabox
     *
     * @param WP_Post $post Post object
     * @param array $args Metabox arguments
     */
    public function render_metabox($post, $args) {
        try {
            $metabox = $args['args']['metabox'];
            
            // Add nonce for security
            wp_nonce_field("mfw_metabox_{$metabox['id']}", "mfw_metabox_{$metabox['id']}_nonce");

            echo '<div class="mfw-metabox">';
            
            foreach ($metabox['fields'] as $field) {
                $this->render_field($field, $post);
            }

            echo '</div>';

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to render metabox: %s', $e->getMessage()),
                'metabox_handler',
                'error'
            );
        }
    }

    /**
     * Save metabox data
     *
     * @param int $post_id Post ID
     * @param WP_Post $post Post object
     */
    public function save_metabox_data($post_id, $post) {
        try {
            // Check if this is an autosave
            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            // Check user permissions
            if (!current_user_can('edit_post', $post_id)) {
                return;
            }

            foreach ($this->metaboxes as $id => $metabox) {
                // Verify nonce
                if (!isset($_POST["mfw_metabox_{$id}_nonce"]) ||
                    !wp_verify_nonce($_POST["mfw_metabox_{$id}_nonce"], "mfw_metabox_{$id}")) {
                    continue;
                }

                // Check if this post type should have this metabox
                if (!in_array($post->post_type, $metabox['post_types'])) {
                    continue;
                }

                // Save fields
                foreach ($metabox['fields'] as $field) {
                    $this->save_field($field, $post_id);
                }
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to save metabox data: %s', $e->getMessage()),
                'metabox_handler',
                'error'
            );
        }
    }

    /**
     * Render field
     *
     * @param array $field Field configuration
     * @param WP_Post $post Post object
     */
    private function render_field($field, $post) {
        try {
            // Get field value
            $value = get_post_meta($post->ID, $field['id'], true);
            if ($value === '' && isset($field['default'])) {
                $value = $field['default'];
            }

            // Render field wrapper
            echo '<div class="mfw-field-wrapper">';
            
            // Render label if set
            if (!empty($field['label'])) {
                echo '<label for="' . esc_attr($field['id']) . '">' . 
                     esc_html($field['label']) . '</label>';
            }

            // Render field
            $method = $this->field_types[$field['type']];
            $this->$method($field, $value);

            // Render description if set
            if (!empty($field['description'])) {
                echo '<p class="description">' . esc_html($field['description']) . '</p>';
            }

            echo '</div>';

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to render field: %s', $e->getMessage()),
                'metabox_handler',
                'error'
            );
        }
    }

    /**
     * Save field value
     *
     * @param array $field Field configuration
     * @param int $post_id Post ID
     */
    private function save_field($field, $post_id) {
        try {
            // Get field value from POST
            $value = isset($_POST[$field['id']]) ? $_POST[$field['id']] : '';

            // Validate value
            $value = apply_filters("mfw_validate_{$field['type']}_field", $value, $field);

            // Save value
            if ($value !== false) {
                update_post_meta($post_id, $field['id'], $value);
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to save field: %s', $e->getMessage()),
                'metabox_handler',
                'error'
            );
        }
    }

    /**
     * Field rendering methods
     */
    private function render_text_field($field, $value) {
        printf(
            '<input type="text" id="%s" name="%s" value="%s" class="regular-text"%s>',
            esc_attr($field['id']),
            esc_attr($field['id']),
            esc_attr($value),
            !empty($field['placeholder']) ? ' placeholder="' . esc_attr($field['placeholder']) . '"' : ''
        );
    }

    private function render_textarea_field($field, $value) {
        printf(
            '<textarea id="%s" name="%s" rows="%d" class="large-text"%s>%s</textarea>',
            esc_attr($field['id']),
            esc_attr($field['id']),
            isset($field['rows']) ? intval($field['rows']) : 5,
            !empty($field['placeholder']) ? ' placeholder="' . esc_attr($field['placeholder']) . '"' : '',
            esc_textarea($value)
        );
    }

    private function render_select_field($field, $value) {
        echo '<select id="' . esc_attr($field['id']) . '" name="' . esc_attr($field['id']) . '">';
        foreach ($field['options'] as $key => $label) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($key),
                selected($value, $key, false),
                esc_html($label)
            );
        }
        echo '</select>';
    }

    /**
     * Validation methods
     */
    public function validate_text($value, $field) {
        if (isset($field['required']) && $field['required'] && empty($value)) {
            return false;
        }
        return sanitize_text_field($value);
    }

    public function validate_textarea($value, $field) {
        if (isset($field['required']) && $field['required'] && empty($value)) {
            return false;
        }
        return sanitize_textarea_field($value);
    }

    public function validate_select($value, $field) {
        if (!isset($field['options'][$value])) {
            return false;
        }
        return $value;
    }
}