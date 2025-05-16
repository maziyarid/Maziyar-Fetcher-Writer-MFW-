<?php
/**
 * User Model Class
 * 
 * Provides an object-oriented interface for WordPress users.
 * Handles user CRUD operations, metadata, and relationships.
 *
 * @package MFW
 * @subpackage Models
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_User_Model extends MFW_Model {
    /**
     * Model initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:39:14';

    /**
     * Model initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * Table name
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Primary key
     *
     * @var string
     */
    protected $primary_key = 'ID';

    /**
     * Fillable attributes
     *
     * @var array
     */
    protected $fillable = [
        'user_login',
        'user_email',
        'user_pass',
        'user_nicename',
        'display_name',
        'user_url',
        'user_registered',
        'user_status',
        'role'
    ];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = [
        'user_pass',
        'user_activation_key'
    ];

    /**
     * Casts
     *
     * @var array
     */
    protected $casts = [
        'ID' => 'integer',
        'user_status' => 'integer',
        'user_registered' => 'datetime'
    ];

    /**
     * Meta fields
     *
     * @var array
     */
    protected $meta = [];

    /**
     * User capabilities
     *
     * @var array
     */
    protected $capabilities = null;

    /**
     * Boot model
     *
     * @return void
     */
    protected function boot() {
        parent::boot();

        // Hash password on creating/updating
        $this->observe(['creating', 'updating'], function() {
            if (isset($this->attributes['user_pass']) && !wp_check_password('', $this->attributes['user_pass'])) {
                $this->attributes['user_pass'] = wp_hash_password($this->attributes['user_pass']);
            }
        });
    }

    /**
     * Get user posts
     *
     * @param array $args Query arguments
     * @return array Post models
     */
    public function posts($args = []) {
        $defaults = [
            'author' => $this->ID,
            'post_type' => 'post',
            'post_status' => 'publish'
        ];

        return MFW_Post_Model::query(array_merge($defaults, $args))->get();
    }

    /**
     * Get user comments
     *
     * @param array $args Query arguments
     * @return array Comment models
     */
    public function comments($args = []) {
        $defaults = [
            'user_id' => $this->ID,
            'status' => 'approve'
        ];

        return MFW_Comment_Model::query(array_merge($defaults, $args))->get();
    }

    /**
     * Get user meta
     *
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed Meta value
     */
    public function get_meta($key, $default = null) {
        if (!isset($this->meta[$key])) {
            $this->meta[$key] = get_user_meta($this->ID, $key, true);
        }
        return $this->meta[$key] ?: $default;
    }

    /**
     * Set user meta
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Whether meta was updated
     */
    public function set_meta($key, $value) {
        $this->meta[$key] = $value;
        return update_user_meta($this->ID, $key, $value);
    }

    /**
     * Delete user meta
     *
     * @param string $key Meta key
     * @return bool Whether meta was deleted
     */
    public function delete_meta($key) {
        unset($this->meta[$key]);
        return delete_user_meta($this->ID, $key);
    }

    /**
     * Get user roles
     *
     * @return array User roles
     */
    public function get_roles() {
        $user = new WP_User($this->ID);
        return $user->roles;
    }

    /**
     * Check if user has role
     *
     * @param string|array $role Role(s) to check
     * @return bool Whether user has role
     */
    public function has_role($role) {
        return array_intersect((array) $role, $this->get_roles());
    }

    /**
     * Add role to user
     *
     * @param string $role Role to add
     * @return void
     */
    public function add_role($role) {
        $user = new WP_User($this->ID);
        $user->add_role($role);
    }

    /**
     * Remove role from user
     *
     * @param string $role Role to remove
     * @return void
     */
    public function remove_role($role) {
        $user = new WP_User($this->ID);
        $user->remove_role($role);
    }

    /**
     * Set user role
     *
     * @param string $role Role to set
     * @return void
     */
    public function set_role($role) {
        $user = new WP_User($this->ID);
        $user->set_role($role);
    }

    /**
     * Check if user has capability
     *
     * @param string $capability Capability to check
     * @return bool Whether user has capability
     */
    public function can($capability) {
        if ($this->capabilities === null) {
            $user = new WP_User($this->ID);
            $this->capabilities = $user->allcaps;
        }
        return isset($this->capabilities[$capability]) && $this->capabilities[$capability];
    }

    /**
     * Get user avatar URL
     *
     * @param int $size Avatar size
     * @return string Avatar URL
     */
    public function get_avatar_url($size = 96) {
        return get_avatar_url($this->ID, ['size' => $size]);
    }

    /**
     * Get user posts URL
     *
     * @return string Posts URL
     */
    public function get_posts_url() {
        return get_author_posts_url($this->ID);
    }

    /**
     * Authenticate user
     *
     * @param string $password Password to check
     * @return bool Whether password is correct
     */
    public function authenticate($password) {
        return wp_check_password($password, $this->user_pass);
    }

    /**
     * Send password reset email
     *
     * @return bool Whether email was sent
     */
    public function send_password_reset() {
        return retrieve_password($this->user_login);
    }

    /**
     * Query builder methods
     */

    /**
     * Query by role
     *
     * @param string $role Role to query
     * @return MFW_Query_Builder Query builder
     */
    public static function role($role) {
        return static::whereExists(function($query) use ($role) {
            $query->from('usermeta')
                ->where('meta_key', 'wp_capabilities')
                ->where('meta_value', 'like', '%"' . $role . '"%');
        });
    }

    /**
     * Query by capability
     *
     * @param string $capability Capability to query
     * @return MFW_Query_Builder Query builder
     */
    public static function can($capability) {
        return static::whereExists(function($query) use ($capability) {
            $query->from('usermeta')
                ->where('meta_key', 'wp_capabilities')
                ->where('meta_value', 'like', '%"' . $capability . '"%');
        });
    }

    /**
     * Search users
     *
     * @param string $keyword Search keyword
     * @return MFW_Query_Builder Query builder
     */
    public static function search($keyword) {
        return static::where(function($query) use ($keyword) {
            $query->where('user_login', 'like', "%{$keyword}%")
                ->orWhere('user_email', 'like', "%{$keyword}%")
                ->orWhere('display_name', 'like', "%{$keyword}%");
        });
    }
}