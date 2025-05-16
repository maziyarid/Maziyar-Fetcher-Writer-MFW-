<?php
/**
 * Email Handler Class
 * 
 * Manages email functionality and templates.
 * Handles email sending, queueing, and tracking.
 *
 * @package MFW
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Email_Handler {
    /**
     * Current timestamp
     */
    private $current_time = '2025-05-13 18:43:08';

    /**
     * Current user
     */
    private $current_user = 'maziyarid';

    /**
     * Email settings
     */
    private $settings;

    /**
     * Email queue
     */
    private $queue;

    /**
     * Template loader
     */
    private $template_loader;

    /**
     * Initialize email handler
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option('mfw_email_settings', [
            'from_name' => get_bloginfo('name'),
            'from_email' => get_bloginfo('admin_email'),
            'template_path' => 'templates/email',
            'queue_batch_size' => 50,
            'track_opens' => true,
            'track_clicks' => true
        ]);

        // Initialize components
        $this->init_components();

        // Add hooks
        add_action('mfw_process_email_queue', [$this, 'process_queue']);
        add_action('wp_ajax_mfw_track_email', [$this, 'track_email_open']);
        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
    }

    /**
     * Send email
     *
     * @param string|array $to Recipient email(s)
     * @param string $subject Email subject
     * @param string $template Template name
     * @param array $data Template data
     * @param array $options Additional options
     * @return bool Success status
     */
    public function send($to, $subject, $template, $data = [], $options = []) {
        try {
            // Parse options
            $options = wp_parse_args($options, [
                'from_name' => $this->settings['from_name'],
                'from_email' => $this->settings['from_email'],
                'reply_to' => '',
                'cc' => [],
                'bcc' => [],
                'attachments' => [],
                'queue' => false,
                'priority' => 'normal'
            ]);

            // Prepare recipients
            $to = is_array($to) ? $to : [$to];

            // Prepare email data
            $email_data = [
                'to' => $to,
                'subject' => $subject,
                'template' => $template,
                'data' => $data,
                'options' => $options
            ];

            // Queue email if requested
            if ($options['queue']) {
                return $this->queue_email($email_data);
            }

            // Send email immediately
            return $this->send_email($email_data);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Email send failed: %s', $e->getMessage()),
                'email_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Process email queue
     */
    public function process_queue() {
        try {
            // Get queued emails
            $emails = $this->queue->get_batch($this->settings['queue_batch_size']);

            foreach ($emails as $email) {
                // Send email
                $result = $this->send_email($email['data']);

                // Update queue item
                if ($result) {
                    $this->queue->mark_completed($email['id']);
                } else {
                    $this->queue->increment_attempts($email['id']);
                }
            }

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Queue processing failed: %s', $e->getMessage()),
                'email_handler',
                'error'
            );
        }
    }

    /**
     * Track email open
     */
    public function track_email_open() {
        try {
            // Verify tracking pixel request
            $email_id = isset($_GET['email_id']) ? sanitize_text_field($_GET['email_id']) : '';
            if (!$email_id || !wp_verify_nonce($_GET['_wpnonce'], 'track_email_' . $email_id)) {
                wp_die();
            }

            // Record open
            $this->record_email_open($email_id);

            // Output tracking pixel
            header('Content-Type: image/gif');
            echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            exit;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Email tracking failed: %s', $e->getMessage()),
                'email_handler',
                'error'
            );
            wp_die();
        }
    }

    /**
     * Set HTML content type for emails
     *
     * @return string Content type
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Initialize components
     */
    private function init_components() {
        // Initialize queue handler
        $this->queue = new MFW_Email_Queue();

        // Initialize template loader
        $this->template_loader = new MFW_Template_Loader([
            'path' => $this->settings['template_path']
        ]);
    }

    /**
     * Queue email
     *
     * @param array $email_data Email data
     * @return bool Success status
     */
    private function queue_email($email_data) {
        try {
            return $this->queue->add([
                'data' => $email_data,
                'priority' => $email_data['options']['priority'],
                'created_by' => $this->current_user,
                'created_at' => $this->current_time
            ]);

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Email queueing failed: %s', $e->getMessage()),
                'email_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Send email
     *
     * @param array $email_data Email data
     * @return bool Success status
     */
    private function send_email($email_data) {
        try {
            // Extract data
            $to = $email_data['to'];
            $subject = $email_data['subject'];
            $template = $email_data['template'];
            $data = $email_data['data'];
            $options = $email_data['options'];

            // Generate unique email ID
            $email_id = wp_generate_uuid4();

            // Add tracking data
            if ($this->settings['track_opens']) {
                $data['tracking_pixel'] = $this->get_tracking_pixel($email_id);
            }

            if ($this->settings['track_clicks']) {
                $data = $this->add_click_tracking($data, $email_id);
            }

            // Render template
            $message = $this->template_loader->render($template, $data);

            // Set up headers
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $options['from_name'] . ' <' . $options['from_email'] . '>'
            ];

            if ($options['reply_to']) {
                $headers[] = 'Reply-To: ' . $options['reply_to'];
            }

            if (!empty($options['cc'])) {
                $headers[] = 'Cc: ' . implode(', ', $options['cc']);
            }

            if (!empty($options['bcc'])) {
                $headers[] = 'Bcc: ' . implode(', ', $options['bcc']);
            }

            // Send email
            $sent = wp_mail($to, $subject, $message, $headers, $options['attachments']);

            if ($sent) {
                // Log success
                $this->log_email_sent($email_id, $email_data);
            }

            return $sent;

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Email sending failed: %s', $e->getMessage()),
                'email_handler',
                'error'
            );
            return false;
        }
    }

    /**
     * Get tracking pixel HTML
     *
     * @param string $email_id Email ID
     * @return string Tracking pixel HTML
     */
    private function get_tracking_pixel($email_id) {
        $url = add_query_arg([
            'action' => 'mfw_track_email',
            'email_id' => $email_id,
            '_wpnonce' => wp_create_nonce('track_email_' . $email_id)
        ], admin_url('admin-ajax.php'));

        return '<img src="' . esc_url($url) . '" width="1" height="1" alt="" style="display:none">';
    }

    /**
     * Add click tracking to links
     *
     * @param array $data Template data
     * @param string $email_id Email ID
     * @return array Modified data
     */
    private function add_click_tracking($data, $email_id) {
        // Process links in data recursively
        array_walk_recursive($data, function(&$value) use ($email_id) {
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
                $value = $this->get_tracking_url($value, $email_id);
            }
        });

        return $data;
    }

    /**
     * Get tracking URL
     *
     * @param string $url Original URL
     * @param string $email_id Email ID
     * @return string Tracking URL
     */
    private function get_tracking_url($url, $email_id) {
        return add_query_arg([
            'mfw_track' => base64_encode($email_id . '|' . $url)
        ], home_url());
    }

    /**
     * Record email open
     *
     * @param string $email_id Email ID
     */
    private function record_email_open($email_id) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_email_opens',
                [
                    'email_id' => $email_id,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to record email open: %s', $e->getMessage()),
                'email_handler',
                'error'
            );
        }
    }

    /**
     * Log sent email
     *
     * @param string $email_id Email ID
     * @param array $email_data Email data
     */
    private function log_email_sent($email_id, $email_data) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_email_log',
                [
                    'email_id' => $email_id,
                    'to' => implode(', ', $email_data['to']),
                    'subject' => $email_data['subject'],
                    'template' => $email_data['template'],
                    'data' => json_encode($email_data['data']),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log email: %s', $e->getMessage()),
                'email_handler',
                'error'
            );
        }
    }
}