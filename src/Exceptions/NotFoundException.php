<?php

/**
 * Exception thrown when no matching entry was found in the container registry.
 *
 * @package StellarWP\Container
 */

namespace StellarWP\Container\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
