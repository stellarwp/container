# StellarWP Dependency Injection (DI) Container

[![CI Pipeline](https://github.com/stellarwp/container/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/stellarwp/container/actions/workflows/continuous-integration.yml)

This library contains [a PSR-11-compatible Dependency Injection (DI) container](https://www.php-fig.org/psr/psr-11/) to aid in resolving dependencies as needed throughout various applications.

## What is Dependency Injection?

In its simplest terms, Dependency Injection is providing dependencies to an object rather than making the object try to create/retrieve them.

For instance, imagine that we're building a plugin that contains different "modules", each of which might receive a global `Settings` object.

With dependency injection, our module definition might look like this:

```php
namespace Acme\SomePlugin;

class SomeModule extends Module
{

  /**
   * @var Settings
   */
  protected $settings;

  /**
   * @param Settings $settings
   */
  public function __construct(Settings $settings)
  {
    $this->settings = $settings;
  }
}
```

By injecting the `Settings` object, we're able to create a single instance of the object and more-easily inject [test doubles](https://phpunit.readthedocs.io/en/9.5/test-doubles.html) in our tests.

Now, compare this to a version of the same class that _doesn't_ use dependency injection:

```php
namespace Acme\SomePlugin;

class SomeModule extends Module
{

  /**
   * @var Settings
   */
  protected $settings;

  public function __construct()
  {
    $this->settings = new Settings();
  }
}
```

Under this model, each instance of the module will be responsible for instantiating their own instance of the `Settings` object, and we lack the ability to inject test doubles.

Furthermore, if the `Settings` class changes its constructor method signature, we'd have to update calls to `new Settings()` throughout the application.

This is one of the major benefits of a DI container: we can define how an object gets constructed in one place, and then recursively resolve dependencies.


### Dependency Injection vs Service Location

It's worth mentioning that the container is designed to be used for Dependency Injection, **not** as a Service Locater.

What's a Service Locater? Imagine instead of injecting the `Settings` object into our integrations, we instead injected the entire `Container` object. Instead of giving the class the tools it needs to do its job, we're instead throwing the entire application at it and saying "here, you figure it out."

[The PSR-11 meta documentation has a good breakdown of these patterns](https://www.php-fig.org/psr/psr-11/meta/#4-recommended-usage-container-psr-and-the-service-locator).

## Installation

It's recommended that you install the DI container as a project dependency via [Composer](https://getcomposer.org):

```sh
$ composer require stellarwp/container
```

Next, create a new class within your project that extends the `StellarWP\Container\Container` class:

```php
<?php

namespace Acme\SomePlugin;

use StellarWP\Container\Container as BaseContainer;

class Container extends BaseContainer
{
    /**
	 * Retrieve a mapping of identifiers to callables.
	 *
	 * When an identifier is requested through the container, the container will find the given
	 * dependency in this array, execute the callable, and return the result.
	 *
	 * @return Array<string,callable> A mapping of identifiers to callables.
	 */
	public function config()
    {
        return [
            // ...
        ];
    }
}
```

You're free to customize anything you'd like, but there's one abstract method that needs filled in: `config()`.

### Defining the config() method

A key part of any DI container is the mapping between abstract dependencies (for example, interfaces and/or class names) and concrete instances; in the StellarWP container, this is defined via the `StellarWP\Container\Container::config()` method.

The `config()` method should return a one-dimensional, associative array mapping abstract identifiers to callables that will produce concrete instances.

A very simple example might look something like this: imagine we have an [interface](https://www.php.net/manual/en/language.oop5.interfaces.php), `SandwichInterface`, that describes how to make a sandwich.

Now, let's assume we have an implementation of this interface, `PBandJ`, that defines a Peanut Butter and Jelly (PB&J) sandwich. As you might have guessed, our `PBandJ` class has three dependencies: bread, peanut butter, and jelly. The definition for this class might look something like this:

```php
namespace Acme\SomePlugin;

class PBandJ implements SandwichInterface
{

    public function __construct(Bread $bread,PeanutButter $pb, Jelly $jelly)
    {
        // ...
    }
}
```

Now, let's assume that any time we want a sandwich throughout our application, it should be a PB&J. Within our container's `config()` method, we'll define an anonymous function that will return an instance of `PBandJ`, bound to the `SandwichInterface`:

```php
use Acme\SomePlugin\Bread;
use Acme\SomePlugin\Jelly;
use Acme\SomePlugin\PeanutButter;
use Acme\SomePlugin\SandwichInterface;

public function config()
{
    return [
        Bread::class        => null,
        Jelly::class        => null,
        PeanutButter::class => null,

        // In order to construct a PBandJ, we need both PeanutButter and Jelly.
        SandwichInterface::class => function ($container) {
            return new PBandJ(
                $container->make(Bread::class),
                $container->make(PeanutButter::class),
                $container->make(Jelly::class)
            );
        },
    ];
}
```

Whenever we request a sandwich from the DI container, we'll now get the PB&J we've defined above:

```php
$sandwich = (new Container())->get(SandwichInterface::class);

var_dump($sandwich instanceof PBandJ);
# => bool(true)
```

If we wanted to define multiple types of sandwiches, we could also use `PBandJ` as the abstract (array key), then request it via `$container->get(PBandJ::class)`.

> #### ⚠️  A note on abstract identifiers
> While it's probably most-useful to use a class or interface name as the abstract identifier, this _can_ be any string (e.g. "peanut_butter").

#### Recursive definitions

In our PB&J example above, notice that the callback for `SandwichInterface` was given the `$container` parameter: this is the current container instance, letting us recursively define our dependencies.

For example, if we were using homemade bread, we might have an implementation for `Bread` defined that accepts `Flour`, `Yeast`, `Water`, and `Salt` as dependencies. We would define `Bread` in the config with these dependencies and, upon calling `$container->make(Bread::class)` within the definition for `SandwichInterface` the container would automatically resolve `Bread` before injecting it.

Please note that a `StellarWP\Container\Exceptions\RecursiveDependencyException` will be thrown if a recursive loop is detected when resolving dependencies (e.g. `DrinkingCoffee` depends on `MakingCoffee`, which depends on `BeingFunctionalInTheMorning`, which depends on `DrinkingCoffee`).

#### Aliases

Sometimes it's helpful to add one container definition to point to another, especially when building base containers meant to be extended or introducing a container to an existing codebase.

The StellarWP container supports alias definitions where the "concrete" value in the configuration array points to another abstract:

```php
[
    Hero::class   => Hoagie::class,
    Hoagie::class => Sub::class,
    Sub::class    => function () {
        return new ItalianSubSandwich();
    },
    // ...
]

$hero   = $container->get(Hero::class);
$hoagie = $container->get(Hoagie::class);
$sub    = $container->get(Sub::class);

var_dump(($hero === $hoagie) && ($hoagie === $sub));
# => bool(true)
```

> #### ⚡️  Performance recommendation
> For the best performance, it's recommended that you try to settle on a single abstract rather than relying on aliases, but they're there if you need them.

## Using the DI container

Once you've defined your container's configuration, it's time to start using it in your project!

First, you'll need to construct an instance of your container:

```php
use Acme\SomePlugin\Container;

$container = new Container();
```

Now that we have our container instance, let's try resolving some dependencies. In order to do so, we can use one of two methods: `get()` or `make()`.

The `get()` method will resolve the dependency and cache the result, so subsequent calls for that same dependency will return the same value:

```php
$first  = $container->get(SomeAbstract::class);
$second = $container->get(SomeAbstract::class);

var_dump($first === $second);
# => bool(true)
```

The `make()` method, on the other hand, will return a fresh copy of the dependency each time:

```php
$first  = $container->make(SomeAbstract::class);
$second = $container->make(SomeAbstract::class);

var_dump($first === $second);
# => bool(false)
```

It's worth noting, however, that calling `get()` on a dependency will *always* cache it (and any recursive dependencies), while `make()` will only cache recursive dependencies if resolved via `get()`. Imagine our container contains the following definitions:

```php
[
    Lunch::class             => function ($container) {
        return new BoxedLunch(
            $container->make(Sandwich::class),
            $container->get(Fruit::class)
        );
    },
    SandwichInterface::class => function ($container) {
        return $container->make(PBandJ::class);
    },
    Fruit::class             => function ($container) {
        return $container->make(Apple::class);
    },

    // ...and more!
]
```

When `Lunch` is resolved through the container, the caching behavior will be different based on whether `make()` or `get()` is used within the definitions:

**Using `$container->get()`:**

```php
$container = new Container();
$container->get(Lunch::class);

var_dump($container->hasResolved(Lunch::class));
# => bool(true)

var_dump($container->hasResolved(SandwichInterface::class));
# => bool(true)

var_dump($container->hasResolved(PBandJ::class));
# => bool(true)

var_dump($container->hasResolved(Fruit::class));
# => bool(true)

var_dump($container->hasResolved(Apple::class));
# => bool(true)
```

**Using `$container->make()`:**

```php
$container = new Container();
$container->make(Lunch::class);

var_dump($container->hasResolved(Lunch::class));
# => bool(false)

var_dump($container->hasResolved(SandwichInterface::class));
# => bool(false)

var_dump($container->hasResolved(PBandJ::class));
# => bool(false)

var_dump($container->hasResolved(Fruit::class));
# => bool(true)

var_dump($container->hasResolved(Apple::class));
# => bool(true)
```

As you can see, the `Fruit` and `Apple` definitions will always be cached, as they use `get()` within the definition for `Lunch`. In some situations this may be desirable, but generally it's best to use `$container->make()` in your resolutions.

If the container is asked for a dependency for which it doesn't have a definition, it will throw a `StellarWP\Container\Exceptions\NotFoundException`. In order to avoid this, you may see if a definition exists via `$container->has(SomeAbstract::class)`. You may also see whether or not the container has a cached resolution with `$container->resolved(SomeAbstract::class)`.

#### Clearing cached dependencies

If you need to clear the cache for a particular dependency, you may call `$container->forget(SomeAbstract::class)` and subsequent calls to `$container->get()` will re-generate the cached value.

It's important to note that calling `$container->forget()` on a dependency will **not** recursively remove its sub-dependencies, e.g.:

```php
$container = new Container();
$container->get(Lunch::class);
$container->forget(Lunch::class);

var_dump($container->hasResolved(Lunch::class));
# => bool(false)

var_dump($container->hasResolved(SandwichInterface::class));
# => bool(true)
```

If you need to forget multiple dependencies, you may pass them as separate arguments to `$container->forget()`:

```php
$container->forget(Lunch::class, SandwichInterface::class);
```

### Using the container as a Singleton

If you need to be able to access the container from within dependencies (not uncommon when introducing a DI container into an existing codebase), you may use the static `Container::getInstance()` to return a Singleton version of the container (meaning each call to `Container::getInstance()` will return the same instance):

```php
use Acme\SomePlugin\Container;

Container::getInstance()->get(SomeAbstract::class);
```

However, this could result in two separate container instances: the Singleton and the instance created via `new Container()`:

```php
use Acme\SomePlugin\Container;

$container = new Container();

// Elsewhere.
$singleton = Container::getInstance();

var_dump($singleton === $container);
# => bool(false)
```

To reduce this duplication, the `getInstance()` method accepts an optional `$instance` argument that overrides the container's internal `$instance` property:

```php
use Acme\SomePlugin\Container;

$container = new Container();

// Elsewhere.
$singleton = Container::getInstance($container);

var_dump($singleton === $container);
# => bool(true)
```

### Extending definitions

The use of a DI container also makes testing easier, especially when we leverage the `extend()` method.

This method lets us override the DI container's definition for a given abstract, letting us inject test doubles and/or known values.

For example, pretend we have a `ServiceSdk` dependency, which is a third-party <abbr title="Software Development Kit">SDK</abbr> for interacting with some service. We don't necessarily want our automated tests to actually hit the service (which can make our tests slow and brittle), so we might replace our definition for the service in our tests:

```php
use Acme\SomePlugin\UserController;
use Vendor\Package\Response;
use Vendor\Package\Sdk as ServiceSdk;

/**
 * @test
 */
public function saveUser_should_update_the_account_email()
{
  $user_id = $this->factory()->user->create([
    'email' => 'old@example.com',
  ]);

  /*
   * Expect that our code will end up calling ServiceSdk::patch() once with the given args and will
   * return a Response object with status code 200.
   *
   * @link https://phpunit.readthedocs.io/en/9.5/test-doubles.html
   */
  $service = $this->createMock(ServiceSdk::class);
  $service->expects($this->once())
    ->method('patch')
    ->withArgs('/users/' . $user_id, ['email' => 'new@example.com'])
    ->willReturn(new Response(200));

  // Replace the default ServiceSdk instance with our mock.
  $this->container->extend(ServiceSdk::class, function () use ($service) {
    return $service;
  });

  $this->container->get(UserController::class)->update([
    'user'  => $user_id,
    'email' => 'new@example.com',
  ]);
}
```

You may also pass the resolved instance directly into the container with `extend()`:

```diff
   // Replace the default ServiceSdk instance with our mock.
-  $this->container->extend(ServiceSdk::class, function () use ($service) {
-    return $service;
-  });
+  $this->container->extend(ServiceSdk::class, $service);
```

> #### ⏮  Restoring original definitions
> If you need to restore the original definition for an abstract, you may remove its extension(s) using `$container->restore()`.

## Contributing

If you're interested in contributing to the project, please [see our contributing documentation](.github/CONTRIBUTING.md).

## License

Copyright © 2022 StellarWP

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
