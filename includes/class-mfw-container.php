<?php
/**
 * Container Class
 * 
 * Dependency injection container implementation.
 * Handles service registration, resolution, and lifecycle management.
 *
 * @package MFW
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

class MFW_Container {
    use MFW_Configurable;
    use MFW_Loggable;

    /**
     * Container bindings
     *
     * @var array
     */
    private $bindings = [];

    /**
     * Container instances
     *
     * @var array
     */
    private $instances = [];

    /**
     * Container aliases
     *
     * @var array
     */
    private $aliases = [];

    /**
     * Container tags
     *
     * @var array
     */
    private $tags = [];

    /**
     * Initialize container
     */
    public function __construct() {
        $this->init_timestamp = '2025-05-14 06:31:27';
        $this->init_user = 'maziyarid';

        $this->info('Container initialized');
    }

    /**
     * Bind a service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     * @param bool $shared Whether the service is shared
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false) {
        // Remove previous binding
        $this->unset($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof \Closure) {
            $concrete = $this->get_closure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');

        $this->debug('Service bound', [
            'abstract' => $abstract,
            'shared' => $shared
        ]);
    }

    /**
     * Bind a shared service to the container
     *
     * @param string $abstract Service identifier
     * @param mixed $concrete Service implementation
     * @return void
     */
    public function singleton($abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Add a service alias
     *
     * @param string $abstract Service identifier
     * @param string $alias Service alias
     * @return void
     */
    public function alias($abstract, $alias) {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Tag services
     *
     * @param array|string $abstracts Service identifiers
     * @param array|string $tags Tags
     * @return void
     */
    public function tag($abstracts, $tags) {
        $tags = (array) $tags;

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve a service
     *
     * @param string $abstract Service identifier
     * @param array $parameters Constructor parameters
     * @return mixed Service instance
     * @throws Exception If service cannot be resolved
     */
    public function resolve($abstract, array $parameters = []) {
        $abstract = $this->get_alias($abstract);

        // Return existing instance if shared
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->get_concrete($abstract);

        // Create the service instance
        $object = $this->build($concrete, $parameters);

        // Store shared instances
        if ($this->is_shared($abstract)) {
            $this->instances[$abstract] = $object;
        }

        $this->debug('Service resolved', [
            'abstract' => $abstract,
            'class' => get_class($object)
        ]);

        return $object;
    }

    /**
     * Get services by tag
     *
     * @param string $tag Tag name
     * @return array Tagged services
     */
    public function tagged($tag) {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return array_map(function ($abstract) {
            return $this->resolve($abstract);
        }, $this->tags[$tag]);
    }

    /**
     * Check if service exists
     *
     * @param string $abstract Service identifier
     * @return bool Whether service exists
     */
    public function has($abstract) {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Remove service binding
     *
     * @param string $abstract Service identifier
     * @return void
     */
    public function unset($abstract) {
        unset($this->bindings[$abstract], $this->instances[$abstract]);
    }

    /**
     * Get service closure
     *
     * @param string $abstract Service identifier
     * @param string $concrete Service implementation
     * @return \Closure Service factory
     */
    private function get_closure($abstract, $concrete) {
        return function ($container, $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete, $parameters);
            }

            return $container->resolve($concrete, $parameters);
        };
    }

    /**
     * Get service alias
     *
     * @param string $abstract Service identifier
     * @return string Real service identifier
     */
    private function get_alias($abstract) {
        return isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
    }

    /**
     * Get service concrete implementation
     *
     * @param string $abstract Service identifier
     * @return mixed Service implementation
     */
    private function get_concrete($abstract) {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Check if service is shared
     *
     * @param string $abstract Service identifier
     * @return bool Whether service is shared
     */
    private function is_shared($abstract) {
        return isset($this->bindings[$abstract]['shared']) && $this->bindings[$abstract]['shared'];
    }

    /**
     * Build service instance
     *
     * @param mixed $concrete Service implementation
     * @param array $parameters Constructor parameters
     * @return object Service instance
     * @throws Exception If service cannot be built
     */
    private function build($concrete, array $parameters = []) {
        if ($concrete instanceof \Closure) {
            return $concrete($this, $parameters);
        }

        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new Exception("Service [$concrete] cannot be resolved: " . $e->getMessage());
        }

        if (!$reflector->isInstantiable()) {
            throw new Exception("Service [$concrete] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $this->resolve_dependencies($constructor, $parameters);

        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Resolve constructor dependencies
     *
     * @param \ReflectionMethod $constructor Constructor reflection
     * @param array $parameters Constructor parameters
     * @return array Resolved dependencies
     */
    private function resolve_dependencies(\ReflectionMethod $constructor, array $parameters) {
        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $dependency = $parameter->getType() && !$parameter->getType()->isBuiltin()
                ? $this->resolve_class_dependency($parameter, $parameters)
                : $this->resolve_parameter_dependency($parameter, $parameters);

            if ($dependency !== null) {
                $dependencies[] = $dependency;
            }
        }

        return $dependencies;
    }

    /**
     * Resolve class dependency
     *
     * @param \ReflectionParameter $parameter Parameter reflection
     * @param array $parameters Constructor parameters
     * @return mixed Resolved dependency
     */
    private function resolve_class_dependency(\ReflectionParameter $parameter, array &$parameters) {
        $class = $parameter->getType()->getName();

        if (isset($parameters[$parameter->name]) && $parameters[$parameter->name] instanceof $class) {
            return array_pull($parameters, $parameter->name);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        return $this->resolve($class);
    }

    /**
     * Resolve parameter dependency
     *
     * @param \ReflectionParameter $parameter Parameter reflection
     * @param array $parameters Constructor parameters
     * @return mixed Resolved dependency
     */
    private function resolve_parameter_dependency(\ReflectionParameter $parameter, array &$parameters) {
        if (isset($parameters[$parameter->name])) {
            return array_pull($parameters, $parameter->name);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isOptional()) {
            return null;
        }

        throw new Exception("Unable to resolve dependency [{$parameter->name}]");
    }
}