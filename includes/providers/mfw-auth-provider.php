<?php
/**
 * Authentication Service Provider
 * 
 * Registers authentication services and guards.
 * Handles user authentication, authorization, and sessions.
 *
 * @package MFW
 * @subpackage Providers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Auth_Provider extends MFW_Service_Provider {
    /**
     * Provider initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:49:23';

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
        // Register authentication manager
        $this->singleton('auth.manager', function($app) {
            return new MFW_Auth_Manager($app);
        });

        // Register session manager
        $this->singleton('auth.session', function() {
            return new MFW_Session_Manager();
        });

        // Register token manager
        $this->singleton('auth.tokens', function($app) {
            return new MFW_Token_Manager(
                $app['db'],
                $app['config']->get('auth.tokens', [])
            );
        });

        // Register role provider
        $this->singleton('auth.roles', function() {
            return new MFW_Role_Provider();
        });

        // Register capability provider
        $this->singleton('auth.capabilities', function() {
            return new MFW_Capability_Provider();
        });

        // Register password broker
        $this->singleton('auth.passwords', function($app) {
            return new MFW_Password_Broker(
                $app['mail'],
                $app['config']->get('auth.passwords', [])
            );
        });

        // Register two-factor authentication provider
        $this->singleton('auth.2fa', function($app) {
            return new MFW_Two_Factor_Provider(
                $app['config']->get('auth.2fa', [])
            );
        });
    }

    /**
     * Boot services
     *
     * @return void
     */
    public function boot() {
        // Start session
        $this->container['auth.session']->start();

        // Register authentication routes
        $this->register_routes();

        // Register middleware
        $this->register_middleware();

        // Register WordPress hooks
        $this->register_hooks();

        // Register custom roles and capabilities
        $this->register_roles();
        $this->register_capabilities();
    }

    /**
     * Register authentication routes
     *
     * @return void
     */
    protected function register_routes() {
        $router = $this->container['router'];

        // Login routes
        $router->get('/login', 'MFW_Auth_Controller@show_login');
        $router->post('/login', 'MFW_Auth_Controller@login');
        $router->post('/logout', 'MFW_Auth_Controller@logout');

        // Password reset routes
        $router->get('/password/reset', 'MFW_Auth_Controller@show_reset');
        $router->post('/password/email', 'MFW_Auth_Controller@send_reset');
        $router->get('/password/reset/{token}', 'MFW_Auth_Controller@show_reset_form');
        $router->post('/password/reset', 'MFW_Auth_Controller@reset');

        // Two-factor authentication routes
        $router->get('/2fa/setup', 'MFW_Auth_Controller@show_2fa_setup');
        $router->post('/2fa/setup', 'MFW_Auth_Controller@setup_2fa');
        $router->get('/2fa/verify', 'MFW_Auth_Controller@show_2fa_verify');
        $router->post('/2fa/verify', 'MFW_Auth_Controller@verify_2fa');
    }

    /**
     * Register authentication middleware
     *
     * @return void
     */
    protected function register_middleware() {
        $router = $this->container['router'];

        // Authentication middleware
        $router->middleware('auth', function($request, $next) {
            if (!$this->container['auth.manager']->check()) {
                return redirect()->to('/login')
                    ->with('error', __('Please login to continue.', 'mfw'));
            }
            return $next($request);
        });

        // Role middleware
        $router->middleware('role', function($request, $next, $role) {
            if (!$this->container['auth.manager']->hasRole($role)) {
                return redirect()->back()
                    ->with('error', __('Permission denied.', 'mfw'));
            }
            return $next($request);
        });

        // Capability middleware
        $router->middleware('can', function($request, $next, $capability) {
            if (!$this->container['auth.manager']->can($capability)) {
                return redirect()->back()
                    ->with('error', __('Permission denied.', 'mfw'));
            }
            return $next($request);
        });
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    protected function register_hooks() {
        // Handle login
        add_filter('authenticate', function($user, $username, $password) {
            if ($user instanceof WP_User) {
                return $user;
            }

            return $this->container['auth.manager']->attempt([
                'user_login' => $username,
                'user_password' => $password
            ]);
        }, 30, 3);

        // Handle logout
        add_action('wp_logout', function() {
            $this->container['auth.manager']->logout();
        });

        // Handle password reset
        add_action('retrieve_password', function($user_login) {
            $this->container['auth.passwords']->sendResetLink([
                'user_login' => $user_login
            ]);
        });

        // Add two-factor authentication to profile
        add_action('show_user_profile', [$this, 'add_2fa_profile_fields']);
        add_action('edit_user_profile', [$this, 'add_2fa_profile_fields']);
        add_action('personal_options_update', [$this, 'save_2fa_profile_fields']);
        add_action('edit_user_profile_update', [$this, 'save_2fa_profile_fields']);
    }

    /**
     * Register custom roles
     *
     * @return void
     */
    protected function register_roles() {
        $roles = $this->container['config']->get('auth.roles', []);
        $provider = $this->container['auth.roles'];

        foreach ($roles as $role => $capabilities) {
            $provider->create($role, $capabilities);
        }
    }

    /**
     * Register custom capabilities
     *
     * @return void
     */
    protected function register_capabilities() {
        $capabilities = $this->container['config']->get('auth.capabilities', []);
        $provider = $this->container['auth.capabilities'];

        foreach ($capabilities as $capability => $roles) {
            $provider->register($capability, $roles);
        }
    }

    /**
     * Add two-factor authentication profile fields
     *
     * @param WP_User $user User object
     * @return void
     */
    public function add_2fa_profile_fields($user) {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }

        $provider = $this->container['auth.2fa'];
        ?>
        <h2><?php _e('Two-Factor Authentication', 'mfw'); ?></h2>
        <table class="form-table">
            <tr>
                <th>
                    <label for="mfw_2fa_enabled">
                        <?php _e('Enable 2FA', 'mfw'); ?>
                    </label>
                </th>
                <td>
                    <input type="checkbox" name="mfw_2fa_enabled" id="mfw_2fa_enabled"
                        <?php checked($provider->isEnabled($user->ID)); ?>>
                    <p class="description">
                        <?php _e('Enable two-factor authentication for additional security.', 'mfw'); ?>
                    </p>
                </td>
            </tr>
            <?php if ($provider->isEnabled($user->ID)): ?>
                <tr>
                    <th><?php _e('Recovery Codes', 'mfw'); ?></th>
                    <td>
                        <button type="button" class="button" data-mfw-action="generate-recovery-codes">
                            <?php _e('Generate New Recovery Codes', 'mfw'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Generate new recovery codes. Previous codes will be invalidated.', 'mfw'); ?>
                        </p>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
        <?php
    }

    /**
     * Save two-factor authentication profile fields
     *
     * @param int $user_id User ID
     * @return void
     */
    public function save_2fa_profile_fields($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        $provider = $this->container['auth.2fa'];
        $enabled = isset($_POST['mfw_2fa_enabled']);

        if ($enabled && !$provider->isEnabled($user_id)) {
            $provider->enable($user_id);
        } elseif (!$enabled && $provider->isEnabled($user_id)) {
            $provider->disable($user_id);
        }
    }
}