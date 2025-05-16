<?php
/**
 * Base Model Class
 * 
 * Provides base functionality for all models.
 * Handles database operations and field validation.
 *
 * @package MFW
 * @subpackage Models
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Model_Base {
    /**
     * Current timestamp
     */
    protected $current_time = '2025-05-13 19:13:07';

    /**
     * Current user
     */
    protected $current_user = 'maziyarid';

    /**
     * Model ID
     */
    protected $id;

    /**
     * Table name
     */
    protected $table;

    /**
     * Primary key
     */
    protected $primary_key = 'id';

    /**
     * Fields configuration
     */
    protected $fields = [];

    /**
     * Required fields
     */
    protected $required = [];

    /**
     * Field validations
     */
    protected $validations = [];

    /**
     * Field defaults
     */
    protected $defaults = [];

    /**
     * Initialize model
     *
     * @param int $id Model ID
     */
    public function __construct($id = null) {
        $this->id = $id;
        $this->init();
    }

    /**
     * Initialize model configuration
     */
    abstract protected function init();

    /**
     * Get model by ID
     *
     * @param int $id Model ID
     * @return static|false Model instance or false if not found
     */
    public static function get($id) {
        $instance = new static($id);
        return $instance->load() ? $instance : false;
    }

    /**
     * Load model data
     *
     * @return bool Whether load was successful
     */
    public function load() {
        global $wpdb;

        $data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}{$this->table} WHERE {$this->primary_key} = %d",
                $this->id
            ),
            ARRAY_A
        );

        if (!$data) {
            return false;
        }

        foreach ($this->fields as $field => $config) {
            if (isset($data[$field])) {
                $this->$field = $this->format_field_value($field, $data[$field]);
            }
        }

        return true;
    }

    /**
     * Save model
     *
     * @return bool|int False on failure, model ID on success
     */
    public function save() {
        try {
            $this->validate();

            global $wpdb;
            $data = $this->prepare_data();

            if ($this->id) {
                // Update
                $result = $wpdb->update(
                    $wpdb->prefix . $this->table,
                    $data['values'],
                    [$this->primary_key => $this->id],
                    $data['formats'],
                    ['%d']
                );

                if ($result === false) {
                    throw new Exception(__('Failed to update record.', 'mfw'));
                }

            } else {
                // Insert
                $result = $wpdb->insert(
                    $wpdb->prefix . $this->table,
                    $data['values'],
                    $data['formats']
                );

                if ($result === false) {
                    throw new Exception(__('Failed to create record.', 'mfw'));
                }

                $this->id = $wpdb->insert_id;
            }

            // Log operation
            $this->log_operation($this->id ? 'update' : 'create');

            return $this->id;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Delete model
     *
     * @param bool $force Whether to force delete
     * @return bool Whether deletion was successful
     */
    public function delete($force = false) {
        try {
            if (!$this->id) {
                throw new Exception(__('Cannot delete unsaved record.', 'mfw'));
            }

            global $wpdb;

            if ($force) {
                $result = $wpdb->delete(
                    $wpdb->prefix . $this->table,
                    [$this->primary_key => $this->id],
                    ['%d']
                );
            } else {
                $result = $wpdb->update(
                    $wpdb->prefix . $this->table,
                    ['deleted_at' => $this->current_time],
                    [$this->primary_key => $this->id],
                    ['%s'],
                    ['%d']
                );
            }

            if ($result === false) {
                throw new Exception(__('Failed to delete record.', 'mfw'));
            }

            // Log operation
            $this->log_operation('delete', ['force' => $force]);

            return true;

        } catch (Exception $e) {
            MFW_Error_Logger::log($e->getMessage(), get_class($this), 'error');
            return false;
        }
    }

    /**
     * Validate model data
     *
     * @throws Exception If validation fails
     */
    protected function validate() {
        $errors = [];

        // Check required fields
        foreach ($this->required as $field) {
            if (empty($this->$field)) {
                $errors[] = sprintf(
                    __('Field "%s" is required.', 'mfw'),
                    $field
                );
            }
        }

        // Run field validations
        foreach ($this->validations as $field => $validations) {
            if (isset($this->$field)) {
                foreach ($validations as $validation => $params) {
                    if (!$this->validate_field($field, $validation, $params)) {
                        $errors[] = sprintf(
                            __('Field "%s" failed validation: %s', 'mfw'),
                            $field,
                            $validation
                        );
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors));
        }
    }

    /**
     * Validate field value
     *
     * @param string $field Field name
     * @param string $validation Validation type
     * @param mixed $params Validation parameters
     * @return bool Whether validation passed
     */
    protected function validate_field($field, $validation, $params) {
        $value = $this->$field;

        switch ($validation) {
            case 'email':
                return is_email($value);

            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;

            case 'numeric':
                return is_numeric($value);

            case 'min':
                return is_numeric($value) && $value >= $params;

            case 'max':
                return is_numeric($value) && $value <= $params;

            case 'length':
                $length = strlen($value);
                return $length >= $params['min'] && $length <= $params['max'];

            case 'in':
                return in_array($value, $params);

            case 'unique':
                return $this->check_unique($field, $value);

            default:
                return true;
        }
    }

    /**
     * Check if field value is unique
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @return bool Whether value is unique
     */
    protected function check_unique($field, $value) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}{$this->table} WHERE {$field} = %s",
            $value
        );

        if ($this->id) {
            $query .= $wpdb->prepare(
                " AND {$this->primary_key} != %d",
                $this->id
            );
        }

        return (int)$wpdb->get_var($query) === 0;
    }

    /**
     * Prepare data for database operation
     *
     * @return array Prepared data with values and formats
     */
    protected function prepare_data() {
        $values = [];
        $formats = [];

        foreach ($this->fields as $field => $config) {
            if (isset($this->$field)) {
                $values[$field] = $this->$field;
                $formats[] = $this->get_field_format($config['type']);
            }
        }

        // Add timestamps
        if ($this->id) {
            $values['updated_at'] = $this->current_time;
            $values['updated_by'] = $this->current_user;
            $formats[] = '%s';
            $formats[] = '%s';
        } else {
            $values['created_at'] = $this->current_time;
            $values['created_by'] = $this->current_user;
            $formats[] = '%s';
            $formats[] = '%s';
        }

        return [
            'values' => $values,
            'formats' => $formats
        ];
    }

    /**
     * Get field format for database operation
     *
     * @param string $type Field type
     * @return string Field format
     */
    protected function get_field_format($type) {
        switch ($type) {
            case 'integer':
                return '%d';
            case 'float':
                return '%f';
            case 'boolean':
                return '%d';
            default:
                return '%s';
        }
    }

    /**
     * Format field value based on field type
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @return mixed Formatted value
     */
    protected function format_field_value($field, $value) {
        if (!isset($this->fields[$field])) {
            return $value;
        }

        switch ($this->fields[$field]['type']) {
            case 'integer':
                return (int)$value;
            case 'float':
                return (float)$value;
            case 'boolean':
                return (bool)$value;
            case 'json':
                return json_decode($value, true);
            case 'datetime':
                return strtotime($value);
            default:
                return $value;
        }
    }

    /**
     * Log model operation
     *
     * @param string $operation Operation type
     * @param array $details Additional details
     */
    protected function log_operation($operation, $details = []) {
        try {
            global $wpdb;

            $wpdb->insert(
                $wpdb->prefix . 'mfw_model_log',
                [
                    'model' => get_class($this),
                    'model_id' => $this->id,
                    'operation' => $operation,
                    'details' => json_encode($details),
                    'created_by' => $this->current_user,
                    'created_at' => $this->current_time
                ],
                ['%s', '%d', '%s', '%s', '%s', '%s']
            );

        } catch (Exception $e) {
            MFW_Error_Logger::log(
                sprintf('Failed to log model operation: %s', $e->getMessage()),
                get_class($this),
                'error'
            );
        }
    }
}