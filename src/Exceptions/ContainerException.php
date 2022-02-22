<?php

/**
 * Exception thrown when a general error occurs within the container.
 *
 * @package StellarWP\Container
 */

namespace StellarWP\Container\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \RuntimeException implements ContainerExceptionInterface
{
}
