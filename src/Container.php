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
     * Abstracts currently being resolved.
     *
     * @var Array<string,bool>
     */
    protected $currentlyResolving = [];

    /**
     * Extensions to the default container configuration.
     *
     * @var Array<string,callable|object|string|null> A mapping of abstracts to callables.
     */
    protected $extensions = [];

    /**
     * Whether or not resolutions should be cached.
     *
     * By default, this will be 0 but will be incremented with each successive call to `get()`.
     *
     * @var int An integer representing the depth of the dependency caching.
     */
    protected $resolutionCacheDepth = 0;

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
     * @return Array<string,callable|object|string|null> A mapping of abstracts to callables.
     */
    abstract public function config();

    /**
     * Extend the default container configuration.
     *
     * This allows definitions to be dynamically added or updated, which is especially useful
     * during testing.
     *
     * @param string          $abstract   The abstract to be added or replaced.
     * @param callable|object $definition Either the resolved dependency object or a callable that
     *                                    can be used to construct the concrete instance of the
     *                                    abstract. Like those in config(), each callable will recieve
     *                                    the current container instance.
     *
     * @return $this
     */
    public function extend($abstract, $definition)
    {
        $this->extensions[$abstract] = $definition;

        // If we have a resolved, concrete instance go ahead and prime the cache.
        if (is_object($definition) && ! $definition instanceof \Closure) {
            $this->resolved[$abstract] = $definition;
        } else {
            $this->forget($abstract);
        }

        return $this;
    }

    /**
     * Remove the cached resolution for the given abstract.
     *
     * @param string ...$abstracts The abstract identifier(s) to forget.
     *
     * @return $this
     */
    public function forget(...$abstracts)
    {
        foreach ($abstracts as $abstract) {
            unset($this->resolved[$abstract]);
        }

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
        /*
         * If we don't yet have a resolution for this abstract, increment the resolutionCacheDepth;
         * if this value is > 0, calls to the make() command for sub-dependencies will also be
         * cached as if they were called via get().
         */
        if (! isset($this->resolved[$abstract])) {
            $this->resolutionCacheDepth++;
            $this->resolved[$abstract] = $this->make($abstract);
            $this->resolutionCacheDepth--;
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

        // If caching is enabled and we have a resolution, return it immediately.
        if ($this->resolutionCacheDepth > 0 && array_key_exists($abstract, $this->resolved)) {
            return $this->resolved[$abstract];
        }

        // No definition exists in the config for this abstract.
        if (! array_key_exists($abstract, $config)) {
            throw new NotFoundException(
                sprintf('No container definition could be found for "%s".', $abstract)
            );
        }

        // Catch recursive resolutions.
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

            // If the definition is a non-closure object, simply return it.
            } elseif (is_object($config[$abstract]) && ! $config[$abstract] instanceof \Closure) {
                $resolved = $config[$abstract];

            // If the definition is callable, execute it and return the result.
            } elseif (is_callable($config[$abstract])) {
                $resolved = $config[$abstract]($this);

            // If all else fails, throw an exception.
            } else {
                throw new ContainerException(sprintf('Unhandled definition type (%s)', gettype($config[$abstract])));
            }
        } catch (RecursiveDependencyException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ContainerException(
                sprintf('An error occured building "%s": %s', $abstract, $e->getMessage()),
                $e->getCode(),
                $e
            );
        }

        // If the cache is enabled, cache this resolution.
        if ($this->resolutionCacheDepth > 0) {
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
