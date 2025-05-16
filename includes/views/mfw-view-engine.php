<?php
/**
 * View Engine Class
 * 
 * Handles template compilation and rendering.
 * Provides Blade-like syntax and directives.
 *
 * @package MFW
 * @subpackage Views
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_View_Engine {
    /**
     * Engine initialization timestamp
     *
     * @var string
     */
    protected $init_timestamp = '2025-05-14 08:24:00';

    /**
     * Engine initialization user
     *
     * @var string
     */
    protected $init_user = 'maziyarid';

    /**
     * Global variables
     *
     * @var array
     */
    protected $globals = [];

    /**
     * Custom directives
     *
     * @var array
     */
    protected $directives = [];

    /**
     * Component aliases
     *
     * @var array
     */
    protected $components = [];

    /**
     * View factory instance
     *
     * @var MFW_View_Factory
     */
    protected $factory;

    /**
     * Cache path
     *
     * @var string
     */
    protected $cachePath;

    /**
     * Create new engine instance
     */
    public function __construct() {
        $this->factory = new MFW_View_Factory();
        $this->cachePath = WP_CONTENT_DIR . '/cache/mfw/views';

        // Register default directives
        $this->registerDefaultDirectives();

        // Create cache directory if needed
        if (!is_dir($this->cachePath)) {
            wp_mkdir_p($this->cachePath);
        }
    }

    /**
     * Render template
     *
     * @param string $path Template path
     * @param array $data Template data
     * @return string
     */
    public function render($path, $data = []) {
        // Get compiled path
        $compiled = $this->getCompiledPath($path);

        // Compile if needed
        if ($this->shouldCompile($path, $compiled)) {
            $this->compile($path, $compiled);
        }

        // Extract data and globals
        extract(array_merge($this->globals, $data));

        // Start output buffering
        ob_start();

        // Include compiled template
        include $compiled;

        // Return rendered content
        return ob_get_clean();
    }

    /**
     * Add global variable
     *
     * @param string $key Variable name
     * @param mixed $value Variable value
     * @return void
     */
    public function addGlobal($key, $value) {
        $this->globals[$key] = $value;
    }

    /**
     * Add custom directive
     *
     * @param string $name Directive name
     * @param callable $handler Directive handler
     * @return void
     */
    public function directive($name, $handler) {
        $this->directives[$name] = $handler;
    }

    /**
     * Add component alias
     *
     * @param string $alias Component alias
     * @param string $view Component view
     * @return void
     */
    public function component($alias, $view) {
        $this->components[$alias] = $view;
    }

    /**
     * Get compiled template path
     *
     * @param string $path Template path
     * @return string
     */
    protected function getCompiledPath($path) {
        return $this->cachePath . '/' . md5($path) . '.php';
    }

    /**
     * Check if template should be compiled
     *
     * @param string $path Template path
     * @param string $compiled Compiled path
     * @return bool
     */
    protected function shouldCompile($path, $compiled) {
        // Always compile in debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // Compile if compiled file doesn't exist
        if (!file_exists($compiled)) {
            return true;
        }

        // Compile if template has been modified
        return filemtime($path) >= filemtime($compiled);
    }

    /**
     * Compile template
     *
     * @param string $path Template path
     * @param string $compiled Compiled path
     * @return void
     */
    protected function compile($path, $compiled) {
        // Get template content
        $content = file_get_contents($path);

        // Apply directives
        $content = $this->compileDirectives($content);

        // Apply components
        $content = $this->compileComponents($content);

        // Apply extends and sections
        $content = $this->compileExtends($content);
        $content = $this->compileSections($content);

        // Apply includes
        $content = $this->compileIncludes($content);

        // Apply echos
        $content = $this->compileEchos($content);

        // Apply PHP tags
        $content = $this->compilePhp($content);

        // Save compiled template
        file_put_contents($compiled, $content);
    }

    /**
     * Compile directives
     *
     * @param string $content Template content
     * @return string
     */
    protected function compileDirectives($content) {
        foreach ($this->directives as $name => $handler) {
            $pattern = "/@{$name}(\s*\(.*\))?/";
            $content = preg_replace_callback($pattern, function($matches) use ($handler) {
                $args = isset($matches[1]) ? trim($matches[1], '()') : '';
                return call_user_func($handler, $args);
            }, $content);
        }

        return $content;
    }

    /**
     * Compile components
     *
     * @param string $content Template content
     * @return string
     */
    protected function compileComponents($content) {
        foreach ($this->components as $alias => $view) {
            $pattern = "/<x-{$alias}(\s+[^>]*)?>/";
            $content = preg_replace_callback($pattern, function($matches) use ($view) {
                $attrs = isset($matches[1]) ? $this->parseAttributes($matches[1]) : [];
                return "<?php echo \$this->renderComponent('{$view}', " . var_export($attrs, true) . "); ?>";
            }, $content);
        }

        return $content;
    }

    /**
     * Compile extends
     *
     * @param string $content Template content
     * @return string
     */
    protected function compileExtends($content) {
        $pattern = '/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/';
        
        if (preg_match($pattern, $content, $matches)) {
            $layout = $matches[1];
            $content = preg_replace($pattern, '', $content);
            
            return "<?php \$this->extend('{$layout}'); ?>\n" . $content;
        }

        return $content;
    }

    /**
     * Compile sections
     *
     * @param string $content Template content
     * @return string
     */
    protected function compileSections($content) {
        $pattern = '/@section\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)(.*?)@endsection/s';
        
        return preg_replace_callback($pattern, function($matches) {
            $name = $matches[1];
            $content = $matches[2];
            
            return "<?php \$this->startSection('{$name}'); ?>\n"
                . $content
                . "\n<?php \$this->endSection(); ?>";
        }, $content);
    }

    /**
     * Compile includes
     *
     * @param string $content Template content
     * @return string
     */
    protected function compileIncludes($content) {
        $pattern = '/@include\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*(.+))?\)/';
        
        return preg_replace_callback($pattern, function($matches) {
            $view = $matches[1];
            $data = isset($matches[2]) ? trim($matches[2]) : '[]';
            
            return "<?php echo \$this->include('{$view}', {$data}); ?>";
        }, $content);
    }

    /**
     * Compile echos
     *
     * @param string $content Template content
     * @return string
     */
    protected function compileEchos($content) {
        // Compile escaped echos
        $content = preg_replace('/\{\{\s*(.+?)\s*\}\}/', '<?php echo esc_html($1); ?>', $content);

        // Compile unescaped echos
        $content = preg_replace('/\{\!\!\s*(.+?)\s*\!\!\}/', '<?php echo $1; ?>', $content);

        return $content;
    }

    /**
     * Compile PHP tags
     *
     * @param string $content Template content
     * @return string
     */
    protected function compilePhp($content) {
        // Compile PHP tags
        $content = preg_replace('/@php\s*(.+?)\s*@endphp/s', '<?php $1 ?>', $content);

        // Compile single-line PHP
        $content = preg_replace('/@php\s*(.+?)$/m', '<?php $1 ?>', $content);

        return $content;
    }

    /**
     * Register default directives
     *
     * @return void
     */
    protected function registerDefaultDirectives() {
        // Control structures
        $this->directive('if', function($expression) {
            return "<?php if{$expression}: ?>";
        });

        $this->directive('else', function() {
            return '<?php else: ?>';
        });

        $this->directive('elseif', function($expression) {
            return "<?php elseif{$expression}: ?>";
        });

        $this->directive('endif', function() {
            return '<?php endif; ?>';
        });

        // Loops
        $this->directive('foreach', function($expression) {
            return "<?php foreach{$expression}: ?>";
        });

        $this->directive('endforeach', function() {
            return '<?php endforeach; ?>';
        });

        // WordPress specific
        $this->directive('wp', function($expression) {
            return "<?php wp{$expression}; ?>";
        });

        $this->directive('wphead', function() {
            return '<?php wp_head(); ?>';
        });

        $this->directive('wpfooter', function() {
            return '<?php wp_footer(); ?>';
        });
    }

    /**
     * Parse component attributes
     *
     * @param string $string Attribute string
     * @return array
     */
    protected function parseAttributes($string) {
        $attributes = [];
        $pattern = '/(\w+)=([\'"])(.*?)\2/';
        
        preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[3];
        }
        
        return $attributes;
    }
}