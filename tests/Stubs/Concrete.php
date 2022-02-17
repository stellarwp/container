<?php

namespace Tests\Stubs;

use StellarWP\Container\Container;

class Concrete extends Container
{
    /**
     * References to keys within the config.
     */
    const ALIAS_KEY       = 'alias';
    const EXCEPTION_KEY   = 'exception';
    const INVALID_KEY     = 'some-other-key';
    const NESTED_GET_KEY  = 'nested-get-key';
    const NESTED_MAKE_KEY = 'nested-make-key';
    const NULL_KEY        = \DateTime::class;
    const VALID_KEY       = 'some-key';

    /**
     * {@inheritDoc}
     */
    public function config()
    {
        return [
            self::VALID_KEY     => function () {
                return new \stdClass();
            },
            self::NESTED_GET_KEY    => function ($app) {
                return new \ArrayObject($app->get(self::VALID_KEY));
            },
            self::NESTED_MAKE_KEY    => function ($app) {
                return new \ArrayObject($app->make(self::VALID_KEY));
            },
            self::NULL_KEY      => null,
            self::ALIAS_KEY     => 'some-key',
            self::EXCEPTION_KEY => function () {
                throw new \RuntimeException('Something went wrong');
            },
            // self::INVALID_KEY should not be in this array.
        ];
    }
}
