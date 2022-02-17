<?php

/**
 * The dependency injection container.
 *
 * @package StellarWP\Container
 */

namespace StellarWP\Container;

use Psr\Container\ContainerInterface;
use StellarWP\Container\Exceptions\ContainerException;
use StellarWP\Container\Exceptions\NotFoundException;
use StellarWP\Container\Exceptions\RecursiveDependencyException;

/**
 * A PSR-11 dependency injection container class.
 */
abstract class Container implements ContainerInterface
{
    /**
     * Whether or not resolutions should be cached.
     *
     * By default, this will be false but will be set to `true` when calling `get()`.
     *
     * @var bool True if resolutions should be cached, false otherwise.
     */
    protected $cacheResolutions = false;

    /**
     * Abstracts currently being resolved.
     *
     * @var Array<string,bool>
     */
    protected $currentlyResolving;

    /**
     * Extensions to the default container configuration.
     *
     * @var Array<string,callable> A mapping of abstracts to callables.
     */
    protected $extensions = [];

    /**
     * A cache of all resolved dependencies.
     *
     * @var Array<string,object> The resolved dependency, keyed by its abstract.
     */
    protected $resolved = [];

    /**
     * The cached, Singleton instance of the container.
     *
     * @var ?self
     */
    protected static $instance;

    /**
     * Retrieve a mapping of abstract identifiers to callables.
     *
     * When an abstract is requested through the container, the container will find the given
     * dependency in this array, execute the callable, and return the result.
     *
     * @return Array<string,callable|null> A mapping of abstracts to callables.
     */
    abstract public function config();

    /**
     * Extend the default container configuration.
     *
     * This allows definitions to be dynamically added or updated, which is especially useful
     * during testing.
     *
     * @param string   $abstract   The abstract to be added or replaced.
     * @param callable $definition A callable to construct the concrete instance of the abstract.
     *                             Like those in config(), each callable will recieve the current
     *                             container instance.
     *
     * @return $this
     */
    public function extend($abstract, callable $definition)
    {
        $this->extensions[$abstract] = $definition;

        return $this->forget($abstract);
    }

    /**
     * Remove the cached resolution for the given abstract.
     *
     * @param string $abstract The abstract identifier to forget.
     *
     * @return $this
     */
    public function forget($abstract)
    {
        unset($this->resolved[$abstract]);

        return $this;
    }

    /**
     * Resolve the given abstract through the container and return it.
     *
     * Results will be cached, enabling subsequent results to return the same instance.
     *
     * @param string $abstract The dependency's abstract identifier.
     *
     * @throws NotFoundException  If no entry was found for this abstract.
     * @throws ContainerException Error while retrieving the entry.
     *
     * @return object The resolved dependency.
     */
    public function get($abstract)
    {
        if (! array_key_exists($abstract, $this->resolved)) {
            $this->cacheResolutions = true;
            $this->make($abstract);
            $this->cacheResolutions = false;
        }

        return $this->resolved[$abstract];
    }

    /**
     * Determine whether or not an entry for the given abstract exists in the container.
     *
     * Please note that just because an entry exists for an abstract does not mean that dependency
     * can be built without errors. However, a truthy response from `has()` should guarantee that this
     * dependency will not produce a `NotFoundException`.
     *
     * @param string $abstract The abstract to look for.
     *
     * @return bool True if the container knows how to resolve the given identifier, false otherwise.
     */
    public function has($abstract)
    {
        $config = $this->config();

        return array_key_exists($abstract, $config);
    }

    /**
     * Check whether or not the given abstract has already been resolved.
     *
     * @param string $abstract The dependency's abstract identifier.
     *
     * @return bool True if the dependency exists in cache, false otherwise.
     */
    public function hasResolved($abstract)
    {
        return array_key_exists($abstract, $this->resolved);
    }

    /**
     * Resolve the given abstract through the container and return it without caching.
     *
     * Unlike get(), a new, uncached instance will be created upon each call.
     *
     * @param string $abstract The dependency's abstract identifier.
     *
     * @throws NotFoundException            If no entry was found for this abstract.
     * @throws ContainerException           Error while retrieving the entry.
     * @throws RecursiveDependencyException If a recursive loop is detected during resolution.
     *
     * @return object The resolved dependency.
     */
    public function make($abstract)
    {
        $config = array_merge($this->config(), $this->extensions);

        if (! array_key_exists($abstract, $config)) {
            throw new NotFoundException(
                sprintf('No container definition could be found for "%s".', $abstract)
            );
        }

        if (isset($this->currentlyResolving[$abstract])) {
            throw new RecursiveDependencyException(
                sprintf('Recursion detected when attempting to resolve "%s"', $abstract)
            );
        }

        try {
            $this->currentlyResolving[$abstract] = true;

            // If the definition is null, simply call `new $abstract()`.
            if (null === $config[$abstract]) {
                $resolved = new $abstract();

            // If given a string that references a container definition, treat this as an alias.
            } elseif (is_string($config[$abstract]) && $this->has($config[$abstract])) {
                $resolved = $this->make($config[$abstract]);

            // Otherwise, attempt to execute the callable.
            } else {
                $resolved = $config[$abstract]($this);
            }
        } catch (\Exception $e) {
            if ($e instanceof RecursiveDependencyException) {
                throw $e;
            }

            throw new ContainerException(
                sprintf('An error occured building "%s": %s', $abstract, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        // If the cache is enabled, cache this resolution.
        if ($this->cacheResolutions) {
            $this->resolved[$abstract] = $resolved;
        }

        unset($this->currentlyResolving[$abstract]);

        return $resolved;
    }

    /**
     * Remove any extensions for the given abstract, reverting to its original definition.
     *
     * @param string $abstract The abstract to restore.
     *
     * @return $this
     */
    public function restore($abstract)
    {
        unset($this->extensions[$abstract]);

        return $this->forget($abstract);
    }

    /**
     * Retrieve a Singleton instance of the container.
     *
     * Singletons are generally not a great idea, but the container is one place where it can make
     * a lot of sense.
     *
     * Note that this Singleton usage is totally optional: the class constructor can still be used
     * normally should you need multiple instances of the container.
     *
     * @param ?Container $instance A concrete instance of the container, used to seed this and future
     *                             calls to the instance() method. Default is empty (create and cache
     *                             a new instance).
     *
     * @return self
     */
    public static function getInstance(Container $instance = null)
    {
        if (null !== $instance) {
            self::$instance = $instance;
        }

        if (! isset(self::$instance)) {
            self::$instance = self::buildSingleton();
        }

        return self::$instance;
    }

    /**
     * Reset the current Singleton instance.
     *
     * @return void
     */
    public static function reset()
    {
        self::$instance = null;
    }

    /**
     * Build a new Singleton instance.
     *
     * If your Container instance requires constructor arguments, you may override this method to
     * avoid having to overwrite self::getInstance().
     *
     * @throws ContainerException If the container cannot be constructed.
     *
     * @return static
     */
    protected static function buildSingleton()
    {
        $constructor = (new \ReflectionClass(static::class))->getConstructor();
        $required    = $constructor ? $constructor->getNumberOfRequiredParameters() : 0;

        if (0 < $required) {
            throw new ContainerException(sprintf(
                '%1$s::__construct() has %2$d required argument(s), so %1$s::buildSingleton() must be overridden.',
                static::class,
                $required
            ));
        }

        // @phpstan-ignore-next-line As we're checking for possible invalid use of `new static()`.
        return new static();
    }
}
