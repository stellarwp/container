<?php

namespace Tests\Unit;

use StellarWP\Container\Container;
use StellarWP\Container\Exceptions\ContainerException;
use StellarWP\Container\Exceptions\NotFoundException;
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
	 * @before
	 */
	protected function createContainerInstance() {
		/** @var \PHPUnit\Framework\MockObject\MockObject&Container $container */
		$this->container = $this->getMockForAbstractClass( Container::class );
		$this->container->expects( $this->any() )
			->method( 'config' )
			->willReturn( [
				'some-key' => function () {
					return new \stdClass();
				},
			] );
	}

	/**
	 * @test
	 */
	public function forget_should_remove_the_cached_resolution() {
		$this->container->get('some-key');

		$this->assertSame($this->container, $this->container->forget('some-key'));
		$this->assertArrayNotHasKey( 'some-key', $this->getResolvedCache( $this->container ) );
	}

	/**
	 * @test
	 */
	public function forget_should_simply_return_if_the_given_entry_does_not_exist() {
		$this->assertEmpty( $this->getResolvedCache( $this->container ), 'Expected an empty cache.' );

		$this->assertSame( $this->container, $this->container->forget( 'some-key' ) );
	}

	/**
	 * @test
	 */
	public function get_should_retrieve_the_given_identifier() {
		$instance  = new \stdClass();

		/** @var \PHPUnit\Framework\MockObject\MockObject&Container $container */
		$container = $this->getMockForAbstractClass( Container::class );
		$container->method( 'config' )
			->willReturn( [
				'some-key' => function () use ( $instance ) {
					return $instance;
				},
			] );

		$this->assertSame( $instance, $container->get( 'some-key' ) );
	}

	/**
	 * @test
	 */
	public function get_should_be_able_to_return_a_new_instance_of_an_identifier_with_a_null_callable() {
		/** @var \PHPUnit\Framework\MockObject\MockObject&Container $container */
		$container = $this->getMockForAbstractClass( Container::class );
		$container->method( 'config' )
			->willReturn( [
				\DateTime::class => null,
			] );

		$this->assertInstanceOf( \DateTime::class, $container->get( \DateTime::class ) );
	}

	/**
	 * @test
	 */
	public function get_should_throw_a_NotFoundException_if_the_given_entry_is_undefined() {
		$this->expectException( NotFoundException::class );
		$this->container->get( 'some-other-key' );
	}

	/**
	 * @test
	 */
	public function get_should_throw_a_ContainerException_if_an_exception_occurs() {
		/** @var \PHPUnit\Framework\MockObject\MockObject&Container $container */
		$container = $this->getMockForAbstractClass( Container::class );
		$container->method( 'config' )
			->willReturn( [
				'some-key' => function () {
					throw new \Exception( 'Something went wrong!' );
				},
			] );

		$this->expectException( ContainerException::class );
		$container->get( 'some-key' );
	}

	/**
	 * @test
	 */
	public function subsequent_calls_to_get_should_return_the_same_instance() {
		$this->container->expects( $this->any() )
			->method( 'config' )
			->willReturn( [
				'some-key' => function () {
					return new \stdClass();
				},
			] );

		$first  = $this->container->get( 'some-key' );
		$second = $this->container->get( 'some-key' );

		$this->assertSame( $first, $second, 'Expected to receive the same instance on subsequent calls.' );
	}

	/**
	 * @test
	 */
	public function has_should_return_true_if_a_definition_for_the_identifier_exists() {
		$this->assertTrue( $this->container->has( 'some-key' ) );
	}

	/**
	 * @test
	 */
	public function has_should_return_false_if_no_definition_for_the_identifier_exists() {
		$this->assertFalse( $this->container->has( 'some-other-key' ) );
	}

	/**
	 * @test
	 */
	public function make_should_create_a_new_instance_of_the_given_identifier() {
		$first  = $this->container->make( 'some-key' );
		$second = $this->container->make( 'some-key' );

		$this->assertEquals( $first, $second, 'Expected two instances of the same class.' );
		$this->assertNotSame( $first, $second, 'Two separate instances should have been returned.' );
	}

	/**
	 * @test
	 */
	public function make_should_be_able_to_return_a_new_instance_of_an_identifier_with_a_null_callable() {
		/** @var \PHPUnit\Framework\MockObject\MockObject&Container $container */
		$container = $this->getMockForAbstractClass( Container::class );
		$container->method( 'config' )
			->willReturn( [
				\DateTime::class => null,
			] );

		$this->assertInstanceOf( \DateTime::class, $container->make( \DateTime::class ) );
	}

	/**
	 * @test
	 */
	public function calling_make_should_not_overwrite_cached_resolutions() {
		$cached = $this->container->get( 'some-key' );
		$uncached = $this->container->make( 'some-key' );

		$this->assertNotSame( $cached, $uncached );
		$this->assertSame( $cached, $this->container->get( 'some-key' ) );
	}

	/**
	 * @test
	 */
	public function make_should_throw_a_NotFoundException_if_the_given_entry_is_undefined() {
		$this->expectException( NotFoundException::class );
		$this->container->make( 'some-other-key' );
	}

	/**
	 * @test
	 */
	public function make_should_throw_a_ContainerException_if_an_exception_occurs() {
		/** @var \PHPUnit\Framework\MockObject\MockObject&Container $container */
		$container = $this->getMockForAbstractClass( Container::class );
		$container->method( 'config' )
			->willReturn( [
				'some-key' => function () {
					throw new \Exception( 'Something went wrong!' );
				},
			] );

		$this->expectException( ContainerException::class );
		$container->make( 'some-key' );
	}

	/**
	 * @test
	 */
	public function resolved_should_return_true_if_the_container_has_resolved_the_given_identifier() {
		$this->container->get( 'some-key' );

		$this->assertArrayHasKey( 'some-key', $this->getResolvedCache( $this->container ) );
		$this->assertTrue( $this->container->resolved( 'some-key' ) );
	}

	/**
	 * @test
	 */
	public function resolved_should_return_false_if_the_container_has_not_resolved_the_given_identifier() {
		$this->assertArrayNotHasKey( 'some-key', $this->getResolvedCache( $this->container ) );
		$this->assertFalse( $this->container->resolved( 'some-key' ) );
	}

	/**
	 * @test
	 */
	public function resolved_should_return_false_if_the_given_identifier_is_undefined() {
		$this->assertArrayNotHasKey( 'some-other-key', $this->getResolvedCache( $this->container ) );
		$this->assertFalse( $this->container->resolved( 'some-other-key' ) );
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
