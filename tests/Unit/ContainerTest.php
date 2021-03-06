<?php

namespace Tests\Unit;

use StellarWP\Container\Container;
use StellarWP\Container\Exceptions\ContainerException;
use StellarWP\Container\Exceptions\NotFoundException;
use StellarWP\Container\Exceptions\RecursiveDependencyException;
use Tests\Stubs\Concrete;
use Tests\Stubs\ConcreteWithConstructorArgs;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * @testdox The StellarWP\Container\Container class
 *
 * @covers StellarWP\Container\Container
 */
class ContainerTest extends TestCase
{
    /**
     * Clear the Singleton instance between tests.
     *
     * @before
     */
    public function resetSingleton()
    {
        Concrete::reset();
    }

    /**
     * @testdox
     * @testdox extend() should overwrite existing definitions
     */
    public function extend_should_overwrite_existing_definitions()
    {
        $instance  = new \stdClass();
        $container = new Concrete();
        $untouched = $container->get(Concrete::VALID_KEY);

        $container->extend(Concrete::EXCEPTION_KEY, function () use ($instance) {
            return $instance;
        });

        $this->assertSame($instance, $container->get(Concrete::EXCEPTION_KEY));
        $this->assertSame($instance, $container->make(Concrete::EXCEPTION_KEY));
        $this->assertEquals(
            $untouched,
            $container->get(Concrete::VALID_KEY),
            'Only values that have been extended should be replaced.'
        );
    }

    /**
     * @test
     * @testdox extend() should be able to add new definitions
     */
    public function extend_should_be_able_to_add_new_definitions()
    {
        $instance  = new \stdClass();
        $container = new Concrete();

        $container->extend(Concrete::UNDEFINED_KEY, function () use ($instance) {
            return $instance;
        });

        $this->assertSame($instance, $container->make(Concrete::UNDEFINED_KEY));
    }

    /**
     * @test
     * @testdox extend() should be able to accept new instances directly
     */
    public function extend_should_be_able_to_accept_new_instances_directly()
    {
        $instance  = new \stdClass();
        $container = new Concrete();

        $container->extend(Concrete::UNDEFINED_KEY, $instance);

        $this->assertSame($instance, $container->make(Concrete::UNDEFINED_KEY));
    }

    /**
     * @test
     * @testdox extend() should prime the resolution cache if given a concrete instance
     */
    public function extend_Should_prime_the_resolution_cache_if_given_a_concrete_instance()
    {
        $instance  = new \stdClass();
        $container = new Concrete();

        $container->extend(Concrete::UNDEFINED_KEY, $instance);

        $this->assertTrue($container->hasResolved(Concrete::UNDEFINED_KEY));
    }

    /**
     * @test
     * @testdox extend() should flush the cached resolution of the given abstract
     */
    public function extend_should_flush_the_cached_resolution_of_the_given_abstract()
    {
        $container = new Concrete();
        $previous  = $container->get(Concrete::VALID_KEY);

        $container->extend(Concrete::VALID_KEY, function () {
            return (object) [
                uniqid(),
            ];
        });

        $new = $container->get(Concrete::VALID_KEY);
        $this->assertNotEquals($previous, $new);
        $this->assertSame($new, $container->get(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox extend() should be able to be used on the same abstract multiple times
     */
    public function extend_should_be_able_to_be_used_on_the_same_abstract_multiple_times()
    {
        $container = new Concrete();
        $instance1 = (object) [ uniqid() ];
        $instance2 = (object) [ uniqid() ];

        $container->extend(Concrete::VALID_KEY, $instance1);
        $this->assertSame($instance1, $container->get(Concrete::VALID_KEY));

        $container->extend(Concrete::VALID_KEY, $instance2);
        $this->assertSame($instance2, $container->get(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox extend() should only impact the called container instance
     */
    public function extend_should_only_impact_the_called_container_instance()
    {
        $container1 = new Concrete();
        $container2 = new Concrete();

        $container1->extend(Concrete::VALID_KEY, function () {
            return (object) [
                uniqid(),
            ];
        });

        $this->assertNotEquals(
            $container1->make(Concrete::VALID_KEY),
            $container2->make(Concrete::VALID_KEY),
            'Only $container2 should have been extended, so the values should be different.'
        );
    }

    /**
     * @test
     * @testdox forget() should remove the cached dependency
     */
    public function forget_should_remove_the_cached_dependency()
    {
        $container = new Concrete();
        $container->get(Concrete::VALID_KEY);

        $this->assertSame($container, $container->forget(Concrete::VALID_KEY));
        $this->assertArrayNotHasKey(Concrete::VALID_KEY, $this->getResolvedCache($container));
    }

    /**
     * @test
     * @testdox forget() should be able to remove multiple dependencies
     */
    public function forget_should_be_able_to_remove_multiple_dependencies()
    {
        $container = new Concrete();
        $container->get(Concrete::VALID_KEY);
        $container->get(Concrete::ALIAS_KEY);
        $container->get(Concrete::NULL_KEY);

        $container->forget(Concrete::VALID_KEY, Concrete::ALIAS_KEY);

        $resolved = $this->getResolvedCache($container);
        $this->assertArrayNotHasKey(Concrete::VALID_KEY, $resolved);
        $this->assertArrayNotHasKey(Concrete::ALIAS_KEY, $resolved);
        $this->assertArrayHasKey(Concrete::NULL_KEY, $resolved);
    }

    /**
     * @test
     * @testdox forget() should simply return if the given entry does not exist in cache
     */
    public function forget_should_simply_return_if_the_given_entry_does_not_exist()
    {
        $container = new Concrete();
        $this->assertEmpty($this->getResolvedCache($container), 'Expected an empty cache.');

        $this->assertSame($container, $container->forget(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox get() should retrieve the given abstract
     */
    public function get_should_retrieve_the_given_abstract()
    {
        $container = new Concrete();
        /** @var callable $callable */
        $callable  = $container->config()[ Concrete::VALID_KEY ];

        $this->assertEquals(
            $callable(),
            $container->get(Concrete::VALID_KEY),
            'The return value should be that of the callback from the configuration.'
        );
        $this->assertTrue($container->hasResolved(Concrete::VALID_KEY), 'The resolved abstract should be cached.');
    }

    /**
     * @test
     * @testdox get() should be able to return a new instance of an identifier with a null callable
     */
    public function get_should_be_able_to_return_a_new_instance_of_an_identifier_with_a_null_callable()
    {
        $container = new Concrete();
        $instance  = $container->get(Concrete::NULL_KEY);

        $this->assertInstanceOf(Concrete::NULL_KEY, $instance);
        $this->assertSame(
            $instance,
            $container->get(Concrete::NULL_KEY),
            'The instance should still be cached.'
        );
    }

    /**
     * @test
     * @testdox get() should recursively cache dependencies using get()
     */
    public function get_should_recursively_cache_dependencies_using_get()
    {
        $container = new Concrete();

        $container->get(Concrete::NESTED_GET_KEY);
        $this->assertTrue($container->hasResolved(Concrete::NESTED_GET_KEY));
        $this->assertTrue($container->hasResolved(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox get() should recursively cache dependencies using make
     */
    public function get_should_recursively_cache_dependencies_using_make()
    {
        $container = new Concrete();

        $container->get(Concrete::NESTED_MAKE_KEY);
        $this->assertTrue($container->hasResolved(Concrete::NESTED_MAKE_KEY));
        $this->assertTrue($container->hasResolved(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox get() should be able to recursively-resolve aliases through the container
     */
    public function get_should_be_able_to_recursively_resolve_aliases_through_the_container()
    {
        $container = new Concrete();
        $instance = (object) [ uniqid() ];

        $container->extend(Concrete::VALID_KEY, $instance);

        $this->assertSame($instance, $container->get(Concrete::ALIAS_KEY));
        $this->assertTrue(
            $container->hasResolved(Concrete::ALIAS_KEY),
            'The resolved alias should be cached.'
        );
        $this->assertTrue(
            $container->hasResolved(Concrete::VALID_KEY),
            'The underlying resource should also be cached.'
        );
    }

    /**
     * @test
     * @testdox get() should throw a NotFoundException if the given entry is undefined
     */
    public function get_should_throw_a_NotFoundException_if_the_given_entry_is_undefined()
    {
        $this->expectException(NotFoundException::class);
        (new Concrete())->get(Concrete::UNDEFINED_KEY);
    }

    /**
     * @test
     * @testdox get() should throw a ContainerException if an exception occurs during resolution
     */
    public function get_should_throw_a_ContainerException_if_an_exception_occurs()
    {
        $container = new Concrete();

        $this->expectException(ContainerException::class);
        $container->get(Concrete::EXCEPTION_KEY);
    }

    /**
     * @test
     * @testdox get() should throw a ContainerException if any recursive dependencies are undefined
     */
    public function get_should_throw_a_ContainerException_if_any_recursive_dependencies_are_undefined()
    {
        $this->expectException(ContainerException::class);
        (new Concrete())->get(Concrete::NESTED_UNDEFINED_KEY);
    }

    /**
     * @test
     * @testdox Subsequent calls to get() should return the same cached instance
     */
    public function subsequent_calls_to_get_should_return_the_same_instance()
    {
        $container = new Concrete();
        $first     = $container->get(Concrete::VALID_KEY);
        $second    = $container->get(Concrete::VALID_KEY);

        $this->assertSame($first, $second, 'Expected to receive the same instance on subsequent calls.');
    }

    /**
     * @test
     * @testdox has() should return true if a definition for the abstract exists
     */
    public function has_should_return_true_if_a_definition_for_the_abstract_exists()
    {
        $this->assertTrue((new Concrete())->has(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox has() should return false if no definition for the abstract exixsts
     */
    public function has_should_return_false_if_no_definition_for_the_abstract_exists()
    {
        $this->assertFalse((new Concrete())->has(Concrete::UNDEFINED_KEY));
    }

    /**
     * @test
     * @testdox make() should create a new instance of the given abstract
     */
    public function make_should_create_a_new_instance_of_the_given_abstract()
    {
        $container = new Concrete();
        $first     = $container->make(Concrete::VALID_KEY);
        $second    = $container->make(Concrete::VALID_KEY);

        $this->assertFalse($container->hasResolved(Concrete::VALID_KEY), 'make() should not cache resolutions.');
        $this->assertEquals($first, $second, 'Expected two instances of the same class.');
        $this->assertNotSame($first, $second, 'Two separate instances should have been returned.');
    }

    /**
     * @test
     * @testdox make() should be able to return a new instance of an abstract with a null callable
     */
    public function make_should_be_able_to_return_a_new_instance_of_an_abstract_with_a_null_callable()
    {
        $container = new Concrete();

        $this->assertInstanceOf(Concrete::NULL_KEY, $container->make(Concrete::NULL_KEY));
        $this->assertFalse($container->hasResolved(Concrete::NULL_KEY));
    }

    /**
     * @test
     * @testdox make() should return the provided resolved instance, when present
     */
    public function make_should_return_the_provided_resolved_instance_when_present()
    {
        $container = new Concrete();
        $instance  = new \stdClass();

        $container->extend(Concrete::INSTANCE_KEY, $instance);

        $this->assertSame($instance, $container->make(Concrete::INSTANCE_KEY));
    }

    /**
     * @test
     * @testdox make() should respect the use of get() within resolution callbacks
     */
    public function make_should_respect_the_use_of_get_within_resolution_callbacks()
    {
        $container = new Concrete();

        $container->make(Concrete::NESTED_GET_KEY);
        $this->assertFalse($container->hasResolved(Concrete::NESTED_GET_KEY));
        $this->assertTrue(
            $container->hasResolved(Concrete::VALID_KEY),
            'The callback used get() to resolve Concrete::VALID_KEY, so it should be cached'
        );
    }

    /**
     * @test
     * @testdox make() should be able to recursively-resolve aliases through the container
     */
    public function make_should_be_able_to_recursively_resolve_aliases_through_the_container()
    {
        $container = new Concrete();
        $instance = (object) [ uniqid() ];

        $container->extend(Concrete::VALID_KEY, function () use ($instance) {
            return $instance;
        });

        $this->assertSame($instance, $container->make(Concrete::ALIAS_KEY));
        $this->assertFalse(
            $container->hasResolved(Concrete::ALIAS_KEY),
            'The ALIAS_KEY resolution should not have been cached.'
        );
        $this->assertFalse(
            $container->hasResolved(Concrete::VALID_KEY),
            'The underlying VALID_KEY resolution should not have been cached.'
        );
    }

    /**
     * @test
     * @testdox Calling make() should not overwrite cached resolutions
     */
    public function calling_make_should_not_overwrite_cached_resolutions()
    {
        $container = new Concrete();
        $cached    = $container->get(Concrete::VALID_KEY);
        $uncached  = $container->make(Concrete::VALID_KEY);

        $this->assertNotSame($cached, $uncached);
        $this->assertSame($cached, $container->get(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox Calling make() should not overwrite cached, nested resolutions
     */
    public function calling_make_should_not_overwrite_nested_cached_resolutions()
    {
        $container = new Concrete();
        $valid     = $container->get(Concrete::VALID_KEY);

        $instance = $container->get(Concrete::NESTED_MAKE_KEY);
        $this->assertSame(
            $valid,
            $instance->offsetGet('prop'),
            'The resolved value should have been returned from cache.'
        );
        $this->assertSame(
            $valid,
            $container->get(Concrete::VALID_KEY),
            'The cache should not have been overwritten'
        );
    }

    /**
     * @test
     * @testdox make() should throw a NotFoundException if the given abstract is undefined
     */
    public function make_should_throw_a_NotFoundException_if_the_given_abstract_is_undefined()
    {
        $this->expectException(NotFoundException::class);
        ( new Concrete() )->make(Concrete::UNDEFINED_KEY);
    }

    /**
     * @test
     * @testdox make() should throw a ContainerException if an exception occurs
     */
    public function make_should_throw_a_ContainerException_if_an_exception_occurs()
    {
        $container = new Concrete();

        $this->expectException(ContainerException::class);
        $container->make(Concrete::EXCEPTION_KEY);
    }

    /**
     * @test
     * @testdox make() should throw a ContainerException if unable to process a definition
     */
    public function make_should_throw_a_ContainerException_if_unable_to_process_a_definition()
    {
        $container = new Concrete();

        $this->expectException(ContainerException::class);
        $container->make(Concrete::INVALID_KEY);
    }

    /**
     * @test
     * @testdox make() should throw a RecursiveDependencyException if a recursive loop is detected
     * @runInSeparateProcess
     */
    public function make_should_throw_a_RecursiveDependencyException_if_a_recursive_loop_is_detected()
    {
        $container = new Concrete();

        $this->expectException(RecursiveDependencyException::class);
        $container->make(Concrete::RECURSIVE_KEY);
    }

    /**
     * @test
     * @testdox hasResolved() should return true if the container has resolved the given abstract
     */
    public function hasResolved_should_return_true_if_the_container_has_resolved_the_given_abstract()
    {
        $container = new Concrete();
        $container->get(Concrete::VALID_KEY);

        $this->assertArrayHasKey(Concrete::VALID_KEY, $this->getResolvedCache($container));
        $this->assertTrue($container->hasResolved(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox hasResolved() should return false if the container has not yet resolved the given abstract
     */
    public function hasResolved_should_return_false_if_the_container_has_not_resolved_the_given_abstract()
    {
        $container = new Concrete();

        $this->assertArrayNotHasKey(Concrete::VALID_KEY, $this->getResolvedCache($container));
        $this->assertFalse($container->hasResolved(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox hasResolved() should return false if the given abstract is undefined
     */
    public function hasResolved_should_return_false_if_the_given_abstract_is_undefined()
    {
        $container = new Concrete();

        $this->assertArrayNotHasKey(Concrete::UNDEFINED_KEY, $this->getResolvedCache($container));
        $this->assertFalse($container->hasResolved(Concrete::UNDEFINED_KEY));
    }

    /**
     * @test
     * @testdox restore() should remove the extension for a given abstract
     */
    public function restore_should_remove_the_extension_for_a_given_abstract()
    {
        $container = new Concrete();
        $original  = $container->get(Concrete::VALID_KEY);

        $container->extend(Concrete::VALID_KEY, function () {
            return (object) [
                uniqid(),
            ];
        });

        $extended = $container->get(Concrete::VALID_KEY);

        $this->assertSame($container, $container->restore(Concrete::VALID_KEY));
        $this->assertNotEquals($extended, $container->get(Concrete::VALID_KEY));
        $this->assertEquals($original, $container->get(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox restore() should just return if the given extension does not exist
     */
    public function restore_should_just_return_if_the_given_extension_does_not_exist()
    {
        $container = new Concrete();

        $this->assertSame($container, $container->restore(Concrete::VALID_KEY));
    }

    /**
     * @test
     * @testdox getInstance() should return a Singleton instance
     */
    public function getInstance_should_return_a_Singleton_instance()
    {
        $instance = Concrete::getInstance();

        $this->assertSame($instance, Concrete::getInstance(), 'The same instance should have been returned.');
    }

    /**
     * @test
     * @testdox Calling getInstance() with a concrete instance should set the Singleton value
     */
    public function calling_getInstance_with_a_concrete_instance_should_set_the_Singleton_value()
    {
        $container = new Concrete();
        $instance  = Concrete::getInstance($container);

        $this->assertSame($container, $instance);
        $this->assertSame($container, Concrete::getInstance(), 'Subsequent calls should use $container.');
    }

    /**
     * @test
     * @testdox Calling getInstance() with a concrete instance should replace the current Singleton
     */
    public function calling_getInstance_with_a_concrete_instance_should_replace_the_current_Singleton()
    {
        $container = new Concrete();
        $instance  = Concrete::getInstance();

        $this->assertNotSame($instance, Container::getInstance($container));
        $this->assertSame($container, Container::getInstance());
    }

    /**
     * @test
     * @testdox getInstance() should throw an excpetion if it cannot safely call `new static()`
     */
    public function getInstance_should_throw_an_exception_if_it_cannot_safely_call_new_static()
    {
        $this->expectException(ContainerException::class);
        ConcreteWithConstructorArgs::getInstance();
    }

    /**
     * @test
     * @testdox getInstance() should not interfere with regular class instantiations
     */
    public function getInstance_should_not_interfere_with_regular_instantiations()
    {
        $instance = Concrete::getInstance();

        $this->assertNotSame($instance, new Concrete());
    }

    /**
     * @test
     * @testdox reset() should clear the Singleton instance
     */
    public function reset_should_clear_the_Singleton_instance()
    {
        $instance = Concrete::getInstance();
        Concrete::reset();

        $this->assertNotSame($instance, Concrete::getInstance());
    }

    /**
     * @test
     * @testdox reset() should just return if there is no Singleton instance
     */
    public function reset_should_just_return_if_there_is_no_Singleton_instance()
    {
        $prop = new \ReflectionProperty(Concrete::class, 'instance');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue());

        Concrete::reset();
    }

    /**
     * Helper method to get the contents of the protected Container::$resolved property.
     *
     * @param Container $container The container instance.
     *
     * @return Array<string,mixed> The value of Container::$resolved.
     */
    protected function getResolvedCache(Container $container)
    {
        $property = new \ReflectionProperty($container, 'resolved');
        $property->setAccessible(true);

        return (array) $property->getValue($container);
    }
}
