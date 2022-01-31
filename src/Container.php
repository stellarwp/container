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

/**
 * A PSR-11 dependency injection container class.
 */
abstract class Container implements ContainerInterface {

	/**
	 * A cache of all resolved dependencies.
	 *
	 * @var Array<string,mixed> The resolved dependency, keyed by its abstract.
	 */
	protected $resolved = [];

	/**
	 * The cached, Singleton instance of the container.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Retrieve a mapping of abstract identifiers to callables.
	 *
	 * When an abstract is requested through the container, the container will find the given
	 * dependency in this array, execute the callable, and return the result.
	 *
	 * @return Array<string,callable> A mapping of abstracts to callables.
	 */
	abstract public function config();

	/**
	 * Remove the cached resolution for the given abstract.
	 *
	 * @param string $abstract The abstract identifier to forget.
	 *
	 * @return self
	 */
	public function forget( $abstract ) {
		unset( $this->resolved[ $abstract ] );

		return $this;
	}

	/**
	 * Resolve the given abstract through the container and return it.
	 *
	 * Results will be cached, enabling subsequent results to return the same instance.
	 *
	 * @throws NotFoundException  If no entry was found for this abstract.
	 * @throws ContainerException Error while retrieving the entry.
	 *
	 * @param string $abstract The dependency's abstract identifier.
	 *
	 * @return mixed The resolved dependency.
	 */
	public function get( $abstract ) {
		if ( ! key_exists( $abstract, $this->resolved ) ) {
			$this->resolved[ $abstract ] = $this->make( $abstract );
		}

		return $this->resolved[ $abstract ];
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
	public function has( $abstract ) {
		$config = $this->config();

		return key_exists( $abstract, $config );
	}

	/**
	 * Resolve the given abstract through the container and return it without caching.
	 *
	 * Unlike get(), a new, uncached instance will be created upon each call.
	 *
	 * @throws NotFoundException  If no entry was found for this abstract.
	 * @throws ContainerException Error while retrieving the entry.
	 *
	 * @param string $id The dependency's abstract identifier.
	 *
	 * @return mixed The resolved dependency.
	 */
	public function make( $abstract ) {
		$config = $this->config();

		if ( ! key_exists( $abstract, $config ) ) {
			throw new NotFoundException(
				sprintf( 'No container definition could be found for "%s".', $abstract )
			);
		}

		try {
			if ( null === $config[ $abstract ] ) {
				return new $abstract();
			}

			$resolved = $config[ $abstract ]( $this );
		} catch ( \Exception $e ) {
			throw new ContainerException(
				sprintf( 'An error occured building "%s": %s', $abstract, $e->getMessage() ),
				$e->getCode(),
				$e
			);
		}

		return $resolved;
	}

	/**
	 * Check whether or not the given abstract has already been resolved.
	 *
	 * @param string $abstract The dependency's abstract identifier.
	 *
	 * @return bool True if the dependency exists in cache, false otherwise.
	 */
	public function resolved( $abstract ) {
		return key_exists( $abstract, $this->resolved );
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
	 * @return self
	 */
	public static function instance() {
		if (! isset( self::$instance ) ) {
			self::$instance = new static();
		}

		return self::$instance;
	}
}
