<?php
/**
 * Admin Settings Class
 * 
 * Handles the settings interface for the plugin.
 * Manages all plugin configuration options.
 *
 * @package MFW
 * @subpackage Admin
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Admin_Settings {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:54:56';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Settings sections
     */
    private $sections = [];

    /**
     * Settings fields
     */
    private $fields = [];

    /**
     * Initialize settings page
     */
    public function __construct() {
        // Register settings
        add_action('admin_init', [$this, 'register_settings']);

        // Add Ajax handlers
        add_action('wp_ajax_mfw_save_settings', [$this, 'handle_save_settings']);
        add_action('wp_ajax_mfw_reset_settings', [$this, 'handle_reset_settings']);

        // Initialize sections and fields
        $this->init_sections();
        $this->init_fields();
    }

    /**
     * Initialize settings sections
     */
    private function init_sections() {
        $this->sections = [
            'general' => [
                'id' => 'general',
                'title' => __('General Settings', 'mfw'),
                'description' => __('Configure general plugin settings.', 'mfw')
            ],
            'api' => [
                'id' => 'api',
                'title' => __('API Settings', 'mfw'),
                'description' => __('Configure API integration settings.', 'mfw')
            ],
            'email' => [
                'id' => 'email',
                'title' => __('Email Settings', 'mfw'),
                'description' => __('Configure email notification settings.', 'mfw')
            ],
            'security' => [
                'id' => 'security',
                'title' => __('Security Settings', 'mfw'),
                'description' => __('Configure security and access settings.', 'mfw')
            ],
            'performance' => [
                'id' => 'performance',
                'title' => __('Performance Settings', 'mfw'),
                'description' => __('Configure performance optimization settings.', 'mfw')
            ],
            'analytics' => [
                'id' => 'analytics',
                'title' => __('Analytics Settings', 'mfw'),
                'description' => __('Configure analytics and tracking settings.', 'mfw')
            ]
        ];
    }

    /**
     * Initialize settings fields
     */
    private function init_fields() {
        $this->fields = [
            'general' => [
                'enable_feature' => [
                    'id' => 'enable_feature',
                    'title' => __('Enable Feature', 'mfw'),
                    'type' => 'checkbox',
                    'default' => true,
                    'description' => __('Enable this feature globally.', 'mfw')
                ],
                'debug_mode' => [
                    'id' => 'debug_mode',
                    'title' => __('Debug Mode', 'mfw'),
                    'type' => 'checkbox',
                    'default' => false,
                    'description' => __('Enable debug mode for development.', 'mfw')
                ]
            ],
            'api' => [
                'api_key' => [
                    'id' => 'api_key',
                    'title' => __('API Key', 'mfw'),
                    'type' => 'text',
                    'default' => '',
                    'description' => __('Enter your API key.', 'mfw')
                ],
                'api_secret' => [
                    'id' => 'api_secret',
                    'title' => __('API Secret', 'mfw'),
                    'type' => 'password',
                    'default' => '',
                    'description' => __('Enter your API secret.', 'mfw')
                ]
            ],
            'email' => [
                'from_name' => [
                    'id' => 'from_name',
                    'title' => __('From Name', 'mfw'),
                    'type' => 'text',
                    'default' => get_bloginfo('name'),
                    'description' => __('The name displayed in email notifications.', 'mfw')
                ],
                'from_email' => [
                    'id' => 'from_email',
                    'title' => __('From Email', 'mfw'),
                    'type' => 'email',
                    'default' => get_bloginfo('admin_email'),
                    'description' => __('The email address used for notifications.', 'mfw')
                ]
            ]
        ];
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register setting
        register_setting(
            'mfw_settings',
            'mfw_options',
            [
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]
        );

        // Register sections
        foreach ($this->sections as $section) {
            add_settings_section(
                'mfw_' . $section['id'],
                $section['title'],
                [$this, 'render_section_description'],
                'mfw_settings'
            );
        }

        // Register fields
        foreach ($this->fields as $section_id => $fields) {
            foreach ($fields as $field) {
                add_settings_field(
                    'mfw_' . $field['id'],
                    $field['title'],
                    [$this, 'render_field'],
                    'mfw_settings',
                    'mfw_' . $section_id,
                    $field
                );
            }
        }
    }

    /**
     * Render settings page
     */
    public function render() {
        // Get current tab
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        // Start output buffering
        ob_start();
        ?>
        <div class="wrap mfw-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php $this->render_tabs($current_tab); ?>

            <form id="mfw-settings-form" action="options.php" method="post">
                <?php
                settings_fields('mfw_settings');
                do_settings_sections('mfw_settings');
                ?>

                <div class="mfw-settings-actions">
                    <?php submit_button(__('Save Settings', 'mfw'), 'primary', 'submit', false); ?>
                    <button type="button" class="button button-secondary" id="mfw-reset-settings">
                        <?php _e('Reset to Defaults', 'mfw'); ?>
                    </button>
                </div>
            </form>
        </div>

        <?php $this->render_import_export(); ?>

        <?php
        // Get and clean buffer
        $output = ob_get_clean();

        // Add inline scripts
        $this->add_inline_scripts();

        // Output page
        echo $output;
    }

    /**
     * Render settings tabs
     *
     * @param string $current_tab Current active tab
     */
    private function render_tabs($current_tab) {
        ?>
        <h2 class="nav-tab-wrapper">
            <?php
            foreach ($this->sections as $section) {
                printf(
                    '<a href="%s" class="nav-tab %s">%s</a>',
                    esc_url(add_query_arg('tab', $section['id'])),
                    $current_tab === $section['id'] ? 'nav-tab-active' : '',
                    esc_html($section['title'])
                );
            }
            ?>
        </h2>
        <?php
    }

    /**
     * Render section description
     *
     * @param array $section Section information
     */
    public function render_section_description($section) {
        $section_id = str_replace('mfw_', '', $section['id']);
        if (isset($this->sections[$section_id]['description'])) {
            echo '<p>' . esc_html($this->sections[$section_id]['description']) . '</p>';
        }
    }

    /**
     * Render field
     *
     * @param array $field Field information
     */
    public function render_field($field) {
        $options = get_option('mfw_options', []);
        $value = isset($options[$field['id']]) ? $options[$field['id']] : $field['default'];

        switch ($field['type']) {
            case 'checkbox':
                $this->render_checkbox_field($field, $value);
                break;

            case 'text':
            case 'email':
            case 'password':
                $this->render_text_field($field, $value);
                break;

            case 'select':
                $this->render_select_field($field, $value);
                break;

            case 'textarea':
                $this->render_textarea_field($field, $value);
                break;
        }

        if (!empty($field['description'])) {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
    }

    /**
     * Render checkbox field
     *
     * @param array $field Field information
     * @param mixed $value Field value
     */
    private function render_checkbox_field($field, $value) {
        ?>
        <label>
            <input type="checkbox"
                   name="mfw_options[<?php echo esc_attr($field['id']); ?>]"
                   value="1"
                   <?php checked($value, true); ?>>
            <?php echo esc_html($field['label'] ?? ''); ?>
        </label>
        <?php
    }

    /**
     * Render text field
     *
     * @param array $field Field information
     * @param mixed $value Field value
     */
    private function render_text_field($field, $value) {
        ?>
        <input type="<?php echo esc_attr($field['type']); ?>"
               class="regular-text"
               name="mfw_options[<?php echo esc_attr($field['id']); ?>]"
               value="<?php echo esc_attr($value); ?>">
        <?php
    }

    /**
     * Add inline scripts
     */
    private function add_inline_scripts() {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle form submission
                $('#mfw-settings-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var form = $(this);
                    var submitButton = form.find('input[type="submit"]');
                    
                    submitButton.prop('disabled', true);
                    
                    $.post(ajaxurl, {
                        action: 'mfw_save_settings',
                        nonce: mfwAdmin.nonce,
                        data: form.serialize()
                    })
                    .done(function(response) {
                        if (response.success) {
                            // Show success message
                            alert(mfwAdmin.i18n.success);
                        } else {
                            // Show error message
                            alert(response.data.message || mfwAdmin.i18n.error);
                        }
                    })
                    .fail(function() {
                        alert(mfwAdmin.i18n.error);
                    })
                    .always(function() {
                        submitButton.prop('disabled', false);
                    });
                });

                // Handle reset settings
                $('#mfw-reset-settings').on('click', function() {
                    if (confirm(mfwAdmin.i18n.confirmReset)) {
                        $.post(ajaxurl, {
                            action: 'mfw_reset_settings',
                            nonce: mfwAdmin.nonce
                        })
                        .done(function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert(response.data.message || mfwAdmin.i18n.error);
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }
}