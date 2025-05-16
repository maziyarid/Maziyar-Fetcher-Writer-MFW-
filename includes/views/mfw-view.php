<?php
/**
 * View Class
 * 
 * Handles template rendering, view composers, and components.
 * Provides a flexible template engine for the framework.
 *
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_View {
    /**
     * View initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 08:11:56';

    /**
     * View initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * View factory instance
     *
     * @var MFW_View_Factory
     */
    protected $factory;

    /**
     * View engine instance
     *
     * @var MFW_View_Engine
     */
    protected $engine;

    /**
     * View composers
     *
     * @var array
     */
    protected $composers = [];

    /**
     * Shared data
     *
     * @var array
     */
    protected $shared = [];

    /**
     * Template path
     *
     * @var string
     */
    protected $path;

    /**
     * View data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Create new view instance
     *
     * @param string $path Template path
     * @param array $data View data
     */
    public function __construct($path = null, $data = []) {
        $this->factory = new MFW_View_Factory();
        $this->engine = new MFW_View_Engine();
        
        if ($path) {
            $this->path = $path;
            $this->data = $data;
        }
    }

    /**
     * Make new view instance
     *
     * @param string $path Template path
     * @param array $data View data
     * @return static
     */
    public static function make($path, $data = []) {
        return new static($path, $data);
    }

    /**
     * Register view composer
     *
     * @param string|array $views View names
     * @param callable|string $callback Composer callback
     * @return void
     */
    public function composer($views, $callback) {
        foreach ((array) $views as $view) {
            if (!isset($this->composers[$view])) {
                $this->composers[$view] = [];
            }

            $this->composers[$view][] = $callback;
        }
    }

    /**
     * Share data across all views
     *
     * @param string|array $key Data key or array
     * @param mixed $value Data value
     * @return void
     */
    public function share($key, $value = null) {
        if (is_array($key)) {
            $this->shared = array_merge($this->shared, $key);
        } else {
            $this->shared[$key] = $value;
        }
    }

    /**
     * Add view global
     *
     * @param string $key Global key
     * @param mixed $value Global value
     * @return void
     */
    public function addGlobal($key, $value) {
        $this->engine->addGlobal($key, $value);
    }

    /**
     * Register view extension
     *
     * @param string $extension Extension name
     * @param callable $callback Extension callback
     * @return void
     */
    public function extend($extension, $callback) {
        $this->engine->extend($extension, $callback);
    }

    /**
     * Get view exists
     *
     * @param string $path Template path
     * @return bool
     */
    public function exists($path) {
        return $this->factory->exists($path);
    }

    /**
     * Get view data
     *
     * @param string $key Data key
     * @param mixed $default Default value
     * @return mixed
     */
    public function getData($key = null, $default = null) {
        if (is_null($key)) {
            return array_merge($this->shared, $this->data);
        }

        $data = array_merge($this->shared, $this->data);
        return isset($data[$key]) ? $data[$key] : $default;
    }

    /**
     * Set view data
     *
     * @param string|array $key Data key or array
     * @param mixed $value Data value
     * @return $this
     */
    public function with($key, $value = null) {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Render view
     *
     * @return string
     * @throws MFW_View_Exception
     */
    public function render() {
        if (!$this->path) {
            throw new MFW_View_Exception('No view path specified.');
        }

        // Call view composers
        $this->callComposers();

        // Get template path
        $path = $this->factory->find($this->path);
        if (!$path) {
            throw new MFW_View_Exception("View [{$this->path}] not found.");
        }

        // Get view data
        $data = array_merge($this->shared, $this->data);

        // Render template
        return $this->engine->render($path, $data);
    }

    /**
     * Call view composers
     *
     * @return void
     */
    protected function callComposers() {
        if (!isset($this->composers[$this->path])) {
            return;
        }

        $view = $this;

        foreach ($this->composers[$this->path] as $callback) {
            if (is_string($callback) && class_exists($callback)) {
                $callback = [new $callback, 'compose'];
            }

            call_user_func($callback, $view);
        }
    }

    /**
     * Convert view to string
     *
     * @return string
     */
    public function __toString() {
        try {
            return $this->render();
        } catch (\Exception $e) {
            return "Error rendering view: {$e->getMessage()}";
        }
    }
}