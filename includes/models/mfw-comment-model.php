<?php
/**
 * Comment Model Class
 * 
 * Provides an object-oriented interface for WordPress comments.
 * Handles comment CRUD operations, metadata, and relationships.
 *
 * @package MFW
 * @subpackage Models
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Comment_Model extends MFW_Model {
    /**
     * Model initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:41:12';

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
    protected $table = 'comments';

    /**
     * Primary key
     *
     * @var string
     */
    protected $primary_key = 'comment_ID';

    /**
     * Fillable attributes
     *
     * @var array
     */
    protected $fillable = [
        'comment_post_ID',
        'comment_author',
        'comment_author_email',
        'comment_author_url',
        'comment_content',
        'comment_parent',
        'user_id',
        'comment_type'
    ];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = [
        'comment_author_IP',
        'comment_agent'
    ];

    /**
     * Casts
     *
     * @var array
     */
    protected $casts = [
        'comment_ID' => 'integer',
        'comment_post_ID' => 'integer',
        'comment_parent' => 'integer',
        'user_id' => 'integer',
        'comment_date' => 'datetime',
        'comment_date_gmt' => 'datetime'
    ];

    /**
     * Meta fields
     *
     * @var array
     */
    protected $meta = [];

    /**
     * Boot model
     *
     * @return void
     */
    protected function boot() {
        parent::boot();

        // Set default comment type
        $this->observe('creating', function() {
            if (!isset($this->attributes['comment_type'])) {
                $this->attributes['comment_type'] = 'comment';
            }
        });
    }

    /**
     * Get comment post
     *
     * @return MFW_Post_Model|null Post model
     */
    public function post() {
        return MFW_Post_Model::find($this->comment_post_ID);
    }

    /**
     * Get comment author user
     *
     * @return MFW_User_Model|null User model
     */
    public function author() {
        return $this->user_id ? MFW_User_Model::find($this->user_id) : null;
    }

    /**
     * Get parent comment
     *
     * @return self|null Parent comment model
     */
    public function parent() {
        return $this->comment_parent ? static::find($this->comment_parent) : null;
    }

    /**
     * Get child comments
     *
     * @return array Child comment models
     */
    public function children() {
        return static::where('comment_parent', $this->comment_ID)->get();
    }

    /**
     * Get comment meta
     *
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed Meta value
     */
    public function get_meta($key, $default = null) {
        if (!isset($this->meta[$key])) {
            $this->meta[$key] = get_comment_meta($this->comment_ID, $key, true);
        }
        return $this->meta[$key] ?: $default;
    }

    /**
     * Set comment meta
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Whether meta was updated
     */
    public function set_meta($key, $value) {
        $this->meta[$key] = $value;
        return update_comment_meta($this->comment_ID, $key, $value);
    }

    /**
     * Delete comment meta
     *
     * @param string $key Meta key
     * @return bool Whether meta was deleted
     */
    public function delete_meta($key) {
        unset($this->meta[$key]);
        return delete_comment_meta($this->comment_ID, $key);
    }

    /**
     * Get comment permalink
     *
     * @return string Comment permalink
     */
    public function get_permalink() {
        return get_comment_link($this->comment_ID);
    }

    /**
     * Get comment status
     *
     * @return string Comment status
     */
    public function get_status() {
        return wp_get_comment_status($this->comment_ID);
    }

    /**
     * Set comment status
     *
     * @param string $status New status
     * @return bool Whether status was updated
     */
    public function set_status($status) {
        return wp_set_comment_status($this->comment_ID, $status);
    }

    /**
     * Approve comment
     *
     * @return bool Whether comment was approved
     */
    public function approve() {
        return $this->set_status('approve');
    }

    /**
     * Unapprove comment
     *
     * @return bool Whether comment was unapproved
     */
    public function unapprove() {
        return $this->set_status('hold');
    }

    /**
     * Mark comment as spam
     *
     * @return bool Whether comment was marked as spam
     */
    public function spam() {
        return $this->set_status('spam');
    }

    /**
     * Mark comment as trash
     *
     * @return bool Whether comment was trashed
     */
    public function trash() {
        return $this->set_status('trash');
    }

    /**
     * Get comment hierarchy
     *
     * @return array Comment hierarchy
     */
    public function get_hierarchy() {
        $hierarchy = [$this];
        $parent = $this->parent();

        while ($parent) {
            array_unshift($hierarchy, $parent);
            $parent = $parent->parent();
        }

        return $hierarchy;
    }

    /**
     * Get comment depth
     *
     * @return int Comment depth
     */
    public function get_depth() {
        return count($this->get_hierarchy()) - 1;
    }

    /**
     * Query builder methods
     */

    /**
     * Query by post
     *
     * @param int $post_id Post ID
     * @return MFW_Query_Builder Query builder
     */
    public static function for_post($post_id) {
        return static::where('comment_post_ID', $post_id);
    }

    /**
     * Query by author
     *
     * @param int $user_id User ID
     * @return MFW_Query_Builder Query builder
     */
    public static function by_author($user_id) {
        return static::where('user_id', $user_id);
    }

    /**
     * Query by type
     *
     * @param string $type Comment type
     * @return MFW_Query_Builder Query builder
     */
    public static function type($type) {
        return static::where('comment_type', $type);
    }

    /**
     * Query approved comments
     *
     * @return MFW_Query_Builder Query builder
     */
    public static function approved() {
        return static::where('comment_approved', '1');
    }

    /**
     * Query pending comments
     *
     * @return MFW_Query_Builder Query builder
     */
    public static function pending() {
        return static::where('comment_approved', '0');
    }

    /**
     * Query spam comments
     *
     * @return MFW_Query_Builder Query builder
     */
    public static function spam() {
        return static::where('comment_approved', 'spam');
    }

    /**
     * Query trashed comments
     *
     * @return MFW_Query_Builder Query builder
     */
    public static function trashed() {
        return static::where('comment_approved', 'trash');
    }

    /**
     * Search comments
     *
     * @param string $keyword Search keyword
     * @return MFW_Query_Builder Query builder
     */
    public static function search($keyword) {
        return static::where(function($query) use ($keyword) {
            $query->where('comment_content', 'like', "%{$keyword}%")
                ->orWhere('comment_author', 'like', "%{$keyword}%")
                ->orWhere('comment_author_email', 'like', "%{$keyword}%");
        });
    }
}