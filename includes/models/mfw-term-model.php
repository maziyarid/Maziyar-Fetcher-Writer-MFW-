<?php
/**
 * Term Model Class
 * 
 * Provides an object-oriented interface for WordPress terms.
 * Handles taxonomy terms, their relationships, and metadata.
 *
 * @package MFW
 * @subpackage Models
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Term_Model extends MFW_Model {
    /**
     * Model initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:40:13';

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
    protected $table = 'terms';

    /**
     * Primary key
     *
     * @var string
     */
    protected $primary_key = 'term_id';

    /**
     * Taxonomy
     *
     * @var string
     */
    protected $taxonomy = '';

    /**
     * Fillable attributes
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent',
        'taxonomy'
    ];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = [
        'term_group'
    ];

    /**
     * Casts
     *
     * @var array
     */
    protected $casts = [
        'term_id' => 'integer',
        'parent' => 'integer',
        'count' => 'integer'
    ];

    /**
     * Meta fields
     *
     * @var array
     */
    protected $meta = [];

    /**
     * Term taxonomy data
     *
     * @var array
     */
    protected $taxonomy_data = null;

    /**
     * Boot model
     *
     * @return void
     */
    protected function boot() {
        parent::boot();

        // Set taxonomy on creating
        $this->observe('creating', function() {
            if (!isset($this->attributes['taxonomy']) && $this->taxonomy) {
                $this->attributes['taxonomy'] = $this->taxonomy;
            }
        });
    }

    /**
     * Get parent term
     *
     * @return self|null Parent term model
     */
    public function parent() {
        return $this->parent ? static::find($this->parent) : null;
    }

    /**
     * Get child terms
     *
     * @return array Child term models
     */
    public function children() {
        return static::where('parent', $this->term_id)
            ->where('taxonomy', $this->get_taxonomy())
            ->get();
    }

    /**
     * Get term posts
     *
     * @param string $post_type Post type
     * @param array $args Query arguments
     * @return array Post models
     */
    public function posts($post_type = 'post', $args = []) {
        $defaults = [
            'post_type' => $post_type,
            'tax_query' => [
                [
                    'taxonomy' => $this->get_taxonomy(),
                    'terms' => $this->term_id
                ]
            ]
        ];

        return MFW_Post_Model::query(array_merge($defaults, $args))->get();
    }

    /**
     * Get term meta
     *
     * @param string $key Meta key
     * @param mixed $default Default value
     * @return mixed Meta value
     */
    public function get_meta($key, $default = null) {
        if (!isset($this->meta[$key])) {
            $this->meta[$key] = get_term_meta($this->term_id, $key, true);
        }
        return $this->meta[$key] ?: $default;
    }

    /**
     * Set term meta
     *
     * @param string $key Meta key
     * @param mixed $value Meta value
     * @return bool Whether meta was updated
     */
    public function set_meta($key, $value) {
        $this->meta[$key] = $value;
        return update_term_meta($this->term_id, $key, $value);
    }

    /**
     * Delete term meta
     *
     * @param string $key Meta key
     * @return bool Whether meta was deleted
     */
    public function delete_meta($key) {
        unset($this->meta[$key]);
        return delete_term_meta($this->term_id, $key);
    }

    /**
     * Get term taxonomy
     *
     * @return string Taxonomy name
     */
    public function get_taxonomy() {
        if ($this->taxonomy_data === null) {
            $this->load_taxonomy_data();
        }
        return $this->taxonomy_data['taxonomy'] ?? $this->taxonomy;
    }

    /**
     * Get term count
     *
     * @return int Term count
     */
    public function get_count() {
        if ($this->taxonomy_data === null) {
            $this->load_taxonomy_data();
        }
        return $this->taxonomy_data['count'] ?? 0;
    }

    /**
     * Load taxonomy data
     *
     * @return void
     */
    protected function load_taxonomy_data() {
        global $wpdb;
        $this->taxonomy_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->term_taxonomy} WHERE term_id = %d",
                $this->term_id
            ),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Get term link
     *
     * @return string Term link
     */
    public function get_link() {
        return get_term_link($this->term_id, $this->get_taxonomy());
    }

    /**
     * Get term hierarchy
     *
     * @return array Term hierarchy
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
     * Query builder methods
     */

    /**
     * Query by taxonomy
     *
     * @param string $taxonomy Taxonomy name
     * @return MFW_Query_Builder Query builder
     */
    public static function taxonomy($taxonomy) {
        return static::whereExists(function($query) use ($taxonomy) {
            $query->from('term_taxonomy')
                ->where('taxonomy', $taxonomy);
        });
    }

    /**
     * Query by parent
     *
     * @param int $parent_id Parent term ID
     * @return MFW_Query_Builder Query builder
     */
    public static function children_of($parent_id) {
        return static::whereExists(function($query) use ($parent_id) {
            $query->from('term_taxonomy')
                ->where('parent', $parent_id);
        });
    }

    /**
     * Search terms
     *
     * @param string $keyword Search keyword
     * @return MFW_Query_Builder Query builder
     */
    public static function search($keyword) {
        return static::where(function($query) use ($keyword) {
            $query->where('name', 'like', "%{$keyword}%")
                ->orWhere('slug', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
        });
    }

    /**
     * Get popular terms
     *
     * @param string $taxonomy Taxonomy name
     * @param int $limit Result limit
     * @return array Term models
     */
    public static function popular($taxonomy, $limit = 10) {
        return static::taxonomy($taxonomy)
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get();
    }
}