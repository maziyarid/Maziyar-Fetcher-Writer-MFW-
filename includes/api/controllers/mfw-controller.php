<?php
/**
 * Base Controller Class
 * 
 * Provides base functionality for all framework controllers.
 * Handles request processing, response generation, and view rendering.
 *
 * @package MFW
 * @subpackage Controllers
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

abstract class MFW_Controller {
    use MFW_Observable;
    use MFW_Configurable;
    use MFW_Loggable;

    /**
     * Controller initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 07:24:02';

    /**
     * Controller initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * Request instance
     *
     * @var MFW_Request
     */
    protected $request;

    /**
     * Response instance
     *
     * @var MFW_Response
     */
    protected $response;

    /**
     * View instance
     *
     * @var MFW_View
     */
    protected $view;

    /**
     * Controller middleware
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * Initialize controller
     */
    public function __construct() {
        $this->request = mfw_service('request');
        $this->response = mfw_service('response');
        $this->view = mfw_service('view');

        $this->init();
    }

    /**
     * Initialize controller
     * Override this method to add custom initialization
     *
     * @return void
     */
    protected function init() {}

    /**
     * Execute middleware
     *
     * @param string $action Action name
     * @return bool Whether middleware execution was successful
     */
    protected function execute_middleware($action) {
        foreach ($this->middleware as $middleware) {
            if (is_string($middleware)) {
                $middleware = mfw_service('middleware')->get($middleware);
            }

            if (!$middleware->handle($this->request, $action)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Render view
     *
     * @param string $view View name
     * @param array $data View data
     * @param string|null $layout Layout name
     * @return string Rendered view
     */
    protected function render($view, array $data = [], $layout = null) {
        return $this->view->render($view, $data, $layout);
    }

    /**
     * JSON response
     *
     * @param mixed $data Response data
     * @param int $status HTTP status code
     * @return MFW_Response Response instance
     */
    protected function json($data, $status = 200) {
        return $this->response
            ->header('Content-Type', 'application/json')
            ->status($status)
            ->body(wp_json_encode($data));
    }

    /**
     * Redirect response
     *
     * @param string $url Redirect URL
     * @param int $status HTTP status code
     * @return MFW_Response Response instance
     */
    protected function redirect($url, $status = 302) {
        return $this->response
            ->header('Location', esc_url_raw($url))
            ->status($status);
    }

    /**
     * Get request parameter
     *
     * @param string $key Parameter key
     * @param mixed $default Default value
     * @return mixed Parameter value
     */
    protected function input($key, $default = null) {
        return $this->request->input($key, $default);
    }

    /**
     * Validate request data
     *
     * @param array $rules Validation rules
     * @return bool Whether validation passed
     */
    protected function validate($rules) {
        return $this->request->validate($rules);
    }

    /**
     * Get validation errors
     *
     * @return array Validation errors
     */
    protected function get_validation_errors() {
        return $this->request->get_validation_errors();
    }

    /**
     * Check if request is AJAX
     *
     * @return bool Whether request is AJAX
     */
    protected function is_ajax() {
        return $this->request->is_ajax();
    }

    /**
     * Check if request is POST
     *
     * @return bool Whether request is POST
     */
    protected function is_post() {
        return $this->request->is_method('POST');
    }

    /**
     * Get request method
     *
     * @return string Request method
     */
    protected function get_method() {
        return $this->request->get_method();
    }

    /**
     * Add middleware
     *
     * @param string|callable $middleware Middleware name or callable
     * @return self
     */
    protected function add_middleware($middleware) {
        $this->middleware[] = $middleware;
        return $this;
    }

    /**
     * Set middleware
     *
     * @param array $middleware Middleware array
     * @return self
     */
    protected function set_middleware($middleware) {
        $this->middleware = $middleware;
        return $this;
    }

    /**
     * Get middleware
     *
     * @return array Middleware array
     */
    protected function get_middleware() {
        return $this->middleware;
    }

    /**
     * Handle errors
     *
     * @param \Exception $e Exception instance
     * @return MFW_Response Response instance
     */
    protected function handle_error(\Exception $e) {
        $this->error($e->getMessage(), [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        if ($this->is_ajax()) {
            return $this->json([
                'error' => $e->getMessage()
            ], 500);
        }

        return $this->render('error', [
            'error' => $e->getMessage()
        ]);
    }

    /**
     * Execute action
     *
     * @param string $action Action name
     * @param array $params Action parameters
     * @return mixed Action result
     */
    public function execute($action, array $params = []) {
        if (!method_exists($this, $action)) {
            throw new \RuntimeException(sprintf(
                'Action %s does not exist in %s',
                $action,
                get_class($this)
            ));
        }

        if (!$this->execute_middleware($action)) {
            return $this->response->status(403);
        }

        try {
            return call_user_func_array([$this, $action], $params);
        } catch (\Exception $e) {
            return $this->handle_error($e);
        }
    }
}