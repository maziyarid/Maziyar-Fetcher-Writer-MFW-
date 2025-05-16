<?php
/**
 * Plugin Setup Wizard Class
 *
 * Handles the initial setup wizard for configuring the plugin.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Installer {
    /**
     * Steps for the setup wizard
     *
     * @var array
     */
    private $steps = [];

    /**
     * Current step
     *
     * @var string
     */
    private $current_step = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->steps = [
            'welcome' => [
                'name' => __('Welcome', 'mfw'),
                'view' => [$this, 'welcome_step'],
                'handler' => '',
            ],
            'api_setup' => [
                'name' => __('API Setup', 'mfw'),
                'view' => [$this, 'api_setup_step'],
                'handler' => [$this, 'api_setup_save'],
            ],
            'content_settings' => [
                'name' => __('Content Settings', 'mfw'),
                'view' => [$this, 'content_settings_step'],
                'handler' => [$this, 'content_settings_save'],
            ],
            'seo_settings' => [
                'name' => __('SEO Settings', 'mfw'),
                'view' => [$this, 'seo_settings_step'],
                'handler' => [$this, 'seo_settings_save'],
            ],
            'scheduling' => [
                'name' => __('Scheduling', 'mfw'),
                'view' => [$this, 'scheduling_step'],
                'handler' => [$this, 'scheduling_save'],
            ],
            'complete' => [
                'name' => __('Complete', 'mfw'),
                'view' => [$this, 'complete_step'],
                'handler' => [$this, 'complete_save'],
            ],
        ];

        // Get current step
        $this->current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : current(array_keys($this->steps));

        // Handle form submissions
        add_action('admin_init', [$this, 'handle_form_submission']);
    }

    /**
     * Initialize the wizard
     */
    public function init() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Add menu item
        add_action('admin_menu', [$this, 'add_wizard_menu']);

        // Enqueue necessary scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Add wizard page to admin menu
     */
    public function add_wizard_menu() {
        add_submenu_page(
            null,
            __('MFW Setup Wizard', 'mfw'),
            __('MFW Setup Wizard', 'mfw'),
            'manage_options',
            'mfw-setup',
            [$this, 'wizard_content']
        );
    }

    /**
     * Enqueue necessary scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style('mfw-wizard', MFW_ASSETS_URL . 'css/wizard.css', [], MFW_VERSION);
        wp_enqueue_script('mfw-wizard', MFW_ASSETS_URL . 'js/wizard.js', ['jquery'], MFW_VERSION, true);

        wp_localize_script('mfw-wizard', 'mfwWizard', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mfw_wizard_nonce'),
        ]);
    }

    /**
     * Display wizard content
     */
    public function wizard_content() {
        ?>
        <div class="wrap mfw-wizard-wrap">
            <h1><?php _e('MFW Setup Wizard', 'mfw'); ?></h1>
            
            <?php $this->show_steps(); ?>
            
            <div class="mfw-wizard-content">
                <form method="post" class="mfw-form">
                    <?php
                    wp_nonce_field('mfw_setup_wizard', 'mfw_setup_nonce');
                    $this->show_current_step();
                    ?>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Display wizard steps
     */
    private function show_steps() {
        ?>
        <ul class="mfw-wizard-steps">
            <?php foreach ($this->steps as $key => $step) : ?>
                <li class="<?php echo $this->get_step_class($key); ?>">
                    <?php echo esc_html($step['name']); ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    /**
     * Get CSS class for step
     */
    private function get_step_class($step_key) {
        $classes = [];

        if ($step_key === $this->current_step) {
            $classes[] = 'active';
        } elseif (array_search($this->current_step, array_keys($this->steps)) > array_search($step_key, array_keys($this->steps))) {
            $classes[] = 'done';
        }

        return implode(' ', $classes);
    }

    /**
     * Display current step content
     */
    private function show_current_step() {
        if (!isset($this->steps[$this->current_step])) {
            return;
        }

        call_user_func($this->steps[$this->current_step]['view']);
    }

    /**
     * Handle form submissions
     */
    public function handle_form_submission() {
        if (!isset($_POST['mfw_setup_nonce']) || !wp_verify_nonce($_POST['mfw_setup_nonce'], 'mfw_setup_wizard')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle the submission
        if (isset($this->steps[$this->current_step]['handler'])) {
            call_user_func($this->steps[$this->current_step]['handler']);
        }

        // Get the next step
        $steps = array_keys($this->steps);
        $current_step_index = array_search($this->current_step, $steps);
        $next_step = isset($steps[$current_step_index + 1]) ? $steps[$current_step_index + 1] : '';

        if ($next_step) {
            wp_redirect(add_query_arg('step', $next_step));
            exit;
        }
    }

    /**
     * Welcome step view
     */
    public function welcome_step() {
        ?>
        <h2><?php _e('Welcome to Maziyar Fetcher Writer', 'mfw'); ?></h2>
        <p><?php _e('This wizard will help you configure the essential settings for your content fetching and AI writing system.', 'mfw'); ?></p>
        
        <p><strong><?php _e('During this setup, we will:', 'mfw'); ?></strong></p>
        <ul>
            <li><?php _e('Configure your AI API keys (Gemini & DeepSeek)', 'mfw'); ?></li>
            <li><?php _e('Set up your content preferences', 'mfw'); ?></li>
            <li><?php _e('Configure SEO integration', 'mfw'); ?></li>
            <li><?php _e('Set up content fetching schedules', 'mfw'); ?></li>
        </ul>

        <p class="mfw-wizard-actions">
            <button type="submit" class="button button-primary">
                <?php _e('Let\'s Get Started', 'mfw'); ?> &rarr;
            </button>
        </p>
        <?php
    }

    /**
     * API Setup step view
     */
    public function api_setup_step() {
        $settings = get_option(MFW_SETTINGS_OPTION, []);
        ?>
        <h2><?php _e('API Configuration', 'mfw'); ?></h2>
        <p><?php _e('Configure your AI service API keys. At least one service must be configured.', 'mfw'); ?></p>

        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="gemini_api_key"><?php _e('Gemini API Key', 'mfw'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="gemini_api_key" 
                           name="mfw_settings[gemini_api_key]" 
                           value="<?php echo esc_attr($settings['gemini_api_key'] ?? ''); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Enter your Gemini API key. Get it from Google AI Studio.', 'mfw'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="deepseek_api_key"><?php _e('DeepSeek API Key', 'mfw'); ?></label>
                </th>
                <td>
                    <input type="password" 
                           id="deepseek_api_key" 
                           name="mfw_settings[deepseek_api_key]" 
                           value="<?php echo esc_attr($settings['deepseek_api_key'] ?? ''); ?>" 
                           class="regular-text">
                    <p class="description">
                        <?php _e('Enter your DeepSeek API key. Optional but recommended for backup.', 'mfw'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <p class="mfw-wizard-actions">
            <button type="submit" class="button button-primary">
                <?php _e('Continue', 'mfw'); ?> &rarr;
            </button>
        </p>
        <?php
    }

    /**
     * Handle API setup save
     */
    public function api_setup_save() {
        if (isset($_POST['mfw_settings'])) {
            $settings = get_option(MFW_SETTINGS_OPTION, []);
            $new_settings = wp_parse_args(
                wp_unslash($_POST['mfw_settings']),
                $settings
            );
            
            // Validate API keys
            if (empty($new_settings['gemini_api_key']) && empty($new_settings['deepseek_api_key'])) {
                add_settings_error(
                    'mfw_messages',
                    'mfw_error',
                    __('At least one API key must be provided.', 'mfw'),
                    'error'
                );
                return;
            }

            update_option(MFW_SETTINGS_OPTION, $new_settings);
        }
    }

    // ... [Similar implementation for other steps] ...

    /**
     * Complete step view
     */
    public function complete_step() {
        // Remove the setup wizard flag
        delete_option('mfw_show_wizard');
        
        ?>
        <h2><?php _e('Setup Complete!', 'mfw'); ?></h2>
        <p><?php _e('Congratulations! Maziyar Fetcher Writer has been configured successfully.', 'mfw'); ?></p>
        
        <ul class="mfw-wizard-next-steps">
            <li>
                <a href="<?php echo admin_url('admin.php?page=mfw-sources'); ?>" class="button button-primary">
                    <?php _e('Configure Content Sources', 'mfw'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=mfw-settings'); ?>" class="button">
                    <?php _e('Review Settings', 'mfw'); ?>
                </a>
            </li>
        </ul>
        <?php
    }
}