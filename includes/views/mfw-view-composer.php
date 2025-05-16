<?php
/**
 * View Composer Class
 * 
 * Handles view composition and data injection.
 * Manages view callbacks and shared data.
 *
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_View_Composer {
    /**
     * Composer initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 08:25:28';

    /**
     * Composer initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * View instance
     *
     * @var MFW_View
     */
    protected $view;

    /**
     * View data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Create new composer instance
     *
     * @param MFW_View $view View instance
     */
    public function __construct($view) {
        $this->view = $view;
    }

    /**
     * Get view instance
     *
     * @return MFW_View
     */
    public function getView() {
        return $this->view;
    }

    /**
     * Get view data
     *
     * @return array
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Set view data
     *
     * @param array $data View data
     * @return $this
     */
    public function setData($data) {
        $this->data = $data;
        return $this;
    }

    /**
     * Add view data
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
     * Compose view
     *
     * @return void
     */
    public function compose() {
        // Override in child classes
    }

    /**
     * Call composer method
     *
     * @param string $method Method name
     * @param array $parameters Method parameters
     * @return mixed
     */
    public function __call($method, $parameters) {
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $parameters);
        }

        throw new MFW_View_Exception("Method [{$method}] not found in view composer.");
    }
}

/**
 * Example Admin Layout Composer
 */
class MFW_Admin_Layout_Composer extends MFW_View_Composer {
    /**
     * Compose view
     *
     * @return void
     */
    public function compose() {
        $this->with([
            'title' => $this->getPageTitle(),
            'menu' => $this->getAdminMenu(),
            'user' => $this->getCurrentUser(),
            'notices' => $this->getAdminNotices()
        ]);
    }

    /**
     * Get page title
     *
     * @return string
     */
    protected function getPageTitle() {
        return get_admin_page_title();
    }

    /**
     * Get admin menu
     *
     * @return array
     */
    protected function getAdminMenu() {
        global $menu, $submenu;
        return [
            'main' => $menu ?? [],
            'sub' => $submenu ?? []
        ];
    }

    /**
     * Get current user
     *
     * @return WP_User
     */
    protected function getCurrentUser() {
        return wp_get_current_user();
    }

    /**
     * Get admin notices
     *
     * @return array
     */
    protected function getAdminNotices() {
        return get_settings_errors();
    }
}

/**
 * Example Frontend Layout Composer
 */
class MFW_Frontend_Layout_Composer extends MFW_View_Composer {
    /**
     * Compose view
     *
     * @return void
     */
    public function compose() {
        $this->with([
            'site' => $this->getSiteInfo(),
            'menu' => $this->getMainMenu(),
            'sidebar' => $this->getSidebar(),
            'footer' => $this->getFooterWidgets()
        ]);
    }

    /**
     * Get site info
     *
     * @return array
     */
    protected function getSiteInfo() {
        return [
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => home_url(),
            'charset' => get_bloginfo('charset'),
            'language' => get_bloginfo('language')
        ];
    }

    /**
     * Get main menu
     *
     * @return array
     */
    protected function getMainMenu() {
        return wp_get_nav_menu_items('primary');
    }

    /**
     * Get sidebar
     *
     * @return array
     */
    protected function getSidebar() {
        ob_start();
        dynamic_sidebar('main-sidebar');
        return ob_get_clean();
    }

    /**
     * Get footer widgets
     *
     * @return array
     */
    protected function getFooterWidgets() {
        $footer_widgets = [];
        for ($i = 1; $i <= 4; $i++) {
            ob_start();
            dynamic_sidebar("footer-{$i}");
            $footer_widgets[$i] = ob_get_clean();
        }
        return $footer_widgets;
    }
}

/**
 * Example Form Component Composer
 */
class MFW_Form_Component_Composer extends MFW_View_Composer {
    /**
     * Compose view
     *
     * @return void
     */
    public function compose() {
        $this->with([
            'method' => $this->getMethod(),
            'action' => $this->getAction(),
            'csrf' => $this->getCsrfField(),
            'errors' => $this->getErrors()
        ]);
    }

    /**
     * Get form method
     *
     * @return string
     */
    protected function getMethod() {
        return $this->getData()['method'] ?? 'POST';
    }

    /**
     * Get form action
     *
     * @return string
     */
    protected function getAction() {
        return $this->getData()['action'] ?? admin_url('admin-post.php');
    }

    /**
     * Get CSRF field
     *
     * @return string
     */
    protected function getCsrfField() {
        return wp_nonce_field('mfw_form', '_wpnonce', true, false);
    }

    /**
     * Get form errors
     *
     * @return array
     */
    protected function getErrors() {
        return get_settings_errors('mfw_form');
    }
}