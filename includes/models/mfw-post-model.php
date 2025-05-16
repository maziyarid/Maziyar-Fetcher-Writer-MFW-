<?php
/**
 * Post Model Class
 * 
 * Provides an object-oriented interface for WordPress posts.
 * Handles post CRUD operations, metadata, and relationships.
 *
 * @package MFW
 * @subpackage Models
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Post_Model extends MFW_Model {
    /**
     * Model initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:38:09';

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
    protected $table = 'posts';

    /**
     * Primary key
     *
     * @var string
     */
    protected $primary_key = 'ID';

    /**
     * Post type
     *
     * @var string
     */
    protected $post_type = 'post';

    /**
     * Fillable attributes
     *
     * @var array
     */
    protected $fillable = [
        'post_title',
        'post_content',
        'post_excerpt',
        'post_status',
        'post_type',
        'post_author',
        'post_parent',
        'menu_order',
        'post_name',
        'post_password',
        'comment_status',
        'ping_status'
    ];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = [
        'post_password'
    ];

    /**
     * Casts
     *
     * @var array
     */
    protected $casts = [
        'ID' => 'integer',
        'post_author' => 'integer',
        'post_parent' => 'integer',
        'menu_order' => 'integer',
        'post_date' => 'datetime',
        'post_date_gmt' => 'datetime',
        'post_modified' => 'datetime',
        'post_modified_gmt' => 'datetime'
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
        
        // Set post type on creating
        $this->observe('creating', function() {
            if (!isset($this->attributes['post_type'])) {
                $this->attributes['post_type'] = $this->post_type;
            }
        });
    }

    /**
     * Get author
     *
     * @return MFW_User_Model|null Author model
     */
    public function author() {
        return MFW_User_Model::find($this->post_author);
    }

    /**
     * Get parent post
     *
     * @return self|null Parent post model
     */
    public function parent() {
        return $this->post_parent ? static::find($this->post_parent) : null;
    }

    /**
     * Get children posts
     *
     * @return array Child post models
     */
    public function children() {
        return static::where('post_parent', $this->ID)->get();
    }

    /**
     * Get post terms
     *
     * @param string $taxonomy Taxonomy name
     * @return array Term models
     */
    public function terms($taxonomy) {
        $terms = wp_get_post_terms($this->ID, $taxonomy);
        return array_map(function($term) {
            return new MFW_Term_Model((array) $term);
        }, $terms);
    }

    /**
     * Get post categories
     *
     * @return array Category models
     */
    public function categories() {
        return $this->terms('category');
    }

    /**
     * Get post tags
     *
     * @return array Tag models
     */
    public function tags() {
        return $this->terms('post_tag');
    }

    /**
     * Get post comments
     *
     * @param array $args Query arguments
     * @return array Comment models
     */
    public function comments($args = []) {
        $defaults = [
            'post_id' => $this->ID,
            'status' => 'approve'
        ];

        $comments = get_comments(array_merge($defaults, $args));
        
        return array_map(function($comment) {
            return new MFW_Comment_Model((array) $comment);
        }, $comments);
    }

    /**
     * Get post meta
     *
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed Meta value
     */
    public function get_meta($key, $default = null) {
        if (!isset($this->meta[$key])) {
            $this->meta[$key] = get_post_meta($this->ID, $key, true);
        }
        return $this->meta[$key] ?: $default;
    }

    /**
     * Set post meta
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Whether meta was updated
     */
    public function set_meta($key, $value) {
        $this->meta[$key] = $value;
        return update_post_meta($this->ID, $key, $value);
    }

    /**
     * Delete post meta
     *
     * @param string $key Meta key
     * @return bool Whether meta was deleted
     */
    public function delete_meta($key) {
        unset($this->meta[$key]);
        return delete_post_meta($this->ID, $key);
    }

    /**
     * Get post permalink
     *
     * @return string Post permalink
     */
    public function get_permalink() {
        return get_permalink($this->ID);
    }

    /**
     * Get post thumbnail
     *
     * @param string $size Image size
     * @return array|false Thumbnail data or false
     */
    public function get_thumbnail($size = 'thumbnail') {
        if (!has_post_thumbnail($this->ID)) {
            return false;
        }
        return wp_get_attachment_image_src(get_post_thumbnail_id($this->ID), $size);
    }

    /**
     * Set post thumbnail
     *
     * @param int $attachment_id Attachment ID
     * @return bool Whether thumbnail was set
     */
    public function set_thumbnail($attachment_id) {
        return set_post_thumbnail($this->ID, $attachment_id);
    }

    /**
     * Query builder methods
     */

    /**
     * Query by post type
     *
     * @param string $type Post type
     * @return MFW_Query_Builder Query builder
     */
    public static function type($type) {
        return static::where('post_type', $type);
    }

    /**
     * Query by post status
     *
     * @param string $status Post status
     * @return MFW_Query_Builder Query builder
     */
    public static function status($status) {
        return static::where('post_status', $status);
    }

    /**
     * Query published posts
     *
     * @return MFW_Query_Builder Query builder
     */
    public static function published() {
        return static::status('publish');
    }

    /**
     * Query draft posts
     *
     * @return MFW_Query_Builder Query builder
     */
    public static function drafts() {
        return static::status('draft');
    }

    /**
     * Query by author
     *
     * @param int $author_id Author ID
     * @return MFW_Query_Builder Query builder
     */
    public static function by_author($author_id) {
        return static::where('post_author', $author_id);
    }

    /**
     * Query by taxonomy term
     *
     * @param string $taxonomy Taxonomy name
     * @param int|string $term Term ID or slug
     * @return MFW_Query_Builder Query builder
     */
    public static function with_term($taxonomy, $term) {
        return static::whereExists(function($query) use ($taxonomy, $term) {
            $query->from('term_relationships')
                ->join('term_taxonomy', 'term_taxonomy.term_taxonomy_id', '=', 'term_relationships.term_taxonomy_id')
                ->where('term_taxonomy.taxonomy', $taxonomy)
                ->where(is_numeric($term) ? 'term_id' : 'slug', $term);
        });
    }

    /**
     * Search posts
     *
     * @param string $keyword Search keyword
     * @return MFW_Query_Builder Query builder
     */
    public static function search($keyword) {
        return static::where(function($query) use ($keyword) {
            $query->where('post_title', 'like', "%{$keyword}%")
                ->orWhere('post_content', 'like', "%{$keyword}%");
        });
    }
}