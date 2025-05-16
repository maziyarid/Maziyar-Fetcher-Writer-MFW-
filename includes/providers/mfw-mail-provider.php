<?php
/**
 * Mail Service Provider
 * 
 * Registers mail services and handlers.
 * Handles email sending, templates, and queues.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Mail_Provider extends MFW_Service_Provider {
    /**
     * Provider initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:52:46';

    /**
     * Provider initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * Register services
     *
     * @return void
     */
    public function register() {
        // Register mail manager
        $this->singleton('mail.manager', function($app) {
            return new MFW_Mail_Manager($app);
        });

        // Register mail queue
        $this->singleton('mail.queue', function($app) {
            return new MFW_Mail_Queue(
                $app['db'],
                $app['config']->get('mail.queue', [])
            );
        });

        // Register template manager
        $this->singleton('mail.templates', function($app) {
            return new MFW_Mail_Template_Manager(
                $app['view'],
                $app['config']->get('mail.templates', [])
            );
        });

        // Register SMTP mailer
        $this->singleton('mail.smtp', function($app) {
            return new MFW_SMTP_Mailer(
                $app['config']->get('mail.smtp', [])
            );
        });

        // Register mailgun mailer
        $this->singleton('mail.mailgun', function($app) {
            return new MFW_Mailgun_Mailer(
                $app['config']->get('mail.mailgun', [])
            );
        });

        // Register sendgrid mailer
        $this->singleton('mail.sendgrid', function($app) {
            return new MFW_Sendgrid_Mailer(
                $app['config']->get('mail.sendgrid', [])
            );
        });

        // Register mail logger
        $this->singleton('mail.logger', function($app) {
            return new MFW_Mail_Logger(
                $app['log'],
                $app['config']->get('mail.logging', true)
            );
        });
    }

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {
        // Create mail queue table if it doesn't exist
        if (!$this->queue_table_exists()) {
            $this->create_queue_table();
        }

        // Register mail templates
        $this->register_templates();

        // Register WordPress hooks
        $this->register_hooks();

        // Register mail events
        $this->register_events();

        // Register admin interface
        $this->register_admin_interface();
    }

    /**
     * Check if queue table exists
     *
     * @return bool Whether table exists
     */
    protected function queue_table_exists() {
        global $wpdb;
        $table = $this->container['config']->get('mail.queue.table', 'mfw_mail_queue');
        return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    }

    /**
     * Create queue table
     *
     * @return void
     */
    protected function create_queue_table() {
        $schema = $this->container['db.schema'];
        $table = $this->container['config']->get('mail.queue.table', 'mfw_mail_queue');

        $schema->create($table, function($table) {
            $table->increments('id');
            $table->string('to');
            $table->string('subject');
            $table->text('body');
            $table->text('headers')->nullable();
            $table->text('attachments')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('send_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Register mail templates
     *
     * @return void
     */
    protected function register_templates() {
        $templates = $this->container['mail.templates'];

        // Register default templates
        $templates->register('default', [
            'path' => MFW_PLUGIN_DIR . 'views/mail/default.php',
            'layout' => MFW_PLUGIN_DIR . 'views/mail/layouts/default.php'
        ]);

        // Register password reset template
        $templates->register('password-reset', [
            'path' => MFW_PLUGIN_DIR . 'views/mail/password-reset.php',
            'layout' => MFW_PLUGIN_DIR . 'views/mail/layouts/default.php',
            'subject' => __('Password Reset Request', 'mfw')
        ]);

        // Register welcome template
        $templates->register('welcome', [
            'path' => MFW_PLUGIN_DIR . 'views/mail/welcome.php',
            'layout' => MFW_PLUGIN_DIR . 'views/mail/layouts/default.php',
            'subject' => __('Welcome to {site_name}', 'mfw')
        ]);

        // Register notification template
        $templates->register('notification', [
            'path' => MFW_PLUGIN_DIR . 'views/mail/notification.php',
            'layout' => MFW_PLUGIN_DIR . 'views/mail/layouts/default.php'
        ]);
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks() {
        // Override WordPress mail function
        add_filter('pre_wp_mail', function($null, $to, $subject, $message, $headers = '', $attachments = []) {
            return $this->container['mail.manager']->send([
                'to' => $to,
                'subject' => $subject,
                'message' => $message,
                'headers' => $headers,
                'attachments' => $attachments
            ]);
        }, 10, 6);

        // Process mail queue
        add_action('mfw_process_mail_queue', function() {
            $this->process_queue();
        });

        // Schedule mail queue processing
        if (!wp_next_scheduled('mfw_process_mail_queue')) {
            wp_schedule_event(time(), 'every_minute', 'mfw_process_mail_queue');
        }
    }

    /**
     * Register mail events
     *
     * @return void
     */
    protected function register_events() {
        $events = $this->container['events'];

        // Log mail events
        $events->listen('mail.sending', function($message) {
            $this->container['mail.logger']->info('Sending email', [
                'to' => $message['to'],
                'subject' => $message['subject']
            ]);
        });

        $events->listen('mail.sent', function($message) {
            $this->container['mail.logger']->info('Email sent successfully', [
                'to' => $message['to'],
                'subject' => $message['subject']
            ]);
        });

        $events->listen('mail.failed', function($message, $error) {
            $this->container['mail.logger']->error('Failed to send email', [
                'to' => $message['to'],
                'subject' => $message['subject'],
                'error' => $error
            ]);
        });
    }

    /**
     * Register admin interface
     *
     * @return void
     */
    protected function register_admin_interface() {
        add_action('admin_menu', function() {
            add_submenu_page(
                'tools.php',
                __('Mail Queue', 'mfw'),
                __('Mail Queue', 'mfw'),
                'manage_options',
                'mfw-mail-queue',
                [$this, 'render_queue_page']
            );
        });

        add_action('admin_post_mfw_process_queue', function() {
            if (!current_user_can('manage_options')) {
                wp_die(__('Permission denied.', 'mfw'));
            }

            $this->process_queue();
            wp_redirect(admin_url('tools.php?page=mfw-mail-queue'));
            exit;
        });

        add_action('admin_post_mfw_clear_queue', function() {
            if (!current_user_can('manage_options')) {
                wp_die(__('Permission denied.', 'mfw'));
            }

            $this->container['mail.queue']->clear();
            wp_redirect(admin_url('tools.php?page=mfw-mail-queue'));
            exit;
        });
    }

    /**
     * Process mail queue
     *
     * @return void
     */
    protected function process_queue() {
        $queue = $this->container['mail.queue'];
        $mailer = $this->container['mail.manager'];

        $queue->process(function($message) use ($mailer) {
            return $mailer->raw(
                $message['to'],
                $message['subject'],
                $message['body'],
                $message['headers'] ?? [],
                $message['attachments'] ?? []
            );
        });
    }

    /**
     * Render queue page
     *
     * @return void
     */
    public function render_queue_page() {
        $queue = $this->container['mail.queue'];
        $per_page = 20;
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $messages = $queue->paginate($per_page, $page);
        $total_pages = ceil($queue->count() / $per_page);

        include MFW_PLUGIN_DIR . 'views/admin/mail-queue.php';
    }
}