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
	 * @var Array<string,mixed>
	 */
	protected $resolved = [];

	/**
	 * The cached, Singleton instance of the container.
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Retrieve a mapping of identifiers to callables.
	 *
	 * When an identifier is requested through the container, the container will find the given
	 * dependency in this array, execute the callable, and return the result.
	 *
	 * @return Array<string,callable> A mapping of identifiers to callables.
	 */
	abstract public function config();

	/**
	 * Remove the cached resolution for the given identifier.
	 *
	 * @param string $id The identifier to forget.
	 *
	 * @return self
	 */
	public function forget( $id ) {
		unset( $this->resolved[ $id ] );

		return $this;
	}

	/**
	 * Resolve the given identifier through the container and return it.
	 *
	 * Results will be cached, enabling subsequent results to return the same instance.
	 *
	 * @throws NotFoundException  If no entry was found for this identifier.
	 * @throws ContainerException Error while retrieving the entry.
	 *
	 * @param string $id The dependency's identifier.
	 *
	 * @return mixed The resolved dependency.
	 */
	public function get( $id ) {
		if ( ! key_exists( $id, $this->resolved ) ) {
			$this->resolved[ $id ] = $this->make( $id );
		}

		return $this->resolved[ $id ];
	}

	/**
	 * Determine whether or not an entry for the given identifier exists in the container.
	 *
	 * Please note that just because an entry exists for an identifier does not mean that dependency
	 * can be built without errors. However, a truthy response from has() should guarantee that this
	 * dependency will not produce a NotFoundException.
	 *
	 * @param string $id The identifier to look for.
	 *
	 * @return bool True if the container knows how to resolve the given identifier, false otherwise.
	 */
	public function has( $id ) {
		$config = $this->config();

		return key_exists( $id, $config );
	}

	/**
	 * Resolve the given identifier through the container and return it without caching.
	 *
	 * Unlike get(), a new, uncached instance will be created upon each call.
	 *
	 * @throws NotFoundException  If no entry was found for this identifier.
	 * @throws ContainerException Error while retrieving the entry.
	 *
	 * @param string $id The dependency's identifier.
	 *
	 * @return mixed The resolved dependency.
	 */
	public function make( $id ) {
		$config = $this->config();

		if ( ! key_exists( $id, $config ) ) {
			throw new NotFoundException( sprintf( 'No container definition could be found for "%s".', $id ) );
		}

		try {
			if ( null === $config[ $id ] ) {
				return new $id();
			}

			$resolved = $config[ $id ]( $this );
		} catch ( \Exception $e ) {
			throw new ContainerException(
				sprintf( 'An error occured building "%s": %s', $id, $e->getMessage() ),
				$e->getCode(),
				$e
			);
		}

		return $resolved;
	}

	/**
	 * Check whether or not the given identifier has already been resolved.
	 *
	 * @param string $id The dependency's identifier.
	 *
	 * @return bool True if the dependency exists in cache, false otherwise.
	 */
	public function resolved( $id ) {
		return key_exists( $id, $this->resolved );
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
