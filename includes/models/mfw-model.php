<?php
/**
 * Base Model Class
 * 
 * Provides base functionality for all framework models.
 * Handles database operations, relationships, and model events.
 *
 * @package MFW
 * @subpackage Models
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Model {
    use MFW_Observable;
    use MFW_Configurable;
    use MFW_Loggable;

    /**
     * Model initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:36:52';

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
    protected $table;

    /**
     * Primary key
     *
     * @var string
     */
    protected $primary_key = 'id';

    /**
     * Fillable attributes
     *
     * @var array
     */
    protected $fillable = [];

    /**
     * Hidden attributes
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Casts
     *
     * @var array
     */
    protected $casts = [];

    /**
     * Relationships
     *
     * @var array
     */
    protected $relationships = [];

    /**
     * Model attributes
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Original attributes
     *
     * @var array
     */
    protected $original = [];

    /**
     * Whether model exists in database
     *
     * @var bool
     */
    protected $exists = false;

    /**
     * Query builder instance
     *
     * @var MFW_Query_Builder
     */
    protected $query;

    /**
     * Create new model instance
     *
     * @param array $attributes Model attributes
     */
    public function __construct($attributes = []) {
        $this->boot();
        $this->fill($attributes);
    }

    /**
     * Boot model
     *
     * @return void
     */
    protected function boot() {
        $this->query = mfw_service('db')->table($this->get_table());
        $this->register_events();
    }

    /**
     * Register model events
     *
     * @return void
     */
    protected function register_events() {
        $this->observe([
            'creating' => [$this, 'on_creating'],
            'created' => [$this, 'on_created'],
            'updating' => [$this, 'on_updating'],
            'updated' => [$this, 'on_updated'],
            'deleting' => [$this, 'on_deleting'],
            'deleted' => [$this, 'on_deleted'],
        ]);
    }

    /**
     * Get table name
     *
     * @return string Table name
     */
    public function get_table() {
        if (!$this->table) {
            $this->table = strtolower(
                preg_replace('/([a-z])([A-Z])/', '$1_$2', 
                str_replace('MFW_', '', get_class($this)))
            );
        }
        return $this->table;
    }

    /**
     * Fill model attributes
     *
     * @param array $attributes Attributes to fill
     * @return self
     */
    public function fill($attributes) {
        foreach ($attributes as $key => $value) {
            if ($this->is_fillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    /**
     * Check if attribute is fillable
     *
     * @param string $key Attribute key
     * @return bool Whether attribute is fillable
     */
    protected function is_fillable($key) {
        return in_array($key, $this->fillable) && !in_array($key, $this->hidden);
    }

    /**
     * Set attribute
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return void
     */
    public function setAttribute($key, $value) {
        $this->attributes[$key] = $this->cast_attribute($key, $value);
    }

    /**
     * Get attribute
     *
     * @param string $key Attribute key
     * @return mixed Attribute value
     */
    public function getAttribute($key) {
        if (!isset($this->attributes[$key])) {
            return null;
        }
        return $this->cast_attribute($key, $this->attributes[$key]);
    }

    /**
     * Cast attribute
     *
     * @param string $key Attribute key
     * @param mixed $value Attribute value
     * @return mixed Casted value
     */
    protected function cast_attribute($key, $value) {
        if (!isset($this->casts[$key])) {
            return $value;
        }

        switch ($this->casts[$key]) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return (array) maybe_unserialize($value);
            case 'json':
                return json_decode($value, true);
            case 'datetime':
                return new \DateTime($value);
            default:
                return $value;
        }
    }

    /**
     * Get all attributes
     *
     * @return array All attributes
     */
    public function getAttributes() {
        return $this->attributes;
    }

    /**
     * Save model
     *
     * @return bool Whether save was successful
     */
    public function save() {
        $this->fire_event($this->exists ? 'updating' : 'creating');

        if ($this->exists) {
            $saved = $this->query
                ->where($this->primary_key, $this->attributes[$this->primary_key])
                ->update($this->get_dirty());
        } else {
            $saved = $this->query->insert($this->attributes);
            if ($saved) {
                $this->attributes[$this->primary_key] = $this->query->insert_id();
                $this->exists = true;
            }
        }

        if ($saved) {
            $this->fire_event($this->exists ? 'updated' : 'created');
            $this->sync_original();
        }

        return $saved;
    }

    /**
     * Delete model
     *
     * @return bool Whether delete was successful
     */
    public function delete() {
        if (!$this->exists) {
            return false;
        }

        $this->fire_event('deleting');

        $deleted = $this->query
            ->where($this->primary_key, $this->attributes[$this->primary_key])
            ->delete();

        if ($deleted) {
            $this->exists = false;
            $this->fire_event('deleted');
        }

        return $deleted;
    }

    /**
     * Get changed attributes
     *
     * @return array Changed attributes
     */
    public function get_dirty() {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $value !== $this->original[$key]) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Sync original attributes
     *
     * @return void
     */
    protected function sync_original() {
        $this->original = $this->attributes;
    }

    /**
     * Find model by ID
     *
     * @param mixed $id Model ID
     * @return static|null Model instance
     */
    public static function find($id) {
        $instance = new static;
        $attributes = $instance->query
            ->where($instance->primary_key, $id)
            ->first();

        if (!$attributes) {
            return null;
        }

        $instance->exists = true;
        $instance->fill($attributes);
        $instance->sync_original();

        return $instance;
    }

    /**
     * Create new model
     *
     * @param array $attributes Model attributes
     * @return static Model instance
     */
    public static function create($attributes) {
        $instance = new static($attributes);
        $instance->save();
        return $instance;
    }

    /**
     * Magic getter
     *
     * @param string $key Property key
     * @return mixed Property value
     */
    public function __get($key) {
        return $this->getAttribute($key);
    }

    /**
     * Magic setter
     *
     * @param string $key Property key
     * @param mixed $value Property value
     */
    public function __set($key, $value) {
        $this->setAttribute($key, $value);
    }

    /**
     * Convert to array
     *
     * @return array Model as array
     */
    public function to_array() {
        $array = [];

        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden)) {
                $array[$key] = $this->cast_attribute($key, $value);
            }
        }

        return $array;
    }

    /**
     * Convert to JSON
     *
     * @return string Model as JSON
     */
    public function to_json() {
        return wp_json_encode($this->to_array());
    }
}