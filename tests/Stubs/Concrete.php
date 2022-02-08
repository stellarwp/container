<?php

namespace Tests\Stubs;

use StellarWP\Container\Container;

class Concrete extends Container
{
    /**
     * References to keys within the config.
     */
    const EXCEPTION_KEY = 'exception';
    const INVALID_KEY   = 'some-other-key';
    const NULL_KEY      = \DateTime::class;
    const VALID_KEY     = 'some-key';

    /**
     * {@inheritDoc}
     */
    public function config()
    {
        return [
            'some-key'       => function () {
                return new \stdClass();
            },
            \DateTime::class => null,
            'exception'      => function () {
                throw new \RuntimeException('Something went wrong');
            },
        ];
    }
}
