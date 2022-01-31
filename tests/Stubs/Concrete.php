<?php

namespace Tests\Stubs;

use StellarWP\Container\Container;

class Concrete extends Container {

    /**
     * References to keys within the config.
     */
    public const EXCEPTION_KEY = 'exception';
    public const INVALID_KEY = 'some-other-key';
    public const NULL_KEY = \DateTime::class;
    public const VALID_KEY = 'some-key';

    /**
	 * {@inheritDoc}
	 */
	public function config() {
        return [
            'some-key' => function () {
                return new \stdClass();
            },
            \DateTime::class => null,
            'exception' => function () {
                throw new \RuntimeException('Something went wrong');
            },
        ];
    }
}
