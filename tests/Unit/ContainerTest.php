<?php

namespace Tests\Unit;

use StellarWP\Container\Container;
use StellarWP\Container\Exceptions\ContainerException;
use StellarWP\Container\Exceptions\NotFoundException;
use Tests\Stubs\Concrete;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * @covers StellarWP\Container\Container
 */
class ContainerTest extends TestCase {

	/**
	 * The mocked, concrete container instance.
	 *
	 * @var \PHPUnit\Framework\MockObject\MockObject&Container
	 */
	protected $container;

	/**
	 * @test
	 */
	public function forget_should_remove_the_cached_resolution() {
		$container = new Concrete();
		$container->get(Concrete::VALID_KEY);

		$this->assertSame( $container, $container->forget( Concrete::VALID_KEY ) );
		$this->assertArrayNotHasKey( Concrete::VALID_KEY, $this->getResolvedCache( $container ) );
	}

	/**
	 * @test
	 */
	public function forget_should_simply_return_if_the_given_entry_does_not_exist() {
		$container = new Concrete();
		$this->assertEmpty( $this->getResolvedCache( $container ), 'Expected an empty cache.' );

		$this->assertSame( $container, $container->forget( Concrete::VALID_KEY ) );
	}

	/**
	 * @test
	 */
	public function get_should_retrieve_the_given_identifier() {
		$container = new Concrete();

		$this->assertEquals(
			$container->config()[ Concrete::VALID_KEY ](),
			$container->get( Concrete::VALID_KEY ),
			'The return value should be that of the callback from the configuration.'
		);
	}

	/**
	 * @test
	 */
	public function get_should_be_able_to_return_a_new_instance_of_an_identifier_with_a_null_callable() {
		$this->assertInstanceOf( Concrete::NULL_KEY, ( new Concrete() )->get( Concrete::NULL_KEY ) );
	}

	/**
	 * @test
	 */
	public function get_should_throw_a_NotFoundException_if_the_given_entry_is_undefined() {
		$this->expectException( NotFoundException::class );
		( new Concrete() )->get( Concrete::INVALID_KEY );
	}

	/**
	 * @test
	 */
	public function get_should_throw_a_ContainerException_if_an_exception_occurs() {
		$container = new Concrete();

		$this->expectException( ContainerException::class );
		$container->get( Concrete::EXCEPTION_KEY );
	}

	/**
	 * @test
	 */
	public function subsequent_calls_to_get_should_return_the_same_instance() {
		$container = new Concrete();
		$first     = $container->get( Concrete::VALID_KEY );
		$second    = $container->get( Concrete::VALID_KEY );

		$this->assertSame( $first, $second, 'Expected to receive the same instance on subsequent calls.' );
	}

	/**
	 * @test
	 */
	public function has_should_return_true_if_a_definition_for_the_identifier_exists() {
		$this->assertTrue( ( new Concrete() )->has( Concrete::VALID_KEY ) );
	}

	/**
	 * @test
	 */
	public function has_should_return_false_if_no_definition_for_the_identifier_exists() {
		$this->assertFalse( ( new Concrete() )->has( Concrete::INVALID_KEY ) );
	}

	/**
	 * @test
	 */
	public function make_should_create_a_new_instance_of_the_given_identifier() {
		$container = new Concrete();
		$first     = $container->make( Concrete::VALID_KEY );
		$second    = $container->make( Concrete::VALID_KEY );

		$this->assertEquals( $first, $second, 'Expected two instances of the same class.' );
		$this->assertNotSame( $first, $second, 'Two separate instances should have been returned.' );
	}

	/**
	 * @test
	 */
	public function make_should_be_able_to_return_a_new_instance_of_an_identifier_with_a_null_callable() {
		$this->assertInstanceOf( Concrete::NULL_KEY, ( new Concrete() )->make( Concrete::NULL_KEY ) );
	}

	/**
	 * @test
	 */
	public function calling_make_should_not_overwrite_cached_resolutions() {
		$container = new Concrete();
		$cached    = $container->get( Concrete::VALID_KEY );
		$uncached  = $container->make( Concrete::VALID_KEY );

		$this->assertNotSame( $cached, $uncached );
		$this->assertSame( $cached, $container->get( Concrete::VALID_KEY ) );
	}

	/**
	 * @test
	 */
	public function make_should_throw_a_NotFoundException_if_the_given_entry_is_undefined() {
		$this->expectException( NotFoundException::class );
		( new Concrete() )->make( Concrete::INVALID_KEY );
	}

	/**
	 * @test
	 */
	public function make_should_throw_a_ContainerException_if_an_exception_occurs() {
		$container = new Concrete();

		$this->expectException( ContainerException::class );
		$container->make( Concrete::EXCEPTION_KEY );
	}

	/**
	 * @test
	 */
	public function resolved_should_return_true_if_the_container_has_resolved_the_given_identifier() {
		$container = new Concrete();
		$container->get( Concrete::VALID_KEY );

		$this->assertArrayHasKey( Concrete::VALID_KEY, $this->getResolvedCache( $container ) );
		$this->assertTrue( $container->resolved( Concrete::VALID_KEY ) );
	}

	/**
	 * @test
	 */
	public function resolved_should_return_false_if_the_container_has_not_resolved_the_given_identifier() {
		$container = new Concrete();

		$this->assertArrayNotHasKey( Concrete::VALID_KEY, $this->getResolvedCache( $container ) );
		$this->assertFalse( $container->resolved( Concrete::VALID_KEY ) );
	}

	/**
	 * @test
	 */
	public function resolved_should_return_false_if_the_given_identifier_is_undefined() {
		$container = new Concrete();

		$this->assertArrayNotHasKey( Concrete::INVALID_KEY, $this->getResolvedCache( $container ) );
		$this->assertFalse( $container->resolved( Concrete::INVALID_KEY ) );
	}

	/**
	 * @test
	 */
	public function instance_should_return_a_Singleton_instance() {
		$instance = Concrete::instance();

		$this->assertSame( $instance, Concrete::instance(), 'The same instance should have been returned.' );
	}

	/**
	 * @test
	 */
	public function instance_should_not_interfere_with_regular_instantiations() {
		$instance = Concrete::instance();

		$this->assertNotSame( $instance, new Concrete() );
	}

	/**
	 * Helper method to get the contents of the protected Container::$resolved property.
	 *
	 * @param Container $container The container instance.
	 *
	 * @return Array<string,mixed> The value of Container::$resolved.
	 */
	protected function getResolvedCache( Container $container ) {
		$property = new \ReflectionProperty( $container, 'resolved' );
		$property->setAccessible( true );

		return $property->getValue( $container );
	}
}
