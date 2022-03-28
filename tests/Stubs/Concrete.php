<?php

namespace Tests\Stubs;

use StellarWP\Container\Container;

class Concrete extends Container
{
    /**
     * References to keys within the config.
     */
    const ALIAS_KEY            = 'alias';
    const EXCEPTION_KEY        = 'exception';
    const INSTANCE_KEY         = 'instance';
    const INVALID_KEY          = 'some-other-key';
    const NESTED_GET_KEY       = 'nested-get-key';
    const NESTED_MAKE_KEY      = 'nested-make-key';
    const NESTED_UNDEFINED_KEY = 'nested-undefined-key';
    const NULL_KEY             = \DateTime::class;
    const RECURSIVE_KEY        = 'recursion';
    const UNDEFINED_KEY        = 'undefined';
    const VALID_KEY            = 'some-key';

    /**
     * {@inheritDoc}
     */
    public function config()
    {
        return [
            self::VALID_KEY            => function () {
                return new \stdClass();
            },
            self::NESTED_GET_KEY       => function ($app) {
                return new \ArrayObject([
                    'prop' => $app->get(self::VALID_KEY),
                ]);
            },
            self::NESTED_MAKE_KEY      => function ($app) {
                return new \ArrayObject([
                    'prop' => $app->make(self::VALID_KEY),
                ]);
            },
            self::NESTED_UNDEFINED_KEY => function ($app) {
                return new \ArrayObject([
                    'prop' => $app->make(self::UNDEFINED_KEY),
                ]);
            },
            self::NULL_KEY             => null,
            self::ALIAS_KEY            => self::VALID_KEY,
            self::RECURSIVE_KEY        => function ($container) {
                return $container->make(self::RECURSIVE_KEY);
            },
            self::EXCEPTION_KEY        => function () {
                throw new \RuntimeException('Something went wrong');
            },
            self::INSTANCE_KEY         => new \stdClass(),
            self::INVALID_KEY          => [],
            // self::UNDEFINED_KEY should not be in this array.
        ];
    }
}
